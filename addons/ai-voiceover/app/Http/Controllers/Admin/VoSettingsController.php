<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Controllers\Admin;

use Addons\AiVoiceover\Http\Requests\UpdateSettingsRequest;
use Addons\AiVoiceover\Jobs\SyncVoices;
use Addons\AiVoiceover\Models\VoVoice;
use Inertia\Inertia;
use Inertia\Response;

class VoSettingsController extends \App\Http\Controllers\Controller
{
    public function edit(): Response
    {
        $voiceSyncStatus = [];
        $providers = ['elevenlabs', 'openai', 'murf', 'playht'];

        foreach ($providers as $provider) {
            $lastSync = VoVoice::where('provider', $provider)->max('synced_at');
            $voiceCount = VoVoice::forProvider($provider)->active()->count();
            $voiceSyncStatus[$provider] = [
                'last_synced_at' => $lastSync,
                'voice_count' => $voiceCount,
            ];
        }

        // Same access-level options the core AI tools expose (minus "inherit",
        // which has no parent to inherit from here).
        $accessLevels = collect(app(\App\Services\AccessLevelService::class)->getOptions())
            ->reject(fn (array $o) => $o['value'] === 'inherit')
            ->values()
            ->all();

        return Inertia::render('Addons/ai-voiceover/Admin/Settings', [
            'voiceSyncStatus' => $voiceSyncStatus,
            'accessLevels' => $accessLevels,
            'systemStatus' => [
                'ffmpeg' => $this->checkBinary('ffmpeg'),
                'ffprobe' => $this->checkBinary('ffprobe'),
            ]
        ]);
    }

    private function checkBinary(string $binary): bool
    {
        $custom = addon_setting('ai-voiceover', "{$binary}_path", '');
        if (!empty($custom) && is_executable($custom)) return true;

        return !empty(trim(shell_exec(PHP_OS_FAMILY === 'Windows' ? "where {$binary}" : "command -v {$binary}") ?? ''));
    }

    public function update(UpdateSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            if ($value !== null) {
                addon_setting_set('ai-voiceover', $key, $value);
            }
        }

        return back()->with('flash', 'Settings updated.');
    }

    public function syncVoices()
    {
        $provider = addon_setting('ai-voiceover', 'default_provider', 'openai');

        if (! in_array($provider, ['elevenlabs', 'openai', 'murf', 'playht'], true)) {
            $provider = 'openai';
        }

        SyncVoices::dispatch($provider);

        return back()->with('flash', 'Voice sync dispatched.');
    }
}
