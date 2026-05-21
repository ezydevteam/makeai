<?php

namespace App\Http\Requests;

use App\Models\AiTemplate;
use App\Models\BlogPost;
use App\Models\Document;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FavoriteToggleRequest extends FormRequest
{
    public const FAVORITEABLES = [
        'blog_posts' => BlogPost::class,
        'ai_templates' => AiTemplate::class,
        'documents' => Document::class,
        'pages' => Page::class,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'favoriteable_type' => ['required', 'string', Rule::in(array_keys(self::FAVORITEABLES))],
            'favoriteable_id' => ['required', 'integer'],
        ];
    }

    public function favoriteableClass(): string
    {
        return self::FAVORITEABLES[$this->validated('favoriteable_type')];
    }
}
