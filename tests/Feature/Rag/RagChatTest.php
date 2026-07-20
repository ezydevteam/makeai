<?php

namespace Tests\Feature\Rag;

use App\Models\AiTool;
use App\Models\KnowledgeBase;
use App\Models\RagSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RagChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RagSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['credits' => 500]);

        AiTool::create([
            'name' => 'Chat with PDF',
            'slug' => 'chat-pdf',
            'type' => 'rag',
            'is_active' => true,
            'fields' => json_encode(['source_type' => 'file', 'accept' => ['pdf']]),
        ]);

        $kb = KnowledgeBase::create([
            'user_id' => $this->user->id,
            'name' => 'Test KB',
            'is_ephemeral' => true,
            'expires_at' => now()->addDays(7),
        ]);

        $this->session = RagSession::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => $this->user->id,
            'tool_slug' => 'chat-pdf',
            'knowledge_base_id' => $kb->id,
            'status' => 'ready',
            'title' => 'Test Session',
        ]);

        settings_set('rag_system_prompt', 'You are helpful. {context}', 'string', 'rag');
        settings_set('rag_top_k', '6', 'integer', 'rag');
    }

    /** @test */
    public function it_rejects_chat_without_message(): void
    {
        $this->actingAs($this->user);

        $response = $this->post("/tools/rag/sessions/{$this->session->id}/chat", [
            'message' => '',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_rejects_chat_for_other_users_session(): void
    {
        $this->actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherSession = RagSession::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => $otherUser->id,
            'tool_slug' => 'chat-pdf',
            'knowledge_base_id' => 1,
            'status' => 'ready',
        ]);

        $response = $this->post("/tools/rag/sessions/{$otherSession->id}/chat", [
            'message' => 'Hello',
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_requires_auth_for_chat(): void
    {
        $response = $this->post("/tools/rag/sessions/{$this->session->id}/chat", [
            'message' => 'Hello',
        ]);

        $response->assertRedirect('/login');
    }

    /** @test */
    public function it_can_export_chat_as_markdown(): void
    {
        $this->actingAs($this->user);

        // Add some messages
        $this->session->messages()->create([
            'id' => \Illuminate\Support\Str::ulid(),
            'role' => 'user',
            'content' => 'What is the revenue?',
        ]);

        $this->session->messages()->create([
            'id' => \Illuminate\Support\Str::ulid(),
            'role' => 'assistant',
            'content' => 'The revenue was $4.2M.',
            'sources' => json_encode([['doc' => 'report.pdf', 'score' => 0.89]]),
        ]);

        $response = $this->get("/tools/rag/sessions/{$this->session->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
        $response->assertSee('# Test Session');
        $response->assertSee('What is the revenue?');
        $response->assertSee('The revenue was $4.2M.');
    }
}
