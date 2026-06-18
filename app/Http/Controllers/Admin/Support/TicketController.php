<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Support\TicketReplyRequest;
use App\Http\Requests\Admin\Support\TicketStateRequest;
use App\Models\Admin;
use App\Models\SupportCannedResponse;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request)
    {
        $this->authorizeSupport();

        $tickets = SupportTicket::query()
            ->with(['user:id,ulid,name,email', 'department:id,name', 'assignedAdmin:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(fn ($q) => $q
                    ->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')->toString()))
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->integer('department')))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->latest('last_reply_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Support/Tickets', [
            'tickets' => $tickets,
            'departments' => SupportDepartment::orderBy('sort_order')->get(['id', 'name']),
            'admins' => Admin::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'priority', 'department', 'assigned_to']),
            'stats' => $this->stats(),
            'sla' => [
                'first_response_hours' => (int) settings('sla_first_response_hours', 24),
                'resolution_hours' => (int) settings('sla_resolution_hours', 72),
            ],
            'settings' => $this->settingsPayload(),
            'openSettings' => $request->boolean('settings'),
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorizeSupport();
        $ticket->update(['admin_last_read_at' => now()]);
        $ticket->load(['user:id,ulid,name,email', 'department:id,name', 'assignedAdmin:id,name', 'replies']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $this->ticketPayload($ticket),
            'departments' => SupportDepartment::orderBy('sort_order')->get(['id', 'name']),
            'admins' => Admin::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cannedResponses' => SupportCannedResponse::with('department:id,name')
                ->orderBy('title')
                ->get(['id', 'title', 'content', 'department_id']),
            'settings' => [
                'ai_reply_suggestion' => (bool) settings('ai_reply_suggestion', true),
            ],
        ]);
    }

    public function reply(TicketReplyRequest $request, SupportTicket $ticket)
    {
        $this->tickets->addAdminReply(
            $ticket,
            auth('admin')->user(),
            $request->validated('message'),
            (bool) $request->validated('is_internal_note'),
            (bool) ($request->validated('is_ai_draft') ?? false),
            $request->file('attachments', [])
        );

        return back()->with('success', translate('Support reply added.'));
    }

    public function updateState(TicketStateRequest $request, SupportTicket $ticket)
    {
        $this->tickets->updateTicketState($ticket, $request->validated());

        return back()->with('success', translate('Ticket updated.'));
    }

    public function suggestReply(SupportTicket $ticket, AiService $aiService): JsonResponse
    {
        $this->authorizeSupport();
        abort_unless((bool) settings('ai_reply_suggestion', true), 403);

        $ticket->load(['user:id,name', 'department:id,name', 'replies']);
        
        // Use a dedicated system user for internal admin AI operations to prevent leaking customer quotas/context
        $user = User::firstOrCreate(
            ['email' => User::internalAiEmail()],
            [
                'name' => User::internalAiName(),
                'password' => bcrypt(Str::random(32)),
                'is_active' => true,
                'plan_id' => null,
                'subscription_status' => 'active',
            ]
        );

        $history = $ticket->replies
            ->where('is_internal_note', false)
            ->map(fn ($reply) => strtoupper($reply->author_type).': '.strip_tags($reply->content))
            ->implode("\n\n");

        $result = $aiService->complete(
            $user,
            "Draft a helpful support reply.\n\nSubject: {$ticket->subject}\nDepartment: {$ticket->department?->name}\nCustomer: {$ticket->user?->name}\n\nConversation:\n{$history}",
            'You are a concise SaaS support agent. Return clean HTML paragraphs only, no greeting if one already exists.',
            options: ['max_tokens' => 500, 'temperature' => 0.35],
            toolSlug: 'admin_ticket_suggest'
        );

        return response()->json([
            'success' => true,
            'data' => ['content' => strip_tags($result->content ?? '', '<p><br><strong><em><ul><ol><li><code><pre><a>')],
            'message' => translate('AI reply suggestion generated.'),
        ]);
    }

    public function destroy(SupportTicket $ticket)
    {
        $this->authorizeSupport();
        $ticket->delete();

        return redirect()->route('admin.support.tickets.index')->with('success', translate('Ticket deleted.'));
    }

    public function bulkAction(Request $request)
    {
        $this->authorizeSupport();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:support_tickets,id'],
            'action' => ['required', 'string', Rule::in(['assign', 'status', 'priority', 'delete'])],
            'assigned_to' => ['required_if:action,assign', 'nullable', 'integer', 'exists:admins,id'],
            'status' => ['required_if:action,status', 'string', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['required_if:action,priority', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ]);

        $ids = $validated['ids'];

        match ($validated['action']) {
            'assign' => SupportTicket::whereIn('id', $ids)->update(['assigned_to' => $validated['assigned_to'] ?? null]),
            'status' => SupportTicket::whereIn('id', $ids)->get()->each(function (SupportTicket $ticket) use ($validated) {
                $this->tickets->updateTicketState($ticket, ['status' => $validated['status']]);
            }),
            'priority' => SupportTicket::whereIn('id', $ids)->update(['priority' => $validated['priority']]),
            'delete' => SupportTicket::whereIn('id', $ids)->delete(),
        };

        return back()->with('success', translate(':count ticket(s) updated.', ['count' => count($ids)]));
    }

    public function merge(Request $request, SupportTicket $ticket)
    {
        $this->authorizeSupport();

        $validated = $request->validate([
            'target_ticket_number' => ['required', 'string', 'exists:support_tickets,ticket_number'],
        ]);

        abort_if($ticket->ticket_number === $validated['target_ticket_number'], 422, translate('Cannot merge a ticket into itself.'));

        $target = SupportTicket::where('ticket_number', $validated['target_ticket_number'])->firstOrFail();
        $this->tickets->mergeTickets($ticket, $target);

        return redirect()->route('admin.support.tickets.show', $target)->with('success', translate('Tickets merged successfully.'));
    }

    public function toggleUserBan(SupportTicket $ticket)
    {
        $this->authorizeSupport();

        $user = $ticket->user;
        abort_unless($user !== null, 404);

        $user->update([
            'is_banned' => ! $user->is_banned,
            'ban_reason' => $user->is_banned ? null : 'Banned via support ticket #'.$ticket->ticket_number,
        ]);

        return back()->with('success', $user->fresh()->is_banned
            ? translate('User banned from ticket creation.')
            : translate('User unbanned.'));
    }

    public function export(Request $request)
    {
        $this->authorizeSupport();

        $tickets = SupportTicket::query()
            ->with(['user:id,name,email', 'department:id,name', 'assignedAdmin:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(fn ($q) => $q
                    ->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')->toString()))
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->integer('department')))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->latest('last_reply_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="support-tickets-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            
            try {
                fputcsv($handle, ['Ticket #', 'Subject', 'Customer', 'Email', 'Department', 'Assigned To', 'Status', 'Priority', 'Created', 'Resolved At', 'Rating']);

                foreach ($tickets as $ticket) {
                    fputcsv($handle, [
                        $ticket->ticket_number,
                        $ticket->subject,
                        $ticket->user?->name ?? '',
                        $ticket->user?->email ?? '',
                        $ticket->department?->name ?? '',
                        $ticket->assignedAdmin?->name ?? translate('Unassigned'),
                        $ticket->status,
                        $ticket->priority,
                        $ticket->created_at?->toDateTimeString() ?? '',
                        $ticket->resolved_at?->toDateTimeString() ?? '',
                        $ticket->satisfaction_rating ?? '',
                    ]);
                }
            } finally {
                fclose($handle);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    private function stats(): array
    {
        $statusCounts = SupportTicket::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $statusCounts->sum(),
            'open' => (int) ($statusCounts['open'] ?? 0),
            'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
            'waiting_user' => (int) ($statusCounts['waiting_user'] ?? 0),
            'resolved' => (int) ($statusCounts['resolved'] ?? 0),
        ];
    }

    private function settingsPayload(): array
    {
        return [
            'tickets_enabled' => (bool) settings('tickets_enabled', true),
            'max_attachments_per_reply' => (int) settings('max_attachments_per_reply', 5),
            'max_attachment_size_mb' => (int) settings('max_attachment_size_mb', 10),
            'allowed_attachment_types' => settings('allowed_attachment_types', 'jpg,png,gif,pdf,txt,zip,mp4'),
            'auto_close_resolved_days' => (int) settings('auto_close_resolved_days', 7),
            'sla_first_response_hours' => (int) settings('sla_first_response_hours', 24),
            'sla_resolution_hours' => (int) settings('sla_resolution_hours', 72),
            'notify_admin_new_ticket' => (bool) settings('notify_admin_new_ticket', true),
            'notify_user_reply' => (bool) settings('notify_user_reply', true),
            'satisfaction_rating_enabled' => (bool) settings('satisfaction_rating_enabled', true),
            'ai_reply_suggestion' => (bool) settings('ai_reply_suggestion', true),
        ];
    }

    private function ticketPayload(SupportTicket $ticket): array
    {
        $admins = $ticket->replies->where('author_type', 'admin')->pluck('author_id')->unique()->values();
        $users = $ticket->replies->where('author_type', 'user')->pluck('author_id')->unique()->values();
        $adminNames = Admin::whereIn('id', $admins)->pluck('name', 'id');
        $userNames = User::whereIn('id', $users)->pluck('name', 'id');

        return [
            ...$ticket->toArray(),
            'user' => $ticket->user?->only(['ulid', 'name', 'email']),
            'department' => $ticket->department?->only(['id', 'name']),
            'assigned_admin' => $ticket->assignedAdmin?->only(['id', 'name']),
            'replies' => $ticket->replies->map(fn ($reply) => [
                ...$reply->toArray(),
                'author_name' => $reply->author_type === 'admin'
                    ? ($adminNames[$reply->author_id] ?? translate('Support agent'))
                    : ($userNames[$reply->author_id] ?? translate('Customer')),
            ])->values(),
        ];
    }

    private function authorizeSupport(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('support.tickets'), 403);
    }
}
