<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsCampaign;
use App\Models\SmsCampaign;
use App\Models\User;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use App\Support\SmsMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSms();

        return Inertia::render('Admin/Marketing/BulkSms', [
            'gatewayEnabled' => SmsService::fromSettings()->isEnabled(),
            // Only users who can actually be texted AND have opted in are selectable,
            // so an admin can never queue guaranteed failures or unconsented sends.
            'recipients' => $this->eligibleUsers(),
            'campaigns' => SmsCampaign::query()
                ->withCount('recipients')
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn (SmsCampaign $c) => [
                    'id' => $c->id,
                    'message' => $c->message,
                    'action_url' => $c->action_url,
                    'action_label' => $c->action_label,
                    'status' => $c->status,
                    'recipient_count' => $c->recipient_count,
                    'sent_count' => $c->sent_count,
                    'failed_count' => $c->failed_count,
                    'created_at' => $c->created_at?->toISOString(),
                    'finished_at' => $c->finished_at?->toISOString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSms();

        if (! SmsService::fromSettings()->isEnabled()) {
            return back()->with('error', translate('SMS gateway is not enabled.'));
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'action_url' => ['nullable', 'url', 'max:500'],
            // Optional label for the link; with no label the bare URL is appended.
            'action_label' => ['nullable', 'string', 'max:80'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        // Re-check eligibility server-side: the picker list can be stale (a user may
        // have opted out or changed their phone since the page loaded).
        $users = User::query()
            ->whereIn('id', $validated['user_ids'])
            ->get()
            ->filter(fn (User $u) => user_can_receive_bulk_sms($u));

        if ($users->isEmpty()) {
            return back()->with('error', translate('None of the selected users can receive bulk SMS.'));
        }

        $campaign = SmsCampaign::create([
            'message' => $validated['message'],
            'action_url' => $validated['action_url'] ?? null,
            'action_label' => $validated['action_label'] ?? null,
            'recipient_count' => $users->count(),
            'status' => 'draft',
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        foreach ($users as $user) {
            $campaign->recipients()->create([
                'user_id' => $user->id,
                'phone' => PhoneNumber::e164($user->phone, $user->phone_country),
                'status' => 'pending',
            ]);
        }

        SendSmsCampaign::dispatch($campaign->id);

        return back()->with('success', translate('Campaign queued for :count recipients.', [
            'count' => $users->count(),
        ]));
    }

    public function retry(SmsCampaign $campaign): RedirectResponse
    {
        $this->authorizeSms();

        if ($campaign->failed_count < 1) {
            return back()->with('info', translate('This campaign has no failed recipients.'));
        }

        SendSmsCampaign::dispatch($campaign->id, true);

        return back()->with('success', translate('Retrying failed recipients.'));
    }

    public function show(SmsCampaign $campaign): Response
    {
        $this->authorizeSms();

        return Inertia::render('Admin/Marketing/BulkSmsCampaign', [
            'campaign' => [
                'id' => $campaign->id,
                'message' => $campaign->message,
                'action_url' => $campaign->action_url,
                'action_label' => $campaign->action_label,
                'status' => $campaign->status,
                'recipient_count' => $campaign->recipient_count,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'preview' => SmsMessage::summary($campaign->message, $campaign->action_url, $campaign->action_label),
                'created_at' => $campaign->created_at?->toISOString(),
                'finished_at' => $campaign->finished_at?->toISOString(),
            ],
            'recipients' => $campaign->recipients()
                ->with('user:id,name,email')
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->user?->name ?? translate('Deleted user'),
                    'email' => $r->user?->email,
                    'phone' => $r->phone,
                    'status' => $r->status,
                    'error_message' => $r->error_message,
                    'sent_at' => $r->sent_at?->toISOString(),
                ]),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function eligibleUsers(): array
    {
        return User::query()
            ->excludingInternal()
            ->whereNotNull('phone')
            ->whereNotNull('phone_verified_at')
            ->where('sms_marketing_opt_in', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'phone_country'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => PhoneNumber::e164($u->phone, $u->phone_country) ?? $u->phone,
            ])
            ->all();
    }

    private function authorizeSms(): void
    {
        abort_unless(
            auth('admin')->user()?->hasAnyPermission(['marketing.newsletter', 'users.manage']),
            403
        );
    }
}
