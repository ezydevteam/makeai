import { computed } from 'vue'

export interface SectionStyleConfig {
    section_bg?: string
    title_align?: string
    title_color?: string
    title_size?: string
    badge_text?: string
}

export const SECTION_BG_DEFAULTS: Record<string, { light: string; dark: string; isDark: boolean }> = {
    default: { light: 'bg-[var(--color-bg)]', dark: '', isDark: false },
    light: { light: 'bg-gray-50', dark: 'dark:bg-surface-900', isDark: false },
    'primary-light': { light: 'bg-primary-50', dark: 'dark:bg-primary-950', isDark: false },
    'green-light': { light: 'bg-emerald-50', dark: 'dark:bg-emerald-950', isDark: false },
    'warning-light': { light: 'bg-amber-50', dark: 'dark:bg-amber-950', isDark: false },
    'red-light': { light: 'bg-red-50', dark: 'dark:bg-red-950', isDark: false },
    gradient1: { light: 'bg-gradient-to-br from-[var(--color-primary)] via-[var(--color-primary)] to-[var(--color-secondary)]', dark: '', isDark: true },
    gradient2: { light: 'bg-gradient-to-br from-purple-600 to-pink-500', dark: '', isDark: true },
    gradient3: { light: 'bg-gradient-to-br from-blue-600 to-cyan-400', dark: '', isDark: true },
    gradient4: { light: 'bg-gradient-to-br from-blue-600 to-purple-600', dark: '', isDark: true },
}

export const TITLE_COLOR_MAP: Record<string, { title: string; subtitle: string }> = {
    primary: { title: '!text-primary-600 dark:!text-primary-400', subtitle: 'text-primary-500/70 dark:text-primary-400/60' },
    danger: { title: '!text-red-600 dark:!text-red-400', subtitle: 'text-red-500/70 dark:text-red-400/60' },
    success: { title: '!text-emerald-600 dark:!text-emerald-400', subtitle: 'text-emerald-500/70 dark:text-emerald-400/60' },
    warning: { title: '!text-amber-600 dark:!text-amber-400', subtitle: 'text-amber-500/70 dark:text-amber-400/60' },
    dark: { title: '!text-gray-900 dark:!text-white', subtitle: 'text-gray-500 dark:text-gray-400' },
    gradient1: { title: 'bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-secondary)] bg-clip-text !text-transparent', subtitle: 'text-primary-500/70 dark:text-primary-400/60' },
    gradient2: { title: 'bg-gradient-to-r from-purple-500 to-pink-400 bg-clip-text !text-transparent', subtitle: 'text-purple-500/70 dark:text-pink-400/60' },
    gradient3: { title: 'bg-gradient-to-r from-blue-500 to-cyan-400 bg-clip-text !text-transparent', subtitle: 'text-blue-500/70 dark:text-cyan-400/60' },
    gradient4: { title: 'bg-gradient-to-r from-blue-500 to-purple-500 bg-clip-text !text-transparent', subtitle: 'text-blue-500/70 dark:text-purple-400/60' },
}

export const TITLE_SIZE_MAP: Record<string, string> = {
    sm: 'text-2xl md:text-3xl leading-[1.25]',
    md: 'text-3xl md:text-5xl leading-[1.25]',
    lg: 'text-4xl md:text-6xl leading-[1.25]',
    xl: 'text-5xl md:text-7xl leading-[1.25]',
}

export function useSectionStyle() {
    const sectionBgClass = (bg: string | undefined, fallback = 'default'): string => {
        const value = bg || fallback
        const def = SECTION_BG_DEFAULTS[value] ?? SECTION_BG_DEFAULTS.default
        return [def.light, def.dark].filter(Boolean).join(' ')
    }

    const sectionBgIsDark = (bg: string | undefined): boolean => {
        const value = bg || 'default'
        if (value.toLowerCase().includes('gradient')) return true
        return SECTION_BG_DEFAULTS[value]?.isDark ?? false
    }

    const titleColorClass = (color: string | undefined, fallback = 'dark'): string => {
        const value = color || fallback
        return TITLE_COLOR_MAP[value]?.title ?? TITLE_COLOR_MAP.dark.title
    }

    const subtitleColorClass = (titleColor: string | undefined, bg?: string, fallback = 'dark'): string => {
        const value = titleColor || fallback
        if (sectionBgIsDark(bg) && value === 'dark') {
            return 'text-white/70'
        }
        return TITLE_COLOR_MAP[value]?.subtitle ?? TITLE_COLOR_MAP.dark.subtitle
    }

    const titleAlignClass = (align: string | undefined, fallback = 'center'): string => {
        return align === 'left' ? 'text-left' : 'text-center'
    }

    const titleSizeClass = (size: string | undefined, fallback = 'md'): string => {
        return TITLE_SIZE_MAP[size || fallback] ?? TITLE_SIZE_MAP[fallback]
    }

    const badgeClass = (bg: string | undefined, titleColor: string | undefined): string => {
        const isDarkBg = sectionBgIsDark(bg)
        const color = titleColor || 'dark'
        const borderClasses = isDarkBg
            ? 'border-white/20 text-white bg-white/10'
            : color === 'dark'
                ? 'border-gray-200 text-gray-600 bg-gray-100 dark:border-surface-700 dark:text-gray-400 dark:bg-surface-800'
                : `border-${color}-200 text-${color}-700 bg-${color}-100 dark:border-${color}-800 dark:text-${color}-300 dark:bg-${color}-900/20`
        return `mb-4 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-bold uppercase tracking-wider ${borderClasses}`
    }

    const cardBgClass = (style: string | undefined): string => {
        const val = style || 'transparent'
        if (val === 'default') return ''
        const map: Record<string, string> = {
            'transparent': 'bg-transparent border border-gray-200 dark:border-surface-800',
            'white': 'bg-white border border-gray-100 dark:bg-surface-900 dark:border-surface-800',
            'light': 'bg-gray-50 border border-gray-100 dark:bg-surface-850 dark:border-surface-800',
            'primary_light': 'bg-primary-50/50 border border-primary-100 dark:bg-primary-950/10 dark:border-primary-950/20',
            'gradient-1': 'bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-600 text-white border border-white/10',
            'gradient-2': 'bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-600 text-white border border-white/10',
            'gradient-3': 'bg-gradient-to-br from-sky-400 via-blue-500 to-indigo-600 text-white border border-white/10',
            'gradient-4': 'bg-gradient-to-br from-orange-500 via-pink-500 to-purple-600 text-white border border-white/10',
        }
        return map[val] ?? map.transparent
    }

    const cardWrapperClass = (style: string | undefined, defaultStyle = 'transparent'): string => {
        const val = style || defaultStyle
        if (val === 'default') return ''
        return `${cardBgClass(val)} relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16`
    }

    const sectionIconClass = (style: string | undefined): string => {
        const val = style || 'primary'
        const map: Record<string, string> = {
            'dark': 'bg-gray-100 text-gray-800 dark:bg-surface-800/80 dark:text-gray-200 dark:border dark:border-surface-700/60',
            'light': 'bg-white text-gray-700 border border-gray-200/50 dark:bg-surface-800/80 dark:text-white dark:border-surface-700/60',
            'primary': 'bg-primary-50 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400 dark:border dark:border-primary-500/30',
            'success': 'bg-success-50 text-success-600 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border dark:border-emerald-500/30',
            'danger': 'bg-danger-50 text-danger-600 dark:bg-rose-500/20 dark:text-rose-400 dark:border dark:border-rose-500/30',
            'warning': 'bg-warning-50 text-warning-600 dark:bg-amber-500/20 dark:text-amber-400 dark:border dark:border-amber-500/30',
            'gradient-1': 'bg-gradient-to-br from-blue-500 to-purple-600 text-white',
            'gradient-4': 'bg-gradient-to-br from-orange-500 via-pink-500 to-purple-600 text-white',
        }
        return `inline-flex items-center justify-center shrink-0 rounded-2xl ${map[val] ?? map.primary}`
    }

    const sectionHeaderClass = (align: string | undefined, position?: string | undefined): string => {
        const alignVal = align || 'center'
        const alignClass = alignVal === 'left' ? 'items-start text-left' : (alignVal === 'right' ? 'items-end text-right' : 'items-center text-center')
        return `flex flex-col ${alignClass} w-full`
    }

    const sectionPaddingStyle = (padding: unknown, fallback = '96'): Record<string, string> => {
        const val = padding !== undefined && padding !== null && padding !== '' ? String(padding) : fallback
        if (/^\d+$/.test(val)) {
            return {
                paddingTop: `${val}px`,
                paddingBottom: `${val}px`,
            }
        }
        return {
            paddingTop: val,
            paddingBottom: val,
        }
    }

    return {
        sectionBgClass,
        sectionBgIsDark,
        titleColorClass,
        subtitleColorClass,
        titleAlignClass,
        titleSizeClass,
        badgeClass,
        cardBgClass,
        cardWrapperClass,
        sectionIconClass,
        sectionHeaderClass,
        sectionPaddingStyle,
    }
}
