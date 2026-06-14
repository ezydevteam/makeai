<?php

namespace Addons\PublicKnowledgeBase\Console\Commands;

use Addons\PublicKnowledgeBase\Jobs\IngestKbArticle;
use Addons\PublicKnowledgeBase\Models\KbArticle;
use Illuminate\Console\Command;

class IngestAllKbArticles extends Command
{
    protected $signature = 'kb:ingest-all';

    protected $description = 'Re-ingest all published KB articles (use after changing embedding model)';

    public function handle(): int
    {
        $count = KbArticle::published()->count();

        if ($count === 0) {
            $this->info('No published articles to ingest.');
            return self::SUCCESS;
        }

        $this->info("Dispatching ingestion jobs for {$count} articles...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        KbArticle::published()->chunk(50, function ($articles) use ($bar) {
            foreach ($articles as $article) {
                $article->update(['embed_status' => 'pending', 'embed_error' => null]);
                IngestKbArticle::dispatch($article->id)->onQueue('embeddings');
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('All ingestion jobs dispatched. Monitor the queue for completion.');

        return self::SUCCESS;
    }
}
