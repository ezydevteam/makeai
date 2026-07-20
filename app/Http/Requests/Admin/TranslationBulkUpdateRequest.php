<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TranslationBulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.id' => ['required', 'integer', 'exists:translations,id'],
            'translations.*.value' => ['required', 'string'],
        ];
    }
}
