<?php

namespace App\Http\Requests\Admin\Auth;

use App\Services\CaptchaService;
use Illuminate\Foundation\Http\FormRequest;

class AdminTwoFactorRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32'],
        ];

        if (CaptchaService::fromSettings()->isEnabled()) {
            $rules['captcha_token'] = ['required', 'string'];
        }

        return $rules;
    }
}
