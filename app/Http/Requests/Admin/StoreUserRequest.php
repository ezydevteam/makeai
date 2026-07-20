<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'credits' => ['required', 'numeric', 'min:0'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'is_active' => ['required', 'boolean'],
            'country' => ['nullable', 'string', 'size:2'],
            'profession' => ['nullable', 'string', 'max:150'],
        ];
    }
}
