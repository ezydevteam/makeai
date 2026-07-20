<?php

namespace App\Http\Controllers\Admin\Activity;

use App\Http\Controllers\Controller;
use App\Support\AuditLogPresenter;
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
            $term = '%'.$request->action.'%';
            $query->where(function ($q) use ($term) {
                $q->where('admin_audit_logs.action', 'like', $term)
                    ->orWhere('admin_audit_logs.route_name', 'like', $term)
                    ->orWhere('admin_audit_logs.target_type', 'like', $term);
            });
        }

        if ($request->filled('date_from')) {
            $query->where('admin_audit_logs.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('admin_audit_logs.created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Enrich each row with buyer-friendly presentation (label, category
        // icon/colour, target, redacted payload) from the single server-side
        // presenter — the frontend renders these directly, no parsing.
        $logs->getCollection()->transform(function ($log) {
            $presented = AuditLogPresenter::present($log);

            $log->action_label = $presented['label'];
            $log->category = $presented['category'];
            $log->category_label = $presented['category_label'];
            $log->category_icon = $presented['icon'];
            $log->category_color = $presented['color'];
            $log->method = $presented['method'];
            $log->target = $presented['target'];
            $log->payload = $presented['payload'];

            return $log;
        });

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
