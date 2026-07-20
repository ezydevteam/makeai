<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'maintenance_title' => ['required', 'string', 'max:160'],
            'maintenance_message' => ['required', 'string', 'max:12000'],
            'maintenance_estimated_restoration_time' => ['nullable', 'date'],
            'maintenance_allowed_ips' => ['nullable', 'string', 'max:2000'],
            'maintenance_background_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_maintenance_background_image' => ['nullable', 'boolean'],
        ];
    }
}
