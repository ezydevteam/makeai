<?php

/**
 * Builds the distributable MakeAI package.
 *
 * Runs identically on Windows, macOS and Linux — the previous rsync/robocopy
 * pair had drifted into producing two different products, so this is the single
 * source of truth. Everything shipped is chosen by ALLOWLIST: a new file in the
 * repo root is excluded until someone deliberately adds it here, rather than
 * leaking until someone remembers to exclude it.
 *
 * Usage:
 *   php scripts/build-release.php <version> [options]
 *
 * Options:
 *   --skip-deps   Reuse the existing vendor/ and public/build (fast iteration)
 *   --no-slim     Keep vendor tests/docs (skips ~40% size reduction)
 *   --keep-build  Leave the staging tree in place for inspection
 *   --allow-placeholder-key
 *                 Build even though the license public key is still the placeholder.
 *                 For test builds only; the result cannot activate on any buyer's install.
 *   --demo        Build the seller's public demo site instead of the buyer package.
 *                 See the DEMO BUILD note below — the two products differ deliberately.
 *
 * ─── DEMO BUILD ──────────────────────────────────────────────────────────────
 *
 * `--demo` produces makeai-demo-vX.Y.Z.zip, which is NOT a buyer package and must never
 * be uploaded to the marketplace. Three deliberate inversions of the rules above:
 *
 *   1. database/seeders and database/factories SHIP. The demo bootstraps by running
 *      `demo:reset` (migrate:refresh + DatabaseSeeder + DemoSeeder) on a six-hourly
 *      schedule, so the seeders are its data source — data.sql is the buyer path and is
 *      irrelevant here.
 *   2. The addons in DEMO_ADDONS are BUNDLED, so the demo showcases the full product.
 *      The buyer package ships an empty addons/ because addons are sold separately.
 *   3. .env.example ships with DEMO_ENABLED=true pre-set.
 *
 * The demo's own DemoReset command marks the license valid so the bundled addons can
 * activate (AddonService::activate() refuses on an unverified license). That makes this
 * package an unlicensed install in the narrow technical sense, which is why it carries
 * demo mode: every write verb is blocked, the login credentials are published on the
 * sign-in page, and the database is wiped every six hours. Do not hand it to anyone as
 * a substitute for a purchase.
 */

// ─── Package layout ──────────────────────────────────────────────────────────

/**
 * Name of the Laravel application directory inside the deployable webroot.
 * The buyer-facing webroot holds only index.php and public assets; all code
 * lives one level down in APP_DIR, which the web server is told to deny.
 */
const APP_DIR = 'core';

/**
 * Public documentation site. Kept in step with ProductDocsService::SITE_URL, which is what
 * the in-app assistant links its citations to.
 */
const DOCS_SITE = 'https://makeai-docs.ezydev.net';

/**
 * Top-level repo paths copied into APP_DIR. Anything absent here never ships.
 *
 * `addons` is deliberately excluded: addons are distributed separately as paid
 * dependencies, not bundled. An empty addons/ directory is still created below
 * so uploads have somewhere to land, and AddonService::syncFromFilesystem()
 * guards on File::isDirectory() so an empty one is a no-op at boot.
 */
const ALLOW_DIRS = [
    'app', 'bootstrap', 'config', 'database', 'resources', 'routes',
];

const ALLOW_FILES = [
    'artisan',
    'composer.json',
    'composer.lock',
    '.env.example',
    // Shipped so buyers can rebuild the frontend after customising it.
    'package.json',
    'package-lock.json',
    'vite.config.ts',
    'tsconfig.json',
];

/**
 * Paths under an allowlisted directory that must still be excluded, either
 * because they are developer state (a real database, a log) or internal-only.
 */
const DENY_SUBPATHS = [
    'database/database.sqlite',   // the developer's working DB — real user data
    'database/migrations_backup', // pre-squash reference copies, internal only
    'bootstrap/cache',            // regenerated at runtime; stale caches break boot
    'resources/installer/.gitkeep',
    // Buyer installs are bootstrapped from database/data/data.sql, not by running
    // seeder classes, so neither the seeders nor the factories they rely on ship.
    // NOTE: this makes data.sql load-bearing — finalize() falls back to the
    // seeders when it is absent, and that fallback no longer exists.
    // Both directories are recreated from STUB_FILES below, holding a single
    // reference class each so `db:seed` and `User::factory()` still work.
    'database/seeders',
    'database/factories',
];

/**
 * Files written into the package after the source copy, keyed by their path
 * relative to APP_DIR. These replace excluded directories with a documented
 * reference implementation rather than leaving the buyer with nothing at all.
 */
const STUB_FILES = [
    'database/seeders/DatabaseSeeder.php' => 'distribution/stubs/database/seeders/DatabaseSeeder.php',
    'database/factories/UserFactory.php'  => 'distribution/stubs/database/factories/UserFactory.php',
];

/**
 * Addons bundled into a --demo build, by directory name under addons/.
 *
 * An explicit allowlist for the same reason ALLOW_DIRS is one: a half-finished addon
 * appearing in addons/ must not reach the demo until someone puts it here. This is the
 * set that is active on the seller's own install and that DemoSeeder writes data for —
 * DemoSeeder imports these five namespaces directly, so dropping one from this list
 * without touching the seeder leaves the demo with empty screens for it.
 *
 * Deliberately absent: ai-image-editor (superseded by ai-image-pro), and the unfinished
 * ai-repurposer / ai-video-creator / ai-voiceover / social-scheduler.
 */
const DEMO_ADDONS = [
    'ai-assistant',
    'ai-chatbot',
    'ai-image-pro',
    'ai-knowledge-base',
    'faker-ai',
];

/** Directory names pruned wherever they appear inside the copied tree. */
const DENY_DIR_NAMES = ['node_modules', '.git', '.github', 'tests', '__pycache__'];

/** Filename patterns pruned wherever they appear. */
const DENY_FILE_PATTERNS = [
    '*.sqlite', '*.sqlite-journal', '.DS_Store', 'Thumbs.db', 'desktop.ini',
    '.gitignore', '.gitattributes', '.phpunit.result.cache',
];

/**
 * mPDF font files pruned from vendor/mpdf/mpdf/ttfonts.
 *
 * mPDF bundles 87 MB of fonts covering every script Unicode has, including dead ones. These
 * are ~17 MB of the buyer's DOWNLOAD (fonts barely compress), which is the single largest
 * saving available anywhere in the package.
 *
 * Safe because mPDF only loads a font when something selects it, and nothing here can be:
 * autoScriptToLang, autoLangToFont and useSubstitutions all default to false and the app
 * never overrides them (see ExportService), so a font is reachable only when a PDF template
 * names it in CSS font-family. No template names any of these.
 *
 * What is deliberately KEPT, and why — do not "optimise" these away:
 *   - dejavu*        mPDF's default family, and DejaVuSans.ttf is read directly by
 *                    ai-image-pro's LocalImageService for GD text rendering.
 *   - free*          FreeSans/FreeSerif/FreeMono: the broad Latin/Greek/Cyrillic/Hebrew
 *                    coverage, and two thirds of the backupSubsFont chain.
 *   - xbriyaz        Arabic/Persian. The product has first-class RTL support (Language::rtl),
 *                    so buyers running RTL sites are expected, not hypothetical.
 *   - sun-exta       Last entry in the backupSubsFont chain and the only bundled font with
 *                    common CJK ideographs. Dropping it breaks Chinese/Japanese PDFs.
 *   - unbatang       Korean.
 *   - abyssinica     Ethiopic is a living script and the file is only 0.6 MB.
 *
 * Sun-ExtB is the exception that needed a code change: it is the default backupSIPFont, so
 * mPDF can select it on its own for a Supplementary-Ideographic-Plane character, and a
 * missing font file is a FATAL exception rather than a blank glyph. ExportService therefore
 * pins backupSIPFont to a font that ships. See the note there.
 */
const PRUNE_FONTS = [
    'Sun-ExtB.ttf',                // CJK Ext B — rare historic ideographs (16.8 MB)
    'Aegyptus.otf',                // Egyptian hieroglyphs
    'Jomolhari.ttf',               // Tibetan
    'Aegean.otf',                  // Linear A/B, Cypriot, Phoenician
    'Akkadian.otf',                // Sumero-Akkadian cuneiform
    'Quivira.otf',                 // historic scripts and symbol blocks
    'damase_v.2.ttf',              // Deseret, Shavian, Osmanya and similar
    'AboriginalSansREGULAR.ttf',   // Canadian Aboriginal syllabics
];

/** Writable directories created empty, with a .gitkeep so extractors keep them. */
const STORAGE_SKELETON = [
    'storage/app/public',
    'storage/app/private',
    'storage/app/temp',
    'storage/app/exports',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];

// ─── Bootstrap ───────────────────────────────────────────────────────────────

$options = array_values(array_filter(array_slice($argv, 1), fn ($a) => str_starts_with($a, '--')));
$args    = array_values(array_filter(array_slice($argv, 1), fn ($a) => ! str_starts_with($a, '--')));

$version = $args[0] ?? null;

if (! $version) {
    fwrite(STDERR, "Usage: php scripts/build-release.php <version> [--skip-deps] [--no-slim] [--keep-build] [--allow-placeholder-key] [--demo]\n");
    exit(1);
}

if (! preg_match('/^\d+\.\d+\.\d+(-[a-z0-9.]+)?$/i', $version)) {
    fwrite(STDERR, "Error: version must look like 1.0.0 or 1.0.0-beta.1 (got \"{$version}\")\n");
    exit(1);
}

$skipDeps  = in_array('--skip-deps', $options, true);
$noSlim    = in_array('--no-slim', $options, true);
$keepBuild = in_array('--keep-build', $options, true);
$allowPlaceholderKey = in_array('--allow-placeholder-key', $options, true);
$demo      = in_array('--demo', $options, true);

/**
 * The seeders and factories are the demo's only data source (see the DEMO BUILD note),
 * so the deny list is relaxed for it. Everything else about the two builds is identical.
 */
$denySubpaths = $demo
    ? array_values(array_diff(DENY_SUBPATHS, ['database/seeders', 'database/factories']))
    : DENY_SUBPATHS;

$srcDir = dirname(__DIR__);

// Staging lives outside the repo so a botched build can never dirty the source
// tree, and so composer runs against a copy — never the developer's vendor/.
$stageRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'makeai-release-build';
$pkgName   = $demo ? "makeai-demo-v{$version}" : "makeai-v{$version}";
$wrapper   = $stageRoot . DIRECTORY_SEPARATOR . $pkgName;  // becomes the zip root
// Staged as a directory, but shipped as script.zip nested inside the outer archive (see
// section 8). The buyer extracts THAT into their document root, so its contents sit at the
// zip root — no wrapper folder to move things out of.
$webroot   = $wrapper . DIRECTORY_SEPARATOR . 'script';
$appRoot   = $webroot . DIRECTORY_SEPARATOR . APP_DIR;

$startedAt = microtime(true);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function step(string $message): void
{
    echo "\n==> {$message}\n";
}

function info(string $message): void
{
    echo "    {$message}\n";
}

function fail(string $message): never
{
    fwrite(STDERR, "\nFATAL: {$message}\n");
    exit(1);
}

function run(string $command, string $cwd, string $what): void
{
    info("$ {$command}");

    // The child inherits this process's stdout/stderr instead of writing into
    // pipes we have to drain. That makes the whole class of pipe deadlocks
    // impossible, and it is the only approach that behaves identically on all
    // three platforms:
    //
    //   - Alternating blocking fgets() over two pipes parks on whichever pipe is
    //     silent and stops draining the other. Once the busy pipe's OS buffer
    //     fills (64KB), the child blocks on write and nothing progresses.
    //     `npm run build` hung for 11 minutes this way, having already finished.
    //   - stream_select() is the usual fix, but on Windows it only supports
    //     sockets — on process pipes it never reports them ready, so it hangs
    //     too. This script has to run on Windows.
    //
    // Trade-off: output is no longer prefixed or capturable. It still streams
    // live, which is what actually matters during a multi-minute build.
    $previous = getcwd();

    if (! @chdir($cwd)) {
        fail("could not enter directory: {$cwd}");
    }

    $exitCode = 0;
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    if ($exitCode !== 0) {
        fail("{$what} failed (exit code {$exitCode}). See output above.");
    }
}


function rmTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        // Delete symlinks as links; never recurse through them.
        if ($item->isLink() || $item->isFile()) {
            @unlink($item->getPathname());
        } else {
            @rmdir($item->getPathname());
        }
    }

    @rmdir($path);
}

function ensureDir(string $path): void
{
    if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
        fail("could not create directory: {$path}");
    }
}

function matchesAny(string $name, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (fnmatch($pattern, $name)) {
            return true;
        }
    }

    return false;
}

/**
 * Recursively copies $from to $to, applying the deny rules. Symlinks are
 * skipped outright — following public/storage is what dragged the developer's
 * media library (and laravel.log) into previous packages.
 *
 * @param  callable(string):bool|null  $skip  receives the path relative to the copy root
 */
function copyTree(string $from, string $to, ?callable $skip = null, string $prefix = ''): int
{
    ensureDir($to);
    $copied = 0;

    foreach (scandir($from) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $source   = $from . DIRECTORY_SEPARATOR . $item;
        $target   = $to . DIRECTORY_SEPARATOR . $item;
        $relative = $prefix === '' ? $item : "{$prefix}/{$item}";

        if (is_link($source)) {
            info("  ~ skipped symlink: {$relative}");
            continue;
        }

        if ($skip && $skip($relative)) {
            continue;
        }

        if (is_dir($source)) {
            if (in_array($item, DENY_DIR_NAMES, true)) {
                continue;
            }
            $copied += copyTree($source, $target, $skip, $relative);
        } else {
            if (matchesAny($item, DENY_FILE_PATTERNS)) {
                continue;
            }
            if (! @copy($source, $target)) {
                fail("could not copy {$relative}");
            }
            $copied++;
        }
    }

    return $copied;
}

function dirSize(string $path): int
{
    if (! is_dir($path)) {
        return 0;
    }

    $bytes = 0;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        if ($item->isFile() && ! $item->isLink()) {
            $bytes += $item->getSize();
        }
    }

    return $bytes;
}

function mb(int $bytes): string
{
    return sprintf('%.1f MB', $bytes / 1048576);
}

/** Finds files anywhere under $root matching a glob, for the preflight gates. */
function findFiles(string $root, string $pattern, ?callable $filter = null): array
{
    if (! is_dir($root)) {
        return [];
    }

    $found = [];
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        if (fnmatch($pattern, $item->getBasename()) && (! $filter || $filter($item))) {
            $found[] = str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
        }
    }

    return $found;
}

// ─── 1. Build dependencies ───────────────────────────────────────────────────

echo "Building MakeAI v{$version}\n";
echo "Source:  {$srcDir}\n";
echo "Staging: {$stageRoot}\n";

step('Cleaning staging directory');
rmTree($stageRoot);
ensureDir($appRoot);

step('Building frontend assets');
if ($skipDeps) {
    info('skipped (--skip-deps)');
} else {
    // npm runs in the source tree: it only writes public/build and leaves the
    // developer's environment intact, unlike composer --no-dev.
    run('npm ci', $srcDir, 'npm ci');
    run('npm run build', $srcDir, 'npm run build');
}

if (! is_dir($srcDir . '/public/build')) {
    fail('public/build is missing — run without --skip-deps to build the frontend.');
}

// ─── 2. Copy application source (allowlist) ──────────────────────────────────

step('Copying application source into ' . APP_DIR . '/');

$denyPaths = array_map(fn ($p) => str_replace('/', DIRECTORY_SEPARATOR, $p), $denySubpaths);
$skip = function (string $relative) use ($denyPaths): bool {
    $normalised = str_replace('/', DIRECTORY_SEPARATOR, $relative);

    foreach ($denyPaths as $deny) {
        if ($normalised === $deny || str_starts_with($normalised, $deny . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }

    return false;
};

$fileCount = 0;
foreach (ALLOW_DIRS as $dir) {
    $source = $srcDir . DIRECTORY_SEPARATOR . $dir;
    if (! is_dir($source)) {
        fail("allowlisted directory is missing from the repo: {$dir}");
    }

    $n = copyTree($source, $appRoot . DIRECTORY_SEPARATOR . $dir, $skip, $dir);
    $fileCount += $n;
    info(sprintf('%-12s %d files', $dir . '/', $n));
}

foreach (ALLOW_FILES as $file) {
    $source = $srcDir . DIRECTORY_SEPARATOR . $file;
    if (! is_file($source)) {
        fail("allowlisted file is missing from the repo: {$file}");
    }

    if (! @copy($source, $appRoot . DIRECTORY_SEPARATOR . $file)) {
        fail("could not copy {$file}");
    }
    $fileCount++;
}
info(sprintf('%-12s %d files', 'root files', count(ALLOW_FILES)));

// The real seeders and factories are denied above; put the reference stubs back
// so database/seeders and database/factories exist and are usable. A demo build ships
// the real ones, so the stubs would overwrite DatabaseSeeder with a reference copy that
// calls none of the seeders demo:reset depends on.
step($demo ? 'Skipping reference stubs (demo ships the real seeders)' : 'Placing reference stubs');
foreach ($demo ? [] : STUB_FILES as $target => $source) {
    $sourcePath = $srcDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
    if (! is_file($sourcePath)) {
        fail("stub is missing from the repo: {$source}");
    }

    $targetPath = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    ensureDir(dirname($targetPath));

    if (! @copy($sourcePath, $targetPath)) {
        fail("could not place stub: {$target}");
    }
    $fileCount++;
    info($target);
}

// ─── 3. Install production dependencies into the staging copy ────────────────

step('Installing production dependencies (composer --no-dev)');
if ($skipDeps) {
    info('copying existing vendor/ (--skip-deps)');
    if (! is_dir($srcDir . '/vendor')) {
        fail('vendor/ is missing — run without --skip-deps.');
    }
    copyTree($srcDir . '/vendor', $appRoot . '/vendor', null, 'vendor');
} else {
    // --no-scripts: package:discover needs a bootable app with a .env, which does
    // not exist yet. Laravel rebuilds the manifest lazily on the buyer's first
    // request, so nothing is lost.
    run(
        'composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts',
        $appRoot,
        'composer install'
    );
}

$vendorBefore = dirSize($appRoot . '/vendor');
info('vendor/ is ' . mb($vendorBefore));

// ─── 4. Slim vendor ──────────────────────────────────────────────────────────

step('Slimming vendor/');
if ($noSlim) {
    info('skipped (--no-slim)');
} else {
    $prunedDirs  = ['tests', 'Tests', 'test', 'docs', 'doc', 'examples', 'example', '.git', '.github'];
    $prunedFiles = ['*.md', '*.dist', 'phpunit.xml*', '.travis.yml', '.editorconfig', 'Makefile'];
    $removed     = 0;

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appRoot . '/vendor', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $name = $item->getBasename();

        if ($item->isDir() && in_array($name, $prunedDirs, true)) {
            rmTree($item->getPathname());
            $removed++;
        } elseif ($item->isFile() && matchesAny($name, $prunedFiles)) {
            @unlink($item->getPathname());
            $removed++;
        }
    }

    // Fonts are pruned by name rather than by the pattern lists above: every one of them is
    // referenced by mPDF's default font config, so no reachability rule can find them. The
    // decision of which to drop is a judgement about scripts, recorded at PRUNE_FONTS.
    $fontDir = $appRoot . '/vendor/mpdf/mpdf/ttfonts';
    $fontBytes = 0;

    if (is_dir($fontDir)) {
        foreach (PRUNE_FONTS as $font) {
            $path = $fontDir . DIRECTORY_SEPARATOR . $font;

            if (is_file($path)) {
                $fontBytes += filesize($path);
                @unlink($path);
                $removed++;
            }
        }

        info(sprintf('pruned %d mPDF fonts (%s)', count(PRUNE_FONTS), mb($fontBytes)));
    }

    $vendorAfter = dirSize($appRoot . '/vendor');
    info(sprintf(
        'pruned %d paths: %s → %s (saved %s)',
        $removed,
        mb($vendorBefore),
        mb($vendorAfter),
        mb($vendorBefore - $vendorAfter)
    ));

    // Pruning can in principle remove a file a package autoloads. Prove the
    // autoloader still resolves the framework before shipping.
    step('Verifying autoloader survived slimming');
    run(
        'php -r "require \'vendor/autoload.php\'; '
        . 'exit(class_exists(\'Illuminate\\\\Foundation\\\\Application\') ? 0 : 1);"',
        $appRoot,
        'autoload verification'
    );
    info('autoloader OK');
}

// ─── 5. Assemble the webroot ─────────────────────────────────────────────────

step('Assembling webroot');

copyTree($srcDir . '/public/build', $webroot . '/build', null, 'build');
info('build/ assets copied');

// Laravel's Vite helper looks for build/manifest.json; Vite 5+ writes it to
// build/.vite/manifest.json. Ship both so either lookup resolves.
$viteManifest = $webroot . '/build/.vite/manifest.json';
if (is_file($viteManifest)) {
    copy($viteManifest, $webroot . '/build/manifest.json');
    info('manifest.json mirrored to build/ root');
}

foreach (['index.php', '.htaccess', 'robots.txt', 'favicon.ico'] as $file) {
    $source = $srcDir . '/distribution/' . $file;
    if (! is_file($source)) {
        fail("distribution/{$file} is missing.");
    }
    copy($source, $webroot . DIRECTORY_SEPARATOR . $file);
}
info('index.php, .htaccess, robots.txt, favicon.ico placed');

// Defence in depth: Apache's root rules live inside <IfModule mod_rewrite.c>,
// and nginx ignores .htaccess entirely. This second file denies the app
// directory directly, so a misconfigured host cannot serve core/.env.
if (is_file($srcDir . '/distribution/core.htaccess')) {
    copy($srcDir . '/distribution/core.htaccess', $appRoot . '/.htaccess');
    info(APP_DIR . '/.htaccess (deny-all) placed');
}

if (is_file($srcDir . '/distribution/web.config')) {
    copy($srcDir . '/distribution/web.config', $webroot . '/web.config');
    info('web.config placed (IIS)');
}

// nginx/supervisor/cron templates. The nginx one is load-bearing: without it a
// VPS buyer has no way to know that core/ must be denied.
if (is_dir($srcDir . '/distribution/deploy')) {
    copyTree($srcDir . '/distribution/deploy', $appRoot . '/deploy', null, 'deploy');
    info('deploy/ templates placed (nginx, supervisor, cron)');
}

// The public disk writes here; it must exist inside the served webroot so
// APP_URL/storage/... resolves without a symlink (shared hosts forbid them).
ensureDir($webroot . '/storage');
touch($webroot . '/storage/.gitkeep');

// Addons ship separately, but the directory has to exist and be writable so the
// admin "upload addon" flow has somewhere to extract to. A demo build fills it: the
// point of the demo is to show the whole product, and demo:reset activates whatever
// it finds here.
ensureDir($appRoot . '/addons');
touch($appRoot . '/addons/.gitkeep');

if (! $demo) {
    info('empty addons/ directory created (addons distributed separately)');
} else {
    $addonFiles = 0;

    foreach (DEMO_ADDONS as $slug) {
        $source = $srcDir . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . $slug;

        if (! is_dir($source)) {
            fail("addon listed in DEMO_ADDONS is missing from the repo: {$slug}");
        }

        // No manifest means AddonService::getAddonConfig() returns null and activate()
        // bails, which would surface on the demo as a silently missing feature.
        if (! is_file($source . DIRECTORY_SEPARATOR . 'addon.json')) {
            fail("addon '{$slug}' has no addon.json — it cannot be activated");
        }

        // $skip carries the same DENY_DIR_NAMES/DENY_FILE_PATTERNS pruning as the core
        // copy (node_modules, tests, .git), so a bundled addon is no fatter than it must be.
        $n = copyTree($source, $appRoot . '/addons/' . $slug, null, "addons/{$slug}");
        $addonFiles += $n;
        info(sprintf('%-22s %d files', $slug . '/', $n));
    }

    info(sprintf('%d addons bundled, %d files', count(DEMO_ADDONS), $addonFiles));
}

// The demo host's logo and favicon. ALLOW_DIRS deliberately omits public/ — the buyer
// package's webroot is assembled file by file — so this one directory has to be carried
// over explicitly, and only for a demo build. It lands in core/public/demo-assets, which
// the web server denies; DemoProvisionSeeder reads it from disk and copies each image
// onto the public disk on every demo:reset. Without this step the demo would come up
// with no logo, and no way to set one (the admin write is blocked in demo mode).
if ($demo) {
    step('Bundling demo branding assets');

    $assetSource = $srcDir . '/public/demo-assets';

    if (! is_dir($assetSource)) {
        fail('public/demo-assets is missing — the demo host has no logo or favicon source');
    }

    $assetFiles = copyTree($assetSource, $appRoot . '/public/demo-assets', null, 'public/demo-assets');
    info("{$assetFiles} files copied");
}

// The shipped .env.example keeps the whole demo block commented out so a buyer cannot
// enable a data-destroying reset by accident. The demo host needs the opposite default,
// and asking the operator to hand-edit it is exactly the step that gets skipped.
if ($demo) {
    step('Enabling demo mode in .env.example');

    $envPath = $appRoot . DIRECTORY_SEPARATOR . '.env.example';
    $env     = file_get_contents($envPath);

    if ($env === false) {
        fail('could not read the staged .env.example');
    }

    // Uncomment the DEMO_* block in place rather than appending a second copy — a
    // duplicate key later in the file would silently win over the one above it.
    // Digits are part of the character class on purpose: DEMO_2CHECKOUT_* would
    // otherwise stay commented out and that gateway would silently never provision.
    $env = preg_replace('/^# (DEMO_[A-Z0-9_]+=)/m', '$1', $env, -1, $uncommented);

    if ($uncommented === 0) {
        fail('no commented DEMO_* keys found in .env.example — the demo preset cannot be applied');
    }

    $env = str_replace('DEMO_ENABLED=false', 'DEMO_ENABLED=true', $env, $flipped);

    if ($flipped === 0) {
        fail('DEMO_ENABLED=false not found in .env.example — the demo preset cannot be applied');
    }

    if (file_put_contents($envPath, $env) === false) {
        fail('could not write the demo .env.example');
    }

    info("{$uncommented} DEMO_* keys uncommented, DEMO_ENABLED=true");
}

step('Creating writable directory skeleton');
foreach (STORAGE_SKELETON as $dir) {
    $path = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    ensureDir($path);
    touch($path . DIRECTORY_SEPARATOR . '.gitkeep');
}
info(count(STORAGE_SKELETON) . ' directories created with .gitkeep');

// ─── 6. Preflight gates ──────────────────────────────────────────────────────

step('Running preflight checks');

$failures = [];
$notShippable = false;

/**
 * Individual assertions actually performed, so the summary line reports the truth. It used
 * to be `count($mustExist) + 6` — a hand-maintained constant that stopped matching reality
 * the first time a gate was added, and quietly under-reported from then on.
 */
$checksRun = 0;

$mustExist = [
    APP_DIR . '/vendor/autoload.php' => $appRoot . '/vendor/autoload.php',
    APP_DIR . '/.env.example'        => $appRoot . '/.env.example',
    APP_DIR . '/artisan'             => $appRoot . '/artisan',
    'index.php'                      => $webroot . '/index.php',
    'build/manifest.json'            => $webroot . '/build/manifest.json',
];

foreach ($mustExist as $label => $path) {
    $checksRun++;

    if (! file_exists($path)) {
        $failures[] = "required file missing: {$label}";
    }
}

// `hot` makes Vite load assets from http://localhost:5173 — the buyer gets a
// blank application. It cannot reach the package via the allowlist, but the
// cost of being wrong here is a broken release, so check anyway.
foreach (['hot', 'hot.bak'] as $devMarker) {
    $checksRun++;

    if (file_exists($webroot . DIRECTORY_SEPARATOR . $devMarker)) {
        $failures[] = "Vite dev-server marker present: {$devMarker}";
    }
}

$checksRun++;

if (file_exists($appRoot . '/.env')) {
    $failures[] = '.env leaked into the package (contains live credentials)';
}

foreach (['*.sqlite', '*.log', '*.md'] as $pattern) {
    $checksRun++;

    // *.md is only forbidden at the app root (vendor/resources legitimately
    // contain markdown that the product renders).
    $found = $pattern === '*.md'
        ? array_values(array_filter(
            glob($appRoot . '/*.md') ?: [],
            fn ($p) => is_file($p)
        ))
        : findFiles($appRoot, $pattern);

    foreach (array_slice($found, 0, 5) as $file) {
        $failures[] = "forbidden file in package: {$file}";
    }
}

$checksRun++;

if (is_dir($appRoot . '/vendor/phpunit')) {
    // Under --skip-deps the developer's vendor/ is reused verbatim, so dev
    // packages are expected. Warn loudly rather than failing: the flag exists to
    // iterate on packaging, and the resulting zip must not be shipped.
    if ($skipDeps) {
        $notShippable = true;
        info('! vendor/phpunit present (expected under --skip-deps)');
    } else {
        $failures[] = 'vendor/phpunit is present — dev dependencies were installed';
    }
}

$checksRun++;

foreach (findFiles($appRoot, 'node_modules', fn ($i) => $i->isDir()) as $dir) {
    $failures[] = "node_modules leaked into the package: {$dir}";
}

$checksRun++;
$oversized = [];
$items = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($wrapper, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($items as $item) {
    if ($item->isFile() && ! $item->isLink() && $item->getSize() > 50 * 1024 * 1024) {
        $oversized[] = str_replace($wrapper . DIRECTORY_SEPARATOR, '', $item->getPathname())
            . ' (' . mb($item->getSize()) . ')';
    }
}
foreach ($oversized as $file) {
    $failures[] = "file larger than 50 MB: {$file}";
}

// Now a hard failure, not a warning: the seeder classes no longer ship, so
// data.sql is the only way a fresh install gets its settings, tools, pages and
// mail templates. Without it finalize() reaches its "no data source" branch and
// the buyer's installation dies after the database has already been migrated.
$checksRun++;

if ($demo) {
    // The demo bootstraps from the seeders it ships, so data.sql is not load-bearing here
    // and the addon-table check below would fail by design: the dev export legitimately
    // contains tables that the bundled addons' own migrations create.
    info('data.sql checks skipped (demo bootstraps from seeders)');
} elseif (! is_file($appRoot . '/database/data/data.sql')) {
    $failures[] = 'database/data/data.sql is missing — it is the only data source left now that '
        . 'database/seeders is excluded. Generate it with: php artisan installer:export-data';
} else {
    // Every table the dump touches must be created by a migration that actually
    // ships. A dev database has all the addons installed, so an export taken
    // without the addon-table exclusions emits LOCK TABLES for tables the buyer's
    // core package never creates — the import then dies on ERROR 1146 after
    // migrate has already run, leaving a half-installed site.
    $checksRun++;

    $coreTables = [];
    foreach (glob($appRoot . '/database/migrations/*.php') ?: [] as $migration) {
        $source = file_get_contents($migration) ?: '';
        if (preg_match_all('/Schema::create\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $source, $m)) {
            foreach ($m[1] as $table) {
                $coreTables[$table] = true;
            }
        }
    }

    $dump = file_get_contents($appRoot . '/database/data/data.sql') ?: '';
    $dumpTables = [];
    if (preg_match_all('/(?:LOCK TABLES|INSERT INTO)\s+`([a-zA-Z0-9_]+)`/', $dump, $m)) {
        foreach ($m[1] as $table) {
            $dumpTables[$table] = true;
        }
    }

    // An empty $coreTables means the regex found nothing — treat that as a broken
    // check rather than silently passing every table.
    if ($coreTables === []) {
        $failures[] = 'could not parse any Schema::create() from database/migrations — '
            . 'the data.sql table check cannot run';
    } else {
        foreach (array_keys($dumpTables) as $table) {
            if (! isset($coreTables[$table])) {
                $failures[] = "database/data/data.sql references `{$table}`, which no shipped migration "
                    . 'creates (likely an addon table picked up from the dev database). '
                    . 'Regenerate it with: php artisan installer:export-data';
            }
        }
    }
}

// The product's own seeders and factories must genuinely be gone: each directory
// may contain nothing beyond its reference stub. A leaked AiToolSeeder would
// also mask the check above, since finalize() would find a usable seeder.
//
// Inverted for the demo, which ships them on purpose: assert instead that the two
// classes demo:reset actually invokes are present, so a copy that silently dropped
// them fails here rather than on the demo host at 3am when the reset cron fires.
$expectedStubs = [];
foreach (array_keys(STUB_FILES) as $stub) {
    $expectedStubs[dirname($stub)][] = basename($stub);
}

if ($demo) {
    foreach (['database/seeders/DatabaseSeeder.php', 'database/seeders/DemoSeeder.php'] as $required) {
        $checksRun++;

        if (! is_file($appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $required))) {
            $failures[] = "{$required} is missing — demo:reset cannot seed the demo without it";
        }
    }

    // Each bundled addon must have arrived whole. A truncated copy activates and then
    // 500s on its first route, which is worse than not shipping it at all.
    foreach (DEMO_ADDONS as $slug) {
        $checksRun++;

        if (! is_file($appRoot . "/addons/{$slug}/addon.json")) {
            $failures[] = "bundled addon '{$slug}' is missing its addon.json in the package";
        }
    }

    $expectedStubs = [];
}

foreach ($expectedStubs as $dir => $allowed) {
    $checksRun++;

    $path = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);

    if (! is_dir($path)) {
        $failures[] = "{$dir} is missing from the package (the reference stub was not placed)";
        continue;
    }

    $unexpected = array_diff(scandir($path) ?: [], ['.', '..'], $allowed);
    foreach ($unexpected as $file) {
        $failures[] = "{$dir}/{$file} leaked into the package (only the reference stub should ship)";
    }
}

// The license public key. This is the only failure mode here that is both silent and
// catastrophic: with the placeholder still in place the package builds, installs and looks
// perfect, then refuses EVERY activation and update. Nothing surfaces it until buyers
// report it, after the sale, with the item already live. Every other mistake this script
// can make fails loudly somewhere above.
//
// Parsed textually rather than by require-ing the class: the build script has no business
// executing application code, and an unparseable file fails the build rather than being
// waved through, which is the right default for a gate of this kind.
$checksRun++;
$licenseKeyFile = $appRoot . '/app/Support/LicenseKey.php';

if (! is_file($licenseKeyFile)) {
    $failures[] = 'app/Support/LicenseKey.php is missing — the license public key cannot be verified';
} else {
    $source = (string) file_get_contents($licenseKeyFile);

    $readConst = static function (string $name) use ($source): ?string {
        return preg_match('/const\s+' . $name . '\s*=\s*[\'"]([^\'"]*)[\'"]/', $source, $m) ? $m[1] : null;
    };

    $publicKey   = $readConst('PUBLIC_KEY');
    $placeholder = $readConst('PLACEHOLDER');

    if ($publicKey === null || $placeholder === null) {
        $failures[] = 'could not read PUBLIC_KEY/PLACEHOLDER from app/Support/LicenseKey.php — '
            . 'check the file by hand before shipping';
    } elseif ($publicKey === $placeholder) {
        if ($allowPlaceholderKey) {
            $notShippable = true;
            info('! license public key is still the placeholder (allowed by --allow-placeholder-key)');
        } else {
            $failures[] = 'the license public key is still the placeholder — no buyer could activate '
                . 'this build. Set the real Ed25519 key in app/Support/LicenseKey.php, or pass '
                . '--allow-placeholder-key for a test build.';
        }
    }
}

if ($failures) {
    fwrite(STDERR, "\nFATAL: " . count($failures) . " preflight check(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "  - {$failure}\n");
    }
    fwrite(STDERR, "\nThe package was NOT built. Staging left at: {$stageRoot}\n");
    exit(1);
}

info($checksRun . ' checks passed');

// ─── 7. Buyer-facing top-level files ─────────────────────────────────────────

step('Writing buyer-facing files');

if (is_file($srcDir . '/LICENSE')) {
    copy($srcDir . '/LICENSE', $wrapper . '/license.txt');
    info('license.txt');
}

// The offline manual. Generated from resources/docs/core on every build, so it can never
// describe a version other than the one in this zip. Rendered by artisan in the SOURCE
// tree — the staging copy has no .env and cannot boot.
step('Building documentation');

ensureDir($wrapper . '/documentation');
$docsHtml = $wrapper . '/documentation/docs.html';

run(
    sprintf(
        'php artisan docs:build-html --output=%s --app-version=%s',
        escapeshellarg($docsHtml),
        escapeshellarg($version)
    ),
    $srcDir,
    'documentation build'
);

// The command fails loudly on an empty corpus, so this only catches a truncated write —
// but shipping a 2 KB "manual" to Envato is exactly the kind of thing nobody notices.
if (! is_file($docsHtml) || filesize($docsHtml) < 20000) {
    fail('documentation/docs.html is missing or suspiciously small — check resources/docs/core.');
}

// The folder's entry point. Sends the reader to the live docs — which are newer than any
// zip and cover the addons — while docs.html stays behind as the offline copy.
//
// Three things this does that a bare `<script>location.href=...</script>` does not:
//
//   - navigator.onLine gates the redirect. Offline (or on a plane, or behind a firewall)
//     the buyer keeps a usable page pointing at docs.html, instead of a browser error.
//   - location.replace() rather than .href, so this page never enters history. With .href,
//     pressing Back from the docs site lands here and immediately redirects again — the
//     reader is trapped and cannot go back at all.
//   - The links render regardless. If the redirect fails for any reason — scripts disabled,
//     site moved, domain lapsed — there is still a working page with both destinations on
//     it, rather than a blank screen.
$docsSite = htmlspecialchars(DOCS_SITE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$versionEscaped = htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// json_encode produces a correctly quoted and escaped JS string literal, so the URL cannot
// break out of it however it is written in DOCS_SITE.
$redirectJs = 'location.replace(' . json_encode(DOCS_SITE, JSON_UNESCAPED_SLASHES) . ');';

file_put_contents($wrapper . '/documentation/index.html', <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MakeAI — Documentation</title>
<style>
  *,*::before,*::after{box-sizing:border-box}
  :root{--bg:#fff;--fg:#1f2430;--muted:#5b6478;--line:#e3e7ee;--accent:#4f46e5;--card:#f7f8fb}
  @media (prefers-color-scheme:dark){
    :root{--bg:#14171f;--fg:#e6e9ef;--muted:#98a1b3;--line:#272c38;--accent:#8b87f5;--card:#1c212b}
  }
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
    background:var(--bg);color:var(--fg);padding:24px;
    font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .card{max-width:540px;width:100%;text-align:center}
  h1{margin:0 0 6px;font-size:22px;letter-spacing:-.01em}
  .version{margin:0 0 26px;color:var(--muted);font-size:13px}
  .links{display:flex;gap:12px;flex-wrap:wrap;justify-content:center}
  a.btn{display:block;flex:1 1 200px;padding:15px 18px;border:1px solid var(--line);
    border-radius:10px;text-decoration:none;color:var(--fg);background:var(--card)}
  a.btn:hover{border-color:var(--accent)}
  a.btn strong{display:block;color:var(--accent);font-size:15px;margin-bottom:3px}
  a.btn span{font-size:12.5px;color:var(--muted)}
  .note{margin-top:24px;font-size:12.5px;color:var(--muted)}
</style>
</head>
<body>
<div class="card">
  <h1>MakeAI Documentation</h1>
  <p class="version">Version {$versionEscaped}</p>
  <div class="links">
    <a class="btn" href="{$docsSite}">
      <strong>Read online &rarr;</strong>
      <span>Always current, and covers the addons</span>
    </a>
    <a class="btn" href="docs.html">
      <strong>Offline copy</strong>
      <span>Bundled with this version, works without internet</span>
    </a>
  </div>
  <p class="note">Taking you to the online documentation&hellip; use the links above if nothing happens.</p>
</div>
<script>
  // Only leave if the browser is confident it has a connection; see build-release.php.
  if (navigator.onLine !== false) {
    {$redirectJs}
  }
</script>
</body>
</html>

HTML);

info('documentation/docs.html, documentation/index.html');

file_put_contents($wrapper . '/readme.txt', implode("\n", [
    "MakeAI v{$version}",
    str_repeat('=', 10 + strlen($version)),
    '',
    'QUICK START',
    '-----------',
    '',
    'Everything you upload is inside script.zip. The documentation and licence',
    'stay on your computer — they do not belong on the server.',
    '',
    'Shared hosting (cPanel, DirectAdmin, Plesk):',
    '  1. Upload script.zip into public_html/ (your domain\'s document root)',
    '  2. Extract it THERE, then delete script.zip',
    '     You should now see index.php and ' . APP_DIR . '/ side by side in public_html/',
    '  3. Create a MySQL database and user, and note the credentials',
    '  4. Visit your domain in a browser',
    '  5. Follow the installation wizard',
    '',
    'VPS / dedicated server (nginx or Apache):',
    '  1. Extract script.zip into /var/www/your-site',
    '  2. Point your web server root at /var/www/your-site',
    '  3. Apply the config in ' . APP_DIR . '/deploy/nginx.conf.example',
    '     (nginx does not read .htaccess — this step is REQUIRED to keep',
    '      the ' . APP_DIR . '/ directory private)',
    '  4. Visit your domain and follow the installation wizard',
    '',
    'REQUIREMENTS',
    '------------',
    '  PHP 8.3 or newer, MySQL 5.7+ / MariaDB 10.3+',
    '  Extensions: pdo, pdo_mysql, mbstring, json, openssl, tokenizer, xml,',
    '              dom, iconv, ctype, bcmath, curl, fileinfo, zip',
    '',
    'The installation wizard checks all of this for you before installing.',
    '',
    'DOCUMENTATION',
    '-------------',
    '',
    '  Open documentation/index.html — it takes you to the online documentation,',
    '  which is always current and covers the addons.',
    '',
    '  documentation/docs.html is the offline copy of this version\'s manual,',
    '  bundled so it keeps working without an internet connection.',
    '',
    '      ' . DOCS_SITE,
    '',
]) . "\n");
info('readme.txt');

// ─── 8. Package ──────────────────────────────────────────────────────────────

// The application ships as its own zip nested inside the outer archive. Two reasons, both
// buyer-facing:
//
//  - It cannot be uploaded wrong. Only script.zip goes on the server; documentation and
//    licence text physically cannot be dragged into the document root with it.
//  - Path length. zip.php stores entries relative to its source, so paths inside script.zip
//    start at `core/...` rather than `makeai-vX.Y.Z/script/core/...` — 21 characters back
//    against the 180-char ceiling Windows Explorer enforces from the extraction root, which
//    is the buyer's own directory depth and outside our control.
//
// Contents sit at the zip root (no wrapper directory), so a buyer extracts straight into
// public_html instead of extracting and then moving everything up a level.
step('Creating script.zip');

$scriptZip = $wrapper . DIRECTORY_SEPARATOR . 'script.zip';

run(
    sprintf(
        'php %s %s %s',
        escapeshellarg($srcDir . '/scripts/zip.php'),
        escapeshellarg($webroot),
        escapeshellarg($scriptZip)
    ),
    $srcDir,
    'script.zip creation'
);

if (! is_file($scriptZip)) {
    fail('script.zip was not created.');
}

// The staged tree cannot stay where it is, or every application file ships twice — once
// loose and once inside script.zip.
//
// Under --keep-build it is moved to a sibling of the staging root rather than deleted:
// inspecting that tree is the whole point of the flag, and it has to leave $stageRoot
// because that is what the outer archive is built from.
if ($keepBuild) {
    $inspectPath = $stageRoot . '-app-tree';
    rmTree($inspectPath);

    if (! @rename($webroot, $inspectPath)) {
        fail("could not move the staged application tree aside: {$inspectPath}");
    }

    info('application tree kept at ' . $inspectPath);
} else {
    rmTree($webroot);

    if (is_dir($webroot)) {
        fail("could not remove the staged application directory: {$webroot}");
    }
}

info('script.zip is ' . mb(filesize($scriptZip)));

step('Creating archive');

ensureDir($srcDir . '/dist');
$zipPath = $srcDir . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . "{$pkgName}.zip";

// Zips the wrapper's *parent* contents so the archive root is makeai-vX.Y.Z/.
run(
    sprintf('php %s %s %s', escapeshellarg($srcDir . '/scripts/zip.php'), escapeshellarg($stageRoot), escapeshellarg($zipPath)),
    $srcDir,
    'zip creation'
);

if (! $keepBuild) {
    step('Cleaning staging directory');
    rmTree($stageRoot);
} else {
    info("staging kept at {$stageRoot}");
}

$elapsed = round(microtime(true) - $startedAt);
echo "\n";
echo "========================================\n";
echo " SUCCESS  dist/{$pkgName}.zip\n";
echo sprintf(" %s, built in %dm %ds\n", mb(filesize($zipPath)), intdiv($elapsed, 60), $elapsed % 60);
echo "========================================\n";

if ($notShippable) {
    echo "\n";
    echo " *** DO NOT SHIP THIS BUILD ***\n";

    if ($skipDeps) {
        echo " - Built with --skip-deps, so vendor/ still contains dev dependencies.\n";
        echo "   Run without --skip-deps to produce a release package.\n";
    }

    if ($allowPlaceholderKey) {
        echo " - The license public key is still the placeholder, so every activation and\n";
        echo "   update will be refused. Set the real Ed25519 key in app/Support/LicenseKey.php.\n";
    }
}
echo "\nExtracts to:\n";
echo "  {$pkgName}/\n";
echo "  ├── script.zip         <- THIS is what the buyer uploads and extracts\n";
echo "  │      extracts to:  index.php  .htaccess  web.config  favicon.ico\n";
echo "  │                    robots.txt  build/  storage/\n";
echo "  │                    " . APP_DIR . "/  <- application, denied by web server\n";
echo "  ├── documentation/\n";
echo "  │   ├── index.html   <- entry point, redirects to the online docs\n";
echo "  │   └── docs.html    <- offline manual (core)\n";
echo "  ├── license.txt\n";
echo "  └── readme.txt       <- quick start\n";
