<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('proof');

            if (! $file) {
                return;
            }

            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            $detected = mime_content_type($file->getPathname()) ?: $file->getMimeType();

            if (! in_array($detected, $allowed, true)) {
                $validator->errors()->add('proof', translate('The uploaded proof file type is not allowed.'));
            }
        });
    }
}
