<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LanguageStoreRequest extends FormRequest
{
    private const DATE_FORMATS = [
        'MMM D, YYYY',
        'D MMM YYYY',
        'MM/DD/YYYY',
        'DD/MM/YYYY',
        'YYYY-MM-DD',
    ];

    private const TIME_FORMATS = [
        'h:mm A',
        'HH:mm',
        'HH:mm:ss',
    ];

    private const NUMBER_SYSTEMS = [
        'latn',
        'arab',
        'arabext',
        'beng',
        'deva',
    ];

    private const CURRENCY_POSITIONS = [
        'before',
        'before_with_space',
        'after',
        'after_with_space',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_position' => in_array($this->input('currency_position'), self::CURRENCY_POSITIONS, true)
                ? $this->input('currency_position')
                : 'before',
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', Rule::unique('languages', 'code')],
            'flag_file' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp', 'max:512'],
            'is_rtl' => ['required', 'boolean'],
            'date_format' => ['required', 'string', 'max:50', Rule::in(self::DATE_FORMATS)],
            'time_format' => ['required', 'string', 'max:50', Rule::in(self::TIME_FORMATS)],
            'decimal_separator' => ['required', 'string', 'size:1'],
            'thousands_separator' => ['required', 'string', 'size:1'],
            'number_system' => ['required', 'string', 'max:20', Rule::in(self::NUMBER_SYSTEMS)],
            'currency_position' => ['required', 'string', 'max:20', Rule::in(self::CURRENCY_POSITIONS)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('flag_file');

                if (! $file) {
                    return;
                }

                $path = $file->getRealPath();
                $extension = strtolower($file->getClientOriginalExtension());

                if ($extension === 'svg') {
                    $contents = file_get_contents($path) ?: '';

                    if (! str_contains(strtolower($contents), '<svg')) {
                        $validator->errors()->add('flag_file', translate('The flag file must be a valid SVG image.'));
                    }

                    if (preg_match('/<script|on[a-z]+\s*=|javascript:/i', $contents)) {
                        $validator->errors()->add('flag_file', translate('The SVG flag contains unsafe content.'));
                    }

                    return;
                }

                if (! getimagesize($path)) {
                    $validator->errors()->add('flag_file', translate('The flag file must be a valid image.'));
                }
            },
        ];
    }
}
