<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Jobs;

use Addons\AiVoiceover\Services\VoiceoverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncVoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(VoiceoverService $service): void
    {
        $providers = ['elevenlabs', 'openai', 'murf', 'playht'];

        foreach ($providers as $provider) {
            try {
                $service->syncVoices($provider);
            } catch (\Throwable $e) {
                Log::warning("Voiceover: Failed to sync voices for {$provider}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
