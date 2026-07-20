<?php

namespace App\Http\Middleware;

use App\Support\AuditLogRedactor;
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
                [$targetType, $targetId] = $this->resolveTarget($request);

                DB::table('admin_audit_logs')->insert([
                    'admin_id' => $admin->id,
                    // Kept for backward-compat with rows logged before route/intent
                    // capture and as a human-readable fallback.
                    'action' => $request->method().' '.$request->path(),
                    'route_name' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    // Redact secrets + shrink noise BEFORE persisting so live
                    // credentials never land in the audit table.
                    'payload' => json_encode(AuditLogRedactor::sanitize($request->except(['_token', '_method']))),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Don't break the request if audit logging fails
                report($e);
            }
        }

        return $response;
    }

    /**
     * Best-effort "what was acted on" from the route's model/scalar parameters,
     * e.g. /admin/users/42 → ['user', '42']. Enables "who changed this record?".
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveTarget(Request $request): array
    {
        $route = $request->route();

        if (! $route) {
            return [null, null];
        }

        foreach ($route->parameters() as $name => $value) {
            $id = is_object($value)
                ? (method_exists($value, 'getKey') ? $value->getKey() : null)
                : $value;

            if ($id !== null && $id !== '') {
                return [$name, (string) $id];
            }
        }

        return [null, null];
    }
}
