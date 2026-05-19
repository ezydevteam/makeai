<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class NewsletterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'newsletter_driver' => ['nullable', 'in:internal,mailchimp,both'],
            'mailchimp_api_key' => ['nullable', 'string', 'max:2000'],
            'mailchimp_server_prefix' => ['nullable', 'string', 'max:50'],
            'mailchimp_list_id' => ['nullable', 'string', 'max:255'],
            'mailchimp_double_optin' => ['nullable', 'boolean'],
            'mailchimp_tags' => ['nullable', 'string', 'max:500'],
            'newsletter_enable_popup' => ['nullable', 'boolean'],
            'newsletter_popup_trigger' => ['nullable', 'in:exit_intent,time_delay,scroll_depth,page_views,first_visit'],
            'newsletter_popup_trigger_value' => ['nullable', 'string', 'max:50'],
            'newsletter_popup_title' => ['nullable', 'string', 'max:255'],
            'newsletter_popup_description' => ['nullable', 'string', 'max:1000'],
            'newsletter_popup_placeholder' => ['nullable', 'string', 'max:255'],
            'newsletter_popup_submit_text' => ['nullable', 'string', 'max:100'],
            'newsletter_popup_success_message' => ['nullable', 'string', 'max:255'],
            'newsletter_popup_bg_color' => ['nullable', 'string', 'max:20'],
            'newsletter_popup_show_mobile' => ['nullable', 'boolean'],
            'newsletter_popup_cookie_duration' => ['nullable', 'integer', 'min:1', 'max:365'],
            'newsletter_popup_hide_for_logged_in' => ['nullable', 'boolean'],
        ];
    }
}
