<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->app->runningUnitTests()) {
            $this->applyMailConfig();
        }
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
                'mailgun_domain' => settings('mailgun_domain'),
                'mailgun_secret' => settings('mailgun_secret'),
                'mailgun_endpoint' => settings('mailgun_endpoint', 'api.mailgun.net'),
                'ses_key' => settings('ses_key'),
                'ses_secret' => settings('ses_secret'),
                'ses_region' => settings('ses_region', 'us-east-1'),
                'postmark_token' => settings('postmark_token'),
                'sendgrid_api_key' => settings('sendgrid_api_key'),
            ];

            if (empty($settings['mail_driver'])) {
                return;
            }

            $config = [
                'transport' => $settings['mail_driver'],
                'host' => $settings['mail_host'] ?? '',
                'port' => $settings['mail_port'] ?? 587,
                'encryption' => $settings['mail_encryption'] ?? 'tls',
                'username' => $settings['mail_username'] ?? '',
                'password' => $settings['mail_password'] ?? '',
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ];

            if ($settings['mail_driver'] === 'mailgun') {
                Config::set('services.mailgun', [
                    'domain' => $settings['mailgun_domain'] ?? '',
                    'secret' => $settings['mailgun_secret'] ?? '',
                    'endpoint' => $settings['mailgun_endpoint'] ?? 'api.mailgun.net',
                ]);
                Config::set('mail.mailers.mailgun', ['transport' => 'mailgun']);
            }

            if ($settings['mail_driver'] === 'ses') {
                Config::set('services.ses', [
                    'key' => $settings['ses_key'] ?? '',
                    'secret' => $settings['ses_secret'] ?? '',
                    'region' => $settings['ses_region'] ?? 'us-east-1',
                ]);
            }

            if ($settings['mail_driver'] === 'postmark') {
                Config::set('services.postmark.key', $settings['postmark_token'] ?? '');
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

            Config::set('mail.mailers.smtp', array_merge(Config::get('mail.mailers.smtp', []), $config));
            Config::set('mail.default', $settings['mail_driver']);

            Config::set('mail.from', [
                'address' => $settings['mail_from_address'] ?? Config::get('mail.from.address'),
                'name' => $settings['mail_from_name'] ?? Config::get('mail.from.name'),
            ]);
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the app if table doesn't exist yet
        }
    }
}
