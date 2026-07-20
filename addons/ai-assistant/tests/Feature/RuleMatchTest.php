<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Models\AiAssistantRule;
use Addons\AiAssistant\Tests\AssistantTestCase;

class RuleMatchTest extends AssistantTestCase
{
    private function rule(string $trigger, string $response, string $matchType): void
    {
        AiAssistantRule::create([
            'trigger' => $trigger,
            'response' => $response,
            'match_type' => $matchType,
            'is_active' => true,
        ]);
    }

    public function test_contains_rule_returns_canned_reply_over_http(): void
    {
        $this->enableFrontend('all');
        $this->useQuotaMode();
        $this->setLimits(0, 0, 0);
        $this->seedChatModel();
        $this->fakeProvider(['AI ANSWER — should not appear']);

        $this->rule('refund policy', 'Our refund policy is 30 days.', 'contains');

        $text = $this->streamText($this->post(route('addon.ai-assistant.chat'), [
            'message' => 'what is your refund policy?',
            'session_id' => 'sess-c',
        ]));

        $this->assertStringContainsString('refund policy is 30 days', $text);
        $this->assertStringNotContainsString('AI ANSWER', $text);
    }

    public function test_exact_rule_requires_the_whole_message_to_match(): void
    {
        $this->enableFrontend('all');
        $this->useQuotaMode();
        $this->setLimits(0, 0, 0);
        $this->seedChatModel();
        $this->fakeProvider(['AI FALLBACK']);

        $this->rule('hours', 'We are open 9-5.', 'exact');

        // Exact: the whole message must equal the trigger (case-insensitive).
        $hit = $this->streamText($this->post(route('addon.ai-assistant.chat'), [
            'message' => 'Hours', 'session_id' => 's1',
        ]));
        $this->assertStringContainsString('open 9-5', $hit);

        // "what are your hours" is NOT an exact match, so it falls through to the AI.
        $miss = $this->streamText($this->post(route('addon.ai-assistant.chat'), [
            'message' => 'what are your hours', 'session_id' => 's2',
        ]));
        $this->assertStringContainsString('AI FALLBACK', $miss);
    }

    public function test_admin_chat_also_applies_rules(): void
    {
        addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');
        $this->useQuotaMode();
        $this->seedChatModel();
        $this->fakeProvider(['AI ANSWER — should not appear']);
        $this->actingAsAdmin();

        $this->rule('server status', 'All systems operational.', 'contains');

        $text = $this->streamText($this->post(route('addon.ai-assistant.admin.chat'), [
            'message' => 'give me the server status now',
            'session_id' => 'sess-admin-rule',
        ]));

        $this->assertStringContainsString('systems operational', $text);
    }

    public function test_rules_index_endpoint_returns_saved_rules(): void
    {
        addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');
        $this->actingAsAdmin();
        $this->rule('hi', 'hello', 'exact');

        $this->getJson(route('addon.ai-assistant.admin.rules.index'))
            ->assertOk()
            ->assertJsonPath('rules.0.trigger', 'hi');
    }
}
