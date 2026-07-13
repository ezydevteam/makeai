import { usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'

type TranslationReplacements = Record<string, string | number>

interface TranslateComposable {
    t: (key: string, replace?: TranslationReplacements) => string
}

function applyReplacements(text: string, replace?: TranslationReplacements): string {
    if (!replace) return text

    let result = text

    if ('count' in replace) {
        const count = Number(replace.count)
        const parts = result.split('|')
        if (parts.length === 2) {
            result = count === 1 ? parts[0] : parts[1]
        } else if (parts.length === 3) {
            // Support zero/singular/plural: zero|one|many
            result = count === 0 ? parts[0] : count === 1 ? parts[1] : parts[2]
        }
    }

    Object.entries(replace).forEach(([k, v]) => {
        result = result.replace(new RegExp(`:${k}`, 'g'), String(v))
    })

    return result
}

/**
 * Standalone translation function.
 * Safe to call anywhere (even outside Vue components/setup context).
 */
let clientCache: Record<string, string> | null = null

export function t(key: string, replace?: TranslationReplacements): string {
    const props = ((router as any).page?.props?.translations as Record<string, string> | null) || clientCache
    const text = props?.[key] ?? key
    return applyReplacements(text, replace)
}

/**
 * Translation composable — uses translations shared via Inertia.
 *
 * Supports pluralization with pipe syntax:
 *   t('One item|:count items', { count: 5 })     → "5 items"
 *   t('No items|One item|:count items', { count: 0 }) → "No items"
 *
 * @example
 * const { t } = useTranslate()
 * t('Welcome back, :name', { name: user.name })
 */
export function useTranslate(): TranslateComposable {
    const page = usePage()

    const translations = computed(() => {
        const props = page.props.translations as Record<string, string> | null
        if (props) {
            clientCache = props
            return props
        }
        return clientCache ?? ({} as Record<string, string>)
    })

    const tReactive: TranslateComposable['t'] = (key, replace) => {
        const text = translations.value[key] ?? key
        return applyReplacements(text, replace)
    }

    return { t: tReactive }
}
