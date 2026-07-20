<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class RateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $this->user() !== null
            && $ticket
            && $ticket->user_id === $this->user()->id
            && $ticket->status === 'resolved'
            && (bool) settings('satisfaction_rating_enabled', true);
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
