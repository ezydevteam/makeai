<?php

namespace Tests\Feature;

use App\Support\EnvFilePermissions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The only part of the nginx exposure problem PHP can act on.
 *
 * The distribution layout keeps the app in <webroot>/core, private by web server deny
 * rules that nginx does not read — so an unconfigured VPS serves core/.env in the clear.
 * A server config cannot be written or reloaded from PHP, but file permissions can: on
 * the usual split (PHP-FPM as the site user, nginx workers as nginx/www-data) mode 0600
 * means the worker cannot open the file and the leak becomes a 403.
 */
class EnvFilePermissionsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('app/testing/env-perms-'.getmypid().'.env');
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, "APP_KEY=base64:test\nDB_PASSWORD=hunter2\n");
    }

    protected function tearDown(): void
    {
        File::delete($this->path);
        parent::tearDown();
    }

    public function test_a_group_or_world_readable_file_is_tightened_to_owner_only(): void
    {
        if (! $this->posixModesSupported()) {
            $this->markTestSkipped('Filesystem does not honour POSIX permission bits.');
        }

        chmod($this->path, 0644);
        $this->assertTrue(EnvFilePermissions::isWorldOrGroupReadable($this->path));

        $this->assertTrue(EnvFilePermissions::harden($this->path));

        $this->assertSame(0600, EnvFilePermissions::mode($this->path));
        $this->assertFalse(EnvFilePermissions::isWorldOrGroupReadable($this->path));
    }

    public function test_an_already_secure_file_is_reported_secure_without_change(): void
    {
        if (! $this->posixModesSupported()) {
            $this->markTestSkipped('Filesystem does not honour POSIX permission bits.');
        }

        chmod($this->path, 0600);

        $this->assertTrue(EnvFilePermissions::harden($this->path));
        $this->assertSame(0600, EnvFilePermissions::mode($this->path));
    }

    /** Contents must survive: this changes who may read the file, not what it says. */
    public function test_the_file_contents_are_untouched(): void
    {
        EnvFilePermissions::harden($this->path);

        $this->assertStringContainsString('DB_PASSWORD=hunter2', File::get($this->path));
    }

    /**
     * Called during installation and from a health check, so a missing file must be a
     * plain false rather than an exception that fails either.
     */
    public function test_a_missing_file_is_false_not_an_exception(): void
    {
        $this->assertFalse(EnvFilePermissions::harden($this->path.'.nope'));
        $this->assertNull(EnvFilePermissions::mode($this->path.'.nope'));
        $this->assertFalse(EnvFilePermissions::isWorldOrGroupReadable($this->path.'.nope'));
    }

    /**
     * Windows reports 0666 regardless of chmod, so the assertions above are meaningless
     * there. Detected rather than assumed from PHP_OS, since the suite also runs on CI.
     */
    private function posixModesSupported(): bool
    {
        chmod($this->path, 0600);
        clearstatcache(true, $this->path);

        return (fileperms($this->path) & 0777) === 0600;
    }
}
