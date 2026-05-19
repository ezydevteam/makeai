<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    /**
     * Store a new contact message.
     */
    public function store(Request $request)
    {
        if (! settings('contact_form_enabled', true)) {
            return back()->with('error', translate('Contact form is currently disabled.'));
        }

        $executed = RateLimiter::attempt(
            'contact-submission:'.$request->ip(),
            3,
            function () use ($request) {
                if (filled($request->input('website'))) {
                    return;
                }

                $subjectOptions = $this->subjectOptions();
                $subjectRules = settings('contact_subject_mode', 'text') === 'dropdown' && $subjectOptions !== []
                    ? ['nullable', 'string', 'max:255', Rule::in($subjectOptions)]
                    : ['nullable', 'string', 'max:255'];

                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|max:255',
                    'subject' => $subjectRules,
                    'message' => 'required|string|max:5000',
                    'website' => 'nullable|string|max:0',
                ]);

                unset($validated['website']);

                $message = ContactMessage::create(array_merge($validated, [
                    'ip_address' => $request->ip(),
                ]));

                $this->queueNotification($message);
                $this->queueAutoReply($message);
            },
            3600 // 1 hour
        );

        if (! $executed) {
            return back()->with('error', translate('Too many messages. Please try again later.'));
        }

        return back()->with('success', settings('contact_success_message', translate('Your message has been sent successfully. We will get back to you soon!')));
    }

    private function subjectOptions(): array
    {
        return collect(explode("\n", (string) settings('contact_subject_options', '')))
            ->map(fn ($subject) => trim($subject))
            ->filter()
            ->values()
            ->all();
    }

    private function queueNotification(ContactMessage $message): void
    {
        $recipient = settings('contact_notification_email');

        if (! $recipient) {
            return;
        }

        Mail::to($recipient)->queue(new ContactMessageMail(
            translate('New contact message: :subject', ['subject' => $message->subject ?: translate('No subject')]),
            "Name: {$message->name}\nEmail: {$message->email}\nSubject: {$message->subject}\n\n{$message->message}",
            $message->email,
            $message->name
        ));
    }

    private function queueAutoReply(ContactMessage $message): void
    {
        if (! settings('contact_auto_reply_enabled', false)) {
            return;
        }

        $body = strtr((string) settings('contact_auto_reply_message', ''), [
            '{name}' => $message->name,
            '{email}' => $message->email,
            '{subject}' => $message->subject ?? '',
        ]);

        if (blank($body)) {
            return;
        }

        Mail::to($message->email)->queue(new ContactMessageMail(
            settings('contact_auto_reply_subject', translate('We received your message')),
            $body,
            settings('contact_notification_email'),
            settings('app_name', 'MakeAI')
        ));
    }
}
