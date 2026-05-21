<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CronTaskRunRequest;
use App\Http\Requests\Admin\MaintenanceSettingsRequest;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function index()
    {
        $this->authorizeSystem();

        return Inertia::render('Admin/System/Index', [
            'stats' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
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

    public function clearCache()
    {
        $this->authorizeSystem();

        Artisan::call('optimize:clear');

        return back()->with('success', translate('Cache cleared successfully.'));
    }

    public function runCronTask(CronTaskRunRequest $request)
    {
        $task = collect($this->scheduledTasks())->firstWhere('key', $request->validated('task'));

        abort_unless($task && $task['runnable'], 404);

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
            return 'Unknown';
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
            'maintenance_title' => settings('maintenance_title', settings('app_name', 'Application').' '.translate('Maintenance')),
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
                    'next_run' => $this->nextRunLabel($task['frequency']),
                ];
            })->values()->all(),
        ];
    }

    private function scheduledTasks(): array
    {
        return [
            [
                'key' => 'ai-reset-usage-counters',
                'name' => 'Reset AI usage counters',
                'command' => 'ai:reset-usage-counters',
                'frequency' => 'Daily at 00:05',
                'description' => 'Resets daily AI credit counters and monthly counters on month start.',
                'runnable' => true,
            ],
            [
                'key' => 'notifications-subscription-reminders',
                'name' => 'Subscription renewal reminders',
                'command' => 'notifications:subscription-reminders',
                'frequency' => 'Daily at 09:00',
                'description' => 'Sends in-app subscription renewal reminders.',
                'runnable' => true,
            ],
            [
                'key' => 'subscriptions-expire-past-due',
                'name' => 'Expire past-due subscriptions',
                'command' => 'subscriptions:expire-past-due',
                'frequency' => 'Hourly',
                'description' => 'Expires past-due subscriptions and notifies affected users.',
                'runnable' => true,
            ],
            [
                'key' => 'scheduler-heartbeat',
                'name' => 'Scheduler heartbeat',
                'command' => 'scheduler-heartbeat',
                'frequency' => 'Every minute',
                'description' => 'Updates the scheduler health timestamp used by admin warnings.',
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
            settings_set('maintenance_title', settings('app_name', 'Application').' '.translate('Maintenance'), 'string', 'maintenance');
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
        return trim(strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><h4><a>'));
    }

    private function authorizeSystem(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('settings.manage'), 403);
    }
}
