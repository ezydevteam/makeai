<?php

namespace App\Services;

use App\Services\AI\ProviderRegistry;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class PlaygroundService
{
    public function share(array $snapshot): string
    {
        $uuid = (string) Str::uuid();
        $key = "makeai:playground_share:{$uuid}";

        Redis::setex($key, 604800, json_encode(array_merge($snapshot, [
            'created_at' => now()->toIso8601String(),
        ])));

        return $uuid;
    }

    public function getShare(string $uuid): ?array
    {
        $key = "makeai:playground_share:{$uuid}";
        $data = Redis::get($key);

        return $data ? json_decode($data, true) : null;
    }

    public function availableProviders(): array
    {
        $providers = ProviderRegistry::all();

        return array_map(function ($provider) {
            return [
                'name' => $provider->name(),
                'models' => $provider->models(),
            ];
        }, $providers);
    }
}
