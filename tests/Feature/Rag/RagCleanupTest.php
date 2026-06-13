<?php

namespace Tests\Feature\Rag;

use App\Models\KnowledgeBase;
use App\Models\RagSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RagCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_deletes_expired_ephemeral_knowledge_bases(): void
    {
        $user = User::factory()->create();

        $expiredKb = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Expired KB',
            'is_ephemeral' => true,
            'expires_at' => now()->subDays(1),
        ]);

        $activeKb = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Active KB',
            'is_ephemeral' => true,
            'expires_at' => now()->addDays(7),
        ]);

        $this->artisan('rag:cleanup-ephemeral')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('knowledge_bases', ['id' => $expiredKb->id]);
        $this->assertDatabaseHas('knowledge_bases', ['id' => $activeKb->id]);
    }

    /** @test */
    public function it_preserves_sessions_marked_saved_to_kb(): void
    {
        $user = User::factory()->create();

        $kb = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Saved KB',
            'is_ephemeral' => true,
            'expires_at' => now()->subDays(1),
        ]);

        $session = RagSession::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => $user->id,
            'tool_slug' => 'chat-pdf',
            'knowledge_base_id' => $kb->id,
            'status' => 'ready',
            'saved_to_kb' => true,
        ]);

        $session->messages()->create([
            'id' => \Illuminate\Support\Str::ulid(),
            'role' => 'user',
            'content' => 'Test message',
        ]);

        $this->artisan('rag:cleanup-ephemeral')
            ->assertExitCode(0);

        // KB should be kept (marked permanent) since a session saved to it
        $this->assertDatabaseHas('knowledge_bases', ['id' => $kb->id]);
        $this->assertDatabaseHas('rag_sessions', ['id' => $session->id]);
    }

    /** @test */
    public function it_handles_no_expired_records_gracefully(): void
    {
        $this->artisan('rag:cleanup-ephemeral')
            ->assertExitCode(0);
    }
}
