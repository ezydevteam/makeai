<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChainCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $runUlid,
        public string $status,
        public int $totalTokens,
        public float $totalCredits,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('chain.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'chain.completed';
    }
}
