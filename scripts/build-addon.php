<?php

/**
 * Packages one addon into an installable zip.
 *
 * Addons are sold separately from core, so they are packaged separately too: the buyer
 * uploads the zip at Admin → Appearance → Addons and AddonController::installAddon()
 * extracts it. That uploader is strict, and every check below exists because it rejects
 * the package otherwise — one root directory named for the slug, a manifest inside it,
 * and under the 20 MB upload cap.
 *
 * Usage: php scripts/build-addon.php <slug> [--out=DIR] [--keep-build]
 *
 * The staging tree is assembled in the system temp directory and zipped with the same
 * scripts/zip.php the core release uses, so an addon package carries the identical
 * extractor-compatibility guarantees (path length, case collisions, POSIX modes).
 */

declare(strict_types=1);

// ─── Configuration ───────────────────────────────────────────────────────────

/**
 * Directory names pruned wherever they appear inside the copied tree. Mirrors
 * build-release.php's DENY_DIR_NAMES so an addon package never carries developer state.
 */
const DENY_DIR_NAMES = ['node_modules', '.git', '.github', 'tests', '__pycache__'];

/** File patterns pruned anywhere in the tree — OS clutter and local editor state. */
const DENY_FILE_PATTERNS = ['.DS_Store', 'Thumbs.db', 'desktop.ini', '*.log', '.env', '.env.*'];

/**
 * Addons that must carry a copy of the core product manual, by slug.
 *
 * The manual (resources/docs/core) documents MakeAI itself — provider keys, credit modes,
 * payment gateways. Core no longer ships the markdown, because nothing in core reads it:
 * the buyer's offline copy is documentation/docs.html, rendered at core build time. The
 * ONLY runtime consumer is the assistant, which grounds its admin answers on it via
 * ProductDocsService. So the corpus travels in this addon's package instead.
 *
 * Copied into docs/core/, mirroring resources/docs/core, so the product manual stays
 * visibly separate from the addon's own pages sitting flat in docs/. ProductDocsService
 * scans an addon's docs/ and one directory below it, which is exactly this shape.
 *
 * The copy happens in staging only. The repo's addons/ai-assistant/docs/ keeps just the
 * two pages that are genuinely about the assistant — core docs stay in resources/docs/core,
 * which is where the writing guidelines say they belong.
 */
const BUNDLE_CORE_DOCS = ['ai-assistant'];

/** The uploader's own limit (AddonController: `max:20480` kilobytes). */
const MAX_ZIP_BYTES = 20 * 1024 * 1024;

// ─── Output helpers ──────────────────────────────────────────────────────────

function step(string $message): void
{
    echo "\n\033[1m" . $message . "\033[0m\n";
}

function info(string $message): void
{
    echo '  ' . $message . "\n";
}

function fail(string $message): never
{
    fwrite(STDERR, "\n\033[31mError:\033[0m " . $message . "\n");
    exit(1);
}

function mb(int $bytes): string
{
    return $bytes >= 1048576
        ? sprintf('%.1f MB', $bytes / 1048576)
        : sprintf('%.0f KB', $bytes / 1024);
}

// ─── Argument parsing ────────────────────────────────────────────────────────

$options = array_values(array_filter(array_slice($argv, 1), fn ($a) => str_starts_with($a, '--')));
$args    = array_values(array_filter(array_slice($argv, 1), fn ($a) => ! str_starts_with($a, '--')));

$slug = $args[0] ?? null;

if ($slug === null) {
    fwrite(STDERR, "Usage: php scripts/build-addon.php <slug> [--out=DIR] [--keep-build]\n");
    exit(1);
}

// The slug becomes a directory name and the zip's single root entry, so it is validated
// rather than trusted: a slug with a slash or a dot segment would escape the staging root.
if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
    fail("invalid slug \"{$slug}\" — expected lowercase letters, digits and hyphens.");
}

$keepBuild = in_array('--keep-build', $options, true);

$outDir = null;
foreach ($options as $option) {
    if (str_starts_with($option, '--out=')) {
        $outDir = substr($option, 6);
    }
}

$srcDir    = dirname(__DIR__);
$addonDir  = $srcDir . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . $slug;
$outDir  ??= $srcDir . DIRECTORY_SEPARATOR . 'dist';

if (! is_dir($addonDir)) {
    fail("no such addon: addons/{$slug}");
}

// ─── Filesystem helpers ──────────────────────────────────────────────────────

function ensureDir(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
        fail("could not create directory: {$path}");
    }
}

function deleteTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function isDeniedFile(string $basename): bool
{
    foreach (DENY_FILE_PATTERNS as $pattern) {
        if (fnmatch($pattern, $basename)) {
            return true;
        }
    }

    return false;
}

/**
 * Recursive copy that prunes developer state as it goes.
 *
 * Symlinks are skipped outright rather than followed: an addon should not contain any,
 * and following one would copy whatever it points at into a buyer's package.
 *
 * @return int files copied
 */
function copyTree(string $from, string $to): int
{
    ensureDir($to);
    $copied = 0;

    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $source = $from . DIRECTORY_SEPARATOR . $entry;

        if (is_link($source)) {
            continue;
        }

        if (is_dir($source)) {
            if (in_array($entry, DENY_DIR_NAMES, true)) {
                continue;
            }

            $copied += copyTree($source, $to . DIRECTORY_SEPARATOR . $entry);
            continue;
        }

        if (isDeniedFile($entry)) {
            continue;
        }

        if (! copy($source, $to . DIRECTORY_SEPARATOR . $entry)) {
            fail("could not copy {$source}");
        }

        $copied++;
    }

    return $copied;
}

function dirSize(string $path): int
{
    $bytes = 0;

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    ) as $file) {
        if ($file->isFile()) {
            $bytes += $file->getSize();
        }
    }

    return $bytes;
}

// ─── 1. Read and validate the manifest ───────────────────────────────────────

step("Building addon: {$slug}");

$manifestPath = $addonDir . DIRECTORY_SEPARATOR . 'addon.json';

if (! is_file($manifestPath)) {
    fail("addons/{$slug}/addon.json is missing — the uploader rejects a package without a manifest.");
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (! is_array($manifest)) {
    fail("addons/{$slug}/addon.json is not valid JSON: " . json_last_error_msg());
}

// A mismatch here installs to one directory but registers under another name, which
// breaks route and setting lookups in ways that are miserable to diagnose later.
if (($manifest['slug'] ?? null) !== $slug) {
    fail(sprintf(
        'addon.json declares slug "%s" but the directory is "%s" — they must match.',
        (string) ($manifest['slug'] ?? ''),
        $slug
    ));
}

$version = (string) ($manifest['version'] ?? '');

if ($version === '') {
    fail('addon.json has no "version" — the update badge compares against it.');
}

info("name     {$manifest['name']}");
info("version  {$version}");

// ─── 2. Stage the addon tree ─────────────────────────────────────────────────

step('Staging files');

$stageRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'makeai-addon-build';

// The zip's root must be the slug directory and nothing else, so the staging root holds
// exactly one child. Cleared first: a leftover tree from a previous run would ship.
deleteTree($stageRoot);
ensureDir($stageRoot);

$stageAddon = $stageRoot . DIRECTORY_SEPARATOR . $slug;

$fileCount = copyTree($addonDir, $stageAddon);
info(sprintf('%d files from addons/%s/', $fileCount, $slug));

// ─── 3. Bundle the core manual where the addon needs it ──────────────────────

if (in_array($slug, BUNDLE_CORE_DOCS, true)) {
    step('Bundling the product documentation');

    $corpus = $srcDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'core';

    if (! is_dir($corpus)) {
        fail("resources/docs/core is missing — {$slug} grounds its admin answers on it.");
    }

    $pages = glob($corpus . DIRECTORY_SEPARATOR . '*.md') ?: [];

    if ($pages === []) {
        fail('resources/docs/core contains no markdown pages.');
    }

    $stageDocs = $stageAddon . DIRECTORY_SEPARATOR . 'docs';
    $stageCore = $stageDocs . DIRECTORY_SEPARATOR . 'core';

    // The addon's own pages were copied in step 2. A docs/core/ already in the repo would
    // mean the addon hand-maintains what this step generates — stop rather than merge two
    // sources of truth into one directory.
    if (is_dir($stageCore)) {
        fail("addons/{$slug}/docs/core/ already exists — it is generated here, so it must not be committed.");
    }

    ensureDir($stageCore);

    foreach ($pages as $page) {
        if (! copy($page, $stageCore . DIRECTORY_SEPARATOR . basename($page))) {
            fail("could not copy {$page}");
        }
    }

    info(sprintf(
        '%d core pages → docs/core/, %d addon pages stay in docs/',
        count($pages),
        count(glob($stageDocs . DIRECTORY_SEPARATOR . '*.md') ?: [])
    ));
}

// ─── 4. Verify the staged package ────────────────────────────────────────────

step('Verifying');

$checks = 0;

if (! is_file($stageAddon . DIRECTORY_SEPARATOR . 'addon.json')) {
    fail('addon.json did not reach the staged package.');
}
$checks++;

// The uploader derives the slug from the zip's root entries and refuses anything other
// than exactly one, so a stray file beside the slug directory fails the install.
$rootEntries = array_values(array_diff(scandir($stageRoot) ?: [], ['.', '..']));

if ($rootEntries !== [$slug]) {
    fail('staging root must contain only ' . $slug . '/, found: ' . implode(', ', $rootEntries));
}
$checks++;

foreach (DENY_DIR_NAMES as $denied) {
    if (is_dir($stageAddon . DIRECTORY_SEPARATOR . $denied)) {
        fail("{$denied}/ survived the copy — it must not ship.");
    }
}
$checks++;

$staged = dirSize($stageAddon);

if ($staged > MAX_ZIP_BYTES) {
    fail(sprintf(
        'staged package is %s uncompressed, over the %s upload limit.',
        mb($staged),
        mb(MAX_ZIP_BYTES)
    ));
}
$checks++;

info($checks . ' checks passed, ' . mb($staged) . ' staged');

// ─── 5. Zip ──────────────────────────────────────────────────────────────────

step('Creating the zip');

ensureDir($outDir);
$zipPath = rtrim($outDir, '/\\') . DIRECTORY_SEPARATOR . "{$slug}-{$version}.zip";

if (is_file($zipPath) && ! unlink($zipPath)) {
    fail("could not overwrite {$zipPath}");
}

$command = sprintf(
    'php %s %s %s',
    escapeshellarg($srcDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'zip.php'),
    escapeshellarg($stageRoot),
    escapeshellarg($zipPath)
);

passthru($command, $exitCode);

if ($exitCode !== 0 || ! is_file($zipPath)) {
    fail('zip.php failed — staging left at: ' . $stageRoot);
}

// Compressed size is what the buyer actually uploads, so it is the one that must clear
// the cap. The uncompressed check above is the earlier, friendlier warning.
$zipped = filesize($zipPath);

if ($zipped > MAX_ZIP_BYTES) {
    unlink($zipPath);
    fail(sprintf('the zip is %s, over the %s upload limit.', mb($zipped), mb(MAX_ZIP_BYTES)));
}

if (! $keepBuild) {
    deleteTree($stageRoot);
} else {
    info('staging kept at ' . $stageRoot);
}

// ─── Done ────────────────────────────────────────────────────────────────────

$relative = str_starts_with($zipPath, $srcDir . DIRECTORY_SEPARATOR)
    ? substr($zipPath, strlen($srcDir) + 1)
    : $zipPath;

echo "\n\033[32m SUCCESS \033[0m " . str_replace(DIRECTORY_SEPARATOR, '/', $relative) . "\n";
echo ' ' . mb($zipped) . ", upload at Admin → Appearance → Addons\n\n";
