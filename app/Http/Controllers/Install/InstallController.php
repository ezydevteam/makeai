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

        // Test DB connection for step 2 before storing (skip for SQLite)
        if ($step === 2 && ($validated['db_driver'] ?? 'mysql') === 'mysql') {
            try {
                $this->testDatabaseConnection($validated);
            } catch (\Exception $e) {
                return back()->with('error', translate('Database connection failed: :error', ['error' => $e->getMessage()]));
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
    public function finalize(Request $request): \Illuminate\Http\RedirectResponse
    {
        $wizard = $this->getWizardState($request);
        $data = $wizard['data'] ?? [];

        // Ensure all 6 data steps are completed
        for ($i = 1; $i <= 6; $i++) {
            if (! isset($data["step_{$i}"])) {
                return back()->with('error', translate('Step :step is incomplete. Please complete all steps.', ['step' => $i]));
            }
        }

        $envService = app(EnvFileService::class);
        $dbDriver = $data['step_2']['db_driver'] ?? 'mysql';

        // ─── 1. Prepare database configuration ───
        $dbConfig = [
            'APP_NAME' => $data['step_3']['site_name'],
            'APP_URL' => rtrim($data['step_3']['site_url'], '/'),
            'DB_CONNECTION' => $dbDriver,
        ];

        if ($dbDriver === 'sqlite') {
            $dbConfig['DB_DATABASE'] = database_path('database.sqlite');
            // Create SQLite file if it doesn't exist
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

        // ─── 2. Write configuration to .env ───
        $envService->writeMultiple($dbConfig);

        // ─── 3. Generate APP_KEY if missing or default ───
        $currentKey = $envService->read('APP_KEY');
        if (empty($currentKey) || $currentKey === 'SomeRandomString') {
            $key = 'base64:' . base64_encode(random_bytes(32));
            $envService->write('APP_KEY', $key);
            config(['app.key' => $key]);
        }

        // ─── 4. Reconnect with new DB config ───
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
            $connection = 'mysql';
        } else {
            config([
                'database.connections.sqlite.database' => $dbConfig['DB_DATABASE'],
            ]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');
            $connection = 'sqlite';
        }

        try {
            DB::connection($connection)->getPdo();
        } catch (\Exception $e) {
            Log::error('InstallController: DB connection failed during finalize.', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', translate('Database connection failed: :error', ['error' => $e->getMessage()]));
        }

        // ─── 5. Run migrations ───
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Exception $e) {
            Log::error('InstallController: Migrations failed.', ['error' => $e->getMessage()]);

            return back()->with('error', translate('Database migrations failed. Check the logs for details.'));
        }

        // ─── 6. Seed roles + permissions + default admin + foundation settings ───
        try {
            Artisan::call('db:seed', [
                '--class' => 'AdminSeeder',
                '--force' => true,
            ]);
            Artisan::call('db:seed', [
                '--class' => 'FoundationSeeder',
                '--force' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('InstallController: Seeders failed.', ['error' => $e->getMessage()]);

            return back()->with('error', translate('Failed to create admin roles and settings.'));
        }

        // Update the seeded default admin account with the user's credentials
        $admin = Admin::where('email', 'admin@makeai.com')->first();

        if ($admin) {
            $admin->update([
                'name' => $data['step_5']['admin_name'],
                'email' => $data['step_5']['admin_email'],
                'password' => $data['step_5']['admin_password'],
                'must_change_password' => false,
            ]);
        } else {
            // Fallback: create from scratch and assign super-admin role (id=1)
            $roleId = AdminRole::where('slug', 'super-admin')->value('id') ?? 1;

            Admin::create([
                'name' => $data['step_5']['admin_name'],
                'email' => $data['step_5']['admin_email'],
                'password' => $data['step_5']['admin_password'],
                'role_id' => $roleId,
                'is_active' => true,
                'must_change_password' => false,
            ]);
        }

        // ─── 7. Store initial site settings ───
        settings_set('site_name', $data['step_3']['site_name'], 'string', 'general');
        settings_set('site_url', rtrim($data['step_3']['site_url'], '/'), 'string', 'general');

        // ─── 8. Store license (already verified in step 4) ───
        $license = $data['step_4']['license_result'] ?? null;

        if ($license) {
            settings_set('license_purchase_code', \Illuminate\Support\Facades\Crypt::encryptString($data['step_4']['purchase_code']), 'encrypted', 'license');
            settings_set('license_type', $license['type'], 'integer', 'license');
            settings_set('license_buyer', $license['buyer'], 'string', 'license');
            settings_set('license_purchased_at', $license['purchase_date'], 'string', 'license');
            settings_set('license_supported_until', $license['supported_until'] ?? null, 'string', 'license');
            settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
            settings_set('license_domain', request()->getHost(), 'string', 'license');
            settings_set('license_status', 'valid', 'string', 'license');
            settings_set('license_grace_started_at', null, 'string', 'license');

            \Illuminate\Support\Facades\Cache::forget('license.status');
        }

        // ─── 9. Import demo content (optional) ───
        if ($data['step_6']['install_demo'] ?? false) {
            $this->importDemo($data['step_6']);
        }

        // ─── 10. Mark installation complete ───
        $envService->write('INSTALLED', 'true');

        // ─── 11. Cache configuration and routes ───
        Artisan::call('config:cache');
        Artisan::call('route:cache');

        // ─── 12. Cleanup session ───
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')
            ->with('success', translate('Installation complete! You may now log in as the admin user.'));
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
                'db_host' => ['nullable', 'string', 'max:255'],
                'db_port' => ['nullable', 'integer', 'between:1,65535'],
                'db_database' => ['nullable', 'string', 'max:255'],
                'db_username' => ['nullable', 'string', 'max:255'],
                'db_password' => ['nullable', 'string', 'max:255'],
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
            DB::connection('_install_test')->getPdo();
        } finally {
            DB::purge('_install_test');
        }
    }

    private function importDemo(array $step6Data): void
    {
        $method = $step6Data['demo_method'] ?? 'file';

        if ($method === 'upload') {
            $file = request()->file('demo_file');
            if ($file && $file->isValid()) {
                DB::unprepared(file_get_contents($file->getRealPath()));
            }

            return;
        }

        $demoPath = database_path('demo/demo.sql');
        if (file_exists($demoPath)) {
            DB::unprepared(file_get_contents($demoPath));
        }
    }
}
