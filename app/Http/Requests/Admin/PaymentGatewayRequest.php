<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('payments.gateways');
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['boolean'],
            'is_test_mode' => ['boolean'],
            'processing_fee_type' => ['required', Rule::in(['none', 'percentage', 'fixed'])],
            'processing_fee_value' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'processing_fee_currency' => ['required', 'string', 'size:3'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:5000'],
            'settings' => ['array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'processing_fee_currency' => strtoupper((string) $this->input('processing_fee_currency')),
        ]);
    }
}
