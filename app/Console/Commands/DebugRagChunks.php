<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugRagChunks extends Command
{
    protected $signature = 'rag:debug';

    public function handle(): void
    {
        $chunks = DB::table('knowledge_base_chunks')->count();
        $vectors = DB::table('vector_embeddings')->count();
        $docs = DB::table('knowledge_base_documents')->get();
        $sessions = DB::table('rag_sessions')->get(['id', 'status', 'knowledge_base_id']);

        $this->info("Chunks: {$chunks}, Vectors: {$vectors}");

        foreach ($docs as $doc) {
            $this->line("  Doc: {$doc->filename} | status={$doc->status} | chunks={$doc->chunk_count} | kb_id={$doc->knowledge_base_id}");
        }

        foreach ($sessions as $s) {
            $this->line("  Session: {$s->id} | status={$s->status} | kb_id={$s->knowledge_base_id}");
        }
    }
}
