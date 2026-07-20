<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * The admin "Edit User" page must expose and correctly persist the phone data:
 * the number is normalized against its country (same rules as self-service) and
 * a changed number clears any prior verification. See UserManagementController::update.
 */
class AdminUserPhoneUpdateTest extends TestCase
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

    private function target(): User
    {
        return User::factory()->create([
            'phone' => '2025550100',
            'phone_country' => 'US',
            'phone_verified_at' => now(),
        ]);
    }

    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'credits' => 100,
            'plan_id' => null,
            'is_active' => true,
            'timezone' => $user->timezone ?? 'UTC',
        ], $overrides);
    }

    public function test_admin_can_update_phone_and_it_is_normalized(): void
    {
        $user = $this->target();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'phone' => '+1 (202) 555-0173',
                'phone_country' => 'US',
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('2025550173', $user->phone);
        $this->assertSame('US', $user->phone_country);
    }

    public function test_changing_phone_clears_verification(): void
    {
        $user = $this->target();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'phone' => '2025550173', // different from the seeded 2025550100
                'phone_country' => 'US',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_unchanged_phone_keeps_verification(): void
    {
        $user = $this->target();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'phone' => '2025550100', // same as seeded
                'phone_country' => 'US',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_admin_can_update_timezone_and_limits(): void
    {
        $user = $this->target();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'timezone' => 'Europe/London',
                'daily_limit' => 1200,
                'monthly_limit' => 15000,
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Europe/London', $user->timezone);
        $this->assertSame('1200.0000', (string) $user->daily_limit);
        $this->assertSame('15000.0000', (string) $user->monthly_limit);
    }

    public function test_blank_limits_are_stored_as_null(): void
    {
        $user = $this->target();
        $user->forceFill(['daily_limit' => 50, 'monthly_limit' => 500])->save();

        // Empty strings are converted to null by the framework → inherit global default.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'daily_limit' => null,
                'monthly_limit' => null,
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->daily_limit);
        $this->assertNull($user->monthly_limit);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $user = $this->target();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'timezone' => 'Not/AZone',
            ]))
            ->assertSessionHasErrors('timezone');
    }

    public function test_credits_and_plan_are_not_mutated_in_quota_mode(): void
    {
        // Default install: regular license → not Pro → quota mode. Billing fields
        // must be ignored even when the request carries them.
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 10, 'price_yearly' => 100,
            'vat_percentage' => 0, 'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
        $user = $this->target();
        $user->forceFill(['credits' => 50, 'plan_id' => null])->save();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'credits' => 999,
                'plan_id' => $plan->id,
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('50.0000', (string) $user->credits);
        $this->assertNull($user->plan_id);
    }

    public function test_credits_are_editable_when_pro_available(): void
    {
        settings_set('license_type', 2, 'integer', 'license');
        settings_set('subscriptions_enabled', true, 'boolean', 'general');
        Setting::flushCache();

        $user = $this->target();
        $user->forceFill(['credits' => 50])->save();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'credits' => 777,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('777.0000', (string) $user->fresh()->credits);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $user = $this->target();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'phone' => '12',
                'phone_country' => 'US',
            ]))
            ->assertSessionHasErrors('phone');

        // Unchanged: still the seeded verified number.
        $user->refresh();
        $this->assertSame('2025550100', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_phone_already_used_by_another_user_is_rejected(): void
    {
        // Seeded with 2025550100/US.
        User::factory()->create(['phone' => '2025550173', 'phone_country' => 'US']);
        $user = $this->target();

        // A raw, formatted duplicate must still be caught — it normalizes to the
        // stored national number before the uniqueness check runs.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'phone' => '+1 (202) 555-0173',
                'phone_country' => 'US',
            ]))
            ->assertSessionHasErrors('phone');

        $this->assertSame('2025550100', $user->fresh()->phone);
    }

    public function test_admin_can_keep_the_users_own_phone(): void
    {
        $user = $this->target();

        // Re-saving the user's existing number must not collide with itself.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $user->ulid), $this->payload($user, [
                'phone' => '2025550100',
                'phone_country' => 'US',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_db_index_rejects_a_duplicate_number_in_the_same_country(): void
    {
        User::factory()->create(['phone' => '2025550100', 'phone_country' => 'US']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['phone' => '2025550100', 'phone_country' => 'US']);
    }

    public function test_db_index_allows_the_same_national_number_in_another_country(): void
    {
        // Same digits, different country → a different real number: the composite
        // (phone, phone_country) index scopes uniqueness by country, so both persist.
        User::factory()->create(['phone' => '2025550100', 'phone_country' => 'US']);
        User::factory()->create(['phone' => '2025550100', 'phone_country' => 'GB']);

        $this->assertSame(2, User::where('phone', '2025550100')->count());
    }

    public function test_db_index_allows_many_users_without_a_phone(): void
    {
        // Nullable column: a unique index treats NULLs as distinct, so the "phone is
        // optional / conditionally required" model is preserved.
        User::factory()->count(3)->create(['phone' => null, 'phone_country' => null]);

        $this->assertSame(3, User::whereNull('phone')->count());
    }
}
