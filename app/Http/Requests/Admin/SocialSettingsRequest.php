<?php

namespace App\Http\Requests\Admin;

use App\Services\SocialService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.social_follow_display_mode' => ['required', 'string', Rule::in(SocialService::FOLLOW_DISPLAY_MODES)],
            'settings.social_follow_refresh_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'profiles' => ['required', 'array'],
            'profiles.*.platform' => ['required', 'string', Rule::in(array_keys(SocialService::FOLLOW_PLATFORMS))],
            'profiles.*.profile_url' => ['nullable', 'url', 'max:500'],
            'profiles.*.manual_count' => ['nullable', 'integer', 'min:0'],
            'profiles.*.count_source' => ['required', 'string', Rule::in(['manual', 'api'])],
            'profiles.*.fetch_enabled' => ['required', 'boolean'],
            'profiles.*.api_key' => ['nullable', 'string', 'max:2000'],
            'profiles.*.external_id' => ['nullable', 'string', 'max:255'],
            'profiles.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'profiles.*.is_active' => ['required', 'boolean'],
        ];
    }
}
