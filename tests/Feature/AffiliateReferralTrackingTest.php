<?php

namespace Tests\Feature;

use App\Models\AffiliateProgram;
use App\Models\AffiliateReferral;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Referral tracking: click capture (/ref/{code}), the dedup + self-referral +
 * ban guards, findReferrer() resolution, and attachReferralToUser() attribution
 * at registration.
 */
class AffiliateReferralTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');
        settings_set('default_currency', 'USD', 'string', 'general');
        AffiliateProgram::current()->update(['cookie_days' => 30]);
    }

    private function service(): AffiliateService
    {
        return app(AffiliateService::class);
    }

    private function referrer(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['referral_code' => 'REF12345'], $attrs));
    }

    /** A request carrying the referral code in its session, from a given IP. */
    private function attachRequest(string $code, string $ip): Request
    {
        $request = Request::create('/register', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]);
        $session = $this->app->make('session')->driver();
        $request->setLaravelSession($session);
        $session->put('affiliate_ref', $code);

        return $request;
    }

    // ─── findReferrer ────────────────────────────

    public function test_find_referrer_resolves_by_code_or_custom_slug(): void
    {
        $user = $this->referrer(['referral_code' => 'CODE0001', 'affiliate_custom_slug' => 'my-alias']);

        $this->assertTrue($this->service()->findReferrer('CODE0001')?->is($user));
        $this->assertTrue($this->service()->findReferrer('my-alias')?->is($user));
        $this->assertNull($this->service()->findReferrer('does-not-exist'));
    }

    // ─── capture (HTTP) ──────────────────────────

    public function test_capturing_a_referral_link_records_a_click_and_remembers_the_code(): void
    {
        $referrer = $this->referrer();

        $this->get('/ref/'.$referrer->referral_code)
            ->assertRedirect(route('register'))
            ->assertSessionHas('affiliate_ref', $referrer->referral_code);

        $this->assertDatabaseHas('affiliate_referrals', [
            'referrer_id' => $referrer->id,
            'referral_code' => $referrer->referral_code,
            'referred_id' => null,
        ]);
    }

    public function test_repeated_clicks_from_the_same_ip_are_deduplicated_within_a_day(): void
    {
        $referrer = $this->referrer();

        $this->get('/ref/'.$referrer->referral_code);
        $this->get('/ref/'.$referrer->referral_code);

        $this->assertSame(1, AffiliateReferral::where('referrer_id', $referrer->id)->count());
    }

    public function test_clicking_your_own_link_records_nothing(): void
    {
        $referrer = $this->referrer();

        $this->actingAs($referrer)->get('/ref/'.$referrer->referral_code)
            ->assertRedirect(route('register'));

        $this->assertSame(0, AffiliateReferral::where('referrer_id', $referrer->id)->count());
    }

    public function test_a_banned_affiliates_link_records_nothing(): void
    {
        $referrer = $this->referrer(['affiliate_banned' => true]);

        $this->get('/ref/'.$referrer->referral_code)->assertRedirect(route('register'));

        $this->assertSame(0, AffiliateReferral::where('referrer_id', $referrer->id)->count());
    }

    public function test_capture_is_inert_when_the_program_is_disabled(): void
    {
        settings_set('affiliate_enabled', '0', 'boolean', 'affiliate');
        $referrer = $this->referrer();

        $this->get('/ref/'.$referrer->referral_code)->assertRedirect(route('register'));

        $this->assertSame(0, AffiliateReferral::where('referrer_id', $referrer->id)->count());
    }

    // ─── attachReferralToUser ────────────────────

    public function test_registration_attributes_the_new_user_to_the_referrer(): void
    {
        $referrer = $this->referrer(['last_login_ip' => '203.0.113.9']);
        // A prior unconverted click from the visitor's IP.
        AffiliateReferral::create([
            'referrer_id' => $referrer->id,
            'referral_code' => $referrer->referral_code,
            'ip_address' => '198.51.100.5',
            'landed_at' => now(),
        ]);
        $newUser = User::factory()->create(['referred_by' => null]);

        $this->service()->attachReferralToUser($this->attachRequest($referrer->referral_code, '198.51.100.5'), $newUser);

        $this->assertSame($referrer->id, $newUser->fresh()->referred_by);
        $this->assertDatabaseHas('affiliate_referrals', [
            'referrer_id' => $referrer->id,
            'referred_id' => $newUser->id,
        ]);
    }

    public function test_registration_from_the_referrers_own_ip_is_not_attributed(): void
    {
        // Same IP as the referrer's last login → almost certainly a self-referral
        // second account; must not be attributed.
        $referrer = $this->referrer(['last_login_ip' => '198.51.100.5']);
        $newUser = User::factory()->create(['referred_by' => null]);

        $this->service()->attachReferralToUser($this->attachRequest($referrer->referral_code, '198.51.100.5'), $newUser);

        $this->assertNull($newUser->fresh()->referred_by);
    }

    public function test_a_banned_referrer_is_not_attributed_at_registration(): void
    {
        $referrer = $this->referrer(['affiliate_banned' => true, 'last_login_ip' => '203.0.113.9']);
        $newUser = User::factory()->create(['referred_by' => null]);

        $this->service()->attachReferralToUser($this->attachRequest($referrer->referral_code, '198.51.100.5'), $newUser);

        $this->assertNull($newUser->fresh()->referred_by);
    }
}
