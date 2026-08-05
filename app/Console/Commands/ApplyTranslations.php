<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ApplyTranslations extends Command
{
    protected $signature = 'translations:apply {code} {path}';

    protected $description = 'Apply a JSON map of {translation_id: translated_value} to a language.';

    public function handle(): int
    {
        $language = Language::where('code', $this->argument('code'))->firstOrFail();

        $path = $this->argument('path');
        $fullPath = str_starts_with($path, '/') ? $path : storage_path('app/'.$path);

        if (! File::exists($fullPath)) {
            $this->error("File not found: {$fullPath}");

            return self::FAILURE;
        }

        $map = json_decode(File::get($fullPath), true);

        if (! is_array($map)) {
            $this->error('Invalid JSON payload.');

            return self::FAILURE;
        }

        $updated = 0;
        $missing = 0;

        DB::transaction(function () use ($map, $language, &$updated, &$missing) {
            foreach ($map as $id => $value) {
                $rows = Translation::where('id', (int) $id)
                    ->where('language_id', $language->id)
                    ->update(['value' => $value]);

                if ($rows > 0) {
                    $updated++;
                } else {
                    $missing++;
                }
            }
        });

        TranslationService::clearCache($language->code);

        $this->info("Updated {$updated} translations, {$missing} ids not found.");

        return self::SUCCESS;
    }
}
