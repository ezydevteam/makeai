<?php

namespace Tests\Feature\Rag;

use App\Models\AiTool;
use App\Models\KnowledgeBase;
use App\Models\RagSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RagSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AiTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['credits' => 500]);
        $this->tool = AiTool::create([
            'name' => 'Chat with PDF',
            'slug' => 'chat-pdf',
            'type' => 'rag',
            'is_active' => true,
            'fields' => json_encode([
                'source_type' => 'file',
                'accept' => ['pdf'],
            ]),
        ]);

        // Seed default RAG settings
        settings_set('rag_max_file_mb', '25', 'integer', 'rag');
        settings_set('rag_ephemeral_retention_days', '7', 'integer', 'rag');
    }

    /** @test */
    public function it_creates_a_rag_session_with_file_upload(): void
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->post("/tools/rag/chat-pdf/sessions", [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('session.status', 'ingesting');
        $this->assertDatabaseHas('rag_sessions', [
            'tool_slug' => 'chat-pdf',
            'user_id' => $this->user->id,
            'status' => 'ingesting',
        ]);
        $this->assertDatabaseHas('knowledge_bases', [
            'user_id' => $this->user->id,
            'is_ephemeral' => true,
        ]);
    }

    /** @test */
    public function it_rejects_invalid_file_types(): void
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('test.exe', 100, 'application/x-msdownload');

        $response = $this->post("/tools/rag/chat-pdf/sessions", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_rejects_files_larger_than_max_size(): void
    {
        $this->actingAs($this->user);

        settings_set('rag_max_file_mb', '1', 'integer', 'rag');

        $file = UploadedFile::fake()->create('large.pdf', 3000, 'application/pdf');

        $response = $this->post("/tools/rag/chat-pdf/sessions", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_returns_404_for_non_owned_session(): void
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

        $response = $this->get("/tools/rag/sessions/{$otherSession->id}/status");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_rejects_chat_when_session_not_ready(): void
    {
        $this->actingAs($this->user);

        $session = RagSession::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => $this->user->id,
            'tool_slug' => 'chat-pdf',
            'knowledge_base_id' => 1,
            'status' => 'ingesting',
        ]);

        $response = $this->post("/tools/rag/sessions/{$session->id}/chat", [
            'message' => 'Hello',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_requires_authentication_for_rag_routes(): void
    {
        $response = $this->post('/tools/rag/chat-pdf/sessions', [
            'file' => UploadedFile::fake()->create('test.pdf', 100),
        ]);

        $response->assertRedirect('/login');
    }

    /** @test */
    public function it_saves_to_knowledge_base(): void
    {
        $this->actingAs($this->user);

        $kb = KnowledgeBase::create([
            'user_id' => $this->user->id,
            'name' => 'Temp KB',
            'is_ephemeral' => true,
            'expires_at' => now()->addDays(7),
        ]);

        $session = RagSession::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => $this->user->id,
            'tool_slug' => 'chat-pdf',
            'knowledge_base_id' => $kb->id,
            'status' => 'ready',
        ]);

        $response = $this->post("/tools/rag/sessions/{$session->id}/save-to-kb", [
            'name' => 'My Saved KB',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('knowledge_bases', [
            'id' => $kb->id,
            'name' => 'My Saved KB',
            'is_ephemeral' => false,
            'expires_at' => null,
        ]);
        $this->assertDatabaseHas('rag_sessions', [
            'id' => $session->id,
            'saved_to_kb' => true,
        ]);
    }

    /** @test */
    public function it_deletes_session(): void
    {
        $this->actingAs($this->user);

        $kb = KnowledgeBase::create([
            'user_id' => $this->user->id,
            'name' => 'Temp',
            'is_ephemeral' => true,
            'expires_at' => now()->addDays(7),
        ]);

        $session = RagSession::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => $this->user->id,
            'tool_slug' => 'chat-pdf',
            'knowledge_base_id' => $kb->id,
            'status' => 'ready',
        ]);

        $response = $this->delete("/tools/rag/sessions/{$session->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('rag_sessions', ['id' => $session->id]);
    }

    /** @test */
    public function it_returns_session_and_messages(): void
    {
        $this->actingAs($this->user);

        $kb = KnowledgeBase::create([
            'user_id' => $this->user->id,
            'name' => 'Test KB',
            'is_ephemeral' => true,
        ]);

        $session = RagSession::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => $this->user->id,
            'tool_slug' => 'chat-pdf',
            'knowledge_base_id' => $kb->id,
            'status' => 'ready',
            'title' => 'My Chat Session',
        ]);

        $response = $this->get("/tools/rag/sessions/{$session->id}");

        $response->assertOk();
        $response->assertJsonPath('session.title', 'My Chat Session');
    }
}
