<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            // Only drivers with a working transport in this build are offered:
            // mailgun/postmark need Symfony API-transport packages that aren't
            // installed, so they were removed. SES uses aws/aws-sdk-php (installed);
            // sendgrid is implemented as a plain SMTP mailer.
            'mail_driver' => ['required', 'string', 'in:smtp,ses,sendgrid,log,array'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:2000'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
            'ses_key' => ['nullable', 'string', 'max:255'],
            'ses_secret' => ['nullable', 'string', 'max:2000'],
            'ses_region' => ['nullable', 'string', 'max:100'],
            'sendgrid_api_key' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
