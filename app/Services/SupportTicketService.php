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
use Illuminate\Support\Facades\Storage;
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

        if (($updates['status'] ?? null) === 'resolved' && $ticket->status !== 'resolved') {
            $updates['resolved_at'] = now();
        }

        if (($updates['status'] ?? null) === 'closed' && $ticket->status !== 'closed') {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

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
        if (! (bool) settings('notify_admin_new_ticket', true)) {
            return;
        }

        $assignedAdmin = $ticket->assignedAdmin;
        $recipient = $assignedAdmin?->email ?? settings('mail_from_address');

        if (! $recipient) {
            return;
        }

        $this->mail->send('admin_announcement', $recipient, [
            'user_name' => $assignedAdmin?->name ?? 'Support Team',
            'message' => "New ticket #{$ticket->ticket_number}: {$ticket->subject}",
            'user_email' => $recipient,
        ]);
    }

    private function sendReplyNotification(SupportTicket $ticket): void
    {
        if (! (bool) settings('notify_user_reply', true)) {
            return;
        }

        $user = $ticket->user;

        if (! $user?->email) {
            return;
        }

        $this->mail->send('admin_announcement', $user->email, [
            'user_name' => $user->name,
            'message' => "You have a new reply on your support ticket #{$ticket->ticket_number}: \"{$ticket->subject}\"",
            'user_email' => $user->email,
        ]);
    }

    private function sendUserReplyNotification(SupportTicket $ticket, User $user): void
    {
        if (! (bool) settings('notify_admin_new_ticket', true)) {
            return;
        }

        $assignedAdmin = $ticket->assignedAdmin;
        $recipient = $assignedAdmin?->email ?? settings('mail_from_address');

        if (! $recipient) {
            return;
        }

        $this->mail->send('admin_announcement', $recipient, [
            'user_name' => $assignedAdmin?->name ?? 'Support Team',
            'message' => "New reply from {$user->name} on ticket #{$ticket->ticket_number}: \"{$ticket->subject}\"",
            'user_email' => $recipient,
        ]);
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
                $path = $file->store("support/{$ticket->ticket_number}", 'public');

                SupportTicketAttachment::create([
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
                    'name' => $file->getClientOriginalName(),
                    'url' => Storage::url($path),
                    'path' => $path,
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
