<?php

namespace App\Http\Requests\Support;

use App\Models\SupportDepartment;
use App\Traits\HasAttachmentValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    use HasAttachmentValidation;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ! $user->is_banned
            && (bool) settings('tickets_enabled', true);
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:500'],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('support_departments', 'id')->where('is_active', true),
            ],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'message' => ['required', 'string', 'max:50000'],
            'attachments' => ['array', 'max:'.(int) settings('max_attachments_per_reply', 5)],
            'attachments.*' => [
                'file',
                'max:'.((int) settings('max_attachment_size_mb', 10) * 1024),
                'mimes:'.$this->allowedMimes(),
            ],
        ];
    }

    public function departments()
    {
        return SupportDepartment::active()->orderBy('sort_order')->get(['id', 'name']);
    }
}
