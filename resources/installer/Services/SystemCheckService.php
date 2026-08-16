<?php

namespace Installer\Services;

/**
 * Runs system requirement checks for the installation wizard.
 * Returns structured results for the Vue frontend to display.
 */
class SystemCheckService
{
    /**
     * Run all system checks.
     * Returns an associative array keyed by category,
     * each containing an array of check results.
     */
    public function check(): array
    {
        return [
            'php_version' => [
                [
                    'label'    => 'PHP Version',
                    'required' => '8.3.0+',
                    'current'  => PHP_VERSION,
                    'pass'     => version_compare(PHP_VERSION, '8.3.0', '>='),
                    'severity' => 'error', // hard-blocker
                ],
            ],
            'core_files'      => $this->checkCoreFiles(),
            'extensions'      => $this->checkExtensions(),
            'writable_dirs'   => $this->checkWritableDirs(),
            'server_config'   => $this->checkServerConfig(),
        ];
    }

    /**
     * Returns true only if every single check passes.
     */
    public function allPass(): bool
    {
        foreach ($this->check() as $category => $checks) {
            foreach ($checks as $check) {
                if (($check['severity'] ?? 'error') === 'error' && ! ($check['pass'] ?? false)) {
                    return false;
                }
            }
        }

        return true;
    }

    // ─── Private helpers ─────────────────────────────

    private function checkCoreFiles(): array
    {
        $vendorAutoload = base_path('vendor/autoload.php');

        return [
            [
                'label'    => 'Vendor Autoloader',
                'required' => 'Present',
                'current'  => file_exists($vendorAutoload) ? 'Found' : 'Missing',
                'pass'     => file_exists($vendorAutoload),
                'severity' => 'error',
                'message'  => file_exists($vendorAutoload) 
                    ? '' 
                    : 'vendor folder missing — please re-extract the zip, do not delete the app/vendor folder.',
            ],
        ];
    }

    private function checkExtensions(): array
    {
        $extensions = [
            'pdo'           => ['required' => 'PDO', 'severity' => 'error'],
            'pdo_mysql'     => ['required' => 'PDO MySQL', 'severity' => 'error'],
            'mbstring'      => ['required' => 'Multibyte String', 'severity' => 'error'],
            'json'          => ['required' => 'JSON', 'severity' => 'error'],
            'openssl'       => ['required' => 'OpenSSL', 'severity' => 'error'],
            'tokenizer'     => ['required' => 'Tokenizer', 'severity' => 'error'],
            'xml'           => ['required' => 'XML', 'severity' => 'error'],
            'dom'           => ['required' => 'DOM (Excel/PDF export)', 'severity' => 'error'],
            'iconv'         => ['required' => 'Iconv', 'severity' => 'error'],
            'ctype'         => ['required' => 'Ctype', 'severity' => 'error'],
            'bcmath'        => ['required' => 'BCMath', 'severity' => 'error'],
            'curl'          => ['required' => 'cURL', 'severity' => 'error'],
            'fileinfo'      => ['required' => 'Fileinfo', 'severity' => 'error'],
            // Not optional despite looking like it: phpoffice/phpspreadsheet declares
            // ext-zip as a hard requirement (xlsx is a zip container), so without it every
            // Excel export in the Export Center fails — and so does uploading an addon or
            // theme, both of which extract a zip. Downgrading this to a warning ships a
            // buyer an install whose paid addons cannot be installed at all.
            'zip'           => ['required' => 'Zip (Excel export, addon/theme upload)', 'severity' => 'error'],
            'gd'            => ['required' => 'GD (image processing)', 'severity' => 'warning'],
            'mysqli'        => ['required' => 'MySQLi', 'severity' => 'warning'],
            'intl'          => ['required' => 'Intl (internationalization)', 'severity' => 'warning'],
            'pcntl'         => ['required' => 'PCNTL (Horizon queue worker)', 'severity' => 'warning'],
            'posix'         => ['required' => 'POSIX (Horizon queue worker)', 'severity' => 'warning'],
            'exif'          => ['required' => 'Exif', 'severity' => 'warning'],
        ];

        $results = [];

        foreach ($extensions as $ext => $meta) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'label'    => $meta['required'],
                'required' => $ext,
                'current'  => $loaded ? 'Enabled' : 'Not installed',
                'pass'     => $loaded,
                'severity' => $meta['severity'],
            ];
        }

        // Redis: satisfied by either the phpredis extension or the bundled
        // Predis client, so the app can always talk to a Redis server.
        $phpredis = extension_loaded('redis');
        $predis   = class_exists(\Predis\Client::class);
        $results[] = [
            'label'    => 'Redis (phpredis or Predis)',
            'required' => 'redis',
            'current'  => $phpredis
                ? 'phpredis extension'
                : ($predis ? 'Predis (bundled)' : 'Not available'),
            'pass'     => $phpredis || $predis,
            'severity' => 'warning',
        ];

        return $results;
    }

    private function checkWritableDirs(): array
    {
        $dirs = [
            'storage'                   => storage_path(),
            'storage/logs'              => storage_path('logs'),
            'storage/framework/views'   => storage_path('framework/views'),
            'storage/framework/cache'   => storage_path('framework/cache'),
            'storage/framework/sessions'=> storage_path('framework/sessions'),
            'bootstrap/cache'          => base_path('bootstrap/cache'),
            '.env'                      => base_path('.env'),
            // Translations are stored as lang/{code}.json, so the admin translation
            // screen cannot save a thing if this is read-only. Caught here rather than
            // as a failed save weeks later, when the buyer is mid-translation.
            'lang'                      => lang_path(),
        ];

        $results = [];

        foreach ($dirs as $label => $path) {
            $writable = $this->isWritable($path);

            // Try to fix it ourselves before reporting a failure the buyer may
            // have no way to resolve: extractors routinely drop the group-write
            // bit, and shared-hosting file managers often cannot chmod
            // recursively. Best-effort — hosts that forbid chmod just fall
            // through to the same failure they would have reported anyway.
            if (! $writable) {
                $this->attemptRepair($path);
                clearstatcache(true, $path);
                $writable = $this->isWritable($path);
            }

            $results[] = [
                'label'    => $label,
                'required' => 'Writable',
                'current'  => $writable ? 'Writable' : 'Not writable',
                'pass'     => $writable,
                'severity' => 'error',
                'message'  => $writable
                    ? ''
                    : 'Set this to 775 (or 755) in your hosting file manager, then re-run the check.',
            ];
        }

        return $results;
    }

    /**
     * A path counts as writable if it exists and is writable, or — for a file
     * that does not exist yet, such as .env — if its directory is.
     */
    private function isWritable(string $path): bool
    {
        if (file_exists($path)) {
            return is_writable($path);
        }

        $parent = dirname($path);

        return is_dir($parent) && is_writable($parent);
    }

    /**
     * Best-effort repair: create the directory if the extractor dropped it
     * (empty directories are commonly skipped), then restore the group-write
     * bit. Never touches .env itself — only the directory it will live in.
     */
    private function attemptRepair(string $path): void
    {
        $target = str_ends_with($path, '.env') ? dirname($path) : $path;

        if (! file_exists($target)) {
            @mkdir($target, 0775, true);
        }

        if (is_dir($target)) {
            @chmod($target, 0775);
        }
    }

    private function checkServerConfig(): array
    {
        $checks = [];

        // Memory limit
        $memoryLimit = $this->parseIniBytes(ini_get('memory_limit'));
        $checks[] = [
            'label'    => 'Memory Limit',
            'required' => '≥ 256M',
            'current'  => ini_get('memory_limit'),
            'pass'     => $memoryLimit >= 256 * 1024 * 1024,
            'severity' => 'warning',
        ];

        // Max execution time
        $maxTime = (int) ini_get('max_execution_time');
        $checks[] = [
            'label'    => 'Max Execution Time',
            'required' => '≥ 60s',
            'current'  => $maxTime . 's',
            'pass'     => $maxTime === 0 || $maxTime >= 60,
            'severity' => 'warning',
        ];

        // Upload max filesize
        $uploadMax = $this->parseIniBytes(ini_get('upload_max_filesize'));
        $checks[] = [
            'label'    => 'Upload Max Filesize',
            'required' => '≥ 20M',
            'current'  => ini_get('upload_max_filesize'),
            'pass'     => $uploadMax >= 20 * 1024 * 1024,
            'severity' => 'warning',
        ];

        // Post max size
        $postMax = $this->parseIniBytes(ini_get('post_max_size'));
        $checks[] = [
            'label'    => 'POST Max Size',
            'required' => '≥ 20M',
            'current'  => ini_get('post_max_size'),
            'pass'     => $postMax >= 20 * 1024 * 1024,
            'severity' => 'warning',
        ];

        return $checks;
    }

    /**
     * Convert PHP ini value like '256M' or '1G' to bytes.
     */
    private function parseIniBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower(substr($value, -1));
        $num = (int) $value;

        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $value,
        };
    }
}
