<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 9 of the settings refactor (see settings-refactor-plan.md).
 *
 * Blobs the `newsletter` group — the one cohesive group missed by the original sweep. Its
 * ~19 keys (newsletter_* popup/driver toggles + mailchimp_* provider config, incl the
 * encrypted mailchimp_api_key) were still flat rows read/written purely by the `group`
 * column (NewsletterController::getGroup('newsletter') / settings_set(..., 'newsletter')).
 *
 * Both `newsletter_` and `mailchimp_` route to the `newsletter` blob. `mailchimp_` does not
 * collide with the existing `mail_` prefix (str_starts_with needs '_' at offset 4).
 *
 * On installs where the operator never configured newsletter, this is a no-op (no flat
 * rows to absorb); collapse is idempotent and blob-wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::collapseGroupToBlob('newsletter');
    }

    public function down(): void
    {
        Setting::expandBlobToFlat('newsletter');
    }
};
