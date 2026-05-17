<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function index()
    {
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
            'logs' => $this->getLastLogs(),
        ]);
    }

    public function clearCache(Request $request)
    {
        $type = $request->input('type', 'all');

        match ($type) {
            'view' => Artisan::call('view:clear'),
            'route' => Artisan::call('route:clear'),
            'config' => Artisan::call('config:clear'),
            'cache' => Artisan::call('cache:clear'),
            default => $this->clearAllCaches(),
        };

        return back()->with('success', "System {$type} cache cleared successfully.");
    }

    public function toggleMaintenance()
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');

            return back()->with('success', 'Platform is now LIVE.');
        }

        Artisan::call('down', [
            '--secret' => 'admin-bypass-123',
            '--render' => 'errors::503',
        ]);

        return back()->with('success', 'Platform is now in MAINTENANCE mode.');
    }

    protected function clearAllCaches()
    {
        Artisan::call('optimize:clear');
        Cache::flush();
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
        return Cache::has('last_scheduler_run');
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
}
