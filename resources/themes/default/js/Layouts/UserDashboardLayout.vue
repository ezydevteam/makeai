<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import CreditAlertBanner from '@themes/default/js/Components/CreditAlertBanner.vue'
import OnboardingModal from '@themes/default/js/Components/OnboardingModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { mediaUrl } from '@/lib/media'

const page = usePage()
const { t } = useTranslate()
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))
const ticketsEnabled = computed(() => Boolean(page.props.ticketsEnabled))
const byokEnabled = computed(() => page.props.byokEnabled !== false)
const playgroundEnabled = computed(() => page.props.playgroundEnabled !== false)
const chainsEnabled = computed(() => page.props.chainsEnabled !== false)
const toolEmbedsEnabled = computed(() => page.props.toolEmbedsEnabled !== false)
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
// Broader than isProAvailable, which also requires the subscriptions toggle to be
// on. Billing follows the licence alone so an existing subscriber can still reach
// the page to view or cancel after an admin switches subscriptions off.
const isExtendedLicense = computed(() => Boolean(page.props.isExtendedLicense))
const referralUser = computed(() => page.props.auth?.user as any)
const dailyLimit = computed(() => Number(page.props.userDailyCreditLimit ?? 0))
const monthlyLimit = computed(() => Number(page.props.userMonthlyCreditLimit ?? 0))
const creditsUsedToday = computed(() => Number(page.props.creditsUsedToday ?? 0))
const creditsUsedMonth = computed(() => Number(page.props.creditsUsedMonth ?? 0))
const dailyRemaining = computed(() => Math.max(0, dailyLimit.value - creditsUsedToday.value))
const monthlyRemaining = computed(() => Math.max(0, monthlyLimit.value - creditsUsedMonth.value))

// In quota mode (Regular license, or Extended with subscriptions off) the wallet
// is never drained — usage meters against a resetting daily/monthly allowance.
// Show that remaining allowance (daily first, then monthly); only when neither
// cap is set does the wallet balance apply. Extended/billing always shows the wallet.
const roundCredits = (value: number): number => Math.round(value * 100) / 100
const creditBalance = computed(() => {
    if (!isProAvailable.value && dailyLimit.value > 0) {
        return { value: roundCredits(dailyRemaining.value), label: t('credits left today') }
    }
    if (!isProAvailable.value && monthlyLimit.value > 0) {
        return { value: roundCredits(monthlyRemaining.value), label: t('credits left this month') }
    }
    return { value: roundCredits(referralUser.value?.credits ?? 0), label: t('credits available') }
})
const creditBalanceValue = computed(() => creditBalance.value.value)
const creditBalanceLabel = computed(() => creditBalance.value.label)
const userAvatarUrl = computed(() => {
    const avatar = referralUser.value?.avatar
    if (!avatar) return null

    return mediaUrl(avatar)
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
const accountOpen = ref(route().current('user.dashboard.profile*') || route().current('user.dashboard.security*') || route().current('user.dashboard.byok*') || route().current('user.dashboard.billing*') || route().current('user.dashboard.privacy*') || route().current('user.dashboard.credit-topup*'))
const socialOpen = ref(route().current('addon.social.user.*'))
const videoOpen = ref(route().current('addon.video.user.*'))
const voiceoverOpen = ref(route().current('addon.vo.user.*'))
const imageProOpen = ref(route().current('addon.aip.user.*'))

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
const imagePro = computed(() => (page.props.imagePro as any) ?? { enabled: false, canUseStudio: false, canUseLibrary: false })
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
    top: 'calc(var(--top-banners-height, 0px) + var(--header-height, 64px))',
    bottom: `${mobileBottomHeaderHeight.value}px`,
    height: `calc(100dvh - var(--top-banners-height, 0px) - var(--header-height, 64px) - ${mobileBottomHeaderHeight.value}px)`,
}))
const mobileSidebarOverlayStyle = computed(() => ({
    top: 'calc(var(--top-banners-height, 0px) + var(--header-height, 64px))',
    bottom: `${mobileBottomHeaderHeight.value}px`,
}))
const appliedMobileSidebarInsetStyle = computed(() => (isDesktopViewport.value ? undefined : mobileSidebarInsetStyle.value))
const appliedMobileSidebarOverlayStyle = computed(() => (isDesktopViewport.value ? undefined : mobileSidebarOverlayStyle.value))

const navItems = computed<NavItem[]>(() => {
    const is = (name: string) => route().current(name)
    const items: NavItem[] = [
        { label: t('Dashboard'), routeName: 'user.dashboard', active: is('user.dashboard'), icon: 'ti ti-dashboard' },
        ...((playgroundEnabled.value || chainsEnabled.value || toolEmbedsEnabled.value) ? [{
            label: t('Workspace'),
            icon: 'ti ti-building-factory',
            active: is('user.dashboard.playground.*') || is('user.dashboard.chains.*') || is('user.dashboard.embeds.*'),
            sectionKey: 'workspace',
            children: [
                ...(playgroundEnabled.value ? [{ label: t('Playground'), routeName: 'user.dashboard.playground.index', active: is('user.dashboard.playground.*') }] : []),
                ...(chainsEnabled.value ? [{ label: t('Chains'), routeName: 'user.dashboard.chains.index', active: is('user.dashboard.chains.*') }] : []),
                ...(toolEmbedsEnabled.value ? [{ label: t('Tool Embeds'), routeName: 'user.dashboard.embeds.index', active: is('user.dashboard.embeds.*') }] : []),
            ],
        }] : []),
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
        ...(imagePro.value.enabled && (imagePro.value.canUseStudio || imagePro.value.canUseLibrary) ? [{
            label: t('AI Image Pro'),
            icon: 'ti ti-sparkles',
            active: is('addon.aip.user.*'),
            sectionKey: 'imagePro',
            children: [
                ...(imagePro.value.canUseStudio ? [{ label: t('Studio'), routeName: 'addon.aip.user.studio', active: is('addon.aip.user.studio') }] : []),
                ...(imagePro.value.canUseLibrary ? [{ label: t('Library'), routeName: 'addon.aip.user.library', active: is('addon.aip.user.library') }] : []),
            ],
        }] : []),
        ...(affiliateEnabled.value ? [{ label: t('Affiliate'), routeName: 'user.dashboard.affiliate', active: is('user.dashboard.affiliate*'), icon: 'ti ti-affiliate' }] : []),
        {
            label: t('Account'),
            icon: 'ti ti-user-circle',
            active: is('user.dashboard.profile*') || is('user.dashboard.security*') || is('user.dashboard.byok*') || is('user.dashboard.privacy*'),
            sectionKey: 'account',
            children: [
                { label: t('Profile'), routeName: 'user.dashboard.profile', active: is('user.dashboard.profile*') },
                { label: t('Security'), routeName: 'user.dashboard.security', active: is('user.dashboard.security*') },
                ...(byokEnabled.value ? [{ label: t('BYOK'), routeName: 'user.dashboard.byok', active: is('user.dashboard.byok') || is('user.dashboard.byok.*') }] : []),
                ...(isExtendedLicense.value ? [{ label: t('Billing'), routeName: 'user.dashboard.billing', active: is('user.dashboard.billing*') }] : []),
                ...(isProAvailable.value ? [{ label: t('Buy Credits'), routeName: 'user.dashboard.credit-topup', active: is('user.dashboard.credit-topup*') }] : []),
                { label: t('Privacy'), routeName: 'user.dashboard.privacy', active: is('user.dashboard.privacy*') },
            ],
        },
        { label: t('Notifications'), routeName: 'user.dashboard.notifications.index', active: is('user.dashboard.notifications.*'), icon: 'ti ti-bell' },
        ...(ticketsEnabled.value ? [{ label: t('Support'), routeName: 'user.dashboard.support.index', active: is('user.dashboard.support.*'), icon: 'ti ti-message-circle' }] : []),
    ]
    return items
})

// One registry, read by both helpers. Previously each function carried its own
// hardcoded key list, so a nav item whose sectionKey was missing from the toggle
// map (an addon adding a collapsible section) threw on `sections[key].value` and
// took the whole sidebar down.
const sections: Record<string, typeof workspaceOpen> = {
    workspace: workspaceOpen,
    library: libraryOpen,
    account: accountOpen,
    social: socialOpen,
    video: videoOpen,
    voiceover: voiceoverOpen,
    imagePro: imageProOpen,
}

function isSectionOpen(key: string): boolean {
    return sections[key]?.value ?? false
}

function toggleSection(key: string) {
    const section = sections[key]

    // An unregistered key is a bug in the nav item, not a reason to crash the sidebar.
    if (!section) return

    const currentVal = section.value

    // Close all first
    Object.keys(sections).forEach(k => {
        sections[k].value = false
    })

    // Toggle current
    section.value = !currentVal
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
                            'fixed left-0 z-40 flex w-[min(18rem,calc(100vw-1.5rem))] max-w-full flex-col border-r border-gray-100 bg-white shadow-xl transition-transform duration-200 dark:border-surface-800 dark:shadow-black/30 lg:static lg:h-auto lg:w-[260px] lg:translate-x-0 lg:self-start lg:rounded-2xl lg:border lg:shadow-[0_18px_40px_rgba(15,23,42,0.06)]'
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

                        <nav class="flex-1 space-y-1 overflow-y-auto p-3 dark:bg-transparent">
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

                        <div class="shrink-0 rounded-b-2xl border-t border-gray-100 bg-white/90 p-3 dark:border-surface-800 dark:bg-transparent">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ creditBalanceValue }}</div>
                                    <div class="flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span>{{ creditBalanceLabel }}</span>
                                        <Tooltip :content="t('Credits are your usage balance. Each AI generation uses a few credits depending on the tool or model you pick — lighter tools or models cost less.')">
                                            <i class="ti ti-info-circle text-xs text-gray-400 dark:text-gray-500 cursor-help" aria-hidden="true"></i>
                                        </Tooltip>
                                    </div>
                                </div>
                                <Link v-if="isProAvailable" :href="route('user.dashboard.credit-topup')" class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:!bg-primary-100 dark:!bg-primary-900/40 dark:hover:!bg-primary-900/60 transition-colors">
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
