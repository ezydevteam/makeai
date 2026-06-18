<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckUpdates extends Command
{
    protected $signature = 'updates:check';
    protected $description = 'Check for new version via Envato API and set update_available flag';

    public function handle(): int
    {
        $itemId = config('license.item_id');
        $apiToken = config('license.api_token');

        if (blank($itemId) || blank($apiToken)) {
            $this->warn('Envato credentials not configured yet. Skipping update check.');

            return self::SUCCESS;
        }

        $this->info('Checking for updates...');

        try {
            $response = Http::withToken($apiToken)
                ->timeout(15)
                ->get("https://api.envato.com/v3/market/catalog/item?id={$itemId}");

            if (! $response->successful()) {
                Log::warning('CheckUpdates: Envato API returned status '.$response->status());

                return self::SUCCESS;
            }

            $data = $response->json();

            $latestVersion = $data['version'] ?? $data['wordpress_theme_metadata']['version'] ?? $data['wordpress_plugin_metadata']['version'] ?? null;

            if (blank($latestVersion)) {
                Log::warning('CheckUpdates: Could not determine latest version from API response.');

                return self::SUCCESS;
            }

            $currentVersion = settings('app_version', '1.0.0');
            $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

            settings_set('update_version', $latestVersion, 'string', 'system');
            settings_set('update_available', $updateAvailable, 'boolean', 'system');
            settings_set('update_last_checked', now()->toDateTimeString(), 'string', 'system');

            Cache::forget('update_available');

            if ($updateAvailable) {
                $this->info("Update available: {$currentVersion} → {$latestVersion}");
                Log::info('CheckUpdates: Update available', [
                    'current' => $currentVersion,
                    'latest' => $latestVersion,
                ]);
            } else {
                $this->info("No updates available. Current: {$currentVersion}, Latest: {$latestVersion}");
            }

        } catch (ConnectionException $e) {
            Log::warning('CheckUpdates: Could not connect to Envato API.', ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('CheckUpdates: Unexpected error', ['error' => $e->getMessage()]);
        }

        return self::SUCCESS;
    }
}
