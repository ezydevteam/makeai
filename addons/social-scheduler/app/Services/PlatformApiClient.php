<?php

namespace Addons\SocialScheduler\Services;

class PlatformApiClient
{
    public function __construct(
        public readonly string $platform,
        public readonly string $accessToken,
        public readonly ?string $pageId,
        public readonly \Illuminate\Http\Client\PendingRequest $http,
    ) {}
}
