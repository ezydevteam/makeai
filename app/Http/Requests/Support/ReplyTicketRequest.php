<?php

namespace App\Http\Requests\Support;

use App\Traits\HasAttachmentValidation;
use Illuminate\Foundation\Http\FormRequest;

class ReplyTicketRequest extends FormRequest
{
    use HasAttachmentValidation;

    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $this->user() !== null
            && $ticket
            && $ticket->user_id === $this->user()->id
            && ! in_array($ticket->status, ['closed'], true);
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:50000'],
            'attachments' => ['array', 'max:'.(int) settings('max_attachments_per_reply', 5)],
            'attachments.*' => [
                'file',
                'max:'.((int) settings('max_attachment_size_mb', 10) * 1024),
                'mimes:'.$this->allowedMimes(),
            ],
        ];
    }
}
