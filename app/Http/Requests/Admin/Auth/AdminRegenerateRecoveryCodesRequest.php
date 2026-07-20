<?php

namespace App\Http\Requests\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminRegenerateRecoveryCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ];
    }
}
