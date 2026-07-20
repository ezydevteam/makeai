<?php

namespace Tests\Unit;

use App\Support\AuditLogPresenter;
use App\Support\AuditLogRedactor;
use Tests\TestCase;

/**
 * The admin audit log presents INTENT (route name → human label + category)
 * and never leaks secrets in the payload. These lock in both behaviours.
 */
class AuditLogPresenterTest extends TestCase
{
    // ─── Redactor ──────────────────────────────

    public function test_redactor_masks_secret_keys(): void
    {
        $clean = AuditLogRedactor::sanitize([
            'name' => 'Acme',
            'smtp_password' => 'hunter2',
            'stripe_secret' => 'sk_live_abc',
            'openai_api_key' => 'sk-xyz',
            'license_key' => 'ENVATO-123',
            'webhook_secret' => 'whsec_1',
        ]);

        $this->assertSame('Acme', $clean['name']);
        foreach (['smtp_password', 'stripe_secret', 'openai_api_key', 'license_key', 'webhook_secret'] as $key) {
            $this->assertSame('••••••', $clean[$key], "$key must be masked");
        }
    }

    public function test_redactor_masks_nested_secrets_and_drops_noise(): void
    {
        $clean = AuditLogRedactor::sanitize([
            '_token' => 'csrf',
            'page' => 3,
            'gateway' => ['label' => 'Stripe', 'api_key' => 'sk-secret'],
        ]);

        $this->assertArrayNotHasKey('_token', $clean);
        $this->assertArrayNotHasKey('page', $clean);
        $this->assertSame('Stripe', $clean['gateway']['label']);
        $this->assertSame('••••••', $clean['gateway']['api_key']);
    }

    public function test_redactor_truncates_long_values(): void
    {
        $clean = AuditLogRedactor::sanitize(['body' => str_repeat('a', 500)]);

        $this->assertStringContainsString('500', $clean['body']);
        $this->assertLessThan(500, mb_strlen($clean['body']));
    }

    // ─── Presenter labels ──────────────────────

    public function test_curated_label_wins(): void
    {
        $out = AuditLogPresenter::present((object) [
            'route_name' => 'admin.affiliate.payouts.process',
            'method' => 'POST',
            'action' => 'POST admin/affiliate/payouts/5',
        ]);

        $this->assertSame('Processed an Affiliate Payout', $out['label']);
        $this->assertSame('affiliate', $out['category']);
    }

    public function test_auto_label_from_route_name_when_uncurated(): void
    {
        $out = AuditLogPresenter::present((object) [
            'route_name' => 'admin.coupons.update',
            'method' => 'PUT',
            'action' => 'PUT admin/coupons/9',
        ]);

        // verb "Updated" + resource "Coupons"
        $this->assertSame('Updated Coupons', $out['label']);
        $this->assertSame('billing', $out['category']);
    }

    public function test_legacy_row_without_route_name_still_humanizes(): void
    {
        $out = AuditLogPresenter::present((object) [
            'route_name' => null,
            'method' => null,
            'action' => 'POST admin/settings/general',
        ]);

        $this->assertStringContainsString('settings', strtolower($out['label']));
        $this->assertSame('settings', $out['category']);
    }

    public function test_target_is_built_from_type_and_id(): void
    {
        $out = AuditLogPresenter::present((object) [
            'route_name' => 'admin.users.update',
            'method' => 'PUT',
            'target_type' => 'user',
            'target_id' => '42',
        ]);

        $this->assertSame('User #42', $out['target']);
    }

    public function test_display_payload_is_redacted(): void
    {
        $out = AuditLogPresenter::present((object) [
            'route_name' => 'admin.mail.update',
            'method' => 'PUT',
            'payload' => json_encode(['smtp_host' => 'mail.x.com', 'smtp_password' => 'secret']),
        ]);

        $this->assertSame('mail.x.com', $out['payload']['smtp_host']);
        $this->assertSame('••••••', $out['payload']['smtp_password']);
    }
}
