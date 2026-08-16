<?php

/**
 * Merges translated chunk files into the lang/{code}.json catalogues.
 *
 * Usage:  php scripts/i18n/apply.php [directory]
 *
 * Reads every *.json in the directory (default: scripts/i18n/incoming) whose contents
 * look like {"bn": {...}, "ar": {...}, "es": {...}, "fr": {...}, "hi": {...}}, merges each
 * locale into its catalogue, and renames the file to *.done so re-running is safe.
 *
 * Refuses a chunk whose locales do not all cover the same number of keys — that is the
 * usual symptom of a translation pass that quietly dropped entries.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\TranslationFileStore;

const LOCALES = ['bn', 'ar', 'es', 'fr', 'hi'];

$dir = rtrim($argv[1] ?? __DIR__ . '/incoming', '/\\');

if (! is_dir($dir)) {
    fwrite(STDERR, "Not a directory: {$dir}\n");
    exit(1);
}

$files = glob($dir . '/*.json') ?: [];
sort($files);

if ($files === []) {
    echo "No .json files to apply in {$dir}\n";
    exit(0);
}

$problems = [];
$applied = 0;

foreach ($files as $path) {
    $name = basename($path);
    $payload = json_decode((string) file_get_contents($path), true);

    if (! is_array($payload)) {
        $problems[] = "{$name}: not valid JSON (" . json_last_error_msg() . ')';

        continue;
    }

    $counts = [];
    $missingLocale = false;

    foreach (LOCALES as $locale) {
        if (! isset($payload[$locale]) || ! is_array($payload[$locale])) {
            $problems[] = "{$name}: missing or malformed locale '{$locale}'";
            $missingLocale = true;

            break;
        }

        $counts[$locale] = count($payload[$locale]);
    }

    if ($missingLocale) {
        continue;
    }

    if (count(array_unique($counts)) !== 1) {
        $problems[] = "{$name}: locales cover different key counts — " . json_encode($counts);

        continue;
    }

    $failed = false;

    foreach (LOCALES as $locale) {
        if (! TranslationFileStore::merge($locale, $payload[$locale])) {
            $problems[] = "{$name}: could not write lang/{$locale}.json — is lang/ writable?";
            $failed = true;

            break;
        }
    }

    if ($failed) {
        continue;
    }

    rename($path, substr($path, 0, -5) . '.done');
    $applied++;

    printf("%-22s applied %d keys × %d locales%s", $name, reset($counts), count(LOCALES), PHP_EOL);
}

echo PHP_EOL . 'Catalogue totals:' . PHP_EOL;

foreach (LOCALES as $locale) {
    printf("  %-3s %d%s", $locale, count(TranslationFileStore::get($locale)), PHP_EOL);
}

if ($problems !== []) {
    echo PHP_EOL . "PROBLEMS (these files were NOT applied):\n  " . implode("\n  ", $problems) . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "{$applied} chunk(s) applied cleanly." . PHP_EOL;
