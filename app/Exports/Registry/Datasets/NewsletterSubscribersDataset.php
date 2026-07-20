<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscribersDataset extends Dataset
{
    public function key(): string { return 'newsletter-subscribers'; }
    public function label(): string { return translate('Newsletter Subscribers'); }
    public function isAvailable(): bool { return (bool) settings('newsletter_enabled', true); }
    public function supportedFilters(): array { return ['status', 'date']; }

    public function query(array $filters): Builder
    {
        return NewsletterSubscriber::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('email', translate('Email'), fn ($r) => $r->email),
            new Column('name', translate('Name'), fn ($r) => $r->name),
            new Column('status', translate('Status'), fn ($r) => ucfirst((string) $r->status)),
            new Column('subscribed_at', translate('Subscribed At'), fn ($r) => $r->subscribed_at?->format('Y-m-d') ?? '—'),
            new Column('unsubscribed_at', translate('Unsubscribed At'), fn ($r) => $r->unsubscribed_at?->format('Y-m-d') ?? '—'),
            new Column('created', translate('Created'), fn ($r) => $r->created_at?->format('Y-m-d')),
        ];
    }

    public function stats(array $filters): array
    {
        return [
            translate('Total') => number_format(NewsletterSubscriber::count()),
            translate('Confirmed') => number_format(NewsletterSubscriber::whereNotNull('subscribed_at')->count()),
            translate('Unsubscribed') => number_format(NewsletterSubscriber::whereNotNull('unsubscribed_at')->count()),
        ];
    }
}
