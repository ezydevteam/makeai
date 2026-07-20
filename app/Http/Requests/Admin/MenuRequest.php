<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        $menuId = $this->route('menu')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('menus', 'slug')->ignore($menuId),
            ],
        ];
    }
}
