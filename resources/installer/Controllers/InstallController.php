<?php

namespace Installer\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\LicenseService;
use Installer\Services\EnvFileService;
use Installer\Services\SystemCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InstallController extends Controller
{
    private const SESSION_KEY = 'install_wizard';
    private const TOTAL_STEPS = 6;

    /** Placeholder sent to the frontend in place of the real admin password. */
    private const PASSWORD_MASK = '••••••••';

    /**
     * GET /install — render the wizard at its current step.
     */
    public function index(Request $request): \Inertia\Response
    {
        $wizard = $this->getWizardState($request);
        $step = (int) ($wizard['current_step'] ?? 1);

        // Never expose the plaintext admin password to the browser. The review
        // step only needs to know one was set, so send a fixed mask instead.
        $formData = $wizard['data'] ?? [];
        if (! empty($formData['step_5']['admin_password'])) {
            $formData['step_5']['admin_password'] = self::PASSWORD_MASK;
        }

        $props = [
            'currentStep' => $step,
            'totalSteps' => self::TOTAL_STEPS,
            'stepsCompleted' => $wizard['steps_completed'] ?? [],
            'formData' => $formData,
        ];

        if ($step === 1) {
            $props['systemCheck'] = app(SystemCheckService::class)->check();
            $props['allPass'] = app(SystemCheckService::class)->allPass();
        }

        if ($step === 3) {
            // Flashed by storeStep() when it refuses a populated database, so the
            // Database step can reveal the reset checkbox even if the buyer never
            // clicked "Test Connection". Null on a normal visit.
            $props['dbState'] = $request->session()->get('db_state');
        }

        if ($step === 4) {
            // The Site Setup step preselects the browser's zone; this is the list it is
            // validated against and the one the buyer picks from if the guess is wrong.
            $props['timezones'] = timezone_identifiers_list();
        }

        if ($step === self::TOTAL_STEPS) {
            // Scheduled tasks (subscription renewals, usage resets, queued mail)
            // never run without this, and the buyer is redirected straight to the
            // login screen after installing — so the review step is the last
            // chance to show it, with the real absolute path already filled in.
            $props['cronCommand'] = sprintf(
                '* * * * * cd %s && php artisan schedule:run >> /dev/null 2>&1',
                base_path()
            );
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

        // Test DB connection + write privileges for the Database step before storing
        if ($step === 3) {
            try {
                $this->testDatabaseConnection($validated);
            } catch (\Throwable $e) {
                return back()->with('error', $this->friendlyDbError($e->getMessage()));
            }

            // A populated target database cannot be safely migrated over: a plain
            // re-install skips already-run migrations and then collides on the
            // seed data / stale admin, leaving a half-overwritten install. Require
            // the buyer to explicitly confirm a destructive reset (the checkbox on
            // the Database step) before we let them continue. Re-flash the detected
            // state so the step can reveal that checkbox even when the buyer never
            // clicked "Test Connection".
            $state = $this->probeDatabaseState($validated);
            if ($state !== 'empty' && empty($validated['db_reset'])) {
                return back()
                    ->with('error', $this->populatedDbMessage($state))
                    ->with('db_state', $state);
            }
        }

        // Verify purchase code against License Server before storing (do not write to database yet)
        if ($step === 2) {
            $licenseResult = app(LicenseService::class)->verify($validated['purchase_code'], false);

            if (! $licenseResult->valid) {
                return back()->with('error', $licenseResult->error);
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

        // Ensure all 5 data steps are completed (step 6 is the review/install screen)
        for ($i = 1; $i <= 5; $i++) {
            if (! isset($data["step_{$i}"])) {
                return back()->with('error', 'Step ' . $i . ' is incomplete. Please complete all steps.');
            }
        }

        // Re-run environment checks right before committing. Step 1 may have passed
        // hours ago (or on a different worker); bail out cleanly BEFORE writing the
        // database or .env if a hard requirement regressed, rather than half-installing.
        if (! app(SystemCheckService::class)->allPass()) {
            return back()->with('error', 'Your server no longer meets all requirements. Please go back to the Requirements step and resolve the failures before finishing.');
        }

        $envPath = base_path('.env');
        $errors = [];
        $dbReady = false;

        // ─── Phase 0: Bootstrap .env file ───
        if (! file_exists($envPath)) {
            if (file_exists(base_path('.env.example'))) {
                copy(base_path('.env.example'), $envPath);
            } else {
                return back()->with('error', '.env.example is missing from the application root.');
            }
        }

        if (! is_writable($envPath)) {
            return back()->with('error', 'The .env file is not writable. Please check file permissions (should be 644 or 664).');
        }

        // ─── Phase 1: Write DB + app config to .env and generate APP_KEY ───
        try {
            $envService = app(EnvFileService::class);
            $dbDriver = $data['step_3']['db_driver'] ?? 'mysql';

            $dbConfig = [
                'APP_NAME' => $data['step_4']['site_name'],
                'APP_URL' => rtrim($data['step_4']['site_url'], '/'),
                'DB_CONNECTION' => $dbDriver,
                'FILESYSTEM_DISK' => 'public',
                // Media root must live INSIDE the served webroot so APP_URL/storage/... resolves
                // without a `storage:link` symlink (shared hosting often forbids symlinks).
                // public_path() is already layout-aware: bootstrap/app.php rebinds it to the real
                // webroot for the distribution package (app in a subfolder, index.php at the root),
                // and returns <app>/public for a standard Laravel layout. Deriving the root from it
                // is therefore correct in BOTH layouts — whereas dirname(base_path()).'/storage'
                // pointed at a sibling directory that is not web-served on a standard layout, so
                // every uploaded file 404'd.
                'PUBLIC_DISK_ROOT' => public_path('storage'),
            ];

            $dbConfig['DB_HOST'] = $data['step_3']['db_host'];
            $dbConfig['DB_PORT'] = (string) $data['step_3']['db_port'];
            $dbConfig['DB_DATABASE'] = $data['step_3']['db_database'];
            $dbConfig['DB_USERNAME'] = $data['step_3']['db_username'];
            $dbConfig['DB_PASSWORD'] = $data['step_3']['db_password'] ?? '';

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
        } catch (\Throwable $e) {
            return back()->with('error', 'Installation failed during configuration: ' . $e->getMessage());
        }

        // ─── Phase 2: Connect DB and run migrations ───
        try {
            // MySQL and MariaDB share identical connection params; write into
            // whichever connection the buyer picked.
            config([
                "database.connections.{$dbDriver}.host" => $data['step_3']['db_host'],
                "database.connections.{$dbDriver}.port" => $data['step_3']['db_port'],
                "database.connections.{$dbDriver}.database" => $data['step_3']['db_database'],
                "database.connections.{$dbDriver}.username" => $data['step_3']['db_username'],
                "database.connections.{$dbDriver}.password" => $data['step_3']['db_password'] ?? '',
            ]);
            DB::purge($dbDriver);
            DB::reconnect($dbDriver);

            DB::connection($dbDriver)->getPdo();

            // Set default connection to $dbDriver BEFORE migrating so that direct DB:: facade
            // queries in migrations/seeders execute against the correct connection
            config(['database.default' => $dbDriver]);
            DB::purge($dbDriver);
            DB::reconnect($dbDriver);

            // Decide migrate vs migrate:fresh from the target DB's ACTUAL state,
            // not just the checkbox — the buyer could have pointed at a different
            // database since the Database step.
            $resetRequested = (bool) ($data['step_3']['db_reset'] ?? false);
            $dbState = $this->probeDatabaseState($data['step_3']);

            if ($dbState !== 'empty' && ! $resetRequested) {
                // Populated database, no reset confirmation. Refuse rather than run
                // a plain migrate that would skip migrations and half-overwrite the
                // install. INSTALLED is still false at this point, so the buyer can
                // go back, tick the reset option (or use an empty DB), and retry.
                $message = $this->populatedDbMessage($dbState);

                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $message], 200)
                    : redirect()->route('install')->with('error', $message);
            }

            if ($dbState !== 'empty') {
                // Buyer confirmed a destructive reset: drop every table (and view)
                // then re-run all migrations, so data.sql imports into a clean
                // schema and the admin reconciliation in Phase 4 matches the
                // freshly-seeded default account instead of a renamed stale one.
                Artisan::call('migrate:fresh', [
                    '--database' => $dbDriver,
                    '--force' => true,
                ]);
            } else {
                Artisan::call('migrate', [
                    '--database' => $dbDriver,
                    '--force' => true,
                ]);
            }

            $dbReady = true;
        } catch (\Throwable $e) {
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

            // Harden the session cookie for production, but only when the install is
            // actually running over HTTPS — forcing Secure on a plain-HTTP install
            // would stop the session cookie being sent and lock the buyer out.
            $secureCookie = (request()->secure()
                || str_starts_with(strtolower((string) config('app.url')), 'https://'))
                ? 'true'
                : 'null';

            // Replace existing values using regex
            $replacements = [
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'INSTALLED' => 'true',
                'SESSION_SECURE_COOKIE' => $secureCookie,
                // The packaged index.php boots a fresh install on file sessions,
                // because no database exists yet at that point. Now that migrations
                // have created the sessions table, hand sessions back to the
                // database driver the product expects — the "active sessions" list
                // and remote sign-out (PrivacyController, admin user management)
                // read that table directly and show nothing without it.
                //
                // Deliberately here in Phase 3 rather than Phase 1: on a migration
                // failure the buyer must keep a working wizard to retry in, and
                // pointing sessions at a table that was never created would replace
                // it with a 500.
                'SESSION_DRIVER' => 'database',
            ];

            // If a Redis server is actually reachable, use it for the CACHE — a
            // free performance win on hosts that have it, and it stops the health
            // page nagging about it. Detected by a real connection, not a guess, so
            // a host without Redis is simply left on the default cache and never
            // pointed at a server that is not there.
            //
            // Sessions are deliberately NOT switched to Redis: the "active sessions
            // / sign out other devices" feature reads the sessions table, which
            // Redis sessions never populate, so that stays on the database driver.
            if ($this->redisIsReachable()) {
                $replacements['CACHE_STORE'] = 'redis';
                // predis is bundled, so Redis works even without the phpredis
                // extension; only prefer phpredis when it is actually installed.
                $replacements['REDIS_CLIENT'] = extension_loaded('redis') ? 'phpredis' : 'predis';
            }

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

        } catch (\Throwable $e) {
            // Last resort: this is the only phase where we return on failure
            return back()->with('error', 'Installation failed: could not mark as installed. Error: ' . $e->getMessage());
        }

        // ─── From here, INSTALLED=true is persisted. Non-fatal phases only. ───

        if ($dbReady) {
            // Re-assert default connection is set and active for Phases 4-5
            try {
                config(['database.default' => $dbDriver]);
                DB::purge($dbDriver);
                DB::reconnect($dbDriver);
            } catch (\Throwable $e) {
                // non-fatal
            }

            // ─── Phase 4: Bootstrap essential data ───
            // The distribution ships database/data/data.sql — a data-only export of
            // the fully seeded database (settings, tools, pages, mail templates, …) —
            // but NOT the real seeder classes. That import is the only data source in
            // a shipped package; the seeder branch below exists purely for development
            // checkouts, where the full database/seeders set is present and data.sql
            // may not be. It is gated on FoundationSeeder, not DatabaseSeeder: the
            // package ships a reference DatabaseSeeder stub, and seeding with that
            // alone would leave the buyer with an empty but "successful" install.
            try {
                $dataPath = database_path('data/data.sql');

                if (in_array($dbDriver, ['mysql', 'mariadb'], true) && is_file($dataPath)) {
                    $this->importDataBootstrap($dataPath);
                } elseif (class_exists(\Database\Seeders\FoundationSeeder::class)) {
                    Artisan::call('db:seed', [
                        '--database' => $dbDriver,
                        '--class' => 'Database\\Seeders\\DatabaseSeeder',
                        '--force' => true,
                    ]);
                } else {
                    throw new \RuntimeException('No data source available: database/data/data.sql is missing and the database seeders are not present.');
                }

                // Update or create admin account
                $admin = Admin::where('email', 'admin@makeai.com')->first();

                if ($admin) {
                    $admin->update([
                        'name' => $data['step_5']['admin_name'],
                        'email' => $data['step_5']['admin_email'],
                        'password' => $data['step_5']['admin_password'],
                    ]);
                } else {
                    $roleId = AdminRole::where('slug', 'super-admin')->value('id') ?? 1;

                    Admin::create([
                        'name' => $data['step_5']['admin_name'],
                        'email' => $data['step_5']['admin_email'],
                        'password' => $data['step_5']['admin_password'],
                        'role_id' => $roleId,
                        'is_active' => true,
                    ]);
                }
            } catch (\Throwable $e) {
                $errors[] = 'Data bootstrap: ' . $e->getMessage();
            }

            // ─── Phase 5: Store site settings + license ───
            try {
                settings_set('site_name', $data['step_4']['site_name'], 'string', 'general');
                settings_set('site_url', rtrim($data['step_4']['site_url'], '/'), 'string', 'general');
                settings_set('site_tagline', $data['step_4']['site_tagline'] ?? '', 'string', 'general');

                // Timestamps are stored in UTC and rendered through display_tz(), which
                // reads this setting — so without it a fresh install shows every date in
                // UTC and looks hours out to anyone not sitting in it. The browser's zone
                // is the best guess available during an install; anything unrecognised
                // (old browser, hand-edited request) falls back to the UTC default rather
                // than writing a zone PHP cannot resolve.
                $detectedZone = (string) ($data['step_4']['site_timezone'] ?? '');
                settings_set(
                    'app_timezone',
                    in_array($detectedZone, timezone_identifiers_list(), true) ? $detectedZone : 'UTC',
                    'string',
                    'general'
                );

                $license = $data['step_2']['license_result'] ?? null;

                if ($license) {
                    settings_set('license_purchase_code', $data['step_2']['purchase_code'], 'encrypted', 'license');
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

            } catch (\Throwable $e) {
                $errors[] = 'Settings: ' . $e->getMessage();
            }
        }

        // ─── Phase 6: Clear caches and cleanup ───
        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
        } catch (\Throwable $e) {
            // non-fatal
        }

        $request->session()->forget(self::SESSION_KEY);

        // Migration failure is handled earlier (Phase 2) by bailing out before the
        // app is ever marked installed, so reaching here means the database is ready.

        $successMessage = 'Installation complete! You may now log in as the admin user.';
        if (! empty($errors)) {
            $successMessage .= ' Some non-critical steps reported warnings.';
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

    /**
     * POST /install/test-database — probe the database connection for the
     * Database step's "Test Connection" button. Unlike storeStep(), this NEVER
     * writes to the wizard session or advances the step; it only validates the
     * inputs and attempts a real connection, returning a JSON verdict.
     */
    public function testDatabase(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'db_driver' => ['required', 'in:mysql,mariadb'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->testDatabaseConnection($validated);
        } catch (\Throwable $e) {
            return response()->json([
                'pass' => false,
                'message' => $this->friendlyDbError($e->getMessage()),
            ]);
        }

        // Connection proved — also report whether the database is empty, a prior
        // install of this app, or populated by something else, so the step can
        // reveal the reset confirmation before the buyer advances. Non-fatal: a
        // probe failure never turns a good connection into a failed one.
        $dbState = 'unknown';
        try {
            $dbState = $this->probeDatabaseState($validated);
        } catch (\Throwable) {
            // leave as 'unknown'
        }

        return response()->json([
            'pass' => true,
            'message' => 'Connection successful!',
            'dbState' => $dbState,
        ]);
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
                'purchase_code' => ['required', 'string', 'max:255'],
            ]),
            // SQLite is deliberately not offered: the installer bootstraps a fresh
            // install from database/data/data.sql, which is a mysqldump, and the
            // seeder classes that used to cover the SQLite path are no longer
            // shipped. Offering a driver with no data source would fail the buyer
            // after migrations had already run.
            3 => $request->validate([
                'db_driver' => ['required', 'in:mysql,mariadb'],
                'db_host' => ['required', 'string', 'max:255'],
                'db_port' => ['required', 'integer', 'between:1,65535'],
                'db_database' => ['required', 'string', 'max:255'],
                'db_username' => ['required', 'string', 'max:255'],
                'db_password' => ['nullable', 'string', 'max:255'],
                // Buyer's explicit consent to wipe a non-empty target database
                // before installing. Only honoured when the DB actually has data.
                'db_reset' => ['sometimes', 'boolean'],
            ], [
                'db_host.required' => 'The database host is required (most shared hosts use "localhost").',
                'db_port.required' => 'The database port is required (MySQL/MariaDB default is 3306).',
                'db_database.required' => 'The database name is required.',
                'db_username.required' => 'The database username is required.',
            ]),
            4 => $request->validate([
                'site_name' => ['required', 'string', 'max:255'],
                'site_url' => ['required', 'string', 'max:255'],
                'site_tagline' => ['nullable', 'string', 'max:255'],
                // Optional, and never fatal: the browser sends its detected zone, but an
                // old browser or a spoofed value must not be able to fail an install that
                // is otherwise fine. Anything not in the PHP zone list falls back to UTC
                // when the setting is written.
                'site_timezone' => ['nullable', 'string', 'max:64'],
            ]),
            5 => $request->validate([
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'email', 'max:255'],
                'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            ]),
            default => throw new \InvalidArgumentException("Invalid step: {$step}"),
        };
    }

    private function testDatabaseConnection(array $db): void
    {
        $driver = $db['db_driver'] ?? 'mysql';

        // Inherit from the chosen connection so `_install_test` uses the correct
        // driver (mysql vs mariadb) and its default options.
        $original = config("database.connections.{$driver}");

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
     * Classify the target database so the installer never silently overwrites
     * real data. Returns one of:
     *   'empty'   — no tables; safe to migrate straight in.
     *   'app'     — has a `migrations` table, i.e. a prior install of this app.
     *   'foreign' — has tables but no `migrations` table (another application).
     *
     * MySQL/MariaDB are the only offered drivers, so `SHOW TABLES` is a reliable
     * table census. Uses a throwaway `_install_probe` connection built from the
     * buyer's credentials and purges it afterwards, exactly like the connection
     * test, so it never disturbs the app's default connection.
     */
    private function probeDatabaseState(array $db): string
    {
        $driver = $db['db_driver'] ?? 'mysql';
        $original = config("database.connections.{$driver}");

        config([
            'database.connections._install_probe' => array_merge((array) $original, [
                'host' => $db['db_host'] ?? '127.0.0.1',
                'port' => $db['db_port'] ?? '3306',
                'database' => $db['db_database'] ?? '',
                'username' => $db['db_username'] ?? '',
                'password' => $db['db_password'] ?? '',
            ]),
        ]);

        try {
            $connection = DB::connection('_install_probe');

            if (count($connection->select('SHOW TABLES')) === 0) {
                return 'empty';
            }

            return $connection->getSchemaBuilder()->hasTable('migrations') ? 'app' : 'foreign';
        } finally {
            DB::purge('_install_probe');
        }
    }

    /**
     * Buyer-facing explanation of why a non-empty database was refused, tailored
     * to whether it looks like a prior install of this app or a foreign database.
     */
    private function populatedDbMessage(string $state): string
    {
        return $state === 'app'
            ? 'A previous installation was found in this database. Tick "Reset the database and reinstall" on the Database step to erase it and continue — or point the installer at a new, empty database.'
            : 'This database already contains tables from another application. Use a new, empty database, or tick "Reset the database and reinstall" on the Database step to erase everything it contains.';
    }

    /**
     * Turn a raw database exception message into buyer-friendly, actionable
     * guidance so non-technical Envato buyers can self-serve without the CLI.
     */
    private function friendlyDbError(string $raw): string
    {
        $lower = strtolower($raw);

        return match (true) {
            str_contains($lower, 'access denied') =>
                'Database access was denied. Go back to the Database step and double-check the database username and password.',
            str_contains($lower, 'unknown database') =>
                'That database name does not exist yet. Create it in your hosting panel (e.g. cPanel → MySQL Databases), then go back and re-enter the name.',
            (str_contains($lower, 'command denied') || str_contains($lower, 'create command denied') || str_contains($lower, 'denied to user')) =>
                'The database user is missing required privileges. In your hosting panel, grant it ALL PRIVILEGES on this database, then try again.',
            (str_contains($lower, 'connection refused') || str_contains($lower, "can't connect") || str_contains($lower, 'could not connect') || str_contains($lower, 'timed out') || str_contains($lower, 'no such host') || str_contains($lower, 'getaddrinfo')) =>
                'Could not reach the database server. Check the database host and port on the Database step — most shared hosts use "localhost".',
            (str_contains($lower, 'mysql_native_password') || (str_contains($lower, 'plugin') && str_contains($lower, 'not loaded'))) =>
                'Your database user uses an older password type (mysql_native_password) that your MySQL server no longer enables. In your hosting panel, recreate the database user or reset its password so it uses the current authentication method, or ask your hosting provider to enable it — then try again.',
            default => 'Database setup failed: ' . \Illuminate\Support\Str::limit($raw, 200) . '. Please review your Database settings and try again.',
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

    /**
     * Import the bundled data-only bootstrap (database/data/data.sql) produced by
     * `php artisan installer:export-data`. Runs the whole dump in one shot, wrapped
     * in an FK-checks guard so parent/child insert order can never fail the import.
     */
    /**
     * Is a Redis server actually reachable with the default connection details?
     *
     * A raw socket PING rather than the Redis facade: no extension is assumed, no
     * package needs booting, and a wrong host simply fails fast (1s timeout)
     * instead of throwing deep in a driver. Handles a password if REDIS_PASSWORD
     * is set; without one it tests the common localhost, no-auth setup.
     */
    private function redisIsReachable(): bool
    {
        $host = env('REDIS_HOST', '127.0.0.1');
        $port = (int) env('REDIS_PORT', 6379);
        $password = env('REDIS_PASSWORD');

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, 1.0);

        if (! $fp) {
            return false;
        }

        try {
            stream_set_timeout($fp, 1);

            if (filled($password)) {
                fwrite($fp, "AUTH {$password}\r\n");
                $auth = fgets($fp, 64);
                if (! is_string($auth) || $auth === '' || $auth[0] !== '+') {
                    return false;
                }
            }

            fwrite($fp, "PING\r\n");
            $pong = fgets($fp, 64);

            return is_string($pong) && str_starts_with($pong, '+PONG');
        } catch (\Throwable) {
            return false;
        } finally {
            fclose($fp);
        }
    }

    private function importDataBootstrap(string $path): void
    {
        $sql = file_get_contents($path);

        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException('The data file is empty or unreadable: ' . $path);
        }

        DB::unprepared("SET FOREIGN_KEY_CHECKS=0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS=1;");
    }
}
