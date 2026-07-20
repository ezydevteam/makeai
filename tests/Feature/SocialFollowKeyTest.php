<?php

namespace Tests\Feature;

use App\Services\SocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the social-follow api-key / external-id read path
 * (SocialService::apiKeySettingKey / externalIdSettingKey) must use the same setting
 * key format the write path (SocialCountersController::updateFollow) persists, i.e.
 * social_follow_api_key_{platform} — not social_follow_{platform}_api_key. The old
 * mismatch meant configured keys were written but never read.
 */
class SocialFollowKeyTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SocialService
    {
        return app(SocialService::class);
    }

    public function test_key_builders_use_the_persisted_format(): void
    {
        $service = $this->service();

        $this->assertSame('social_follow_api_key_facebook', $service->apiKeySettingKey('facebook'));
        $this->assertSame('social_follow_external_id_youtube', $service->externalIdSettingKey('youtube'));
    }

    public function test_a_key_written_the_way_the_admin_saves_it_is_read_back(): void
    {
        $service = $this->service();

        // Exactly how SocialCountersController::updateFollow persists a profile.
        settings_set('social_follow_api_key_youtube', 'yt-secret', 'encrypted', 'social');
        settings_set('social_follow_external_id_youtube', 'UC-123', 'string', 'social');

        // The service now reads them through its builders (previously a no-op miss).
        $this->assertSame('yt-secret', settings($service->apiKeySettingKey('youtube')));
        $this->assertSame('UC-123', settings($service->externalIdSettingKey('youtube')));
    }
}
