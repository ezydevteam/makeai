<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import type { CSSProperties } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import LiveSearch from '@/Components/LiveSearch.vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import SocialFollow from '@/Components/SocialFollow.vue'

type HeaderStyle = CSSProperties & Record<`--${string}`, string>

const { isDark, toggleDark } = useTheme()
const { t } = useTranslate()
const page = usePage()

const user = computed(() => page.props.auth?.user as any)
const rawHeaderConfig = computed(() => page.props.headerConfig as any)
const headerConfig = computed(() => rawHeaderConfig.value?.main ?? rawHeaderConfig.value)
const topHeaderConfig = computed(() => rawHeaderConfig.value?.top)
const mobileHeaderConfig = computed(() => rawHeaderConfig.value?.mobile)
const mobileBottomHeaderConfig = computed(() => rawHeaderConfig.value?.mobile_bottom)
const activeBlocks = computed(() => {
    return headerConfig.value?.blocks?.filter((b: any) => b.enabled) || []
})
const activeTopBlocks = computed(() => {
    return topHeaderConfig.value?.blocks?.filter((b: any) => b.enabled) || []
})
const activeMobileBlocks = computed(() => {
    return mobileHeaderConfig.value?.blocks?.filter((b: any) => b.enabled) || []
})
const activeMobileBottomBlocks = computed(() => {
    return mobileBottomHeaderConfig.value?.blocks?.filter((b: any) => b.enabled) || []
})
const mobilePrimaryBlocks = computed(() => activeMobileBlocks.value.filter((block: any) => ['hamburger', 'logo'].includes(block.type)))
const mobileActionBlocks = computed(() => activeMobileBlocks.value.filter((block: any) => !['hamburger', 'logo'].includes(block.type)))
const mobileHamburgerBlock = computed(() => activeMobileBlocks.value.find((block: any) => block.type === 'hamburger'))
const mobileBottomHamburgerBlock = computed(() => activeMobileBottomBlocks.value.find((block: any) => block.type === 'hamburger'))
const mobileDrawerMenuSlug = computed(() => mobileHamburgerBlock.value?.config?.menu_slug || mobileBottomHamburgerBlock.value?.config?.menu_slug || 'mobile')
const mobileDrawerTitle = computed(() => mobileHamburgerBlock.value?.config?.drawer_title || mobileBottomHamburgerBlock.value?.config?.drawer_title || page.props.appName)

const profileOpen = ref(false)
const mobileMenuOpen = ref(false)
const mobileSearchOpen = ref(false)
const desktopSearchBlockId = ref<string | null>(null)
const mobileSearchConfig = ref<Record<string, unknown> | null>(null)
const lastScrollY = ref(0)
const scrollY = ref(0)
const scrollDirection = ref<'up' | 'down'>('down')
const scrollProgress = ref(0)

const logout = () => router.post(route('logout'))

const globalMenus = computed(() => page.props.globalMenus as Array<any> || [])
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))

const getMenu = (slug: string) => {
    return globalMenus.value.find((m: any) => m.slug === slug)
}

const visibleMenuItems = (slug: string) => {
    return getMenu(slug)?.items?.filter((item: any) => item.is_active !== false) || []
}

const menuItemId = (item: any) => item.id ?? item.key ?? item.url
const menuItemHref = (item: any) => String(item.final_url || item.url || '#')
const menuParentId = (item: any) => item.parent_id ?? item.parentId ?? null
const topMenuItems = (slug: string) => {
    return visibleMenuItems(slug).filter((item: any) => !menuParentId(item))
}
const submenuItems = (slug: string, parentId: string | number) => {
    return visibleMenuItems(slug).filter((item: any) => String(menuParentId(item)) === String(parentId))
}

const isActive = (url: string) => {
    if (!url) return false;
    const path = new URL(url, window.location.origin).pathname;
    return window.location.pathname === path;
}

const stickyBehavior = (config: any) => {
    if (config?.sticky_behavior) return config.sticky_behavior
    if (config?.sticky === false) return 'none'

    return config?.hide_on_scroll ? 'upscroll' : 'always'
}

const stickyOffset = (config: any) => scrollDirection.value === 'up'
    ? Number(config?.upscroll_offset ?? 80)
    : Number(config?.downscroll_offset ?? 80)

const isHeaderVisible = (config: any) => {
    const behavior = stickyBehavior(config)

    if (behavior === 'none' || behavior === 'always') return true
    if (scrollY.value < stickyOffset(config)) return true

    return behavior === 'upscroll'
        ? scrollDirection.value === 'up'
        : scrollDirection.value === 'down'
}

const containerClass = (config: any, mobile = false) => {
    if (config?.container_width === 'full') return 'w-full px-4 sm:px-6'
    if (config?.container_width === 'boxed') return mobile ? 'mx-auto w-full max-w-[1080px] px-4' : 'mx-auto w-full max-w-[1080px] px-4 sm:px-6'

    return mobile ? 'mx-auto w-full max-w-7xl px-4' : 'mx-auto w-full max-w-7xl px-4 sm:px-6'
}

const sectionPositionClass = (config: any) => stickyBehavior(config) === 'none' ? 'relative z-50' : 'sticky z-50'
const sectionTransitionClass = (config: any) => config?.transition_enabled === false ? '' : 'transition-all duration-300 ease-out'
const sectionShadowClass = (config: any) => config?.shadow ? 'shadow-md shadow-gray-900/10 dark:shadow-black/20' : ''
const sectionVisibilityClass = (config: any) => isHeaderVisible(config) ? 'translate-y-0' : '-translate-y-full pointer-events-none'
const isBottomHeaderVisible = (config: any) => {
    const behavior = stickyBehavior(config)

    if (behavior === 'none' || behavior === 'always') return true

    const offset = behavior === 'upscroll'
        ? Number(config?.upscroll_offset ?? 80)
        : Number(config?.downscroll_offset ?? 80)

    if (scrollY.value < offset) return true

    return behavior === 'upscroll'
        ? scrollDirection.value === 'up'
        : scrollDirection.value === 'down'
}
const bottomSectionVisibilityClass = (config: any) => isBottomHeaderVisible(config) ? 'translate-y-0' : 'translate-y-full pointer-events-none'

const stickyTop = (section: 'top' | 'main' | 'mobile' | 'mobile_bottom') => {
    if (section !== 'main') return '0px'
    if (!topHeaderConfig.value?.enabled || stickyBehavior(topHeaderConfig.value) === 'none' || !isHeaderVisible(topHeaderConfig.value)) return '0px'

    return `${Number(topHeaderConfig.value.height ?? 40)}px`
}

const sectionStyle = (config: any, section: 'top' | 'main' | 'mobile' | 'mobile_bottom', defaultHeight: number) => ({
    height: `${Number(config?.height ?? defaultHeight)}px`,
    top: stickyTop(section),
})

const mobileIconButtonClass = 'flex h-10 w-10 items-center justify-center rounded-xl bg-gray-50 text-gray-600 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
const mobileBottomItemClass = 'flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
const configString = (config: Record<string, unknown> | undefined, key: string, fallback = '') => {
    const value = config?.[key]

    return typeof value === 'string' ? value : fallback
}
const configBoolean = (config: Record<string, unknown> | undefined, key: string, fallback = true) => {
    const value = config?.[key]

    return typeof value === 'boolean' ? value : fallback
}
const mobileCtaClass = (block: any, bottom = false) => [
    bottom ? 'flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-bold transition-colors' : (block.config?.icon_only ? 'inline-flex h-10 w-10 items-center justify-center rounded-xl text-sm font-bold transition-colors' : 'inline-flex h-10 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-bold transition-colors'),
    block.config?.style === 'outline' ? 'border border-primary-600 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : '',
    block.config?.style === 'ghost' ? 'text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : '',
    block.config?.style === 'bg_light' ? 'bg-gray-50 text-gray-600 hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300' : '',
    !block.config?.style || block.config?.style === 'filled' ? 'bg-primary-600 text-white shadow-lg shadow-primary-600/20 hover:bg-primary-500' : '',
]
const blockIconClass = (block: any, fallback = '') => String(block.config?.icon_class || fallback)
const ctaIconSizeClass = (bottom = false) => bottom ? 'text-xl leading-none' : 'text-[20px] leading-none'
const isIconOnly = (block: any) => Boolean(block.config?.icon_only)
const blockText = (block: any, fallback: string) => String(block.config?.text || fallback)
const blockLabel = (block: any, fallback: string) => String(block.config?.label || fallback)
const showBlockLabel = (block: any) => block.config?.show_label !== false
const languageShowFlag = (block: any) => block.config?.show_flag !== false
const languageShowName = (block: any) => block.config?.show_name !== false
const socialDisplayMode = (block: any): 'icons' | 'counts' | 'cards' => {
    const mode = String(block.config?.display_mode || 'icons')

    return ['icons', 'counts', 'cards'].includes(mode) ? mode as 'icons' | 'counts' | 'cards' : 'icons'
}

const menuAlignmentClass = (block: any) => {
    const alignment = String(block.config?.alignment || 'center')

    if (alignment === 'left') return 'justify-start'
    if (alignment === 'right') return 'justify-end'

    return 'justify-center'
}
const menuHoverStyleClass = (block: any) => {
    const style = String(block.config?.hover_style || 'underline')

    return ['underline', 'pill', 'box', 'glow'].includes(style) ? `header-menu-hover-${style}` : 'header-menu-hover-underline'
}
const menuStyle = (block: any): CSSProperties => {
    const style: HeaderStyle = {}
    const textColor = configString(block.config, 'text_color')
    const hoverColor = configString(block.config, 'hover_color')

    if (textColor) style['--header-menu-text-color'] = textColor
    if (hoverColor) style['--header-menu-hover-color'] = hoverColor

    return style
}
const submenuStyle = (block: any): CSSProperties => {
    const style: HeaderStyle = {}
    const bgColor = configString(block.config, 'submenu_bg_color')
    const textColor = configString(block.config, 'submenu_text_color') || configString(block.config, 'text_color')

    if (bgColor) style.backgroundColor = bgColor
    if (textColor) style['--header-submenu-text-color'] = textColor

    return style
}
const blockVisualStyle = (block: any): CSSProperties => {
    const style: CSSProperties = {}
    const iconColor = configString(block.config, 'icon_color')
    const bgColor = configString(block.config, 'bg_color')
    const textColor = configString(block.config, 'text_color')

    if (iconColor) style.color = iconColor
    if (bgColor && (block.config?.bg_style === 'custom' || block.type === 'cta_button')) style.backgroundColor = bgColor
    if (textColor && block.type === 'cta_button') style.color = textColor

    return style
}
const iconSurfaceClass = (block: any, baseClass: string) => [
    baseClass,
    block.config?.bg_style === 'transparent' ? 'bg-transparent dark:bg-transparent' : '',
    block.config?.bg_style === 'filled' ? 'bg-primary-600 text-white hover:bg-primary-500 hover:text-white dark:bg-primary-600 dark:text-white dark:hover:bg-primary-500' : '',
    block.config?.bg_style === 'custom' ? 'hover:opacity-90' : '',
]
const searchLiveEnabled = (config?: Record<string, unknown>) => configBoolean(config, 'enable_live_search', true)
const searchSuggestionsEnabled = (config?: Record<string, unknown>) => configBoolean(config, 'show_suggestions', true)
const searchStyle = (block: any) => String(block.config?.search_style || 'box')
const toggleDesktopSearch = (block: any) => {
    desktopSearchBlockId.value = desktopSearchBlockId.value === String(block.id) ? null : String(block.id)
}
const toggleMobileSearch = (block: any) => {
    mobileSearchConfig.value = block.config ?? null
    mobileSearchOpen.value = !mobileSearchOpen.value
    mobileMenuOpen.value = false
}

const userIconHref = computed(() => user.value ? route('user.dashboard') : '/login')
const userIconLabel = computed(() => user.value ? t('Dashboard') : t('Sign In'))
const closeMobileMenu = () => {
    mobileMenuOpen.value = false
}

const updateScrollState = () => {
    const currentY = Math.max(window.scrollY, 0)
    scrollDirection.value = currentY >= lastScrollY.value ? 'down' : 'up'
    scrollY.value = currentY
    lastScrollY.value = currentY

    const scrollable = Math.max(document.documentElement.scrollHeight - window.innerHeight, 1)
    scrollProgress.value = Math.min(100, Math.max(0, (currentY / scrollable) * 100))
}

const close = () => { profileOpen.value = false }
const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeMobileMenu()
        mobileSearchOpen.value = false
    }
}

watch(mobileMenuOpen, (open) => {
    if (typeof document === 'undefined') return
    document.documentElement.classList.toggle('overflow-hidden', open)
})

onMounted(() => {
    document.addEventListener('click', close)
    document.addEventListener('keydown', closeOnEscape)
    updateScrollState()
    window.addEventListener('scroll', updateScrollState, { passive: true })
})
onUnmounted(() => {
    document.removeEventListener('click', close)
    document.removeEventListener('keydown', closeOnEscape)
    window.removeEventListener('scroll', updateScrollState)
    document.documentElement.classList.remove('overflow-hidden')
})
</script>

<template>
    <header
        v-if="topHeaderConfig?.enabled"
        :class="[sectionPositionClass(topHeaderConfig), sectionTransitionClass(topHeaderConfig), sectionShadowClass(topHeaderConfig), sectionVisibilityClass(topHeaderConfig)]"
        :style="sectionStyle(topHeaderConfig, 'top', 40)"
        class="hidden w-full border-b border-gray-200 bg-gray-50/90 backdrop-blur-md dark:border-white/5 dark:bg-surface-950/80 md:block"
    >
        <div class="flex h-full items-center justify-between gap-3" :class="containerClass(topHeaderConfig)">
            <div v-for="block in activeTopBlocks" :key="block.id" class="flex items-center">
                <nav v-if="block.type === 'navigation'" class="flex items-center gap-1" :class="[menuAlignmentClass(block), menuHoverStyleClass(block)]" :style="menuStyle(block)">
                    <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative">
                        <a :href="menuItemHref(item)" :target="item.target" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link rounded-lg px-3 py-1.5 text-xs font-semibold transition">
                            {{ item.label || item.title }}
                        </a>
                        <div
                            v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                            class="invisible absolute inset-inline-start-0 top-full z-50 mt-2 min-w-44 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                            :style="submenuStyle(block)"
                        >
                            <a
                                v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))"
                                :key="menuItemId(child)"
                                :href="menuItemHref(child)"
                                :target="child.target"
                                class="header-submenu-link block rounded-lg px-3 py-2 text-xs font-semibold transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20"
                            >
                                {{ child.label || child.title }}
                            </a>
                        </div>
                    </div>
                </nav>
                <LanguageSwitcher v-else-if="block.type === 'language_switcher'" :show-flag="languageShowFlag(block)" :show-name="languageShowName(block)" />
                <Link v-else-if="block.type === 'cta_button'" :href="String(block.config.link || '/register')" :class="mobileCtaClass(block)" :style="blockVisualStyle(block)">
                    <i v-if="blockIconClass(block) || isIconOnly(block)" :class="[blockIconClass(block, 'ti ti-rocket'), ctaIconSizeClass()]" aria-hidden="true" />
                    <span v-if="!isIconOnly(block)">{{ blockText(block, t('Get Started')) }}</span>
                </Link>
                <SocialFollow v-else-if="block.type === 'social_icons'" :style="socialDisplayMode(block)" />
                <div v-else-if="block.type === 'custom_html'" class="text-xs text-gray-500">{{ block.config.content }}</div>
            </div>
        </div>
        <div v-if="topHeaderConfig?.progressbar" class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </header>

    <header :class="[
        sectionPositionClass(headerConfig),
        sectionTransitionClass(headerConfig),
        sectionShadowClass(headerConfig),
        sectionVisibilityClass(headerConfig),
        headerConfig?.transparent_homepage && route().current('home') ? 'absolute w-full bg-transparent border-none' : 'bg-white/90 dark:bg-surface-900/80 backdrop-blur-md border-b border-gray-200 dark:border-white/5',
    ]" :style="sectionStyle(headerConfig, 'main', 72)" class="hidden w-full shrink-0 md:block">
        <div class="flex h-full items-center gap-3 justify-start" :class="containerClass(headerConfig)">
            
            <div v-for="block in activeBlocks" :key="block.id" 
                 :class="[
                    block.type === 'navigation' && headerConfig?.layout !== 'centered' ? 'flex-1 flex' : '',
                    block.type === 'navigation' && headerConfig?.layout === 'centered' ? 'w-full flex mt-2' : '',
                    block.type === 'logo' && headerConfig?.layout === 'classic' && !activeBlocks.find((b: any) => b.type === 'navigation') ? 'flex-1' : ''
                 ]" class="flex items-center">
                
                <!-- LOGO -->
                <Link v-if="block.type === 'logo'" :href="block.config.link || '/'" class="flex items-center gap-2.5 group">
                    <img v-if="block.config.image" :src="String(block.config.image)" :alt="String(block.config.alt || block.config.text || page.props.appName)" class="h-9 w-auto max-w-36 shrink-0 object-contain" />
                    <div v-else class="w-8 h-8 bg-gradient-to-br from-primary-500 to-accent-500 rounded-xl flex items-center justify-center shadow-lg shadow-primary-500/20 shrink-0 group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                    </div>
                    <span v-if="block.config.show_text" class="text-lg font-bold text-gray-900 dark:text-white tracking-tight hidden sm:block whitespace-nowrap">{{ block.config.text || page.props.appName }}</span>
                </Link>

                <!-- NAVIGATION -->
                <nav v-else-if="block.type === 'navigation'" class="hidden w-full md:flex items-center gap-1" :class="[menuAlignmentClass(block), menuHoverStyleClass(block)]" :style="menuStyle(block)">
                    <template v-if="getMenu(block.config.menu_slug)">
                        <div v-for="item in topMenuItems(block.config.menu_slug)" :key="menuItemId(item)" class="group relative">
                            <a :href="menuItemHref(item)" :target="item.target" :class="{ 'header-menu-link-active': isActive(menuItemHref(item)) }" class="header-menu-link px-3.5 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap">
                                {{ item.label || item.title }}
                            </a>
                            <div
                                v-if="submenuItems(block.config.menu_slug, menuItemId(item)).length"
                                class="invisible absolute inset-inline-start-0 top-full z-50 mt-3 min-w-52 rounded-xl border border-gray-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 dark:border-surface-700 dark:bg-surface-900"
                                :style="submenuStyle(block)"
                            >
                                <a
                                    v-for="child in submenuItems(block.config.menu_slug, menuItemId(item))"
                                    :key="menuItemId(child)"
                                    :href="menuItemHref(child)"
                                    :target="child.target"
                                    class="header-submenu-link block rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20"
                                >
                                    {{ child.label || child.title }}
                                </a>
                            </div>
                        </div>
                    </template>
                </nav>

                <!-- SEARCH BAR -->
                <div v-else-if="block.type === 'search'" class="hidden md:block relative">
                    <LiveSearch
                        v-if="searchStyle(block) === 'box'"
                        context="public"
                        :compact="Boolean(block.config.compact)"
                        :enable-live-search="searchLiveEnabled(block.config)"
                        :show-suggestions="searchSuggestionsEnabled(block.config)"
                    />
                    <div v-else class="relative">
                        <button type="button" :class="iconSurfaceClass(block, mobileIconButtonClass)" :style="blockVisualStyle(block)" :aria-label="t('Search')" @click="toggleDesktopSearch(block)">
                            <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" /></svg>
                        </button>
                        <div v-if="desktopSearchBlockId === String(block.id)" class="absolute inset-inline-end-0 top-full z-50 mt-3 w-80 rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-surface-700 dark:bg-surface-900">
                            <LiveSearch context="public" compact :enable-live-search="searchLiveEnabled(block.config)" :show-suggestions="searchSuggestionsEnabled(block.config)" />
                        </div>
                    </div>
                </div>

                <LanguageSwitcher v-else-if="block.type === 'language_switcher'" :show-flag="languageShowFlag(block)" :show-name="languageShowName(block)" />

                <NotificationBell v-else-if="block.type === 'notification_bell' && user" context="user" />

                <div v-else-if="block.type === 'credit_balance' && user" class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-primary-50 px-3 text-sm font-bold text-primary-700 dark:bg-primary-900/20 dark:text-primary-300" :style="blockVisualStyle(block)">
                    <i :class="blockIconClass(block, 'ti ti-bolt')" class="text-[18px]" aria-hidden="true" />
                    <span>{{ user.credits ?? 0 }}</span>
                    <span class="text-xs font-semibold text-primary-600/70 dark:text-primary-300/70">{{ blockLabel(block, t('Credits')) }}</span>
                </div>

                <SocialFollow v-else-if="block.type === 'social_icons'" :style="socialDisplayMode(block)" />

                <!-- DARK MODE TOGGLE -->
                <button v-else-if="block.type === 'dark_mode'" @click="toggleDark()" :class="iconSurfaceClass(block, 'w-10 h-10 flex items-center justify-center rounded-xl bg-gray-50 dark:bg-surface-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 transition-all shrink-0')" :style="blockVisualStyle(block)">
                    <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </button>

                <!-- CTA BUTTON -->
                <Link v-else-if="block.type === 'cta_button'" :href="block.config.link" class="rounded-xl text-sm font-bold transition-all whitespace-nowrap shrink-0" :style="blockVisualStyle(block)" :class="[
                    isIconOnly(block) ? 'flex h-10 w-10 items-center justify-center' : 'px-5 py-2',
                    block.config.style === 'filled' ? 'bg-primary-600 text-white hover:bg-primary-500 shadow-lg shadow-primary-600/20' : '',
                    block.config.style === 'bg_light' ? 'bg-gray-50 text-gray-600 hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300' : '',
                    block.config.style === 'outline' ? 'border-2 border-primary-600 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : '',
                    block.config.style === 'ghost' ? 'text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : ''
                ]">
                    <span class="inline-flex items-center gap-1.5">
                        <i v-if="blockIconClass(block) || isIconOnly(block)" :class="[blockIconClass(block, 'ti ti-rocket'), ctaIconSizeClass()]" aria-hidden="true" />
                        <span v-if="!isIconOnly(block)">{{ blockText(block, t('Get Started')) }}</span>
                    </span>
                </Link>

                <!-- USER MENU -->
                <template v-else-if="block.type === 'user_menu'">
                    <div v-if="user" class="relative flex items-center" @click.stop>
                        <div v-if="block.config.show_credits" class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-primary-500/10 border border-primary-500/20 rounded-lg mr-2 rtl:mr-0 rtl:ml-2">
                            <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                            <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">{{ user.credits ?? 0 }}</span>
                        </div>

                        <button @click="profileOpen = !profileOpen" class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
                            <div v-if="block.config.show_avatar" class="w-8 h-8 bg-gradient-to-br from-primary-500 to-accent-500 rounded-lg flex items-center justify-center text-white text-sm font-bold shrink-0">
                                {{ user.name?.charAt(0) ?? 'U' }}
                            </div>
                            <svg class="w-4 h-4 text-gray-500 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>

                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="profileOpen" class="absolute right-0 rtl:right-auto rtl:left-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-50">
                                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-white/5">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                                </div>
                                <div class="px-4 py-2 border-b border-gray-100 dark:border-white/5 sm:hidden" v-if="block.config.show_credits">
                                    <span class="text-xs text-primary-600 dark:text-primary-400 font-semibold">{{ t(':count credits', { count: user.credits ?? 0 }) }}</span>
                                </div>
                                <Link :href="route('user.dashboard')" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                    {{ t('Dashboard') }}
                                </Link>
                                <Link v-if="affiliateEnabled" :href="route('affiliate.dashboard')" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m0-12l-3 3m3-3l-3-3m0 15l3-3m-3 3l3 3M15.75 9h3A2.25 2.25 0 0121 11.25v1.5A2.25 2.25 0 0118.75 15h-3" /></svg>
                                    {{ t('Affiliate') }}
                                </Link>
                                <button @click="logout" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                    {{ t('Sign Out') }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                    <div v-else class="flex items-center gap-3">
                        <Link href="/login" class="text-sm font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors px-4 py-2">
                            {{ t('Sign In') }}
                        </Link>
                        <Link href="/register" class="bg-primary-600 hover:bg-primary-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg shadow-primary-600/20 whitespace-nowrap">
                            {{ t('Get Started') }}
                        </Link>
                    </div>
                </template>

            </div>
        </div>
        <div v-if="headerConfig?.progressbar" class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </header>

    <header
        v-if="mobileHeaderConfig?.enabled"
        :class="[sectionPositionClass(mobileHeaderConfig), sectionTransitionClass(mobileHeaderConfig), sectionShadowClass(mobileHeaderConfig), sectionVisibilityClass(mobileHeaderConfig)]"
        :style="sectionStyle(mobileHeaderConfig, 'mobile', 64)"
        class="w-full border-b border-gray-200 bg-white/95 backdrop-blur-md dark:border-white/5 dark:bg-surface-900/90 md:hidden"
    >
        <div class="flex h-full items-center justify-between gap-3" :class="containerClass({ ...mobileHeaderConfig, container_width: 'default' }, true)">
            <div class="flex items-center gap-2">
                <template v-for="block in mobilePrimaryBlocks" :key="block.id">
                    <button v-if="block.type === 'hamburger'" type="button" :class="iconSurfaceClass(block, mobileIconButtonClass)" :style="blockVisualStyle(block)" :aria-label="t('Open menu')" @click="mobileMenuOpen = !mobileMenuOpen; mobileSearchOpen = false">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </button>
                    <Link v-else-if="block.type === 'logo'" :href="block.config.link || '/'" class="flex items-center gap-2 text-base font-bold text-gray-900 dark:text-white">
                        <img v-if="block.config.image" :src="String(block.config.image)" :alt="String(block.config.alt || block.config.text || page.props.appName)" class="h-9 w-auto max-w-32 object-contain" />
                        <span v-if="!block.config.image || block.config.show_text">{{ block.config.text || page.props.appName }}</span>
                    </Link>
                </template>
            </div>
            <div class="flex items-center gap-1">
                <template v-for="block in mobileActionBlocks" :key="block.id">
                    <Link v-if="block.type === 'home_link'" :href="String(block.config.link || '/')" :class="iconSurfaceClass(block, mobileIconButtonClass)" :style="blockVisualStyle(block)" :aria-label="t('Home')">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.592 0L21.75 12M4.5 9.75v9A2.25 2.25 0 0 0 6.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25v-9" /></svg>
                    </Link>
                    <button v-else-if="block.type === 'search_icon'" type="button" :class="iconSurfaceClass(block, mobileIconButtonClass)" :style="blockVisualStyle(block)" :aria-label="t('Search')" @click="toggleMobileSearch(block)">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" /></svg>
                    </button>
                    <Link v-else-if="block.type === 'user_menu_icon'" :href="userIconHref" :class="iconSurfaceClass(block, mobileIconButtonClass)" :style="blockVisualStyle(block)" :aria-label="userIconLabel">
                        <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-[20px] leading-none']" aria-hidden="true" />
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                    </Link>
                    <NotificationBell v-else-if="block.type === 'notification_bell' && user" context="user" />
                    <LanguageSwitcher v-else-if="block.type === 'language_switcher'" :show-flag="languageShowFlag(block)" :show-name="languageShowName(block)" />
                    <Link v-else-if="block.type === 'cta_button'" :href="String(block.config.link || '/register')" :class="mobileCtaClass(block)" :style="blockVisualStyle(block)">
                        <i v-if="blockIconClass(block) || isIconOnly(block)" :class="[blockIconClass(block, 'ti ti-rocket'), ctaIconSizeClass()]" aria-hidden="true" />
                        <span v-if="!isIconOnly(block)">{{ blockText(block, t('Get Started')) }}</span>
                    </Link>
                    <button v-else-if="block.type === 'dark_mode'" type="button" :class="iconSurfaceClass(block, 'flex h-10 w-10 items-center justify-center rounded-xl bg-gray-50 text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-400 dark:hover:bg-primary-900/20 dark:hover:text-primary-300')" :style="blockVisualStyle(block)" @click="toggleDark()">
                        <svg v-if="isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>
                </template>
            </div>
        </div>
        <div v-if="mobileHeaderConfig?.progressbar" class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </header>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="mobileMenuOpen" class="fixed inset-0 z-[80] md:hidden" role="dialog" aria-modal="true" :aria-label="t('Mobile menu')">
                <button type="button" class="absolute inset-0 h-full w-full bg-gray-950/60 backdrop-blur-sm" :aria-label="t('Close menu')" @click="closeMobileMenu"></button>

                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-x-full rtl:translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="-translate-x-full rtl:translate-x-full"
                >
                    <aside class="absolute inset-y-0 left-0 flex w-[min(20rem,calc(100vw-2rem))] max-w-full flex-col border-r border-gray-200 bg-white shadow-2xl dark:border-surface-800 dark:bg-surface-900 rtl:left-auto rtl:right-0 rtl:border-l rtl:border-r-0">
                        <div class="flex h-16 items-center justify-between border-b border-gray-100 px-5 dark:border-surface-800">
                            <Link href="/" class="min-w-0 truncate text-base font-bold text-gray-900 dark:text-white" @click="closeMobileMenu">
                                {{ mobileDrawerTitle }}
                            </Link>
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-50 text-gray-500 transition hover:bg-primary-50 hover:text-primary-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" :aria-label="t('Close menu')" @click="closeMobileMenu">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <nav class="flex-1 space-y-1 overflow-y-auto p-4">
                            <a
                                v-for="item in visibleMenuItems(mobileDrawerMenuSlug)"
                                :key="item.id"
                                :href="menuItemHref(item)"
                                :target="item.target"
                                class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition"
                                :class="isActive(menuItemHref(item)) ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white'"
                                @click="closeMobileMenu"
                            >
                                <span class="truncate">{{ item.label || item.title }}</span>
                                <svg class="h-4 w-4 shrink-0 text-gray-300 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                            </a>
                            <div v-if="visibleMenuItems(mobileDrawerMenuSlug).length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-700">
                                {{ t('No menu items found.') }}
                            </div>
                        </nav>
                    </aside>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <div
        v-if="mobileSearchOpen"
        class="fixed inset-x-0 z-50 border-b border-gray-200 bg-white p-4 shadow-lg dark:border-white/5 dark:bg-surface-900 md:hidden"
        :style="{ top: mobileHeaderConfig?.enabled ? `${Number(mobileHeaderConfig?.height ?? 64)}px` : '0px' }"
    >
        <LiveSearch
            context="public"
            :enable-live-search="searchLiveEnabled(mobileSearchConfig ?? undefined)"
            :show-suggestions="searchSuggestionsEnabled(mobileSearchConfig ?? undefined)"
        />
    </div>

    <nav
        v-if="mobileBottomHeaderConfig?.enabled"
        :class="[sectionTransitionClass(mobileBottomHeaderConfig), sectionShadowClass(mobileBottomHeaderConfig), bottomSectionVisibilityClass(mobileBottomHeaderConfig)]"
        :style="{ height: `${Number(mobileBottomHeaderConfig?.height ?? 64)}px` }"
        class="fixed inset-x-0 bottom-0 z-50 transform-gpu border-t border-gray-200 bg-white/95 backdrop-blur-md will-change-transform dark:border-white/5 dark:bg-surface-900/90 md:hidden"
    >
        <div class="flex h-full items-center justify-around gap-1" :class="containerClass({ ...mobileBottomHeaderConfig, container_width: 'default' }, true)">
            <template v-for="block in activeMobileBottomBlocks" :key="block.id">
                <Link v-if="block.type === 'home_link'" :href="String(block.config.link || '/')" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Home')">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.592 0L21.75 12M4.5 9.75v9A2.25 2.25 0 0 0 6.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25v-9" /></svg>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Home')) }}</span>
                </Link>
                <button v-else-if="block.type === 'hamburger'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Open menu')" @click="mobileMenuOpen = !mobileMenuOpen; mobileSearchOpen = false">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Menu')) }}</span>
                </button>
                <button v-else-if="block.type === 'search_icon'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Search')" @click="toggleMobileSearch(block)">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" /></svg>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Search')) }}</span>
                </button>
                <Link v-else-if="block.type === 'user_menu_icon'" :href="userIconHref" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="userIconLabel">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                    <span v-if="showBlockLabel(block)">{{ user ? blockLabel(block, t('Dashboard')) : String(block.config?.guest_label || blockLabel(block, t('Sign In'))) }}</span>
                </Link>
                <div v-else-if="block.type === 'notification_bell' && user" class="flex min-w-0 flex-1 justify-center">
                    <NotificationBell
                        context="user"
                        :label="showBlockLabel(block) ? blockLabel(block, t('Notifications')) : ''"
                        root-class="flex min-w-0 w-full"
                        button-class="relative flex min-w-0 w-full flex-col items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                        dropdown-class="fixed inset-x-4 bottom-20 z-50 max-h-[70vh] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900"
                    />
                </div>
                <div v-else-if="block.type === 'language_switcher'" class="flex min-w-0 flex-1 justify-center">
                    <LanguageSwitcher variant="bottom" :show-flag="languageShowFlag(block)" :show-name="languageShowName(block)" />
                </div>
                <Link v-else-if="block.type === 'cta_button'" :href="String(block.config.link || '/register')" :class="mobileCtaClass(block, true)" :style="blockVisualStyle(block)">
                    <i v-if="blockIconClass(block, 'ti ti-arrow-right')" :class="[blockIconClass(block, 'ti ti-arrow-right'), ctaIconSizeClass(true)]" aria-hidden="true" />
                    <span v-if="!isIconOnly(block)" class="max-w-full truncate">{{ blockText(block, t('Start')) }}</span>
                </Link>
                <button v-else-if="block.type === 'dark_mode'" type="button" :class="iconSurfaceClass(block, mobileBottomItemClass)" :style="blockVisualStyle(block)" :aria-label="t('Toggle dark mode')" @click="toggleDark()">
                    <i v-if="blockIconClass(block)" :class="[blockIconClass(block), 'text-xl leading-none']" aria-hidden="true" />
                    <template v-else>
                        <svg v-if="isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 0 1 8.646 3.646 9.003 9.003 0 0 0 12 21a9.003 9.003 0 0 0 8.354-5.646z" /></svg>
                    </template>
                    <span v-if="showBlockLabel(block)">{{ blockLabel(block, t('Theme')) }}</span>
                </button>
            </template>
        </div>
        <div v-if="mobileBottomHeaderConfig?.progressbar" class="absolute inset-x-0 top-0 h-0.5 bg-primary-500/15">
            <div class="h-full bg-primary-500 transition-[width] duration-150" :style="{ width: `${scrollProgress}%` }"></div>
        </div>
    </nav>
</template>

<style scoped>
.header-menu-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    color: var(--header-menu-text-color, var(--color-gray-500));
}

.dark .header-menu-link {
    color: var(--header-menu-text-color, var(--color-gray-400));
}

.header-menu-link:hover,
.header-menu-link-active {
    color: var(--header-menu-hover-color, var(--color-primary-600));
}

.dark .header-menu-link:hover,
.dark .header-menu-link-active {
    color: var(--header-menu-hover-color, var(--color-primary-400));
}

.header-menu-hover-underline .header-menu-link::after {
    position: absolute;
    inset-inline: 0.875rem;
    bottom: 0.25rem;
    height: 2px;
    content: "";
    background: var(--header-menu-hover-color, var(--color-primary-500));
    border-radius: var(--radius-full);
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.18s ease;
}

.header-menu-hover-underline .header-menu-link:hover::after,
.header-menu-hover-underline .header-menu-link-active::after {
    transform: scaleX(1);
}

.header-menu-hover-pill .header-menu-link:hover,
.header-menu-hover-pill .header-menu-link-active {
    background: var(--color-primary-50);
}

.dark .header-menu-hover-pill .header-menu-link:hover,
.dark .header-menu-hover-pill .header-menu-link-active {
    background: rgb(16 185 129 / 0.14);
}

.header-menu-hover-box .header-menu-link:hover,
.header-menu-hover-box .header-menu-link-active {
    background: var(--surface-card);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}

.header-menu-hover-glow .header-menu-link:hover,
.header-menu-hover-glow .header-menu-link-active {
    background: rgb(16 185 129 / 0.08);
    box-shadow: 0 0 0 1px rgb(16 185 129 / 0.14), 0 8px 18px rgb(16 185 129 / 0.14);
}

.header-submenu-link {
    color: var(--header-submenu-text-color, var(--color-gray-700));
}

.dark .header-submenu-link {
    color: var(--header-submenu-text-color, var(--color-gray-200));
}
</style>
