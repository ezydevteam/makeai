<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import LiveSearch from '@/Components/LiveSearch.vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import AdminSidebar from '@/Components/AdminSidebar.vue'
import AiAssistantLoader from '@/Components/Addons/AiAssistantLoader.vue'

const { isDark, toggleDark } = useTheme()
const { t } = useTranslate()
const page = usePage()
useFlashToasts()

const appearanceThemeSettings = computed(() => (page.props.appearanceThemeSettings as Record<string, string>) || {})
const themeAllowToggle = computed(() => {
    const val = appearanceThemeSettings.value.theme_allow_user_toggle
    return val === undefined || val === '' || val === 'true' || val === '1'
})

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
    const hash = Array.from(name).reduce((acc, char) => acc + char.charCodeAt(0), 0)

    return avatarPalette[hash % avatarPalette.length]
})
const adminAvatarUrl = computed(() => {
    const avatar = admin.value?.avatar

    if (!avatar) return ''
    if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')) return avatar

    return `/storage/${avatar}`
})
const adminActionsLabel = computed(() => t('Admin actions'))
const isSuperAdmin = computed(() => (page.props.admin as any)?.isSuperAdmin ?? false)
const permissions = computed(() => (page.props.admin as any)?.permissions ?? [])
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const cronStatus = computed(() => page.props.cronStatus as { is_configured?: boolean; setup_url?: string; last_run_at?: string | null } | undefined)
const showCronBanner = computed(() => Boolean(admin.value && cronStatus.value && cronStatus.value.is_configured === false))
const shellInsetClass = 'px-4 sm:px-5 lg:px-5 xl:px-6 2xl:px-6'
const contentInsetClass = 'p-4 sm:p-5 lg:p-5 xl:p-6 2xl:p-6'

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
watch(adminSettings, (settings) => {
    const root = document.documentElement
    if (settings.primary_color) root.style.setProperty('--admin-primary', settings.primary_color)
    if (settings.sidebar_bg) root.style.setProperty('--admin-sidebar-bg', settings.sidebar_bg)
    if (settings.sidebar_text_color) root.style.setProperty('--admin-sidebar-text', settings.sidebar_text_color)
    if (settings.navbar_bg) root.style.setProperty('--admin-navbar-bg', settings.navbar_bg)
    if (settings.navbar_text_color) root.style.setProperty('--admin-navbar-text', settings.navbar_text_color)
    if (settings.accent_color) root.style.setProperty('--admin-accent', settings.accent_color)
    if (settings.font_family) root.style.setProperty('--admin-font', settings.font_family)
    if (settings.base_font_size) root.style.setProperty('--admin-font-size', settings.base_font_size)
}, { immediate: true })

onMounted(() => {
    document.addEventListener('click', closeHeaderMenus)
    document.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
    document.removeEventListener('click', closeHeaderMenus)
    document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <div class="admin-layout min-h-screen bg-[#f5f6fa] dark:bg-[#0d1117] text-gray-900 dark:text-gray-100 transition-colors duration-300 flex">
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
        <div class="flex-1 flex flex-col min-w-0">
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
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                    <button @click="dismissDemoBanner" class="ml-2 p-1 rounded text-white/70 hover:text-white hover:bg-white/10 transition-colors" :title="t('Dismiss')">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
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

            <!-- Header -->
            <header :class="shellInsetClass" class="sticky top-0 z-30 flex h-[60px] items-center justify-between border-b border-gray-200 bg-white/95 backdrop-blur-sm shrink-0 transition-colors duration-300 dark:border-gray-700 dark:bg-[#161b22]/95">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Mobile hamburger -->
                    <button class="lg:hidden w-9 h-9 flex items-center justify-center rounded-lg text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-surface-800 transition-colors" @click="mobileSidebarOpen = true">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    </button>

                    <slot name="header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><slot name="title" /></h2>
                    </slot>

                    <div class="hidden min-w-0 flex-1 lg:block lg:max-w-xl xl:max-w-2xl">
                        <LiveSearch context="admin" />
                    </div>
                </div>

                <!-- Profile -->
                <div class="flex items-center gap-2 lg:gap-4">
                    <NotificationBell context="admin" />

                    <div class="relative" @click.stop>
                        <button
                            type="button"
                            class="flex h-9 w-9 lg:h-10 lg:w-10 items-center justify-center rounded-xl bg-gray-50 text-gray-500 transition-all hover:bg-gray-100 hover:text-gray-900 disabled:opacity-60 dark:bg-surface-800 dark:text-gray-400 dark:hover:bg-surface-700 dark:hover:text-white"
                            :aria-label="adminActionsLabel"
                            :disabled="cacheClearing"
                            @click="actionsOpen = !actionsOpen"
                        >
                            <svg v-if="cacheClearing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75h.008v.008H12V6.75zm0 5.25h.008v.008H12V12zm0 5.25h.008v.008H12v-.008z" />
                            </svg>
                        </button>

                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="actionsOpen" class="absolute right-0 z-50 mt-2 w-52 rounded-xl border border-gray-200 bg-white py-1.5 shadow-xl dark:border-surface-700 dark:bg-surface-800 rtl:left-0 rtl:right-auto">
                                <a :href="route('home')" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                    {{ t('Visit Site') }}
                                </a>
                                <button
                                    v-if="can('settings.manage')"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-60 dark:text-gray-200 dark:hover:bg-surface-700 rtl:text-right"
                                    :disabled="cacheClearing"
                                    @click="requestClearCache"
                                >
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M7.977 14.652H2.985m18.03-9.295v4.992m0 0h-4.992m4.992 0-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7" />
                                    </svg>
                                    {{ cacheClearing ? t('Clearing...') : t('Clear Cache') }}
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <button v-if="themeAllowToggle" @click="toggleDark()" class="w-9 h-9 lg:w-10 lg:h-10 flex items-center justify-center rounded-xl bg-gray-50 dark:bg-surface-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 transition-all">
                        <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>

                    <div class="relative" @click.stop>
                        <button @click="profileOpen = !profileOpen" class="flex items-center gap-2 lg:gap-3 px-2 lg:px-3 py-1.5 rounded-xl hover:bg-gray-50 dark:hover:bg-surface-800 transition-colors text-gray-700 dark:text-gray-200">
                            <div :class="['w-7 h-7 lg:w-8 lg:h-8 rounded-lg flex items-center justify-center text-sm font-bold', adminAvatarFallbackClass]">
                                {{ admin?.name?.charAt(0) ?? 'A' }}
                            </div>
                            <span class="text-sm font-medium hidden sm:block">{{ adminDisplayName }}</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>

                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="profileOpen" class="absolute right-0 rtl:right-auto rtl:left-0 mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl shadow-xl py-1.5 z-50">
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
                                <Link :href="route('admin.security.2fa.show')" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                    {{ t('Two-Factor Security') }}
                                </Link>
                                <button @click="logout" class="w-full text-left rtl:text-right px-4 py-2.5 text-sm text-danger-500 hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                    {{ t('Sign Out') }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main :class="contentInsetClass" class="flex-1">
                <slot />
            </main>
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
