<?php

namespace App\Console\Commands;

use App\Services\SocialService;
use Illuminate\Console\Command;

class RefreshSocialCounts extends Command
{
    protected $signature = 'social:refresh';

    protected $description = 'Refresh social media follow counts from external APIs';

    public function handle(SocialService $socialService): void
    {
        $this->info('Refreshing social follow counts...');
        $socialService->refreshCounts();
        $this->info('Done!');
    }
}
