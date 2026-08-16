<?php

/**
 * Checks the lang/{code}.json catalogues against the rules that matter at render time.
 *
 * Usage:  php scripts/i18n/verify.php
 *
 * Placeholder handling is the subtle one: translate() does a plain str_replace(":name", …)
 * per supplied key, so ":countd ago" is the placeholder :count followed by a literal "d".
 * A naive /:[a-z]+/ match reads that as ":countd" and reports a false loss, so a token only
 * counts as missing when NO prefix of it survives in the translation.
 *
 * Exit status: 0 clean, 1 problems found.
 */

const LOCALES = ['bn', 'ar', 'es', 'fr', 'hi'];

// Locales written in a non-Latin script: a value that is still pure ASCII there is almost
// always an untranslated string that slipped through (brand terms being the fair exception).
const NON_LATIN = ['bn', 'ar', 'hi'];

$langPath = __DIR__ . '/../../lang';

$tokensIn = static function (string $text): array {
    preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $text, $m);

    return array_values(array_unique($m[0]));
};

$survives = static function (string $token, string $value): bool {
    for ($len = strlen($token); $len >= 2; $len--) {
        if (str_contains($value, substr($token, 0, $len))) {
            return true;
        }
    }

    return false;
};

$totalProblems = 0;

foreach (LOCALES as $locale) {
    $path = "{$langPath}/{$locale}.json";

    if (! is_file($path)) {
        echo "{$locale}: MISSING FILE\n";
        $totalProblems++;

        continue;
    }

    $catalogue = json_decode((string) file_get_contents($path), true);

    if (! is_array($catalogue)) {
        echo "{$locale}: INVALID JSON — " . json_last_error_msg() . "\n";
        $totalProblems++;

        continue;
    }

    $lost = [];
    $identity = [];
    $empty = [];
    $asciiOnly = [];

    foreach ($catalogue as $key => $value) {
        $key = (string) $key;

        if (! is_string($value)) {
            $lost[] = "{$key} => value is not a string";

            continue;
        }

        if ($value === '') {
            $empty[] = $key;
        }

        if ($value === $key) {
            $identity[] = $key;
        }

        foreach ($tokensIn($key) as $token) {
            if (! $survives($token, $value)) {
                $lost[] = "{$key}  ⇒  {$value}   (lost {$token})";
            }
        }

        if (in_array($locale, NON_LATIN, true) && preg_match('/^[\x20-\x7E]+$/', $value)) {
            $asciiOnly[] = "{$key}  ⇒  {$value}";
        }
    }

    printf(
        "%-3s entries=%-5d lost-placeholders=%-3d identity=%-3d empty=%-3d ascii-only=%d%s",
        $locale,
        count($catalogue),
        count($lost),
        count($identity),
        count($empty),
        count($asciiOnly),
        PHP_EOL
    );

    foreach (array_slice($lost, 0, 10) as $p) {
        echo '     ! ' . $p . PHP_EOL;
    }

    foreach (array_slice($asciiOnly, 0, 5) as $p) {
        echo '     ? untranslated? ' . $p . PHP_EOL;
    }

    // ascii-only is a warning, not a failure: brand terms legitimately stay in English.
    $totalProblems += count($lost) + count($identity) + count($empty);
}

echo PHP_EOL . ($totalProblems === 0 ? 'OK — no problems found' : "{$totalProblems} problem(s) found") . PHP_EOL;

exit($totalProblems === 0 ? 0 : 1);
