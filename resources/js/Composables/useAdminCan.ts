import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface AdminShared {
    isSuperAdmin?: boolean
    permissions?: string[]
}

/**
 * Admin permission helpers backed by the shared `admin` Inertia props.
 *
 * Mirrors the server-side AdminPermission middleware: super-admins bypass all
 * checks, and `canAny` grants access when the admin holds ANY of the given
 * permissions (OR logic). Use this to hide actions the current admin cannot
 * perform — the routes still enforce the same permissions server-side.
 */
export function useAdminCan() {
    const page = usePage()

    const admin = computed<AdminShared | null | undefined>(
        () => (page.props as { admin?: AdminShared | null }).admin
    )

    const isSuperAdmin = computed<boolean>(() => admin.value?.isSuperAdmin ?? false)
    const permissions = computed<string[]>(() => admin.value?.permissions ?? [])

    const can = (permission: string): boolean =>
        isSuperAdmin.value || permissions.value.includes(permission)

    const canAny = (perms: string[]): boolean =>
        isSuperAdmin.value || perms.some((perm) => permissions.value.includes(perm))

    return { isSuperAdmin, permissions, can, canAny }
}
