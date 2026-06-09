<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Support\SupportSettingsRequest;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeSupport();

        return Inertia::render('Admin/Support/Settings', [
            'settings' => $this->settings(),
        ]);
    }

    public function update(SupportSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
            settings_set($key, $value, $type, 'support');
        }

        return back()->with('success', translate('Support settings updated.'));
    }

    private function settings(): array
    {
        return [
            'tickets_enabled' => (bool) settings('tickets_enabled', true),
            'max_attachments_per_reply' => (int) settings('max_attachments_per_reply', 5),
            'max_attachment_size_mb' => (int) settings('max_attachment_size_mb', 10),
            'allowed_attachment_types' => settings('allowed_attachment_types', 'jpg,png,gif,pdf,txt,zip,mp4'),
            'auto_close_resolved_days' => (int) settings('auto_close_resolved_days', 7),
            'sla_first_response_hours' => (int) settings('sla_first_response_hours', 24),
            'sla_resolution_hours' => (int) settings('sla_resolution_hours', 72),
            'notify_admin_new_ticket' => (bool) settings('notify_admin_new_ticket', true),
            'notify_user_reply' => (bool) settings('notify_user_reply', true),
            'satisfaction_rating_enabled' => (bool) settings('satisfaction_rating_enabled', true),
            'ai_reply_suggestion' => (bool) settings('ai_reply_suggestion', true),
        ];
    }

    private function authorizeSupport(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('support.tickets'), 403);
    }
}
