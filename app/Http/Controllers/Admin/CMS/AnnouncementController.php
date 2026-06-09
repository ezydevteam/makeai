<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\NotificationEventService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAnnouncements();

        $query = Announcement::query()->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('content', 'like', "%{$search}%"));
        }

        return Inertia::render('Admin/CMS/Announcements/Index', [
            'announcements' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['search']),
            'totalCount' => Announcement::count(),
            'activeCount' => Announcement::where('is_active', true)->count(),
            'topbarCount' => Announcement::where('type', 'topbar')->count(),
            'popupCount' => Announcement::where('type', 'popup')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAnnouncements();

        $validated = $request->validate([
            'type' => 'required|in:topbar,popup,notification',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'bg_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:500',
            'target_audience' => 'required|in:all,guests,auth,free,pro',
            'trigger_type' => 'nullable|string|max:50',
            'trigger_value' => 'nullable|string|max:50',
            'show_frequency' => 'required|in:always,session,once',
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['created_by'] = auth('admin')->id();
        $validated['content'] = $this->sanitizeContent($validated['content'] ?? '');

        $announcement = Announcement::create($validated);

        if ($announcement->type === 'notification' && $announcement->is_active) {
            $this->sendNotification($announcement);
        }

        return back()->with('success', translate('Announcement created successfully.'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorizeAnnouncements();

        $validated = $request->validate([
            'type' => 'required|in:topbar,popup,notification',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'bg_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:500',
            'target_audience' => 'required|in:all,guests,auth,free,pro',
            'trigger_type' => 'nullable|string|max:50',
            'trigger_value' => 'nullable|string|max:50',
            'show_frequency' => 'required|in:always,session,once',
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['content'] = $this->sanitizeContent($validated['content'] ?? '');
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
        abort_unless(auth('admin')->user()?->hasPermission('content.pages'), 403);
    }
}
