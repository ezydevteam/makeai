<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Models\AssistantCsat;
use Addons\AiAssistant\Tests\AssistantTestCase;
use App\Models\ContactMessage;

/**
 * The MagicAI-style multi-panel endpoints: Help Center, "leave a message", and CSAT.
 *
 * The Help panel depends on the ai-knowledge-base addon, which is NOT registered in
 * this harness — so Help endpoints must degrade gracefully (empty/404), which is exactly
 * the behaviour the widget relies on to hide the tab. Message and CSAT stand alone and are
 * exercised fully.
 */
class PanelsTest extends AssistantTestCase
{
    // ─── Help (KB absent → graceful) ─────────────────────────

    public function test_help_articles_is_empty_and_flagged_unavailable_without_kb(): void
    {
        $this->enableFrontend('all');

        $response = $this->getJson(route('addon.ai-assistant.help.articles'));

        $response->assertOk()
            ->assertJson(['available' => false, 'articles' => []]);
    }

    public function test_help_article_404s_without_kb(): void
    {
        $this->enableFrontend('all');

        $this->getJson(route('addon.ai-assistant.help.article', ['slug' => 'anything']))
            ->assertNotFound();
    }

    public function test_help_endpoints_refuse_when_assistant_is_hidden(): void
    {
        $this->enableFrontend('logged_in'); // guests excluded

        // A guest gets the graceful empty payload, never a leak or an error.
        $this->getJson(route('addon.ai-assistant.help.articles'))
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    // ─── Leave a message ─────────────────────────────────────

    public function test_message_creates_a_contact_message(): void
    {
        $this->enableFrontend('all');

        $response = $this->postJson(route('addon.ai-assistant.message'), [
            'email' => 'visitor@example.com',
            'name' => 'Jane Visitor',
            'message' => 'I need help installing the plugin.',
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'visitor@example.com',
            'name' => 'Jane Visitor',
            'subject' => 'AI Assistant message',
        ]);
    }

    public function test_message_honeypot_is_rejected(): void
    {
        $this->enableFrontend('all');

        // A filled honeypot fails validation (rule: max:0).
        $this->postJson(route('addon.ai-assistant.message'), [
            'email' => 'bot@example.com',
            'message' => 'spam',
            'website' => 'http://spam.example',
        ])->assertStatus(422);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_message_is_refused_when_the_panel_is_disabled(): void
    {
        $this->enableFrontend('all');
        addon_setting_set('ai-assistant', 'enable_message', false, 'boolean');

        $this->postJson(route('addon.ai-assistant.message'), [
            'email' => 'visitor@example.com',
            'message' => 'hello',
        ])->assertStatus(403);
    }

    // ─── CSAT ────────────────────────────────────────────────

    public function test_csat_stores_a_score_once_per_session(): void
    {
        $this->enableFrontend('all');

        $this->postJson(route('addon.ai-assistant.csat'), [
            'session_id' => 'sess-csat',
            'score' => 5,
        ])->assertOk();

        // A second rating for the same session updates rather than duplicating.
        $this->postJson(route('addon.ai-assistant.csat'), [
            'session_id' => 'sess-csat',
            'score' => 3,
        ])->assertOk();

        $this->assertSame(1, AssistantCsat::count());
        $this->assertSame(3, AssistantCsat::first()->score);
    }

    public function test_csat_rejects_out_of_range_scores(): void
    {
        $this->enableFrontend('all');

        $this->postJson(route('addon.ai-assistant.csat'), [
            'session_id' => 'sess-bad',
            'score' => 9,
        ])->assertStatus(422);
    }
}
