<script setup lang="ts">
import { Head, usePage, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

const page = usePage()
const { t } = useTranslate()
const { formatDate } = useDateFormat()
const user = computed(() => page.props.auth?.user as any)

const cancelSubscription = () => {
    if (!confirm(t('Cancel your subscription at the end of the current period?'))) return

    router.post('/subscription/cancel', {}, { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Dashboard')" />

    <UserDashboardLayout>
        <div>
            <h1 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Welcome, :name', { name: user?.name ?? '' }) }}</h1>
            <p class="mb-8 text-sm text-gray-500">{{ t('Your AI workspace is ready.') }}</p>

            <div v-if="user?.subscription_status === 'active' || user?.subscription_status === 'trialing'" class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-primary-500">{{ user.subscription_status }}</p>
                        <h2 class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ user.subscription_features?.plan_name || t('Current plan') }}</h2>
                        <p v-if="user.subscription_ends_at" class="mt-1 text-sm text-gray-500">{{ t('Access until :date', { date: formatDate(user.subscription_ends_at) }) }}</p>
                    </div>
                    <button type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-bold text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10" @click="cancelSubscription">
                        {{ t('Cancel subscription') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <Link :href="route('user.dashboard')" class="group cursor-pointer rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all hover:border-primary-200 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500/10 transition-transform group-hover:scale-105">
                        <svg class="h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-gray-900 dark:text-white">{{ t('AI Chat') }}</h3>
                    <p class="text-sm text-gray-500">{{ t('Start a conversation with AI') }}</p>
                </Link>

                <Link :href="route('ai.tools.index')" class="group cursor-pointer rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all hover:border-violet-200 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-violet-500/10 transition-transform group-hover:scale-105">
                        <svg class="h-5 w-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-1.125 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-gray-900 dark:text-white">{{ t('AI Writer') }}</h3>
                    <p class="text-sm text-gray-500">{{ t('Generate content with templates') }}</p>
                </Link>

                <Link :href="route('user.dashboard')" class="group cursor-pointer rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all hover:border-primary-200 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500/10 transition-transform group-hover:scale-105">
                        <svg class="h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-gray-900 dark:text-white">{{ t('Image Generator') }}</h3>
                    <p class="text-sm text-gray-500">{{ t('Create visuals with AI') }}</p>
                </Link>
            </div>
        </div>
    </UserDashboardLayout>
</template>
