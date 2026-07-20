<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Models\AiAssistantFeedback;
use Addons\AiAssistant\Models\AssistantMessage;
use Addons\AiAssistant\Tests\AssistantTestCase;

class FeedbackTest extends AssistantTestCase
{
    private function haveAnAnsweredMessage(string $session, string $answer): AssistantMessage
    {
        $this->enableFrontend('all');
        $this->useMeteredMode();
        $this->setLimits(0, 0, 0);
        $this->seedChatModel();
        $this->fakeProvider([$answer]);

        $this->post(route('addon.ai-assistant.chat'), [
            'message' => 'a question',
            'session_id' => $session,
        ])->streamedContent();

        return AssistantMessage::where('role', 'assistant')->firstOrFail();
    }

    /**
     * The whole point of persistence for feedback: a rating now links to the exact stored
     * message it was about, instead of a dangling hash pointing at nothing.
     */
    public function test_feedback_links_to_the_message_it_rates(): void
    {
        $answer = 'The answer users are rating.';
        $message = $this->haveAnAnsweredMessage('sess-fb', $answer);

        $this->postJson(route('addon.ai-assistant.feedback'), [
            'session_id' => 'sess-fb',
            'message_hash' => sha1($answer),
            'rating' => 1,
        ])->assertOk();

        $feedback = AiAssistantFeedback::firstOrFail();
        $this->assertSame($message->id, $feedback->message_id, 'the rating must resolve to the stored message');
        $this->assertSame(1, $feedback->rating);
    }

    public function test_feedback_still_saves_when_no_message_matches(): void
    {
        // No conversation/message exists for this hash — the rating is still recorded,
        // just unlinked, rather than lost.
        $this->postJson(route('addon.ai-assistant.feedback'), [
            'session_id' => 'sess-none',
            'message_hash' => sha1('nothing here'),
            'rating' => -1,
        ])->assertOk();

        $feedback = AiAssistantFeedback::firstOrFail();
        $this->assertNull($feedback->message_id);
        $this->assertSame(-1, $feedback->rating);
    }

    public function test_admin_feedback_page_lists_ratings_with_their_message(): void
    {
        $answer = 'Reviewable answer content.';
        $this->haveAnAnsweredMessage('sess-admin-fb', $answer);

        $this->postJson(route('addon.ai-assistant.feedback'), [
            'session_id' => 'sess-admin-fb',
            'message_hash' => sha1($answer),
            'rating' => 1,
            'comment' => 'Very helpful',
        ])->assertOk();

        $this->actingAsAdmin();

        // Invoke the controller directly and read the props it set, via reflection. Calling
        // toResponse() would additionally evaluate every GLOBAL Inertia share (admin menu,
        // support-ticket badge counts, …), some of which are bound to mysql and blow up in
        // this sqlite harness — noise unrelated to the read path under test.
        $request = \Illuminate\Http\Request::create(route('admin.addons.ai-assistant.feedback'), 'GET');

        $inertia = app(\Addons\AiAssistant\Controllers\AiAssistantController::class)->feedbackIndex($request);

        $propsRef = new \ReflectionProperty($inertia, 'props');
        $propsRef->setAccessible(true);
        $props = $propsRef->getValue($inertia);

        $this->assertSame(1, $props['stats']['total']);
        $this->assertSame(1, $props['stats']['positive']);

        $row = $props['feedback']->items()[0];
        $this->assertSame('Very helpful', $row['comment']);
        $this->assertStringContainsString('Reviewable answer', (string) $row['message']);
    }

    public function test_admin_feedback_page_requires_admin_auth(): void
    {
        $this->get(route('admin.addons.ai-assistant.feedback'))->assertRedirect();
    }
}
