<?php

namespace App\Console\Commands;

use App\Services\CurrencyRateService;
use Illuminate\Console\Command;

class SyncCurrencyRates extends Command
{
    protected $signature = 'currency:sync-rates';

    protected $description = 'Sync exchange rates from external API to the currencies table.';

    public function handle(): int
    {
        $this->info('Fetching exchange rates...');

        $result = CurrencyRateService::fromSettings()->syncRates();

        if (! ($result['success'] ?? false)) {
            $this->error('Rate sync failed: '.($result['error'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        $this->info("Synced {$result['updated']} currency rate(s).");

        return self::SUCCESS;
    }
}
