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
        $user = User::query()->firstOrFail();
        $history = $ticket->replies
            ->where('is_internal_note', false)
            ->map(fn ($reply) => strtoupper($reply->author_type).': '.strip_tags($reply->content))
            ->implode("\n\n");

        $result = $aiService->complete(
            $user,
            "Draft a helpful support reply.\n\nSubject: {$ticket->subject}\nDepartment: {$ticket->department?->name}\nCustomer: {$ticket->user?->name}\n\nConversation:\n{$history}",
            'You are a concise SaaS support agent. Return clean HTML paragraphs only, no greeting if one already exists.',
            settings('default_ai_provider', 'openai'),
            settings('default_ai_model', 'gpt-4o-mini'),
            ['max_tokens' => 500, 'temperature' => 0.35]
        );

        return response()->json([
            'success' => true,
            'data' => ['content' => strip_tags($result['content'] ?? '', '<p><br><strong><em><ul><ol><li><code><pre><a>')],
            'message' => translate('AI reply suggestion generated.'),
        ]);
    }

    public function destroy(SupportTicket $ticket)
    {
        $this->authorizeSupport();
        $ticket->delete();

        return redirect()->route('admin.support.tickets.index')->with('success', translate('Ticket deleted.'));
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
