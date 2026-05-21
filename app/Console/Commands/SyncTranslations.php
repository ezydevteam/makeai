<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync {--dry-run : Show missing keys without writing them}';

    protected $description = 'Scan PHP and Vue files for translation keys and sync missing rows.';

    /**
     * @var array<int, string>
     */
    private array $paths = [
        'app',
        'resources/js',
        'resources/views',
    ];

    public function handle(): int
    {
        $languages = Language::query()->where('is_active', true)->get(['id', 'code']);

        if ($languages->isEmpty()) {
            $this->warn('No active languages found.');

            return self::SUCCESS;
        }

        $keys = $this->collectKeys();

        if ($keys === []) {
            $this->info('No translation keys found.');

            return self::SUCCESS;
        }

        $created = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($languages as $language) {
            $existing = Translation::query()
                ->where('language_id', $language->id)
                ->pluck('key')
                ->all();

            $missing = array_values(array_diff($keys, $existing));

            if ($dryRun) {
                $this->line($language->code.': '.count($missing).' missing keys');

                continue;
            }

            foreach ($missing as $key) {
                Translation::query()->create([
                    'language_id' => $language->id,
                    'key' => $key,
                    'value' => $key,
                ]);
                $created++;
            }

            TranslationService::clearCache($language->code);
        }

        $this->info($dryRun ? 'Dry run complete.' : "Synced {$created} translation rows.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function collectKeys(): array
    {
        $keys = [];

        foreach ($this->paths as $path) {
            $fullPath = base_path($path);

            if (! File::exists($fullPath)) {
                continue;
            }

            foreach (File::allFiles($fullPath) as $file) {
                $extension = $file->getExtension();

                if (! in_array($extension, ['php', 'vue', 'ts'], true)) {
                    continue;
                }

                $contents = File::get($file->getPathname());

                foreach ($this->extractKeys($contents) as $key) {
                    $keys[$key] = $key;
                }
            }
        }

        ksort($keys);

        return array_values($keys);
    }

    /**
     * @return array<int, string>
     */
    private function extractKeys(string $contents): array
    {
        $keys = [];
        $patterns = [
            '/(?<![A-Za-z0-9_])translate\(\s*[\'"]([^\'"]+)[\'"]/',
            '/(?<![A-Za-z0-9_])t\(\s*[\'"]([^\'"]+)[\'"]/',
            '/\$t\(\s*[\'"]([^\'"]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $contents, $matches);

            foreach ($matches[1] ?? [] as $key) {
                $keys[] = stripcslashes($key);
            }
        }

        return $keys;
    }
}
