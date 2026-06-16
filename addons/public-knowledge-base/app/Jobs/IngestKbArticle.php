<?php

namespace Addons\PublicKnowledgeBase\Jobs;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbEmbedding;
use App\Services\AI\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class IngestKbArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $articleId,
    ) {}

    public function handle(AiService $ai): void
    {
        $article = KbArticle::find($this->articleId);

        if (! $article) {
            return;
        }

        if ($article->status !== 'published') {
            return;
        }

        $article->update(['embed_status' => 'processing']);

        $text = $article->body_plain;

        if (empty($text)) {
            $article->update([
                'embed_status' => 'failed',
                'embed_error' => 'No plain text content',
            ]);
            return;
        }

        $chunks = $this->chunkText($article->title, $text);

        if (empty($chunks)) {
            $article->update([
                'embed_status' => 'failed',
                'embed_error' => 'Text too short to chunk',
            ]);
            return;
        }

        $provider = addon_setting('public-knowledge-base', 'provider');
        if (empty($provider)) {
            $provider = settings('default_ai_provider', 'openai');
        }

        $rows = [];
        foreach ($chunks as $index => $chunkText) {
            $result = $ai->embedText($chunkText, $provider);
            $rows[] = [
                'kb_article_id' => $article->id,
                'chunk_index' => $index,
                'chunk_text' => $chunkText,
                'embedding' => json_encode($result->vector),
                'token_count' => (int) (strlen($chunkText) / 4),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        KbEmbedding::where('kb_article_id', $article->id)->delete();
        KbEmbedding::insert($rows);

        $article->update([
            'embed_status' => 'done',
            'embedded_at' => now(),
            'embed_error' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        KbArticle::where('id', $this->articleId)->update([
            'embed_status' => 'failed',
            'embed_error' => Str::limit($exception->getMessage(), 500),
        ]);
    }

    private function chunkText(string $title, string $text): array
    {
        $maxChars = 1600;

        // Split by sentence while keeping the delimiter
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if (strlen($current . ' ' . $sentence) > $maxChars && strlen($current) > 100) {
                $chunks[] = trim($current);
                $current = $sentence;
            } else {
                $current .= ($current ? ' ' : '') . $sentence;
            }
        }

        if (strlen(trim($current)) >= 100) {
            $chunks[] = trim($current);
        }

        if (empty($chunks)) {
            $chunks[] = $text;
        }

        $final = [];
        foreach ($chunks as $i => $chunk) {
            $final[] = ($i === 0 ? "Title: {$title}\n\n" : "From article: {$title}\n\n") . $chunk;
        }

        return $final;
    }
}
