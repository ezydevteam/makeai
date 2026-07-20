<?php

namespace App\Http\Requests\User;

use App\Support\CountryCatalog;
use App\Support\PhoneNumber;
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
            'brand_voice' => ['nullable', 'string', 'max:2000'],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\d+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! PhoneNumber::isValid($value, $this->input('phone_country'))) {
                        $fail(__('The phone number is not valid for the selected country.'));
                    }
                },
            ],
            'phone_country' => [
                'nullable',
                'required_with:phone',
                'string',
                'size:2',
                Rule::in(collect(CountryCatalog::countries(app()->getLocale()))->pluck('code')->all()),
            ],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'country' => is_string($this->input('country')) ? strtoupper(trim($this->input('country'))) : $this->input('country'),
            'profession' => is_string($this->input('profession')) ? trim($this->input('profession')) : $this->input('profession'),
            // Normalize to the national number (digits only) parsed against phone_country:
            // strips the dial code and formatting, preserves significant leading zeros, and
            // round-trips back to the same E.164 number. A cleared field collapses to null.
            'phone' => PhoneNumber::nationalNumber($this->input('phone'), $this->input('phone_country')),
            'phone_country' => is_string($this->input('phone_country')) ? strtoupper(trim($this->input('phone_country'))) : $this->input('phone_country'),
        ]);
    }
}
