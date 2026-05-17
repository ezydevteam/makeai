/**
 * Inertia page props interface for MakeAI
 */
export interface User {
    id: number
    ulid: string
    name: string
    email: string
    avatar: string | null
    credits: number
    plan_id: number | null
    theme_preference: 'light' | 'dark' | 'system'
    email_verified_at: string | null
    created_at: string
}

export interface Admin {
    id: number
    name: string
    email: string
    avatar: string | null
    role: AdminRole
}

export interface AdminRole {
    id: number
    name: string
    slug: string
    permissions: string[]
}

export interface FlashMessages {
    success: string | null
    error: string | null
    warning: string | null
    info: string | null
}

export interface SharedPageProps {
    auth: {
        user: User | null
    }
    admin?: {
        user: Admin | null
        isSuperAdmin?: boolean
        permissions?: string[]
    }
    flash: FlashMessages
    app?: {
        demo?: boolean
        [key: string]: unknown
    }
    appName: string
    locale:
        | string
        | {
              code: string
              direction?: 'ltr' | 'rtl'
              [key: string]: unknown
          }
    settings?: Record<string, unknown>
    translations?: Record<string, string>
    [key: string]: unknown
}

export interface PaginatedData<T> {
    data: T[]
    links: {
        first: string
        last: string
        prev: string | null
        next: string | null
    }
    meta: {
        current_page: number
        from: number
        last_page: number
        per_page: number
        to: number
        total: number
    }
}
