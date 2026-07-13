<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\User;
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
            ->select(['id', 'name', 'email', 'is_active', 'credits', 'plan_id', 'created_at'])
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('name', translate('Name'), fn ($u) => $u->name),
            new Column('email', translate('Email'), fn ($u) => $u->email),
            new Column('active', translate('Active'), fn ($u) => $u->is_active ? translate('Yes') : translate('No')),
            new Column('plan', translate('Plan'), fn ($u) => $u->plan?->name ?? translate('Free')),
            new Column('credits', translate('Credits'), fn ($u) => number_format((float) $u->credits, 2)),
            new Column('joined', translate('Joined'), fn ($u) => $u->created_at?->format('Y-m-d')),
        ];
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
