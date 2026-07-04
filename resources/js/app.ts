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
import PageLoader from './Components/PageLoader.vue'
import { useGlobalShortcuts } from './Composables/useKeyboardShortcuts'

const appName = import.meta.env.VITE_APP_NAME || document.title
const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue')
const addonPages = import.meta.glob<{ default: DefineComponent }>('../../addons/*/resources/js/Pages/**/*.vue')
const addonComponents = import.meta.glob<{ default: DefineComponent }>('../../addons/*/resources/js/Components/**/*.vue')
const addonTemplates = import.meta.glob<{ default: DefineComponent }>('../../addons/*/resources/js/Templates/**/*.vue')

// Build lookup: { 'ai-assistant/Admin/Settings': importFn }
const addonPageMap: Record<string, () => Promise<{ default: DefineComponent }>> = {}
const addonTemplateMap: Record<string, () => Promise<{ default: DefineComponent }>> = {}

for (const [path, importFn] of Object.entries(addonPages)) {
    const match = path.match(/addons\/([^/]+)\/resources\/js\/Pages\/(.+)\.vue$/)
    if (match) {
        addonPageMap[`${match[1]}/${match[2]}`] = importFn as () => Promise<{ default: DefineComponent }>
    }
}

for (const [path, importFn] of Object.entries(addonTemplates)) {
    const match = path.match(/addons\/([^/]+)\/resources\/js\/Templates\/(.+)\.vue$/)
    if (match) {
        addonTemplateMap[`${match[1]}/${match[2]}`] = importFn as () => Promise<{ default: DefineComponent }>
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

function applyThemeDefaults(themeSettings: Record<string, string> | undefined) {
    if (!themeSettings) return
    const html = document.documentElement

    // ── Theme Default Mode ──────────────────────────────────
    const modeSetting = themeSettings.theme_default_mode
    if (modeSetting === 'dark' || modeSetting === 'light') {
        const stored = localStorage.getItem('vueuse-color-scheme')
        if (stored === null) {
            localStorage.setItem('vueuse-color-scheme', modeSetting === 'dark' ? 'dark' : '')
        }
    }

    // ── Smooth Scroll ───────────────────────────────────────
    const scroll = themeSettings.smooth_scroll
    if (scroll === 'true' || scroll === '1') {
        html.style.scrollBehavior = 'smooth'
    } else {
        html.style.scrollBehavior = ''
    }

    // ── Container Width ─────────────────────────────────────
    // Apply a CSS rule that makes all frontend containers respect the setting
    const isAdmin = window.location.pathname.startsWith('/admin')
    const cw = themeSettings.container_width
    const widthMap: Record<string, string> = {
        full: '100%',
        '1080px': '1080px',
        '1280px': '1280px',
        '1536px': '1536px',
    }
    const pageWidth = widthMap[cw] || '1280px'
    html.style.setProperty('--page-width', pageWidth)
    // Inject a live CSS rule scoped to non-admin pages
    let styleTag = document.getElementById('theme-width-css') as HTMLStyleElement | null
    if (!styleTag) {
        styleTag = document.createElement('style')
        styleTag.id = 'theme-width-css'
        document.head.appendChild(styleTag)
    }
    styleTag.textContent = isAdmin ? '' : `
        [style*="--page-width: ${pageWidth}"] .mx-auto {
            max-width: var(--page-width) !important;
        }
        .max-w-7xl {
            max-width: var(--page-width, 1280px) !important;
        }
    `

    // ── Page Loading Animation ───────────────────────────────
    html.dataset.pageLoading = !isAdmin ? (themeSettings.page_loading_animation || 'none') : 'none'
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.vue`]
            ?? (
                name.startsWith('Templates/')
                ? addonTemplateMap[`ai-chatbot/${name.slice('Templates/'.length)}`]
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
        const initialProps = props.initialPage.props as Record<string, any>
        syncDocumentLocale(initialProps.locale)
        router.on('navigate', (event) => syncDocumentLocale(event.detail.page.props.locale))

        // Apply theme default settings on initial load
        const themeSettings = initialProps.appearanceThemeSettings as Record<string, string> | undefined
        applyThemeDefaults(themeSettings)

        // Re-apply theme defaults after each navigation (for settings that may change)
        router.on('navigate', (event) => {
            const pageProps = event.detail.page.props as Record<string, any>
            applyThemeDefaults(pageProps.appearanceThemeSettings as Record<string, string> | undefined)
        })

        useGlobalShortcuts()

        const pinia = createPinia()
        const vueApp = createApp({
            render: () => [h(App, props), h(ToastContainer), h(ShortcutsReferenceModal), h(PageLoader)],
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

// Listen for events from embedded tool frames (e.g. copy requests)
window.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'makeai-embed-copy' && event.data.text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(event.data.text).catch(() => {});
        }
    }
})
