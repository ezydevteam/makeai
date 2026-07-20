<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

use App\Http\Requests\Support\RateTicketRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Models\Admin;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use App\Services\InAppNotificationService;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('last_reply_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('User/Support/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'search']),
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

        return redirect()->route('user.dashboard.support.tickets.show', $ticket)->with('success', translate('Support ticket created.'));
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        abort_unless((bool) settings('tickets_enabled', true), 404);

        $ticket->update(['user_last_read_at' => now()]);
        $ticket->load(['department:id,name', 'assignedAdmin:id,name', 'replies']);

        return Inertia::render('User/Support/Show', [
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

    /**
     * Stream a support attachment from the private disk. Accessible to the
     * ticket owner or to an admin holding the support.tickets permission — the
     * files themselves are never publicly reachable.
     */
    public function downloadAttachment(SupportTicketAttachment $attachment): StreamedResponse
    {
        $ticket = $attachment->ticket;
        abort_unless($ticket !== null, 404);

        $admin = auth('admin')->user();
        $user = auth('web')->user();

        $allowed = ($admin && $admin->hasAnyPermission(['support.tickets']))
            || ($user && $ticket->user_id === $user->id);

        abort_unless($allowed, 403);

        // Prefer the private disk; fall back to public for any pre-existing files.
        foreach (['local', 'public'] as $disk) {
            if ($attachment->file_path && Storage::disk($disk)->exists($attachment->file_path)) {
                return Storage::disk($disk)->download($attachment->file_path, $attachment->file_name);
            }
        }

        abort(404);
    }

    private function ticketPayload(SupportTicket $ticket, bool $includeInternal): array
    {
        $admins = $ticket->replies->where('author_type', 'admin')->pluck('author_id')->unique()->values();
        $users = $ticket->replies->where('author_type', 'user')->pluck('author_id')->unique()->values();
        $adminData = Admin::whereIn('id', $admins)->get(['id', 'name', 'avatar'])->keyBy('id');
        $userData = User::whereIn('id', $users)->get(['id', 'name', 'avatar'])->keyBy('id');

        return [
            ...$ticket->toArray(),
            'department' => $ticket->department?->only(['id', 'name']),
            'assigned_admin' => $ticket->assignedAdmin?->only(['id', 'name']),
            'replies' => $ticket->replies
                ->filter(fn ($reply) => $includeInternal || ! $reply->is_internal_note)
                ->map(fn ($reply) => [
                    ...$reply->toArray(),
                    'author_name' => $reply->author_type === 'admin'
                        ? ($adminData[$reply->author_id]?->name ?? translate('Support agent'))
                        : ($userData[$reply->author_id]?->name ?? translate('Customer')),
                    'author_avatar' => $reply->author_type === 'admin'
                        ? ($adminData[$reply->author_id]?->avatar ?? null)
                        : ($userData[$reply->author_id]?->avatar ?? null),
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
