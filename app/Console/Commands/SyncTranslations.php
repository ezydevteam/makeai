<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\TranslationController;
use App\Models\Language;
use App\Services\TranslationFileStore;
use App\Services\TranslationKeyScanner;
use Illuminate\Console\Command;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync
        {--dry-run : Report what would change without writing}
        {--prune : Drop catalogue entries whose source string no longer exists}';

    protected $description = 'Refresh the translation key cache and report catalogue coverage per language.';

    public function handle(): int
    {
        // Catalogues are files now, so there are no rows to create. What this command does
        // is drop the cached key list — so newly added strings show up immediately instead
        // of when the cache expires — and report where each language stands.
        TranslationController::forgetKeyCache();

        $keys = $this->collectKeys();

        if ($keys === []) {
            $this->warn('No translation keys found — check the scanned paths.');

            return self::SUCCESS;
        }

        $this->info(count($keys).' translatable strings found in the source.');

        $languages = Language::query()->where('is_active', true)->get(['id', 'code', 'is_default']);

        if ($languages->isEmpty()) {
            $this->warn('No active languages found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prune = (bool) $this->option('prune');
        $rows = [];

        foreach ($languages as $language) {
            $catalogue = TranslationFileStore::get($language->code);
            $orphans = array_diff(array_keys($catalogue), $keys);

            if ($prune && $orphans !== [] && ! $dryRun) {
                $catalogue = array_diff_key($catalogue, array_flip($orphans));

                if (! TranslationFileStore::write($language->code, $catalogue)) {
                    $this->error("Could not write lang/{$language->code}.json — is the lang directory writable?");

                    return self::FAILURE;
                }
            }

            $translated = count($catalogue);

            $rows[] = [
                $language->code.($language->is_default ? ' (default)' : ''),
                $translated,
                count($keys) - $translated,
                sprintf('%d%%', count($keys) > 0 ? round($translated / count($keys) * 100) : 0),
                count($orphans),
            ];
        }

        $this->table(['Language', 'Translated', 'Missing', 'Coverage', 'Orphaned'], $rows);

        if ($dryRun) {
            $this->comment('Dry run — nothing was written.');
        } elseif (! $prune) {
            $this->comment('Pass --prune to drop orphaned entries whose source string is gone.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function collectKeys(): array
    {
        return TranslationKeyScanner::scan();
    }
}
