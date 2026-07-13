<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Services\SlashCommandRegistry;
use Addons\AiAssistant\Tests\AssistantTestCase;

class SlashCommandTest extends AssistantTestCase
{
    private function registry(): SlashCommandRegistry
    {
        return app(SlashCommandRegistry::class);
    }

    // ─── parsing ─────────────────────────────────────────────

    public function test_parse_recognises_commands_and_ignores_the_rest(): void
    {
        $r = $this->registry();

        $this->assertSame(['name' => 'help', 'args' => ''], $r->parse('/help'));
        $this->assertSame(['name' => 'docs', 'args' => 'how do I cancel'], $r->parse('/docs how do I cancel'));

        // Not commands: ordinary text, a path-looking string, and a bare slash.
        $this->assertNull($r->parse('help me please'));
        $this->assertNull($r->parse('/admin/users'), 'a path is not a command');
        $this->assertNull($r->parse('/'), 'a bare slash is not a command');
    }

    // ─── scope enforcement ───────────────────────────────────

    public function test_admin_command_cannot_be_run_from_the_user_scope(): void
    {
        // /stats is admin-only. A frontend user must not be able to invoke it.
        $this->assertNull(
            $this->registry()->handle('stats', '', SlashCommandRegistry::SCOPE_USER, $this->freeUser()),
            'user scope must not resolve an admin command'
        );

        $result = $this->registry()->handle('stats', '', SlashCommandRegistry::SCOPE_ADMIN, null);
        $this->assertNotNull($result, 'admin scope resolves /stats');
        $this->assertTrue($result->isDirectReply());
    }

    public function test_help_lists_only_in_scope_commands(): void
    {
        $userNames = collect($this->registry()->list(SlashCommandRegistry::SCOPE_USER))->pluck('name');
        $adminNames = collect($this->registry()->list(SlashCommandRegistry::SCOPE_ADMIN))->pluck('name');

        $this->assertContains('usage', $userNames);
        $this->assertNotContains('stats', $userNames, 'admin command must not leak into the user menu');

        $this->assertContains('stats', $adminNames);
        $this->assertNotContains('usage', $adminNames, 'user command must not appear in the admin menu');
    }

    // ─── the enable toggle (a manifest setting that is actually read) ──

    public function test_disabling_slash_commands_empties_the_menu_and_stops_handling(): void
    {
        addon_setting_set('ai-assistant', 'enable_slash_commands', false, 'boolean');

        $this->assertSame([], $this->registry()->list(SlashCommandRegistry::SCOPE_USER));
        $this->assertNull(
            $this->registry()->handle('help', '', SlashCommandRegistry::SCOPE_USER, $this->freeUser()),
            'with commands off, /help is treated as ordinary chat'
        );
    }

    // ─── mode-aware /credits — the core cross-license requirement ──

    public function test_credits_command_reports_a_wallet_balance_in_metered_mode(): void
    {
        $this->useMeteredMode();
        $user = $this->freeUser(['credits' => 42]);

        $reply = $this->registry()->handle('credits', '', SlashCommandRegistry::SCOPE_USER, $user)->reply;

        $this->assertStringContainsString('42', $reply);
        $this->assertStringContainsStringIgnoringCase('balance', $reply);
    }

    public function test_credits_command_never_quotes_a_purchasable_balance_in_quota_mode(): void
    {
        $this->useQuotaMode();
        $user = $this->freeUser(['credits' => 42]);

        $reply = $this->registry()->handle('credits', '', SlashCommandRegistry::SCOPE_USER, $user)->reply;

        // In quota mode credits are a resetting allowance with no top-up path — the reply
        // must talk about the allowance, not a wallet balance the user cannot refill.
        $this->assertStringContainsStringIgnoringCase('allowance', $reply);
        $this->assertStringNotContainsStringIgnoringCase('balance', $reply);
    }

    // ─── /docs is gated on the Knowledge Base addon ──────────

    public function test_docs_command_is_hidden_when_the_knowledge_base_addon_is_absent(): void
    {
        // The KB addon is not registered in this test, so /docs must not be offered…
        $names = collect($this->registry()->list(SlashCommandRegistry::SCOPE_USER))->pluck('name');
        $this->assertNotContains('docs', $names);

        // …and invoking it directly must not resolve either.
        $this->assertNull(
            $this->registry()->handle('docs', 'anything', SlashCommandRegistry::SCOPE_USER, $this->freeUser())
        );
    }

    // ─── a direct-reply command costs nothing (no provider, no charge) ──

    public function test_direct_reply_command_over_http_never_calls_the_provider(): void
    {
        $this->enableFrontend('all');
        $this->useQuotaMode();
        $this->setLimits(guest: 0, member: 0, pro: 0);
        $this->seedChatModel();

        // If /help reached the provider this canned reply would appear; it must not.
        $this->fakeProvider(['PROVIDER WAS CALLED']);

        $user = $this->freeUser();
        $response = $this->actingAs($user)->post(route('addon.ai-assistant.chat'), [
            'message' => '/help',
            'session_id' => 'sess-help',
        ]);

        $text = $this->streamText($response);
        $this->assertStringContainsString('Available commands', $text);
        $this->assertStringNotContainsString('PROVIDER WAS CALLED', $text);

        // No usage row — a local answer is free.
        $this->assertSame(0, \App\Models\AiUsageLog::count());
    }
}
