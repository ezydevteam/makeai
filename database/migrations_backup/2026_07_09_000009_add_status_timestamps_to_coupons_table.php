<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add nullable `activated_at` / `published_at` timestamps to coupons so admin
 * analytics can measure real week-over-week movement of the (toggleable)
 * is_active and show_in_header states — the coupons table has no status history
 * otherwise, and filtering the CURRENT flag by created_at only measured age.
 *
 * Stamped by the Coupon model's `saving` hook whenever those flags flip. Existing
 * rows keep a null timestamp and are treated as "already active/published before
 * the window" by the dashboard, so no backfill is required.
 *
 * Guarded (hasColumn) so it is safe to re-run on installs that already have them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('coupons', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('show_in_header');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            foreach (['activated_at', 'published_at'] as $column) {
                if (Schema::hasColumn('coupons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
