<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give the internal AI system account a real identity.
 *
 * Until now "is this the system account?" was answered by comparing the email against
 * internalai@{APP_URL host} — which is derived from config at call time. Change APP_URL (a
 * domain migration, moving off a staging host) and the existing account stops matching: it
 * reappears in the admin users table as an ordinary, deletable customer, a fresh system
 * account is created alongside it, and the old one keeps its entire ai_usage_logs history
 * while nothing points at it any more.
 *
 * A column can't drift with config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('is_active')->index();
        });

        // Backfill the existing account, matched by the email rule that was in force until
        // this migration ran. Deliberately an EXACT match rather than 'internalai@%': the
        // flag grants exemption from credit limits and immunity from deletion, so a false
        // positive would hand a real user unlimited free AI and make them undeletable. An
        // exact match cannot false-positive — that address is occupied by the system account
        // itself, so no real user can hold it.
        DB::table('users')
            ->where('email', User::internalAiEmail())
            ->update(['is_internal' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_internal']);
            $table->dropColumn('is_internal');
        });
    }
};
