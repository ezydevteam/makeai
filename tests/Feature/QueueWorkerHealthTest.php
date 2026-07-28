<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\System\SystemController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The queue health check must never report a pass it has not observed.
 *
 * The check it replaces was `DB::table('jobs')->count() === 0 || Cache::has('horizon:status')`,
 * which was wrong in the one direction that matters. The jobs table belongs to the
 * database driver alone, so on Redis it is permanently empty and the check reported
 * "Queue worker: Active" indefinitely while nothing consumed anything — a green light on
 * a site whose sign-in codes were never being delivered. Even on database it measured
 * backlog rather than liveness: a worker keeping up has an empty queue, so "healthy" and
 * "no worker has ever run" were indistinguishable.
 *
 * Liveness is now a heartbeat stamped after each processed job (AppServiceProvider), which
 * works on any driver. These tests pin the four outcomes, and in particular that an
 * unverifiable queue says so instead of guessing.
 */
class QueueWorkerHealthTest extends TestCase
{
    use RefreshDatabase;

    private function health(): array
    {
        $call = new ReflectionMethod(SystemController::class, 'queueHealth');
        $call->setAccessible(true);

        return $call->invoke(app(SystemController::class));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('last_queue_worker_run');
    }

    public function test_sync_needs_no_worker_and_passes(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertSame('pass', $this->health()['status']);
    }

    public function test_a_recent_heartbeat_is_a_pass(): void
    {
        config(['queue.default' => 'database']);
        Cache::put('last_queue_worker_run', now()->subMinutes(2)->toDateTimeString(), 1800);

        $health = $this->health();

        $this->assertSame('pass', $health['status']);
        $this->assertNull($health['suggestion']);
    }

    public function test_a_stale_heartbeat_is_not_a_pass(): void
    {
        config(['queue.default' => 'database']);
        Cache::put('last_queue_worker_run', now()->subHours(3)->toDateTimeString(), 1800);

        $this->assertNotSame('pass', $this->health()['status']);
    }

    public function test_waiting_jobs_with_no_heartbeat_is_a_failure(): void
    {
        config(['queue.default' => 'database']);

        \Illuminate\Support\Facades\DB::table('jobs')->insert([
            'queue' => 'ai',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $health = $this->health();

        // Work is queued and nothing has reported in — the one case that is unambiguous.
        $this->assertSame('fail', $health['status']);
        $this->assertNotNull($health['suggestion']);
    }

    public function test_redis_without_a_worker_does_not_report_a_false_pass(): void
    {
        // The exact shape of the shipped bug: on Redis the jobs table is empty, which the
        // old check read as healthy. Nothing has processed a job here, so the only honest
        // answers are "unverified" or "not running" — never "Active".
        config(['queue.default' => 'redis']);

        $this->assertNotSame('pass', $this->health()['status']);
    }

    public function test_an_unverifiable_queue_says_so_rather_than_guessing(): void
    {
        config(['queue.default' => 'database']);

        $health = $this->health();

        $this->assertSame('warn', $health['status']);
        $this->assertNotNull($health['suggestion']);
        // The suggestion has to carry the full --queue list, or an admin follows it and
        // still ends up processing only "default".
        foreach (SystemController::WORKER_QUEUES as $queue) {
            $this->assertStringContainsString($queue, $health['suggestion']);
        }
    }
}
