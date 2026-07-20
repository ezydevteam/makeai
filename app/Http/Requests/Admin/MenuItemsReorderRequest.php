<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MenuItemsReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
