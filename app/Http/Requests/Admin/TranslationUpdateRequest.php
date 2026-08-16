<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TranslationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The source string, which is the key in lang/{code}.json.
            'key' => ['required', 'string'],
            // `present` rather than `required`: clearing the field is how an admin reverts
            // an entry to the source string, and `required` rejects an empty string.
            'value' => ['present', 'string'],
        ];
    }
}
