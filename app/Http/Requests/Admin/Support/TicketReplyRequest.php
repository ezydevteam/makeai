<?php

namespace App\Http\Requests\Admin\Support;

use App\Traits\HasAttachmentValidation;
use Illuminate\Foundation\Http\FormRequest;

class TicketReplyRequest extends FormRequest
{
    use HasAttachmentValidation;

    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return auth('admin')->check()
            && auth('admin')->user()->hasPermission('support.respond')
            && $ticket
            && ! in_array($ticket->status, ['closed'], true);
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
}
