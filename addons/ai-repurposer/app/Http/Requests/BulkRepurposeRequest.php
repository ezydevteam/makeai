<?php

namespace Addons\AiRepurposer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkRepurposeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxBulkItems = (int) addon_setting('ai-repurposer', 'max_bulk_items', 10);

        return [
            'urls'          => ['required', 'array', 'min:2', "max:{$maxBulkItems}"],
            'urls.*'        => ['required', 'url'],
            'formats'       => ['required', 'array', 'min:1'],
            'formats.*'     => ['in:blog_post,twitter_thread,linkedin_article,email_newsletter,tiktok_script,podcast_show_notes,key_quotes,chapter_markers'],
            'title_prefix'  => ['nullable', 'string', 'max:255'],
        ];
    }
}
