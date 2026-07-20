<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTemplatedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300, 600];

    public function __construct(
        protected string $slug,
        protected string $to,
        protected array $data = []
    ) {
        $this->onQueue('emails');
    }

    public function handle(MailService $mailService): void
    {
        $mailService->processSend($this->slug, $this->to, $this->data);
    }
}
