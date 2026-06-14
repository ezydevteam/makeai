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

        return Inertia::render('Addons/ai-voiceover/Admin/Settings', [
            'voiceSyncStatus' => $voiceSyncStatus,
        ]);
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
        SyncVoices::dispatch();

        return back()->with('flash', 'Voice sync dispatched.');
    }
}
