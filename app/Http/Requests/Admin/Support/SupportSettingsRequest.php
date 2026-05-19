<?php

namespace App\Http\Requests\Admin\Support;

use Illuminate\Foundation\Http\FormRequest;

class SupportSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('support.tickets');
    }

    public function rules(): array
    {
        return [
            'tickets_enabled' => ['required', 'boolean'],
            'guest_tickets' => ['required', 'boolean'],
            'max_attachments_per_reply' => ['required', 'integer', 'min:0', 'max:10'],
            'max_attachment_size_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'allowed_attachment_types' => ['required', 'string', 'max:255'],
            'auto_close_resolved_days' => ['required', 'integer', 'min:1', 'max:365'],
            'sla_first_response_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'sla_resolution_hours' => ['required', 'integer', 'min:1', 'max:1440'],
            'notify_admin_new_ticket' => ['required', 'boolean'],
            'notify_user_reply' => ['required', 'boolean'],
            'satisfaction_rating_enabled' => ['required', 'boolean'],
            'ai_reply_suggestion' => ['required', 'boolean'],
        ];
    }
}
