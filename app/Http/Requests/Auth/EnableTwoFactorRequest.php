<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['nullable', 'string', Rule::in(['totp', 'sms'])],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
