<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FeatureSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'scroll_to_top_enabled' => ['required', 'boolean'],
            'ai_chat_enabled' => ['required', 'boolean'],
            'ai_variations_enabled' => ['required', 'boolean'],
            'social_sharing_enabled' => ['required', 'boolean'],
            'document_editor_enabled' => ['required', 'boolean'],
            'favorites_enabled' => ['required', 'boolean'],
            'reviews_enabled' => ['required', 'boolean'],
            'recently_used_tools_enabled' => ['required', 'boolean'],
            'estimated_generation_time_enabled' => ['required', 'boolean'],
            'subscriptions_enabled' => ['required', 'boolean'],
        ];
    }
}
