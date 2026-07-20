<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'max:120', 'exists:plans,slug'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
            'gateway' => ['required', 'string', 'max:50', 'exists:payment_gateways,slug'],
            'coupon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
