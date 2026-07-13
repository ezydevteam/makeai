<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Tests\AssistantTestCase;
use App\Models\AiUsageLog;
use App\Models\User;

class BillingTest extends AssistantTestCase
{
    /**
     * The headline billing regression. The old controller only charged inside
     * `if (isset($chunk['input_tokens']))` — so a provider that streams text but never
     * volunteers a usage array produced a completely free request. The rewrite estimates
     * tokens and always settles through TokenGuard::after().
     */
    public function test_a_stream_without_usage_stats_is_still_billed(): void
    {
        $this->enableFrontend('all');
        $this->useMeteredMode();
        $this->setLimits(guest: 0, member: 0, pro: 0);
        $this->seedChatModel();

        // A plain text response with no usage array — exactly the hole that leaked.
        // (Each array element is one full response for a successive call, not a chunk.)
        $this->fakeProvider(['This is a billed answer.']);

        $user = $this->freeUser();
        $response = $this->actingAs($user)->post(route('addon.ai-assistant.chat'), [
            'message' => 'tell me something',
            'session_id' => 'sess-bill',
        ]);

        $this->assertStringContainsString('billed answer', $this->streamText($response));

        $log = AiUsageLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log, 'a usage row must be written even without provider usage stats');
        $this->assertGreaterThan(0, $log->input_tokens + $log->output_tokens, 'estimated tokens must be non-zero');
    }

    /**
     * Admin chat is billed to the internal system user (as the other admin AI-assist
     * features are), never to a real account — and it must reach TokenGuard at all, which
     * the old version skipped entirely.
     */
    public function test_admin_chat_bills_the_internal_ai_user(): void
    {
        addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');
        $this->useMeteredMode();
        $this->seedChatModel();
        $this->fakeProvider(['Admin answer.']);

        $admin = $this->actingAsAdmin();

        $response = $this->post(route('addon.ai-assistant.admin.chat'), [
            'message' => 'how many users do we have?',
            'session_id' => 'sess-admin',
        ]);

        $this->assertStringContainsString('Admin answer', $this->streamText($response));

        $internal = User::where('email', User::internalAiEmail())->first();
        $this->assertNotNull($internal, 'the internal AI user should have been resolved');
        $this->assertSame(1, AiUsageLog::where('user_id', $internal->id)->count());
    }

    /**
     * The admin global-budget bypass. The old adminChat never called TokenGuard::before(),
     * so the operator's daily AI cost kill-switch did not apply to it. Now it does: once
     * spend crosses the configured budget, admin chat is refused like any other.
     */
    public function test_admin_chat_respects_the_global_daily_budget(): void
    {
        addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');
        $this->useMeteredMode();
        $this->seedChatModel();
        $this->fakeProvider(['first answer', 'second answer']);

        $this->actingAsAdmin();

        // First call accrues global spend — but the stream's billing only runs when its
        // content is actually read, so consume it before checking the budget.
        $first = $this->post(route('addon.ai-assistant.admin.chat'), [
            'message' => 'first question',
            'session_id' => 'sess-a',
        ]);
        $this->assertStringContainsString('first answer', $this->streamText($first));

        // Set the daily budget below what has already been spent today.
        settings_set('global_daily_ai_budget_usd', '0.000001', 'string', 'ai');

        $second = $this->post(route('addon.ai-assistant.admin.chat'), [
            'message' => 'second question',
            'session_id' => 'sess-a',
        ]);

        $error = $this->errorFrame($second);
        $this->assertNotNull($error, 'admin chat must be refused once the global budget is exceeded');
        $this->assertNotContains('token', $this->frameTypes($second), 'no answer once the budget is blown');
    }
}
