<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /** Fingerprint of the mail config last handed to the mail manager. */
    protected ?string $appliedSignature = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        // Mail config normally lives in the database so buyers can change it from
        // the admin panel without shell access. That means it travels with the
        // database: a production dump restored locally would otherwise hand a dev
        // box live SMTP credentials, and this app mails real users on register,
        // password reset and email change. Local/staging set this to opt out and
        // keep whatever .env says (Mailpit, log, ...).
        if (filter_var(env('MAIL_CONFIG_FROM_ENV', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $this->applyMailConfig();
        $this->appliedSignature = $this->currentSignature();

        // A queue worker boots once and runs for days, so the config applied above
        // is a snapshot: an admin changing SMTP host, credentials or the from-name
        // would keep seeing queued mail go out under the old settings until someone
        // remembered to restart the worker. Re-apply before each job instead.
        Event::listen(JobProcessing::class, function (): void {
            $this->applyMailConfig();

            $signature = $this->currentSignature();

            if ($signature === $this->appliedSignature) {
                return;
            }

            // Only when something actually changed — MailManager caches resolved
            // mailers, and dropping them unconditionally would rebuild the SMTP
            // transport for every job and forfeit connection reuse.
            $this->appliedSignature = $signature;
            Mail::forgetMailers();
        });
    }

    /**
     * Fingerprint of the mail config currently in effect, used to detect an
     * admin-side settings change from inside a long-running worker.
     */
    protected function currentSignature(): string
    {
        $mailer = Config::get('mail.default');

        return md5(serialize([
            $mailer,
            Config::get('mail.mailers.'.$mailer),
            Config::get('mail.from'),
        ]));
    }

    /**
     * Whether the selected driver is missing the one credential it cannot work without.
     *
     * Deliberately narrow: only the setting whose absence guarantees a thrown exception
     * on the first send. Username and password are omitted on purpose — plenty of relay
     * setups authenticate by IP — and anything that merely *might* be wrong is left to
     * fail visibly rather than being silently downgraded.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function driverIsUnconfigured(array $settings): bool
    {
        return match ($settings['mail_driver']) {
            'smtp' => blank($settings['mail_host'] ?? null),
            'sendgrid' => blank($settings['sendgrid_api_key'] ?? null),
            'ses' => blank($settings['ses_key'] ?? null) || blank($settings['ses_secret'] ?? null),
            default => false,
        };
    }

    protected function applyMailConfig(): void
    {
        try {
            $settings = [
                'mail_driver' => settings('mail_driver'),
                'mail_host' => settings('mail_host'),
                'mail_port' => settings('mail_port', 587),
                'mail_username' => settings('mail_username'),
                'mail_password' => settings('mail_password'),
                'mail_encryption' => settings('mail_encryption', 'tls'),
                'mail_from_address' => settings('mail_from_address'),
                'mail_from_name' => settings('mail_from_name'),
                'ses_key' => settings('ses_key'),
                'ses_secret' => settings('ses_secret'),
                'ses_region' => settings('ses_region', 'us-east-1'),
                'sendgrid_api_key' => settings('sendgrid_api_key'),
            ];

            if (empty($settings['mail_driver'])) {
                return;
            }

            // A driver that is selected but not actually configured is worse than no
            // driver at all: every send throws instead of failing quietly. An empty host
            // with a port left at some dev default produces
            // "Connection could not be established with host \":1025\"" on every queued
            // mail — thousands of log lines a day on the demo, and enough noise to bury
            // real errors.
            //
            // Falling back to the log mailer keeps mail visible (an admin can read what
            // would have been sent) without pretending it was delivered, and it is the
            // same default config/mail.php already ships.
            if ($this->driverIsUnconfigured($settings)) {
                Config::set('mail.default', 'log');

                return;
            }

            // "None" is stored as the literal string 'null' — convert it to a real
            // null so the SMTP transport doesn't treat a truthy string as encryption.
            $encryption = $settings['mail_encryption'] ?? 'tls';
            $encryption = $encryption === 'null' ? null : $encryption;

            $config = [
                'transport' => $settings['mail_driver'],
                'host' => $settings['mail_host'] ?? '',
                'port' => $settings['mail_port'] ?? 587,
                'encryption' => $encryption,
                'username' => $settings['mail_username'] ?? '',
                'password' => $settings['mail_password'] ?? '',
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ];

            if ($settings['mail_driver'] === 'ses') {
                Config::set('services.ses', [
                    'key' => $settings['ses_key'] ?? '',
                    'secret' => $settings['ses_secret'] ?? '',
                    'region' => $settings['ses_region'] ?? 'us-east-1',
                ]);
            }

            if ($settings['mail_driver'] === 'sendgrid') {
                Config::set('mail.mailers.sendgrid', [
                    'transport' => 'smtp',
                    'host' => 'smtp.sendgrid.net',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'apikey',
                    'password' => $settings['sendgrid_api_key'] ?? '',
                    'timeout' => null,
                    'local_domain' => env('MAIL_EHLO_DOMAIN'),
                ]);
            }

            // Only overlay the SMTP credentials onto the smtp mailer when SMTP is
            // actually the selected driver — otherwise the smtp mailer's transport
            // would be corrupted with e.g. 'ses'/'sendgrid'.
            if ($settings['mail_driver'] === 'smtp') {
                Config::set('mail.mailers.smtp', array_merge(Config::get('mail.mailers.smtp', []), $config));
            }

            Config::set('mail.default', $settings['mail_driver']);

            // ?: not ?? — a blank saved value has to fall through too, otherwise an
            // empty string wins and mail goes out with no sender name. An unset
            // from-name follows the live site name rather than a build-time brand,
            // so a rebranded install does not keep signing mail as someone else.
            Config::set('mail.from', [
                'address' => $settings['mail_from_address'] ?: Config::get('mail.from.address'),
                'name' => $settings['mail_from_name'] ?: settings('app_name', Config::get('mail.from.name')),
            ]);
        } catch (\Throwable $e) {
            // Before the installer has run there is no settings table, and booting
            // must not break — that case stays silent. Afterwards, swallowing this
            // silently drops the app back to the .env mailer (often 'log'), so
            // mail vanishes with nothing to point at. Say so.
            if (filter_var(config('app.installed', false), FILTER_VALIDATE_BOOLEAN)) {
                Log::warning('MailConfigServiceProvider: could not apply mail settings — falling back to the .env mailer.', [
                    'mailer' => Config::get('mail.default'),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
