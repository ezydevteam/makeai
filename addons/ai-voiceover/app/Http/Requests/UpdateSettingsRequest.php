<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'show_to' => ['nullable', 'string', 'in:all,logged_in,pro'],
            'default_provider' => ['nullable', 'string', 'in:elevenlabs,openai,murf,playht'],
            'default_voice_id' => ['nullable', 'string', 'max:100'],
            'speed_default' => ['nullable', 'numeric', 'min:0.25', 'max:4.0'],
            'stability_default' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'similarity_boost_default' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'podcast_enabled' => ['nullable', 'boolean'],
            'podcast_base_url' => ['nullable', 'string', 'max:500'],
            'credits_per_1k_chars' => ['nullable', 'integer', 'min:1'],
            'credits_stt' => ['nullable', 'integer', 'min:1'],
            'max_script_chars' => ['nullable', 'integer', 'min:100', 'max:50000'],
            'max_file_size_mb' => ['nullable', 'integer', 'min:1', 'max:100'],
            'background_music_max_size_mb' => ['nullable', 'integer', 'min:1', 'max:50'],
            'auto_transcribe' => ['nullable', 'boolean'],
            'ffmpeg_path' => ['nullable', 'string', 'max:500'],
            'auto_delete_days' => ['nullable', 'integer', 'min:0'],
            'elevenlabs_api_key' => ['nullable', 'string'],
            'openai_api_key' => ['nullable', 'string'],
            'murf_api_key' => ['nullable', 'string'],
            'playht_api_key' => ['nullable', 'string'],
            'playht_user_id' => ['nullable', 'string'],
        ];
    }
}
