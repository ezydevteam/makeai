<?php

namespace App\Http\Requests\Comments;

use App\Models\AiTool;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public const COMMENTABLES = [
        'blog_posts' => BlogPost::class,
        'pages' => Page::class,
        'ai_tools' => AiTool::class,
    ];

    public function authorize(): bool
    {
        return (bool) settings('comments_enabled', true)
            && ($this->user() || (bool) settings('comments_allow_guests', false));
    }

    public function rules(): array
    {
        return [
            'commentable_type' => ['required', 'string', Rule::in(array_keys(self::COMMENTABLES))],
            'commentable_id' => ['required', 'integer'],
            'content' => ['required', 'string', 'min:2', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'guest_name' => [$this->user() ? 'nullable' : 'required', 'nullable', 'string', 'max:100'],
            'guest_email' => [$this->user() ? 'nullable' : 'required', 'nullable', 'email', 'max:255'],
        ];
    }

    public function commentableClass(): string
    {
        return self::COMMENTABLES[$this->validated('commentable_type')];
    }
}
