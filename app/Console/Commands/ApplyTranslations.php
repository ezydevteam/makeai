<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Services\TranslationFileStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ApplyTranslations extends Command
{
    protected $signature = 'translations:apply {code} {path}';

    protected $description = 'Merge a JSON map of {"source string": "translated value"} into lang/{code}.json.';

    public function handle(): int
    {
        $language = Language::where('code', $this->argument('code'))->firstOrFail();

        $path = $this->argument('path');
        $fullPath = is_file($path) ? $path : storage_path('app/'.$path);

        if (! File::exists($fullPath)) {
            $this->error("File not found: {$fullPath}");

            return self::FAILURE;
        }

        $map = json_decode(File::get($fullPath), true);

        if (! is_array($map)) {
            $this->error('Invalid JSON payload.');

            return self::FAILURE;
        }

        // Keyed by the source string, not by a database id. The old format was
        // {translation_id: value}, which broke the moment anything renumbered the table —
        // and migrate:fresh renumbered all of it, silently mapping saved translations onto
        // whichever unrelated strings happened to land on those ids.
        $pairs = [];

        foreach ($map as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            $pairs[$key] = $value;
        }

        if ($pairs === []) {
            $this->error('No usable entries — expected {"source string": "translated value"}.');

            return self::FAILURE;
        }

        if (! TranslationFileStore::merge($language->code, $pairs)) {
            $this->error("Could not write lang/{$language->code}.json — is the lang directory writable?");

            return self::FAILURE;
        }

        $total = count(TranslationFileStore::get($language->code));

        $this->info(sprintf(
            'Applied %d entries to lang/%s.json (%d total).',
            count($pairs),
            $language->code,
            $total
        ));

        return self::SUCCESS;
    }
}
