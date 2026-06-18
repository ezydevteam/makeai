<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@/Components/AdSection.vue'
import CreditAlertBanner from '@/Components/CreditAlertBanner.vue'
import OnboardingModal from '@/Components/OnboardingModal.vue'

const page = usePage()
const { t } = useTranslate()
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))
const referralUser = computed(() => page.props.auth?.user as any)

const sidebarOpen = ref(false)
const onboardingOpen = ref(false)
const sidebarSearchQuery = ref('')

function performSidebarSearch() {
    const query = sidebarSearchQuery.value.trim()
    if (query.length >= 2) {
        window.location.href = route('user.dashboard.search', { q: query })
    }
}

function onOnboardingClosed() {
    onboardingOpen.value = false
}

onMounted(() => {
    const props = page.props as any
    if (props.showOnboarding) {
        onboardingOpen.value = true
    }
    window.addEventListener('onboarding:closed', onOnboardingClosed)
})

onUnmounted(() => {
    window.removeEventListener('onboarding:closed', onOnboardingClosed)
})

const workspaceOpen = ref(route().current('user.dashboard.playground.*') || route().current('user.dashboard.chains.*') || route().current('user.dashboard.embeds.*'))
const libraryOpen = ref(route().current('user.dashboard.collections.*') || route().current('user.dashboard.favorites.*') || route().current('user.dashboard.history.*') || Boolean(route().current()?.startsWith('user.dashboard.documents.') || route().current()?.startsWith('documents.')))
const accountOpen = ref(route().current('user.dashboard.profile*') || route().current('user.dashboard.security*') || route().current('user.dashboard.api-keys*') || route().current('user.dashboard.billing*') || route().current('user.dashboard.privacy*') || route().current('user.dashboard.credit-topup*'))
const notificationsOpen = ref(route().current('notifications.*') || route().current('user.dashboard.notifications.*'))
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

const navItems = computed<NavItem[]>(() => {
    const is = (name: string) => route().current(name)
    const items: NavItem[] = [
        { label: t('Dashboard'), routeName: 'user.dashboard', active: is('user.dashboard'), icon: 'ti ti-dashboard' },
        { label: t('AI Tools'), routeName: 'ai.tools.index', active: is('ai.tools.*'), icon: 'ti ti-tools' },
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
            active: is('user.dashboard.collections.*') || is('user.dashboard.favorites.*') || is('user.dashboard.history.*') || Boolean(route().current()?.startsWith('user.dashboard.documents.') || route().current()?.startsWith('documents.')),
            sectionKey: 'library',
            children: [
                { label: t('Documents'), routeName: 'user.dashboard.documents.index', active: Boolean(route().current()?.startsWith('user.dashboard.documents.') || route().current()?.startsWith('documents.')) },
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
                { label: t('Composer'), routeName: 'addon.social.user.posts.create', active: is('addon.social.user.posts.create') },
                { label: t('Posts'), routeName: 'addon.social.user.posts.index', active: is('addon.social.user.posts.*') },
                { label: t('Accounts'), routeName: 'addon.social.user.accounts', active: is('addon.social.user.accounts') },
                { label: t('Analytics'), routeName: 'addon.social.user.analytics', active: is('addon.social.user.analytics') },
            ],
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
                { label: t('API Keys'), routeName: 'user.dashboard.api-keys', active: is('user.dashboard.api-keys.*') },
                { label: t('Billing'), routeName: 'user.dashboard.billing', active: is('user.dashboard.billing*') },
                { label: t('Buy Credits'), routeName: 'user.dashboard.credit-topup', active: is('user.dashboard.credit-topup*') },
                { label: t('Privacy'), routeName: 'user.dashboard.privacy', active: is('user.dashboard.privacy*') },
            ],
        },
        { label: t('Notifications'), routeName: 'user.dashboard.notifications.index', active: is('user.dashboard.notifications.*'), icon: 'ti ti-bell' },
        { label: t('Support'), routeName: 'user.dashboard.support.index', active: is('user.dashboard.support.*'), icon: 'ti ti-message-circle' },
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
    if (key === 'workspace') workspaceOpen.value = !workspaceOpen.value
    if (key === 'library') libraryOpen.value = !libraryOpen.value
    if (key === 'account') accountOpen.value = !accountOpen.value
    if (key === 'social') socialOpen.value = !socialOpen.value
    if (key === 'video') videoOpen.value = !videoOpen.value
    if (key === 'voiceover') voiceoverOpen.value = !voiceoverOpen.value
}
</script>

<template>
    <UserLayout>
        <div class="min-h-screen bg-white py-8 dark:bg-surface-950">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Mobile hamburger -->
                <button class="mb-4 flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 lg:hidden" @click="sidebarOpen = !sidebarOpen">
                    <i class="ti ti-menu-2 text-lg"></i>
                    {{ t('Menu') }}
                </button>

                <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <!-- Mobile overlay -->
                    <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="sidebarOpen = false" />

                    <!-- Sidebar -->
                    <aside
                        :class="[
                            sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                            'fixed left-4 top-4 z-50 w-[260px] flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm transition-transform duration-200 dark:border-gray-800 dark:bg-gray-900 lg:static lg:left-auto lg:top-auto lg:z-auto lg:translate-x-0 lg:max-h-none lg:shadow-sm',
                            { 'max-h-[calc(100vh-2rem)] overflow-hidden': !sidebarOpen }
                        ]"
                    >
                        <!-- Search Box -->
                        <div class="p-3 border-b border-gray-100 dark:border-gray-800">
                            <form @submit.prevent="performSidebarSearch" class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    v-model="sidebarSearchQuery"
                                    type="text"
                                    :placeholder="t('Search...')"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500"
                                />
                            </form>
                        </div>

                        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
                            <template v-for="item in navItems" :key="item.label">
                                <!-- Section header (collapsible) -->
                                <button
                                    v-if="item.children && item.children.length"
                                    @click="toggleSection(item.sectionKey!)"
                                    :class="item.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white'"
                                    class="flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-sm font-semibold transition"
                                >
                                    <i :class="item.icon" class="text-lg shrink-0"></i>
                                    <span class="flex-1 text-left">{{ item.label }}</span>
                                    <i class="ti ti-chevron-down shrink-0 text-sm transition-transform" :class="{ 'rotate-180': isSectionOpen(item.sectionKey!) }"></i>
                                </button>

                                <!-- Collapsible children -->
                                <div v-if="item.children && isSectionOpen(item.sectionKey!)" class="ml-4 space-y-1 border-l border-gray-200 pl-3 dark:border-gray-700">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.routeName"
                                        :href="route(child.routeName)"
                                        :class="child.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-transparent text-gray-500 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white'"
                                        class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition"
                                        @click="sidebarOpen = false"
                                    >
                                        <span>{{ child.label }}</span>
                                    </Link>
                                </div>

                                <!-- Standalone link -->
                                <Link
                                    v-else-if="item.routeName"
                                    :href="route(item.routeName)"
                                    :class="item.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white'"
                                    class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm font-semibold transition"
                                    @click="sidebarOpen = false"
                                >
                                    <i :class="item.icon" class="text-lg shrink-0"></i>
                                    <span>{{ item.label }}</span>
                                </Link>
                            </template>
                        </nav>

                        <div class="shrink-0 border-t border-gray-100 p-3 dark:border-gray-700">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ t('Credit Balance') }}</p>
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ referralUser?.credits ?? 0 }}</div>
                                    <div class="text-[10px] text-gray-500">{{ t('credits available') }}</div>
                                </div>
                                <Link :href="route('user.dashboard.credit-topup')" class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 transition-colors">
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
