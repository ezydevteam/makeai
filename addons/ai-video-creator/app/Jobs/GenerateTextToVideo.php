<?php

namespace Addons\AiVideoCreator\Jobs;

use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Services\VideoProviderService;

class GenerateTextToVideo extends BaseGenerateJob
{
    public function handle(VideoProviderService $providerService): void
    {
        $render = $this->findRender();
        if (! $render) {
            return;
        }

        $this->startProcessing($render);

        $client = $providerService->getClient('text_to_video');
        $jobId = $client->submitJob([
            'type' => 'text_to_video',
            'prompt' => $render->prompt,
            'duration' => $render->duration_seconds,
            'aspect_ratio' => $render->aspect_ratio,
        ]);

        $render->update(['provider_job_id' => $jobId]);
        $this->dispatchPoll($render);
    }
}
