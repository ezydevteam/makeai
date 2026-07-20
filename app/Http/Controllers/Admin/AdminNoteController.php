<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNote;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminNoteController extends Controller
{
    public function index()
    {
        $notes = AdminNote::where('admin_id', auth()->guard('admin')->id())
            ->latest()
            ->get();

        $remindersDue = AdminNote::where('admin_id', auth()->guard('admin')->id())
            ->remindersDue()
            ->get();

        return response()->json([
            'notes' => $notes,
            'remindersDue' => $remindersDue,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reminder_date' => 'nullable|date',
            'auto_delete_date' => 'nullable|date',
        ]);

        $validated['admin_id'] = auth()->guard('admin')->id();

        AdminNote::create($validated);

        return response()->json(['message' => translate('Note created successfully.')]);
    }

    public function update(Request $request, AdminNote $note)
    {
        if ($note->admin_id !== auth()->guard('admin')->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reminder_date' => 'nullable|date',
            'auto_delete_date' => 'nullable|date',
        ]);

        $note->update($validated);

        return response()->json(['message' => translate('Note updated successfully.')]);
     }

    public function snooze(AdminNote $note)
    {
        if ($note->admin_id !== auth()->guard('admin')->id()) {
            abort(403);
        }

        $note->update([
            'reminder_date' => now()->addHour(),
            'reminder_sent' => false,
        ]);

        return response()->json(['message' => translate('Reminder postponed by 1 hour.')]);
    }

    public function dismiss(AdminNote $note)
    {
        if ($note->admin_id !== auth()->guard('admin')->id()) {
            abort(403);
        }

        $note->update([
            'reminder_sent' => true,
        ]);

        return response()->json(['message' => translate('Reminder dismissed.')]);
    }

    public function destroy(AdminNote $note)
    {
        if ($note->admin_id !== auth()->guard('admin')->id()) {
            abort(403);
        }

        $note->delete();

        return response()->json(['message' => translate('Note deleted successfully.')]);
    }
}
