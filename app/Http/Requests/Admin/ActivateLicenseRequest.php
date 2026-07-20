<?php

namespace App\Http\Requests\Admin;

use App\Support\PurchaseCode;
use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Format comes from the single source of truth (App\Support\PurchaseCode):
        // relaxed TEST-... codes in test mode, strict Envato UUID otherwise.
        return [
            'purchase_code' => ['required', 'string', 'regex:' . PurchaseCode::validationPattern()],
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
