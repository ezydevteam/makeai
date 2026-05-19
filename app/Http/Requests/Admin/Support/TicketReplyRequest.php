<?php

namespace App\Http\Requests\Admin\Support;

use Illuminate\Foundation\Http\FormRequest;

class TicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('support.respond');
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:50000'],
            'is_internal_note' => ['required', 'boolean'],
            'is_ai_draft' => ['sometimes', 'boolean'],
            'attachments' => ['array', 'max:'.(int) settings('max_attachments_per_reply', 5)],
            'attachments.*' => [
                'file',
                'max:'.((int) settings('max_attachment_size_mb', 10) * 1024),
                'mimes:'.$this->allowedMimes(),
            ],
        ];
    }

    private function allowedMimes(): string
    {
        return collect(explode(',', (string) settings('allowed_attachment_types', 'jpg,png,gif,pdf,txt,zip,mp4')))
            ->map(fn ($type) => trim($type))
            ->filter()
            ->implode(',');
    }
}
