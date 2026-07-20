<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, useSlots } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import LiveSearch from '@/Components/Utility/LiveSearch.vue'
import NotificationBell from '@themes/default/js/Components/NotificationBell.vue'
import AdminSidebar from '@/Components/Admin/AdminSidebar.vue'
import AiAssistantLoader from '../../../addons/ai-assistant/resources/js/Components/AiAssistantLoader.vue'
import LanguageSwitcher from '@/Components/Utility/LanguageSwitcher.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { mediaUrl } from '@/lib/media'

const { isDark, toggleDark } = useTheme()
const { t } = useTranslate()
const page = usePage()
const slots = useSlots()
useFlashToasts()

type Branding = {
    site_name?: string
    site_support_url?: string
}

const mobileSidebarOpen = ref(false)
const sidebarCollapsed = ref(false)
const profileOpen = ref(false)
const actionsOpen = ref(false)
const cacheClearing = ref(false)
const confirmClearCacheOpen = ref(false)
const demoDismissed = ref(false)

onMounted(() => {
    demoDismissed.value = sessionStorage.getItem('demo_admin_banner_dismissed') === '1'
})

const demoBannerColor = computed(() => {
    const color = (page.props.app as any)?.demo_banner_color ?? 'amber'
    const colors: Record<string, string> = {
        indigo: 'bg-indigo-600',
        amber: 'bg-amber-600',
        emerald: 'bg-emerald-600',
        rose: 'bg-rose-600',
        sky: 'bg-sky-600',
    }
    return colors[color] ?? colors.amber
})

const envatoUrl = computed(() => (page.props as any).app?.envato_url ?? 'https://codecanyon.net')
const branding = computed<Branding>(() => (page.props.branding as Branding | undefined) ?? {})
const siteName = computed(() => String(branding.value.site_name || page.props.appName || t('MakeAI')))
const currentYear = new Date().getFullYear()
const adminFooterLinks = computed<Array<{ label: string; href: string; icon?: string }>>(() => [
    {
        label: t('Docs'),
        href: 'https://makeai-docs.ezydev.net',
    },
    {
        label: t('Support'),
        href: 'https://makeai.ezydev.net',
    },
    {
        label: t('Buy More'),
        href: envatoUrl.value,
        icon: 'ti ti-external-link',
    },
])

function dismissDemoBanner() {
    demoDismissed.value = true
    sessionStorage.setItem('demo_admin_banner_dismissed', '1')
}

const admin = computed(() => (page.props.admin as any)?.user)
const adminDisplayName = computed(() => {
    const name = admin.value?.name?.trim() ?? ''
    return name.split(/\s+/).filter(Boolean)[0] || t('Admin')
})
const avatarPalette = [
    'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    'bg-secondary-100 text-secondary-700 dark:bg-secondary-900/30 dark:text-secondary-300',
    'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
    'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
]
const adminAvatarFallbackClass = computed(() => {
    const name = admin.value?.name?.trim() || 'Admin'
    const hash = name.split('').reduce((acc: number, char: string) => acc + char.charCodeAt(0), 0)

    return avatarPalette[hash % avatarPalette.length]
})
const adminAvatarUrl = computed(() => {
    const avatar = admin.value?.avatar

    return mediaUrl(avatar)
})
const adminActionsLabel = computed(() => t('Actions'))
const activeLanguages = computed(() => (page.props.languages as unknown[]) ?? [])
const hasMultipleLanguages = computed(() => activeLanguages.value.length > 1)
const themeToggleLabel = computed(() => isDark.value ? t('Light mode') : t('Dark mode'))
const notificationsLabel = computed(() => t('Notifications'))
const isSuperAdmin = computed(() => (page.props.admin as any)?.isSuperAdmin ?? false)
const permissions = computed(() => (page.props.admin as any)?.permissions ?? [])
const cronStatus = computed(() => page.props.cronStatus as { is_configured?: boolean; setup_url?: string; last_run_at?: string | null } | undefined)
const showCronBanner = computed(() => Boolean(admin.value && cronStatus.value && cronStatus.value.is_configured === false))
const shellInsetClass = 'px-8 sm:px-11 lg:px-11 xl:px-14 2xl:px-16'

const coreUpdate = computed(() => (page.props.admin as any)?.coreUpdate as { available?: boolean; version?: string; changelog?: string | null; show_banner?: boolean; updates_url?: string } | undefined)
const showUpdateBanner = computed(() => Boolean(coreUpdate.value?.show_banner))
const updateBannerBusy = ref(false)
function snoozeUpdate() {
    updateBannerBusy.value = true
    router.post(route('admin.system.update-banner.snooze'), {}, {
        preserveScroll: true,
        onFinish: () => { updateBannerBusy.value = false },
    })
}
function dismissUpdate() {
    updateBannerBusy.value = true
    router.post(route('admin.system.update-banner.dismiss'), {}, {
        preserveScroll: true,
        onFinish: () => { updateBannerBusy.value = false },
    })
}
const contentInsetClass = 'p-4 sm:p-5 lg:p-5 xl:p-6 2xl:p-6'
const hasHeaderTitle = computed(() => Boolean(slots.header || slots.title))
const headerIconButtonClass = 'admin-header-icon-button inline-flex h-10 w-10 items-center justify-center rounded-full bg-transparent shadow-none transition-all duration-300 ease-out focus:!outline-none focus:outline-none focus:ring-0 focus-visible:!outline-none focus-visible:outline-none focus-visible:ring-0'
const circularHeaderControlClass = '!h-10 !w-10 !min-w-10 !rounded-full !border-0 !shadow-none'
const circularHeaderIconClass = '!text-[18px] !leading-none'
const notificationBellUi = {
    triggerClass: `${headerIconButtonClass} ${circularHeaderControlClass} header-soft-icon-button header-soft-icon-button--icon-only`,
    iconClass: `ti ti-bell ${circularHeaderIconClass}`,
}
const footerLanguageSwitcherUi = {
    buttonClass: 'admin-footer-lang inline-flex min-w-0 items-center gap-1 text-xs font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-300',
}

const can = (perm: string) => {
    if (isSuperAdmin.value) return true
    return permissions.value.includes(perm)
}

const logout = () => router.post(route('admin.logout'))

const requestClearCache = () => {
    if (cacheClearing.value) return
    actionsOpen.value = false
    confirmClearCacheOpen.value = true
}

const clearCache = () => {
    if (cacheClearing.value) return
    cacheClearing.value = true
    router.post(route('admin.system.cache.clear'), {}, {
        preserveScroll: true,
        onFinish: () => {
            cacheClearing.value = false
            confirmClearCacheOpen.value = false
        },
    })
}

// Close mobile sidebar on escape
const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && mobileSidebarOpen.value) {
        mobileSidebarOpen.value = false
    }
}

// Close header dropdowns on outside click
const closeHeaderMenus = () => {
    profileOpen.value = false
    actionsOpen.value = false
}
const adminSettings = computed(() => (page.props.appearanceAdminSettings as Record<string, string>) || {})
const adminCssVars: Record<string, string> = {
    primary_color: '--admin-primary',
    accent_color: '--admin-accent',
    bg_color: '--admin-bg',
    text_primary_color: '--admin-text-primary',
    sidebar_bg: '--admin-sidebar-bg',
    sidebar_text_color: '--admin-sidebar-text',
    navbar_bg: '--admin-navbar-bg',
    navbar_text_color: '--admin-navbar-text',
    font_family: '--admin-font',
    base_font_size: '--admin-font-size',
    heading_weight: '--admin-heading-weight',
}

const applyAdminCssVars = (settings: Record<string, string> = {}) => {
    const root = document.documentElement

    Object.entries(adminCssVars).forEach(([settingKey, cssVar]) => {
        const value = settings[settingKey]?.trim()

        if (value) {
            root.style.setProperty(cssVar, value)
            return
        }

        root.style.removeProperty(cssVar)
    })
}

watch(adminSettings, (settings) => {
    applyAdminCssVars(settings)
}, { immediate: true })

onMounted(() => {
    document.documentElement.classList.add('admin-layout-root')
    document.addEventListener('click', closeHeaderMenus)
    document.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
    if (!window.location.pathname.startsWith('/admin')) {
        document.documentElement.classList.remove('admin-layout-root')
        applyAdminCssVars()
    }
    document.removeEventListener('click', closeHeaderMenus)
    document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <div class="admin-layout min-h-screen transition-colors duration-300 flex">
        <!-- Desktop Sidebar -->
        <div class="hidden lg:block shrink-0">
            <AdminSidebar :collapsed="sidebarCollapsed" @toggle="sidebarCollapsed = !sidebarCollapsed" />
        </div>

        <!-- Mobile Sidebar Overlay -->
        <Teleport to="body">
            <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="mobileSidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="mobileSidebarOpen = false"></div>
            </Transition>

            <Transition enter-active-class="transition-transform duration-300 ease-out" enter-from-class="-translate-x-full" enter-to-class="translate-x-0" leave-active-class="transition-transform duration-200 ease-in" leave-from-class="translate-x-0" leave-to-class="-translate-x-full">
                <div v-if="mobileSidebarOpen" class="fixed inset-y-0 left-0 z-50 lg:hidden">
                    <AdminSidebar :collapsed="false" @toggle="mobileSidebarOpen = false" />
                </div>
            </Transition>
        </Teleport>

        <!-- ═══ Main ═══ -->
        <div class="admin-main-panel flex-1 flex flex-col min-w-0">
            <!-- Demo Notice -->
            <div v-if="$page.props.app?.demo && !demoDismissed" :class="demoBannerColor" class="px-4 py-2.5 text-center relative overflow-hidden">
                <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white via-transparent to-transparent"></div>
                <div class="relative z-10 flex items-center justify-center gap-4 flex-wrap">
                    <p class="text-sm font-semibold text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                        {{ t('Demo mode active — destructive actions are disabled') }}
                        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                    </p>
                    <a :href="envatoUrl" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-white/20 px-3 py-1 text-sm font-bold text-white hover:bg-white/30 transition-colors">
                        {{ t('Buy Now') }}
                        <i class="ti ti-external-link text-base"></i>
                    </a>
                    <button @click="dismissDemoBanner" class="ml-2 p-1 rounded text-white/70 hover:text-white hover:bg-white/10 transition-colors" :title="t('Dismiss')">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>
            </div>

            <div v-if="showCronBanner" :class="shellInsetClass" class="border-b border-amber-200 bg-amber-50 py-3 text-amber-900 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-100">
                <div class="flex flex-col gap-3 text-sm font-medium lg:flex-row lg:items-center lg:justify-between">
                    <span>{{ t('Cron job is not configured. Scheduled tasks, renewals, and automation may not run.') }}</span>
                    <Link :href="cronStatus?.setup_url || route('admin.system.index')" class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500">
                        {{ t('Set Up Cron Job') }}
                    </Link>
                </div>
            </div>

            <div v-if="showUpdateBanner" :class="shellInsetClass" class="border-b border-blue-200 bg-blue-50 py-3 text-blue-900 dark:border-blue-900/40 dark:bg-blue-900/20 dark:text-blue-100">
                <div class="flex flex-col gap-3 text-sm lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-2 font-medium">
                        <i class="ti ti-rocket text-base"></i>
                        <span>{{ t('A new version (v:version) is available.', { version: coreUpdate?.version ?? '' }) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="coreUpdate?.updates_url || route('admin.system.updates')" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500">
                            {{ t('View Update') }}
                        </Link>
                        <button type="button" :disabled="updateBannerBusy" @click="snoozeUpdate" class="inline-flex items-center justify-center rounded-lg border border-blue-300 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 disabled:opacity-60 dark:border-blue-700 dark:text-blue-200 dark:hover:bg-blue-900/40">
                            {{ t('Remind me later') }}
                        </button>
                        <button type="button" :disabled="updateBannerBusy" @click="dismissUpdate" class="inline-flex items-center justify-center rounded-lg px-2 py-1.5 text-xs font-medium text-blue-700/70 hover:text-blue-900 disabled:opacity-60 dark:text-blue-200/70 dark:hover:text-white" :title="t('Don\'t show again for this version')">
                            {{ t('Dismiss') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <header :class="shellInsetClass" class="admin-shell-header sticky top-0 z-30 flex h-[60px] items-center justify-between border-b backdrop-blur-sm shrink-0 transition-colors duration-300">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Mobile hamburger -->
                    <button type="button" :aria-label="t('Open navigation')" :class="['lg:hidden', headerIconButtonClass]" @click="mobileSidebarOpen = true">
                        <i class="ti ti-menu-2 text-xl leading-none"></i>
                    </button>

                    <template v-if="hasHeaderTitle">
                        <slot name="header">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><slot name="title" /></h2>
                        </slot>
                    </template>

                    <div class="hidden min-w-0 flex-1 lg:block lg:max-w-xl xl:max-w-2xl">
                        <LiveSearch context="admin" />
                    </div>
                </div>

                <!-- Profile -->
                <div class="flex items-center gap-1.5 lg:gap-3">
                    <!-- Teleport target for the AI Assistant header button (Widget Position =
                         "header-button"). Empty unless that addon is active and configured
                         for it; the addon renders into it rather than us importing it here.
                         The teleported button inherits this colour, so it matches the other
                         header icons. -->
                    <div id="ai-assistant-header-slot" class="flex items-center text-gray-600 dark:text-gray-300"></div>

                    <div class="relative" @click.stop>
                        <Tooltip :content="adminActionsLabel" placement="bottom">
                            <button
                                type="button"
                                :class="headerIconButtonClass"
                                :aria-label="adminActionsLabel"
                                :disabled="cacheClearing"
                                @click="actionsOpen = !actionsOpen"
                            >
                                <i v-if="cacheClearing" class="ti ti-loader-2 h-5 w-5 text-xl animate-spin leading-none"></i>
                                <i v-else class="ti ti-dots-vertical h-5 w-5 text-xl leading-none"></i>
                            </button>
                        </Tooltip>

                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="actionsOpen" class="admin-header-menu absolute right-0 z-50 mt-2 min-w-44 rounded-xl border py-1.5 shadow-xl rtl:left-0 rtl:right-auto">
                                <a :href="route('home')" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-2.5 text-sm text-green-600 transition-colors hover:bg-green-50 dark:hover:bg-green-900/20">
                                    <i class="ti ti-eye"></i>
                                    {{ t('Visit Site') }}
                                </a>
                                <hr class="my-1 h-px border-gray-100 dark:border-surface-700">
                                <button
                                    v-if="can('settings.manage')"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-60 rtl:text-right"
                                    :disabled="cacheClearing"
                                    @click="requestClearCache"
                                >
                                    <i class="ti ti-refresh"></i>
                                    {{ cacheClearing ? t('Clearing...') : t('Clear Cache') }}
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <Tooltip :content="themeToggleLabel" placement="bottom">
                        <button type="button" :aria-label="themeToggleLabel" :class="headerIconButtonClass" @click="toggleDark()">
                            <i v-if="isDark" class="ti ti-sun text-xl leading-none"></i>
                            <i v-else class="ti ti-moon text-xl leading-none"></i>
                        </button>
                    </Tooltip>

                    <Tooltip :content="notificationsLabel" placement="bottom">
                        <NotificationBell context="admin" :ui="notificationBellUi" />
                    </Tooltip>

                    <div class="relative" @click.stop>
                        <button @click="profileOpen = !profileOpen" class="admin-profile-trigger">
                            <div :class="['h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold', adminAvatarFallbackClass]">
                                <img v-if="adminAvatarUrl" :src="adminAvatarUrl" :alt="admin?.name ?? adminDisplayName" class="h-full w-full rounded-full object-cover" />
                                <span v-else>{{ admin?.name?.charAt(0) ?? 'A' }}</span>
                            </div>
                        </button>

                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="profileOpen" class="admin-profile-menu absolute right-0 rtl:right-auto rtl:left-0 mt-2 w-56 border rounded-xl shadow-xl py-1.5 z-50">
                                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 dark:border-surface-700">
                                    <div :class="['h-10 w-10 shrink-0 overflow-hidden rounded-full flex items-center justify-center', adminAvatarFallbackClass]">
                                        <img v-if="adminAvatarUrl" :src="adminAvatarUrl" :alt="admin?.name ?? adminDisplayName" class="h-full w-full object-cover" />
                                        <span v-else class="text-sm font-bold">{{ admin?.name?.charAt(0) ?? 'A' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ admin?.name }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ admin?.email }}</p>
                                    </div>
                                </div>
                                <Link :href="route('admin.account.settings')" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700">
                                    <i class="ti ti-user-cog"></i>
                                    {{ t('My Account') }}
                                </Link>
                                <Link :href="route('admin.security.2fa.show')" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700">
                                    <i class="ti ti-shield-lock"></i>
                                    {{ t('2FA Security') }}
                                </Link>
                                <a href="https://makeai-docs.ezydev.net" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700">
                                    <i class="ti ti-help-circle"></i>
                                    {{ t('Help Center') }}
                                </a>
                                <hr class="my-1 h-px border-gray-100 dark:border-surface-700">
                                <button @click="logout" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 hover:bg-red-50 dark:hover:bg-surface-700 transition-colors flex items-center gap-2">
                                    <i class="ti ti-logout"></i>
                                    {{ t('Sign Out') }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main :class="contentInsetClass" class="admin-shell-main flex-1">
                <slot />
            </main>

            <footer :class="shellInsetClass" class="border-t border-gray-200/80 py-3 text-xs text-gray-500 dark:border-surface-800 dark:text-gray-400">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="min-w-0">
                        {{ t('© :year :site_name', { year: currentYear, site_name: siteName }) }}
                        <span class="mx-1 text-gray-300 dark:text-gray-600">•</span>
                        {{ t('Developed by MakeAI') }}
                    </p>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 sm:justify-end">
                        <nav class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <a
                                v-for="item in adminFooterLinks"
                                :key="item.label"
                                :href="item.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 transition hover:text-primary-600 dark:hover:text-primary-300"
                            >
                                {{ item.label }}
                                <i v-if="item.icon" :class="item.icon" aria-hidden="true"></i>
                            </a>
                        </nav>

                        <LanguageSwitcher
                            v-if="hasMultipleLanguages"
                            display="icon_label"
                            placement="up"
                            :ui="footerLanguageSwitcherUi"
                        />
                    </div>
                </div>
            </footer>
        </div>

        <ActionConfirmModal
            :open="confirmClearCacheOpen"
            :title="t('Clear cache?')"
            :message="t('This will run Laravel optimize clear and may briefly refresh cached configuration, routes, views, and services.')"
            :confirm-label="t('Clear Cache')"
            :processing-label="t('Clearing...')"
            :processing="cacheClearing"
            variant="primary"
            @cancel="confirmClearCacheOpen = false"
            @confirm="clearCache"
        />
        <AiAssistantLoader />
    </div>
</template>

<style>
html.admin-layout-root {
    --admin-primary-color: var(--admin-primary, #9028f1);
    --admin-secondary-color: var(--admin-text-primary, #111827);

    /* Local overrides for Tailwind CSS v4 color variables inside Admin Layout (including teleported modals) */
    --color-primary: var(--admin-primary-color);
    --color-primary-50: color-mix(in srgb, var(--admin-primary-color) 8%, #ffffff);
    --color-primary-100: color-mix(in srgb, var(--admin-primary-color) 18%, #ffffff);
    --color-primary-200: color-mix(in srgb, var(--admin-primary-color) 32%, #ffffff);
    --color-primary-300: color-mix(in srgb, var(--admin-primary-color) 48%, #ffffff);
    --color-primary-400: color-mix(in srgb, var(--admin-primary-color) 72%, #ffffff);
    --color-primary-500: var(--admin-primary-color);
    --color-primary-600: color-mix(in srgb, var(--admin-primary-color) 86%, #000000);
    --color-primary-700: color-mix(in srgb, var(--admin-primary-color) 74%, #000000);
    --color-primary-800: color-mix(in srgb, var(--admin-primary-color) 62%, #000000);
    --color-primary-900: color-mix(in srgb, var(--admin-primary-color) 50%, #000000);
    --color-primary-950: color-mix(in srgb, var(--admin-primary-color) 36%, #000000);

    --color-secondary: var(--admin-secondary-color);
    --color-secondary-50: color-mix(in srgb, var(--admin-secondary-color) 8%, #ffffff);
    --color-secondary-100: color-mix(in srgb, var(--admin-secondary-color) 18%, #ffffff);
    --color-secondary-200: color-mix(in srgb, var(--admin-secondary-color) 32%, #ffffff);
    --color-secondary-300: color-mix(in srgb, var(--admin-secondary-color) 48%, #ffffff);
    --color-secondary-400: color-mix(in srgb, var(--admin-secondary-color) 72%, #ffffff);
    --color-secondary-500: var(--admin-secondary-color);
    --color-secondary-600: color-mix(in srgb, var(--admin-secondary-color) 86%, #000000);
    --color-secondary-700: color-mix(in srgb, var(--admin-secondary-color) 74%, #000000);
    --color-secondary-800: color-mix(in srgb, var(--admin-secondary-color) 62%, #000000);
    --color-secondary-900: color-mix(in srgb, var(--admin-secondary-color) 50%, #000000);
    --color-secondary-950: color-mix(in srgb, var(--admin-secondary-color) 36%, #000000);
}

.admin-layout {
    --admin-bg-color: var(--admin-bg, #ffffff);
    --admin-surface: color-mix(in srgb, var(--admin-navbar-bg, #ffffff) 92%, white);
    --admin-border: color-mix(in srgb, var(--admin-navbar-text, #111827) 14%, transparent);
    --admin-primary-color: var(--admin-primary, #9028f1);
    --admin-secondary-color: var(--admin-text-primary, #111827);

    /* Local overrides for Tailwind CSS v4 color variables inside Admin Layout */
    --color-primary: var(--admin-primary-color);
    --color-primary-50: color-mix(in srgb, var(--admin-primary-color) 8%, #ffffff);
    --color-primary-100: color-mix(in srgb, var(--admin-primary-color) 18%, #ffffff);
    --color-primary-200: color-mix(in srgb, var(--admin-primary-color) 32%, #ffffff);
    --color-primary-300: color-mix(in srgb, var(--admin-primary-color) 48%, #ffffff);
    --color-primary-400: color-mix(in srgb, var(--admin-primary-color) 72%, #ffffff);
    --color-primary-500: var(--admin-primary-color);
    --color-primary-600: color-mix(in srgb, var(--admin-primary-color) 86%, #000000);
    --color-primary-700: color-mix(in srgb, var(--admin-primary-color) 74%, #000000);
    --color-primary-800: color-mix(in srgb, var(--admin-primary-color) 62%, #000000);
    --color-primary-900: color-mix(in srgb, var(--admin-primary-color) 50%, #000000);
    --color-primary-950: color-mix(in srgb, var(--admin-primary-color) 36%, #000000);

    --color-secondary: var(--admin-secondary-color);
    --color-secondary-50: color-mix(in srgb, var(--admin-secondary-color) 8%, #ffffff);
    --color-secondary-100: color-mix(in srgb, var(--admin-secondary-color) 18%, #ffffff);
    --color-secondary-200: color-mix(in srgb, var(--admin-secondary-color) 32%, #ffffff);
    --color-secondary-300: color-mix(in srgb, var(--admin-secondary-color) 48%, #ffffff);
    --color-secondary-400: color-mix(in srgb, var(--admin-secondary-color) 72%, #ffffff);
    --color-secondary-500: var(--admin-secondary-color);
    --color-secondary-600: color-mix(in srgb, var(--admin-secondary-color) 86%, #000000);
    --color-secondary-700: color-mix(in srgb, var(--admin-secondary-color) 74%, #000000);
    --color-secondary-800: color-mix(in srgb, var(--admin-secondary-color) 62%, #000000);
    --color-secondary-900: color-mix(in srgb, var(--admin-secondary-color) 50%, #000000);
    --color-secondary-950: color-mix(in srgb, var(--admin-secondary-color) 36%, #000000);

    --admin-primary-soft: color-mix(in srgb, var(--admin-primary-color) 16%, transparent);
    --admin-primary-soft-strong: color-mix(in srgb, var(--admin-primary-color) 24%, transparent);
    --admin-primary-contrast: color-mix(in srgb, var(--admin-primary-color) 82%, black);
    --admin-text-main: var(--admin-text-primary, #111827);
    --admin-accent-soft: color-mix(in srgb, var(--admin-accent, #3b82f6) 12%, transparent);
    background: var(--admin-bg-color);
    color: var(--admin-text-main);
    font-family: var(--admin-font, Inter), sans-serif;
    font-size: var(--admin-font-size, 14px);
}

.dark .admin-layout {
    --admin-bg-color: #0b0f19;
    --admin-surface: #111827;
    --admin-border: #1f2937;
    /* Blue-gray secondary tones so nested surfaces, tables, hovers and inputs stay
       on the same hue as the cards/navbar/sidebar (not the theme's purple-black
       --color-surface-* palette). Ordered lightest page bg → border. */
    --admin-surface-hover: #151d2b;   /* subtle row / soft hover */
    --admin-surface-muted: #1a2333;   /* nested surfaces, table head, strong hover, input fill */
    --admin-input-border: #2b3648;    /* form control borders */
    --admin-secondary-color: #f3f4f6;
    --admin-text-main: #e5e7eb;
    --admin-navbar-bg: #111827;
    --admin-navbar-text: #f3f4f6;
    --admin-sidebar-bg: #111827;
    --admin-sidebar-text: #e5e7eb;
}

.admin-layout :is(button, input, select, textarea, a) {
    font-family: inherit;
}

.admin-layout *:focus-visible {
    outline: 2px solid var(--admin-primary-color);
    outline-offset: 2px;
}

.admin-layout :where(input:not([type="checkbox"]):not([type="radio"]):not([type="range"]), textarea:not(.chat-textarea), select):focus,
.admin-layout :where(input:not([type="checkbox"]):not([type="radio"]):not([type="range"]), textarea:not(.chat-textarea), select):focus-visible {
    border-color: var(--admin-primary-color) !important;
    box-shadow: 0 0 0 3px var(--admin-primary-soft) !important;
    outline: none !important;
}

.admin-layout input[type="checkbox"]:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--admin-primary-color) 45%, #d1d5db);
}

.admin-layout input[type="checkbox"]:checked {
    border-color: var(--admin-primary-color);
    background: var(--admin-primary-color);
}

.admin-layout input[type="checkbox"]:focus-visible {
    outline: none;
    box-shadow: 0 0 0 4px var(--admin-primary-soft-strong);
}

.admin-layout .app-switch:focus-visible {
    outline: none;
    box-shadow: 0 0 0 4px var(--admin-primary-soft-strong);
}

.admin-layout .app-switch[aria-checked="true"] {
    border-color: var(--admin-primary-color);
    background: var(--admin-primary-color);
}

.admin-layout .btn-primary-admin,
.admin-layout .btn-primary {
    background: linear-gradient(135deg, var(--admin-primary-color), var(--color-primary-600));
    border-color: var(--admin-primary-color);
    color: #ffffff;
}

.admin-layout .btn-primary-admin:hover,
.admin-layout .btn-primary:hover {
    background: linear-gradient(135deg, var(--color-primary-600), var(--admin-primary-color));
    border-color: var(--admin-primary-contrast);
}

.admin-layout .btn-primary-admin:focus-visible,
.admin-layout .btn-primary:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px var(--admin-primary-soft-strong), var(--shadow-sm);
}

.admin-main-panel,
.admin-shell-main {
    color: var(--admin-text-main);
}

.admin-shell-header {
    --header-soft-icon-bg: transparent;
    --header-soft-icon-border: transparent;
    --header-soft-icon-color: color-mix(in srgb, var(--admin-navbar-text, #111827) 58%, transparent);
    --header-soft-icon-hover-bg: var(--admin-accent-soft);
    --header-soft-icon-hover-bg-dark: var(--admin-accent-soft);
    --header-soft-icon-hover-color: var(--admin-accent, #3b82f6);
    background: color-mix(in srgb, var(--admin-navbar-bg, #ffffff) 94%, transparent);
    border-bottom-color: var(--admin-border);
    color: var(--admin-navbar-text, #111827);
}

.admin-header-icon-button {
    color: color-mix(in srgb, var(--admin-navbar-text, #111827) 58%, transparent);
}

.admin-header-icon-button:hover {
    background: var(--admin-accent-soft);
    color: var(--admin-accent, #3b82f6);
}

.admin-header-menu,
.admin-profile-menu {
    background: var(--admin-surface);
    border-color: var(--admin-border);
}

/* Footer language switcher: strip the shared button's padding + hover surface, shrink the chevron. */
.admin-footer-lang.language-switcher-button,
.admin-footer-lang.language-switcher-button:hover {
    padding: 0 !important;
    background: transparent !important;
    border-color: transparent !important;
}

.admin-footer-lang svg {
    height: 0.75rem;
    width: 0.75rem;
}

.admin-footer-lang .ti-language {
    display: none;
}

</style>
