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
            'mail_driver' => ['required', 'string', 'in:smtp,mailgun,ses,postmark,sendgrid,log,array'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:2000'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
            'mailgun_domain' => ['nullable', 'string', 'max:255'],
            'mailgun_secret' => ['nullable', 'string', 'max:2000'],
            'mailgun_endpoint' => ['nullable', 'string', 'in:api.mailgun.net,api.eu.mailgun.net'],
            'ses_key' => ['nullable', 'string', 'max:255'],
            'ses_secret' => ['nullable', 'string', 'max:2000'],
            'ses_region' => ['nullable', 'string', 'max:100'],
            'postmark_token' => ['nullable', 'string', 'max:2000'],
            'sendgrid_api_key' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
