<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactReplyRequest;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeContact();

        $messages = ContactMessage::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->string('status')->toString() === 'unread') {
                    $query->where('is_read', false);
                }

                if ($request->string('status')->toString() === 'read') {
                    $query->where('is_read', true);
                }
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Contact/Messages', [
            'messages' => $messages,
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => ContactMessage::count(),
                'unread' => ContactMessage::where('is_read', false)->count(),
                'replied' => ContactMessage::whereNotNull('replied_at')->count(),
            ],
        ]);
    }

    public function markRead(ContactMessage $message)
    {
        $this->authorizeContact();

        $message->update(['is_read' => true]);

        return back()->with('success', translate('Message marked as read.'));
    }

    public function reply(ContactReplyRequest $request, ContactMessage $message)
    {
        $validated = $request->validated();

        Mail::to($message->email)->queue(new ContactMessageMail(
            $validated['subject'],
            $validated['message'],
            settings('mail_from_address'),
            settings('mail_from_name', settings('app_name', 'MakeAI'))
        ));

        $message->update([
            'is_read' => true,
            'replied_at' => now(),
        ]);

        return back()->with('success', translate('Reply queued for sending.'));
    }

    public function destroy(ContactMessage $message)
    {
        $this->authorizeContact();

        $message->delete();

        return back()->with('success', translate('Message deleted.'));
    }

    public function export(): StreamedResponse
    {
        $this->authorizeContact();

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'email', 'subject', 'message', 'ip_address', 'is_read', 'replied_at', 'created_at']);

            ContactMessage::orderByDesc('created_at')->chunk(200, function ($messages) use ($handle) {
                foreach ($messages as $message) {
                    fputcsv($handle, [
                        $message->name,
                        $message->email,
                        $message->subject,
                        $message->message,
                        $message->ip_address,
                        $message->is_read ? 'yes' : 'no',
                        optional($message->replied_at)->toDateTimeString(),
                        optional($message->created_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 'contact-messages.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeContact(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('content.pages'), 403);
    }
}
