<?php

namespace Addons\AiVideoCreator\Jobs;

use Addons\AiVideoCreator\Services\VideoProviderService;

class GenerateAvatarVideo extends BaseGenerateJob
{
    public function handle(VideoProviderService $providerService): void
    {
        $render = $this->findRender();
        if (! $render) return;

        if (empty($render->script)) {
            $render->update(['status' => 'failed', 'error_message' => 'Script is required for avatar video.']);
            $providerService->refundCredits($render);
            return;
        }

        $this->startProcessing($render);

        $settings = $render->provider_settings ?? [];

        $client = $providerService->getClient('avatar_video');
        $jobId = $client->submitJob([
            'type' => 'avatar_video',
            'script' => $render->script,
            'avatar_id' => $settings['avatar_id'] ?? null,
            'voice_id' => $settings['voice_id'] ?? null,
            'presenter_id' => $settings['presenter_id'] ?? null,
        ]);

        $render->update(['provider_job_id' => $jobId]);
        $this->dispatchPoll($render);
    }
}
