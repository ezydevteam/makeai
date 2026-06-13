<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Models\RagSession;
use App\Services\AI\Rag\VectorStoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupEphemeralRag extends Command
{
    protected $signature = 'rag:cleanup-ephemeral';
    protected $description = 'Delete expired ephemeral RAG knowledge bases and sessions.';

    public function handle(VectorStoreService $vectorStore): int
    {
        $expiredKbs = KnowledgeBase::where('is_ephemeral', true)
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredKbs->isEmpty()) {
            $this->info('No expired ephemeral knowledge bases found.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiredKbs->count()} expired ephemeral knowledge base(s).");

        foreach ($expiredKbs as $kb) {
            $sessions = RagSession::where('knowledge_base_id', $kb->id)->get();
            $savedSessionCount = 0;
            $deletedSessionCount = 0;

            foreach ($sessions as $session) {
                if ($session->saved_to_kb) {
                    // KB is now permanent — only clean up temp files
                    $this->line("  Session {$session->id} saved to KB — retaining chat history.");
                    $savedSessionCount++;
                } else {
                    // Delete session + messages
                    $session->messages()->delete();
                    $session->delete();
                    $deletedSessionCount++;
                }
            }

            if ($savedSessionCount === 0) {
                // Delete all vectors and documents
                $documents = DB::table('knowledge_base_documents')
                    ->where('knowledge_base_id', $kb->id)
                    ->pluck('id');

                foreach ($documents as $docId) {
                    $vectorStore->deleteDocumentVectors($docId);
                }

                DB::table('knowledge_base_chunks')
                    ->whereIn('document_id', $documents)
                    ->delete();

                DB::table('knowledge_base_documents')
                    ->where('knowledge_base_id', $kb->id)
                    ->delete();

                $kb->delete();
                $this->line("  Deleted KB {$kb->name} ({$kb->id}) and {$deletedSessionCount} session(s).");
            } else {
                // Saved sessions exist — keep the KB but mark as permanent
                $this->line("  KB {$kb->name} ({$kb->id}): {$savedSessionCount} session(s) saved, {$deletedSessionCount} deleted.");
            }
        }

        $this->info('Cleanup complete.');

        return self::SUCCESS;
    }
}
