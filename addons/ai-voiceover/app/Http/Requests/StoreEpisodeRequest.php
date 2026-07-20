<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEpisodeRequest extends FormRequest
{
    public function rules(): array
    {
        $maxChars = (int) addon_setting('ai-voiceover', 'max_script_chars', 5000);
        $musicMaxSizeMb = (int) addon_setting('ai-voiceover', 'background_music_max_size_mb', 10);

        return [
            'title' => ['required', 'string', 'max:255'],
            'script' => ['nullable', 'string', 'max:' . $maxChars],
            'segments' => ['nullable', 'array'],
            'segments.*.speaker' => ['required_with:segments', 'string', 'max:100'],
            'segments.*.text' => ['required_with:segments', 'string'],
            'segments.*.voice_id' => ['nullable', 'string', 'max:100'],
            'segments.*.provider' => ['nullable', 'string', 'in:elevenlabs,openai,murf,playht'],
            'provider' => ['nullable', 'string', 'in:elevenlabs,openai,murf,playht'],
            'voice_id' => ['nullable', 'string', 'max:100'],
            'music_track_id' => ['nullable', 'integer', 'exists:vo_music_library,id'],
            'music_volume' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'episode_number' => ['nullable', 'integer', 'min:1'],
            'season_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
