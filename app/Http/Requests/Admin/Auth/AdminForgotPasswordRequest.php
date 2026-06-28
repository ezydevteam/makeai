<?php

namespace App\Http\Requests\Admin\Auth;

use App\Services\CaptchaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->guest();
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
                Rule::exists('admins', 'email')->where('is_active', true),
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
            'email.exists' => translate('No active admin account was found for this email address.'),
        ];
    }
}
