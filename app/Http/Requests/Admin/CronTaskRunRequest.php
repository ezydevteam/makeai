<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CronTaskRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'task' => ['required', 'string', Rule::in([
                'ai-reset-usage-counters',
                'notifications-subscription-reminders',
                'subscriptions-expire-past-due',
                'scheduler-heartbeat',
            ])],
        ];
    }
}
