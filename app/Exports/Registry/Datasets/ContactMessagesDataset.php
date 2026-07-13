<?php

namespace App\Exports\Registry\Datasets;

use App\Exports\Registry\Column;
use App\Exports\Registry\Dataset;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ContactMessagesDataset extends Dataset
{
    public function key(): string { return 'contact-messages'; }
    public function label(): string { return translate('Contact Submissions'); }
    public function isAvailable(): bool { return (bool) settings('contact_enabled', true); }
    public function supportedFilters(): array { return ['status', 'date']; }

    public function query(array $filters): Builder
    {
        return ContactMessage::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_read', $status === 'read'))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest();
    }

    public function columns(): array
    {
        return [
            new Column('name', translate('Name'), fn ($r) => $r->name),
            new Column('email', translate('Email'), fn ($r) => $r->email),
            new Column('subject', translate('Subject'), fn ($r) => $r->subject),
            new Column('message', translate('Message'), fn ($r) => Str::limit((string) $r->message, 200)),
            new Column('read', translate('Read'), fn ($r) => $r->is_read ? translate('Yes') : translate('No')),
            new Column('replied_at', translate('Replied At'), fn ($r) => $r->replied_at?->format('Y-m-d H:i') ?? '—'),
            new Column('created', translate('Created'), fn ($r) => $r->created_at?->format('Y-m-d H:i')),
        ];
    }

    public function stats(array $filters): array
    {
        return [
            translate('Total') => number_format(ContactMessage::count()),
            translate('Unread') => number_format(ContactMessage::where('is_read', false)->count()),
            translate('Replied') => number_format(ContactMessage::whereNotNull('replied_at')->count()),
        ];
    }
}
