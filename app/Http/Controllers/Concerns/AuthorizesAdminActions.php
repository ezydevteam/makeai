<?php

namespace App\Http\Controllers\Concerns;

/**
 * Defense-in-depth authorization for admin controllers.
 *
 * The route-level `admin.permission:*` middleware is the primary guard, but a
 * destructive action (delete, ban, refund, impersonate) should not depend on a
 * single registration being correct. Re-assert the permission inside the action
 * so a route that is ever moved out of its permission group still fails closed.
 */
trait AuthorizesAdminActions
{
    /**
     * Abort with 403 unless the current admin holds ANY of the given permission
     * slugs (same OR-semantics as the AdminPermission middleware). Super admins
     * always pass (see HasRBAC).
     */
    protected function authorizeAdmin(string ...$permissions): void
    {
        $admin = auth('admin')->user();

        abort_if(! $admin, 401);
        abort_if(
            ! $admin->hasAnyPermission($permissions),
            403,
            translate('You do not have permission to perform this action.'),
        );
    }
}
