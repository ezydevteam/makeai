<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

type SocialAccount = {
    id: number
    platform: string
    platform_label: string
    platform_username: string | null
    platform_name: string | null
    avatar_url: string | null
    account_type: string
    is_active: boolean
    is_token_expired: boolean
    follower_count: number
    connected_at: string
}

type PlatformItem = {
    slug: string
    label: string
    icon: string
}

const props = defineProps<{
    accounts: SocialAccount[]
    platforms_list: PlatformItem[]
}>()

const { t } = useTranslate()

function accountsForPlatform(platform: string) {
    return props.accounts.filter((account) => account.platform === platform && account.is_active)
}

function formatDate(value: string) {
    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return t('Unknown')
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date)
}

function platformTone(platform: string) {
    if (platform === 'instagram') return 'from-pink-500/15 to-orange-500/10 text-pink-700 dark:text-pink-300'
    if (platform === 'facebook') return 'from-blue-500/15 to-sky-500/10 text-blue-700 dark:text-blue-300'
    if (platform === 'twitter') return 'from-sky-500/15 to-cyan-500/10 text-sky-700 dark:text-sky-300'
    if (platform === 'linkedin') return 'from-blue-700/15 to-indigo-500/10 text-blue-800 dark:text-blue-300'

    return 'from-gray-500/10 to-gray-400/10 text-gray-700 dark:text-gray-300'
}
</script>

<template>
    <Head :title="t('Connected Accounts')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Connected Social Accounts') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('See which networks are connected, monitor token health, and jump back into account setup when a channel needs attention.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div
                v-for="platform in platforms_list"
                :key="platform.slug"
                class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3">
                            <div :class="platformTone(platform.slug)" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br">
                                <i :class="platform.icon" class="text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ platform.label }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{
                                        accountsForPlatform(platform.slug).length
                                            ? t(':count active account(s)', { count: accountsForPlatform(platform.slug).length })
                                            : t('No active account connected yet')
                                    }}
                                </p>
                            </div>
                        </div>

                        <div v-if="accountsForPlatform(platform.slug).length" class="mt-5 space-y-3">
                            <div
                                v-for="account in accountsForPlatform(platform.slug)"
                                :key="account.id"
                                class="rounded-2xl border border-gray-200 bg-gray-50/80 p-4 dark:border-surface-700 dark:bg-surface-800/60"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <img
                                            v-if="account.avatar_url"
                                            :src="account.avatar_url"
                                            class="h-11 w-11 rounded-full object-cover ring-2 ring-white dark:ring-surface-900"
                                        />
                                        <div
                                            v-else
                                            class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-gray-200 text-gray-500 dark:bg-surface-700 dark:text-gray-300"
                                        >
                                            <i class="ti ti-user text-lg"></i>
                                        </div>

                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ account.platform_name || account.platform_username || t('Connected account') }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ account.platform_username || t('No username available') }}
                                            </p>
                                        </div>
                                    </div>

                                    <span
                                        v-if="account.account_type !== 'personal'"
                                        class="inline-flex shrink-0 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-surface-900 dark:text-gray-300 dark:ring-surface-700"
                                    >
                                        {{ account.account_type }}
                                    </span>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 font-medium text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-surface-900 dark:text-gray-300 dark:ring-surface-700">
                                        <i class="ti ti-users text-sm"></i>
                                        {{ new Intl.NumberFormat().format(account.follower_count) }} {{ t('followers') }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 font-medium text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-surface-900 dark:text-gray-300 dark:ring-surface-700">
                                        <i class="ti ti-calendar-event text-sm"></i>
                                        {{ t('Connected :date', { date: formatDate(account.connected_at) }) }}
                                    </span>
                                    <span
                                        v-if="account.is_token_expired"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/20"
                                    >
                                        <i class="ti ti-alert-triangle text-sm"></i>
                                        {{ t('Token expired') }}
                                    </span>
                                </div>

                                <div class="mt-4">
                                    <Link
                                        :href="route('addon.social.user.accounts.disconnect', account.id)"
                                        method="delete"
                                        as="button"
                                        class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-900/60 dark:bg-surface-900 dark:text-red-400 dark:hover:bg-red-950/30"
                                    >
                                        <i class="ti ti-unlink text-sm"></i>
                                        {{ t('Disconnect') }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50/80 p-5 text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800/60 dark:text-gray-400"
                        >
                            {{ t('Connect this platform to start scheduling and tracking post performance here.') }}
                        </div>
                    </div>

                    <Link
                        :href="route('addon.social.user.accounts.redirect', platform.slug)"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-4 py-2 text-sm font-semibold !text-white shadow-sm transition hover:bg-primary-600"
                    >
                        <i class="ti ti-plus text-sm"></i>
                        {{ accountsForPlatform(platform.slug).length ? t('Add another') : t('Connect') }}
                    </Link>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/70 bg-white p-5 text-sm text-gray-500 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/5">
            <div class="flex items-start gap-3">
                <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
                    <i class="ti ti-lock text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Safe credential storage') }}</p>
                    <p class="mt-1 leading-6">{{ t('Your social credentials are stored encrypted and only used for posting, syncing, and pulling analytics for your own workspace.') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
