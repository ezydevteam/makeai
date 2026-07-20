<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\NotificationEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAnnouncements();

        $query = Announcement::query()->with('creator:id,name')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('content', 'like', "%{$search}%"));
        }

        if ($request->filled('audience') && $request->input('audience') !== 'all') {
            $query->where('target_audience', $request->input('audience'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        return Inertia::render('Admin/Marketing/Announcements', [
            'announcements' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['search', 'audience', 'status']),
            'totalCount' => Announcement::count(),
            'activeCount' => Announcement::where('is_active', true)->count(),
            'topbarCount' => Announcement::where('type', 'topbar')->count(),
            'popupCount' => Announcement::where('type', 'popup')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAnnouncements();

        $validated = $request->validate($this->validationRules());

        $validated['created_by'] = auth('admin')->id();
        $validated['content'] = $this->sanitizeContent($validated['content'] ?? '');
        $validated['image'] = $this->resolveImage($request, null);
        unset($validated['image_file']);

        $announcement = Announcement::create($validated);

        if ($announcement->type === 'notification' && $announcement->is_active) {
            $this->sendNotification($announcement);
        }

        return back()->with('success', translate('Announcement created successfully.'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorizeAnnouncements();

        $validated = $request->validate($this->validationRules());

        $validated['content'] = $this->sanitizeContent($validated['content'] ?? '');
        $validated['image'] = $this->resolveImage($request, $announcement);
        unset($validated['image_file']);
        $wasActive = $announcement->type === 'notification' && $announcement->is_active;

        $announcement->update($validated);

        $isNowNotification = $announcement->type === 'notification' && $announcement->is_active;
        if ($isNowNotification && ! $wasActive) {
            $this->sendNotification($announcement);
        }

        return back()->with('success', translate('Announcement updated successfully.'));
    }

    public function destroy(Announcement $announcement)
    {
        $this->authorizeAnnouncements();
        $announcement->delete();

        return back()->with('success', translate('Announcement deleted successfully.'));
    }

    public function toggleActive(Announcement $announcement)
    {
        $this->authorizeAnnouncements();
        $announcement->update(['is_active' => ! $announcement->is_active]);

        return back()->with('success', translate('Announcement status updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        return [
            'type' => 'required|in:topbar,popup,bottom_popup,notification',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'bg_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => ['nullable', 'string', 'max:500', 'not_regex:/^\s*(?:javascript|data|vbscript):/i'],
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'target_audience' => 'required|in:all,guests,auth,free,pro',
            'trigger_type' => 'nullable|string|max:50',
            'trigger_value' => 'nullable|string|max:50',
            'show_frequency' => 'required|in:always,session,once',
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ];
    }

    /**
     * Resolve the stored image key. A freshly uploaded file wins and replaces any
     * previous upload; otherwise the submitted string (URL or existing key) is kept.
     */
    private function resolveImage(Request $request, ?Announcement $announcement): ?string
    {
        if ($request->hasFile('image_file')) {
            $this->deleteUploadedImage($announcement?->image);

            // Store the RELATIVE disk key; the view resolves it with mediaUrl() so the
            // reference keeps working when the admin switches to a cloud storage driver.
            return store_public_upload($request->file('image_file'), 'announcements');
        }

        $image = $request->input('image');

        return $image !== null && $image !== '' ? $image : ($announcement?->image);
    }

    /**
     * Remove a previously uploaded image from the media disk. An external URL is not on
     * our disk (media_path returns it unchanged) so it is left untouched.
     */
    private function deleteUploadedImage(?string $image): void
    {
        if (! $image || preg_match('/^(https?:)?\/\//i', $image)) {
            return;
        }

        $key = media_path($image);

        if ($key && Storage::disk('public')->exists($key)) {
            Storage::disk('public')->delete($key);
        }
    }

    private function sanitizeContent(?string $content): string
    {
        return \App\Services\TiptapHtmlSanitizer::sanitize(
            (string) $content,
            \App\Services\TiptapHtmlSanitizer::BASIC_TAGS
        );
    }

    private function sendNotification(Announcement $announcement): void
    {
        app(NotificationEventService::class)->adminAnnouncement(
            $announcement->title ?: translate('Announcement'),
            strip_tags((string) $announcement->content),
            $announcement->target_audience,
            $announcement->cta_url
        );
    }

    private function authorizeAnnouncements(): void
    {
        if (! auth('admin')->user()?->hasPermission('content.pages')) {
            abort(403, translate('Unauthorized.'));
        }
    }
}
