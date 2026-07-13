<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove settings rows for the mailgun/postmark mail drivers, which were dropped
 * from the admin UI because their Symfony API-transport packages are not
 * installed in this build (selecting them threw "Unsupported mail transport").
 *
 * These keys are unseeded — they only exist if an admin previously saved them —
 * and are now read by no code path, so they are dead. SES (aws/aws-sdk-php) and
 * SendGrid (plain SMTP) remain fully supported.
 *
 * Irreversible by design (down() is a no-op): the values feed removed drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'mailgun_domain',
                'mailgun_secret',
                'mailgun_endpoint',
                'postmark_token',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible — keys feed the removed mailgun/postmark drivers.
    }
};
