import '../css/app.css'
import { createApp, h } from 'vue'
import type { DefineComponent } from 'vue'
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3'
import { createPinia } from 'pinia'
import { ZiggyVue } from 'ziggy-js'
import AppSelect from './Components/UI/AppSelect.vue'
import AppColorPicker from './Components/UI/AppColorPicker.vue'
import ToastContainer from './Components/UI/ToastContainer.vue'
import ShortcutsReferenceModal from '@themes/default/js/Components/ShortcutsReferenceModal.vue'
import PageLoader from './Components/UI/PageLoader.vue'
import { useGlobalShortcuts } from './Composables/useKeyboardShortcuts'
import { syncDefaultColorScheme } from './lib/colorScheme'

// Document-title site name for the "<page title> - <site name>" pattern. Sourced
// from the server-shared `appName` prop — which is settings('site_name') and itself
// falls back to 'MakeAI'. Deliberately NOT VITE_APP_NAME/APP_NAME: a buyer's stray
// APP_NAME (e.g. the Laravel default) must never leak into user-facing page titles.
function resolveSiteName(): string {
    try {
        const el = document.getElementById('app')
        const initial = el?.dataset.page ? JSON.parse(el.dataset.page) : null
        const name = initial?.props?.appName
        return typeof name === 'string' && name.trim() !== '' ? name : 'MakeAI'
    } catch {
        return 'MakeAI'
    }
}
const appName = resolveSiteName()

// The current Inertia page, so the document-title format can vary by page type.
// Set in setup() from the initial page and refreshed on every client navigation.
let currentPage: { component?: string; props?: Record<string, any> } | null = null

// Homepage tagline when the admin hasn't set site_tagline.
const HOME_TAGLINE_FALLBACK = 'One platform. Every AI tool.'

// Fallback separator between a frontend page title and the site name, used until the
// admin's `title_separator` theme setting resolves. Mirrors the same fallback in
// ThemeSettingsService::getTitleSeparator().
const TITLE_SEPARATOR_FALLBACK = '|'

/**
 * Compose the document <title> per page type (site name always falls back to
 * 'MakeAI', never APP_NAME):
 *   - Home:            "<site name> <separator> <tagline>", or the admin's homepage
 *                      meta_title verbatim — both already composed server-side and
 *                      handed over as seo.title, so the tab can't drift from the tag.
 *   - Admin/*:         "<page title> - <site name>"
 *   - everything else: "<page title> <separator> <site name>"
 * For public pages the page title may already be "Heading - meta_title"
 * (composed via usePageTitle); this only appends the site suffix.
 *
 * The frontend separator is admin-configurable (Appearance → Theme → General). This
 * MUST match document_title() in app/Helpers/helpers.php: the server composes the
 * <title> for the initial HTML and this recomposes it on mount, so any drift shows up
 * as the tab flipping after load. Admin pages are deliberately excluded — their " - "
 * is fixed and the setting is frontend-only.
 */
function formatDocumentTitle(pageTitle: string): string {
    const props = currentPage?.props ?? {}
    const site = typeof props.appName === 'string' && props.appName.trim() !== '' ? props.appName : appName
    const component = currentPage?.component ?? ''

    const separator = String(props?.appearanceThemeSettings?.title_separator ?? '').trim() || TITLE_SEPARATOR_FALLBACK

    if (component === 'Home') {
        // Prefer the server's composed title (HomepageController::buildSeo) so an admin
        // homepage meta_title is honoured and the tab matches <title inertia> exactly.
        // The local compose is only a fallback for when the seo prop is absent.
        const serverTitle = String(props?.seo?.title ?? '').trim()
        if (serverTitle) return serverTitle

        const tagline = String(props?.branding?.site_tagline ?? '').trim() || HOME_TAGLINE_FALLBACK
        return `${site} ${separator} ${tagline}`
    }
    // The installer composes its own complete document title (e.g. "MakeAI -
    // Installation Wizard"); never append the site suffix to it.
    if (component.startsWith('Install/')) {
        return pageTitle || site
    }

    // Core admin pages are 'Admin/*'; addon admin pages render as 'Addons/<slug>/Admin/*'.
    // Both must share the fixed " - " admin style so an addon's browser tab matches the rest
    // of the panel (they otherwise fell through to the frontend "<title> | <site>" format).
    if (component.startsWith('Admin/') || /^Addons\/[^/]+\/Admin\//.test(component)) {
        return pageTitle ? `${pageTitle} - ${site}` : site
    }

    return pageTitle ? `${pageTitle} ${separator} ${site}` : site
}
const pages = import.meta.glob<{ default: DefineComponent }>([
    './Pages/**/*.vue',
    '../themes/*/js/**/*.vue',
    '!../themes/*/js/Components/**/*.vue',
    '!../themes/*/js/Layouts/**/*.vue',
    '!../themes/*/js/Sections/**/*.vue',
])
// The installer is a self-contained package under resources/installer/. Its only
// rendered page is `Install/Index`; components/layout are static-imported by Index.vue.
const installerPages = import.meta.glob<{ default: DefineComponent }>('../installer/js/**/*.vue')
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

// Read the resolved frontend theme settings straight from the initial page data
// that @inertia embeds in #app[data-page]. We need this BEFORE createInertiaApp(),
// where the `progress` option is fixed — setup()'s props aren't available yet.
function readBootThemeSettings(): Record<string, unknown> {
    const el = document.getElementById('app')
    if (!el?.dataset.page) return {}
    try {
        const page = JSON.parse(el.dataset.page)
        return (page?.props?.appearanceThemeSettings ?? {}) as Record<string, unknown>
    } catch {
        return {}
    }
}

function isSettingEnabled(value: unknown, fallback: boolean): boolean {
    if (typeof value === 'boolean') return value
    if (typeof value === 'number') return value !== 0
    if (typeof value === 'string') return !['false', '0', 'no', 'off', ''].includes(value.trim().toLowerCase())
    return fallback
}

// The navigation progress bar is a frontend theme setting; the admin panel always
// keeps it. This is tracked as a MUTABLE flag rather than baked into createInertiaApp's
// static `progress` option, because admin and the frontend are the same Inertia app:
// the option is fixed at first load, so a static admin-vs-frontend / on-vs-off decision
// would freeze for the whole SPA session and ignore later navigations. Instead we keep
// the bar registered and suppress it per-visit (see the router 'before' hook below).
const bootThemeSettings = readBootThemeSettings()
let navProgressSettingEnabled = isSettingEnabled(bootThemeSettings.nav_progress_bar, true)
const navProgressColor = typeof bootThemeSettings.primary_color === 'string' && bootThemeSettings.primary_color.trim()
    ? bootThemeSettings.primary_color.trim()
    : '#1F75FE'

// Show the bar on admin pages regardless; on the frontend, honour the theme setting.
// Decided per-navigation from the destination URL so it reacts as the user moves around.
function shouldShowNavProgress(destinationUrl: unknown): boolean {
    let pathname = ''
    try {
        pathname = destinationUrl instanceof URL
            ? destinationUrl.pathname
            : new URL(String(destinationUrl), window.location.origin).pathname
    } catch {
        pathname = ''
    }
    return pathname.startsWith('/admin') || navProgressSettingEnabled
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
    syncDefaultColorScheme(themeSettings.theme_default_mode)

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
        main .mx-auto {
            max-width: var(--page-width) !important;
        }
        main .max-w-7xl {
            max-width: var(--page-width, 1280px) !important;
        }
    `

    // ── Page Loading Animation ───────────────────────────────
    html.dataset.pageLoading = !isAdmin ? (themeSettings.page_loading_animation || 'none') : 'none'
}

createInertiaApp({
    // Per-page-type document title (Home / Admin / other). See formatDocumentTitle.
    title: (title) => formatDocumentTitle(title),
    resolve: async (name) => {
        const page = pages[`../themes/default/js/${name}.vue`]
            ?? pages[`./Pages/${name}.vue`]
            ?? (
                name.startsWith('Install/')
                ? installerPages[`../installer/js/${name.slice('Install/'.length)}.vue`]
                : null
            )
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
        // Seed the page-type tracker before the first render so formatDocumentTitle()
        // picks the right title format on initial load too.
        currentPage = props.initialPage as { component?: string; props?: Record<string, any> }
        syncDocumentLocale(initialProps.locale)
        router.on('navigate', (event) => {
            currentPage = event.detail.page as { component?: string; props?: Record<string, any> }
            syncDocumentLocale(event.detail.page.props.locale)
        })

        // Apply theme default settings on initial load
        const themeSettings = initialProps.appearanceThemeSettings as Record<string, string> | undefined
        applyThemeDefaults(themeSettings)

        // Re-apply theme defaults after each navigation (for settings that may change)
        router.on('navigate', (event) => {
            const pageProps = event.detail.page.props as Record<string, any>
            const themeSettings = pageProps.appearanceThemeSettings as Record<string, unknown> | undefined
            applyThemeDefaults(themeSettings as Record<string, string> | undefined)
            // Keep the progress-bar flag in sync when the setting changes (after a save/reload).
            if (themeSettings && 'nav_progress_bar' in themeSettings) {
                navProgressSettingEnabled = isSettingEnabled(themeSettings.nav_progress_bar, true)
            }
        })

        // Suppress the (still-registered) progress bar per visit when it shouldn't show.
        // 'before' fires before Inertia's progress 'start' handler and shares the same
        // visit object, so flipping showProgress here cleanly hides the bar for that visit.
        router.on('before', (event) => {
            if (!shouldShowNavProgress((event.detail.visit as { url?: unknown }).url)) {
                (event.detail.visit as { showProgress?: boolean }).showProgress = false
            }
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
        color: navProgressColor,
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
