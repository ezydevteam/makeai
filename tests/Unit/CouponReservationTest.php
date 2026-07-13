<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Models\User;
use Tests\TestCase;

/**
 * Finding #4 — a per-user-limited coupon must not be claimable twice by
 * concurrent paid checkouts before either records a redemption.
 */
class CouponReservationTest extends TestCase
{
    private function coupon(?int $perUserLimit): Coupon
    {
        return Coupon::create([
            'code' => 'SAVE'.strtoupper(uniqid()),
            'type' => 'percent',
            'value' => 20,
            'per_user_limit' => $perUserLimit,
            'is_active' => true,
        ]);
    }

    public function test_single_use_coupon_reserves_one_slot_then_blocks(): void
    {
        $user = User::factory()->create();
        $coupon = $this->coupon(1);

        $first = $coupon->reserveForUser($user);
        $second = $coupon->reserveForUser($user);

        $this->assertNotNull($first, 'First checkout should hold the slot.');
        $this->assertNull($second, 'A concurrent second checkout must not claim the same single-use slot.');
    }

    public function test_releasing_a_reservation_frees_the_slot(): void
    {
        $user = User::factory()->create();
        $coupon = $this->coupon(1);

        $key = $coupon->reserveForUser($user);
        $this->assertNotNull($key);

        Coupon::releaseReservation($key);

        $this->assertNotNull($coupon->reserveForUser($user), 'Slot should be reusable after an abandoned checkout releases it.');
    }

    public function test_multi_use_coupon_allows_up_to_the_limit(): void
    {
        $user = User::factory()->create();
        $coupon = $this->coupon(2);

        $this->assertNotNull($coupon->reserveForUser($user));
        $this->assertNotNull($coupon->reserveForUser($user));
        $this->assertNull($coupon->reserveForUser($user), 'Third reservation exceeds the per-user limit of 2.');
    }

    public function test_unlimited_coupon_needs_no_reservation(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->coupon(null)->reserveForUser($user));
    }
}
