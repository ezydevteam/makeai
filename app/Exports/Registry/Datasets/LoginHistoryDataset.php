<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\LoginHistory;
use Illuminate\Database\Eloquent\Builder;

class LoginHistoryDataset extends Dataset
{
    public function key(): string { return 'login-history'; }
    public function label(): string { return translate('Login History'); }
    public function supportedFilters(): array { return ['status', 'user_id', 'date']; }

    public function query(array $filters): Builder
    {
        return LoginHistory::query()
            ->with('user:id,name,email')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('success', $status === 'success'))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('date', translate('Date'), fn ($r) => $r->created_at?->format('Y-m-d H:i')),
            new Column('user', translate('User'), fn ($r) => $r->user?->name ?? translate('N/A')),
            new Column('ip', translate('IP'), fn ($r) => $r->ip),
            new Column('country', translate('Country'), fn ($r) => $r->country),
            new Column('city', translate('City'), fn ($r) => $r->city),
            new Column('success', translate('Success'), fn ($r) => $r->success ? translate('Yes') : translate('No')),
            new Column('user_agent', translate('User Agent'), fn ($r) => $r->user_agent),
        ];
    }

    public function stats(array $filters): array
    {
        return [
            translate('Total') => number_format(LoginHistory::count()),
            translate('Successful') => number_format(LoginHistory::where('success', true)->count()),
            translate('Failed') => number_format(LoginHistory::where('success', false)->count()),
            translate('Unique IPs') => number_format(LoginHistory::distinct('ip')->count('ip')),
        ];
    }
}
