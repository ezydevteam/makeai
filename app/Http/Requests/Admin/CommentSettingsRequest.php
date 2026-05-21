<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CommentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return auth('admin')->check()
            && (
                $admin?->hasPermission('content.comments')
                || $admin?->hasPermission('content.blog')
                || $admin?->hasPermission('content.pages')
            );
    }

    public function rules(): array
    {
        return [
            'comments_enabled' => ['required', 'boolean'],
            'comments_auto_approve_users' => ['required', 'boolean'],
            'comments_allow_guests' => ['required', 'boolean'],
            'comments_require_approval' => ['required', 'boolean'],
            'comments_notify_admin' => ['required', 'boolean'],
            'comments_poll_seconds' => ['required', 'integer', 'min:10', 'max:300'],
            'comments_akismet_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
