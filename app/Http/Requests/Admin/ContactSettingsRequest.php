<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('content.pages');
    }

    public function rules(): array
    {
        return [

            'contact_subject_mode' => ['required', Rule::in(['text', 'dropdown'])],
            'contact_subject_options' => ['nullable', 'string', 'max:2000'],
            'contact_notification_email' => ['nullable', 'email', 'max:255'],
            'contact_success_message' => ['required', 'string', 'max:500'],
            'contact_auto_reply_enabled' => ['required', 'boolean'],
            'contact_auto_reply_subject' => ['nullable', 'string', 'max:255'],
            'contact_auto_reply_message' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
