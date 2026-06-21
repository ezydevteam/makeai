<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('settings.general');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notifications_driver' => ['required', 'string', Rule::in(['reverb', 'pusher', 'polling'])],
            'notifications_polling_interval' => ['required', 'integer', 'min:10000', 'max:300000'],
            'reverb.app_id' => ['nullable', 'string', 'max:255'],
            'reverb.app_key' => ['nullable', 'string', 'max:255'],
            'reverb.app_secret' => ['nullable', 'string', 'max:2000'],
            'reverb.host' => ['nullable', 'string', 'max:255'],
            'reverb.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'reverb.scheme' => ['nullable', 'string', Rule::in(['http', 'https'])],
            'pusher.app_id' => ['nullable', 'string', 'max:255'],
            'pusher.key' => ['nullable', 'string', 'max:255'],
            'pusher.secret' => ['nullable', 'string', 'max:2000'],
            'pusher.cluster' => ['nullable', 'string', 'max:50'],
        ];
    }
}
