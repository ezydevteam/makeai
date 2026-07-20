<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChainStepCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $runUlid,
        public int $stepNumber,
        public int $totalSteps,
        public string $toolSlug,
        public string $outputPreview,
    ) {}

    public function broadcastOn(): array
    {
        // Private: a chain's step output is the user's own generated content, and a
        // public channel let anyone subscribe to chain.{id} and read it.
        return [new PrivateChannel('chain.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'chain.step-completed';
    }
}
