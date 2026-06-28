<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, useSlots } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import LiveSearch from '@/Components/LiveSearch.vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import AdminSidebar from '@/Components/AdminSidebar.vue'
import AiAssistantLoader from '@/Components/Addons/AiAssistantLoader.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const { isDark, toggleDark } = useTheme()
const { t } = useTranslate()
const page = usePage()
const slots = useSlots()
useFlashToasts()

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
    const hash = name.split('').reduce((acc: number, char: string) => acc + char.charCodeAt(0), 0)

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
const shellInsetClass = 'px-8 sm:px-11 lg:px-11 xl:px-14 2xl:px-16'
const contentInsetClass = 'p-4 sm:p-5 lg:p-5 xl:p-6 2xl:p-6'
const hasHeaderTitle = computed(() => Boolean(slots.header || slots.title))
const headerIconButtonClass = 'inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100/90 text-gray-500 shadow-sm shadow-gray-200/40 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-200/85 hover:text-gray-900 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-200/70 dark:bg-surface-800 dark:text-gray-400 dark:shadow-black/20 dark:hover:bg-surface-700 dark:hover:text-white'
const circularHeaderControlClass = '!h-10 !w-10 !min-w-10 !rounded-full !border-0 !bg-gray-100/90 dark:!bg-surface-800'
const circularHeaderIconClass = '!text-[18px] !leading-none'
const languageSwitcherButtonClass = `${headerIconButtonClass} ${circularHeaderControlClass} header-soft-icon-button--icon-only !text-gray-500 hover:!text-gray-900 dark:!text-gray-400 dark:hover:!text-white`
const notificationBellUi = {
    triggerClass: `${headerIconButtonClass} ${circularHeaderControlClass} header-soft-icon-button header-soft-icon-button--icon-only`,
    iconClass: `ti ti-bell ${circularHeaderIconClass}`,
}
const languageSwitcherUi = {
    buttonClass: `${languageSwitcherButtonClass} language-switcher-button header-soft-icon-button header-soft-icon-button--icon-only`,
    iconStyle: {
        fontSize: '18px',
        lineHeight: '1',
    },
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

            <!-- Header -->
            <header :class="shellInsetClass" class="sticky top-0 z-30 flex h-[60px] items-center justify-between border-b border-gray-200 bg-white/95 backdrop-blur-sm shrink-0 transition-colors duration-300 dark:border-gray-700 dark:bg-[#161b22]/95">
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
                <div class="flex items-center gap-2 lg:gap-4">
                    <div class="relative" @click.stop>
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

                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="actionsOpen" class="absolute right-0 z-50 mt-2 min-w-44 rounded-xl border border-gray-200 bg-white py-1.5 shadow-xl dark:border-surface-700 dark:bg-surface-800 rtl:left-0 rtl:right-auto">
                                <a :href="route('home')" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-2.5 text-sm text-green-600 transition-colors hover:bg-green-50">
                                    <i class="ti ti-eye"></i>
                                    {{ t('Visit Site') }}
                                </a>
                                <hr class="my-1 h-px border-gray-100 dark:border-surface-700">
                                <button
                                    v-if="can('settings.manage')"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 transition-colors hover:bg-red-50 disabled:opacity-60 rtl:text-right"
                                    :disabled="cacheClearing"
                                    @click="requestClearCache"
                                >
                                    <i class="ti ti-refresh"></i>
                                    {{ cacheClearing ? t('Clearing...') : t('Clear Cache') }}
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <LanguageSwitcher display="icon" :ui="languageSwitcherUi" />

                    <button type="button" :aria-label="isDark ? t('Switch to light mode') : t('Switch to dark mode')" :class="headerIconButtonClass" @click="toggleDark()">
                        <i v-if="isDark" class="ti ti-sun text-xl leading-none"></i>
                        <i v-else class="ti ti-moon text-xl leading-none"></i>
                    </button>

                    <NotificationBell context="admin" :ui="notificationBellUi" />

                    <div class="relative" @click.stop>
                        <button @click="profileOpen = !profileOpen" class="flex items-center rounded-full text-gray-700 transition-all duration-200 lg:gap-3 lg:bg-gray-100/90 lg:py-1.5 lg:pl-1.5 lg:pr-2.5 lg:shadow-sm lg:shadow-gray-200/30 lg:hover:bg-gray-200/85 dark:text-gray-200 dark:lg:bg-surface-800 dark:lg:shadow-black/20 dark:lg:hover:bg-surface-700">
                            <div :class="['h-9 w-9 rounded-full flex items-center justify-center text-sm font-bold', adminAvatarFallbackClass]">
                                <img v-if="adminAvatarUrl" :src="adminAvatarUrl" :alt="admin?.name ?? adminDisplayName" class="h-full w-full rounded-full object-cover" />
                                <span v-else>{{ admin?.name?.charAt(0) ?? 'A' }}</span>
                            </div>
                            <span class="hidden text-sm font-medium lg:block">{{ adminDisplayName }}</span>
                            <i class="ti ti-chevron-down hidden text-base text-gray-400 lg:block"></i>
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
                                <Link :href="route('admin.account.settings')" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700">
                                    <i class="ti ti-user-cog"></i>
                                    {{ t('My Account') }}
                                </Link>
                                <Link :href="route('admin.security.2fa.show')" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-700">
                                    <i class="ti ti-shield-lock"></i>
                                    {{ t('2FA Security') }}
                                </Link>
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
