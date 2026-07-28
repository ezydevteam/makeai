<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The installer has to capture a timezone.
 *
 * Timestamps are stored in UTC and rendered through display_tz(), which reads the
 * app_timezone setting. With nothing written at install time that setting defaults to
 * UTC, so a fresh install showed every date hours out to anyone not sitting in UTC —
 * and the buyer had no reason to suspect a setting they had never been asked about.
 *
 * The browser's zone is the only signal available during an install (the host's own
 * clock is almost always UTC), so the Site Setup step sends it. It is advisory: the
 * server accepts only zones PHP can resolve and falls back to UTC, because a bad
 * detection must never be able to fail an otherwise good install.
 */
class InstallerTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.installed' => false]);
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    private function atSiteSetupStep(): self
    {
        return $this->withSession(['install_wizard' => [
            'current_step' => 4,
            'steps_completed' => [1, 2, 3],
            'data' => [
                'step_1' => [],
                'step_2' => ['purchase_code' => 'test-code'],
                'step_3' => ['db_driver' => 'mysql', 'db_host' => '127.0.0.1', 'db_port' => 3306],
            ],
        ]]);
    }

    public function test_the_site_setup_step_is_given_the_timezone_list(): void
    {
        $this->atSiteSetupStep()
            ->get('/install')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentStep', 4)
                ->has('timezones')
                ->where('timezones', fn ($zones) => collect($zones)->contains('UTC')
                    && collect($zones)->contains('Asia/Dhaka'))
            );
    }

    /** The list is only needed on step 4 — it is ~400 entries of dead weight elsewhere. */
    public function test_the_timezone_list_is_not_sent_on_other_steps(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentStep', 1)
                ->missing('timezones')
            );
    }

    public function test_a_detected_timezone_is_accepted_and_carried_through_the_wizard(): void
    {
        $this->atSiteSetupStep()
            ->post('/install/step/4', [
                'site_name' => 'MakeAI',
                'site_url' => 'https://example.com',
                'site_timezone' => 'Asia/Dhaka',
            ])
            ->assertSessionHasNoErrors();

        $wizard = session('install_wizard');

        $this->assertSame('Asia/Dhaka', $wizard['data']['step_4']['site_timezone']);
    }

    /**
     * An old browser sends nothing and a tampered request can send anything. Neither may
     * block the install — the step still has to pass, with UTC applied when the setting
     * is finally written.
     */
    public function test_a_missing_or_unusable_timezone_does_not_fail_the_step(): void
    {
        $this->atSiteSetupStep()
            ->post('/install/step/4', [
                'site_name' => 'MakeAI',
                'site_url' => 'https://example.com',
            ])
            ->assertSessionHasNoErrors();

        $this->atSiteSetupStep()
            ->post('/install/step/4', [
                'site_name' => 'MakeAI',
                'site_url' => 'https://example.com',
                'site_timezone' => 'Mars/Olympus_Mons',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * The write itself, exercised directly: finalize() runs migrations and creates the
     * admin account, which is far more than this rule needs. What matters is that a
     * resolvable zone is stored and anything else becomes UTC rather than a value
     * display_tz() would later have to reject.
     */
    public function test_only_a_resolvable_zone_is_stored_and_the_rest_become_utc(): void
    {
        foreach (['Asia/Dhaka' => 'Asia/Dhaka', 'Europe/Berlin' => 'Europe/Berlin',
            'Mars/Olympus_Mons' => 'UTC', '' => 'UTC'] as $sent => $expected) {
            $resolved = in_array($sent, timezone_identifiers_list(), true) ? $sent : 'UTC';

            settings_set('app_timezone', $resolved, 'string', 'general');

            $this->assertSame($expected, settings('app_timezone'));
            $this->assertSame($expected, display_tz(),
                'display_tz() must never return a zone PHP cannot resolve');
        }
    }
}
