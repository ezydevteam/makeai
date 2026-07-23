<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\User;
use App\Support\CountryCatalog;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;

class UsersDataset extends Dataset
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return translate('Users');
    }

    public function supportedFilters(): array
    {
        return ['status', 'plan_id', 'date'];
    }

    public function query(array $filters): Builder
    {
        return User::query()
            ->with('plan:id,name')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->when($filters['plan_id'] ?? null, fn ($q, $planId) => $q->where('plan_id', $planId))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->select(['id', 'name', 'email', 'phone', 'phone_country', 'country', 'timezone', 'is_active', 'credits', 'credits_used_today', 'credits_used_month', 'plan_id', 'created_at'])
            ->latest();
    }

    public function columns(): array
    {
        return array_values(array_filter([
            new Column('name', translate('Name'), fn ($u) => $u->name),
            new Column('email', translate('Email'), fn ($u) => $u->email),
            // Dialable E.164 form (falls back to the stored national number).
            new Column('phone', translate('Phone'), fn ($u) => PhoneNumber::e164($u->phone, $u->phone_country) ?? $u->phone),
            new Column('country', translate('Country'), fn ($u) => CountryCatalog::countryName($u->country) ?? $u->country),
            new Column('timezone', translate('Timezone'), fn ($u) => $u->timezone),
            new Column('active', translate('Active'), fn ($u) => $u->is_active ? translate('Yes') : translate('No')),
            // Plans/billing only exist on the Extended license — omit the column entirely
            // on a Regular license, where every user is on the Free plan.
            is_regular_license() ? null : new Column('plan', translate('Plan'), fn ($u) => $u->plan?->name ?? translate('Free')),
            new Column('credits', translate('Credits'), fn ($u) => number_format((float) $u->credits, 2)),
            new Column('daily_usage', translate('Daily Usage'), fn ($u) => number_format((float) $u->credits_used_today, 2)),
            new Column('monthly_usage', translate('Monthly Usage'), fn ($u) => number_format((float) $u->credits_used_month, 2)),
            new Column('joined', translate('Joined'), fn ($u) => $u->created_at?->format('Y-m-d')),
        ]));
    }

    public function stats(array $filters): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        return [
            translate('Total Users') => number_format(User::count()),
            translate('New This Period') => number_format(
                User::when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                    ->count()
            ),
            translate('Active') => number_format(User::where('is_active', true)->count()),
            translate('Pro Subscribers') => number_format(User::whereHas('plan')->count()),
        ];
    }
}
