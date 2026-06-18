<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class UpdateService
{
    /**
     * Apply a one-click update from Envato.
     */
    public function applyUpdate(): bool
    {
        $itemId = config('license.item_id');
        $apiToken = config('license.api_token');

        if (blank($itemId) || blank($apiToken)) {
            throw new \Exception('Envato Item ID and API Token are required in config/license.php or .env.');
        }

        // 1. Database Backup
        $this->backupDatabase();

        // 2. Create Rollback Zip (current codebase, excluding volatile dirs)
        $this->createRollbackZip();

        // 3. Download Update Package
        $tempDir = storage_path('app/temp/update_' . Str::random(8));
        File::makeDirectory($tempDir, 0755, true);
        $zipPath = $tempDir . '/update.zip';

        $response = Http::withToken($apiToken)
            ->timeout(30)
            ->get("https://api.envato.com/v3/market/buyer/download?item_id={$itemId}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get download link from Envato. Status: ' . $response->status());
        }

        $downloadUrl = $response->json('wordpress') ?? $response->json('php') ?? $response->json('download_url');

        if (blank($downloadUrl)) {
            throw new \Exception('No download URL found in Envato response.');
        }

        $downloadResponse = Http::timeout(300)->get($downloadUrl);
        if (!$downloadResponse->successful()) {
            throw new \Exception('Failed to download update package from Envato.');
        }
        File::put($zipPath, $downloadResponse->body());

        // 4. Extract Package
        $extractDir = $tempDir . '/extracted';
        File::makeDirectory($extractDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractDir);
            $zip->close();
        } else {
            throw new \Exception('Failed to extract update package.');
        }

        // 5. Find the actual application root inside the extracted zip
        $sourceDir = $this->findAppRoot($extractDir);
        if (!$sourceDir) {
            throw new \Exception('Could not find application root (artisan file) in the downloaded package.');
        }

        // 6. Copy files (excluding protected directories)
        $excludeDirs = ['config', '.env', 'storage', 'addons', 'resources/themes', 'public/themes', 'vendor', 'node_modules', '.git'];
        $this->copyFiles($sourceDir, base_path(), $excludeDirs);

        // 7. Run Migrations
        Artisan::call('migrate', ['--force' => true]);

        // 8. Clear and Rebuild Caches
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');

        // 9. Update version in settings
        $latestVersion = $this->getLatestVersion($itemId, $apiToken);
        if ($latestVersion) {
            settings_set('app_version', $latestVersion, 'string', 'system');
            settings_set('update_version', $latestVersion, 'string', 'system');
            settings_set('update_available', false, 'boolean', 'system');
        }

        // 10. Cleanup temp files
        File::deleteDirectory($tempDir);

        return true;
    }

    /**
     * Rollback to the previous version (available for 24h).
     */
    public function rollbackUpdate(): bool
    {
        $rollbackZip = settings('last_rollback_zip');
        $rollbackTime = settings('last_rollback_time');

        if (blank($rollbackZip) || blank($rollbackTime)) {
            throw new \Exception('No rollback package is available.');
        }

        $rollbackTimeObj = Carbon::parse($rollbackTime);
        if ($rollbackTimeObj->lt(now()->subHours(24))) {
            throw new \Exception('Rollback is only available for 24 hours post-update.');
        }

        $zipPath = storage_path('app/backups/' . $rollbackZip);
        if (!File::exists($zipPath)) {
            throw new \Exception('Rollback zip file not found in storage.');
        }

        $tempDir = storage_path('app/temp/rollback_' . Str::random(8));
        File::makeDirectory($tempDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            throw new \Exception('Failed to extract rollback package.');
        }

        // Copy files back (excluding protected dirs to keep current DB config)
        $excludeDirs = ['storage', '.env', 'config'];
        $this->copyFiles($tempDir, base_path(), $excludeDirs);

        // Clear rollback info
        settings_set('last_rollback_zip', null, 'string', 'system');
        settings_set('last_rollback_time', null, 'string', 'system');

        // Clear caches
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');

        File::deleteDirectory($tempDir);

        return true;
    }

    private function backupDatabase(): void
    {
        $db = config('database.default');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('app/backups');
        File::makeDirectory($backupDir, 0755, true, true);

        if ($db === 'sqlite') {
            $dbPath = database_path('database.sqlite');
            if (File::exists($dbPath)) {
                File::copy($dbPath, "{$backupDir}/database_{$timestamp}.sqlite");
            }
        } else {
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');

            $command = "mysqldump -h {$host} -P {$port} -u {$username} -p'{$password}' {$database} > {$backupDir}/database_{$timestamp}.sql 2>&1";
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                Log::warning('mysqldump failed, falling back to schema dump', ['output' => implode("\n", $output)]);
                Artisan::call('schema:dump');
                if (File::exists(database_path('schema/mysql-schema.dump'))) {
                    File::copy(database_path('schema/mysql-schema.dump'), "{$backupDir}/database_{$timestamp}.sql");
                }
            }
        }
    }

    private function createRollbackZip(): void
    {
        $zipPath = storage_path('app/backups/rollback_' . now()->format('Y-m-d_H-i-s') . '.zip');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path(), \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                $relativePath = substr($name, strlen(base_path()) + 1);
                $firstDir = explode(DIRECTORY_SEPARATOR, $relativePath)[0] ?? '';

                if (in_array($firstDir, ['storage', 'vendor', 'node_modules', '.git', 'bootstrap/cache']) || $relativePath === '.env') {
                    continue;
                }

                if (!$file->isDir()) {
                    $zip->addFile($name, $relativePath);
                }
            }
            $zip->close();

            settings_set('last_rollback_zip', basename($zipPath), 'string', 'system');
            settings_set('last_rollback_time', now()->toDateTimeString(), 'string', 'system');
        } else {
            Log::warning('Failed to create rollback zip.');
        }
    }

    private function findAppRoot(string $dir): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'artisan' && $file->isFile()) {
                return $file->getPath();
            }
        }
        return null;
    }

    private function copyFiles(string $source, string $destination, array $exclude): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $firstDir = explode(DIRECTORY_SEPARATOR, $relativePath)[0] ?? '';

            if (in_array($firstDir, $exclude) || $relativePath === '.env') {
                if ($item->isDir()) {
                    $iterator->next();
                }
                continue;
            }

            $destPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!File::exists($destPath)) {
                    File::makeDirectory($destPath, 0755, true);
                }
            } else {
                File::copy($item->getPathname(), $destPath);
            }
        }
    }

    private function getLatestVersion(string $itemId, string $apiToken): ?string
    {
        $response = Http::withToken($apiToken)
            ->timeout(15)
            ->get("https://api.envato.com/v3/market/catalog/item?id={$itemId}");

        if ($response->successful()) {
            return $response->json('version');
        }
        return null;
    }
}