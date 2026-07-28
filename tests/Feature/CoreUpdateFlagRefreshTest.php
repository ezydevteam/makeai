<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The update banner has to appear on its own.
 *
 * update_available moved in exactly two places: the "Check for updates" button, and the
 * updates:check command scheduled daily at 03:00. That schedule was the whole mechanism,
 * so wherever cron is not actually running — routine on shared hosting, and the reason
 * the Scheduler health check exists — the header banner and the sidebar dot never showed
 * at all. An operator had to already suspect an update existed in order to discover one.
 *
 * The panel now refreshes the flag itself when the answer has gone stale, after the
 * response is flushed so nothing waits on the License Server.
 */
class CoreUpdateFlagRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('core_update_check_lock');
    }

    private function refresh(): void
    {
        (new \ReflectionMethod(HandleInertiaRequests::class, 'refreshUpdateFlagIfStale'))
            ->invoke(app(HandleInertiaRequests::class));
    }

    public function test_a_recent_check_is_left_alone(): void
    {
        settings_set('update_last_checked', now()->subMinutes(30)->toDateTimeString(), 'string', 'system');

        $this->refresh();

        // Untouched means no lock was taken, so nothing was scheduled.
        $this->assertFalse(Cache::has('core_update_check_lock'));
    }

    public function test_a_stale_check_schedules_a_refresh(): void
    {
        settings_set('update_last_checked', now()->subHours(9)->toDateTimeString(), 'string', 'system');

        $this->refresh();

        $this->assertTrue(Cache::has('core_update_check_lock'));
    }

    /** A site that has never checked is the case that matters most on a fresh install. */
    public function test_never_having_checked_counts_as_stale(): void
    {
        settings_set('update_last_checked', '', 'string', 'system');

        $this->refresh();

        $this->assertTrue(Cache::has('core_update_check_lock'));
    }

    /**
     * Every admin request runs this. Without the lock, a burst of them would each start
     * their own outbound call to the License Server.
     */
    public function test_only_one_request_in_a_burst_claims_the_check(): void
    {
        settings_set('update_last_checked', now()->subDay()->toDateTimeString(), 'string', 'system');

        $this->refresh();
        $this->assertTrue(Cache::has('core_update_check_lock'));

        // A second pass must find the lock already held and do nothing further. Proven by
        // the lock's expiry not moving.
        $before = Cache::get('core_update_check_lock');
        $this->refresh();

        $this->assertSame($before, Cache::get('core_update_check_lock'));
    }

    /** A corrupt stored value must not wedge the check permanently. */
    public function test_an_unparseable_last_checked_is_treated_as_stale(): void
    {
        settings_set('update_last_checked', 'not a date', 'string', 'system');

        $this->refresh();

        $this->assertTrue(Cache::has('core_update_check_lock'));
    }
}
