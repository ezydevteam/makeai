<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SocialPublishScheduled extends Command
{
    protected $signature = 'social:publish-scheduled';

    protected $description = 'Publish scheduled social media posts (placeholder for addon integration)';

    public function handle(): void
    {
        $this->info(translate('No scheduled social posts found. Install the Social Media Suite addon for full scheduling capabilities.'));
    }
}
