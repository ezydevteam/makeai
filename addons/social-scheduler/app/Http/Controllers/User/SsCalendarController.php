<?php

namespace Addons\SocialScheduler\Http\Controllers\User;

use Addons\SocialScheduler\Models\SsScheduledPost;
use Addons\SocialScheduler\Models\SsSocialAccount;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsCalendarController extends Controller
{
    public function index(): \Inertia\Response
    {
        $accounts = SsSocialAccount::where('user_id', auth()->id())->active()->get();

        return inertia('Addons/social-scheduler/User/Calendar', [
            'initial_month' => now()->format('Y-m'),
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'platform' => $a->platform,
                'platform_label' => $a->platform_label,
            ]),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'platforms' => ['nullable', 'array'],
        ]);

        $posts = SsScheduledPost::where('user_id', auth()->id())
            ->forCalendar(Carbon::parse($validated['start']), Carbon::parse($validated['end']))
            ->with(['postPlatforms'])
            ->get(['id', 'ulid', 'title', 'caption', 'platforms', 'status', 'scheduled_at', 'post_type']);

        $events = $posts->map(fn ($p) => [
            'id' => $p->id,
            'ulid' => $p->ulid,
            'title' => $p->title ?? \Illuminate\Support\Str::limit($p->caption, 60),
            'platforms' => $p->platforms,
            'status' => $p->status,
            'post_type' => $p->post_type,
            'scheduled_at' => $p->scheduled_at?->toISOString(),
            'platform_statuses' => $p->postPlatforms->map(fn ($pp) => [
                'platform' => $pp->platform,
                'status' => $pp->status,
            ]),
        ]);

        if (! empty($validated['platforms'])) {
            $events = $events->filter(fn ($e) =>
                ! empty(array_intersect($e['platforms'], $validated['platforms']))
            );
        }

        return response()->json($events->values());
    }

    public function reschedule(Request $request, SsScheduledPost $post): JsonResponse
    {
        abort_if($post->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $post->update(['scheduled_at' => $validated['scheduled_at']]);

        return response()->json([
            'success' => true,
            'scheduled_at' => $post->scheduled_at->toISOString(),
        ]);
    }
}
