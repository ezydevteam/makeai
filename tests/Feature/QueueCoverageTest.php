<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every queue the code dispatches to must be covered by every worker configuration.
 *
 * This pins a bug that shipped and was invisible on the affected site for as long as it
 * took someone to notice a missing feature. `queue:work` with no --queue processes ONLY
 * the queue named "default". MakeAI dispatches to ten named queues, and the shared-hosting
 * cron in deploy/cron.txt omitted --queue entirely — so on every shared-hosting install
 * following the shipped instructions, nine queues were never processed. Generation
 * succeeded, the page looked correct, and the sign-in OTP email simply never arrived.
 *
 * The failure mode is silence, which is why it needs a test rather than a code review:
 * adding a job on a new queue is a one-line change that breaks a deployment document
 * nobody re-reads, and nothing anywhere fails until a user reports a missing email.
 */
class QueueCoverageTest extends TestCase
{
    /** Queue names the application actually dispatches to, read from source. */
    private function dispatchedQueues(): array
    {
        $queues = [];

        foreach ([base_path('app'), base_path('addons')] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                if (preg_match_all("/onQueue\(\s*'([a-z_-]+)'\s*\)/", (string) file_get_contents($file->getPathname()), $m)) {
                    foreach ($m[1] as $queue) {
                        $queues[$queue] = true;
                    }
                }
            }
        }

        ksort($queues);

        return array_keys($queues);
    }

    /** The --queue=a,b,c list out of a shipped deployment file. */
    private function queuesInFile(string $path): array
    {
        $this->assertFileExists($path, 'shipped deployment file is missing');

        preg_match('/--queue=([a-z,_-]+)/', (string) file_get_contents($path), $m);

        $this->assertNotEmpty(
            $m[1] ?? '',
            basename($path).' has no --queue= list. Without it queue:work processes only "default" '
            .'and every other queue is silently never processed.'
        );

        $queues = array_unique(explode(',', $m[1]));
        sort($queues);

        return $queues;
    }

    public function test_the_shared_hosting_cron_covers_every_queue(): void
    {
        $missing = array_diff($this->dispatchedQueues(), $this->queuesInFile(base_path('distribution/deploy/cron.txt')));

        $this->assertSame([], array_values($missing),
            'distribution/deploy/cron.txt does not process: '.implode(', ', $missing)
            .'. Shared-hosting buyers follow this file verbatim, so those queues would never run.');
    }

    public function test_the_supervisor_config_covers_every_queue(): void
    {
        $missing = array_diff($this->dispatchedQueues(), $this->queuesInFile(base_path('distribution/deploy/supervisor.conf.example')));

        $this->assertSame([], array_values($missing),
            'distribution/deploy/supervisor.conf.example does not process: '.implode(', ', $missing));
    }

    public function test_horizon_covers_every_queue(): void
    {
        $horizon = config('horizon.defaults', []);

        $configured = [];
        foreach ($horizon as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
                $configured[$queue] = true;
            }
        }

        $this->assertNotEmpty($configured, 'horizon.defaults defines no queues at all');

        $missing = array_diff($this->dispatchedQueues(), array_keys($configured));

        $this->assertSame([], array_values($missing),
            'config/horizon.php does not process: '.implode(', ', $missing)
            .'. Redis installs run Horizon, so those queues would never run there.');
    }

    public function test_the_admin_panels_queue_list_covers_every_queue(): void
    {
        // This constant builds the copy-paste cron entry shown to admins and decides
        // which Redis lists are counted when looking for waiting work. If it drifts, the
        // panel hands out a command that silently skips queues — the original bug, moved
        // from a text file into the product.
        $missing = array_diff(
            $this->dispatchedQueues(),
            \App\Http\Controllers\Admin\System\SystemController::WORKER_QUEUES
        );

        $this->assertSame([], array_values($missing),
            'SystemController::WORKER_QUEUES does not include: '.implode(', ', $missing));
    }

    public function test_the_queue_list_is_discovered_and_not_empty(): void
    {
        // Guards the three tests above: if the source scan silently returned nothing,
        // every array_diff would be empty and all of them would pass while checking nothing.
        $queues = $this->dispatchedQueues();

        $this->assertNotEmpty($queues, 'no onQueue() calls found — the source scan is broken');
        $this->assertContains('ai', $queues);
        $this->assertContains('otp', $queues);
    }
}
