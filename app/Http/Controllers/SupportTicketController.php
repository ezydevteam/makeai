<?php

namespace App\Http\Controllers;

use App\Http\Requests\Support\RateTicketRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Models\Admin;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InAppNotificationService;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request)
    {
        abort_unless((bool) settings('tickets_enabled', true), 404);

        $tickets = SupportTicket::query()
            ->with(['department:id,name', 'assignedAdmin:id,name'])
            ->where('user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('last_reply_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status']),
            'departments' => SupportDepartment::active()->orderBy('sort_order')->get(['id', 'name']),
            'settings' => $this->settings(),
        ]);
    }

    public function store(StoreTicketRequest $request)
    {
        $ticket = $this->tickets->createTicket(
            $request->user(),
            $request->validated(),
            $request->file('attachments', [])
        );

        $ticket->loadMissing(['department.assignedRole', 'user']);
        app(InAppNotificationService::class)->notifyAdmins([
            'title' => translate('New support ticket'),
            'message' => translate(':subject from :user', [
                'subject' => $ticket->subject,
                'user' => $ticket->user?->name ?? $request->user()->name,
            ]),
            'level' => $ticket->priority === 'urgent' ? 'warning' : 'info',
            'category' => 'support',
            'action_url' => route('admin.support.tickets.show', $ticket),
            'action_label' => translate('View ticket'),
        ], $ticket->department?->assignedRole?->slug);

        $this->tickets->sendNewTicketNotification($ticket);

        return redirect()->route('support.tickets.show', $ticket)->with('success', translate('Support ticket created.'));
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        abort_unless((bool) settings('tickets_enabled', true), 404);

        $ticket->update(['user_last_read_at' => now()]);
        $ticket->load(['department:id,name', 'assignedAdmin:id,name', 'replies']);

        return Inertia::render('Support/Show', [
            'ticket' => $this->ticketPayload($ticket, false),
            'userLastReadAt' => $ticket->user_last_read_at?->toISOString() ?? $ticket->created_at->toISOString(),
            'settings' => $this->settings(),
        ]);
    }

    public function reply(ReplyTicketRequest $request, SupportTicket $ticket)
    {
        $this->tickets->addUserReply(
            $ticket,
            $request->user(),
            $request->validated('message'),
            $request->file('attachments', [])
        );

        return back()->with('success', translate('Reply added.'));
    }

    public function resolve(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        abort_if($ticket->status === 'closed', 422, translate('Closed tickets cannot be changed.'));

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return back()->with('success', translate('Ticket marked as resolved.'));
    }

    public function rate(RateTicketRequest $request, SupportTicket $ticket)
    {
        $this->tickets->storeRating(
            $ticket,
            (int) $request->validated('rating'),
            $request->validated('comment')
        );

        return back()->with('success', translate('Thanks for rating this support ticket.'));
    }

    private function ticketPayload(SupportTicket $ticket, bool $includeInternal): array
    {
        $admins = $ticket->replies->where('author_type', 'admin')->pluck('author_id')->unique()->values();
        $users = $ticket->replies->where('author_type', 'user')->pluck('author_id')->unique()->values();
        $adminNames = Admin::whereIn('id', $admins)->pluck('name', 'id');
        $userNames = User::whereIn('id', $users)->pluck('name', 'id');

        return [
            ...$ticket->toArray(),
            'department' => $ticket->department?->only(['id', 'name']),
            'assigned_admin' => $ticket->assignedAdmin?->only(['id', 'name']),
            'replies' => $ticket->replies
                ->filter(fn ($reply) => $includeInternal || ! $reply->is_internal_note)
                ->map(fn ($reply) => [
                    ...$reply->toArray(),
                    'author_name' => $reply->author_type === 'admin'
                        ? ($adminNames[$reply->author_id] ?? translate('Support agent'))
                        : ($userNames[$reply->author_id] ?? translate('Customer')),
                ])
                ->values(),
        ];
    }

    private function settings(): array
    {
        return [
            'max_attachments_per_reply' => (int) settings('max_attachments_per_reply', 5),
            'max_attachment_size_mb' => (int) settings('max_attachment_size_mb', 10),
            'allowed_attachment_types' => settings('allowed_attachment_types', 'jpg,png,gif,pdf,txt,zip,mp4'),
            'satisfaction_rating_enabled' => (bool) settings('satisfaction_rating_enabled', true),
        ];
    }
}
