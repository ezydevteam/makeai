<?php

namespace App\Http\Requests\Auth;

use App\Services\CaptchaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForgotPasswordOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guest();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::exists('users', 'email')->where('is_active', true),
            ],
        ];

        if (CaptchaService::fromSettings()->isEnabled()) {
            $rules['captcha_token'] = ['required', 'string'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => translate('No active account was found for this email address.'),
        ];
    }
}
