<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LanguageUpdateRequest extends FormRequest
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
        'thai',
        'tamldec',
        'telu',
        'knda',
        'mlym',
        'gujr',
        'guru',
        'mymr',
    ];

    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'flag_file' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp', 'max:512'],
            'is_rtl' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'date_format' => ['required', 'string', 'max:50', Rule::in(self::DATE_FORMATS)],
            'time_format' => ['required', 'string', 'max:50', Rule::in(self::TIME_FORMATS)],
            'number_system' => ['required', 'string', 'max:20', Rule::in(self::NUMBER_SYSTEMS)],
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

                    if (preg_match('/<script|<foreignObject|<iframe|<embed|on[a-z]+\s*=|javascript:|data:text\/html/i', $contents)) {
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
