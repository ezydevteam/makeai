<?php

namespace Tests\Feature;

use App\Jobs\MigrateStorageFiles;
use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function disks(): array
    {
        return [Storage::fake('mig-src'), Storage::fake('mig-dst')];
    }

    public function test_copies_files_and_skips_healthcheck_probes(): void
    {
        [$src, $dst] = $this->disks();
        $src->put('avatars/1.png', 'one');
        $src->put('branding/logo.svg', 'logo');
        $src->put('healthcheck/probe.txt', 'probe');

        $res = (new MigrateStorageFiles('local', 's3'))->copyBetween($src, $dst);

        $dst->assertExists('avatars/1.png');
        $dst->assertExists('branding/logo.svg');
        $dst->assertMissing('healthcheck/probe.txt');
        $this->assertSame(0, $res['failed']);
        $this->assertSame(2, $res['processed'] - 1); // 3 iterated, 1 healthcheck skipped
    }

    public function test_copy_is_idempotent_on_rerun(): void
    {
        [$src, $dst] = $this->disks();
        $src->put('a/1.png', 'aaa');
        $src->put('a/2.png', 'bbbb');

        $first = (new MigrateStorageFiles('local', 's3'))->copyBetween($src, $dst);
        $this->assertSame(0, $first['skipped']);

        // Second run: both already present at the same size → both skipped, none re-copied.
        $second = (new MigrateStorageFiles('local', 's3'))->copyBetween($src, $dst);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(0, $second['failed']);
    }

    public function test_changed_size_triggers_recopy(): void
    {
        [$src, $dst] = $this->disks();
        $src->put('a/1.png', 'aaa');
        (new MigrateStorageFiles('local', 's3'))->copyBetween($src, $dst);

        // The source file grows — it must be re-copied, not skipped.
        $src->put('a/1.png', 'aaa-much-longer-now');
        $res = (new MigrateStorageFiles('local', 's3'))->copyBetween($src, $dst);

        $this->assertSame(0, $res['skipped']);
        $this->assertSame('aaa-much-longer-now', $dst->get('a/1.png'));
    }

    public function test_activate_on_complete_switches_driver_after_successful_copy(): void
    {
        settings_set('storage_driver', 'local', 'string', 'storage');
        Setting::flushCache();

        [$src, $dst] = $this->disks();
        $src->put('avatars/1.png', 'one');

        $job = new TestableMigrateStorageFiles('local', 's3', true);
        TestableMigrateStorageFiles::$src = $src;
        TestableMigrateStorageFiles::$dst = $dst;

        $job->handle();

        Setting::flushCache();
        $this->assertSame('s3', settings('storage_driver'), 'driver should flip only after copy completes');
        $dst->assertExists('avatars/1.png');

        $state = Cache::get(MigrateStorageFiles::STATE_KEY);
        $this->assertSame('completed', $state['status']);
    }

    public function test_does_not_activate_when_flag_is_false(): void
    {
        settings_set('storage_driver', 'local', 'string', 'storage');
        Setting::flushCache();

        [$src, $dst] = $this->disks();
        $src->put('avatars/1.png', 'one');

        $job = new TestableMigrateStorageFiles('local', 's3', false);
        TestableMigrateStorageFiles::$src = $src;
        TestableMigrateStorageFiles::$dst = $dst;
        $job->handle();

        Setting::flushCache();
        $this->assertSame('local', settings('storage_driver'), 'manual copy must not switch the driver');
    }

    public function test_failure_preserves_progress_counters(): void
    {
        // Simulate a run that got partway, then a failure callback fires.
        Cache::put(MigrateStorageFiles::STATE_KEY, [
            'status' => 'running', 'total' => 100, 'processed' => 42, 'failed' => 3,
            'message' => 'Copying…', 'source' => 'local', 'target' => 's3',
        ], now()->addHour());

        (new MigrateStorageFiles('local', 's3'))->failed(new \RuntimeException('worker died'));

        $state = Cache::get(MigrateStorageFiles::STATE_KEY);
        $this->assertSame('failed', $state['status']);
        $this->assertSame(100, $state['total'], 'counters must be preserved, not zeroed');
        $this->assertSame(42, $state['processed']);
        $this->assertSame(3, $state['failed']);
    }
}

class TestableMigrateStorageFiles extends MigrateStorageFiles
{
    public static Filesystem $src;
    public static Filesystem $dst;

    protected function resolveDisk(string $driver): Filesystem
    {
        return $driver === 'local' ? self::$src : self::$dst;
    }
}
