<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AddonHookService
{
    private array $listeners = [];

    /**
     * Register a hook listener. Called from addon ServiceProvider::boot().
     */
    public function listen(string $event, callable $handler): void
    {
        $this->listeners[$event][] = $handler;
    }

    /**
     * Fire a hook event. Called from core code.
     */
    public function fire(string $event, mixed ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $handler) {
            try {
                $handler(...$args);
            } catch (\Exception $e) {
                Log::warning("Addon hook '{$event}' failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if any listeners are registered for an event.
     */
    public function hasListeners(string $event): bool
    {
        return ! empty($this->listeners[$event]);
    }
}
