<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend the mail_templates.category ENUM with 'support', 'export' and 'content'
 * so support-ticket, export-ready and moderation-approval templates can be seeded.
 *
 * Support tickets previously reused the 'admin_announcement' template, which also
 * meant ticket mail inherited the 'account' => 'admin' preference group and could
 * be silently suppressed by a user's notification preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mail_templates MODIFY category ENUM('auth','account','subscription','newsletter','custom','affiliate','support','export','content') NOT NULL");
        }
        // SQLite stores enums as plain text (no CHECK) — nothing to change.

        // 'referral_earned' grants account credits; 'affiliate_commission_earned' pays a
        // cash commission. Both shipped as "Commission Earned", which read as a duplicate
        // in admin, and the credits copy never interpolated the amount. The seeder uses
        // firstOrCreate, so existing rows need this rename here — but only where an admin
        // has not customized the template (last_edited_by IS NULL).
        DB::table('mail_templates')
            ->where('slug', 'referral_earned')
            ->whereNull('last_edited_by')
            ->update([
                'name' => 'Referral — Credits Earned',
                'subject' => 'You earned {credits} referral credits',
                'content' => '<h1>Referral Credits Earned</h1><p>Hi {user_name},</p><p><strong>{credits}</strong> referral credits have been added to your account because someone you referred signed up.</p><p><a href="{site_url}">View your affiliate dashboard</a></p>',
            ]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('mail_templates')
                ->whereIn('category', ['support', 'export', 'content'])
                ->update(['category' => 'custom']);

            DB::statement("ALTER TABLE mail_templates MODIFY category ENUM('auth','account','subscription','newsletter','custom','affiliate') NOT NULL");
        }
    }
};
