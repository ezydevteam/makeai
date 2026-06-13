import '../css/app.css'
import { createApp, h } from 'vue'
import type { DefineComponent } from 'vue'
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3'
import { createPinia } from 'pinia'
import { ZiggyVue } from 'ziggy-js'
import AppSelect from './Components/AppSelect.vue'
import AppColorPicker from './Components/AppColorPicker.vue'
import ToastContainer from './Components/ToastContainer.vue'
import ShortcutsReferenceModal from './Components/ShortcutsReferenceModal.vue'
import { useGlobalShortcuts } from './Composables/useKeyboardShortcuts'

const appName = import.meta.env.VITE_APP_NAME || document.title
const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue')
const templates = import.meta.glob<{ default: DefineComponent }>('./Templates/**/*.vue')
const addonPages = import.meta.glob<{ default: DefineComponent }>('../../addons/*/resources/js/Pages/**/*.vue')
const addonComponents = import.meta.glob<{ default: DefineComponent }>('../../addons/*/resources/js/Components/**/*.vue')

// Build lookup: { 'ai-assistant/Admin/Settings': importFn }
const addonPageMap: Record<string, () => Promise<{ default: DefineComponent }>> = {}

for (const [path, importFn] of Object.entries(addonPages)) {
    const match = path.match(/addons\/([^/]+)\/resources\/js\/Pages\/(.+)\.vue$/)
    if (match) {
        addonPageMap[`${match[1]}/${match[2]}`] = importFn as () => Promise<{ default: DefineComponent }>
    }
}

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
            ?? (
                name.startsWith('Addons/')
                ? addonPageMap[name.replace('Addons/', '')]
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

        useGlobalShortcuts()

        const pinia = createPinia()
        const vueApp = createApp({
            render: () => [h(App, props), h(ToastContainer), h(ShortcutsReferenceModal)],
        })

        vueApp
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .component('Link', Link)
            .component('Head', Head)
            .component('AppSelect', AppSelect)
            .component('AppColorPicker', AppColorPicker)
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
