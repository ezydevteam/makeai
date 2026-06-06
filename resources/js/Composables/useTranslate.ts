import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

type TranslationReplacements = Record<string, string | number>

interface TranslateComposable {
    t: (key: string, replace?: TranslationReplacements) => string
}

/**
 * Translation composable — uses translations shared via Inertia.
 *
 * @example
 * const { t } = useTranslate()
 * t('Welcome back, :name', { name: user.name })
 */
export function useTranslate(): TranslateComposable {
    const page = usePage()

    const translations = computed(() => {
        return (page.props.translations ?? {}) as Record<string, string>
    })

    const t: TranslateComposable['t'] = (key, replace) => {
        let text = translations.value[key] ?? key

        if (replace) {
            Object.entries(replace).forEach(([k, v]) => {
                text = text.replace(new RegExp(`:${k}`, 'g'), String(v))
            })
        }

        return text
    }

    return { t }
}
