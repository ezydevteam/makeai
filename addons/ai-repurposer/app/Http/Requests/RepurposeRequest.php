<?php

namespace Addons\AiRepurposer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RepurposeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFileMb = (int) addon_setting('ai-repurposer', 'max_file_size_mb', 100);

        return [
            'source_type' => ['required', 'in:youtube_url,file_upload,text_paste'],
            'source_url'  => ['required_if:source_type,youtube_url', 'url', 'max:500'],
            'file'        => ['required_if:source_type,file_upload', 'file', 'mimes:mp3,mp4,m4a,wav,webm,ogg', "max:{$maxFileMb}"],
            'text'        => ['required_if:source_type,text_paste', 'string', 'min:100', 'max:20000'],
            'title'       => ['nullable', 'string', 'max:255'],
            'formats'     => ['required', 'array', 'min:1'],
            'formats.*'   => ['in:blog_post,twitter_thread,linkedin_article,email_newsletter,tiktok_script,podcast_show_notes,key_quotes,chapter_markers'],
            'is_bulk'     => ['boolean'],
        ];
    }
}
