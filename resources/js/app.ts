import '../css/app.css'
import { createApp, h } from 'vue'
import type { DefineComponent } from 'vue'
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3'
import { createPinia } from 'pinia'
import { ZiggyVue } from 'ziggy-js'

const appName = import.meta.env.VITE_APP_NAME || document.title
const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue')
const templates = import.meta.glob<{ default: DefineComponent }>('./Templates/**/*.vue')

function syncDocumentLocale(locale: unknown) {
    const localeInfo = (locale ?? {}) as { code?: string; is_rtl?: boolean | number | string }
    const html = document.documentElement
    const code = localeInfo.code ?? 'en'
    const isRtl = localeInfo.is_rtl === true || localeInfo.is_rtl === 1 || localeInfo.is_rtl === '1'

    html.lang = code.replace('_', '-')
    html.dir = isRtl ? 'rtl' : 'ltr'
    html.classList.toggle('rtl', isRtl)
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.vue`]

            ?? (
                name.startsWith('Templates/')
                ? templates[`./Templates/${name.slice('Templates/'.length)}.vue`]
                : null
            )

        if (!page) {
            throw new Error(`Page not found: ${name}`)
        }

        return (await page()).default
    },
    setup({ el, App, props, plugin }) {
        syncDocumentLocale(props.initialPage.props.locale)
        router.on('navigate', (event) => syncDocumentLocale(event.detail.page.props.locale))

        const pinia = createPinia()
        const vueApp = createApp({ render: () => h(App, props) })

        vueApp
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .component('Link', Link)
            .component('Head', Head)
        vueApp.config.globalProperties.$t = function (this: any, key: string, replace?: Record<string, string | number>) {
            const translations = (this.$page?.props?.translations ?? {}) as Record<string, string>
            let text = translations[key] ?? key

            if (replace) {
                Object.entries(replace).forEach(([name, value]) => {
                    text = text.replace(new RegExp(`:${name}`, 'g'), String(value))
                })
            }

            return text
        }
        vueApp.mount(el)
    },
    progress: {
        color: '#6366f1',
        showSpinner: true,
    },
})
