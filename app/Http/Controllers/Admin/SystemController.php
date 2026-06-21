<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CronTaskRunRequest;
use App\Http\Requests\Admin\MaintenanceSettingsRequest;
use App\Services\BroadcastingService;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemController extends Controller
{
    public function index()
    {
        $this->authorizeSystem();

        return Inertia::render('Admin/System/Index', [
            'health' => $this->healthChecks(),
            'healthSummary' => $this->healthCheckSummary(),
            'update' => $this->updateStatus(),
            'stats' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? translate('N/A'),
                'database_version' => $this->getDatabaseVersion(),
                'disk_free' => $this->getDiskSpace(),
                'memory_usage' => $this->getMemoryUsage(),
            ],
            'status' => [
                'is_maintenance' => app()->isDownForMaintenance(),
                'queue_running' => $this->isQueueRunning(),
                'scheduler_running' => $this->isSchedulerRunning(),
            ],
            'cron' => $this->cronStatus(),
            'maintenance' => $this->maintenanceSettings(),
            'logs' => $this->getLastLogs(),
        ]);
    }

    public function health()
    {
        $this->authorizeSystem();

        return Inertia::render('Admin/System/Health', [
            'health' => $this->healthChecks(),
            'healthSummary' => $this->healthCheckSummary(),
            'stats' => [
                'app_name' => settings('app_name', translate('Application')),
                'app_version' => settings('app_version', '1.0.0'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? translate('N/A'),
                'database_version' => $this->getDatabaseVersion(),
                'uptime' => $this->getInstallationUptime(),
                'disk_free' => $this->getDiskSpace(),
                'memory_usage' => $this->getMemoryUsage(),
            ],
        ]);
    }

    public function updates()
    {
        $this->authorizeSystem();

        return Inertia::render('Admin/System/Updates', [
            'update' => $this->updateStatus(),
        ]);
    }

    public function tools()
    {
        $this->authorizeSystem();

        return Inertia::render('Admin/System/CronJobs', [
            'cron' => $this->cronStatus(),
            'logs' => $this->getLastLogs(),
        ]);
    }

    public function maintenance()
    {
        $this->authorizeSystem();

        return Inertia::render('Admin/System/Maintenance', [
            'maintenance' => $this->maintenanceSettings(),
            'status' => [
                'is_maintenance' => app()->isDownForMaintenance(),
            ],
        ]);
    }

    public function clearCache()
    {
        $this->authorizeSystem();

        Artisan::call('optimize:clear');

        return back()->with('success', translate('Cache cleared successfully.'));
    }

    public function runCronTask(CronTaskRunRequest $request)
    {
        $this->authorizeSystem();
        $task = collect($this->scheduledTasks())->firstWhere('key', $request->validated('task'));

        abort_unless($task && $task['runnable'], 404);

        $isCronConfigured = $this->isSchedulerRunning();

        if (! $isCronConfigured) {
            return back()->with('error', translate('Cron scheduler is not configured. Set up the required cron entry first, then try again.'));
        }

        $exitCode = $task['command'] === 'scheduler-heartbeat'
            ? $this->runSchedulerHeartbeat()
            : Artisan::call($task['command']);

        if ($exitCode !== 0) {
            return back()->with('error', translate('Cron task failed. Check recent logs for details.'));
        }

        $ranAt = now()->toDateTimeString();
        settings_set('cron_task_last_run_'.$task['key'], $ranAt, 'string', 'system');

        return back()->with('success', translate(':task ran successfully.', ['task' => translate($task['name'])]));
    }

    public function updateMaintenanceSettings(MaintenanceSettingsRequest $request)
    {
        $this->authorizeSystem();
        $settings = $request->validated();
        $settings['maintenance_message'] = $this->sanitizeHtml($settings['maintenance_message']);

        if ($request->boolean('remove_maintenance_background_image')) {
            $this->deleteMaintenanceBackground();
            $settings['maintenance_background_image'] = null;
        } elseif ($request->hasFile('maintenance_background_image')) {
            $this->deleteMaintenanceBackground();
            $settings['maintenance_background_image'] = $request->file('maintenance_background_image')
                ->store('maintenance', 'public');
        } else {
            unset($settings['maintenance_background_image']);
        }

        unset($settings['remove_maintenance_background_image']);

        foreach ($settings as $key => $value) {
            settings_set($key, $value, $value === null ? 'string' : 'string', 'maintenance');
        }

        if (app()->isDownForMaintenance()) {
            $this->enterMaintenanceMode();

            return back()
                ->with('success', translate('Maintenance settings saved.'))
                ->withCookie(MaintenanceModeBypassCookie::create((string) settings('maintenance_bypass_secret')));
        }

        return back()->with('success', translate('Maintenance settings saved.'));
    }

    public function toggleMaintenance()
    {
        $this->authorizeSystem();

        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
            settings_set('maintenance_mode', false, 'boolean', 'maintenance');

            return back()->with('success', translate('Platform is now LIVE.'));
        }

        $this->ensureMaintenanceDefaults();
        $this->enterMaintenanceMode();
        settings_set('maintenance_mode', true, 'boolean', 'maintenance');

        return back()
            ->with('success', translate('Platform is now in MAINTENANCE mode.'))
            ->withCookie(MaintenanceModeBypassCookie::create((string) settings('maintenance_bypass_secret')));
    }

    protected function getDatabaseVersion()
    {
        try {
            $results = DB::select('select version() as version');

            return $results[0]->version;
        } catch (\Exception $e) {
            return translate('Unknown');
        }
    }

    protected function getDiskSpace()
    {
        $free = disk_free_space(base_path());

        return round($free / 1024 / 1024 / 1024, 2).' GB';
    }

    protected function getMemoryUsage()
    {
        $usage = memory_get_usage(true);

        return round($usage / 1024 / 1024, 2).' MB';
    }

    protected function isQueueRunning()
    {
        // Simple check for Horizon or default queue
        return DB::table('jobs')->count() === 0 || Cache::has('horizon:status');
    }

    protected function isSchedulerRunning()
    {
        return Cache::has('last_scheduler_run') || $this->lastSchedulerRun()?->greaterThan(now()->subMinutes(5));
    }

    protected function getLastLogs()
    {
        $path = storage_path('logs/laravel.log');
        if (! File::exists($path)) {
            return [];
        }

        $content = File::get($path);
        $lines = array_reverse(explode("\n", trim($content)));

        return array_slice($lines, 0, 50);
    }

    private function maintenanceSettings(): array
    {
        $this->ensureMaintenanceDefaults();
        $image = settings('maintenance_background_image');
        $restorationTime = settings('maintenance_estimated_restoration_time');

        return [
            'maintenance_title' => settings('maintenance_title', settings('app_name', translate('Application')).' '.translate('Maintenance')),
            'maintenance_message' => settings('maintenance_message', '<p>'.translate('We are improving the platform. Please check back soon.').'</p>'),
            'maintenance_estimated_restoration_time' => $restorationTime ? date('Y-m-d\TH:i', strtotime((string) $restorationTime)) : null,
            'maintenance_allowed_ips' => settings('maintenance_allowed_ips', ''),
            'maintenance_background_image' => $image,
            'maintenance_background_image_url' => $image ? Storage::url($image) : null,
        ];
    }

    private function cronStatus(): array
    {
        $lastRun = $this->lastSchedulerRun();
        $isConfigured = $lastRun?->greaterThan(now()->subMinutes(5)) ?? false;

        return [
            'is_configured' => $isConfigured,
            'last_run_at' => $lastRun?->toDateTimeString(),
            'last_run_human' => $lastRun?->diffForHumans(),
            'required_entry' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
            'project_path' => base_path(),
            'php_binary' => PHP_BINARY,
            'cpanel_detected' => $this->isCpanelDetected(),
            'tasks' => collect($this->scheduledTasks())->map(function (array $task): array {
                $lastRun = settings('cron_task_last_run_'.$task['key']);

                return [
                    ...$task,
                    'last_run_at' => $lastRun,
                    'next_run' => $this->nextRunLabel($task['frequency_key'] ?? $task['frequency']),
                ];
            })->values()->all(),
        ];
    }

    private function scheduledTasks(): array
    {
        return [
            [
                'key' => 'ai-reset-usage-counters',
                'name' => translate('Reset AI usage counters'),
                'command' => 'ai:reset-usage-counters',
                'frequency_key' => 'Daily at 00:05',
                'frequency' => translate('Daily at 00:05'),
                'description' => translate('Resets daily AI credit counters and monthly counters on month start.'),
                'runnable' => true,
            ],
            [
                'key' => 'notifications-subscription-reminders',
                'name' => translate('Subscription renewal reminders'),
                'command' => 'notifications:subscription-reminders',
                'frequency_key' => 'Daily at 09:00',
                'frequency' => translate('Daily at 09:00'),
                'description' => translate('Sends in-app subscription renewal reminders.'),
                'runnable' => true,
            ],
            [
                'key' => 'subscriptions-expire-past-due',
                'name' => translate('Expire past-due subscriptions'),
                'command' => 'subscriptions:expire-past-due',
                'frequency_key' => 'Hourly',
                'frequency' => translate('Hourly'),
                'description' => translate('Expires past-due subscriptions and notifies affected users.'),
                'runnable' => true,
            ],
            [
                'key' => 'notes-prune-expired',
                'name' => translate('Prune expired notes'),
                'command' => 'notes:prune-expired',
                'frequency_key' => 'Hourly',
                'frequency' => translate('Hourly'),
                'description' => translate('Deletes admin notes past their auto-delete date.'),
                'runnable' => true,
            ],
            [
                'key' => 'tools-flush-views',
                'name' => translate('Flush tool view counters'),
                'command' => 'tools:flush-views',
                'frequency_key' => 'Hourly',
                'frequency' => translate('Hourly'),
                'description' => translate('Persists in-memory tool view counts to database.'),
                'runnable' => true,
            ],
            [
                'key' => 'exports-cleanup',
                'name' => translate('Cleanup old exports'),
                'command' => 'exports:cleanup',
                'frequency_key' => 'Daily',
                'frequency' => translate('Daily'),
                'description' => translate('Removes expired export files from storage.'),
                'runnable' => true,
            ],
            [
                'key' => 'license-reverify',
                'name' => translate('Re-verify license'),
                'command' => 'license:reverify',
                'frequency_key' => 'Daily',
                'frequency' => translate('Daily'),
                'description' => translate('Re-verifies the active Envato license via API.'),
                'runnable' => true,
            ],
            [
                'key' => 'blog-publish-scheduled',
                'name' => translate('Publish scheduled blog posts'),
                'command' => 'blog:publish-scheduled',
                'frequency_key' => 'Every minute',
                'frequency' => translate('Every minute'),
                'description' => translate('Publishes blog posts scheduled for the current time.'),
                'runnable' => true,
            ],
            [
                'key' => 'support-auto-close',
                'name' => translate('Auto-close resolved tickets'),
                'command' => 'support:auto-close',
                'frequency_key' => 'Daily at 01:00',
                'frequency' => translate('Daily at 01:00'),
                'description' => translate('Closes resolved support tickets after inactivity.'),
                'runnable' => true,
            ],
            [
                'key' => 'social-refresh',
                'name' => translate('Refresh social counts'),
                'command' => 'social:refresh',
                'frequency_key' => 'Daily at 04:00',
                'frequency' => translate('Daily at 04:00'),
                'description' => translate('Refreshes social media follower counters.'),
                'runnable' => true,
            ],
            [
                'key' => 'scheduler-heartbeat',
                'name' => translate('Scheduler heartbeat'),
                'command' => 'scheduler-heartbeat',
                'frequency_key' => 'Every minute',
                'frequency' => translate('Every minute'),
                'description' => translate('Updates the scheduler health timestamp used by admin warnings.'),
                'runnable' => true,
            ],
        ];
    }

    private function runSchedulerHeartbeat(): int
    {
        $timestamp = now()->toDateTimeString();

        Cache::put('last_scheduler_run', $timestamp, now()->addMinutes(10));
        settings_set('last_scheduler_run', $timestamp, 'string', 'system');

        return 0;
    }

    private function lastSchedulerRun(): ?Carbon
    {
        $lastRun = Cache::get('last_scheduler_run') ?: settings('last_scheduler_run');

        if (! $lastRun) {
            return null;
        }

        try {
            return Carbon::parse($lastRun);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nextRunLabel(string $frequency): string
    {
        return match ($frequency) {
            'Every minute' => translate('Within 1 minute'),
            'Hourly' => translate('Within 1 hour'),
            'Daily at 00:05', 'Daily at 09:00' => translate('Within 24 hours'),
            default => translate('Scheduled'),
        };
    }

    private function isCpanelDetected(): bool
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return filled(getenv('CPANEL'))
            || filled(getenv('cpanel'))
            || ($home && is_dir($home.DIRECTORY_SEPARATOR.'.cpanel'));
    }

    private function ensureMaintenanceDefaults(): void
    {
        if (! settings('maintenance_title')) {
            settings_set('maintenance_title', settings('app_name', translate('Application')).' '.translate('Maintenance'), 'string', 'maintenance');
        }

        if (! settings('maintenance_message')) {
            settings_set('maintenance_message', '<p>'.translate('We are improving the platform. Please check back soon.').'</p>', 'string', 'maintenance');
        }

        if (! settings('maintenance_bypass_secret')) {
            settings_set('maintenance_bypass_secret', Str::random(32), 'string', 'maintenance');
        }
    }

    private function enterMaintenanceMode(): void
    {
        $options = [
            '--secret' => (string) settings('maintenance_bypass_secret'),
            '--render' => 'maintenance',
        ];

        if ($this->retryTime()) {
            $options['--retry'] = $this->retryTime();
        }

        Artisan::call('down', $options);
    }

    private function retryTime(): ?string
    {
        $restorationTime = settings('maintenance_estimated_restoration_time');

        return filled($restorationTime) ? (string) $restorationTime : null;
    }

    private function deleteMaintenanceBackground(): void
    {
        $image = settings('maintenance_background_image');

        if ($image) {
            Storage::disk('public')->delete($image);
        }
    }

    private function sanitizeHtml(string $html): string
    {
        return \App\Services\TiptapHtmlSanitizer::sanitize($html);
    }

    private function authorizeSystem(): void
    {
    }

    public function checkUpdates()
    {
        $this->authorizeSystem();

        $itemId = config('license.item_id');
        $apiToken = config('license.api_token');

        if (blank($itemId) || blank($apiToken)) {
            return back()->with('error', translate('Envato credentials not configured yet, please contact the author.'));
        }

        try {
            $response = Http::withToken($apiToken)
                ->timeout(15)
                ->get("https://api.envato.com/v3/market/catalog/item?id={$itemId}");

            if (!$response->successful()) {
                Log::warning('CheckUpdates: Envato API returned status '.$response->status());

                return back()->with('error', translate('Could not reach Envato API. Status: :status', ['status' => $response->status()]));
            }

            $data = $response->json();
            $latestVersion = $data['version'] ?? null;

            if (blank($latestVersion)) {
                return back()->with('error', translate('Could not determine latest version from Envato API response.'));
            }

            $currentVersion = settings('app_version', '1.0.0');
            $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

            settings_set('update_version', $latestVersion, 'string', 'system');
            settings_set('update_available', $updateAvailable, 'boolean', 'system');
            settings_set('update_last_checked', now()->toDateTimeString(), 'string', 'system');

            Cache::forget('update_available');

            if ($updateAvailable) {
                return back()->with('success', translate('Update available! Version :version (current: :current)', [
                    'version' => $latestVersion,
                    'current' => $currentVersion,
                ]));
            }

            return back()->with('success', translate('You are up to date. Version :current is the latest.', [
                'current' => $currentVersion,
            ]));

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('CheckUpdates: Could not connect to Envato API.', ['error' => $e->getMessage()]);

            return back()->with('error', translate('Could not connect to Envato API. Please try again later.'));
        } catch (\Throwable $e) {
            Log::error('CheckUpdates: Unexpected error', ['error' => $e->getMessage()]);

            return back()->with('error', translate('An unexpected error occurred while checking for updates.'));
        }
    }

    public function applyUpdate()
    {
        $this->authorizeSystem();

        try {
            app(\App\Services\UpdateService::class)->applyUpdate();
            return back()->with('success', translate('Update applied successfully. The application has been upgraded.'));
        } catch (\Exception $e) {
            Log::error('Update failed: ' . $e->getMessage());
            return back()->with('error', translate('Update failed: :message', ['message' => $e->getMessage()]));
        }
    }

    public function rollbackUpdate()
    {
        $this->authorizeSystem();

        try {
            app(\App\Services\UpdateService::class)->rollbackUpdate();
            return back()->with('success', translate('Rollback completed successfully.'));
        } catch (\Exception $e) {
            Log::error('Rollback failed: ' . $e->getMessage());
            return back()->with('error', translate('Rollback failed: :message', ['message' => $e->getMessage()]));
        }
    }

    private function updateStatus(): array
    {
        $rollbackTime = settings('last_rollback_time');
        $rollbackAvailable = false;

        if ($rollbackTime) {
            try {
                $rollbackTimeObj = \Carbon\Carbon::parse($rollbackTime);
                $rollbackAvailable = $rollbackTimeObj->gte(now()->subHours(24));
            } catch (\Throwable) {
                $rollbackAvailable = false;
            }
        }

        return [
            'current_version' => settings('app_version', '1.0.0'),
            'latest_version' => settings('update_version'),
            'update_available' => (bool) settings('update_available'),
            'last_checked' => settings('update_last_checked'),
            'rollback_available' => $rollbackAvailable,
            'rollback_time' => $rollbackTime,
        ];
    }

    private function getInstallationUptime(): string
    {
        $installedAt = DB::table('settings')->orderBy('created_at')->value('created_at');

        if (blank($installedAt)) {
            return translate('N/A');
        }

        return Carbon::parse($installedAt)->diffForHumans(now(), [
            'parts' => 3,
            'short' => true,
            'syntax' => Carbon::DIFF_ABSOLUTE,
        ]);
    }

    private function healthChecks(): array
    {
        return [
            'server' => $this->serverChecks(),
            'application' => $this->applicationChecks(),
            'services' => $this->servicesChecks(),
            'license' => $this->licenseChecks(),
        ];
    }

    private function healthCheckSummary(): array
    {
        $checks = collect($this->healthChecks())->flatten(1);

        return [
            'pass' => $checks->where('status', 'pass')->count(),
            'warn' => $checks->where('status', 'warn')->count(),
            'fail' => $checks->where('status', 'fail')->count(),
        ];
    }

    private function serverChecks(): array
    {
        return [
            [
                'status' => version_compare(PHP_VERSION, '8.3', '>=') ? 'pass' : 'fail',
                'label' => translate('PHP version'),
                'detail' => PHP_VERSION.' — '.translate('Required: 8.3+'),
                'suggestion' => version_compare(PHP_VERSION, '8.3', '>=') ? null : translate('Upgrade PHP to version 8.3 or higher.'),
            ],
            [
                'status' => $this->checkPhpExtensions(),
                'label' => translate('PHP extensions'),
                'detail' => $this->getPhpExtensionsSummary(),
                'suggestion' => $this->getPhpExtensionsSuggestion(),
            ],
            [
                'status' => is_writable(storage_path()) ? 'pass' : 'fail',
                'label' => translate('Storage writable'),
                'detail' => storage_path(),
                'suggestion' => is_writable(storage_path()) ? null : translate('Make storage/ writable: chmod -R 775 storage'),
            ],
            [
                'status' => is_writable(base_path('bootstrap/cache')) ? 'pass' : 'fail',
                'label' => translate('Cache writable'),
                'detail' => base_path('bootstrap/cache'),
                'suggestion' => is_writable(base_path('bootstrap/cache')) ? null : translate('Make bootstrap/cache/ writable: chmod -R 775 bootstrap/cache'),
            ],
            [
                'status' => $this->checkUploadSize(),
                'label' => translate('Max upload size'),
                'detail' => ini_get('upload_max_filesize'),
                'suggestion' => $this->checkUploadSize() === 'fail' ? translate('Set upload_max_filesize ≥ 64M in php.ini') : null,
            ],
            [
                'status' => $this->checkExecutionTime(),
                'label' => translate('Max execution time'),
                'detail' => ini_get('max_execution_time').'s',
                'suggestion' => $this->checkExecutionTime() === 'fail' ? translate('Set max_execution_time ≥ 120 in php.ini') : null,
            ],
        ];
    }

    private function applicationChecks(): array
    {
        return [
            [
                'status' => filled(env('APP_KEY')) ? 'pass' : 'fail',
                'label' => translate('APP_KEY set'),
                'detail' => filled(env('APP_KEY')) ? translate('Configured') : translate('Missing'),
                'suggestion' => filled(env('APP_KEY')) ? null : translate('Run: php artisan key:generate'),
            ],
            [
                'status' => app()->environment('production') && env('APP_DEBUG') === true ? 'warn'
                    : (app()->environment('production') ? 'pass' : 'warn'),
                'label' => translate('Debug mode'),
                'detail' => env('APP_DEBUG') ? translate('Enabled') : translate('Disabled'),
                'suggestion' => app()->environment('production') && env('APP_DEBUG')
                    ? translate('Set APP_DEBUG=false in production .env')
                    : null,
            ],
            [
                'status' => $this->isQueueRunning() ? 'pass' : 'warn',
                'label' => translate('Queue worker'),
                'detail' => $this->isQueueRunning() ? translate('Active') : translate('May be offline'),
                'suggestion' => $this->isQueueRunning() ? null : translate('Start queue worker: php artisan queue:work'),
            ],
            [
                'status' => $this->isSchedulerRunning() ? 'pass' : 'fail',
                'label' => translate('Scheduler'),
                'detail' => $this->isSchedulerRunning() ? translate('Active') : translate('Not running'),
                'suggestion' => $this->isSchedulerRunning() ? null : translate('Add cron job. See Cron Jobs section below.'),
            ],
            [
                'status' => config('cache.default') === 'redis' ? 'pass' : 'warn',
                'label' => translate('Cache driver'),
                'detail' => config('cache.default'),
                'suggestion' => config('cache.default') === 'redis' ? null : translate('Use Redis for better performance: CACHE_STORE=redis'),
            ],
            [
                'status' => config('session.driver') === 'redis' ? 'pass' : 'warn',
                'label' => translate('Session driver'),
                'detail' => config('session.driver'),
                'suggestion' => config('session.driver') === 'redis' ? null : translate('Use Redis for sessions: SESSION_DRIVER=redis'),
            ],
            [
                'status' => $this->broadcastingCheck(),
                'label' => translate('Broadcasting'),
                'detail' => $this->getBroadcastingDetail(),
                'suggestion' => $this->getBroadcastingSuggestion(),
            ],
        ];
    }

    private function servicesChecks(): array
    {
        return [
            [
                'status' => $this->pingDatabase() ? 'pass' : 'fail',
                'label' => translate('Database'),
                'detail' => $this->pingDatabase() ? translate('Connected') : translate('Connection failed'),
                'suggestion' => $this->pingDatabase() ? null : translate('Check DB_HOST, DB_PORT, DB_DATABASE in .env'),
            ],
            [
                'status' => $this->pingRedis() ? 'pass' : 'fail',
                'label' => translate('Redis'),
                'detail' => $this->pingRedis() ? translate('Connected') : translate('Connection failed'),
                'suggestion' => $this->pingRedis() ? null : translate('Check REDIS_HOST, REDIS_PORT in .env. Start Redis server.'),
            ],
            [
                'status' => $this->storageWriteTest() ? 'pass' : 'fail',
                'label' => translate('Storage write'),
                'detail' => $this->storageWriteTest() ? translate('Writable') : translate('Not writable'),
                'suggestion' => $this->storageWriteTest() ? null : translate('Check storage permissions and disk configuration.'),
            ],
        ];
    }

    private function licenseChecks(): array
    {
        $verified = license_verified();
        $domainMatches = $verified ? $this->domainCheck() : null;

        return [
            [
                'status' => $verified ? 'pass' : 'fail',
                'label' => translate('License active'),
                'detail' => $verified ? translate('Verified') : translate('Not verified'),
                'suggestion' => $verified ? null : translate('Activate your license in Settings → License.'),
            ],
            [
                'status' => ! $verified ? 'warn' : ($domainMatches ? 'pass' : 'fail'),
                'label' => translate('Domain match'),
                'detail' => ! $verified ? translate('N/A') : ($domainMatches ? translate('Matches') : translate('Mismatch')),
                'suggestion' => $verified && ! $domainMatches ? translate('Deactivate and re-activate license on this domain.') : null,
            ],
        ];
    }

    private function checkPhpExtensions(): string
    {
        $required = ['curl', 'zip', 'gd', 'mbstring', 'pdo', 'redis', 'fileinfo', 'tokenizer', 'xml'];
        $missing = array_filter($required, fn(string $ext) => ! extension_loaded($ext));

        if (empty($missing)) {
            return 'pass';
        }

        return count($missing) <= 2 ? 'warn' : 'fail';
    }

    private function getPhpExtensionsSummary(): string
    {
        $required = ['curl', 'zip', 'gd', 'mbstring', 'pdo', 'redis', 'fileinfo', 'tokenizer', 'xml'];
        $missing = array_filter($required, fn(string $ext) => ! extension_loaded($ext));

        if (empty($missing)) {
            return translate('All required extensions loaded');
        }

        return translate('Missing: :extensions', ['extensions' => implode(', ', $missing)]);
    }

    private function getPhpExtensionsSuggestion(): ?string
    {
        $required = ['curl', 'zip', 'gd', 'mbstring', 'pdo', 'redis', 'fileinfo', 'tokenizer', 'xml'];
        $missing = array_filter($required, fn(string $ext) => ! extension_loaded($ext));

        if (empty($missing)) {
            return null;
        }

        return translate('Install missing PHP extensions: :extensions', ['extensions' => implode(', ', $missing)]);
    }

    private function checkUploadSize(): string
    {
        $val = ini_get('upload_max_filesize');
        $bytes = $this->iniSizeToBytes($val);

        if ($bytes >= 64 * 1024 * 1024) {
            return 'pass';
        }

        if ($bytes >= 32 * 1024 * 1024) {
            return 'warn';
        }

        return 'fail';
    }

    private function checkExecutionTime(): string
    {
        $val = (int) ini_get('max_execution_time');

        if ($val >= 120) {
            return 'pass';
        }

        if ($val >= 60) {
            return 'warn';
        }

        return 'fail';
    }

    private function iniSizeToBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;

        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    private function broadcastingCheck(): string
    {
        $health = app(BroadcastingService::class)->healthStatus();

        if ($health['degraded']) {
            return 'warn';
        }

        $effective = $health['effective'] ?? 'polling';

        return $effective === 'polling' ? 'warn' : 'pass';
    }

    private function getBroadcastingDetail(): string
    {
        $health = app(BroadcastingService::class)->healthStatus();
        $effective = $health['effective'] ?? 'polling';

        return match ($effective) {
            'reverb' => translate('Laravel Reverb (WebSocket)'),
            'pusher' => translate('Pusher (WebSocket)'),
            'polling' => translate('HTTP Polling'),
            default => translate('Not configured'),
        };
    }

    private function getBroadcastingSuggestion(): ?string
    {
        $health = app(BroadcastingService::class)->healthStatus();

        if ($health['degraded']) {
            return $health['reason'];
        }

        if (($health['effective'] ?? '') === 'polling') {
            return translate('Configure Reverb or Pusher in Settings → Broadcasting for real-time notifications.');
        }

        return null;
    }

    private function pingDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function pingRedis(): bool
    {
        try {
            return (bool) Cache::store('redis')->get('health:redis_ping', function () {
                Cache::store('redis')->put('health:redis_ping', true, 10);

                return true;
            });
        } catch (\Exception) {
            return false;
        }
    }

    private function storageWriteTest(): bool
    {
        try {
            $path = 'health-check-test.txt';
            Storage::disk('public')->put($path, 'test');
            Storage::disk('public')->delete($path);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function domainCheck(): bool
    {
        try {
            $service = app(\App\Services\LicenseService::class);

            return $service->checkDomain();
        } catch (\Exception) {
            return false;
        }
    }
}
