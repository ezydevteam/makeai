<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check()
            && auth('admin')->user()->hasAnyPermission(['settings.general', 'settings.manage']);
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:120'],
            'app_url' => ['nullable', 'url', 'max:255'],
            'default_language' => ['required', 'string', 'max:20', Rule::exists('languages', 'code')],
            'default_currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_position' => ['required', Rule::in(['before', 'before_with_space', 'after', 'after_with_space'])],
            'currency_decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'app_timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }
}
