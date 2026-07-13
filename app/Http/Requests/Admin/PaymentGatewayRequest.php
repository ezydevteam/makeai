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
            // A fixed fee is a flat amount in the store base currency (added to the
            // charge). There is no separate fee currency — it is always the base.
            'processing_fee_value' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
