<?php

namespace Tests\Feature;

use App\Jobs\MigrateStorageFiles;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorageSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
    }

    private function admin(): Admin
    {
        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);

        return Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    public function test_switching_to_local_activates_immediately(): void
    {
        settings_set('storage_driver', 's3', 'string', 'storage');
        Setting::flushCache();

        $r = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.storage.settings.update'), ['driver' => 'local']);

        $r->assertRedirect();
        $r->assertSessionHasNoErrors();
        Setting::flushCache();
        $this->assertSame('local', settings('storage_driver'));
    }

    public function test_cancel_migration_clears_state(): void
    {
        Cache::put(MigrateStorageFiles::STATE_KEY, [
            'status' => 'running', 'total' => 5, 'processed' => 1, 'failed' => 0,
            'message' => 'Copying…', 'source' => 'local', 'target' => 's3',
        ], now()->addHour());

        $r = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.storage.settings.migrate.cancel'));

        $r->assertRedirect();
        $this->assertNull(Cache::get(MigrateStorageFiles::STATE_KEY));
    }

    public function test_migrate_is_blocked_while_one_is_running(): void
    {
        Cache::put(MigrateStorageFiles::STATE_KEY, [
            'status' => 'running', 'total' => 5, 'processed' => 1, 'failed' => 0,
            'message' => 'Copying…', 'source' => 'local', 'target' => 's3',
            'updated_at' => now()->toIso8601String(),
        ], now()->addHour());

        $r = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.storage.settings.migrate'), ['source' => 'local', 'target' => 's3']);

        $r->assertRedirect();
        $r->assertSessionHas('error');
    }

    public function test_requires_manage_permission(): void
    {
        $role = AdminRole::create(['name' => 'Editor', 'slug' => 'editor']);
        $weak = Admin::create([
            'name' => 'Weak', 'email' => 'weak@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->actingAs($weak, 'admin')
            ->post(route('admin.storage.settings.migrate.cancel'))
            ->assertForbidden();
    }
}
