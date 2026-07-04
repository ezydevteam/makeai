<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import CreditAlertBanner from '@/Components/CreditAlertBanner.vue'
import OnboardingModal from '@/Components/OnboardingModal.vue'

const page = usePage()
const { t } = useTranslate()
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))
const ticketsEnabled = computed(() => Boolean(page.props.ticketsEnabled))
const referralUser = computed(() => page.props.auth?.user as any)
const userAvatarUrl = computed(() => {
    const avatar = referralUser.value?.avatar
    if (!avatar) return null
    if (avatar.startsWith('http://') || avatar.startsWith('https://')) {
        return avatar
    }
    return `/storage/${avatar}`
})

const sidebarOpen = ref(false)
const onboardingOpen = ref(false)
const isDesktopViewport = ref(false)

function syncViewportState() {
    isDesktopViewport.value = window.innerWidth >= 1024
}

function closeSidebar() {
    sidebarOpen.value = false
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && sidebarOpen.value) {
        closeSidebar()
    }
}

watch(sidebarOpen, (open) => {
    document.body.classList.toggle('overflow-hidden', open)
})

function onOnboardingClosed() {
    onboardingOpen.value = false
}

onMounted(() => {
    const props = page.props as any

    syncViewportState()

    if (props.showOnboarding) {
        onboardingOpen.value = true
    }
    window.addEventListener('onboarding:closed', onOnboardingClosed)
    window.addEventListener('keydown', onKeydown)
    window.addEventListener('resize', syncViewportState)
})

onUnmounted(() => {
    window.removeEventListener('onboarding:closed', onOnboardingClosed)
    window.removeEventListener('keydown', onKeydown)
    window.removeEventListener('resize', syncViewportState)
    document.body.classList.remove('overflow-hidden')
})

const workspaceOpen = ref(route().current('user.dashboard.playground.*') || route().current('user.dashboard.chains.*') || route().current('user.dashboard.embeds.*'))
const libraryOpen = ref(route().current('user.dashboard.collections.*') || route().current('user.dashboard.favorites.*') || route().current('user.dashboard.history.*') || Boolean(route().current('user.dashboard.documents.*') || route().current('documents.*')))
const accountOpen = ref(route().current('user.dashboard.profile*') || route().current('user.dashboard.security*') || route().current('user.dashboard.api-keys*') || route().current('user.dashboard.billing*') || route().current('user.dashboard.privacy*') || route().current('user.dashboard.credit-topup*'))
const socialOpen = ref(route().current('addon.social.user.*'))
const videoOpen = ref(route().current('addon.video.user.*'))
const voiceoverOpen = ref(route().current('addon.vo.user.*'))

interface NavItem {
    label: string
    routeName?: string
    active: boolean
    icon: string
    children?: { label: string; routeName: string; active: boolean }[]
    sectionKey?: string
}

const socialScheduler = computed(() => (page.props.socialScheduler as any) ?? { enabled: false })
const videoCreator = computed(() => (page.props.videoCreator as any) ?? { enabled: false })
const voiceover = computed(() => (page.props.voiceover as any) ?? { enabled: false })
const imageEditor = computed(() => (page.props.imageEditor as any) ?? { enabled: false })
const frontendHeaderSettings = computed(() => (page.props as any).frontendHeaderSettings ?? {})
const mobileBottomHeaderHeight = computed(() => {
    const mobileBottom = frontendHeaderSettings.value?.mobile_bottom ?? {}

    if (mobileBottom.enabled !== true) {
        return 0
    }

    return mobileBottom.hide_menu_labels === true ? 48 : 60
})
const dashboardShellStyle = computed(() => ({
    paddingTop: '32px',
    paddingBottom: '24px',
}))
const mobileSidebarInsetStyle = computed(() => ({
    top: 'var(--header-height, 64px)',
    bottom: `${mobileBottomHeaderHeight.value}px`,
    height: `calc(100dvh - var(--header-height, 64px) - ${mobileBottomHeaderHeight.value}px)`,
}))
const mobileSidebarOverlayStyle = computed(() => ({
    top: 'var(--header-height, 64px)',
    bottom: `${mobileBottomHeaderHeight.value}px`,
}))
const appliedMobileSidebarInsetStyle = computed(() => (isDesktopViewport.value ? undefined : mobileSidebarInsetStyle.value))
const appliedMobileSidebarOverlayStyle = computed(() => (isDesktopViewport.value ? undefined : mobileSidebarOverlayStyle.value))

const navItems = computed<NavItem[]>(() => {
    const is = (name: string) => route().current(name)
    const items: NavItem[] = [
        { label: t('Dashboard'), routeName: 'user.dashboard', active: is('user.dashboard'), icon: 'ti ti-dashboard' },
        {
            label: t('Workspace'),
            icon: 'ti ti-building-factory',
            active: is('user.dashboard.playground.*') || is('user.dashboard.chains.*') || is('user.dashboard.embeds.*'),
            sectionKey: 'workspace',
            children: [
                { label: t('Playground'), routeName: 'user.dashboard.playground.index', active: is('user.dashboard.playground.*') },
                { label: t('Chains'), routeName: 'user.dashboard.chains.index', active: is('user.dashboard.chains.*') },
                { label: t('Tool Embeds'), routeName: 'user.dashboard.embeds.index', active: is('user.dashboard.embeds.*') },
            ],
        },
        {
            label: t('Library'),
            icon: 'ti ti-books',
            active: is('user.dashboard.collections.*') || is('user.dashboard.favorites.*') || is('user.dashboard.history.*') || Boolean(is('user.dashboard.documents.*') || is('documents.*')),
            sectionKey: 'library',
            children: [
                { label: t('Documents'), routeName: 'user.dashboard.documents.index', active: Boolean(is('user.dashboard.documents.*') || is('documents.*')) },
                { label: t('Collections'), routeName: 'user.dashboard.collections.index', active: is('user.dashboard.collections.*') },
                { label: t('Favorites'), routeName: 'user.dashboard.favorites.index', active: is('user.dashboard.favorites.*') },
                { label: t('History'), routeName: 'user.dashboard.history.index', active: is('user.dashboard.history.*') },
            ],
        },
        { label: t('My Usage'), routeName: 'user.dashboard.usage.index', active: is('user.dashboard.usage.*'), icon: 'ti ti-chart-bar' },
        ...(videoCreator.value.enabled ? [{
            label: t('Video Creator'),
            icon: 'ti ti-video',
            active: is('addon.video.user.*'),
            sectionKey: 'video',
            children: [
                { label: t('Library'), routeName: 'addon.video.user.library', active: is('addon.video.user.library') },
                { label: t('Create'), routeName: 'addon.video.user.create', active: is('addon.video.user.create') },
            ],
        }] : []),
        ...(voiceover.value.enabled ? [{
            label: t('Voiceover Studio'),
            icon: 'ti ti-microphone',
            active: is('addon.vo.user.*'),
            sectionKey: 'voiceover',
            children: [
                { label: t('My Projects'), routeName: 'addon.vo.user.studio', active: is('addon.vo.user.studio') },
            ],
        }] : []),
        ...(socialScheduler.value.enabled ? [{
            label: t('Social Scheduler'),
            icon: 'ti ti-calendar-share',
            active: is('addon.social.user.*'),
            sectionKey: 'social',
            children: [
                { label: t('Calendar'), routeName: 'addon.social.user.calendar', active: is('addon.social.user.calendar') },
                { label: t('Posts'), routeName: 'addon.social.user.posts.index', active: is('addon.social.user.posts.index') || is('addon.social.user.posts.edit') },
                { label: t('Accounts'), routeName: 'addon.social.user.accounts', active: is('addon.social.user.accounts') },
                { label: t('Analytics'), routeName: 'addon.social.user.analytics', active: is('addon.social.user.analytics') },
            ],
        }] : []),
        ...(imageEditor.value.enabled ? [{
            label: t('Image Editor'),
            icon: 'ti ti-photo-edit',
            active: is('addon.ie.user.*'),
            routeName: 'addon.ie.user.editor',
        }] : []),
        ...(affiliateEnabled.value ? [{ label: t('Affiliate'), routeName: 'user.dashboard.affiliate', active: is('user.dashboard.affiliate*'), icon: 'ti ti-affiliate' }] : []),
        {
            label: t('Account'),
            icon: 'ti ti-user-circle',
            active: is('user.dashboard.profile*') || is('user.dashboard.security*') || is('user.dashboard.api-keys*') || is('user.dashboard.privacy*'),
            sectionKey: 'account',
            children: [
                { label: t('Profile'), routeName: 'user.dashboard.profile', active: is('user.dashboard.profile*') },
                { label: t('Security'), routeName: 'user.dashboard.security', active: is('user.dashboard.security*') },
                { label: t('API Keys'), routeName: 'user.dashboard.api-keys', active: is('user.dashboard.api-keys') || is('user.dashboard.api-keys.*') },
                { label: t('Billing'), routeName: 'user.dashboard.billing', active: is('user.dashboard.billing*') },
                { label: t('Buy Credits'), routeName: 'user.dashboard.credit-topup', active: is('user.dashboard.credit-topup*') },
                { label: t('Privacy'), routeName: 'user.dashboard.privacy', active: is('user.dashboard.privacy*') },
            ],
        },
        { label: t('Notifications'), routeName: 'user.dashboard.notifications.index', active: is('user.dashboard.notifications.*'), icon: 'ti ti-bell' },
        ...(ticketsEnabled.value ? [{ label: t('Support'), routeName: 'user.dashboard.support.index', active: is('user.dashboard.support.*'), icon: 'ti ti-message-circle' }] : []),
    ]
    return items
})

function isSectionOpen(key: string): boolean {
    if (key === 'workspace') return workspaceOpen.value
    if (key === 'library') return libraryOpen.value
    if (key === 'account') return accountOpen.value
    if (key === 'social') return socialOpen.value
    if (key === 'video') return videoOpen.value
    if (key === 'voiceover') return voiceoverOpen.value
    return false
}

function toggleSection(key: string) {
    const sections: Record<string, typeof workspaceOpen> = {
        workspace: workspaceOpen,
        library: libraryOpen,
        account: accountOpen,
        social: socialOpen,
        video: videoOpen,
        voiceover: voiceoverOpen,
    }

    const currentVal = sections[key].value

    // Close all first
    Object.keys(sections).forEach(k => {
        sections[k].value = false
    })

    // Toggle current
    sections[key].value = !currentVal
}
</script>

<template>
    <UserLayout>
        <div class="min-h-screen" :style="dashboardShellStyle">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Mobile hamburger -->
                <button class="mb-4 flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-800 lg:hidden" @click="sidebarOpen = !sidebarOpen">
                    <i class="ti ti-menu-2 text-lg"></i>
                    {{ t('Menu') }}
                </button>

                <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <!-- Mobile overlay -->
                    <div
                        v-if="sidebarOpen"
                        class="fixed inset-x-0 z-30 bg-slate-950/50 backdrop-blur-sm lg:hidden dark:bg-black/60"
                        :style="appliedMobileSidebarOverlayStyle"
                        @click="closeSidebar"
                    />

                    <!-- Sidebar -->
                    <aside
                        :class="[
                            sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                            'fixed left-0 z-40 flex w-[min(18rem,calc(100vw-1.5rem))] max-w-full flex-col border-r border-gray-100 bg-white shadow-xl transition-transform duration-200 dark:border-surface-800 dark:bg-surface-900 dark:shadow-black/30 lg:static lg:h-auto lg:w-[260px] lg:translate-x-0 lg:self-start lg:rounded-xl lg:border lg:shadow-[0_18px_40px_rgba(15,23,42,0.06)]'
                        ]"
                        :style="appliedMobileSidebarInsetStyle"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-surface-800 lg:hidden">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Dashboard Menu') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Navigate your workspace') }}</p>
                            </div>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:border-surface-600 dark:hover:bg-surface-750 dark:hover:text-white" :aria-label="t('Close menu')" @click="closeSidebar">
                                <i class="ti ti-x text-lg" aria-hidden="true"></i>
                            </button>
                        </div>

                        <!-- User Profile Info -->
                        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-4 dark:border-surface-800">
                            <!-- Avatar -->
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-500/10">
                                <img
                                    v-if="userAvatarUrl"
                                    :src="userAvatarUrl"
                                    :alt="referralUser?.name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center font-semibold text-primary-700 dark:text-primary-300 uppercase">
                                    {{ referralUser?.name ? referralUser.name.charAt(0) : 'U' }}
                                </div>
                            </div>
                            <!-- Name & Email -->
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" :title="referralUser?.name">{{ referralUser?.name }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400" :title="referralUser?.email">{{ referralUser?.email }}</p>
                            </div>
                        </div>

                        <nav class="flex-1 space-y-1 overflow-y-auto p-3 dark:bg-surface-900/60">
                            <template v-for="item in navItems" :key="item.label">
                                <!-- Section header (collapsible) -->
                                <button
                                    v-if="item.children && item.children.length"
                                    @click="toggleSection(item.sectionKey!)"
                                    :class="item.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800/40 dark:!bg-primary-500/15 dark:!text-primary-300' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white'"
                                    class="flex w-full items-center gap-3 rounded-full border px-3 py-2.5 text-sm font-semibold transition"
                                >
                                    <i :class="item.icon" class="text-lg shrink-0"></i>
                                    <span class="flex-1 text-left">{{ item.label }}</span>
                                    <i class="ti ti-chevron-down shrink-0 text-sm transition-transform" :class="{ 'rotate-180': isSectionOpen(item.sectionKey!) }"></i>
                                </button>

                                <!-- Collapsible children -->
                                <div v-if="item.children && isSectionOpen(item.sectionKey!)" class="ml-4 space-y-1 border-l border-gray-200 pl-3 dark:border-surface-800/80">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.routeName"
                                        :href="route(child.routeName)"
                                        :class="child.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800/40 dark:!bg-primary-500/15 dark:!text-primary-300' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-white'"
                                        class="flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition"
                                        @click="closeSidebar"
                                    >
                                        <span>{{ child.label }}</span>
                                    </Link>
                                </div>

                                <!-- Standalone link -->
                                <Link
                                    v-else-if="item.routeName"
                                    :href="route(item.routeName)"
                                    :class="item.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800/40 dark:!bg-primary-500/15 dark:!text-primary-300' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white'"
                                    class="flex items-center gap-3 rounded-full border px-3 py-2.5 text-sm font-semibold transition"
                                    @click="closeSidebar"
                                >
                                    <i :class="item.icon" class="text-lg shrink-0"></i>
                                    <span>{{ item.label }}</span>
                                </Link>
                            </template>
                        </nav>

                        <div class="shrink-0 rounded-b-xl border-t border-gray-100 bg-white/90 p-3 dark:border-surface-800 dark:bg-surface-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ t('Credit Balance') }}</p>
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ referralUser?.credits ?? 0 }}</div>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ t('credits available') }}</div>
                                </div>
                                <Link :href="route('user.dashboard.credit-topup')" class="inline-flex items-center gap-1 rounded-full bg-primary-500 px-3 py-1.5 text-xs font-semibold !text-white hover:bg-primary-700 transition-colors">
                                    <i class="ti ti-plus text-sm"></i>
                                    {{ t('Top Up') }}
                                </Link>
                            </div>
                        </div>
                    </aside>

                    <main class="min-w-0">
                        <CreditAlertBanner />
                        <slot />
                    </main>
                </div>
            </div>
        </div>

        <OnboardingModal :open="onboardingOpen" @close="onboardingOpen = false" />
    </UserLayout>
</template>
