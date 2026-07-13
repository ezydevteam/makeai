<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Services\AiAssistantService;
use Addons\AiAssistant\Tests\AssistantTestCase;

/**
 * Who may use the assistant is the admin buyer's decision (enabled + show_to). These
 * prove that decision is honoured at the service gate AND at the route — a hidden widget
 * is not access control, so a hand-rolled POST must be refused too.
 */
class AccessControlTest extends AssistantTestCase
{
    private function service(): AiAssistantService
    {
        return app(AiAssistantService::class);
    }

    public function test_disabled_addon_is_never_visible_on_frontend(): void
    {
        addon_setting_set('ai-assistant', 'enabled', false, 'boolean');

        $this->assertFalse($this->service()->isVisibleForUser(null, false));
        $this->assertFalse($this->service()->isVisibleForUser($this->freeUser(), false));
    }

    public function test_show_to_all_includes_guests(): void
    {
        $this->enableFrontend('all');

        $this->assertTrue($this->service()->isVisibleForUser(null, false));
    }

    public function test_show_to_logged_in_excludes_guests_only(): void
    {
        $this->enableFrontend('logged_in');

        $this->assertFalse($this->service()->isVisibleForUser(null, false));
        $this->assertTrue($this->service()->isVisibleForUser($this->freeUser(), false));
    }

    /**
     * The headline regression. The old gate called isProAvailable() — a GLOBAL install
     * flag — so on any Extended install every free user passed "pro only". The fix keys
     * on the user's own plan.
     */
    public function test_pro_only_gates_on_the_users_plan_when_billing_is_available(): void
    {
        $this->useMeteredMode(); // isProAvailable() === true
        $this->enableFrontend('pro_only');

        $this->assertFalse($this->service()->isVisibleForUser(null, false), 'guest');
        $this->assertFalse($this->service()->isVisibleForUser($this->freeUser(), false), 'free user must NOT pass pro_only');
        $this->assertTrue($this->service()->isVisibleForUser($this->proUser(), false), 'pro user passes');
    }

    /**
     * Documented fallback: with no sellable tier (Regular license / subscriptions off),
     * "pro only" would otherwise hide the assistant from everyone. It collapses to
     * "any signed-in user" instead.
     */
    public function test_pro_only_degrades_to_logged_in_without_billing(): void
    {
        $this->useQuotaMode(); // isProAvailable() === false
        $this->enableFrontend('pro_only');

        $this->assertFalse($this->service()->isVisibleForUser(null, false), 'guest still excluded');
        $this->assertTrue($this->service()->isVisibleForUser($this->freeUser(), false), 'any member passes in quota mode');
    }

    public function test_admin_surface_gates_on_admin_enabled_independently(): void
    {
        addon_setting_set('ai-assistant', 'enabled', false, 'boolean'); // frontend off
        addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');

        $this->assertTrue($this->service()->isVisibleForUser(null, true), 'admin surface unaffected by frontend toggle');

        addon_setting_set('ai-assistant', 'admin_enabled', false, 'boolean');
        $this->assertFalse($this->service()->isVisibleForUser(null, true));
    }

    // ─── route-level enforcement ─────────────────────────────

    public function test_route_refuses_guest_when_show_to_is_logged_in(): void
    {
        $this->enableFrontend('logged_in');
        $this->useQuotaMode();
        $this->seedChatModel();
        $this->fakeProvider(['should never run']);

        $response = $this->post(route('addon.ai-assistant.chat'), [
            'message' => 'hello',
            'session_id' => 'sess-guest',
        ]);

        // The gate answers with a clean error frame, not a completion.
        $error = $this->errorFrame($response);
        $this->assertNotNull($error, 'guest should be refused');
        $this->assertSame('unavailable', $error['code']);
        $this->assertNotContains('token', $this->frameTypes($response), 'no answer tokens for a refused request');
    }

    public function test_route_serves_guest_when_show_to_is_all(): void
    {
        $this->enableFrontend('all');
        $this->useQuotaMode();
        $this->setLimits(guest: 0, member: 0, pro: 0); // unlimited
        $this->seedChatModel();
        $this->fakeProvider(['Hello, guest!']);

        $response = $this->post(route('addon.ai-assistant.chat'), [
            'message' => 'hi',
            'session_id' => 'sess-guest',
        ]);

        $this->assertNull($this->errorFrame($response));
        $this->assertStringContainsString('Hello, guest!', $this->streamText($response));
    }

    public function test_extract_endpoint_refuses_a_guest_when_assistant_is_hidden(): void
    {
        $this->enableFrontend('logged_in'); // guests not allowed

        $response = $this->postJson(route('addon.ai-assistant.extract'), [
            'file' => \Illuminate\Http\Testing\File::create('doc.txt', 1),
        ]);

        $response->assertForbidden();
    }
}
