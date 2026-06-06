<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const toast = useToastr()

type TierConfig = {
    category: string
    guest_max: number
    guest_window: number
    free_max: number
    free_window: number
    pro_max: number
    pro_window: number
}

type BannedIp = {
    id: number
    ip_address: string
    reason: string
    category: string
    banned_at: string
    expires_at: string | null
    banned_by: { id: number; name: string } | null
}

type Override = {
    id: number
    user_id: number
    category: string
    max_attempts: number
    window_seconds: number
    expires_at: string | null
    user: { id: number; name: string; email: string } | null
    created_at: string
}

const props = defineProps<{
    tiers: TierConfig[]
    categories: Record<string, string>
    bannedIps: { data: BannedIp[]; current_page: number; last_page: number }
    overrides: { data: Override[]; current_page: number; last_page: number }
}>()

const activeTab = ref<'tiers' | 'bans' | 'overrides'>('tiers')

const tierForm = useForm({
    tiers: props.tiers.map((t) => ({
        category: t.category,
        guest_max: t.guest_max,
        guest_window: t.guest_window,
        free_max: t.free_max,
        free_window: t.free_window,
        pro_max: t.pro_max,
        pro_window: t.pro_window,
    })),
})

const banForm = useForm({
    ip_address: '',
    reason: '',
    category: 'text_gen',
    expires_in_hours: null as number | null,
})

const overrideForm = useForm({
    user_id: '',
    category: 'text_gen',
    max_attempts: 30,
    window_seconds: 60,
    expires_in_hours: null as number | null,
})

const saveTiers = () => {
    tierForm.post(route('admin.security.rate-limits.tiers.update'), { preserveScroll: true })
}

const submitBan = () => {
    banForm.post(route('admin.security.rate-limits.ban'), {
        preserveScroll: true,
        onSuccess: () => banForm.reset(),
    })
}

const removeBan = (bannedIp: BannedIp) => {
    if (!confirm(t('Remove this IP ban?'))) return
    router.delete(route('admin.security.rate-limits.unban', { bannedIp: bannedIp.id }), {
        preserveScroll: true,
    })
}

const submitOverride = () => {
    overrideForm.post(route('admin.security.rate-limits.overrides.store'), {
        preserveScroll: true,
        onSuccess: () => overrideForm.reset(),
    })
}

const removeOverride = (override: Override) => {
    if (!confirm(t('Remove this override?'))) return
    router.delete(route('admin.security.rate-limits.overrides.delete', { override: override.id }), {
        preserveScroll: true,
    })
}

const formatDateTime = (dt: string | null): string => {
    if (!dt) return t('Never')
    return new Date(dt).toLocaleString()
}

const formatWindow = (seconds: number): string => {
    if (!seconds || isNaN(seconds) || seconds <= 0) return '—'
    if (seconds < 60) return `${seconds}s`
    if (seconds < 3600) return `${seconds}s (${(seconds / 60).toFixed(1)} min)`
    return `${seconds}s (${(seconds / 3600).toFixed(1)} hr)`
}

const categoryDescriptions: Record<string, string> = {
    text_gen: 'AI text generation endpoints (streaming & sync)',
    auth: 'Login form submissions (user & admin)',
    otp: 'OTP/2FA code verification & password reset',
    contact: 'Contact form submissions',
    comments: 'Blog/tool comments: posting, liking, reporting',
    newsletter: 'Newsletter signup form submissions',
    public: 'Public pages: live search, tool catalog browsing',
    social_auth: 'Social login redirects & callbacks',
}
</script>

<template>
    <Head :title="t('Rate Limits')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Rate Limits') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('Sliding-window rate limiting prevents abuse. Each user tier gets configurable limits — non-technical buyers can leave defaults as-is.') }}
            </p>
        </div>

        <!-- Info Banner -->
        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900/40 dark:bg-blue-900/20 dark:text-blue-200">
            <p class="font-semibold">{{ t('How rate limiting works') }}</p>
            <p class="mt-1">
                {{ t('Each endpoint category uses a "sliding window": if the limit is 30 requests per 60 seconds, the system looks back 60 seconds from RIGHT NOW — not from the top of the minute. This prevents burst abuse at window boundaries.') }}
            </p>
        </div>

        <!-- Tabs -->
        <div class="mb-6 flex gap-2 border-b border-gray-200 dark:border-surface-700">
            <button
                v-for="tab in (['tiers', 'bans', 'overrides'] as const)"
                :key="tab"
                @click="activeTab = tab"
                :class="activeTab === tab
                    ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
            >
                {{ tab === 'tiers' ? t('Tier Limits') : tab === 'bans' ? t('Banned IPs') : t('User Overrides') }}
            </button>
        </div>

        <!-- Tier Limits -->
        <section v-if="activeTab === 'tiers'">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                    <span>{{ t('Guest') }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded-full bg-blue-400"></div>
                    <span>{{ t('Free User') }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded-full bg-purple-400"></div>
                    <span>{{ t('Pro User') }}</span>
                </div>
            </div>

            <form @submit.prevent="saveTiers">
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-surface-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-surface-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('Endpoint Category') }}</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1">
                                        {{ t('Guest — Max') }}
                                        <span class="cursor-help" :title="String(t('How many requests a guest (not logged in) can make within the window.'))">&#9432;</span>
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1">
                                        {{ t('Guest — Window') }}
                                        <span class="cursor-help" :title="String(t('Time window in seconds for guest requests (e.g. 3600 = 1 hour).'))">&#9432;</span>
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1">
                                        {{ t('Free — Max') }}
                                        <span class="cursor-help" :title="String(t('How many requests a logged-in free user can make within the window.'))">&#9432;</span>
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1">
                                        {{ t('Free — Window') }}
                                        <span class="cursor-help" :title="String(t('Time window in seconds for free users (e.g. 60 = 1 minute).'))">&#9432;</span>
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1">
                                        {{ t('Pro — Max') }}
                                        <span class="cursor-help" :title="String(t('How many requests a Pro subscribed user can make within the window.'))">&#9432;</span>
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1">
                                        {{ t('Pro — Window') }}
                                        <span class="cursor-help" :title="String(t('Time window in seconds for Pro users (e.g. 60 = 1 minute).'))">&#9432;</span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-for="(tier, i) in tierForm.tiers" :key="tier.category" class="bg-white dark:bg-surface-900 group">
                                <td class="px-4 py-2.5">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ categories[tier.category] ?? tier.category }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ categoryDescriptions[tier.category] ?? '' }}</div>
                                </td>
                                <td class="px-2 py-2.5">
                                    <input
                                        v-model.number="tier.guest_max"
                                        type="number" min="1" max="100000"
                                        :title="String(t('Max requests · Guest tier ·') + ' ' + (categoryDescriptions[tier.category] ?? ''))"
                                        class="w-20 rounded-lg border border-gray-300 dark:border-surface-600 bg-amber-50 dark:bg-amber-900/10 px-2 py-1.5 text-center text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </td>
                                <td class="px-2 py-2.5">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <input
                                            v-model.number="tier.guest_window"
                                            type="number" min="1" max="86400"
                                            :title="String(t('Window seconds · Guest tier'))"
                                            class="w-20 rounded-lg border border-gray-300 dark:border-surface-600 bg-amber-50 dark:bg-amber-900/10 px-2 py-1.5 text-center text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        />
                                        <span class="text-[10px] text-gray-400">{{ formatWindow(tier.guest_window) }}</span>
                                    </div>
                                </td>
                                <td class="px-2 py-2.5">
                                    <input
                                        v-model.number="tier.free_max"
                                        type="number" min="1" max="100000"
                                        :title="String(t('Max requests · Free tier ·') + ' ' + (categoryDescriptions[tier.category] ?? ''))"
                                        class="w-20 rounded-lg border border-gray-300 dark:border-surface-600 bg-blue-50 dark:bg-blue-900/10 px-2 py-1.5 text-center text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </td>
                                <td class="px-2 py-2.5">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <input
                                            v-model.number="tier.free_window"
                                            type="number" min="1" max="86400"
                                            :title="String(t('Window seconds · Free tier'))"
                                            class="w-20 rounded-lg border border-gray-300 dark:border-surface-600 bg-blue-50 dark:bg-blue-900/10 px-2 py-1.5 text-center text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        />
                                        <span class="text-[10px] text-gray-400">{{ formatWindow(tier.free_window) }}</span>
                                    </div>
                                </td>
                                <td class="px-2 py-2.5">
                                    <input
                                        v-model.number="tier.pro_max"
                                        type="number" min="1" max="100000"
                                        :title="String(t('Max requests · Pro tier ·') + ' ' + (categoryDescriptions[tier.category] ?? ''))"
                                        class="w-20 rounded-lg border border-gray-300 dark:border-surface-600 bg-purple-50 dark:bg-purple-900/10 px-2 py-1.5 text-center text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </td>
                                <td class="px-2 py-2.5">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <input
                                            v-model.number="tier.pro_window"
                                            type="number" min="1" max="86400"
                                            :title="String(t('Window seconds · Pro tier'))"
                                            class="w-20 rounded-lg border border-gray-300 dark:border-surface-600 bg-purple-50 dark:bg-purple-900/10 px-2 py-1.5 text-center text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        />
                                        <span class="text-[10px] text-gray-400">{{ formatWindow(tier.pro_window) }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-gray-500">{{ t('All limits are stored safely in your database. No further configuration needed.') }}</p>
                    <button
                        type="submit"
                        :disabled="tierForm.processing"
                        class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-60"
                    >
                        {{ tierForm.processing ? t('Saving...') : t('Save All Tier Limits') }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Banned IPs -->
        <section v-if="activeTab === 'bans'">
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200">
                <p class="font-semibold">{{ t('Auto-ban protection') }}</p>
                <p>{{ t('IPs are automatically banned when a user exceeds the AI abuse threshold (default: 100 hits/min). You can also manually ban IPs below. Leave the hours field empty for a permanent ban.') }}</p>
            </div>

            <form @submit.prevent="submitBan" class="mb-6 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-4 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Manually ban an IP address') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ t('IP Address') }}</label>
                        <input v-model="banForm.ip_address" type="text" required placeholder="192.168.1.1" class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ t('Reason') }}</label>
                        <input v-model="banForm.reason" type="text" required placeholder="Brute force attempt" class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ t('Endpoint Category') }}</label>
                        <select v-model="banForm.category" class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm">
                            <option v-for="(label, val) in categories" :key="val" :value="val">{{ label }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-1">{{ t('Ban duration') }}<span class="text-gray-400"> — {{ t('optional') }}</span></label>
                            <input v-model.number="banForm.expires_in_hours" type="number" min="1" max="8760" placeholder="e.g. 24 hours" class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm" />
                        </div>
                        <button type="submit" :disabled="banForm.processing" class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white hover:bg-danger-500 disabled:opacity-60 whitespace-nowrap">{{ t('Ban IP') }}</button>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-surface-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('IP Address') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('Category') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('Reason') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('Banned At') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('Expires') }}</th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                        <tr v-if="bannedIps.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                                <p class="font-medium">{{ t('No banned IPs') }}</p>
                                <p class="text-xs mt-1">{{ t('IPs will appear here when auto-banned or manually added.') }}</p>
                            </td>
                        </tr>
                        <tr v-for="ban in bannedIps.data" :key="ban.id" class="bg-white dark:bg-surface-900">
                            <td class="px-4 py-2.5 font-mono text-sm text-gray-900 dark:text-white">{{ ban.ip_address }}</td>
                            <td class="px-4 py-2.5">{{ categories[ban.category] ?? ban.category }}</td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ ban.reason }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ formatDateTime(ban.banned_at) }}</td>
                            <td class="px-4 py-2.5">
                                <span v-if="!ban.expires_at" class="text-danger-500 font-medium">{{ t('Permanent') }}</span>
                                <span v-else class="text-gray-500">{{ formatDateTime(ban.expires_at) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <button @click="removeBan(ban)" class="text-danger-500 hover:text-danger-400 text-sm font-medium">{{ t('Remove Ban') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- User Overrides -->
        <section v-if="activeTab === 'overrides'">
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                <p class="font-semibold">{{ t('What are overrides?') }}</p>
                <p>{{ t('Overrides let you give specific users higher (or lower) limits than their tier would normally allow. Useful for trusted power users or limiting specific abusive accounts. Overrides take priority over tier-level settings.') }}</p>
            </div>

            <form @submit.prevent="submitOverride" class="mb-6 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-4 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Add a per-user rate limit override') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ t('User ID') }}</label>
                        <input v-model="overrideForm.user_id" type="text" required placeholder="User ID" :title="String(t('Find the user ID in the Users list.'))" class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ t('Endpoint Category') }}</label>
                        <select v-model="overrideForm.category" class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm">
                            <option v-for="(label, val) in categories" :key="val" :value="val">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">
                            {{ t('Max Requests') }}
                            <span class="cursor-help" :title="String(t('How many requests this user can make within the window.'))">&#9432;</span>
                        </label>
                        <input v-model.number="overrideForm.max_attempts" type="number" min="1" max="100000" required class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">
                            {{ t('Window (seconds)') }}
                            <span class="cursor-help" :title="String(t('Sliding window duration in seconds. 60 = 1 minute, 3600 = 1 hour.'))">&#9432;</span>
                        </label>
                        <input v-model.number="overrideForm.window_seconds" type="number" min="1" max="86400" required class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm" />
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-1">{{ t('Expires after') }}<span class="text-gray-400"> — {{ t('optional') }}</span></label>
                            <input v-model.number="overrideForm.expires_in_hours" type="number" min="1" max="8760" placeholder="e.g. 72 hours" class="w-full rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm" />
                        </div>
                        <button type="submit" :disabled="overrideForm.processing" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-60 whitespace-nowrap">{{ t('Add Override') }}</button>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-surface-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('User') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('Category') }}</th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                <span class="inline-flex items-center gap-1">
                                    {{ t('Max Requests') }}
                                    <span class="cursor-help" :title="String(t('Maximum requests allowed in the window.'))">&#9432;</span>
                                </span>
                            </th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                <span class="inline-flex items-center gap-1">
                                    {{ t('Window') }}
                                    <span class="cursor-help" :title="String(t('Sliding window duration. 60s = 1 minute, 3600s = 1 hour.'))">&#9432;</span>
                                </span>
                            </th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">{{ t('Expires') }}</th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                        <tr v-if="overrides.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                                <p class="font-medium">{{ t('No overrides') }}</p>
                                <p class="text-xs mt-1">{{ t('Overrides let you customize limits for specific users. Add one above to get started.') }}</p>
                            </td>
                        </tr>
                        <tr v-for="ov in overrides.data" :key="ov.id" class="bg-white dark:bg-surface-900">
                            <td class="px-4 py-2.5">
                                <div class="text-gray-900 dark:text-white font-medium">{{ ov.user?.name ?? '#' + ov.user_id }}</div>
                                <div class="text-xs text-gray-500">{{ ov.user?.email }}</div>
                            </td>
                            <td class="px-4 py-2.5">{{ categories[ov.category] ?? ov.category }}</td>
                            <td class="px-4 py-2.5 text-center font-mono">{{ ov.max_attempts }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="font-mono">{{ ov.window_seconds }}s</span>
                                <span class="text-xs text-gray-400 block">{{ formatWindow(ov.window_seconds) }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span v-if="!ov.expires_at" class="text-gray-500">{{ t('Never') }}</span>
                                <span v-else class="text-gray-500">{{ formatDateTime(ov.expires_at) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <button @click="removeOverride(ov)" class="text-danger-500 hover:text-danger-400 text-sm font-medium">{{ t('Delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
