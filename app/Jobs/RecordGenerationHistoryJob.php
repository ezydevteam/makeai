<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GenerationHistoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordGenerationHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $user,
        protected array $data,
    ) {
        $this->onQueue('ai');
    }

    public function handle(GenerationHistoryService $service): void
    {
        $service->record($this->user, $this->data);
    }
}
