<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminAuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $admin = auth('admin')->user();

        if ($admin && $admin->isSuperAdmin() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            try {
                DB::table('admin_audit_logs')->insert([
                    'admin_id' => $admin->id,
                    'action' => $request->method() . ' ' . $request->path(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'payload' => json_encode($request->except(['password', 'password_confirmation', '_token'])),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Don't break the request if audit logging fails
                report($e);
            }
        }

        return $response;
    }
}
