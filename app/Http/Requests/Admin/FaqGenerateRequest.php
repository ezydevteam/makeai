<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FaqGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('content.faq');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->input('category_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:160'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'count' => ['required', 'integer', 'min:5', 'max:20'],
            'category_id' => ['nullable', 'integer', 'exists:faq_categories,id'],
        ];
    }
}
