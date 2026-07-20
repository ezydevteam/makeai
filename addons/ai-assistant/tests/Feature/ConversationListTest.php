<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Models\AssistantConversation;
use Addons\AiAssistant\Tests\AssistantTestCase;
use App\Models\User;

/**
 * Multiple chat sessions: a signed-in user builds a list of past conversations they can
 * reopen. Guests keep a single temporary thread (no history), which is both the safe
 * choice and what MagicAI itself does ("Login to save your chat history").
 */
class ConversationListTest extends AssistantTestCase
{
    private function chatAs(?User $user, string $message, string $session): void
    {
        $this->fakeProvider(['Answer for ' . $session]);

        $request = $user ? $this->actingAs($user) : $this;

        $request->post(route('addon.ai-assistant.chat'), [
            'message' => $message,
            'session_id' => $session,
        ])->streamedContent(); // consume so the stream body (and persistence) runs
    }

    private function setUpChat(): void
    {
        $this->enableFrontend('all');
        $this->useMeteredMode();
        $this->setLimits(0, 0, 0);
        $this->seedChatModel();
    }

    public function test_user_sees_their_conversations_newest_first(): void
    {
        $this->setUpChat();
        $user = $this->freeUser();

        $this->chatAs($user, 'first thread question', 'sess-1');
        $this->chatAs($user, 'second thread question', 'sess-2');

        $response = $this->actingAs($user)->getJson(route('addon.ai-assistant.conversations'));

        $response->assertOk()->assertJsonPath('can_save', true);

        $list = $response->json('conversations');
        $this->assertCount(2, $list);

        // Newest first, and each carries the title derived from the opening message.
        $this->assertSame('sess-2', $list[0]['session_id']);
        $this->assertStringContainsString('second thread', $list[0]['title']);
        $this->assertSame(2, $list[0]['message_count']);
    }

    public function test_a_user_never_sees_another_users_conversations(): void
    {
        $this->setUpChat();

        $owner = $this->freeUser();
        $this->chatAs($owner, 'private thread', 'sess-owner');

        $other = $this->freeUser();
        $response = $this->actingAs($other)->getJson(route('addon.ai-assistant.conversations'));

        $response->assertOk();
        $this->assertCount(0, $response->json('conversations'));
    }

    public function test_guests_get_no_history_and_are_told_why(): void
    {
        $this->setUpChat();
        $this->useQuotaMode();

        $this->chatAs(null, 'guest question', 'sess-guest');

        $response = $this->getJson(route('addon.ai-assistant.conversations'));

        $response->assertOk()
            ->assertJsonPath('can_save', false)   // → widget shows "sign in to save history"
            ->assertJsonPath('conversations', []);
    }

    public function test_opening_a_past_conversation_returns_its_own_transcript(): void
    {
        $this->setUpChat();
        $user = $this->freeUser();

        $this->chatAs($user, 'about billing', 'sess-a');
        $this->chatAs($user, 'about shipping', 'sess-b');

        // Each session id resolves to its own independent thread.
        $a = $this->actingAs($user)->getJson(route('addon.ai-assistant.transcript', ['session_id' => 'sess-a']));
        $b = $this->actingAs($user)->getJson(route('addon.ai-assistant.transcript', ['session_id' => 'sess-b']));

        $this->assertSame('about billing', $a->json('messages.0.content'));
        $this->assertSame('about shipping', $b->json('messages.0.content'));
    }

    public function test_user_can_delete_their_own_conversation(): void
    {
        $this->setUpChat();
        $user = $this->freeUser();

        $this->chatAs($user, 'delete me', 'sess-del');
        $this->assertSame(1, AssistantConversation::count());

        $this->actingAs($user)
            ->deleteJson(route('addon.ai-assistant.conversations.delete'), ['session_id' => 'sess-del'])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertSame(0, AssistantConversation::count());
    }

    public function test_a_user_cannot_delete_someone_elses_conversation(): void
    {
        $this->setUpChat();

        $owner = $this->freeUser();
        $this->chatAs($owner, 'not yours', 'sess-owned');

        $attacker = $this->freeUser();
        $this->actingAs($attacker)
            ->deleteJson(route('addon.ai-assistant.conversations.delete'), ['session_id' => 'sess-owned'])
            ->assertOk()
            ->assertJsonPath('status', 'not_found');

        $this->assertSame(1, AssistantConversation::count(), 'the owner\'s thread must survive');
    }
}
