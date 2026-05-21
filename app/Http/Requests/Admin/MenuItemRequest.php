<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Validator;

class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:url,page,route'],
            'url' => ['nullable', 'required_if:type,url', 'string', 'max:500'],
            'page_id' => ['nullable', 'required_if:type,page', 'exists:pages,id'],
            'route_name' => ['nullable', 'required_if:type,route', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:menu_items,id'],
            'target' => ['required', 'in:_self,_blank'],
            'icon' => ['nullable', 'string', 'max:100'],
            'badge_text' => ['nullable', 'string', 'max:50'],
            'badge_color' => ['nullable', 'in:green,blue,violet,amber,red,gray'],
            'is_active' => ['required', 'boolean'],
            'requires_auth' => ['required', 'in:none,guest,auth,pro'],
            'mega_menu' => ['required', 'boolean'],
            'mega_menu_content' => ['nullable', 'string', 'max:50000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === 'route' && ! Route::has((string) $this->input('route_name'))) {
                    $validator->errors()->add('route_name', translate('The selected route does not exist.'));
                }
            },
        ];
    }
}
