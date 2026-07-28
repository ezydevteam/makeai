<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\System\SystemController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The one thing in the distribution layout a buyer can get wrong by doing nothing.
 *
 * The app ships into <webroot>/core so nobody has to repoint a document root — unzip into
 * public_html and it works. The trade is that core/ sits inside the served tree, private
 * only because of deny rules: .htaccess on Apache/LiteSpeed, web.config on IIS.
 *
 * nginx reads neither. A buyer who unzips onto an nginx VPS and never applies
 * core/deploy/nginx.conf.example serves core/.env as plain text — database password,
 * APP_KEY, every API credential — and nothing on screen says so.
 *
 * The check must be wrong in the safe direction: silence is never reported as safety.
 */
class AppDirectoryExposureCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('system.app_dir_exposed');
        settings_set('site_url', 'https://buyer-site.test', 'string', 'general');
    }

    private function check(): array
    {
        return (new \ReflectionMethod(SystemController::class, 'appDirectoryExposureCheck'))
            ->invoke(app(SystemController::class));
    }

    /**
     * This repo is a standard checkout — app outside the webroot — so the check has
     * nothing to test and says so rather than firing a false alarm.
     */
    public function test_a_standard_layout_is_reported_as_not_applicable(): void
    {
        $result = $this->check();

        $this->assertSame('pass', $result['status']);
        $this->assertStringContainsString('Not applicable', $result['detail']);
    }

    /**
     * The rest of the behaviour is the response handling, which is what actually decides
     * whether a buyer is warned. Driven directly, since this checkout cannot be reshaped
     * into the packaged layout mid-test.
     */
    public function test_an_env_file_served_with_200_is_treated_as_exposed(): void
    {
        Http::fake(['*' => Http::response("APP_KEY=base64:abc\nDB_PASSWORD=hunter2", 200)]);

        $verdict = $this->classify();

        $this->assertSame('exposed', $verdict['state']);
    }

    /** A deny rule doing its job. */
    public function test_a_403_is_treated_as_protected(): void
    {
        Http::fake(['*' => Http::response('Forbidden', 403)]);

        $this->assertSame('protected', $this->classify()['state']);
    }

    /**
     * Hosts commonly answer 200 with a catch-all page for anything missing. Treating that
     * as exposure would cry wolf on every correctly configured site, so the body has to
     * look like an env file as well.
     */
    public function test_a_catch_all_200_page_is_not_mistaken_for_exposure(): void
    {
        Http::fake(['*' => Http::response('<!doctype html><h1>Page not found</h1>', 200)]);

        $this->assertSame('protected', $this->classify()['state']);
    }

    /**
     * Many hosts block a server from resolving its own public hostname. That proves
     * nothing, so it must report "could not verify" — never a pass.
     */
    public function test_an_unreachable_self_request_is_inconclusive_not_a_pass(): void
    {
        Http::fake(fn () => throw new \RuntimeException('cURL error 6: Could not resolve host'));

        $this->assertSame('unknown', $this->classify()['state']);
    }

    /** Invokes the real probe, so these tests cannot pass against a copy of the logic. */
    private function classify(): array
    {
        return (new \ReflectionMethod(SystemController::class, 'probeAppDirectory'))
            ->invoke(app(SystemController::class), 'core');
    }

    /** No site URL means nothing can be probed — again inconclusive, not a pass. */
    public function test_a_missing_site_url_is_inconclusive(): void
    {
        settings_set('site_url', '', 'string', 'general');
        config(['app.url' => '']);

        $this->assertSame('unknown', $this->classify()['state']);
    }
}
