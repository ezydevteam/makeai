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

export interface LocaleInfo {
    code: string
    name: string
    flag?: string | null
    is_rtl: boolean
    date_format?: string | null
    time_format?: string | null
    currency_position?: CurrencyPosition | null
    number_format?: {
        decimal?: string
        thousands?: string
        system?: string
    }
}

export interface LanguageOption {
    code: string
    name: string
    flag?: string | null
    is_rtl?: boolean
}

export interface CurrencyInfo {
    code: string
    symbol?: string | null
    position?: CurrencyPosition | null
    decimals?: number
}

export type CurrencyPosition = 'before' | 'before_with_space' | 'after' | 'after_with_space'

export interface Branding {
    site_name: string
    site_tagline: string
    site_description: string
    site_logo_light: string
    site_logo_dark: string
    site_favicon_ico: string
    site_favicon_png: string
    site_og_image: string
    site_copyright_text: string
    site_support_email: string
    site_support_url: string
    site_terms_url: string
    site_privacy_url: string
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
    branding: Branding
    locale: LocaleInfo
    isRtl?: boolean
    languages?: LanguageOption[]
    currency?: CurrencyInfo
    socialFollow?: SocialFollowPayload
    cronStatus?: {
        is_configured: boolean
        last_run_at: string | null
        setup_url: string
    } | null
    settings?: Record<string, unknown>
    translations?: Record<string, string>
    [key: string]: unknown
}

export interface SocialFollowProfile {
    platform: string
    label: string
    unit: string
    url: string
    count: number
}

export interface SocialFollowPayload {
    display_mode: 'icons' | 'counts' | 'cards'
    profiles: SocialFollowProfile[]
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
