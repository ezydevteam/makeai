<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailSettingsRequest;
use App\Http\Requests\Admin\MailTestRequest;
use App\Mail\TestMail;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class MailController extends Controller
{
    private const SECRET_KEYS = [
        'mail_password',
        'mailgun_secret',
        'ses_secret',
        'postmark_token',
        'sendgrid_api_key',
    ];

    public function index()
    {
        $settings = Setting::getGroup('mail');
        $configuredSecrets = [];

        foreach (self::SECRET_KEYS as $key) {
            $configuredSecrets[$key] = filled($settings[$key] ?? null);
            unset($settings[$key]);
        }

        return Inertia::render('Admin/Mail/Index', [
            'settings' => $settings,
            'configuredSecrets' => $configuredSecrets,
        ]);
    }

    public function update(MailSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            if (in_array($key, self::SECRET_KEYS, true) && blank($value)) {
                continue;
            }

            $type = in_array($key, self::SECRET_KEYS, true) ? 'encrypted' : 'string';
            settings_set($key, $value, $type, 'mail');
        }

        return back()->with('success', translate('Mail settings updated successfully.'));
    }

    public function test(MailTestRequest $request)
    {
        try {
            Mail::to($request->validated('email'))->send(new TestMail);

            return back()->with('success', translate('Test email sent successfully.'));
        } catch (\Exception $e) {
            return back()->with('error', translate('Failed to send test email: :message', ['message' => $e->getMessage()]));
        }
    }
}
