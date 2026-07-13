<?php

namespace App\Http\Controllers\Admin\Mail;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailSettingsRequest;
use App\Http\Requests\Admin\MailTestRequest;
use App\Mail\TestMail;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class MailController extends Controller
{
    private const SECRET_KEYS = [
        'mail_password',
        'ses_secret',
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

        return Inertia::render('Admin/Mail/Settings', [
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

    public function test(MailTestRequest $request): RedirectResponse
    {
        $settings = Setting::getGroup('mail');
        $validationError = $this->validateTestConfiguration($settings);

        if ($validationError !== null) {
            return back()->with('error', $validationError);
        }

        try {
            Mail::to($request->validated('email'))->send(new TestMail);

            return back()->with('success', translate('Test email sent successfully.'));
        } catch (\Exception $e) {
            return back()->with('error', translate('Failed to send test email: :message', ['message' => $e->getMessage()]));
        }
    }

    private function validateTestConfiguration(array $settings): ?string
    {
        $driver = (string) ($settings['mail_driver'] ?? '');

        if (blank($driver)) {
            return translate('Select a mail driver and save your settings before running a connectivity test.');
        }

        if (in_array($driver, ['log', 'array'], true)) {
            return translate('Connectivity test is not available for the :driver driver.', [
                'driver' => strtoupper($driver),
            ]);
        }

        $commonRequired = [
            'mail_from_address' => translate('From address'),
            'mail_from_name' => translate('From name'),
        ];

        $driverRequired = match ($driver) {
            'smtp' => [
                'mail_host' => translate('Host'),
                'mail_port' => translate('Port'),
                'mail_username' => translate('Username'),
                'mail_password' => translate('Password'),
            ],
            'ses' => [
                'ses_key' => translate('Access key ID'),
                'ses_secret' => translate('Secret access key'),
                'ses_region' => translate('Region'),
            ],
            'sendgrid' => [
                'sendgrid_api_key' => translate('API key'),
            ],
            default => [],
        };

        if ($driverRequired === []) {
            return translate('The selected mail driver is not supported for connectivity testing.');
        }

        $missingFields = [];

        foreach (array_merge($commonRequired, $driverRequired) as $key => $label) {
            if (blank($settings[$key] ?? null)) {
                $missingFields[] = $label;
            }
        }

        if ($missingFields !== []) {
            return translate('Complete the :driver configuration first. Missing: :fields', [
                'driver' => strtoupper($driver),
                'fields' => implode(', ', $missingFields),
            ]);
        }

        return null;
    }
}
