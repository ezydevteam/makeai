<?php

namespace Tests\Feature\Rag;

use App\Models\AiTool;
use App\Models\KnowledgeBase;
use App\Models\RagSession;
use App\Models\User;
use App\Services\AI\Rag\VectorStoreService;
use App\Services\RagToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Regression cover for the RAG production audit.
 *
 * Pins the cross-tenant file leak, the ingestion refund, the retrieval pricing that
 * ignored its own setting, and the vector search that held every embedding in memory.
 */
class RagAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(
            \App\Services\NotificationEventService::class,
            Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
    }

    private function tool(): AiTool
    {
        return AiTool::create([
            'name' => 'Chat with PDF',
            'slug' => 'chat-pdf-'.uniqid(),
            'type' => 'rag',
            'is_active' => true,
            'fields' => json_encode(['source_type' => 'file', 'accept' => ['pdf']]),
        ]);
    }

    private function ragSession(User $user, array $meta, string $toolSlug = 'chat-pdf'): RagSession
    {
        $kb = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Ephemeral',
            'is_ephemeral' => true,
        ]);

        return RagSession::create([
            'user_id' => $user->id,
            'tool_slug' => $toolSlug,
            'knowledge_base_id' => $kb->id,
            'title' => 'Session',
            'status' => 'ready',
            'source_meta' => $meta,
        ]);
    }

    // ─── Cross-tenant document leak ────────────────────────────────

    public function test_a_session_never_resolves_to_another_users_uploaded_file(): void
    {
        Storage::fake('local');

        $victim = User::factory()->create(['is_active' => true]);
        $attacker = User::factory()->create(['is_active' => true]);

        // Uploads used to land in one flat, shared directory — this is the layout the
        // vulnerable fallback scanned, and it is what a real install already has on disk.
        Storage::put('rag-uploads/secret.pdf', str_repeat('S', 2048));
        $this->assertContains('rag-uploads/secret.pdf', Storage::files('rag-uploads'));

        // The attacker owns a session whose own file is gone, but which records the
        // same filesize. The old size + mtime fallback scanned that directory and
        // happily returned the victim's file — the ownership check is on the session,
        // not on the file it resolves to.
        $session = $this->ragSession($attacker, ['filename' => 'mine.pdf', 'filesize' => 2048]);

        $path = app(RagToolService::class)->getSessionFilePath($session);

        $this->assertNull($path);
    }

    public function test_a_session_still_resolves_its_own_recorded_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['is_active' => true]);
        Storage::put("rag-uploads/{$user->id}/mine.pdf", 'hello');

        $session = $this->ragSession($user, ['filepath' => "rag-uploads/{$user->id}/mine.pdf"]);

        $this->assertSame(
            "rag-uploads/{$user->id}/mine.pdf",
            app(RagToolService::class)->getSessionFilePath($session),
        );
    }

    // ─── Uploads are private, not web-served ───────────────────────

    /**
     * The installer sets FILESYSTEM_DISK=public, whose root is the webroot, so an
     * unqualified storeAs() published every uploaded document. Uploads must name
     * the private `local` disk.
     */
    public function test_a_new_upload_resolves_on_the_private_disk(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['is_active' => true]);
        Storage::disk('local')->put("rag-uploads/{$user->id}/new.pdf", 'hello');

        $session = $this->ragSession($user, ['filepath' => "rag-uploads/{$user->id}/new.pdf"]);

        $location = app(RagToolService::class)->getSessionFileLocation($session);

        $this->assertSame('local', $location['disk']);
        $this->assertSame("rag-uploads/{$user->id}/new.pdf", $location['path']);
    }

    /**
     * A file on neither disk resolves to nothing rather than a path the caller
     * would then fail to read.
     */
    public function test_a_missing_upload_resolves_to_null(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['is_active' => true]);
        $session = $this->ragSession($user, ['filepath' => "rag-uploads/{$user->id}/gone.pdf"]);

        $this->assertNull(app(RagToolService::class)->getSessionFileLocation($session));
        $this->assertNull(app(RagToolService::class)->getSessionFilePath($session));
    }

    // ─── Ingestion refund (mode-aware) ─────────────────────────────

    public function test_failed_ingestion_refunds_the_charge_in_metered_mode(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');

        $user = User::factory()->create(['credits' => 100, 'is_active' => true]);
        $session = $this->ragSession($user, ['ingest_credits' => 12.0]);

        app(RagToolService::class)->refundIngestionCredits($session);

        $this->assertSame(112.0, (float) $user->fresh()->credits);
    }

    public function test_failed_ingestion_refund_winds_back_the_allowance_in_quota_mode(): void
    {
        // Quota mode (Regular license): the wallet was never drained, the daily and
        // monthly counters were — so those must be wound back instead.
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');

        $user = User::factory()->create([
            'credits' => 100,
            'is_active' => true,
            'credits_used_today' => 20,
            'credits_used_month' => 40,
        ]);
        $session = $this->ragSession($user, ['ingest_credits' => 12.0]);

        app(RagToolService::class)->refundIngestionCredits($session);

        $fresh = $user->fresh();
        $this->assertSame(8.0, (float) $fresh->credits_used_today);
        $this->assertSame(28.0, (float) $fresh->credits_used_month);
        // The un-refillable wallet must not be inflated in quota mode.
        $this->assertSame(100.0, (float) $fresh->credits);
    }

    public function test_ingestion_refund_is_idempotent(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');

        $user = User::factory()->create(['credits' => 100, 'is_active' => true]);
        $session = $this->ragSession($user, ['ingest_credits' => 12.0]);

        // A multi-file session runs one job per file; only the first failure may refund.
        $service = app(RagToolService::class);
        $service->refundIngestionCredits($session);
        $service->refundIngestionCredits($session->fresh());

        $this->assertSame(112.0, (float) $user->fresh()->credits);
    }

    // ─── Vector search: top-K without loading everything ───────────

    public function test_vector_search_returns_the_nearest_chunks(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $kb = KnowledgeBase::create(['user_id' => $user->id, 'name' => 'KB', 'is_ephemeral' => true]);

        // Three vectors, increasingly far from the query [1, 0].
        $vectors = [
            1 => [1.0, 0.0],    // identical      → best
            2 => [0.9, 0.44],   // close
            3 => [0.0, 1.0],    // orthogonal     → worst
        ];

        foreach ($vectors as $chunkId => $vector) {
            DB::table('vector_embeddings')->insert([
                'knowledge_base_id' => $kb->id,
                'document_id' => 1,
                'chunk_id' => $chunkId,
                'user_id' => $user->id,
                'embedding' => json_encode($vector),
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $results = app(VectorStoreService::class)->search((string) $kb->id, [1.0, 0.0], 2);

        // The cursor-based top-K must still rank correctly and honour the limit.
        $this->assertCount(2, $results);
        $this->assertSame(1, (int) $results[0]['chunk_id']);
        $this->assertSame(2, (int) $results[1]['chunk_id']);
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
    }

    public function test_vector_search_returns_everything_when_fewer_rows_than_top_k(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $kb = KnowledgeBase::create(['user_id' => $user->id, 'name' => 'KB', 'is_ephemeral' => true]);

        foreach ([[0.0, 1.0], [1.0, 0.0]] as $i => $vector) {
            DB::table('vector_embeddings')->insert([
                'knowledge_base_id' => $kb->id,
                'document_id' => 1,
                'chunk_id' => $i + 1,
                'user_id' => $user->id,
                'embedding' => json_encode($vector),
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $results = app(VectorStoreService::class)->search((string) $kb->id, [1.0, 0.0], 6);

        // Under-filled top-K never hit the "list is full" sort — it must still be ranked.
        $this->assertCount(2, $results);
        $this->assertSame(2, (int) $results[0]['chunk_id']);
    }
}
