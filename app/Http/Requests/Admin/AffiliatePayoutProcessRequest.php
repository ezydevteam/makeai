<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AffiliatePayoutProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:processing,paid,rejected'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
