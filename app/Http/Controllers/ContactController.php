<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Services\CaptchaService;
use App\Services\InAppNotificationService;
use App\Services\RateLimiterService;
use App\Services\SpamFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    /**
     * Store a new contact message.
     */
    public function store(Request $request)
    {
        if (! settings('contact_enabled', true)) {
            return back()->with('error', translate('Contact form is currently disabled.'));
        }

        $rateLimiter = app(RateLimiterService::class);
        $result = $rateLimiter->attempt('contact', $request->ip(), 3, 3600);

        if (! $result['allowed']) {
            return back()->with('error', translate('Too many messages. Please try again later.'));
        }

        $rateLimiter->hit('contact', $request->ip(), 3600);

        if (filled($request->input('website'))) {
            return back()->with('success', settings('contact_success_message', translate('Your message has been sent successfully. We will get back to you soon!')));
        }

        // No-op when captcha is disabled; throws a validation error on captcha_token
        // when enabled and the token is missing/invalid.
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

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

        // Silently drop Akismet-flagged submissions the same way the honeypot does:
        // the sender sees success, but nothing is stored or forwarded to admins.
        $isSpam = SpamFilterService::fromSettings()->check([
            'type' => 'contact-form',
            'content' => $validated['message'],
            'author' => $validated['name'],
            'email' => $validated['email'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
        ]);

        if ($isSpam) {
            return back()->with('success', settings('contact_success_message', translate('Your message has been sent successfully. We will get back to you soon!')));
        }

        $message = ContactMessage::create(array_merge($validated, [
            'ip_address' => $request->ip(),
        ]));

        $this->queueNotification($message);
        $this->queueInAppNotification($message);
        $this->queueAutoReply($message);

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

    /**
     * Resolve where contact notifications are sent / sent from. Falls back through
     * the branding support address and the mail "from" address so a fresh install
     * (where the contact_notification_email key is not yet saved) still delivers.
     */
    private function notificationEmail(): ?string
    {
        return settings('contact_notification_email')
            ?: settings('site_support_email')
            ?: settings('mail_from_address')
            ?: null;
    }

    private function queueNotification(ContactMessage $message): void
    {
        $recipient = $this->notificationEmail();

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

    private function queueInAppNotification(ContactMessage $message): void
    {
        app(InAppNotificationService::class)->notifyAdmins([
            'title' => translate('New contact message'),
            'message' => translate(':subject from :email', [
                'subject' => $message->subject ?: translate('No subject'),
                'email' => $message->email,
            ]),
            'level' => 'info',
            'category' => 'contact',
            'action_url' => route('admin.contact.messages.index'),
            'action_label' => translate('Open inbox'),
        ]);
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
            $this->notificationEmail(),
            settings('app_name', translate('Application'))
        ));
    }
}
