<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportTicketService
{
    public function __construct(private readonly MailService $mail) {}

    public function createTicket(User $user, array $data, array $attachments = []): SupportTicket
    {
        return DB::transaction(function () use ($user, $data, $attachments) {
            $department = SupportDepartment::query()->findOrFail($data['department_id']);
            $assignedAdminId = $this->resolveAssignedAdminId($department);

            $ticket = SupportTicket::create([
                'ticket_number' => $this->nextTicketNumber(),
                'user_id' => $user->id,
                'department_id' => $department->id,
                'assigned_to' => $assignedAdminId,
                'subject' => $data['subject'],
                'priority' => $data['priority'],
                'source' => 'web',
                'last_reply_at' => now(),
                'last_reply_by' => 'user',
                'user_last_read_at' => now(),
            ]);

            $reply = $this->createReply($ticket, 'user', $user->id, $data['message'], false, false, $attachments);
            $ticket->update(['last_reply_at' => $reply->created_at]);

            return $ticket->fresh(['department', 'assignedAdmin', 'replies']);
        });
    }

    public function addUserReply(SupportTicket $ticket, User $user, string $content, array $attachments = []): SupportTicketReply
    {
        return DB::transaction(function () use ($ticket, $user, $content, $attachments) {
            $reply = $this->createReply($ticket, 'user', $user->id, $content, false, false, $attachments);

            $ticket->update([
                'status' => 'open',
                'last_reply_at' => $reply->created_at,
                'last_reply_by' => 'user',
                'user_last_read_at' => now(),
            ]);

            $this->sendUserReplyNotification($ticket, $user);

            return $reply;
        });
    }

    public function addAdminReply(
        SupportTicket $ticket,
        Admin $admin,
        string $content,
        bool $internalNote = false,
        bool $aiDraft = false,
        array $attachments = []
    ): SupportTicketReply {
        return DB::transaction(function () use ($ticket, $admin, $content, $internalNote, $aiDraft, $attachments) {
            $reply = $this->createReply($ticket, 'admin', $admin->id, $content, $internalNote, $aiDraft, $attachments);

            $updates = ['admin_last_read_at' => now()];

            if (! $internalNote) {
                $updates = array_merge($updates, [
                    'status' => 'in_progress',
                    'first_response_at' => $ticket->first_response_at ?: now(),
                    'last_reply_at' => $reply->created_at,
                    'last_reply_by' => 'admin',
                ]);
            }

            $ticket->update($updates);

            if (! $internalNote) {
                $this->sendReplyNotification($ticket);
            }

            return $reply;
        });
    }

    public function updateTicketState(SupportTicket $ticket, array $data): SupportTicket
    {
        $updates = collect($data)->only(['status', 'priority', 'assigned_to', 'department_id'])->all();

        $justResolved = ($updates['status'] ?? null) === 'resolved' && $ticket->status !== 'resolved';

        if ($justResolved) {
            $updates['resolved_at'] = now();
        }

        if (($updates['status'] ?? null) === 'closed' && $ticket->status !== 'closed') {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

        if ($justResolved) {
            $this->sendTicketResolvedNotification($ticket);
        }

        return $ticket->fresh(['department', 'assignedAdmin']);
    }

    public function storeRating(SupportTicket $ticket, int $rating, ?string $comment = null): void
    {
        $ticket->update([
            'satisfaction_rating' => $rating,
            'satisfaction_comment' => $comment,
        ]);
    }

    public function mergeTickets(SupportTicket $source, SupportTicket $target): SupportTicket
    {
        return DB::transaction(function () use ($source, $target) {
            SupportTicketReply::where('ticket_id', $source->id)
                ->update(['ticket_id' => $target->id]);

            SupportTicketAttachment::where('ticket_id', $source->id)
                ->update(['ticket_id' => $target->id]);

            $source->delete();

            $lastReply = SupportTicketReply::where('ticket_id', $target->id)->latest()->first();
            $statuses = SupportTicketReply::where('ticket_id', $target->id)
                ->where('is_internal_note', false)
                ->latest()
                ->value('author_type');

            $target->update([
                'status' => $target->status === 'closed' ? 'open' : $target->status,
                'last_reply_at' => $lastReply?->created_at ?? $target->last_reply_at,
                'last_reply_by' => $statuses ?? $target->last_reply_by,
            ]);

            return $target->fresh(['department', 'assignedAdmin', 'replies']);
        });
    }

    public function nextTicketNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "TKT-{$year}-";
        $last = SupportTicket::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('ticket_number');

        $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function sendNewTicketNotification(SupportTicket $ticket): void
    {
        // Confirm receipt to the customer regardless of the admin-alert setting —
        // that toggle governs who on the team gets paged, not whether the person
        // who opened the ticket hears back.
        $this->sendTicketCreatedConfirmation($ticket);

        if (! (bool) settings('notify_admin_new_ticket', true)) {
            return;
        }

        $recipient = $this->adminRecipient($ticket);

        if (! $recipient) {
            return;
        }

        $ticket->loadMissing(['user', 'assignedAdmin', 'department']);

        $this->mail->send('ticket_created_admin', $recipient, array_merge($this->ticketVariables($ticket, admin: true), [
            'user_name' => $ticket->assignedAdmin?->name ?? 'Support Team',
        ]));
    }

    private function sendTicketCreatedConfirmation(SupportTicket $ticket): void
    {
        $ticket->loadMissing(['user', 'department']);
        $user = $ticket->user;

        if (! $user?->email) {
            return;
        }

        $this->mail->send('ticket_created', $user->email, array_merge($this->ticketVariables($ticket), [
            'user_name' => $user->name,
        ]));
    }

    /**
     * The customer's ticket was resolved. Called from updateTicketState on the
     * pending -> resolved transition only, so re-saving a resolved ticket does
     * not re-send the email.
     */
    private function sendTicketResolvedNotification(SupportTicket $ticket): void
    {
        if (! (bool) settings('notify_user_reply', true)) {
            return;
        }

        $ticket->loadMissing(['user', 'department']);
        $user = $ticket->user;

        if (! $user?->email) {
            return;
        }

        $this->mail->send('ticket_resolved', $user->email, array_merge($this->ticketVariables($ticket), [
            'user_name' => $user->name,
        ]));
    }

    private function sendReplyNotification(SupportTicket $ticket): void
    {
        if (! (bool) settings('notify_user_reply', true)) {
            return;
        }

        $ticket->loadMissing(['user', 'department']);
        $user = $ticket->user;

        if (! $user?->email) {
            return;
        }

        $this->mail->send('ticket_replied', $user->email, array_merge($this->ticketVariables($ticket), [
            'user_name' => $user->name,
        ]));
    }

    private function sendUserReplyNotification(SupportTicket $ticket, User $user): void
    {
        if (! (bool) settings('notify_admin_new_ticket', true)) {
            return;
        }

        $recipient = $this->adminRecipient($ticket);

        if (! $recipient) {
            return;
        }

        $ticket->loadMissing(['assignedAdmin', 'department']);

        $this->mail->send('ticket_replied_admin', $recipient, array_merge($this->ticketVariables($ticket, admin: true), [
            'user_name' => $ticket->assignedAdmin?->name ?? 'Support Team',
            'customer_name' => $user->name,
        ]));
    }

    private function adminRecipient(SupportTicket $ticket): ?string
    {
        return $ticket->assignedAdmin?->email ?: (settings('mail_from_address') ?: null);
    }

    /**
     * Template variables shared by every ticket email. $admin swaps the ticket
     * link to the admin panel, since staff cannot open the customer-facing view.
     */
    private function ticketVariables(SupportTicket $ticket, bool $admin = false): array
    {
        return [
            'ticket_number' => $ticket->ticket_number,
            'ticket_subject' => $ticket->subject,
            'ticket_priority' => ucfirst((string) $ticket->priority),
            'ticket_department' => $ticket->department?->name ?? '—',
            'ticket_url' => $admin
                ? route('admin.support.tickets.show', $ticket)
                : route('user.dashboard.support.tickets.show', $ticket),
            'customer_name' => $ticket->user?->name ?? 'Customer',
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ];
    }

    private function createReply(
        SupportTicket $ticket,
        string $authorType,
        int $authorId,
        string $content,
        bool $internalNote,
        bool $aiDraft,
        array $attachments
    ): SupportTicketReply {
        $reply = $ticket->replies()->create([
            'author_type' => $authorType,
            'author_id' => $authorId,
            'content' => $this->sanitizeContent($content),
            'is_internal_note' => $internalNote,
            'is_ai_draft' => $aiDraft,
        ]);

        $stored = $this->storeAttachments($ticket, $reply, $authorType, $authorId, $attachments);
        $reply->update(['attachments' => $stored]);

        return $reply;
    }

    private function storeAttachments(SupportTicket $ticket, SupportTicketReply $reply, string $authorType, int $authorId, array $attachments): array
    {
        return collect($attachments)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(function (UploadedFile $file) use ($ticket, $reply, $authorType, $authorId) {
                // Private disk: support attachments routinely contain personal data
                // (screenshots, invoices, logs) and must not be reachable by URL.
                // They are served through an authenticated, access-checked route.
                $path = $file->store("support/{$ticket->ticket_number}", 'local');

                $attachment = SupportTicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'reply_id' => $reply->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize() ?: 0,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'uploaded_by_type' => $authorType,
                    'uploaded_by_id' => $authorId,
                    'created_at' => now(),
                ]);

                return [
                    'id' => $attachment->id,
                    'name' => $file->getClientOriginalName(),
                    'url' => route('support.attachments.download', $attachment),
                    'size' => $file->getSize() ?: 0,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                ];
            })
            ->values()
            ->all();
    }

    private function resolveAssignedAdminId(SupportDepartment $department): ?int
    {
        if (! $department->assigned_role_id) {
            return null;
        }

        return Admin::query()
            ->where('role_id', $department->assigned_role_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }

    private function sanitizeContent(string $content): string
    {
        return \App\Services\TiptapHtmlSanitizer::sanitize($content, \App\Services\TiptapHtmlSanitizer::BASIC_TAGS);
    }
}
