<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendUserNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('users.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
            'level' => ['required', 'string', Rule::in(['info', 'success', 'warning', 'error'])],
            'deliver_via' => ['required', 'string', Rule::in(['in_app', 'in_app_email', 'email'])],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'action_url' => ['nullable', 'string', 'max:500'],
            'action_label' => ['nullable', 'string', 'max:80'],
        ];
    }
}
