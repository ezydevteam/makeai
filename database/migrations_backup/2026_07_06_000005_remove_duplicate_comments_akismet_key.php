<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spam filtering (Akismet) now lives solely under Extensions → Spam Filter
 * (external_spam_filter_*). The blog/comment settings pages duplicated it as
 * `comments_akismet_key`, which had no spam-check consumer. Remove the orphaned
 * key. Safe no-op if it was never set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'comments_akismet_key')->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: the setting is duplicated by the Extensions spam filter.
    }
};
