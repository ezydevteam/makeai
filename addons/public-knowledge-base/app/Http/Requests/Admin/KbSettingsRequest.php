<?php

namespace Addons\PublicKnowledgeBase\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class KbSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'public_slug' => ['nullable', 'string', 'max:50'],
            'page_title' => ['nullable', 'string', 'max:150'],
            'page_description' => ['nullable', 'string', 'max:320'],
            'show_vote_buttons' => ['boolean'],
            'allow_guest_search' => ['boolean'],
            'widget_enabled' => ['boolean'],
            'widget_accent_color' => ['nullable', 'string', 'max:7'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'embedding_model' => ['nullable', 'string', 'max:100'],
            'top_k' => ['integer', 'min:1', 'max:20'],
            'max_answer_tokens' => ['integer', 'min:64', 'max:4096'],
            'provider' => ['nullable', 'string', 'max:50'],
        ];
    }
}
