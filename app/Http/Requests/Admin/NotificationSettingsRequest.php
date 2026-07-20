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
        $driver = $this->input('notifications_driver');

        // Secrets are write-only: the form leaves them blank when one is already
        // stored ("leave blank to keep"). So the secret is only *required* when
        // its driver is selected AND nothing is stored yet — otherwise a routine
        // edit that keeps the existing secret would fail validation.
        $reverbSecretStored = filled(settings('notifications_reverb_app_secret'));
        $pusherSecretStored = filled(settings('notifications_pusher_secret'));

        return [
            'notifications_driver' => ['required', 'string', Rule::in(['reverb', 'pusher', 'polling'])],
            'notifications_polling_interval' => ['required', 'integer', 'min:10000', 'max:300000'],

            // Reverb — required only when the Reverb driver is chosen.
            'reverb.app_id' => [Rule::requiredIf($driver === 'reverb'), 'string', 'max:255'],
            'reverb.app_key' => [Rule::requiredIf($driver === 'reverb'), 'string', 'max:255'],
            'reverb.app_secret' => [Rule::requiredIf($driver === 'reverb' && ! $reverbSecretStored), 'string', 'max:2000'],
            'reverb.host' => ['nullable', 'string', 'max:255'],
            'reverb.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'reverb.scheme' => ['nullable', 'string', Rule::in(['http', 'https'])],

            // Pusher — required only when the Pusher driver is chosen. Cluster is
            // required too: pusher-js cannot connect without it.
            'pusher.app_id' => [Rule::requiredIf($driver === 'pusher'), 'string', 'max:255'],
            'pusher.key' => [Rule::requiredIf($driver === 'pusher'), 'string', 'max:255'],
            'pusher.secret' => [Rule::requiredIf($driver === 'pusher' && ! $pusherSecretStored), 'string', 'max:2000'],
            'pusher.cluster' => [Rule::requiredIf($driver === 'pusher'), 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reverb.app_id.required' => translate('The Reverb app ID is required to use the Reverb driver.'),
            'reverb.app_key.required' => translate('The Reverb app key is required to use the Reverb driver.'),
            'reverb.app_secret.required' => translate('The Reverb app secret is required to use the Reverb driver.'),
            'pusher.app_id.required' => translate('The Pusher app ID is required to use the Pusher driver.'),
            'pusher.key.required' => translate('The Pusher key is required to use the Pusher driver.'),
            'pusher.secret.required' => translate('The Pusher secret is required to use the Pusher driver.'),
            'pusher.cluster.required' => translate('The Pusher cluster is required to use the Pusher driver.'),
        ];
    }
}
