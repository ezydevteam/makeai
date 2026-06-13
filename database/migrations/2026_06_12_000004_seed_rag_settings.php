<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $ragSettings = [
            'rag_max_file_mb' => ['value' => '25', 'type' => 'integer', 'group' => 'rag'],
            'rag_max_pages' => ['value' => '300', 'type' => 'integer', 'group' => 'rag'],
            'rag_top_k' => ['value' => '6', 'type' => 'integer', 'group' => 'rag'],
            'rag_chunk_size' => ['value' => '800', 'type' => 'integer', 'group' => 'rag'],
            'rag_chunk_overlap' => ['value' => '100', 'type' => 'integer', 'group' => 'rag'],
            'rag_embedding_model' => ['value' => '', 'type' => 'string', 'group' => 'rag'],
            'rag_system_prompt' => ['value' => 'You are a helpful document assistant. Below is content extracted from the user\'s document, split into numbered sections [0], [1], [2], etc. Answer the user\'s question using ONLY this content.

{context}

Rules:
- If the answer is in the content, explain it clearly and cite the section numbers
- If the question is partially covered, explain what IS in the document about that topic, then note what is missing
- If the document has nothing about the question at all, say: "This document doesn\'t cover that topic. Here\'s what it DOES contain: [brief overview of the document\'s subject matter based on the sections provided]"
- Never invent or guess facts not present in the document

Be concise and helpful.', 'type' => 'string', 'group' => 'rag'],
            'rag_ephemeral_retention_days' => ['value' => '7', 'type' => 'integer', 'group' => 'rag'],
            'rag_chunks_per_credit' => ['value' => '50', 'type' => 'integer', 'group' => 'rag'],
            'rag_youtube_whisper_fallback' => ['value' => '0', 'type' => 'boolean', 'group' => 'rag'],
            'rag_search_mode' => ['value' => 'vector', 'type' => 'string', 'group' => 'rag'],
            'rag_chunking_mode' => ['value' => 'fixed', 'type' => 'string', 'group' => 'rag'],
            'rag_map_reduce_batch_size' => ['value' => '10', 'type' => 'integer', 'group' => 'rag'],
        ];

        foreach ($ragSettings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        $keys = [
            'rag_max_file_mb', 'rag_max_pages', 'rag_top_k', 'rag_chunk_size',
            'rag_chunk_overlap', 'rag_embedding_model', 'rag_system_prompt',
            'rag_ephemeral_retention_days', 'rag_chunks_per_credit',
            'rag_youtube_whisper_fallback', 'rag_search_mode', 'rag_chunking_mode',
            'rag_map_reduce_batch_size',
        ];

        foreach ($keys as $key) {
            \DB::table('settings')->where('key', $key)->delete();
        }
    }
};
