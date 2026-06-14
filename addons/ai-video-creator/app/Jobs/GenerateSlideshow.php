<?php

namespace Addons\AiVideoCreator\Jobs;

use Addons\AiVideoCreator\Services\SlideshowBuilderService;
use Illuminate\Support\Facades\Storage;

class GenerateSlideshow extends BaseGenerateJob
{
    public function handle(SlideshowBuilderService $builder): void
    {
        $render = $this->findRender();
        if (! $render) return;

        $this->startProcessing($render);

        $outputPath = $builder->build($render);

        $render->update([
            'status' => 'completed',
            'file_path' => $outputPath,
            'file_url' => Storage::url($outputPath),
            'file_size_bytes' => Storage::size($outputPath),
            'completed_at' => now(),
        ]);

        broadcast(new \Addons\AiVideoCreator\Events\VideoRenderCompleted($render->fresh()));
    }
}
