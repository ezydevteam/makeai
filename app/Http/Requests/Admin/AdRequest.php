<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        $ad = $this->route('ad');

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['adsense', 'custom_html', 'image_link'])],
            'zone' => ['required', Rule::in(array_keys(config('ads.zones')))],
            'adsense_client' => ['nullable', 'string', 'max:100', 'regex:/^ca-pub-[0-9]+$/'],
            'adsense_slot' => ['nullable', 'required_if:type,adsense', 'string', 'max:50'],
            'adsense_format' => ['nullable', 'string', 'max:50'],
            'custom_html' => ['nullable', 'required_if:type,custom_html', 'string'],
            'image_file' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('type') === 'image_link' && blank($ad?->image_url) && ! $this->hasFile('image_file')),
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:5120',
            ],
            'link_url' => ['nullable', 'required_if:type,image_link', 'url', 'max:500'],
            'link_target' => ['required', Rule::in(['_blank', '_self'])],
            'show_to' => ['required', Rule::in(['all', 'guests', 'logged_in', 'free_users', 'paid_users'])],
            'is_active' => ['boolean'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
