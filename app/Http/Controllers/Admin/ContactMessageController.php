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
            'settings' => $this->getSettingsPayload(),
            'canManageSettings' => auth('admin')->user()?->hasPermission('content.pages') ?? false,
            'openSettings' => $request->boolean('settings'),
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
            settings('mail_from_name', settings('app_name', translate('Application')))
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
            
            try {
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
            } finally {
                fclose($handle);
            }
        }, 'contact-messages.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeContact(): void
    {
        // Note: Ensure 'contact.messages' permission is seeded in the database.
        // Previously used 'content.pages' which was an architectural mismatch.
        abort_unless(auth('admin')->user()?->hasPermission('contact.messages'), 403);
    }

    private function getSettingsPayload(): array
    {
        return [
            'contact_form_enabled' => (bool) settings('contact_form_enabled', true),
            'contact_subject_mode' => settings('contact_subject_mode', 'text'),
            'contact_subject_options' => settings('contact_subject_options', "General Inquiry\nSupport\nBilling\nPartnership"),
            'contact_notification_email' => settings('contact_notification_email', settings('mail_from_address')),
            'contact_success_message' => settings('contact_success_message', 'Your message has been sent successfully. We will get back to you soon!'),
            'contact_auto_reply_enabled' => (bool) settings('contact_auto_reply_enabled', false),
            'contact_auto_reply_subject' => settings('contact_auto_reply_subject', 'We received your message'),
            'contact_auto_reply_message' => settings('contact_auto_reply_message', "Hi {name},\n\nThanks for contacting us. We received your message and will reply soon."),
        ];
    }
}
