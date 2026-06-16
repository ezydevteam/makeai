<?php

namespace Addons\AiVideoCreator\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VideoSettingsController extends Controller
{
    public function edit(): \Inertia\Response
    {
        $ffmpegPath = addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg');

        return inertia('Addons/ai-video-creator/Admin/Settings', [
            'settings' => [
                'enabled' => addon_setting('ai-video-creator', 'enabled', true),
                'show_to' => addon_setting('ai-video-creator', 'show_to', 'logged_in'),
                'text_video_provider' => addon_setting('ai-video-creator', 'text_video_provider', 'kling'),
                'image_video_provider' => addon_setting('ai-video-creator', 'image_video_provider', 'kling'),
                'avatar_provider' => addon_setting('ai-video-creator', 'avatar_provider', 'heygen'),
                'tts_provider' => addon_setting('ai-video-creator', 'tts_provider', 'openai'),
                'subtitle_provider' => addon_setting('ai-video-creator', 'subtitle_provider', 'whisper'),
                'max_video_duration' => addon_setting('ai-video-creator', 'max_video_duration', 30),
                'max_storage_mb_per_user' => addon_setting('ai-video-creator', 'max_storage_mb_per_user', 500),
                'credits_text_video' => addon_setting('ai-video-creator', 'credits_text_video', 50),
                'credits_text_video_long' => addon_setting('ai-video-creator', 'credits_text_video_long', 100),
                'credits_image_video' => addon_setting('ai-video-creator', 'credits_image_video', 40),
                'credits_avatar_video' => addon_setting('ai-video-creator', 'credits_avatar_video', 80),
                'credits_slideshow' => addon_setting('ai-video-creator', 'credits_slideshow', 30),
                'credits_subtitles' => addon_setting('ai-video-creator', 'credits_subtitles', 10),
                'enable_slideshow_fallback' => addon_setting('ai-video-creator', 'enable_slideshow_fallback', false),
                'enable_watermark' => addon_setting('ai-video-creator', 'enable_watermark', false),
                'watermark_path' => addon_setting('ai-video-creator', 'watermark_path', null),
                'ffmpeg_path' => $ffmpegPath,
                'poll_interval_seconds' => addon_setting('ai-video-creator', 'poll_interval_seconds', 30),
                'max_poll_attempts' => addon_setting('ai-video-creator', 'max_poll_attempts', 20),
                'auto_delete_days' => addon_setting('ai-video-creator', 'auto_delete_days', 30),
            ],
            'apiKeyStatus' => [
                'slideshow_provider' => addon_setting('ai-video-creator', 'slideshow_provider', 'local'),
                'kling_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'kling_api_key', '')),
                'slideshow_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'slideshow_api_key', '')),
                'kling_api_secret' => $this->maskedSecret(addon_setting('ai-video-creator', 'kling_api_secret', '')),
                'runway_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'runway_api_key', '')),
                'pika_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'pika_api_key', '')),
                'minimax_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'minimax_api_key', '')),
                'heygen_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'heygen_api_key', '')),
                'did_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'did_api_key', '')),
                'elevenlabs_api_key' => $this->maskedSecret(addon_setting('ai-video-creator', 'elevenlabs_api_key', '')),
            ],
            'ffmpeg_found' => file_exists($ffmpegPath) || ! empty(trim(shell_exec(
                PHP_OS_FAMILY === 'Windows' ? "where {$ffmpegPath}" : "command -v {$ffmpegPath}"
            ) ?? '')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'show_to' => ['in:all,logged_in,pro'],
            'text_video_provider' => ['in:kling,runway,pika,minimax'],
            'image_video_provider' => ['in:kling,runway,pika'],
            'avatar_provider' => ['in:heygen,did'],
            'tts_provider' => ['in:elevenlabs,openai'],
            'subtitle_provider' => ['in:whisper'],
            'kling_api_key' => ['nullable', 'string'],
            'slideshow_api_key' => ['nullable', 'string'],
            'slideshow_provider' => ['in:local,api'],
            'kling_api_secret' => ['nullable', 'string'],
            'runway_api_key' => ['nullable', 'string'],
            'pika_api_key' => ['nullable', 'string'],
            'minimax_api_key' => ['nullable', 'string'],
            'heygen_api_key' => ['nullable', 'string'],
            'did_api_key' => ['nullable', 'string'],
            'elevenlabs_api_key' => ['nullable', 'string'],
            'max_video_duration' => ['integer', 'min:5', 'max:120'],
            'max_storage_mb_per_user' => ['integer', 'min:10', 'max:10000'],
            'credits_text_video' => ['integer', 'min:1'],
            'credits_text_video_long' => ['integer', 'min:1'],
            'credits_image_video' => ['integer', 'min:1'],
            'credits_avatar_video' => ['integer', 'min:1'],
            'credits_slideshow' => ['integer', 'min:1'],
            'credits_subtitles' => ['integer', 'min:1'],
            'ffmpeg_path' => ['string', 'max:500'],
            'enable_slideshow_fallback' => ['boolean'],
            'enable_watermark' => ['boolean'],
            'watermark_file' => ['nullable', 'image', 'max:2048'],
            'poll_interval_seconds' => ['integer', 'min:5', 'max:300'],
            'max_poll_attempts' => ['integer', 'min:5', 'max:100'],
            'auto_delete_days' => ['integer', 'min:0', 'max:365'],
        ]);

        foreach ($validated as $key => $value) {
            if ($key === 'watermark_file' && $request->hasFile('watermark_file')) {
                $path = $request->file('watermark_file')->store('addons/ai-video-creator/watermarks', 'public');
                addon_setting_set('ai-video-creator', 'watermark_path', $path);
                continue;
            }

            if ($this->isEncryptedKey($key) && ($value === null || $value === '')) {
                continue;
            }

            addon_setting_set('ai-video-creator', $key, $value);
        }

        return back()->with('flash', 'Settings saved.');
    }

    private function isEncryptedKey(string $key): bool
    {
        return in_array($key, [
            'kling_api_key',
            'slideshow_api_key',
            'kling_api_secret',
            'runway_api_key',
            'pika_api_key',
            'minimax_api_key',
            'heygen_api_key',
            'did_api_key',
            'elevenlabs_api_key',
        ], true);
    }

    private function maskedSecret(mixed $value): string
    {
        return is_string($value) && $value !== '' ? str_repeat('•', 12) : '';
    }
}
