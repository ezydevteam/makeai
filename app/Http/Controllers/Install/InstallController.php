<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\EnvFileService;
use App\Services\LicenseService;
use App\Services\SystemCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InstallController extends Controller
{
    private const SESSION_KEY = 'install_wizard';
    private const TOTAL_STEPS = 7;

    /**
     * GET /install — render the wizard at its current step.
     */
    public function index(Request $request): \Inertia\Response
    {
        $wizard = $this->getWizardState($request);
        $step = (int) ($wizard['current_step'] ?? 1);

        $props = [
            'currentStep' => $step,
            'totalSteps' => self::TOTAL_STEPS,
            'stepsCompleted' => $wizard['steps_completed'] ?? [],
            'formData' => $wizard['data'] ?? [],
        ];

        if ($step === 1) {
            $props['systemCheck'] = app(SystemCheckService::class)->check();
            $props['allPass'] = app(SystemCheckService::class)->allPass();
        }

        if ($step === 6) {
            $demoPath = database_path('demo/demo.sql');
            $props['demoExists'] = file_exists($demoPath);
        }

        return Inertia::render('Install/Index', $props);
    }

    /**
     * POST /install/step/{step} — validate & store data for one step.
     */
    public function storeStep(Request $request, int $step): \Illuminate\Http\RedirectResponse
    {
        $wizard = $this->getWizardState($request);

        $validated = $this->validateStep($request, $step);

        // Persist an uploaded demo SQL file to disk NOW: finalize() is a separate
        // request, so the UploadedFile is gone by then (and can't be serialized
        // into the session). Store the path instead.
        if ($step === 6) {
            unset($validated['demo_file']);

            if (($validated['demo_method'] ?? null) === 'upload' && $request->hasFile('demo_file')) {
                $dest = storage_path('app/install');
                if (! is_dir($dest)) {
                    mkdir($dest, 0755, true);
                }
                $request->file('demo_file')->move($dest, 'demo-upload.sql');
                $validated['demo_file_path'] = $dest . DIRECTORY_SEPARATOR . 'demo-upload.sql';
            }
        }

        // Test DB connection + write privileges for step 2 before storing (skip for SQLite)
        if ($step === 2 && ($validated['db_driver'] ?? 'mysql') === 'mysql') {
            try {
                $this->testDatabaseConnection($validated);
            } catch (\Throwable $e) {
                return back()->with('error', $this->friendlyDbError($e->getMessage()));
            }
        }

        // Verify purchase code against License Server before storing (do not write to database yet)
        if ($step === 4) {
            $licenseResult = app(LicenseService::class)->verify($validated['purchase_code'], false);

            if (! $licenseResult->valid) {
                return back()->with('error', translate($licenseResult->error));
            }

            // Store license details for finalization
            $validated['license_result'] = [
                'type' => $licenseResult->type,
                'buyer' => $licenseResult->buyer,
                'purchase_date' => $licenseResult->purchaseDate,
                'supported_until' => $licenseResult->supportedUntil,
            ];
        }

        $wizard['data']["step_{$step}"] = $validated;
        $wizard['steps_completed'] = array_unique(
            array_merge($wizard['steps_completed'] ?? [], [$step])
        );
        $wizard['current_step'] = min($step + 1, self::TOTAL_STEPS);

        $request->session()->put(self::SESSION_KEY, $wizard);

        return redirect()->route('install');
    }

    /**
     * POST /install/goto-step/{step} — navigate backward.
     */
    public function gotoStep(Request $request, int $step): \Illuminate\Http\RedirectResponse
    {
        $wizard = $this->getWizardState($request);
        $completed = $wizard['steps_completed'] ?? [];

        // Allow going to any completed step or the next incomplete
        $maxAllowed = empty($completed) ? 1 : max($completed) + 1;
        $target = min($step, $maxAllowed);
        $target = max($target, 1);

        $wizard['current_step'] = $target;
        $request->session()->put(self::SESSION_KEY, $wizard);

        return redirect()->route('install');
    }

    /**
     * POST /install/finalize — commit everything.
     */
    public function finalize(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $wizard = $this->getWizardState($request);
        $data = $wizard['data'] ?? [];

        // Shared hosting frequently caps max_execution_time at ~30s, which can
        // kill the migration/seed run mid-way and leave a half-installed app.
        // Give finalize the room it needs (best-effort; silently ignored when the
        // host disables these via disable_functions).
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        // Ensure all 6 data steps are completed
        for ($i = 1; $i <= 6; $i++) {
            if (! isset($data["step_{$i}"])) {
                return back()->with('error', translate('Step :step is incomplete. Please complete all steps.', ['step' => $i]));
            }
        }

        // Re-run environment checks right before committing. Step 1 may have passed
        // hours ago (or on a different worker); bail out cleanly BEFORE writing the
        // database or .env if a hard requirement regressed, rather than half-installing.
        if (! app(SystemCheckService::class)->allPass()) {
            return back()->with('error', translate('Your server no longer meets all requirements. Please go back to the Requirements step and resolve the failures before finishing.'));
        }

        $envPath = base_path('.env');
        $debugLog = storage_path('logs/install-debug.log');
        $errors = [];
        $dbReady = false;

        // Raw debug logger — writes directly to file, no Laravel dependencies
        $debug = function (string $msg) use ($debugLog) {
            @file_put_contents($debugLog, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
        };

        $debug('=== Finalize started ===');

        // ─── Phase 0: Bootstrap .env file ───
        if (! file_exists($envPath)) {
            if (file_exists(base_path('.env.example'))) {
                copy(base_path('.env.example'), $envPath);
                $debug('Phase 0: Copied .env.example to .env');
            } else {
                $debug('Phase 0: FATAL — .env.example missing');

                return back()->with('error', translate('.env.example is missing from the application root.'));
            }
        }

        if (! is_writable($envPath)) {
            $debug('Phase 0: FATAL — .env not writable. Perms: ' . decoct(fileperms($envPath) & 0777));

            return back()->with('error', translate('The .env file is not writable. Please check file permissions (should be 644 or 664).'));
        }

        $debug('Phase 0: .env exists and is writable');

        // ─── Phase 1: Write DB + app config to .env and generate APP_KEY ───
        try {
            $envService = app(EnvFileService::class);
            $dbDriver = $data['step_2']['db_driver'] ?? 'mysql';

            $dbConfig = [
                'APP_NAME' => $data['step_3']['site_name'],
                'APP_URL' => rtrim($data['step_3']['site_url'], '/'),
                'DB_CONNECTION' => $dbDriver,
                'FILESYSTEM_DISK' => 'public',
                'PUBLIC_DISK_ROOT' => dirname(base_path()) . '/storage',
            ];

            if ($dbDriver === 'sqlite') {
                $dbConfig['DB_DATABASE'] = database_path('database.sqlite');
                if (! file_exists($dbConfig['DB_DATABASE'])) {
                    touch($dbConfig['DB_DATABASE']);
                    chmod($dbConfig['DB_DATABASE'], 0664);
                }
            } else {
                $dbConfig['DB_HOST'] = $data['step_2']['db_host'];
                $dbConfig['DB_PORT'] = (string) $data['step_2']['db_port'];
                $dbConfig['DB_DATABASE'] = $data['step_2']['db_database'];
                $dbConfig['DB_USERNAME'] = $data['step_2']['db_username'];
                $dbConfig['DB_PASSWORD'] = $data['step_2']['db_password'] ?? '';
            }

            $envService->writeMultiple($dbConfig);

            // Generate APP_KEY if missing or placeholder
            $currentKey = $envService->read('APP_KEY');
            if (empty($currentKey) || $currentKey === 'SomeRandomString') {
                $currentKey = 'base64:' . base64_encode(random_bytes(32));
                $envService->write('APP_KEY', $currentKey);
            }
            config(['app.key' => $currentKey]);

            // Re-bind encrypter so Crypt works immediately
            $cipher = config('app.cipher', 'AES-256-CBC');
            $keyData = base64_decode(substr($currentKey, 7));
            $encrypter = new \Illuminate\Encryption\Encrypter($keyData, $cipher);
            app()->instance('encrypter', $encrypter);

            $debug('Phase 1: OK — .env config written, APP_KEY set');
        } catch (\Throwable $e) {
            $debug('Phase 1: FATAL — ' . $e->getMessage());

            return back()->with('error', translate('Installation failed during configuration: :error', ['error' => $e->getMessage()]));
        }

        // ─── Phase 2: Connect DB and run migrations ───
        try {
            if ($dbDriver === 'mysql') {
                config([
                    'database.connections.mysql.host' => $data['step_2']['db_host'],
                    'database.connections.mysql.port' => $data['step_2']['db_port'],
                    'database.connections.mysql.database' => $data['step_2']['db_database'],
                    'database.connections.mysql.username' => $data['step_2']['db_username'],
                    'database.connections.mysql.password' => $data['step_2']['db_password'] ?? '',
                ]);
                DB::purge('mysql');
                DB::reconnect('mysql');
            } else {
                config([
                    'database.connections.sqlite.database' => $dbConfig['DB_DATABASE'],
                ]);
                DB::purge('sqlite');
                DB::reconnect('sqlite');
            }

            DB::connection($dbDriver)->getPdo();
            $debug('Phase 2: DB connected on driver: ' . $dbDriver);

            // Set default connection to $dbDriver BEFORE migrating so that direct DB:: facade
            // queries in migrations/seeders execute against the correct connection
            config(['database.default' => $dbDriver]);
            DB::purge($dbDriver);
            DB::reconnect($dbDriver);

            Artisan::call('migrate', [
                '--database' => $dbDriver,
                '--force' => true,
            ]);

            $dbReady = true;
            $debug('Phase 2: OK — migrations complete');
        } catch (\Throwable $e) {
            $debug('Phase 2: FAILED — ' . $e->getMessage());
            $debug('Phase 2: Trace — ' . $e->getTraceAsString());

            // Migrations failed. Do NOT mark the app installed and do NOT clear
            // the wizard — keep every entered value so the buyer can correct their
            // database settings and retry entirely from the browser. No CLI needed.
            return $this->migrationFailedResponse($request, $e);
        }

        // ─── Phase 3: WRITE INSTALLED=true ───
        // Only reached once migrations have succeeded. Uses raw file I/O for
        // reliability.
        try {
            clearstatcache(true, $envPath);
            $envContents = file_get_contents($envPath);

            if ($envContents === false) {
                throw new \RuntimeException('Could not read .env file at: ' . $envPath);
            }

            // Replace existing values using regex
            $replacements = [
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'INSTALLED' => 'true',
            ];

            foreach ($replacements as $key => $value) {
                $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
                if (preg_match($pattern, $envContents)) {
                    $envContents = preg_replace($pattern, $key . '=' . $value, $envContents);
                } else {
                    // Key doesn't exist — append it
                    $envContents = rtrim($envContents) . "\n" . $key . '=' . $value . "\n";
                }
            }

            $writeResult = file_put_contents($envPath, $envContents, LOCK_EX);

            if ($writeResult === false) {
                throw new \RuntimeException('file_put_contents returned false for: ' . $envPath);
            }

            // Verify by re-reading
            clearstatcache(true, $envPath);
            $verify = file_get_contents($envPath);
            if (strpos($verify, 'INSTALLED=true') === false) {
                throw new \RuntimeException('Verification failed — INSTALLED=true not found after write');
            }

            config([
                'app.env' => 'production',
                'app.debug' => false,
                'app.installed' => true,
            ]);

            $debug('Phase 3: OK — INSTALLED=true written and verified');
        } catch (\Throwable $e) {
            $debug('Phase 3: CRITICAL FAILURE — ' . $e->getMessage());
            // Last resort: this is the only phase where we return on failure
            return back()->with('error', translate('Installation failed: could not mark as installed. Error: :error', ['error' => $e->getMessage()]));
        }

        // ─── From here, INSTALLED=true is persisted. Non-fatal phases only. ───

        if ($dbReady) {
            // Re-assert default connection is set and active for Phase 4 to 6
            try {
                config(['database.default' => $dbDriver]);
                DB::purge($dbDriver);
                DB::reconnect($dbDriver);
                $debug('Phases 4-6: Re-asserted default database connection to: ' . $dbDriver);
            } catch (\Throwable $e) {
                $debug('Phases 4-6: Failed to re-assert connection: ' . $e->getMessage());
            }

            // ─── Phase 4: Seed database ───
            try {
                $debug('Phase 4: Seeding database via Database\\Seeders\\DatabaseSeeder on database: ' . $dbDriver);
                Artisan::call('db:seed', [
                    '--database' => $dbDriver,
                    '--class' => 'Database\\Seeders\\DatabaseSeeder',
                    '--force' => true,
                ]);
                $debug('Phase 4: DatabaseSeeder output: ' . Artisan::output());

                // Update or create admin account
                $admin = Admin::where('email', 'admin@makeai.com')->first();
                $debug('Phase 4: Admin check - ' . ($admin ? 'Found admin@makeai.com' : 'Not found admin@makeai.com'));

                if ($admin) {
                    $admin->update([
                        'name' => $data['step_5']['admin_name'],
                        'email' => $data['step_5']['admin_email'],
                        'password' => $data['step_5']['admin_password'],
                        'must_change_password' => false,
                    ]);
                    $debug('Phase 4: Admin updated successfully.');
                } else {
                    $roleId = AdminRole::where('slug', 'super-admin')->value('id') ?? 1;

                    Admin::create([
                        'name' => $data['step_5']['admin_name'],
                        'email' => $data['step_5']['admin_email'],
                        'password' => $data['step_5']['admin_password'],
                        'role_id' => $roleId,
                        'is_active' => true,
                        'must_change_password' => false,
                    ]);
                    $debug('Phase 4: Admin created successfully (no default admin found).');
                }

                $debug('Phase 4: OK — seeders run, admin created');
            } catch (\Throwable $e) {
                $errors[] = 'Seeding: ' . $e->getMessage();
                $debug('Phase 4: FAILED — ' . $e->getMessage());
                $debug('Phase 4: Trace — ' . $e->getTraceAsString());
            }

            // ─── Phase 5: Store site settings + license ───
            try {
                settings_set('site_name', $data['step_3']['site_name'], 'string', 'general');
                settings_set('site_url', rtrim($data['step_3']['site_url'], '/'), 'string', 'general');

                $license = $data['step_4']['license_result'] ?? null;

                if ($license) {
                    settings_set('license_purchase_code', $data['step_4']['purchase_code'], 'encrypted', 'license');
                    settings_set('license_type', $license['type'], 'integer', 'license');
                    settings_set('license_buyer', $license['buyer'], 'string', 'license');
                    settings_set('license_purchased_at', $license['purchase_date'], 'string', 'license');
                    settings_set('license_supported_until', $license['supported_until'] ?? '', 'string', 'license');
                    settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
                    settings_set('license_domain', request()->getHost(), 'string', 'license');
                    settings_set('license_status', 'valid', 'string', 'license');
                    settings_set('license_grace_started_at', '', 'string', 'license');

                    \Illuminate\Support\Facades\Cache::forget('license.status');
                }

                $debug('Phase 5: OK — settings and license stored');
            } catch (\Throwable $e) {
                $errors[] = 'Settings: ' . $e->getMessage();
                $debug('Phase 5: FAILED — ' . $e->getMessage());
            }

            // ─── Phase 6: Demo content ───
            try {
                if ($data['step_6']['install_demo'] ?? false) {
                    $this->importDemo($data['step_6']);
                    $debug('Phase 6: OK — demo content imported');
                }
            } catch (\Throwable $e) {
                $errors[] = 'Demo: ' . $e->getMessage();
                $debug('Phase 6: FAILED — ' . $e->getMessage());
            }
        } else {
            $debug('Phases 4-6: SKIPPED — database not ready');
        }

        // ─── Phase 7: Clear caches and cleanup ───
        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
        } catch (\Throwable $e) {
            $debug('Phase 7: Cache clear failed — ' . $e->getMessage());
        }

        $request->session()->forget(self::SESSION_KEY);

        $debug('=== Finalize completed. DB ready: ' . ($dbReady ? 'YES' : 'NO') . '. Errors: ' . count($errors) . ' ===');

        // Migration failure is handled earlier (Phase 2) by bailing out before the
        // app is ever marked installed, so reaching here means the database is ready.

        $successMessage = translate('Installation complete! You may now log in as the admin user.');
        if (! empty($errors)) {
            $successMessage .= ' ' . translate('Some non-critical steps had warnings — check storage/logs/install-debug.log for details.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'redirect' => route('admin.login'),
            ]);
        }

        return redirect()->route('admin.login')->with('success', $successMessage);
    }

    // ─── Private helpers ────────────────────────────────────────────────

    private function getWizardState(Request $request): array
    {
        $default = [
            'current_step' => 1,
            'steps_completed' => [],
            'data' => [],
        ];

        return $request->session()->get(self::SESSION_KEY, $default);
    }

    private function validateStep(Request $request, int $step): array
    {
        return match ($step) {
            1 => $request->validate([
                'confirmed' => ['required', 'accepted'],
            ]),
            2 => $request->validate([
                'db_driver' => ['required', 'in:mysql,sqlite'],
                // Host/name/user are required for MySQL, ignored for SQLite.
                'db_host' => ['required_if:db_driver,mysql', 'nullable', 'string', 'max:255'],
                'db_port' => ['required_if:db_driver,mysql', 'nullable', 'integer', 'between:1,65535'],
                'db_database' => ['required_if:db_driver,mysql', 'nullable', 'string', 'max:255'],
                'db_username' => ['required_if:db_driver,mysql', 'nullable', 'string', 'max:255'],
                'db_password' => ['nullable', 'string', 'max:255'],
            ], [
                'db_host.required_if' => translate('The database host is required (most shared hosts use "localhost").'),
                'db_port.required_if' => translate('The database port is required (MySQL default is 3306).'),
                'db_database.required_if' => translate('The database name is required.'),
                'db_username.required_if' => translate('The database username is required.'),
            ]),
            3 => $request->validate([
                'site_name' => ['required', 'string', 'max:255'],
                'site_url' => ['required', 'string', 'max:255'],
            ]),
            4 => $request->validate([
                'purchase_code' => ['required', 'string', 'max:255'],
            ]),
            5 => $request->validate([
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'email', 'max:255'],
                'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            ]),
            6 => $request->validate([
                'install_demo' => ['required', 'boolean'],
                'demo_method' => ['nullable', 'in:file,upload'],
                'demo_file' => ['nullable', 'file', 'mimetypes:text/plain,application/sql', 'max:51200'],
            ]),
            default => throw new \InvalidArgumentException("Invalid step: {$step}"),
        };
    }

    private function testDatabaseConnection(array $db): void
    {
        if (($db['db_driver'] ?? 'mysql') !== 'mysql') {
            return;
        }

        $original = config('database.connections.mysql');

        config([
            'database.connections._install_test' => array_merge($original, [
                'host' => $db['db_host'] ?? '127.0.0.1',
                'port' => $db['db_port'] ?? '3306',
                'database' => $db['db_database'] ?? '',
                'username' => $db['db_username'] ?? '',
                'password' => $db['db_password'] ?? '',
            ]),
        ]);

        try {
            $connection = DB::connection('_install_test');
            $connection->getPdo();

            // Connectivity alone isn't enough — verify the user can actually
            // create and drop tables. Missing privileges are the most common
            // reason migrations fail deep inside finalize on shared hosting, and
            // catching it here lets the buyer fix it while still on this step.
            $probe = 'install_privilege_check_' . substr(md5(uniqid('', true)), 0, 8);
            $connection->statement("CREATE TABLE `{$probe}` (`id` INT)");
            $connection->statement("DROP TABLE `{$probe}`");
        } finally {
            DB::purge('_install_test');
        }
    }

    /**
     * Turn a raw database exception message into buyer-friendly, actionable
     * guidance so non-technical Envato buyers can self-serve without the CLI.
     */
    private function friendlyDbError(string $raw): string
    {
        $lower = strtolower($raw);

        return match (true) {
            str_contains($lower, 'access denied') => translate(
                'Database access was denied. Go back to the Database step and double-check the database username and password.'
            ),
            str_contains($lower, 'unknown database') => translate(
                'That database name does not exist yet. Create it in your hosting panel (e.g. cPanel → MySQL Databases), then go back and re-enter the name.'
            ),
            (str_contains($lower, 'command denied') || str_contains($lower, 'create command denied') || str_contains($lower, 'denied to user')) => translate(
                'The database user is missing required privileges. In your hosting panel, grant it ALL PRIVILEGES on this database, then try again.'
            ),
            (str_contains($lower, 'connection refused') || str_contains($lower, "can't connect") || str_contains($lower, 'could not connect') || str_contains($lower, 'timed out') || str_contains($lower, 'no such host') || str_contains($lower, 'getaddrinfo')) => translate(
                'Could not reach the database server. Check the database host and port on the Database step — most shared hosts use "localhost".'
            ),
            default => translate('Database setup failed: :error. Please review your Database settings and try again.', [
                'error' => \Illuminate\Support\Str::limit($raw, 200),
            ]),
        };
    }

    /**
     * Response for a failed migration: the app is left UN-installed and the wizard
     * state is preserved, so the buyer can correct their settings and retry from
     * the browser. Never mentions the command line.
     */
    private function migrationFailedResponse(Request $request, \Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        $message = $this->friendlyDbError($e->getMessage());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 200);
        }

        return redirect()->route('install')->with('error', $message);
    }

    private function importDemo(array $step6Data): void
    {
        $method = $step6Data['demo_method'] ?? 'file';

        if ($method === 'upload') {
            // The file was moved to disk during step 6 (finalize is a separate request).
            $path = $step6Data['demo_file_path'] ?? null;
            if ($path && file_exists($path)) {
                DB::unprepared(file_get_contents($path));
                @unlink($path);
            }

            return;
        }

        $demoPath = database_path('demo/demo.sql');
        if (file_exists($demoPath)) {
            DB::unprepared(file_get_contents($demoPath));
        }
    }
}
