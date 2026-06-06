<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_code' => ['required', 'string', 'regex:/^[a-zA-Z0-9\-]{20,50}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_code.required' => translate('Please enter your Envato purchase code.'),
            'purchase_code.regex' => translate('Invalid purchase code format.'),
        ];
    }
}
