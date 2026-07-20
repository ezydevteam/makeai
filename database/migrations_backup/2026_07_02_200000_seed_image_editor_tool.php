<?php

use Illuminate\Database\Migrations\Migration;

/**
 * RETIRED. The Image Editor tool (and image generation in general) is now
 * addon-provided, not core. This migration no longer seeds anything; existing
 * installs have the tool removed by
 * 2026_07_06_000002_remove_addon_only_integrations_and_image_editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op — Image Editor moved to an addon.
    }

    public function down(): void
    {
        // no-op
    }
};
