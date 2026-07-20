<?php

namespace Addons\SocialScheduler\Http\Controllers\User;

use Addons\SocialScheduler\Models\SsRssFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsRssFeedController extends Controller
{
    public function index(): \Inertia\Response
    {
        $feeds = SsRssFeed::where('user_id', auth()->id())->latest()->get();

        return inertia('Addons/social-scheduler/User/RssFeeds', [
            'feeds' => $feeds->map(fn ($f) => [
                'id' => $f->id,
                'url' => $f->url,
                'title' => $f->title,
                'platforms' => $f->platforms,
                'status' => $f->status,
                'caption_prompt' => $f->caption_prompt,
                'last_polled_at' => $f->last_polled_at?->toISOString(),
                'last_error' => $f->last_error,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'in:instagram,facebook,twitter,linkedin'],
            'caption_prompt' => ['nullable', 'string', 'max:1000'],
        ]);

        SsRssFeed::create([
            'user_id' => auth()->id(),
            'url' => $validated['url'],
            'platforms' => $validated['platforms'],
            'caption_prompt' => $validated['caption_prompt'] ?? null,
            'status' => 'active',
        ]);

        return back()->with('flash', translate('RSS feed added.'));
    }

    public function update(Request $request, SsRssFeed $rssFeed): RedirectResponse
    {
        abort_if($rssFeed->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'in:instagram,facebook,twitter,linkedin'],
            'caption_prompt' => ['nullable', 'string', 'max:1000'],
            'status' => ['in:active,paused'],
        ]);

        $rssFeed->update($validated);

        return back()->with('flash', translate('RSS feed updated.'));
    }

    public function destroy(SsRssFeed $rssFeed): RedirectResponse
    {
        abort_if($rssFeed->user_id !== auth()->id(), 403);
        $rssFeed->delete();

        return back()->with('flash', translate('RSS feed removed.'));
    }
}
