<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Services\AiAssistantService;
use Addons\AiAssistant\Tests\AssistantTestCase;
use Illuminate\Support\Facades\Cache;

class DailyLimitTest extends AssistantTestCase
{
    private function service(): AiAssistantService
    {
        return app(AiAssistantService::class);
    }

    // ─── tiering ─────────────────────────────────────────────

    public function test_limit_tier_depends_on_who_is_asking(): void
    {
        $this->setLimits(guest: 5, member: 20, pro: 50);

        $this->assertSame(5, $this->service()->dailyLimitFor(null), 'guest tier');
        $this->assertSame(20, $this->service()->dailyLimitFor($this->freeUser()), 'member tier');

        // The pro tier only applies where a paid tier can actually exist.
        $this->useMeteredMode();
        $this->assertSame(50, $this->service()->dailyLimitFor($this->proUser()), 'pro tier when billing is on');
    }

    public function test_zero_means_unlimited(): void
    {
        $this->setLimits(guest: 0, member: 0, pro: 0);

        $this->assertTrue($this->service()->checkDailyLimit($this->freeUser(), 'sess'));
        $this->assertNull($this->service()->remainingToday($this->freeUser()));
    }

    public function test_member_limit_is_enforced_after_the_allowance_is_spent(): void
    {
        $this->setLimits(guest: 0, member: 2, pro: 0);
        $user = $this->freeUser();

        $this->assertTrue($this->service()->checkDailyLimit($user, 'sess'));
        $this->service()->incrementDailyCount($user, 'sess');
        $this->service()->incrementDailyCount($user, 'sess');
        $this->assertFalse($this->service()->checkDailyLimit($user, 'sess'), 'third message is over the limit of 2');
    }

    /**
     * The guest bypass. Guests are keyed by IP, never by the client-supplied session id —
     * otherwise a guest could mint a fresh quota on every request just by changing the id
     * they send. Same IP, different session, must share one counter.
     */
    public function test_guest_limit_is_keyed_by_ip_not_by_session_id(): void
    {
        $this->setLimits(guest: 1, member: 0, pro: 0);

        $this->assertTrue($this->service()->checkDailyLimit(null, 'session-A'));
        $this->service()->incrementDailyCount(null, 'session-A');

        // A brand-new session id from the same IP must NOT reset the guest's quota.
        $this->assertFalse(
            $this->service()->checkDailyLimit(null, 'a-totally-different-session'),
            'changing session_id must not grant a guest a fresh allowance'
        );
    }

    /**
     * The counter uses the Cache facade (not the Redis facade), so it works on whatever
     * store the install runs — this codebase ships CACHE_STORE=database.
     */
    public function test_counter_is_stored_via_the_cache_facade(): void
    {
        $this->setLimits(guest: 0, member: 10, pro: 0);
        $user = $this->freeUser();

        $this->service()->incrementDailyCount($user, 'sess');

        $key = 'addon_ai_assistant.limit.user.' . $user->id . '.' . today()->toDateString();
        $this->assertSame(1, (int) Cache::get($key), 'the count must be readable straight from the cache store');
    }

    /**
     * Fail CLOSED. The old code returned true when the store threw, turning any install
     * without the expected backend into an uncapped AI endpoint. A refused message is
     * recoverable; an unmetered spend surface is not.
     */
    public function test_check_fails_closed_when_the_cache_throws(): void
    {
        $this->setLimits(guest: 0, member: 5, pro: 0);
        $user = $this->freeUser();

        Cache::shouldReceive('get')->andThrow(new \RuntimeException('cache down'));

        $this->assertFalse(
            $this->service()->checkDailyLimit($user, 'sess'),
            'a cache failure must deny, not allow'
        );
    }
}
