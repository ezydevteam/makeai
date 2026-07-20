<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:voiceover,podcast'],
            'description' => ['nullable', 'string'],
            'cover_art' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'podcast_author' => ['nullable', 'string', 'max:150'],
            'podcast_category' => ['nullable', 'string', 'max:100'],
            'podcast_language' => ['nullable', 'string', 'max:10'],
            'podcast_explicit' => ['nullable', 'boolean'],
        ];
    }
}
