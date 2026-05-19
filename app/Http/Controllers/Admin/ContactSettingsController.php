<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactSettingsRequest;
use Inertia\Inertia;

class ContactSettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeContact();

        return Inertia::render('Admin/Contact/Settings', [
            'settings' => [
                'contact_form_enabled' => (bool) settings('contact_form_enabled', true),
                'contact_subject_mode' => settings('contact_subject_mode', 'text'),
                'contact_subject_options' => settings('contact_subject_options', "General Inquiry\nSupport\nBilling\nPartnership"),
                'contact_notification_email' => settings('contact_notification_email', settings('mail_from_address')),
                'contact_success_message' => settings('contact_success_message', 'Your message has been sent successfully. We will get back to you soon!'),
                'contact_auto_reply_enabled' => (bool) settings('contact_auto_reply_enabled', false),
                'contact_auto_reply_subject' => settings('contact_auto_reply_subject', 'We received your message'),
                'contact_auto_reply_message' => settings('contact_auto_reply_message', "Hi {name},\n\nThanks for contacting us. We received your message and will reply soon."),
            ],
        ]);
    }

    public function update(ContactSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $type = is_bool($value) ? 'boolean' : 'string';
            settings_set($key, $value, $type, 'contact');
        }

        return back()->with('success', translate('Contact settings saved.'));
    }

    private function authorizeContact(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('content.pages'), 403);
    }
}
