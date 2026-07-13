<?php

namespace App\Services;

use App\Support\PurchaseCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class UpdateService
{
    /**
     * Ask the License Server whether an update exists for this licensed install.
     * Verifies the Ed25519 signature, persists the result for the admin UI, and
     * returns the verified manifest payload.
     *
     * @return array<string,mixed>
     */
    public function checkForUpdate(): array
    {
        $payload = $this->requestManifest();

        settings_set('update_version', $payload['latest_version'] ?? '', 'string', 'system');
        settings_set('update_available', (bool) ($payload['update_available'] ?? false), 'boolean', 'system');
        settings_set('update_changelog', $payload['changelog'] ?? '', 'string', 'system');
        settings_set('update_last_checked', now()->toDateTimeString(), 'string', 'system');
        \Illuminate\Support\Facades\Cache::forget('update_available');

        return $payload;
    }

    /**
     * One-click update: fetch a fresh signed manifest, download the package via the
     * short-lived signed token, verify it against the SIGNED sha256, then apply.
     */
    public function applyUpdate(): bool
    {
        if (PurchaseCode::testModeActive()) {
            throw new \Exception('Updates cannot be applied in test mode — there is no real package. Disable LICENSE_TEST_MODE and activate a real license (with the License Server deployed) to apply updates.');
        }

        return $this->runUpdate(function (string $tempDir): array {
            $payload = $this->requestManifest();

            if (empty($payload['update_available']) || blank($payload['download_token'] ?? null)) {
                throw new \Exception('No update is currently available to apply.');
            }

            $zipPath = $tempDir . '/update.zip';
            $url = app(LicenseService::class)->downloadEndpoint() . '?token=' . urlencode((string) $payload['download_token']);

            $download = Http::timeout(300)->get($url);
            if (! $download->successful()) {
                throw new \Exception('Failed to download the update package from the license server.');
            }
            File::put($zipPath, $download->body());

            // Integrity: the expected hash came from the SIGNED manifest, so a
            // tampered or truncated download is rejected before any file changes.
            $expected = (string) ($payload['package_sha256'] ?? '');
            if ($expected === '' || ! hash_equals($expected, hash_file('sha256', $zipPath))) {
                throw new \Exception('Downloaded package failed its integrity check. No files were changed.');
            }

            return [$zipPath, $payload['latest_version'] ?? null];
        });
    }

    /**
     * Manual update: apply a package the admin uploaded on the Updates page. Same
     * backup/rollback/apply pipeline, just skipping the download + signature.
     */
    public function applyUpdateFromZip(string $uploadedZipPath): bool
    {
        return $this->runUpdate(function (string $tempDir) use ($uploadedZipPath): array {
            $zipPath = $tempDir . '/update.zip';
            File::copy($uploadedZipPath, $zipPath);

            return [$zipPath, null];
        });
    }

    /**
     * Shared apply pipeline. $obtainZip(string $tempDir): array{0:string zipPath, 1:?string version}.
     */
    private function runUpdate(\Closure $obtainZip): bool
    {
        // Copying/migrating the whole codebase easily exceeds shared-hosting limits;
        // without this the request times out mid-copy and leaves a half-updated,
        // broken install. Best-effort (silently ignored when disabled by the host).
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        // Put the public site behind maintenance while files are swapped. /admin/*
        // is exempt so the admin driving the update keeps working; always lifted
        // again in finally.
        $broughtDown = false;
        try {
            Artisan::call('down', ['--retry' => 60]);
            $broughtDown = true;
        } catch (\Throwable $e) {
            Log::warning('Update: could not enable maintenance mode', ['error' => $e->getMessage()]);
        }

        $tempDir = storage_path('app/temp/update_' . Str::random(8));

        try {
            // 1-2. Backups + prune
            $this->backupDatabase();
            $this->createRollbackZip();
            $this->pruneOldBackups();

            // 3. Obtain the package (download or uploaded file)
            File::makeDirectory($tempDir, 0755, true);
            [$zipPath, $version] = $obtainZip($tempDir);

            // 3b. Sanity-check before touching any live files.
            if (! File::exists($zipPath) || File::size($zipPath) < 100 * 1024) {
                throw new \Exception('The update package is too small or empty. No files were changed.');
            }

            // 4. Extract
            $extractDir = $tempDir . '/extracted';
            File::makeDirectory($extractDir, 0755, true);

            $zip = new ZipArchive;
            $opened = $zip->open($zipPath);
            if ($opened !== true) {
                throw new \Exception('The update package is not a valid zip (code ' . $opened . '). No files were changed.');
            }
            if ($zip->numFiles < 1) {
                $zip->close();
                throw new \Exception('The update package is empty. No files were changed.');
            }
            $zip->extractTo($extractDir);
            $zip->close();

            // 5. Locate the app root inside the package
            $sourceDir = $this->findAppRoot($extractDir);
            if (! $sourceDir) {
                throw new \Exception('Could not find the application root (artisan file) in the package. No files were changed.');
            }

            // 6. Copy files. Only genuine buyer data is protected:
            //    .env (secrets/DB), storage (uploads/logs/backups), addons (own
            //    update + license lifecycle). Everything else — core code, config,
            //    the default theme and vendor — is intentionally updated so your
            //    fixes and dependency bumps actually reach buyers. All buyer
            //    customization lives in the DB `settings` table, which is untouched
            //    (the pipeline runs `migrate` only, never `db:seed`).
            $excludePaths = ['.env', 'storage', 'addons', 'node_modules', '.git'];
            $this->copyFiles($sourceDir, base_path(), $excludePaths);

            // 7. Migrate
            Artisan::call('migrate', ['--force' => true]);

            // 8. Clear + best-effort rebuild caches
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            try {
                Artisan::call('config:cache');
                Artisan::call('route:cache');
            } catch (\Throwable $e) {
                Log::warning('Update: cache rebuild failed, left cleared', ['error' => $e->getMessage()]);
                Artisan::call('config:clear');
                Artisan::call('route:clear');
            }

            // 9. Record the new version (when known)
            if (! blank($version)) {
                settings_set('app_version', $version, 'string', 'system');
                settings_set('update_version', $version, 'string', 'system');
            }
            settings_set('update_available', false, 'boolean', 'system');
            \Illuminate\Support\Facades\Cache::forget('update_available');

            return true;
        } finally {
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            if ($broughtDown) {
                try {
                    Artisan::call('up');
                } catch (\Throwable $e) {
                    Log::error('Update: failed to disable maintenance mode — run `php artisan up`.', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Request + verify a signed update manifest from the License Server using the
     * stored purchase code. Throws on any transport/verification/license failure.
     *
     * @return array<string,mixed>
     */
    private function requestManifest(): array
    {
        $license = app(LicenseService::class);

        $encrypted = settings('license_purchase_code');
        if (blank($encrypted)) {
            throw new \Exception('No license is activated. Activate your license before checking for updates.');
        }

        try {
            $code = Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            throw new \Exception('Stored license could not be read. Please re-activate your license.');
        }

        // In test mode the License Server is never contacted (the license itself is
        // a fake code). Return a simulated manifest so the whole update UI can be
        // previewed locally. Applying is blocked separately (needs the real server).
        if (PurchaseCode::testModeActive()) {
            return $this->testModeManifest(settings('app_version', '1.0.0'));
        }

        try {
            $response = Http::timeout(20)->retry(2, 1000)->post($license->updateEndpoint(), [
                'product' => 'core',
                'slug' => 'makeai',
                'purchase_code' => $code,
                'domain' => request()?->getHost() ?? settings('site_url', ''),
                'current_version' => settings('app_version', '1.0.0'),
            ]);
        } catch (\Throwable $e) {
            throw new \Exception('Could not reach the update server. Please try again later.');
        }

        if (! $response->successful()) {
            throw new \Exception('Update server returned an error (HTTP ' . $response->status() . ').');
        }

        $payload = $license->verifySignedResponse($response->body(), (array) $response->json());
        if ($payload === null) {
            throw new \Exception('The update server response could not be verified. Update aborted for safety.');
        }

        if (empty($payload['valid'])) {
            $map = [
                'not_found' => 'This purchase code was not recognised by the license server.',
                'revoked' => 'This license has been revoked — updates are unavailable.',
                'invalid_format' => 'The stored purchase code is invalid.',
            ];
            throw new \Exception($map[$payload['error'] ?? ''] ?? 'License check failed while requesting the update.');
        }

        return $payload;
    }

    /**
     * A simulated update manifest for LICENSE_TEST_MODE — no server contact. Set
     * settings('update_test_latest_version') to control the reported version; by
     * default it reports a bumped version so the update UI is visible for preview.
     *
     * @return array<string,mixed>
     */
    private function testModeManifest(string $current): array
    {
        $latest = (string) settings('update_test_latest_version', $this->bumpVersion($current));
        $available = version_compare($latest, $current, '>');

        return [
            'valid' => true,
            'slug' => 'makeai',
            'update_available' => $available,
            'latest_version' => $latest,
            'changelog' => $available
                ? "(Test mode) Simulated update to preview the update UI. Applying requires the live License Server."
                : null,
            'package_sha256' => null,
            'download_token' => null,
            'error' => null,
        ];
    }

    private function bumpVersion(string $version): string
    {
        $parts = explode('.', $version ?: '1.0.0');
        $parts[count($parts) - 1] = (string) ((int) end($parts) + 1);

        return implode('.', $parts);
    }

    /**
     * Rollback to the previous version (available for 24h).
     */
    public function rollbackUpdate(): bool
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

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

        // Restore everything the update could have changed; only keep the live
        // .env and storage (secrets + buyer uploads/data).
        $excludeDirs = ['.env', 'storage'];
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
            $host = (string) config('database.connections.mysql.host');
            $port = (string) config('database.connections.mysql.port');
            $database = (string) config('database.connections.mysql.database');
            $username = (string) config('database.connections.mysql.username');
            $password = (string) config('database.connections.mysql.password');
            $dumpFile = "{$backupDir}/database_{$timestamp}.sql";

            // Escape every argument, and pass the password via MYSQL_PWD instead of
            // the command line (which would leak it in the process list and break on
            // passwords containing shell metacharacters).
            $command = sprintf(
                'mysqldump -h %s -P %s -u %s %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database),
                escapeshellarg($dumpFile)
            );

            putenv('MYSQL_PWD=' . $password);
            exec($command, $output, $returnVar);
            putenv('MYSQL_PWD');

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

                // Back up everything the updater can overwrite so a rollback is
                // complete — including vendor (dependency bumps are now applied).
                // Only skip volatile/never-restored paths and secrets.
                if (in_array($firstDir, ['storage', 'node_modules', '.git']) || $relativePath === '.env') {
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

    /**
     * Delete backup dumps / rollback zips older than $keepDays so they don't
     * accumulate and fill the disk. The most recent rollback (needed for the 24h
     * rollback window) is far newer than the cutoff and is always retained.
     */
    private function pruneOldBackups(int $keepDays = 14): void
    {
        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            return;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();

        foreach (File::files($backupDir) as $file) {
            if ($file->getMTime() < $cutoff) {
                @File::delete($file->getPathname());
            }
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
        // Normalise excludes to forward-slash so both top-level ('config') and
        // nested ('resources/themes') paths match reliably across OSes.
        $exclude = array_map(fn (string $p) => str_replace('\\', '/', $p), $exclude);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $normalized = str_replace('\\', '/', $relativePath);

            // Exclude the exact path OR anything beneath it (so an excluded dir
            // protects its whole subtree, including nested excludes like themes).
            $isExcluded = false;
            foreach ($exclude as $ex) {
                if ($normalized === $ex || str_starts_with($normalized, $ex . '/')) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
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

}
