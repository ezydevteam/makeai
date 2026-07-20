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
}
