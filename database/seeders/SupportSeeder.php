<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use App\Models\Setting;
use App\Models\SupportDepartment;
use Illuminate\Database\Seeder;

class SupportSeeder extends Seeder
{
    public function run(): void
    {
        $supportRoleId = AdminRole::where('slug', 'support')->value('id');

        $departments = [
            ['name' => 'General', 'slug' => 'general', 'description' => 'General product and account questions.', 'sort_order' => 10],
            ['name' => 'Technical', 'slug' => 'technical', 'description' => 'Bug reports, errors, and technical help.', 'sort_order' => 20],
            ['name' => 'Billing', 'slug' => 'billing', 'description' => 'Payments, invoices, and subscription questions.', 'sort_order' => 30],
            ['name' => 'Feature Request', 'slug' => 'feature-request', 'description' => 'Ideas and product improvement requests.', 'sort_order' => 40],
        ];

        foreach ($departments as $department) {
            SupportDepartment::firstOrCreate(
                ['slug' => $department['slug']],
                [
                    ...$department,
                    'assigned_role_id' => $supportRoleId,
                    'is_active' => true,
                ]
            );
        }

        $settings = [
            ['key' => 'tickets_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'max_attachments_per_reply', 'value' => '5', 'type' => 'integer'],
            ['key' => 'max_attachment_size_mb', 'value' => '10', 'type' => 'integer'],
            ['key' => 'allowed_attachment_types', 'value' => 'jpg,png,gif,pdf,txt,zip,mp4', 'type' => 'string'],
            ['key' => 'auto_close_resolved_days', 'value' => '7', 'type' => 'integer'],
            ['key' => 'sla_first_response_hours', 'value' => '24', 'type' => 'integer'],
            ['key' => 'sla_resolution_hours', 'value' => '72', 'type' => 'integer'],
            ['key' => 'notify_admin_new_ticket', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'notify_user_reply', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'satisfaction_rating_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'ai_reply_suggestion', 'value' => '1', 'type' => 'boolean'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                [...$setting, 'group' => 'support']
            );
        }
    }
}
