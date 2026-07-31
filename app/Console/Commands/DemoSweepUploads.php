<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Delete every uploaded file left behind by the previous demo window.
 *
 * demo:reset runs migrate:fresh, which drops the rows and does not touch disk. Everything
 * a visitor uploaded or generated in the preceding six hours — avatars, RAG documents,
 * image-tool output, ticket attachments, bank-transfer proofs — therefore survives the wipe
 * with nothing left pointing at it, and another batch lands on top six hours later. The
 * database is flat by construction (DemoSeeder is keyed on stable emails and gateway ids, so
 * it rewrites the same ~300 rows every time); this tree is the one part of a demo host that
 * grows without bound.
 *
 * Deleting whole trees rather than an enumerated list of directories is deliberate, and is
 * safe for one specific reason: the release ships storage/app/public and storage/app/private
 * EMPTY (STORAGE_SKELETON in scripts/build-release.php). No file under either root is
 * irreplaceable — it is either a seeder's, and rewritten moments later, or a visitor's, and
 * already orphaned. An allowlist would also have to be extended by hand every time an addon
 * introduces an upload directory, and the leak would come back silently when someone forgot.
 *
 * Demo-only, with no way to wave it through: on a real install this is a request to delete
 * every uploaded file the site has.
 */
class DemoSweepUploads extends Command
{
    protected $signature = 'demo:sweep-uploads {--dry-run : List what would be deleted without deleting it}';

    protected $description = 'Delete orphaned uploads left over from the previous demo window (demo mode only)';

    /**
     * The disks to sweep.
     *
     * `local_public_media` rather than `public`: the public disk can be rebound to a cloud
     * bucket at runtime from Settings → Storage (see config/filesystems.php), and a sweep
     * that followed that rebind would aim these deletes at a bucket. local_public_media is
     * documented as never rebound, so it always resolves to the on-server media root.
     *
     * @var array<int, string>
     */
    private const DISKS = ['local_public_media', 'local'];

    /**
     * Root-level entries the sweep steps over.
     *
     * `app`, `framework`, `logs` and `maintenance` are Laravel's own trees and have no
     * business under a media root — but PUBLIC_DISK_ROOT has resolved to the wrong place on
     * misconfigured installs before (see the comments on the `public` disk in
     * config/filesystems.php), and a sweep that took these with it would delete the session
     * store and the log file the failure was about to be reported in. Cheap insurance
     * against a config mistake this project has already made once.
     *
     * @var array<int, string>
     */
    private const PRESERVE = [
        '.gitkeep',
        '.gitignore',   // Laravel ships one at each storage root; deleting it un-ignores the tree
        '__probe',      // storage health-check probe; recreated on demand
        'app',
        'framework',
        'logs',
        'maintenance',
    ];

    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->error('demo:sweep-uploads is refused: demo mode is not enabled (DEMO_ENABLED).');
            $this->line('This command deletes every uploaded file on the site.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach (self::DISKS as $name) {
            $total += $this->sweepDisk($name, $dryRun);
        }

        $this->info($dryRun
            ? "{$total} orphaned entr".($total === 1 ? 'y' : 'ies').' would be deleted.'
            : "{$total} orphaned entr".($total === 1 ? 'y' : 'ies').' deleted.');

        return self::SUCCESS;
    }

    /**
     * @return int entries removed (or that would be removed) on this disk
     */
    private function sweepDisk(string $name, bool $dryRun): int
    {
        $disk = Storage::disk($name);
        $root = realpath($disk->path(''));

        if ($root === false) {
            // Nothing has been written to this disk yet on this host. Not an error —
            // a freshly installed demo reaches its first reset in exactly this state.
            $this->line("  {$name}: root does not exist yet, nothing to sweep");

            return 0;
        }

        if ($this->rootIsTooBroad($root)) {
            // The disk is misconfigured and the loop below would be an rm -rf of the
            // install. Warn and skip rather than guess at what was meant — one reset with
            // stale uploads is recoverable, this is not.
            $this->warn("  {$name}: refused, root resolves to {$root}");

            return 0;
        }

        $removed = 0;

        foreach ($disk->directories() as $dir) {
            if (in_array($dir, self::PRESERVE, true)) {
                continue;
            }

            if ($dryRun) {
                $this->line("  would delete {$name}:{$dir}/");
            } else {
                $disk->deleteDirectory($dir);
            }

            $removed++;
        }

        foreach ($disk->files() as $file) {
            if (in_array($file, self::PRESERVE, true)) {
                continue;
            }

            if ($dryRun) {
                $this->line("  would delete {$name}:{$file}");
            } else {
                $disk->delete($file);
            }

            $removed++;
        }

        if (! $dryRun) {
            $this->line("  {$name}: {$removed} removed");
        }

        return $removed;
    }

    /**
     * Whether a resolved disk root is a directory the sweep must never empty.
     *
     * Both real layouts put the media root one level BELOW each of these — public/storage in
     * a standard checkout, the webroot's storage/ in a distribution build, storage/app/private
     * for the local disk — so an exact match means the root was misconfigured, not that this
     * is an unusual but valid install.
     */
    private function rootIsTooBroad(string $root): bool
    {
        $forbidden = array_filter([
            realpath(base_path()),
            realpath(storage_path()),
            realpath(storage_path('app')),
            realpath(public_path()),
        ]);

        return in_array($root, $forbidden, true);
    }
}
