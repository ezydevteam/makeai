<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed admin roles, permissions, and the initial super admin account.
     */
    public function run(): void
    {
        // ═══════════════════════════════════════════
        // 1. Create Roles
        // ═══════════════════════════════════════════
        $superAdmin = AdminRole::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Full access to everything', 'is_system' => true]
        );

        $manager = AdminRole::firstOrCreate(
            ['slug' => 'manager'],
            ['name' => 'Manager', 'description' => 'Manage users and content', 'is_system' => false]
        );

        $support = AdminRole::firstOrCreate(
            ['slug' => 'support'],
            ['name' => 'Support', 'description' => 'Handle support tickets and user issues', 'is_system' => false]
        );

        $contentManager = AdminRole::firstOrCreate(
            ['slug' => 'content-manager'],
            ['name' => 'Content Manager', 'description' => 'Manage templates and content', 'is_system' => false]
        );

        // ═══════════════════════════════════════════
        // 2. Create Permissions (grouped)
        // ═══════════════════════════════════════════
        $permissions = [
            // Dashboard
            ['slug' => 'dashboard.view', 'name' => 'View Dashboard', 'group' => 'dashboard'],
            ['slug' => 'dashboard.analytics', 'name' => 'View Analytics', 'group' => 'dashboard'],

            // Users
            ['slug' => 'users.manage', 'name' => 'Manage Users & Community', 'group' => 'users'],
            ['slug' => 'users.view', 'name' => 'View Users', 'group' => 'users'],
            ['slug' => 'users.create', 'name' => 'Create Users', 'group' => 'users'],
            ['slug' => 'users.edit', 'name' => 'Edit Users', 'group' => 'users'],
            ['slug' => 'users.delete', 'name' => 'Delete Users', 'group' => 'users'],
            ['slug' => 'users.credits', 'name' => 'Manage User Credits', 'group' => 'users'],
            ['slug' => 'users.impersonate', 'name' => 'Impersonate Users', 'group' => 'users'],

            // Admins
            ['slug' => 'admins.view', 'name' => 'View Admins', 'group' => 'admins'],
            ['slug' => 'admins.create', 'name' => 'Create Admins', 'group' => 'admins'],
            ['slug' => 'admins.edit', 'name' => 'Edit Admins', 'group' => 'admins'],
            ['slug' => 'admins.delete', 'name' => 'Delete Admins', 'group' => 'admins'],

            // Roles
            ['slug' => 'roles.view', 'name' => 'View Roles', 'group' => 'roles'],
            ['slug' => 'roles.create', 'name' => 'Create Roles', 'group' => 'roles'],
            ['slug' => 'roles.edit', 'name' => 'Edit Roles', 'group' => 'roles'],
            ['slug' => 'roles.delete', 'name' => 'Delete Roles', 'group' => 'roles'],

            // Settings
            ['slug' => 'settings.manage', 'name' => 'Manage All Settings', 'group' => 'settings'],
            ['slug' => 'settings.general', 'name' => 'General Settings', 'group' => 'settings'],
            ['slug' => 'settings.ai', 'name' => 'AI Settings', 'group' => 'settings'],
            ['slug' => 'settings.payment', 'name' => 'Payment Settings', 'group' => 'settings'],
            ['slug' => 'settings.mail', 'name' => 'Mail Settings', 'group' => 'settings'],
            ['slug' => 'settings.security', 'name' => 'Security Settings', 'group' => 'settings'],
            ['slug' => 'settings.license', 'name' => 'License Settings', 'group' => 'settings'],
            ['slug' => 'settings.gdpr', 'name' => 'GDPR Settings', 'group' => 'settings'],

            // AI Management
            ['slug' => 'ai.tools', 'name' => 'Manage AI Tools & Access', 'group' => 'ai'],
            ['slug' => 'ai.templates', 'name' => 'Manage AI Templates', 'group' => 'ai'],
            ['slug' => 'ai.models', 'name' => 'Manage AI Models', 'group' => 'ai'],
            ['slug' => 'ai.providers', 'name' => 'Manage AI Providers', 'group' => 'ai'],
            ['slug' => 'ai.logs', 'name' => 'View AI Logs', 'group' => 'ai'],

            // Content
            ['slug' => 'content.pages', 'name' => 'Manage Pages', 'group' => 'content'],
            ['slug' => 'content.blog', 'name' => 'Manage Blog', 'group' => 'content'],
            ['slug' => 'content.comments', 'name' => 'Manage Comments', 'group' => 'content'],
            ['slug' => 'content.faq', 'name' => 'Manage FAQ', 'group' => 'content'],
            ['slug' => 'content.testimonials', 'name' => 'Manage Testimonials', 'group' => 'content'],

            // Plans & Pricing
            ['slug' => 'plans.view', 'name' => 'View Plans', 'group' => 'plans'],
            ['slug' => 'plans.create', 'name' => 'Create Plans', 'group' => 'plans'],
            ['slug' => 'plans.edit', 'name' => 'Edit Plans', 'group' => 'plans'],
            ['slug' => 'plans.delete', 'name' => 'Delete Plans', 'group' => 'plans'],

            // Payments
            ['slug' => 'payments.view', 'name' => 'View Payments', 'group' => 'payments'],
            ['slug' => 'payments.refund', 'name' => 'Process Refunds', 'group' => 'payments'],
            ['slug' => 'payments.gateways', 'name' => 'Manage Gateways', 'group' => 'payments'],

            // Addons
            ['slug' => 'addons.view', 'name' => 'View Addons', 'group' => 'addons'],
            ['slug' => 'addons.install', 'name' => 'Install Addons', 'group' => 'addons'],
            ['slug' => 'addons.manage', 'name' => 'Manage Addons', 'group' => 'addons'],

            // Themes
            ['slug' => 'themes.view', 'name' => 'View Themes', 'group' => 'themes'],
            ['slug' => 'themes.activate', 'name' => 'Activate Themes', 'group' => 'themes'],
            ['slug' => 'themes.customize', 'name' => 'Customize Themes', 'group' => 'themes'],

            // Translations
            ['slug' => 'translations.manage', 'name' => 'Manage Languages & Translations', 'group' => 'translations'],
            ['slug' => 'translations.view', 'name' => 'View Translations', 'group' => 'translations'],
            ['slug' => 'translations.edit', 'name' => 'Edit Translations', 'group' => 'translations'],
            ['slug' => 'translations.languages', 'name' => 'Manage Languages', 'group' => 'translations'],

            // Reports
            ['slug' => 'reports.revenue', 'name' => 'Revenue Reports', 'group' => 'reports'],
            ['slug' => 'reports.usage', 'name' => 'Usage Reports', 'group' => 'reports'],
            ['slug' => 'reports.export', 'name' => 'Export Reports', 'group' => 'reports'],

            // Support
            ['slug' => 'support.tickets', 'name' => 'Manage Tickets', 'group' => 'support'],
            ['slug' => 'support.respond', 'name' => 'Respond to Tickets', 'group' => 'support'],
            ['slug' => 'support.departments', 'name' => 'Manage Support Departments', 'group' => 'support'],
            ['slug' => 'support.canned', 'name' => 'Manage Canned Responses', 'group' => 'support'],
        ];

        foreach ($permissions as $perm) {
            AdminPermission::firstOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        // ═══════════════════════════════════════════
        // 3. Assign Permissions to Roles
        // ═══════════════════════════════════════════

        $superAdmin->syncDefaultPermissions();
        $manager->syncDefaultPermissions();
        $support->syncDefaultPermissions();
        $contentManager->syncDefaultPermissions();

        // ═══════════════════════════════════════════
        // 4. Create Default Super Admin
        // ═══════════════════════════════════════════
        Admin::firstOrCreate(
            ['email' => 'admin@makeai.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'role_id' => $superAdmin->id,
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
    }
}
