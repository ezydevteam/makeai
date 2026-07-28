<?php

namespace Tests\Feature;

use App\Providers\MailConfigServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A selected-but-unconfigured mail driver must not throw on every send.
 *
 * The demo shipped mail_driver=smtp with an empty mail_host and a port left at a dev
 * default, so every queued mail raised
 * "Connection could not be established with host \":1025\"" — thousands of log lines a
 * day, enough to bury the real errors underneath them. It took a while to find an actual
 * payment failure in that noise.
 *
 * Falling back to the log mailer keeps the mail visible without pretending it was
 * delivered, and `log` is already config/mail.php's own default.
 */
class UnconfiguredMailFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function isUnconfigured(array $settings): bool
    {
        $method = new \ReflectionMethod(MailConfigServiceProvider::class, 'driverIsUnconfigured');

        return $method->invoke(app(MailConfigServiceProvider::class, ['app' => app()]), $settings);
    }

    public function test_smtp_without_a_host_counts_as_unconfigured(): void
    {
        $this->assertTrue($this->isUnconfigured(['mail_driver' => 'smtp', 'mail_host' => '']));
        $this->assertTrue($this->isUnconfigured(['mail_driver' => 'smtp', 'mail_host' => null]));
    }

    public function test_smtp_with_a_host_is_left_alone(): void
    {
        $this->assertFalse($this->isUnconfigured(['mail_driver' => 'smtp', 'mail_host' => 'smtp.mailgun.org']));
    }

    /**
     * Credentials are deliberately NOT part of the check: plenty of relays authenticate
     * by IP, and downgrading those to the log mailer would silently stop real mail.
     */
    public function test_smtp_without_credentials_is_still_considered_configured(): void
    {
        $this->assertFalse($this->isUnconfigured([
            'mail_driver' => 'smtp',
            'mail_host' => 'localhost',
            'mail_username' => '',
            'mail_password' => '',
        ]));
    }

    public function test_sendgrid_and_ses_need_their_keys(): void
    {
        $this->assertTrue($this->isUnconfigured(['mail_driver' => 'sendgrid', 'sendgrid_api_key' => '']));
        $this->assertFalse($this->isUnconfigured(['mail_driver' => 'sendgrid', 'sendgrid_api_key' => 'SG.xxx']));

        $this->assertTrue($this->isUnconfigured(['mail_driver' => 'ses', 'ses_key' => 'k', 'ses_secret' => '']));
        $this->assertFalse($this->isUnconfigured(['mail_driver' => 'ses', 'ses_key' => 'k', 'ses_secret' => 's']));
    }

    /** Drivers with nothing to verify are never downgraded. */
    public function test_other_drivers_are_never_treated_as_unconfigured(): void
    {
        $this->assertFalse($this->isUnconfigured(['mail_driver' => 'log']));
        $this->assertFalse($this->isUnconfigured(['mail_driver' => 'sendmail']));
        $this->assertFalse($this->isUnconfigured(['mail_driver' => 'array']));
    }

    /** End to end: the demo's exact settings must leave the app on the log mailer. */
    public function test_the_demo_configuration_falls_back_to_the_log_mailer(): void
    {
        settings_set('mail_driver', 'smtp', 'string', 'mail');
        settings_set('mail_host', '', 'string', 'mail');
        settings_set('mail_port', '1025', 'string', 'mail');

        $method = new \ReflectionMethod(MailConfigServiceProvider::class, 'applyMailConfig');
        $method->invoke(app(MailConfigServiceProvider::class, ['app' => app()]));

        $this->assertSame('log', config('mail.default'));
    }
}
