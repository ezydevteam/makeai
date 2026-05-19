<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TestimonialGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('content.testimonials');
    }

    public function rules(): array
    {
        return [
            'company_type' => ['required', 'string', 'max:120'],
            'tone' => ['required', 'string', 'max:80'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'count' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
