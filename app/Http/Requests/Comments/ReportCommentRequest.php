<?php

namespace App\Http\Requests\Comments;

use Illuminate\Foundation\Http\FormRequest;

class ReportCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A report is a simple flag — no fields are stored. Kept as a FormRequest
        // for the authorize() gate and consistent handling.
        return [];
    }
}
