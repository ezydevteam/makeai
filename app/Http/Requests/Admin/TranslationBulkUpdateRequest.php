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
            // The source string, which is the key in lang/{code}.json. There is no row to
            // check it against any more — the catalogue is the store — and an unknown key
            // is harmless: it simply never matches anything the product renders.
            'translations.*.key' => ['required', 'string'],
            // `present` rather than `required`: clearing the field reverts an entry to the
            // source string, and `required` rejects an empty string.
            'translations.*.value' => ['present', 'string'],
        ];
    }
}
