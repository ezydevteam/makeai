<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Jobs\SendAdminNotificationSms;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * Admins can deliver a user notification as an SMS ("Text message"), but only when
 * the SMS gateway is active and the user has a phone number. See
 * UserManagementController::sendNotification and App\Jobs\SendAdminNotificationSms.
 */
class AdminUserNotificationSmsTest extends TestCase
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

    private function enableSmsGateway(): void
    {
        settings_set('external_sms_gateway_enabled', true, 'boolean', 'integrations');
        settings_set('external_sms_gateway_provider', 'twilio', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_account_sid', 'AC_test', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_auth_token', 'token_test', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_from', '+15550000000', 'string', 'integrations');
        Setting::flushCache();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Heads up',
            'message' => 'Your account was reviewed.',
            'level' => 'info',
            'deliver_via' => 'sms',
        ], $overrides);
    }

    private function verifiedSmsUser(): User
    {
        return User::factory()->create([
            'phone' => '2025550173', 'phone_country' => 'US', 'phone_verified_at' => now(),
        ]);
    }

    public function test_sms_notification_dispatches_the_sms_job(): void
    {
        Queue::fake();
        $this->enableSmsGateway();
        $user = $this->verifiedSmsUser();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.notification', $user->ulid), $this->payload())
            ->assertSessionHas('success');

        Queue::assertPushed(SendAdminNotificationSms::class);
    }

    public function test_in_app_sms_dispatches_the_sms_job(): void
    {
        Queue::fake();
        $this->enableSmsGateway();
        $user = $this->verifiedSmsUser();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.notification', $user->ulid), $this->payload(['deliver_via' => 'in_app_sms']))
            ->assertSessionHas('success');

        Queue::assertPushed(SendAdminNotificationSms::class);
    }

    public function test_sms_is_rejected_when_phone_unverified(): void
    {
        Queue::fake();
        $this->enableSmsGateway();
        // Phone present but not verified → the gate must reject.
        $user = User::factory()->create(['phone' => '2025550173', 'phone_country' => 'US', 'phone_verified_at' => null]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.notification', $user->ulid), $this->payload())
            ->assertSessionHas('error');

        Queue::assertNotPushed(SendAdminNotificationSms::class);
    }

    public function test_sms_is_rejected_when_user_has_no_phone(): void
    {
        Queue::fake();
        $this->enableSmsGateway();
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.notification', $user->ulid), $this->payload())
            ->assertSessionHas('error');

        Queue::assertNotPushed(SendAdminNotificationSms::class);
    }

    public function test_sms_is_rejected_when_gateway_disabled(): void
    {
        Queue::fake();
        // Verified phone, but gateway intentionally not enabled → still rejected.
        $user = User::factory()->create(['phone' => '2025550173', 'phone_country' => 'US', 'phone_verified_at' => now()]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.notification', $user->ulid), $this->payload())
            ->assertSessionHas('error');

        Queue::assertNotPushed(SendAdminNotificationSms::class);
    }

    public function test_job_sends_sms_with_title_and_message(): void
    {
        $this->enableSmsGateway();
        $user = $this->verifiedSmsUser();

        $body = null;
        Http::fake(function ($request) use (&$body) {
            $body = $request->data();

            return Http::response(['sid' => 'SM_test'], 201);
        });

        (new SendAdminNotificationSms($user->id, ['title' => 'Heads up', 'message' => 'Reviewed.']))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com'));
        $this->assertNotNull($body);
        $this->assertStringContainsString('Heads up', (string) $body['Body']);
        $this->assertStringContainsString('Reviewed.', (string) $body['Body']);
        $this->assertSame('+12025550173', $body['To']);
    }
}
