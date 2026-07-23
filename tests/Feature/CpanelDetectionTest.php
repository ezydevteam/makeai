<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\System\SystemController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * isCpanelDetected() used to call filled(getenv('CPANEL')). getenv() returns
 * boolean false for a missing variable and filled(false) is true, so the first
 * condition short-circuited the whole check to true and the "cPanel detected"
 * banner showed on every install — Windows and Plesk boxes included.
 */
class CpanelDetectionTest extends TestCase
{
    private ReflectionMethod $detect;

    private SystemController $controller;

    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = app(SystemController::class);
        $this->detect = new ReflectionMethod(SystemController::class, 'isCpanelDetected');
        $this->detect->setAccessible(true);

        foreach (['CPANEL', 'cpanel', 'HOME', 'USERPROFILE'] as $key) {
            $this->originalEnv[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            $value === false ? putenv($key) : putenv("{$key}={$value}");
        }

        parent::tearDown();
    }

    private function detected(): bool
    {
        return $this->detect->invoke($this->controller);
    }

    public function test_not_detected_when_no_signal_is_present(): void
    {
        putenv('HOME='.sys_get_temp_dir());

        $this->assertFalse($this->detected());
    }

    public function test_detected_from_the_cpanel_env_var(): void
    {
        putenv('CPANEL=1');

        $this->assertTrue($this->detected());
    }

    public function test_detected_from_a_dot_cpanel_home_directory(): void
    {
        $home = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cpanel-detect-'.uniqid();
        mkdir($home.DIRECTORY_SEPARATOR.'.cpanel', 0777, true);
        putenv("HOME={$home}");

        try {
            $this->assertTrue($this->detected());
        } finally {
            rmdir($home.DIRECTORY_SEPARATOR.'.cpanel');
            rmdir($home);
        }
    }

    public function test_falls_back_to_userprofile_when_home_is_unset(): void
    {
        $home = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cpanel-detect-'.uniqid();
        mkdir($home.DIRECTORY_SEPARATOR.'.cpanel', 0777, true);
        putenv("USERPROFILE={$home}");

        try {
            $this->assertTrue($this->detected());
        } finally {
            rmdir($home.DIRECTORY_SEPARATOR.'.cpanel');
            rmdir($home);
        }
    }
}
