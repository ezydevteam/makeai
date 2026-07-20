<?php

namespace App\Http\Requests\Auth;

use App\Services\CaptchaService;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordResetOtpRequest extends FormRequest
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
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'digits:6'],
        ];

        if (CaptchaService::fromSettings()->isEnabled()) {
            $rules['captcha_token'] = ['required', 'string'];
        }

        return $rules;
    }
}
