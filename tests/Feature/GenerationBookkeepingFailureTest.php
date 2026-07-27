<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\GenerateController;
use App\Models\AiTool;
use App\Models\Document;
use App\Models\User;
use App\Services\GenerationHistoryService;
use App\Services\NotificationEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A generation the user has already paid for must survive its own bookkeeping failing.
 *
 * The bug this pins shipped and broke a live site. saveGeneratedDocument() and the
 * history dispatch both ran bare inside the generation handler's try block, so a throw
 * from either was caught by that handler, flattened by AiErrors::sanitize() into the
 * generic "Something went wrong. Please try again or contact support.", and the finished
 * output was discarded — after the model had produced it and the credits were spent.
 *
 * It was invisible in development. With a real queue driver the dispatch only writes a
 * row, and a failing job surfaces later in failed_jobs, nowhere near the request. Under
 * QUEUE_CONNECTION=sync — a legitimate choice on shared hosting with no worker, and what
 * the affected install ran — the job executes INLINE inside the streaming request, so its
 * exception propagated into the user's response. Hence both tests below force sync: that
 * is the configuration under which the guard has to hold.
 *
 * Reflection is used because the seams are private and the alternative — faking a full
 * provider round trip to reach them through the HTTP endpoint — would test the mock far
 * more than the guard.
 */
class GenerationBookkeepingFailureTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(string $method, array $args): mixed
    {
        $call = new ReflectionMethod(GenerateController::class, $method);
        $call->setAccessible(true);

        return $call->invokeArgs(app(GenerateController::class), $args);
    }

    private function tool(): AiTool
    {
        return AiTool::create([
            'name' => 'Blog Writer',
            'slug' => 'blog-writer-'.uniqid(),
            'description' => 'test',
            'prompt_system' => 'You write blogs.',
            'is_active' => true,
        ]);
    }

    public function test_a_failing_document_save_does_not_destroy_the_generation(): void
    {
        // documentReady() runs inside saveGeneratedDocument(), so this is the real
        // shape of the failure: the row is written, then a listener blows up.
        $this->instance(
            NotificationEventService::class,
            Mockery::mock(NotificationEventService::class)
                ->shouldReceive('documentReady')->andThrow(new \RuntimeException('notification backend down'))
                ->getMock(),
        );

        $user = User::factory()->create(['is_active' => true]);

        $document = $this->invoke('trySaveGeneratedDocument', [$this->tool(), $user, 'the generated article']);

        // Swallowed, not rethrown: reaching this line at all is the assertion that
        // matters. Returning null lets the caller omit the document from the payload
        // while still sending the content.
        $this->assertNull($document);
    }

    public function test_a_failing_history_job_does_not_destroy_the_generation(): void
    {
        // The exact production configuration: sync runs the job inline, in-request.
        config(['queue.default' => 'sync']);

        $this->instance(
            NotificationEventService::class,
            Mockery::mock(NotificationEventService::class)->shouldIgnoreMissing(),
        );

        $this->instance(
            GenerationHistoryService::class,
            Mockery::mock(GenerationHistoryService::class)
                ->shouldReceive('record')->andThrow(new \RuntimeException('history table is gone'))
                ->getMock(),
        );

        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        $document = $this->invoke('persistGeneration', [
            $tool, $user, 'the generated article', true, [
                'tool_slug' => $tool->slug,
                'prompt_system' => 'sys',
                'prompt_user' => 'usr',
                'field_values' => ['topic' => 'seo'],
                'model' => 'gpt-4o-mini',
                'provider' => 'openai',
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'output_preview' => 'the generated article',
                'tokens_input' => 10,
                'tokens_output' => 20,
            ],
        ]);

        // The document survives a dead history pipeline — losing the audit row is a
        // reporting gap, losing this is the user's paid-for work.
        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame('the generated article', $document->content);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_the_guard_does_not_swallow_a_successful_save(): void
    {
        // The counterpart to the two above: a guard that always returned null would
        // pass both of them and silently stop recording anything at all.
        config(['queue.default' => 'sync']);

        $this->instance(
            NotificationEventService::class,
            Mockery::mock(NotificationEventService::class)->shouldIgnoreMissing(),
        );

        // record() is typed to return GenerationHistory. Returning null here would raise
        // a TypeError that the guard would then swallow — the test would pass without
        // proving anything about the payload. Return a real model so the only thing
        // under test is what persistGeneration passed in.
        $recorded = [];
        $this->instance(
            GenerationHistoryService::class,
            Mockery::mock(GenerationHistoryService::class)
                ->shouldReceive('record')->andReturnUsing(function ($user, $data) use (&$recorded) {
                    $recorded = $data;

                    return new \App\Models\GenerationHistory;
                })
                ->getMock(),
        );

        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        $document = $this->invoke('persistGeneration', [
            $tool, $user, 'the generated article', true, [
                'tool_slug' => $tool->slug,
                'model' => 'gpt-4o-mini',
            ],
        ]);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame($tool->slug, $recorded['tool_slug'] ?? null);
        // document_id is filled in by persistGeneration, not the caller.
        $this->assertSame($document->id, $recorded['document_id'] ?? null);
    }

    public function test_ineligible_generations_are_skipped_without_writing_anything(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        // Guest previews and empty completions must not create documents.
        $this->assertNull($this->invoke('persistGeneration', [$tool, $user, 'content', false, []]));
        $this->assertNull($this->invoke('persistGeneration', [$tool, $user, '', true, []]));
        $this->assertNull($this->invoke('persistGeneration', [$tool, null, 'content', true, []]));

        $this->assertDatabaseCount('documents', 0);
    }
}
