<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'contact_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'contact_subject_mode', 'value' => 'text', 'type' => 'string'],
            ['key' => 'contact_subject_options', 'value' => '', 'type' => 'string'],
            ['key' => 'contact_success_message', 'value' => 'Your message has been sent successfully. We will get back to you soon!', 'type' => 'string'],
            // Left blank on purpose: ContactController falls back to site_support_email
            // and then mail_from_address, so notifications deliver before an admin sets this.
            ['key' => 'contact_notification_email', 'value' => '', 'type' => 'string'],
            ['key' => 'contact_auto_reply_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'contact_auto_reply_subject', 'value' => 'We received your message', 'type' => 'string'],
            ['key' => 'contact_auto_reply_message', 'value' => "Hi {name},\n\nThanks for reaching out. We've received your message and our team will get back to you shortly.\n\nBest regards", 'type' => 'string'],
        ];

        // Seed through the blob shim, not Setting::firstOrCreate(). firstOrCreate matches on
        // the flat `key` column, which no longer exists once a key lives inside its group
        // blob — so it saw every key as missing and re-inserted it as a flat row, on every
        // run, after FoundationSeeder had already collapsed. isPersisted() is blob-aware and
        // keeps the "never overwrite an operator's value" guarantee.
        foreach ($settings as $setting) {
            if (! Setting::isPersisted($setting['key'])) {
                settings_set($setting['key'], $setting['value'], $setting['type'], 'contact');
            }
        }
    }
}
