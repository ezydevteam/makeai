<?php

namespace App\Http\Requests\User;

use App\Support\CountryCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user()?->id),
            ],
            'country' => [
                'nullable',
                'string',
                'size:2',
                Rule::in(collect(CountryCatalog::countries(app()->getLocale()))->pluck('code')->all()),
            ],
            'profession' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'country' => is_string($this->input('country')) ? strtoupper(trim($this->input('country'))) : $this->input('country'),
            'profession' => is_string($this->input('profession')) ? trim($this->input('profession')) : $this->input('profession'),
        ]);
    }
}
