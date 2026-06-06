<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@/Components/AdSection.vue'

const page = usePage()
const { t } = useTranslate()
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))
const referralUser = computed(() => page.props.auth?.user as any)

const navItems = computed(() => [
    {
        label: t('Dashboard'),
        routeName: 'user.dashboard',
        active: route().current('user.dashboard'),
        show: true,
        icon: 'dashboard',
    },
    {
        label: t('AI Tools'),
        routeName: 'ai.tools.index',
        active: route().current('ai.tools.*'),
        show: true,
        icon: 'tools',
    },
    {
        label: t('Favorites'),
        routeName: 'favorites.index',
        active: route().current('favorites.*'),
        show: true,
        icon: 'favorites',
    },
    {
        label: t('Affiliate'),
        routeName: 'affiliate.dashboard',
        active: route().current('affiliate.dashboard'),
        show: affiliateEnabled.value,
        icon: 'affiliate',
    },
    {
        label: t('API Keys'),
        routeName: 'user.api-keys',
        active: route().current('user.api-keys'),
        show: true,
        icon: 'api',
    },
    {
        label: t('Settings'),
        routeName: 'user.settings',
        active: route().current('user.settings'),
        show: true,
        icon: 'settings',
    },
    {
        label: t('Support'),
        routeName: 'support.index',
        active: route().current('support.*'),
        show: true,
        icon: 'support',
    },
].filter((item) => item.show))
</script>

<template>
    <UserLayout>
        <div class="bg-emerald-50/70 py-8 dark:bg-surface-950">
            <div class="mx-auto grid max-w-7xl gap-6 px-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                <aside class="h-fit rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <nav class="space-y-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.routeName"
                            :href="route(item.routeName)"
                            :class="item.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white'"
                            class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm font-semibold transition"
                        >
                            <svg v-if="item.icon === 'dashboard'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                            <svg v-else-if="item.icon === 'tools'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                            <svg v-else-if="item.icon === 'favorites'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg>
                            <svg v-else-if="item.icon === 'affiliate'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m0-12l-3 3m3-3l-3-3m0 15l3-3m-3 3l3 3M15.75 9h3A2.25 2.25 0 0121 11.25v1.5A2.25 2.25 0 0118.75 15h-3" /></svg>
                            <svg v-else-if="item.icon === 'api'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                            <svg v-else-if="item.icon === 'settings'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <svg v-else class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM21 12c0 4.556-4.03 8.25-9 8.25a9.77 9.77 0 01-2.555-.337L3.75 21l1.252-4.383A7.564 7.564 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                            <span>{{ item.label }}</span>
                        </Link>
                    </nav>

                    <div class="mt-4 rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ t('Referral Earnings') }}</p>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ referralUser?.referral_earnings ?? 0 }}</div>
                                <div class="text-[10px] text-gray-500">{{ t('credits earned') }}</div>
                            </div>
                            <Link :href="route('affiliate.dashboard')" class="text-xs font-semibold text-[#1F75FE] hover:underline">
                                {{ t('Payout') }}
                            </Link>
                        </div>
                    </div>
                </aside>

                <main class="min-w-0">
                    <AdSection zone="dashboard_top" class="mb-6" />
                    <slot />
                </main>
            </div>
        </div>
    </UserLayout>
</template>
