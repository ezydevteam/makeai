<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class ProAccessSeeder extends Seeder
{
    /**
     * Enable subscription features for local/demo Pro access.
     */
    public function run(): void
    {
        settings_set('license_type', 2, 'integer', 'license');
        settings_set('license_status', 'valid', 'string', 'license');
        settings_set('subscriptions_enabled', true, 'boolean', 'features');

        $proPermissions = [
            ['slug' => 'plans.view', 'name' => 'View Plans', 'group' => 'plans'],
            ['slug' => 'plans.create', 'name' => 'Create Plans', 'group' => 'plans'],
            ['slug' => 'plans.edit', 'name' => 'Edit Plans', 'group' => 'plans'],
            ['slug' => 'plans.delete', 'name' => 'Delete Plans', 'group' => 'plans'],
            ['slug' => 'payments.view', 'name' => 'View Payments', 'group' => 'payments'],
            ['slug' => 'payments.refund', 'name' => 'Process Refunds', 'group' => 'payments'],
            ['slug' => 'payments.gateways', 'name' => 'Manage Gateways', 'group' => 'payments'],
        ];

        foreach ($proPermissions as $permission) {
            AdminPermission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $superAdminRole = AdminRole::where('slug', 'super-admin')->first();

        if ($superAdminRole) {
            $superAdminRole->permissions()->syncWithoutDetaching(AdminPermission::pluck('id'));
        }
    }
}
