<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = Announcement::orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/CMS/Announcements/Index', [
            'announcements' => $announcements,
        ]);
    }

    public function store(Request $request)
    {
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

        $validated['created_by'] = auth()->id();

        Announcement::create($validated);

        return back()->with('success', 'Announcement created successfully.');
    }

    public function update(Request $request, Announcement $announcement)
    {
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

        $announcement->update($validated);

        return back()->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted successfully.');
    }

    public function toggleActive(Announcement $announcement)
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        return back()->with('success', 'Announcement status updated.');
    }
}
