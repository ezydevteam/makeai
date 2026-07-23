<?php

namespace App\Traits;

trait HasAttachmentValidation
{
    private function allowedMimes(): string
    {
        return collect(explode(',', (string) settings('allowed_attachment_types', 'jpg,png,gif,pdf,txt,zip,mp4')))
            ->map(fn ($type) => trim($type))
            ->filter()
            ->implode(',');
    }

    /**
     * Friendly validation messages so per-file errors read "Each attachment ..."
     * instead of exposing the raw array key ("The attachments.0 field ...").
     */
    public function messages(): array
    {
        return [
            'attachments.*.mimes' => translate('Each attachment must be a file of type: :values.'),
            'attachments.*.max' => translate('Each attachment must not be larger than :size MB.', ['size' => (int) settings('max_attachment_size_mb', 10)]),
            'attachments.*.file' => translate('Each attachment must be a valid file.'),
            'attachments.max' => translate('You can attach at most :max files.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'attachments' => translate('attachments'),
            'attachments.*' => translate('attachment'),
        ];
    }
}
