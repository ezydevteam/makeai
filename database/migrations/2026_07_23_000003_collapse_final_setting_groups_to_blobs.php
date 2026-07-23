<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Last of the settings blob refactor: the four groups still holding flat rows.
 *
 * - features: nine keys were registered and collapsed earlier; the other twelve
 *   toggles on the same admin screen never got a mapping and sat flat beside the
 *   blob they belong to.
 * - mail: `mail_` is prefix-routed, but the provider credentials that do not
 *   carry it (ses_*, sendgrid_api_key) stayed behind.
 * - reports: one key.
 * - system: install/update/scheduler bookkeeping. No blob existed at all. The
 *   per-task `cron_task_last_run_*` keys are prefix-routed rather than listed,
 *   since that key set grows with the scheduled-task registry.
 *
 * After this, `settings` holds blob rows only.
 */
return new class extends Migration
{
    private array $groups = ['features', 'mail', 'reports', 'system'];

    public function up(): void
    {
        foreach ($this->groups as $group) {
            Setting::collapseGroupToBlob($group);
        }
    }

    public function down(): void
    {
        foreach ($this->groups as $group) {
            Setting::expandBlobToFlat($group);
        }
    }
};
