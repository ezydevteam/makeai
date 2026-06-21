<?php

namespace App\Http\Controllers\Admin\Activity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403);
        $windowStart = now()->subDays(29)->startOfDay();

        $query = DB::table('admin_audit_logs')
            ->join('admins', 'admin_audit_logs.admin_id', '=', 'admins.id')
            ->select(
                'admin_audit_logs.*',
                'admins.name as admin_name',
                'admins.email as admin_email'
            )
            ->where('admin_audit_logs.created_at', '>=', $windowStart)
            ->orderBy('admin_audit_logs.created_at', 'desc');

        if ($request->filled('admin_id')) {
            $query->where('admin_audit_logs.admin_id', $request->admin_id);
        }

        if ($request->filled('action')) {
            $query->where('admin_audit_logs.action', 'like', '%' . $request->action . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('admin_audit_logs.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('admin_audit_logs.created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50);

        $admins = DB::table('admins')
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Activity/AdminLog', [
            'logs' => $logs,
            'admins' => $admins,
            'filters' => $request->only(['admin_id', 'action', 'date_from', 'date_to']),
        ]);
    }
}
