<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\MigrateStorageFiles;
use App\Services\CloudStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class StorageSettingsController extends Controller
{
    /** S3-compatible drivers (everything except `local`). */
    private const CLOUD_DRIVERS = ['s3', 'r2', 'spaces', 'wasabi', 'b2'];

    private const SECRET_FIELDS = ['access_key', 'secret_key'];

    private const OPTION_FIELDS = ['region', 'bucket', 'endpoint', 'url'];

    public function edit()
    {
        return Inertia::render('Admin/Settings/Storage', [
            'currentDriver' => (string) settings('storage_driver', 'local'),
            'drivers' => $this->driverCatalog(),
            'providers' => $this->providerPayload(),
            'migration' => $this->migrationState(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeManage();

        $driver = (string) $request->input('driver');

        // R2 and Backblaze B2 have no per-object public URL derivable from the bucket —
        // the admin must supply a public/custom domain, or served media would 404.
        $urlRules = in_array($driver, ['r2', 'b2'], true)
            ? ['required', 'string', 'max:255', 'regex:/^https?:\/\//i']
            : ['nullable', 'string', 'max:255', 'regex:/^https?:\/\//i'];

        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:local,'.implode(',', self::CLOUD_DRIVERS)],
            'credentials' => ['nullable', 'array'],
            'credentials.access_key' => ['nullable', 'string', 'max:255'],
            'credentials.secret_key' => ['nullable', 'string', 'max:512'],
            'credentials.region' => ['nullable', 'string', 'max:100'],
            'credentials.bucket' => ['nullable', 'string', 'max:255'],
            // Custom endpoint / public URL are rendered nowhere as HTML, but block
            // dangerous schemes defensively and require a real http(s) origin.
            'credentials.endpoint' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\//i'],
            'credentials.url' => $urlRules,
        ], [
            'credentials.url.required' => translate('Cloudflare R2 and Backblaze B2 need a public bucket URL (custom or public development domain) so uploaded files are reachable.'),
        ]);

        $driver = $validated['driver'];
        $currentDriver = (string) settings('storage_driver', 'local');

        // Switching to LOCAL: a prior migration only ever COPIED files (never deleted
        // them from local), so the local disk still holds everything. Activate at once —
        // there is no 404 window to guard against.
        if ($driver === 'local') {
            settings_set('storage_driver', 'local', 'string', 'storage');
            \App\Models\Setting::flushCache();

            return back()->with('success', translate('Storage switched to local server. Files are served from this server.'));
        }

        // Persist any newly-supplied credentials BEFORE testing, but blank secrets
        // keep their stored value (the UI sends blank for already-configured secrets).
        $this->persistCredentials($driver, $validated['credentials'] ?? []);

        // Enforce a working connection before a cloud driver can go live — a bad
        // config must never silently break media serving for the whole site.
        $result = CloudStorageService::forDriver($driver)->testConnection();

        if (! ($result['success'] ?? false)) {
            return back()
                ->withErrors(['driver' => $result['error'] ?? translate('The storage connection test failed. Settings were saved but the driver was not activated.')])
                ->with('error', translate('Connection test failed — :error', ['error' => $result['error'] ?? translate('unknown error')]));
        }

        // Re-saving the already-active driver (e.g. rotating keys): just keep the creds.
        if ($driver === $currentDriver) {
            \App\Models\Setting::flushCache();

            return back()->with('success', translate('Storage settings saved.'));
        }

        if (($this->migrationState()['status'] ?? null) === 'running') {
            return back()->with('error', translate('A storage migration is already running. Please wait for it to finish before switching drivers.'));
        }

        // Switching to a DIFFERENT cloud driver whose bucket may not yet hold the existing
        // media. If there's nothing to move, activate now; otherwise copy first and let the
        // job flip the driver on success, so the site never serves 404s mid-switch.
        $sourceCount = $this->sourceFileCount($currentDriver);

        if ($sourceCount === 0) {
            settings_set('storage_driver', $driver, 'string', 'storage');
            \App\Models\Setting::flushCache();

            return back()->with('success', translate('Cloud storage connected and activated. New uploads will be stored in your bucket.'));
        }

        $this->putMigrationState('running', $currentDriver, $driver, translate('Starting…'));
        MigrateStorageFiles::dispatch($currentDriver, $driver, true);

        return back()->with('success', translate(
            'Copying your existing files to the new storage (:count found). It will switch over automatically when the copy finishes — your site keeps serving from the current location until then. A queue worker must be running for this to complete.',
            ['count' => $sourceCount],
        ));
    }

    /**
     * Test a driver's stored (plus any just-typed) credentials without activating it.
     */
    public function test(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:'.implode(',', self::CLOUD_DRIVERS)],
            'credentials' => ['nullable', 'array'],
        ]);

        $driver = $validated['driver'];
        $this->persistCredentials($driver, $validated['credentials'] ?? []);

        return response()->json(CloudStorageService::forDriver($driver)->testConnection());
    }

    /**
     * Kick off a background copy of existing files between two drivers.
     */
    public function migrate(Request $request)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'source' => ['required', 'string', 'in:local,'.implode(',', self::CLOUD_DRIVERS)],
            'target' => ['required', 'string', 'in:local,'.implode(',', self::CLOUD_DRIVERS), 'different:source'],
        ]);

        $state = $this->migrationState();
        if (($state['status'] ?? null) === 'running') {
            return back()->with('error', translate('A migration is already running. Please wait for it to finish.'));
        }

        // Both endpoints must be usable before we start moving anything.
        foreach (['source' => $validated['source'], 'target' => $validated['target']] as $role => $driver) {
            if ($driver === 'local') {
                continue;
            }
            $check = CloudStorageService::forDriver($driver)->testConnection();
            if (! ($check['success'] ?? false)) {
                return back()->with('error', translate('The :role storage failed its connection check — :error', [
                    'role' => translate($role),
                    'error' => $check['error'] ?? translate('unknown error'),
                ]));
            }
        }

        $this->putMigrationState('running', $validated['source'], $validated['target'], translate('Starting…'));

        // A manual copy does NOT switch the active driver — it only syncs files.
        MigrateStorageFiles::dispatch($validated['source'], $validated['target'], false);

        return back()->with('success', translate('Migration started. You can leave this page — progress is saved. A queue worker must be running.'));
    }

    public function migrateStatus(): JsonResponse
    {
        return response()->json($this->migrationState());
    }

    /**
     * Clear a stuck/finished migration so the admin isn't blocked from starting another
     * (e.g. when a migration was dispatched but no queue worker ever ran it).
     */
    public function cancelMigration()
    {
        $this->authorizeManage();

        Cache::forget(MigrateStorageFiles::STATE_KEY);

        return back()->with('success', translate('Migration status cleared.'));
    }

    private function migrationState(): array
    {
        $state = Cache::get(MigrateStorageFiles::STATE_KEY, [
            'status' => 'idle',
            'total' => 0,
            'processed' => 0,
            'failed' => 0,
            'message' => '',
            'source' => null,
            'target' => null,
        ]);

        // Stale-running detection: if a migration was marked running but nothing has
        // touched it for well beyond the job's own timeout, the queue worker almost
        // certainly never picked it up (or died). Report it as stalled so the UI can
        // offer "Clear" instead of spinning forever.
        if (($state['status'] ?? null) === 'running' && ! empty($state['updated_at'])) {
            $staleAfterSeconds = 3600 + 600; // job timeout + margin
            try {
                if (\Illuminate\Support\Carbon::parse($state['updated_at'])->addSeconds($staleAfterSeconds)->isPast()) {
                    $state['status'] = 'stalled';
                    $state['message'] = translate('The migration has not progressed — is a queue worker running? You can clear it and try again.');
                }
            } catch (\Throwable) {
                // leave state as-is on an unparsable timestamp
            }
        }

        return $state;
    }

    private function putMigrationState(string $status, string $source, string $target, string $message): void
    {
        Cache::put(MigrateStorageFiles::STATE_KEY, [
            'status' => $status,
            'total' => 0,
            'processed' => 0,
            'failed' => 0,
            'message' => $message,
            'source' => $source,
            'target' => $target,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(6));
    }

    /**
     * Count the media files at a driver's location (excluding health-check probes).
     * Used to decide whether a driver switch needs a migration first. On any error
     * (unreachable bucket, bad creds) it assumes there may be files so the safe
     * migrate-then-activate path is taken rather than an immediate, possibly-404 switch.
     */
    private function sourceFileCount(string $driver): int
    {
        try {
            $disk = $driver === 'local'
                ? \Illuminate\Support\Facades\Storage::disk('local_public_media')
                : \Illuminate\Support\Facades\Storage::build(CloudStorageService::forDriver($driver)->diskConfig());

            return count(array_filter(
                $disk->allFiles(),
                static fn (string $path): bool => ! str_starts_with($path, 'healthcheck/'),
            ));
        } catch (\Throwable) {
            return 1;
        }
    }

    /**
     * Store credentials for a driver. Secrets left blank by the UI (meaning
     * "keep existing") are skipped so a saved key is never wiped.
     */
    private function persistCredentials(string $driver, array $credentials): void
    {
        foreach (self::SECRET_FIELDS as $field) {
            $value = $credentials[$field] ?? null;
            if (blank($value)) {
                continue;
            }
            settings_set("storage_{$driver}_{$field}", $value, 'encrypted', 'storage');
        }

        foreach (self::OPTION_FIELDS as $field) {
            if (! array_key_exists($field, $credentials)) {
                continue;
            }
            settings_set("storage_{$driver}_{$field}", (string) ($credentials[$field] ?? ''), 'string', 'storage');
        }
    }

    private function driverCatalog(): array
    {
        return [
            ['value' => 'local', 'label' => translate('Local Server (default)'), 'cloud' => false],
            ['value' => 's3', 'label' => 'Amazon S3', 'cloud' => true, 'doc_url' => 'https://docs.aws.amazon.com/AmazonS3/latest/userguide/creating-bucket.html'],
            ['value' => 'r2', 'label' => 'Cloudflare R2', 'cloud' => true, 'doc_url' => 'https://developers.cloudflare.com/r2/buckets/create-buckets/'],
            ['value' => 'spaces', 'label' => 'DigitalOcean Spaces', 'cloud' => true, 'doc_url' => 'https://docs.digitalocean.com/products/spaces/how-to/create/'],
            ['value' => 'wasabi', 'label' => 'Wasabi', 'cloud' => true, 'doc_url' => 'https://docs.wasabi.com/docs/creating-a-bucket'],
            ['value' => 'b2', 'label' => 'Backblaze B2', 'cloud' => true, 'doc_url' => 'https://www.backblaze.com/docs/cloud-storage-create-and-manage-buckets'],
        ];
    }

    /**
     * Per-cloud-driver stored config, with secrets reported as configured/not
     * (never returned in plaintext) and options echoed for editing.
     */
    private function providerPayload(): array
    {
        $payload = [];

        foreach (self::CLOUD_DRIVERS as $driver) {
            $secrets = [];
            foreach (self::SECRET_FIELDS as $field) {
                $secrets[$field] = filled(settings("storage_{$driver}_{$field}"));
            }

            $options = [];
            foreach (self::OPTION_FIELDS as $field) {
                $options[$field] = (string) settings("storage_{$driver}_{$field}", '');
            }

            $payload[$driver] = [
                'configured_secrets' => $secrets,
                'options' => $options,
            ];
        }

        return $payload;
    }

    private function authorizeManage(): void
    {
        abort_unless(
            auth('admin')->user()?->hasPermission('settings.manage'),
            403
        );
    }
}
