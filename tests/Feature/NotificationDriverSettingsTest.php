<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rule::requiredIf(false) drops the requirement but does not make a field
 * optional, so `string` still ran against the null the form posts for whichever
 * driver was NOT selected — saving Reverb failed on "the pusher.app id field
 * must be a string".
 */
class NotificationDriverSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function save(array $overrides): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.settings.update'), array_merge([
                'notifications_enabled' => true,
                'notifications_polling_interval' => 30000,
            ], $overrides));
    }

    public function test_reverb_saves_while_pusher_fields_are_null(): void
    {
        $this->save([
            'notifications_driver' => 'reverb',
            'reverb' => ['app_id' => 'app', 'app_key' => 'key', 'app_secret' => 'secret', 'host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http'],
            'pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null],
        ])->assertSessionHasNoErrors();

        $this->assertSame('reverb', settings('notifications_driver'));
        $this->assertSame('app', settings('notifications_reverb_app_id'));
    }

    public function test_pusher_saves_while_reverb_fields_are_null(): void
    {
        $this->save([
            'notifications_driver' => 'pusher',
            'reverb' => ['app_id' => null, 'app_key' => null, 'app_secret' => null, 'host' => null, 'port' => null, 'scheme' => null],
            'pusher' => ['app_id' => 'pid', 'key' => 'pkey', 'secret' => 'psecret', 'cluster' => 'mt1'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('pusher', settings('notifications_driver'));
        $this->assertSame('pid', settings('notifications_pusher_app_id'));
    }

    public function test_polling_saves_with_both_credential_sets_null(): void
    {
        $this->save([
            'notifications_driver' => 'polling',
            'reverb' => ['app_id' => null, 'app_key' => null, 'app_secret' => null, 'host' => null, 'port' => null, 'scheme' => null],
            'pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null],
        ])->assertSessionHasNoErrors();

        $this->assertSame('polling', settings('notifications_driver'));
    }

    /** The relaxation must not let a driver be selected without its credentials. */
    public function test_selected_driver_still_requires_its_own_credentials(): void
    {
        $this->save([
            'notifications_driver' => 'pusher',
            'reverb' => ['app_id' => null, 'app_key' => null, 'app_secret' => null],
            'pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null],
        ])->assertSessionHasErrors(['pusher.app_id', 'pusher.key', 'pusher.secret', 'pusher.cluster']);

        $this->save([
            'notifications_driver' => 'reverb',
            'reverb' => ['app_id' => null, 'app_key' => null, 'app_secret' => null],
            'pusher' => ['app_id' => null, 'key' => null, 'secret' => null, 'cluster' => null],
        ])->assertSessionHasErrors(['reverb.app_id', 'reverb.app_key', 'reverb.app_secret']);
    }
}
