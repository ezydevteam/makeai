<?php

namespace Addons\AiVideoCreator\Jobs;

use Addons\AiVideoCreator\Services\VideoProviderService;

class GenerateImageToVideo extends BaseGenerateJob
{
    public function handle(VideoProviderService $providerService): void
    {
        $render = $this->findRender();
        if (! $render) return;

        $this->startProcessing($render);

        $imagePath = storage_path('app/' . $render->input_media_path);
        $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
        $base64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($imagePath));

        $client = $providerService->getClient('image_to_video');
        $jobId = $client->submitJob([
            'type' => 'image_to_video',
            'image' => $base64,
            'prompt' => $render->prompt ?? '',
            'duration' => $render->duration_seconds,
            'aspect_ratio' => $render->aspect_ratio,
        ]);

        $render->update(['provider_job_id' => $jobId]);
        $this->dispatchPoll($render);
    }
}
