<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CronTaskRunRequest;
use App\Http\Requests\Admin\MaintenanceSettingsRequest;
use App\Jobs\SendMaintenanceNotice;
use App\Services\BroadcastingService;
use App\Support\EnvFilePermissions;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\Request;
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
    /**
     * Every queue the application dispatches to, in the order a worker should drain them.
     *
     * `queue:work` with no --queue processes ONLY the queue named "default". Shipping that
     * bare command in the shared-hosting docs left nine of these permanently unprocessed
     * on those installs — including otp, which carries sign-in codes — with no visible
     * failure anywhere. This list is the one shown to admins and the one counted when
     * looking for waiting work.
     *
     * QueueCoverageTest asserts this stays in step with every onQueue() call in the
     * codebase, and with deploy/cron.txt, supervisor.conf.example and config/horizon.php.
     */
    public const WORKER_QUEUES = [
        'otp', 'emails', 'mail', 'default', 'webhooks', 'ai', 'media', 'embeddings', 'social', 'low',
    ];

    public function health()
    {
        $this->authorizeSystem();

        return Inertia::render('Admin/System/Health', [
            'health' => $this->healthChecks(),
            'healthSummary' => $this->healthCheckSummary(),
            'stats' => [
                // The name the admin actually sets lives under `site_name` (General
                // Settings). `app_name` was a stale key that kept showing the seeded
                // "MakeAI" default and never tracked a rebrand. Prefer site_name, fall
                // back to the old key, then a generic label.
                'app_name' => settings('site_name', settings('app_name', translate('Application'))),
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
            'notice' => [
                'audience_count' => $this->maintenanceAudienceCount(),
                'already_sent' => (bool) settings('maintenance_notice_sent', false),
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
            // Store first; the old background is removed only after the new write succeeds.
            $settings['maintenance_background_image'] = store_public_upload(
                $request->file('maintenance_background_image'),
                'maintenance',
                settings('maintenance_background_image'),
            );
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

            $notified = $this->notifyMaintenanceBackOnline();

            return back()->with('success', $notified
                ? translate('Platform is now LIVE. Users are being emailed.')
                : translate('Platform is now LIVE.'));
        }

        $this->ensureMaintenanceDefaults();
        $this->enterMaintenanceMode();
        settings_set('maintenance_mode', true, 'boolean', 'maintenance');

        return back()
            ->with('success', translate('Platform is now in MAINTENANCE mode.'))
            ->withCookie(MaintenanceModeBypassCookie::create((string) settings('maintenance_bypass_secret')));
    }

    /**
     * Announce an upcoming maintenance window to every user.
     *
     * Deliberately a separate action from the toggle rather than a side effect of
     * it: `queue:work` refuses to run while the application is down, so a notice
     * dispatched after `artisan down` sits in the queue until maintenance ends and
     * lands next to the all-clear. Announcing while the site is still live is the
     * only ordering that delivers on time without a --force worker.
     */
    public function notifyMaintenance()
    {
        $this->authorizeSystem();

        if (app()->isDownForMaintenance()) {
            return back()->with('error', translate('Announce the window before switching maintenance on — queued mail does not send while the platform is down.'));
        }

        if ($this->maintenanceAudienceCount() === 0) {
            return back()->with('error', translate('There are no active, verified users to notify.'));
        }

        $this->ensureMaintenanceDefaults();

        $restorationTime = $this->restorationTimeIso();

        SendMaintenanceNotice::dispatch('maintenance_scheduled', [
            'maintenance_title' => (string) settings('maintenance_title', ''),
            // The stored message is admin-authored HTML, but every {token} is
            // HTML-escaped on render — dropped in as-is it would show its own
            // markup as literal text in the email body.
            'maintenance_message' => trim(strip_tags((string) settings('maintenance_message', ''))),
            'restoration_time' => $restorationTime
                ? Carbon::parse($restorationTime)->toDayDateTimeString().' UTC'
                : translate('as soon as possible'),
        ]);

        // Records that this window was announced, so the all-clear only goes to a
        // user base that was actually warned — going live after a silent
        // maintenance would otherwise send "we are back" out of nowhere.
        settings_set('maintenance_notice_sent', true, 'boolean', 'maintenance');

        return back()->with('success', translate('Maintenance notice queued for :count users.', [
            'count' => $this->maintenanceAudienceCount(),
        ]));
    }

    /**
     * Everyone SendMaintenanceNotice will actually mail.
     */
    private function maintenanceAudienceCount(): int
    {
        return SendMaintenanceNotice::audience()->count();
    }

    /**
     * Announce the end of a maintenance window. Returns whether mail went out.
     *
     * Gated purely on whether the window was announced: telling people you are
     * back only makes sense if you told them you were going. This one is safe to
     * send from the toggle — by the time it runs the application is already up,
     * so the queue is running again.
     */
    private function notifyMaintenanceBackOnline(): bool
    {
        if (! (bool) settings('maintenance_notice_sent', false)) {
            return false;
        }

        settings_set('maintenance_notice_sent', false, 'boolean', 'maintenance');

        SendMaintenanceNotice::dispatch('maintenance_completed');

        return true;
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

    /**
     * Queue worker health, as {status, detail, suggestion}.
     *
     * Replaces a boolean that could only be wrong in the dangerous direction:
     * `DB::table('jobs')->count() === 0 || Cache::has('horizon:status')`. The jobs table
     * belongs to the database driver alone, so on Redis it is permanently empty and that
     * check reported "Active" forever while nothing consumed anything. Even on database
     * it measured backlog rather than liveness — a worker keeping up has an empty queue,
     * and a brief spike on a healthy one read as an outage.
     *
     * Liveness now comes from the heartbeat AppServiceProvider stamps after each processed
     * job, which is driver-agnostic. The one rule here: never report a pass that has not
     * been observed. A queue that cannot be verified says so, because a false green is
     * what let a site run for days with every OTP email undelivered.
     */
    protected function queueHealth(): array
    {
        $driver = (string) config('queue.default');

        // Nothing to run: sync executes jobs inside the request that dispatched them.
        if ($driver === 'sync') {
            return [
                'status' => 'pass',
                'detail' => translate('Not required (QUEUE_CONNECTION=sync runs jobs immediately)'),
                'suggestion' => null,
            ];
        }

        $lastRun = $this->lastQueueWorkerRun();

        // A worker that finished a job in the last 15 minutes is unambiguously alive.
        // The window is wide because a healthy but idle queue produces no heartbeats.
        if ($lastRun && $lastRun->greaterThan(now()->subMinutes(15))) {
            return [
                'status' => 'pass',
                'detail' => translate('Active').' — '.translate('last job :time', ['time' => $lastRun->diffForHumans()]),
                'suggestion' => null,
            ];
        }

        $pending = $this->pendingJobCount();

        // Work is waiting and no worker has reported in: the one case we can call broken.
        if ($pending > 0) {
            return [
                'status' => 'fail',
                'detail' => translate(':count job(s) waiting with no worker running', ['count' => (string) $pending]),
                'suggestion' => $this->queueWorkerCommand(),
            ];
        }

        // Nothing queued and no heartbeat. Could be a healthy idle queue or no worker at
        // all, and there is no way to tell them apart — so say that rather than guess.
        return [
            'status' => 'warn',
            'detail' => $lastRun
                ? translate('No job processed since :time', ['time' => $lastRun->diffForHumans()])
                : translate('Cannot verify — no job has been processed yet'),
            'suggestion' => $this->queueWorkerCommand(),
        ];
    }

    private function lastQueueWorkerRun(): ?Carbon
    {
        $lastRun = Cache::get('last_queue_worker_run') ?: settings('last_queue_worker_run');

        if (! $lastRun) {
            return null;
        }

        try {
            return Carbon::parse($lastRun);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Jobs waiting to be picked up, or null when the driver cannot be inspected.
     */
    private function pendingJobCount(): int
    {
        $driver = (string) config('queue.default');

        try {
            if ($driver === 'database') {
                return (int) DB::table(config('queue.connections.database.table', 'jobs'))->count();
            }

            if ($driver === 'redis') {
                $total = 0;

                foreach (self::WORKER_QUEUES as $queue) {
                    $total += (int) \Illuminate\Support\Facades\Redis::llen("queues:{$queue}");
                }

                return $total;
            }
        } catch (\Throwable) {
            // Redis down, table missing — unknown, not zero.
            return 0;
        }

        return 0;
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
            // An ISO instant, not a pre-formatted wall clock. The admin's browser renders
            // it in their own timezone and converts back on save; formatting it here with
            // date() bakes in the SERVER's timezone, so the picker showed a time offset
            // from the one they chose and drifted further on every re-save.
            'maintenance_estimated_restoration_time' => $this->restorationTimeIso(),
            'maintenance_allowed_ips' => settings('maintenance_allowed_ips', ''),
            'maintenance_background_image' => $image,
            // media_url() (not Storage::url(), which resolves against the DEFAULT disk and
            // ignores the active cloud driver) so the preview works on local and on a bucket.
            'maintenance_background_image_url' => $image ? media_url($image) : null,
        ];
    }

    private function cronStatus(): array
    {
        $lastRun = $this->lastSchedulerRun();
        $isConfigured = $lastRun?->greaterThan(now()->subMinutes(5)) ?? false;
        $php = $this->cliPhpBinary();

        return [
            'is_configured' => $isConfigured,
            // Never-run and stopped are different faults with different fixes, and rendering
            // both as "Setup Required" sent buyers hunting for a cron entry that already exists.
            'has_ever_run' => $lastRun !== null,
            // ISO, not toDateTimeString(): the browser reads a zone-less "Y-m-d H:i:s" as
            // its own local time, so a UTC wall clock reached the screen unconverted.
            'last_run_at' => $lastRun?->toIso8601String(),
            'last_run_human' => $lastRun?->diffForHumans(),
            'required_entry' => '* * * * * cd '.base_path().' && '.$php.' artisan schedule:run >> /dev/null 2>&1',
            'project_path' => base_path(),
            'php_binary' => $php,
            'cpanel_detected' => $this->isCpanelDetected(),
            // The scheduler is only half the setup, and this page used to show only that
            // half — an admin who configured what it asked for still had no worker, and
            // nothing here said so. Everything deferred (sign-in OTP emails, all other
            // mail, generation history, media, webhooks) needs the second entry.
            'queue' => [
                ...$this->queueHealth(),
                'required_entry' => $this->queueWorkerEntry(),
                'driver' => (string) config('queue.default'),
                'queues' => self::WORKER_QUEUES,
            ],
            'tasks' => collect($this->scheduledTasks())->map(function (array $task): array {
                $lastRun = settings('cron_task_last_run_'.$task['key']);

                return [
                    ...$task,
                    'last_run_at' => $this->asInstant($lastRun),
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

    /**
     * Absolute path to a PHP **CLI** binary, for the cron entry shown to the operator.
     *
     * Deliberately not PHP_BINARY. Under PHP-FPM — which is what cPanel and most managed
     * hosts run — PHP_BINARY is the FPM binary (…/sbin/php-fpm), which cannot run artisan.
     * The page used to print bare `php` instead, and that is worse on exactly the hosts
     * that need this most: cron on cPanel often has no `php` on PATH, or resolves it to a
     * PHP 5.x/7.x that fatals. Either way `>> /dev/null 2>&1` eats the error and the page
     * sits on "Setup Required" with nothing to go on. That was the single largest source
     * of "I set the cron up and it still says not configured".
     *
     * PHP_BINDIR is the winner for FPM installs: for ea-php83 it is
     * /opt/cpanel/ea-php83/root/usr/bin, whose `php` is the matching CLI build.
     */
    private function cliPhpBinary(): string
    {
        $candidates = [
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php',
            '/usr/local/bin/php',
            '/usr/bin/php',
        ];

        // On CLI (artisan tinker, tests) PHP_BINARY is already the right answer, so prefer
        // it over guessing — but only when it really is a CLI SAPI.
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            array_unshift($candidates, PHP_BINARY);
        }

        foreach ($candidates as $candidate) {
            if ($candidate && @is_executable($candidate)) {
                return $candidate;
            }
        }

        // Nothing verifiable — bare `php` at least works on hosts with a sane PATH, and
        // the operator can compare it against their host's documented binary.
        return 'php';
    }

    /**
     * The queue-worker cron entry, ready to paste, with this server's own paths.
     *
     * --stop-when-empty so each run exits instead of stacking workers a minute apart,
     * and --max-time so a run can never outlive its own cron slot.
     */
    private function queueWorkerEntry(): string
    {
        return '* * * * * cd '.base_path().' && '.$this->cliPhpBinary()
            .' artisan queue:work --queue='.implode(',', self::WORKER_QUEUES)
            .' --stop-when-empty --max-time=55 >/dev/null 2>&1';
    }

    /** The same worker invocation without the cron wrapper, for running by hand. */
    private function queueWorkerCommand(): string
    {
        return translate('Start a queue worker:').' php artisan queue:work --queue='.implode(',', self::WORKER_QUEUES);
    }

    private function isCpanelDetected(): bool
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        // Cast before filled(): getenv() returns boolean false for a missing
        // variable, and filled(false) is true — so this short-circuited to true
        // on every install and the cPanel banner always showed, even on Windows.
        return filled((string) getenv('CPANEL'))
            || filled((string) getenv('cpanel'))
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

    /**
     * The stored restoration time as an ISO instant for the admin UI, or null when unset
     * or unparseable — a malformed setting should leave the picker empty, not 500 the
     * whole System page.
     */
    private function restorationTimeIso(): ?string
    {
        $restorationTime = settings('maintenance_estimated_restoration_time');

        if (blank($restorationTime)) {
            return null;
        }

        try {
            return Carbon::parse($restorationTime)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * The route middleware already gates each system area on its own permission
     * (system.health / system.updates / system.maintenance / system.cache), with
     * `settings.manage` still opening all of them. This is the defence-in-depth check, so it
     * must accept the same set — otherwise a role granted only, say, system.updates would
     * pass the route and then be rejected here.
     */
    private function authorizeSystem(): void
    {
        $allowed = [
            'settings.manage',
            'system.health',
            'system.updates',
            'system.maintenance',
            'system.cache',
            'system.rate_limits',
        ];

        if (! auth('admin')->user()?->hasAnyPermission($allowed)) {
            abort(403, translate('Unauthorized.'));
        }
    }

    public function checkUpdates()
    {
        $this->authorizeSystem();

        try {
            $manifest = app(\App\Services\UpdateService::class)->checkForUpdate();
            $currentVersion = settings('app_version', '1.0.0');

            if (! empty($manifest['update_available'])) {
                return back()->with('success', translate('Update available! Version :version (current: :current)', [
                    'version' => $manifest['latest_version'] ?? '?',
                    'current' => $currentVersion,
                ]));
            }

            return back()->with('success', translate('You are up to date. Version :current is the latest.', [
                'current' => $currentVersion,
            ]));
        } catch (\Throwable $e) {
            Log::warning('CheckUpdates: ' . $e->getMessage());

            return back()->with('error', translate($e->getMessage()));
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

    public function uploadUpdate(Request $request)
    {
        $this->authorizeSystem();

        $request->validate([
            'package' => ['required', 'file', 'mimetypes:application/zip,application/x-zip-compressed', 'max:307200'],
        ], [
            'package.max' => translate('The update package may not be larger than 300 MB.'),
        ]);

        $stored = $request->file('package')->store('temp');
        $absolute = Storage::disk('local')->path($stored);

        try {
            app(\App\Services\UpdateService::class)->applyUpdateFromZip($absolute);
            return back()->with('success', translate('Update applied successfully from the uploaded package.'));
        } catch (\Exception $e) {
            Log::error('Manual update failed: ' . $e->getMessage());
            return back()->with('error', translate('Update failed: :message', ['message' => $e->getMessage()]));
        } finally {
            Storage::disk('local')->delete($stored);
        }
    }

    public function snoozeUpdateBanner()
    {
        $this->authorizeSystem();

        // Remind again in 24h (the sidebar badge stays regardless).
        settings_set('core_update_snoozed_until', now()->addHours(24)->toDateTimeString(), 'string', 'system');

        return back();
    }

    public function dismissUpdateBanner()
    {
        $this->authorizeSystem();

        // Never show the banner again for this specific version.
        settings_set('core_update_dismissed_version', (string) settings('update_version', ''), 'string', 'system');

        return back();
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
            // In test mode the License Server is never contacted and the manifest
            // is simulated by bumping the current version, so the screen must not
            // present that number as a real release it can install.
            'test_mode' => \App\Support\PurchaseCode::testModeActive(),
            'changelog' => settings('update_changelog'),
            'last_checked' => $this->asInstant(settings('update_last_checked')),
            'rollback_available' => $rollbackAvailable,
            'rollback_time' => $this->asInstant($rollbackTime),
        ];
    }

    /**
     * Render a stored timestamp as an unambiguous instant for the browser.
     *
     * These are written with now()->toDateTimeString() — "2026-07-28 15:39:00", no zone
     * marker. JavaScript's new Date() reads a string in that shape as BROWSER-local, so
     * the UTC wall clock was displayed verbatim: a site six hours ahead of UTC showed
     * 3:39 PM for something that happened at 9:39 PM. useDateFormat then rebased an
     * instant that was already wrong, so the timezone plumbing could not save it.
     *
     * ISO 8601 carries the offset, so the browser parses the correct instant and the
     * site-zone rebasing lands where it should. Converted on read rather than at the
     * write site, so installs already holding a zone-less value are fixed too.
     */
    private function asInstant(?string $stored): ?string
    {
        if (blank($stored)) {
            return null;
        }

        try {
            // Stored wall clock is UTC (config('app.timezone')); an already-ISO value
            // keeps its own offset, since the second argument is only a fallback.
            return Carbon::parse($stored, config('app.timezone', 'UTC'))->toIso8601String();
        } catch (\Throwable) {
            return $stored;
        }
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

    /**
     * Is the application directory readable over HTTP?
     *
     * The distribution layout puts the whole app in <webroot>/core so the buyer never has
     * to repoint a document root — unzip into public_html and it works. The price is that
     * core/ is inside the served tree, kept private by deny rules rather than by being
     * unreachable. Apache and LiteSpeed get those rules from .htaccess (webroot plus a
     * second deny-all inside core/), IIS from web.config.
     *
     * nginx reads none of it. A buyer who unzips onto an nginx VPS and never applies
     * core/deploy/nginx.conf.example is serving core/.env as plain text — database
     * password, APP_KEY and every API credential — with nothing on screen to suggest it.
     * Documentation has not been enough, so the app checks itself.
     *
     * Verified by fetching the file rather than by inspecting config, because config is
     * exactly what is wrong in the failing case. A 200 alone is not proof — hosts serve
     * catch-all pages — so the body has to look like an env file too.
     */
    private function appDirectoryExposureCheck(): array
    {
        $label = translate('Application directory privacy');

        // Only meaningful in the packaged layout. A standard checkout keeps the app
        // outside the webroot entirely, so there is nothing to expose. Same shape test
        // bootstrap/app.php uses to locate the webroot.
        if (is_dir(base_path('public')) || ! is_file(base_path('../index.php'))) {
            return [
                'status' => 'pass',
                'label' => $label,
                'detail' => translate('Not applicable — the application is outside the webroot'),
                'suggestion' => null,
            ];
        }

        $appDir = basename(base_path());

        // Cached: this is an outbound HTTP request and the health page is not.
        $result = Cache::remember(
            'system.app_dir_exposed',
            now()->addHours(12),
            fn () => $this->probeAppDirectory($appDir)
        );

        if ($result['state'] === 'exposed') {
            Log::critical('Application directory is publicly readable over HTTP', [
                'url' => "/{$appDir}/.env",
            ]);

            // Act, do not merely report. Where nginx runs as its own user — the usual
            // split — dropping .env to 0600 makes the worker unable to open it and the
            // leak becomes a 403 immediately, without waiting for anyone to edit a config
            // file. Where both run as the same user it changes nothing, so the warning
            // below stands either way.
            $hardened = EnvFilePermissions::harden();

            return [
                'status' => 'fail',
                'label' => $label,
                'detail' => translate('EXPOSED — :path is publicly readable', ['path' => "/{$appDir}/.env"]),
                'suggestion' => ($hardened
                    ? translate('Permissions on .env were tightened to 0600 just now, which blocks this on most nginx setups — verify by opening the path above. ')
                    : '')
                    .translate('Your database password and APP_KEY may already be public. Apply the rules in :file and reload nginx, then change those credentials.', ['file' => "{$appDir}/deploy/nginx.conf.example"]),
            ];
        }

        if ($result['state'] === 'unknown') {
            return [
                'status' => 'warn',
                'label' => $label,
                'detail' => translate('Could not be verified from this server'),
                'suggestion' => translate('Open :path in a browser. If it downloads or displays, your credentials are public — deny the :dir directory in your web server config.', ['path' => "/{$appDir}/.env", 'dir' => $appDir]),
            ];
        }

        return [
            'status' => 'pass',
            'label' => $label,
            'detail' => translate('Private — :dir is not served', ['dir' => $appDir]),
            'suggestion' => null,
        ];
    }

    /**
     * Fetch <site>/<appDir>/.env and decide what the answer means.
     *
     * Three outcomes, and the distinction between the last two is the point: a request
     * that could not be made proves nothing, and reporting it as safety would be the
     * one failure mode worse than not checking.
     *
     *   exposed    200 AND the body reads like an env file
     *   protected  any non-200, or a 200 that is clearly a catch-all page
     *   unknown    no site URL, or the request could not be made at all
     */
    private function probeAppDirectory(string $appDir): array
    {
        $base = rtrim((string) (settings('site_url') ?: config('app.url')), '/');

        if ($base === '') {
            return ['state' => 'unknown', 'reason' => 'no site URL configured'];
        }

        try {
            $response = Http::withoutRedirecting()
                ->timeout(5)
                ->withHeaders(['User-Agent' => 'makeai-selfcheck'])
                ->get("{$base}/{$appDir}/.env");
        } catch (\Throwable $e) {
            // Plenty of hosts block a server from resolving its own public hostname.
            // That is not evidence either way.
            return ['state' => 'unknown', 'reason' => $e->getMessage()];
        }

        if ($response->status() !== 200) {
            return ['state' => 'protected', 'reason' => 'HTTP '.$response->status()];
        }

        // A 200 alone is not proof: hosts answer with catch-all pages for anything
        // missing, and treating those as exposure would cry wolf on every correctly
        // configured site.
        $looksLikeEnv = Str::contains(
            (string) $response->body(),
            ['APP_KEY=', 'DB_PASSWORD=', 'DB_DATABASE=', 'APP_ENV=']
        );

        return $looksLikeEnv
            ? ['state' => 'exposed', 'reason' => 'HTTP 200 and the body is an env file']
            : ['state' => 'protected', 'reason' => 'HTTP 200 but not the env file (catch-all page)'];
    }

    private function serverChecks(): array
    {
        return [
            $this->appDirectoryExposureCheck(),
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

    /**
     * Is uploaded media actually reachable over HTTP?
     *
     * The public disk writes to one path and its URLs are generated from another
     * (APP_URL/storage). When those disagree, uploading succeeds and every resulting
     * image 403s or 404s — a failure that produces no exception, no log entry, and a
     * broken media library the admin cannot explain. This makes that visible.
     *
     * Only meaningful for a local disk: once the buyer points storage at S3 in
     * Settings → Storage, the served path is the bucket's problem and this check
     * would be comparing unrelated things.
     */
    private function publicMediaCheck(): array
    {
        $label = translate('Public media reachable');

        if (config('filesystems.disks.public.driver') !== 'local') {
            return [
                'status' => 'pass',
                'label' => $label,
                'detail' => translate('Served by remote storage').' ('.config('filesystems.disks.public.driver').')',
                'suggestion' => null,
            ];
        }

        $root = (string) config('filesystems.disks.public.root');
        $webroot = rtrim(str_replace('\\', '/', public_path()), '/');
        $normalised = rtrim(str_replace('\\', '/', $root), '/');

        // Compared as a path prefix rather than by equality: a buyer may legitimately
        // point PUBLIC_DISK_ROOT at a subdirectory of the webroot.
        $served = $normalised === $webroot || str_starts_with($normalised.'/', $webroot.'/');
        $writable = is_dir($root) ? is_writable($root) : is_writable(dirname($root));

        if (! $served) {
            return [
                'status' => 'fail',
                'label' => $label,
                'detail' => translate('Media is stored outside the public web root').': '.$root,
                'suggestion' => translate('Uploads will save but never load. Set PUBLIC_DISK_ROOT in .env to').' '.public_path('storage'),
            ];
        }

        if (! $writable) {
            return [
                'status' => 'fail',
                'label' => $label,
                'detail' => translate('Not writable').': '.$root,
                'suggestion' => translate('Make the media directory writable: chmod -R 775').' '.$root,
            ];
        }

        return [
            'status' => 'pass',
            'label' => $label,
            'detail' => $root,
            'suggestion' => null,
        ];
    }

    private function applicationChecks(): array
    {
        // Tested once, not assumed from config. The cache/session driver checks
        // below only nag about Redis when it is genuinely running AND unused —
        // never when it is absent, because file/database is the correct choice on
        // a host without Redis and a buyer can do nothing about it.
        $redisReady = $this->pingRedis();

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
            $this->publicMediaCheck(),
            [
                // Evaluated once: the old code called isQueueRunning() three times, so a
                // queue draining mid-render could report a different status than detail.
                ...$this->queueHealth(),
                'label' => translate('Queue worker'),
            ],
            [
                'status' => $this->isSchedulerRunning() ? 'pass' : 'fail',
                'label' => translate('Scheduler'),
                'detail' => $this->isSchedulerRunning() ? translate('Active') : translate('Not running'),
                'suggestion' => $this->isSchedulerRunning() ? null : translate('Add cron job. See Cron Jobs section below.'),
            ],
            [
                // Redis is a bonus, not a requirement — pass unless Redis is
                // actually available and being left on the table.
                'status' => (config('cache.default') === 'redis' || ! $redisReady) ? 'pass' : 'warn',
                'label' => translate('Cache driver'),
                'detail' => ucfirst((string) config('cache.default')),
                'suggestion' => (config('cache.default') !== 'redis' && $redisReady)
                    ? translate('Redis is running on this server and is faster than the file cache. Set CACHE_STORE=redis in your .env file, or ask your host to enable it.')
                    : null,
            ],
            [
                // Do NOT push Redis here. The product runs sessions on the database
                // driver on purpose: the "active sessions / sign out other devices"
                // feature reads the sessions table, which Redis sessions never
                // populate. So `database` is the correct, healthy state; only warn
                // if sessions are on `file`, which loses that feature and does not
                // persist across multiple app servers.
                'status' => in_array(config('session.driver'), ['database', 'redis'], true) ? 'pass' : 'warn',
                'label' => translate('Session driver'),
                'detail' => ucfirst((string) config('session.driver')),
                'suggestion' => in_array(config('session.driver'), ['database', 'redis'], true)
                    ? null
                    : translate('Set SESSION_DRIVER=database in your .env so the "active sessions" and sign-out features work.'),
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
                // Redis is optional. Only a red failure when a driver is actually
                // pointed at it and it cannot be reached; otherwise its absence is
                // normal and shown as such, not as a scary error a non-technical
                // buyer cannot act on.
                'status' => $this->pingRedis() ? 'pass' : ($this->redisRequired() ? 'fail' : 'pass'),
                'label' => translate('Redis'),
                'detail' => $this->pingRedis()
                    ? translate('Connected')
                    : ($this->redisRequired() ? translate('Connection failed') : translate('Not in use (optional)')),
                'suggestion' => (! $this->pingRedis() && $this->redisRequired())
                    ? translate('A driver is set to Redis but it is unreachable. Check REDIS_HOST and REDIS_PORT in .env, or start the Redis server.')
                    : null,
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

    /**
     * PHP extensions the app hard-requires. Redis is intentionally absent:
     * the app talks to Redis through the bundled Predis client (no phpredis
     * extension needed), and live Redis connectivity is covered separately by
     * the "Redis" service ping check.
     */
    private function requiredPhpExtensions(): array
    {
        return ['curl', 'zip', 'gd', 'mbstring', 'pdo', 'fileinfo', 'tokenizer', 'xml', 'dom', 'iconv'];
    }

    private function missingPhpExtensions(): array
    {
        return array_values(array_filter(
            $this->requiredPhpExtensions(),
            fn(string $ext) => ! extension_loaded($ext),
        ));
    }

    private function checkPhpExtensions(): string
    {
        $missing = $this->missingPhpExtensions();

        if (empty($missing)) {
            return 'pass';
        }

        return count($missing) <= 2 ? 'warn' : 'fail';
    }

    private function getPhpExtensionsSummary(): string
    {
        $missing = $this->missingPhpExtensions();

        if (empty($missing)) {
            return translate('All required extensions loaded');
        }

        return translate('Missing: :extensions', ['extensions' => implode(', ', $missing)]);
    }

    private function getPhpExtensionsSuggestion(): ?string
    {
        $missing = $this->missingPhpExtensions();

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
            return translate('Configure Reverb or Pusher in Settings → Notifications for real-time notifications.');
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

    /** Memoised: the health page calls this several times per render. */
    private ?bool $redisPing = null;

    private function pingRedis(): bool
    {
        if ($this->redisPing !== null) {
            return $this->redisPing;
        }

        try {
            return $this->redisPing = (bool) Cache::store('redis')->get('health:redis_ping', function () {
                Cache::store('redis')->put('health:redis_ping', true, 10);

                return true;
            });
        } catch (\Throwable) {
            return $this->redisPing = false;
        }
    }

    /** Is any core driver actually configured to use Redis? */
    private function redisRequired(): bool
    {
        return config('cache.default') === 'redis'
            || config('session.driver') === 'redis'
            || config('queue.default') === 'redis';
    }

    private function storageWriteTest(): bool
    {
        try {
            $disk = Storage::disk('public');
            $path = 'health-check-'.\Illuminate\Support\Str::random(16).'.txt';
            $payload = 'ok-'.\Illuminate\Support\Str::random(8);

            // put() returns false (not throws) on the throw=>false public disk, so the
            // boolean must be checked — and the content round-tripped — or a broken/cloud
            // misconfigured disk would be reported healthy.
            if ($disk->put($path, $payload) === false) {
                return false;
            }

            $readBack = $disk->get($path);
            $disk->delete($path);

            return $readBack === $payload;
        } catch (\Throwable) {
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
