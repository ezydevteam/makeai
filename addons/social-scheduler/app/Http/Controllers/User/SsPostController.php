<?php

namespace Addons\SocialScheduler\Http\Controllers\User;

use Addons\SocialScheduler\Models\SsScheduledPost;
use Addons\SocialScheduler\Models\SsSocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SsPostController extends Controller
{
    public function index(): \Inertia\Response
    {
        $posts = SsScheduledPost::where('user_id', auth()->id())
            ->with(['postPlatforms', 'media'])
            ->latest()
            ->paginate(20);

        return inertia('Addons/social-scheduler/User/Posts/Index', [
            'posts' => $posts->through(fn ($p) => $this->formatPost($p)),
        ]);
    }

    public function create(): \Inertia\Response
    {
        $accounts = SsSocialAccount::where('user_id', auth()->id())->active()->get();

        return inertia('Addons/social-scheduler/User/Posts/Composer', [
            'post' => null,
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'platform' => $a->platform,
                'platform_label' => $a->platform_label,
                'platform_username' => $a->platform_username,
            ]),
            'approval_required' => (bool) addon_setting('social-scheduler', 'approval_required', false),
            'max_media_mb' => (int) addon_setting('social-scheduler', 'max_media_mb', 50),
            'carousel_max_slides' => (int) addon_setting('social-scheduler', 'carousel_max_slides', 10),
            'first_comment_enabled' => (bool) addon_setting('social-scheduler', 'first_comment_enabled', true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'caption' => ['required', 'string', 'max:5000'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'in:instagram,facebook,twitter,linkedin'],
            'post_type' => ['required', 'in:single,carousel,thread,story,reel'],
            'title' => ['nullable', 'string', 'max:255'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
            'first_comment' => ['nullable', 'string', 'max:2200'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'ss_campaign_id' => ['nullable', 'exists:ss_campaigns,id'],
            'platform_overrides' => ['nullable', 'array'],
            'media' => ['nullable', 'array'],
            'media.*' => ['file', 'image', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        DB::transaction(function () use ($validated) {
            $postData = [
                'user_id' => auth()->id(),
                'caption' => $validated['caption'],
                'platforms' => $validated['platforms'],
                'post_type' => $validated['post_type'],
                'title' => $validated['title'] ?? null,
                'hashtags' => $validated['hashtags'] ?? null,
                'first_comment' => $validated['first_comment'] ?? null,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'ss_campaign_id' => $validated['ss_campaign_id'] ?? null,
                'platform_overrides' => $validated['platform_overrides'] ?? null,
                'status' => $validated['scheduled_at'] ? 'scheduled' : 'draft',
            ];

            $post = SsScheduledPost::create($postData);

            foreach ($validated['platforms'] as $platform) {
                $account = SsSocialAccount::where('user_id', auth()->id())
                    ->where('platform', $platform)
                    ->active()
                    ->first();

                if ($account) {
                    $post->postPlatforms()->create([
                        'ss_social_account_id' => $account->id,
                        'platform' => $platform,
                        'status' => 'pending',
                    ]);
                }
            }

            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $index => $file) {
                    $path = $file->store('social-media/' . auth()->id(), 'public');
                    $post->media()->create([
                        'type' => Str::startsWith($file->getMimeType(), 'video') ? 'video' : 'image',
                        'path' => $path,
                        'url' => Storage::disk('public')->url($path),
                        'mime_type' => $file->getMimeType(),
                        'file_size_bytes' => $file->getSize(),
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        return redirect()->route('addon.social.user.posts.index')
            ->with('flash', translate('Post created.'));
    }

    public function edit(SsScheduledPost $post): \Inertia\Response
    {
        abort_if($post->user_id !== auth()->id(), 403);
        abort_if(in_array($post->status, ['publishing', 'published']), 403, 'Cannot edit a published post.');

        $accounts = SsSocialAccount::where('user_id', auth()->id())->active()->get();

        return inertia('Addons/social-scheduler/User/Posts/Composer', [
            'post' => [
                'id' => $post->id,
                'ulid' => $post->ulid,
                'title' => $post->title,
                'caption' => $post->caption,
                'hashtags' => $post->hashtags,
                'platforms' => $post->platforms,
                'post_type' => $post->post_type,
                'status' => $post->status,
                'scheduled_at' => $post->scheduled_at?->toISOString(),
                'first_comment' => $post->first_comment,
                'platform_overrides' => $post->platform_overrides,
                'ss_campaign_id' => $post->ss_campaign_id,
                'media' => $post->media->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => $m->url,
                    'type' => $m->type,
                    'sort_order' => $m->sort_order,
                ]),
            ],
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'platform' => $a->platform,
                'platform_label' => $a->platform_label,
                'platform_username' => $a->platform_username,
            ]),
            'approval_required' => (bool) addon_setting('social-scheduler', 'approval_required', false),
            'max_media_mb' => (int) addon_setting('social-scheduler', 'max_media_mb', 50),
            'carousel_max_slides' => (int) addon_setting('social-scheduler', 'carousel_max_slides', 10),
            'first_comment_enabled' => (bool) addon_setting('social-scheduler', 'first_comment_enabled', true),
        ]);
    }

    public function update(Request $request, SsScheduledPost $post): RedirectResponse
    {
        abort_if($post->user_id !== auth()->id(), 403);
        abort_if(in_array($post->status, ['publishing', 'published']), 403);

        $validated = $request->validate([
            'caption' => ['required', 'string', 'max:5000'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'in:instagram,facebook,twitter,linkedin'],
            'post_type' => ['required', 'in:single,carousel,thread,story,reel'],
            'title' => ['nullable', 'string', 'max:255'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
            'first_comment' => ['nullable', 'string', 'max:2200'],
            'scheduled_at' => ['nullable', 'date'],
            'ss_campaign_id' => ['nullable', 'exists:ss_campaigns,id'],
            'platform_overrides' => ['nullable', 'array'],
        ]);

        $post->update($validated);

        return redirect()->route('addon.social.user.posts.index')
            ->with('flash', translate('Post updated.'));
    }

    public function destroy(SsScheduledPost $post): RedirectResponse
    {
        abort_if($post->user_id !== auth()->id(), 403);
        abort_if($post->status === 'publishing', 403, 'Cannot delete a post currently being published.');

        foreach ($post->media as $media) {
            Storage::disk('public')->delete($media->path);
        }

        $post->delete();

        return back()->with('flash', translate('Post deleted.'));
    }

    private function formatPost(SsScheduledPost $post): array
    {
        return [
            'id' => $post->id,
            'ulid' => $post->ulid,
            'title' => $post->title,
            'caption' => Str::limit($post->caption, 120),
            'platforms' => $post->platforms,
            'status' => $post->status,
            'status_label' => $post->status_label,
            'post_type' => $post->post_type,
            'is_overdue' => $post->is_overdue,
            'scheduled_at' => $post->scheduled_at?->toISOString(),
            'published_at' => $post->published_at?->toISOString(),
            'media' => $post->media->map(fn ($m) => ['url' => $m->url, 'type' => $m->type]),
            'platform_statuses' => $post->postPlatforms->map(fn ($pp) => [
                'platform' => $pp->platform,
                'status' => $pp->status,
            ]),
            'created_at' => $post->created_at->toISOString(),
        ];
    }
}
