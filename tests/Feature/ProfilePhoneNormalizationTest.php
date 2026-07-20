<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * The profile update path must normalize users.phone to the national number
 * (digits only) parsed against users.phone_country: dial codes and formatting
 * are stripped, significant leading zeros survive, and numbers that aren't
 * valid for the selected country are rejected rather than stored. See
 * App\Support\PhoneNumber and App\Http\Requests\User\UpdateProfileRequest.
 */
class ProfilePhoneNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass the install/license gates on the web group — this test exercises
        // phone normalization, not installation or licensing.
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $this->user = User::factory()->create();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => $this->user->email,
            'timezone' => 'UTC',
        ], $overrides);
    }

    private function submit(array $overrides = [])
    {
        return $this->actingAs($this->user)
            ->put(route('user.dashboard.profile.update'), $this->payload($overrides));
    }

    public function test_strips_dial_code_and_formatting_to_national_number(): void
    {
        $this->submit(['phone' => '+1 (202) 555-0173', 'phone_country' => 'US'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->user->refresh();
        $this->assertSame('2025550173', $this->user->phone);
        $this->assertSame('US', $this->user->phone_country);
    }

    public function test_lowercase_country_is_normalized_and_number_stored(): void
    {
        $this->submit(['phone' => '202-555-0173', 'phone_country' => 'us'])
            ->assertSessionHasNoErrors();

        $this->user->refresh();
        $this->assertSame('2025550173', $this->user->phone);
        $this->assertSame('US', $this->user->phone_country);
    }

    public function test_preserves_significant_leading_zero(): void
    {
        // Italy's leading 0 is part of the number (not a trunk prefix), so it
        // must survive normalization.
        $this->submit(['phone' => '+39 02 1234 5678', 'phone_country' => 'IT'])
            ->assertSessionHasNoErrors();

        $this->user->refresh();
        $this->assertSame('0212345678', $this->user->phone);
    }

    public function test_rejects_number_invalid_for_country(): void
    {
        $this->submit(['phone' => '12', 'phone_country' => 'US'])
            ->assertSessionHasErrors('phone');

        $this->user->refresh();
        $this->assertNull($this->user->phone);
    }

    public function test_requires_country_when_phone_present(): void
    {
        $this->submit(['phone' => '2025550173'])
            ->assertSessionHasErrors('phone_country');

        $this->user->refresh();
        $this->assertNull($this->user->phone);
    }

    public function test_clearing_phone_stores_null(): void
    {
        $this->user->update(['phone' => '2025550173', 'phone_country' => 'US']);

        // An empty phone collapses to null, so phone_country is no longer required.
        $this->submit(['phone' => ''])
            ->assertSessionHasNoErrors();

        $this->user->refresh();
        $this->assertNull($this->user->phone);
    }
}
