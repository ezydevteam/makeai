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
                'status' => in_array($ticket->status, ['resolved', 'closed'], true) ? 'open' : 'waiting_user',
                'last_reply_at' => $reply->created_at,
                'last_reply_by' => 'user',
                'user_last_read_at' => now(),
            ]);

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
        $html = strip_tags($content, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><code><pre><a><img>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s+(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>\s]*(\2)?/i', '', $html) ?? '';

        return $html;
    }
}
