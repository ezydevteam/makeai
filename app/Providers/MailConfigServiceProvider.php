<?php

namespace App\Providers;

use App\Models\Setting;
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
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $this->applyMailConfig();
        }
    }

    protected function applyMailConfig(): void
    {
        try {
            $settings = Setting::getGroup('mail');

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
            }

            if ($settings['mail_driver'] === 'ses') {
                Config::set('services.ses', [
                    'key' => $settings['ses_key'] ?? '',
                    'secret' => $settings['ses_secret'] ?? '',
                    'region' => $settings['ses_region'] ?? 'us-east-1',
                ]);
            }

            Config::set('mail.mailers.smtp', array_merge(Config::get('mail.mailers.smtp', []), $config));
            Config::set('mail.default', $settings['mail_driver'] === 'smtp' ? 'smtp' : $settings['mail_driver']);

            Config::set('mail.from', [
                'address' => $settings['mail_from_address'] ?? Config::get('mail.from.address'),
                'name' => $settings['mail_from_name'] ?? Config::get('mail.from.name'),
            ]);
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the app if table doesn't exist yet
        }
    }
}
