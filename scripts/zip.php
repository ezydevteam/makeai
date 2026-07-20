<?php

/**
 * Packages a directory into a zip that extracts cleanly on Windows, macOS and
 * Linux — with the POSIX permissions shared hosts need preserved.
 *
 * Every rule enforced below exists because some extractor, somewhere, silently
 * does the wrong thing: Windows Explorer refuses long paths, macOS and Windows
 * collapse names that differ only by case, and symlinks either escape the tree
 * or get materialised as fat copies of whatever they point at.
 *
 * Usage: php zip.php <source_dir> <output_zip>
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php zip.php <source_dir> <output_zip>\n");
    exit(1);
}

$sourceDir = realpath($argv[1]);
$outputZip = $argv[2];

if (! $sourceDir || ! is_dir($sourceDir)) {
    fwrite(STDERR, "Error: source directory does not exist: {$argv[1]}\n");
    exit(1);
}

// ─── Extractor compatibility limits ──────────────────────────────────────────

/**
 * Windows Explorer's extractor fails on paths beyond MAX_PATH (260) counted
 * from the *extraction root*, which we do not control. 180 leaves the buyer
 * roughly 80 characters of their own directory nesting before it breaks.
 */
const MAX_ENTRY_PATH = 180;

/** Reserved DOS device names — a file called `aux.php` cannot exist on Windows. */
const WINDOWS_RESERVED = [
    'CON', 'PRN', 'AUX', 'NUL',
    'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
    'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
];

/** OS-generated clutter that must never reach a buyer. */
const JUNK_NAMES = ['.DS_Store', 'Thumbs.db', 'desktop.ini', '__MACOSX', '.AppleDouble'];

// Unix modes, shifted into the zip's external-attributes field.
const MODE_DIR         = 040755 << 16;
const MODE_DIR_WRITABLE = 040775 << 16;
const MODE_FILE        = 0100644 << 16;
const MODE_FILE_EXEC   = 0100755 << 16;

/**
 * Paths needing group-write so the web server can create cache/session/log
 * files. Shared hosts commonly run PHP as a different user than the FTP account.
 */
function needsWritableMode(string $entry): bool
{
    // Matched at any depth so the mode survives however the package is nested
    // (`storage/`, `core/storage/`, `makeai/core/storage/`, ...).
    return (bool) preg_match('#(^|/)(storage|bootstrap/cache)(/|$)#', $entry);
}

function isJunk(string $basename): bool
{
    return in_array($basename, JUNK_NAMES, true);
}

/** File types that are already compressed, so deflating them again is wasted work. */
function matchesCompressed(string $entry): bool
{
    return (bool) preg_match('/\.(zip|gz|tgz|bz2|xz|7z|rar|png|jpg|jpeg|webp|woff|woff2|mp4|webm)$/i', $entry);
}

/**
 * Validates one zip entry name against every extractor quirk we know about.
 * Returns a human-readable problem, or null when the entry is safe.
 */
function validateEntry(string $entry): ?string
{
    if (strlen($entry) > MAX_ENTRY_PATH) {
        return sprintf('path is %d chars (limit %d) — Windows extraction will fail', strlen($entry), MAX_ENTRY_PATH);
    }

    if (! mb_check_encoding($entry, 'UTF-8')) {
        return 'name is not valid UTF-8';
    }

    foreach (explode('/', $entry) as $segment) {
        if ($segment === '') {
            return 'contains an empty path segment';
        }

        // Windows silently strips these, producing a name that no longer matches
        // what the application requires at runtime.
        if (rtrim($segment, ". \t") !== $segment) {
            return "segment \"{$segment}\" ends with a dot or space (illegal on Windows)";
        }

        if (preg_match('/[:*?"<>|\\\\]/', $segment, $m)) {
            return "segment \"{$segment}\" contains illegal character \"{$m[0]}\"";
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $segment)) {
            return "segment \"{$segment}\" contains a control character";
        }

        $stem = strtoupper(pathinfo($segment, PATHINFO_FILENAME));
        if (in_array($stem, WINDOWS_RESERVED, true)) {
            return "segment \"{$segment}\" is a reserved Windows device name";
        }
    }

    return null;
}

// ─── Walk the tree ───────────────────────────────────────────────────────────

// SKIP_DOTS only; symlinks are *not* followed (we reject them outright below),
// so the iterator can never wander outside $sourceDir.
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$entries  = [];   // entry name => ['path' => absolute, 'dir' => bool]
$problems = [];
$skipped  = ['junk' => 0, 'symlink' => 0];

/** Detects names colliding once case is folded, as on NTFS and APFS. */
$caseMap = [];

foreach ($iterator as $file) {
    $basename = $file->getBasename();

    if (isJunk($basename)) {
        $skipped['junk']++;
        continue;
    }

    // Never resolve with realpath(): a symlink resolves outside $sourceDir and
    // the relative-path arithmetic below would produce a corrupt entry name.
    $absolute = $file->getPathname();
    $relative = ltrim(substr($absolute, strlen($sourceDir)), '/\\');
    $entry    = str_replace('\\', '/', $relative);

    if ($entry === '') {
        continue;
    }

    // A symlink would otherwise be stored as a copy of its target — which is how
    // public/storage drags the whole dev media library into the package.
    if ($file->isLink()) {
        fwrite(STDERR, "  ! skipped symlink: {$entry}\n");
        $skipped['symlink']++;
        continue;
    }

    if ($problem = validateEntry($entry)) {
        $problems[] = "{$entry}: {$problem}";
        continue;
    }

    $lower = strtolower($entry);
    if (isset($caseMap[$lower]) && $caseMap[$lower] !== $entry) {
        $problems[] = "{$entry}: collides with \"{$caseMap[$lower]}\" when case is ignored — "
            . 'one would overwrite the other on Windows/macOS';
        continue;
    }
    $caseMap[$lower] = $entry;

    $entries[$entry] = ['path' => $absolute, 'dir' => $file->isDir()];
}

if ($problems) {
    fwrite(STDERR, "\nFATAL: " . count($problems) . " entr" . (count($problems) === 1 ? 'y is' : 'ies are')
        . " unsafe to ship:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, "  - {$problem}\n");
    }
    exit(1);
}

if (! $entries) {
    fwrite(STDERR, "Error: nothing to archive in {$sourceDir}\n");
    exit(1);
}

// ─── Write the archive ───────────────────────────────────────────────────────

// Sorted so repeated builds of identical input produce identical archives,
// which makes "did anything actually change?" answerable by checksum.
ksort($entries, SORT_STRING);

if (is_file($outputZip)) {
    unlink($outputZip);
}

$zip = new ZipArchive();
if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Error: could not create archive: {$outputZip}\n");
    exit(1);
}

echo "Archiving " . count($entries) . " entries from {$sourceDir}\n";

$count = 0;
foreach ($entries as $entry => $meta) {
    if ($meta['dir']) {
        $zip->addEmptyDir($entry);

        // addEmptyDir() stores the entry WITH a trailing slash. Setting the
        // attributes on the unslashed name silently matches nothing, leaving the
        // directory at the 0777 default — which suPHP/suexec hosts reject with a
        // 500 error. The slash is load-bearing.
        if (! $zip->setExternalAttributesName(
            $entry . '/',
            ZipArchive::OPSYS_UNIX,
            needsWritableMode($entry) ? MODE_DIR_WRITABLE : MODE_DIR
        )) {
            fwrite(STDERR, "Error: could not set permissions on directory entry: {$entry}\n");
            exit(1);
        }
    } else {
        if (! $zip->addFile($meta['path'], $entry)) {
            fwrite(STDERR, "Error: could not add {$entry}\n");
            exit(1);
        }

        // Deflating something already compressed burns minutes and gives back nothing —
        // occasionally it grows the entry. The outer release archive carries a ~57 MB
        // script.zip, so storing it rather than re-compressing is the difference between a
        // near-instant finalise and a multi-minute one.
        if (matchesCompressed($entry)) {
            $zip->setCompressionName($entry, ZipArchive::CM_STORE);
        }

        // artisan is invoked directly on VPS deployments and via cron.
        $mode = ($entry === 'core/artisan' || str_ends_with($entry, '/artisan') || $entry === 'artisan')
            ? MODE_FILE_EXEC
            : MODE_FILE;

        $zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, $mode);
    }

    if (++$count % 1000 === 0) {
        echo "  {$count} entries...\n";
    }
}

echo "Finalising (compressing " . count($entries) . " entries, this takes a moment)...\n";

if (! $zip->close()) {
    fwrite(STDERR, "Error: failed to finalise archive — {$zip->getStatusString()}\n");
    exit(1);
}

$size = filesize($outputZip);
printf(
    "SUCCESS: %s (%.1f MB, %d entries%s)\n",
    $outputZip,
    $size / 1048576,
    count($entries),
    $skipped['symlink'] || $skipped['junk']
        ? sprintf(', skipped %d symlink(s) and %d junk file(s)', $skipped['symlink'], $skipped['junk'])
        : ''
);
