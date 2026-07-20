<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import Tooltip from '@/Components/UI/Tooltip.vue'

defineOptions({ layout: AdminLayout })

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

type PaginationLink = {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    tiers: TierConfig[]
    categories: Record<string, string>
    banCategories: Record<string, string>
    bannedIps: {
        data: BannedIp[]
        links: PaginationLink[]
    }
    overrides: {
        data: Override[]
        links: PaginationLink[]
    }
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()

const activeTab = ref<'tiers' | 'bans' | 'overrides'>('tiers')
const confirmBanTarget = ref<BannedIp | null>(null)
const confirmOverrideTarget = ref<Override | null>(null)

const tierForm = useForm({
    tiers: props.tiers.map((tier) => ({
        category: tier.category,
        guest_max: tier.guest_max,
        guest_window: tier.guest_window,
        free_max: tier.free_max,
        free_window: tier.free_window,
        pro_max: tier.pro_max,
        pro_window: tier.pro_window,
    })),
})

const banForm = useForm({
    ip_address: '',
    reason: '',
    // Default to a full site-wide block — the common intent when banning an IP.
    category: 'site',
    expires_in_hours: null as number | null,
})

const overrideForm = useForm({
    user_id: '',
    category: props.tiers[0]?.category ?? 'text_gen',
    max_attempts: 30,
    window_seconds: 60,
    expires_in_hours: null as number | null,
})

const categoryOptions = computed(() =>
    Object.entries(props.categories).map(([value, label]) => ({ value, label })),
)

const banCategoryOptions = computed(() =>
    Object.entries(props.banCategories).map(([value, label]) => ({ value, label })),
)

const tierTabs = computed(() => [
    { id: 'tiers', label: t('Tier Limits') },
    { id: 'bans', label: t('Banned IPs') },
    { id: 'overrides', label: t('User Overrides') },
])

const tierCounts = computed(() => ({
    categories: tierForm.tiers.length,
    bans: props.bannedIps.data.length,
    overrides: props.overrides.data.length,
}))

const categoryDescriptions = computed<Record<string, string>>(() => ({
    text_gen: t('AI text generation endpoints, including sync and streaming requests.'),
    auth: t('User and admin login submission attempts.'),
    otp: t('OTP, 2FA verification, and password reset attempts.'),
    contact: t('Public contact form submissions.'),
    comments: t('Comment posting, voting, and reporting actions.'),
    newsletter: t('Newsletter signup submissions.'),
    public: t('Public browsing, live search, and guest tool discovery.'),
    social_auth: t('Social login redirects and callback verification.'),
}))

function saveTiers() {
    tierForm.post(route('admin.system.rate-limits.tiers.update'), {
        preserveScroll: true,
    })
}

function submitBan() {
    banForm.post(route('admin.system.rate-limits.ban'), {
        preserveScroll: true,
        onSuccess: () => {
            banForm.reset()
            banForm.category = 'site'
        },
    })
}

function submitOverride() {
    overrideForm.post(route('admin.system.rate-limits.overrides.store'), {
        preserveScroll: true,
        onSuccess: () => {
            overrideForm.reset()
            overrideForm.category = props.tiers[0]?.category ?? 'text_gen'
            overrideForm.max_attempts = 30
            overrideForm.window_seconds = 60
        },
    })
}

function confirmRemoveBan(bannedIp: BannedIp) {
    confirmBanTarget.value = bannedIp
}

function confirmRemoveOverride(override: Override) {
    confirmOverrideTarget.value = override
}

function removeBan() {
    if (!confirmBanTarget.value) {
        return
    }

    router.delete(route('admin.system.rate-limits.unban', { bannedIp: confirmBanTarget.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            confirmBanTarget.value = null
        },
    })
}

function removeOverride() {
    if (!confirmOverrideTarget.value) {
        return
    }

    router.delete(route('admin.system.rate-limits.overrides.delete', { override: confirmOverrideTarget.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            confirmOverrideTarget.value = null
        },
    })
}

function formatWindow(seconds: number): string {
    if (!seconds || Number.isNaN(seconds) || seconds <= 0) {
        return t('Invalid window')
    }

    if (seconds < 60) {
        return t(':seconds sec', { seconds })
    }

    if (seconds < 3600) {
        return t(':seconds sec (:minutes min)', {
            seconds,
            minutes: (seconds / 60).toFixed(1),
        })
    }

    return t(':seconds sec (:hours hr)', {
        seconds,
        hours: (seconds / 3600).toFixed(1),
    })
}
</script>

<template>
    <Head :title="t('Rate Limits')" />

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Rate Limits') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Configure request throttling for each endpoint group, manually block abusive IPs, and assign custom per-user limits where needed.') }}
                </p>
            </div>

            <button
                v-if="activeTab === 'tiers'"
                type="button"
                :disabled="tierForm.processing"
                class="shrink-0  btn-primary-admin inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium disabled:opacity-60"
                @click="saveTiers"
            >
                <i class="ti ti-device-floppy text-base"></i>
                {{ tierForm.processing ? t('Saving...') : t('Save Limits') }}
            </button>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tierTabs"
                    :key="tab.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-all"
                    :class="activeTab === tab.id
                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700'"
                    @click="activeTab = tab.id as 'tiers' | 'bans' | 'overrides'"
                >
                    {{ tab.label }}
                    <span
                        class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
                        :class="activeTab === tab.id
                            ? 'bg-white/70 text-primary-700 dark:bg-primary-950/50 dark:text-primary-200'
                            : 'bg-white text-gray-500 dark:bg-surface-700 dark:text-gray-300'"
                    >
                        {{ tab.id === 'tiers' ? tierCounts.categories : tab.id === 'bans' ? tierCounts.bans : tierCounts.overrides }}
                    </span>
                </button>
            </div>
        </div>

        <div v-if="activeTab === 'tiers'" class="space-y-6 p-4 sm:p-6">
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900/40 dark:bg-blue-900/20 dark:text-blue-200">
                <p class="font-medium">{{ t('Sliding window protection') }}</p>
                <p class="mt-1">
                    {{ t('Each limit checks requests across the previous window from the current moment, which prevents abuse spikes at exact minute boundaries.') }}
                </p>
            </div>

            <form @submit.prevent="saveTiers" class="space-y-4">
                <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-surface-800">
                    <table class="min-w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-surface-800/80">
                                <th class="rounded-tl-xl border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Endpoint Category') }}</th>
                                <th class="border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-amber-700 dark:border-surface-700 dark:text-amber-300">{{ t('Guest Max') }}</th>
                                <th class="border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-amber-700 dark:border-surface-700 dark:text-amber-300">{{ t('Guest Window') }}</th>
                                <th class="border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-blue-700 dark:border-surface-700 dark:text-blue-300">{{ t('Free Max') }}</th>
                                <th class="border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-blue-700 dark:border-surface-700 dark:text-blue-300">{{ t('Free Window') }}</th>
                                <th class="border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-violet-700 dark:border-surface-700 dark:text-violet-300">{{ t('Premium Max') }}</th>
                                <th class="rounded-tr-xl border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-violet-700 dark:border-surface-700 dark:text-violet-300">{{ t('Premium Window') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="tier in tierForm.tiers"
                                :key="tier.category"
                                class="transition hover:bg-primary-50/40 dark:hover:bg-primary-900/10"
                            >
                                <td class="border-b border-gray-100 px-5 py-4 align-top dark:border-surface-800">
                                    <div class="space-y-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ categories[tier.category] ?? tier.category }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ categoryDescriptions[tier.category] ?? '' }}</p>
                                    </div>
                                </td>
                                <td class="border-b border-gray-100 px-3 py-4 text-center dark:border-surface-800">
                                    <input v-model.number="tier.guest_max" type="number" min="1" max="100000" :placeholder="t('e.g. 20')" class="w-24 rounded-xl border border-gray-200 bg-amber-50 px-3 py-2 text-center text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-amber-900/10 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                                </td>
                                <td class="border-b border-gray-100 px-3 py-4 text-center dark:border-surface-800">
                                    <div class="space-y-1">
                                        <input v-model.number="tier.guest_window" type="number" min="1" max="86400" :placeholder="t('e.g. 60')" class="w-24 rounded-xl border border-gray-200 bg-amber-50 px-3 py-2 text-center text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-amber-900/10 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                                        <p class="text-[11px] text-gray-400">{{ formatWindow(tier.guest_window) }}</p>
                                    </div>
                                </td>
                                <td class="border-b border-gray-100 px-3 py-4 text-center dark:border-surface-800">
                                    <input v-model.number="tier.free_max" type="number" min="1" max="100000" :placeholder="t('e.g. 50')" class="w-24 rounded-xl border border-gray-200 bg-blue-50 px-3 py-2 text-center text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-blue-900/10 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                                </td>
                                <td class="border-b border-gray-100 px-3 py-4 text-center dark:border-surface-800">
                                    <div class="space-y-1">
                                        <input v-model.number="tier.free_window" type="number" min="1" max="86400" :placeholder="t('e.g. 60')" class="w-24 rounded-xl border border-gray-200 bg-blue-50 px-3 py-2 text-center text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-blue-900/10 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                                        <p class="text-[11px] text-gray-400">{{ formatWindow(tier.free_window) }}</p>
                                    </div>
                                </td>
                                <td class="border-b border-gray-100 px-3 py-4 text-center dark:border-surface-800">
                                    <input v-model.number="tier.pro_max" type="number" min="1" max="100000" :placeholder="t('e.g. 120')" class="w-24 rounded-xl border border-gray-200 bg-violet-50 px-3 py-2 text-center text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-violet-900/10 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                                </td>
                                <td class="border-b border-gray-100 px-3 py-4 text-center dark:border-surface-800">
                                    <div class="space-y-1">
                                        <input v-model.number="tier.pro_window" type="number" min="1" max="86400" :placeholder="t('e.g. 60')" class="w-24 rounded-xl border border-gray-200 bg-violet-50 px-3 py-2 text-center text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-violet-900/10 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                                        <p class="text-[11px] text-gray-400">{{ formatWindow(tier.pro_window) }}</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Use max for allowed requests and window for the rolling time span in seconds. Example: 60 requests within 60 seconds.') }}</p>
        </div>

        <div v-else-if="activeTab === 'bans'" class="space-y-6 p-4 sm:p-6">
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200">
                <p class="font-medium">{{ t('Manual and automatic bans') }}</p>
                <p class="mt-1">
                    {{ t('IPs can be blocked automatically after repeated abuse or added manually below. Leave the expiration empty to create a permanent ban.') }}
                </p>
            </div>

            <form @submit.prevent="submitBan" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-950/40">
                <div class="grid gap-4 xl:grid-cols-12">
                    <div class="xl:col-span-3">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('IP Address') }}</label>
                        <input v-model="banForm.ip_address" type="text" required :placeholder="t('e.g. 192.168.1.1')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="xl:col-span-3">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Reason') }}</label>
                        <input v-model="banForm.reason" type="text" required :placeholder="t('e.g. Brute force attempt')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="xl:col-span-3">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Ban Scope') }}</label>
                        <AppSelect v-model="banForm.category" :options="banCategoryOptions" :placeholder="t('Select scope')" />
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Expires After Hours') }}</label>
                        <input v-model.number="banForm.expires_in_hours" type="number" min="1" max="8760" :placeholder="t('Optional')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="flex items-end xl:col-span-1">
                        <button type="submit" :disabled="banForm.processing" class="bg-red-600 text-white w-full rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-60 hover:bg-red-700">
                            {{ banForm.processing ? t('Saving...') : t('Ban') }}
                        </button>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-surface-800">
                <table class="min-w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-surface-800/80">
                            <th class="rounded-tl-xl border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('IP Address') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Category') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Reason') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Banned At') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Expires') }}</th>
                            <th class="rounded-tr-xl border-b border-gray-100 px-5 py-4 text-right text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="bannedIps.data.length === 0">
                            <td colspan="6" class="px-6 py-14 text-center">
                                <div class="space-y-1">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('No banned IPs') }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Blocked addresses will appear here once the system or an admin adds them.') }}</p>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="ban in bannedIps.data"
                            :key="ban.id"
                            class="transition hover:bg-primary-50/40 dark:hover:bg-primary-900/10"
                        >
                            <td class="border-b border-gray-100 px-5 py-4 font-mono text-sm text-gray-900 dark:border-surface-800 dark:text-white">{{ ban.ip_address }}</td>
                            <td class="border-b border-gray-100 px-5 py-4 text-sm dark:border-surface-800">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="ban.category === 'site'
                                        ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                        : ban.category === 'all'
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                            : 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-200'"
                                >
                                    {{ banCategories[ban.category] ?? ban.category }}
                                </span>
                            </td>
                            <td class="border-b border-gray-100 px-5 py-4 text-sm text-gray-600 dark:border-surface-800 dark:text-gray-300">{{ ban.reason }}</td>
                            <td class="border-b border-gray-100 px-5 py-4 text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">{{ formatDateTime(ban.banned_at) }}</td>
                            <td class="border-b border-gray-100 px-5 py-4 text-sm dark:border-surface-800">
                                <span v-if="!ban.expires_at" class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ t('Permanent') }}</span>
                                <span v-else class="text-gray-500 dark:text-gray-400">{{ formatDateTime(ban.expires_at) }}</span>
                            </td>
                            <td class="border-b border-gray-100 px-5 py-4 text-right dark:border-surface-800">
                                <Tooltip :content="t('Remove Ban')" placement="top">
                                    <button
                                        type="button"
                                        @click="confirmRemoveBan(ban)"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-danger-600 transition hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-950/30"
                                    >
                                        <i class="ti ti-lock-open text-base"></i>
                                    </button>
                                </Tooltip>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="bannedIps.links" />
        </div>

        <div v-else class="space-y-6 p-4 sm:p-6">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                <p class="font-medium">{{ t('Per-user overrides') }}</p>
                <p class="mt-1">
                    {{ t('Overrides let you raise or lower rate limits for one specific user without changing the entire tier configuration.') }}
                </p>
            </div>

            <form @submit.prevent="submitOverride" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-surface-700 dark:bg-surface-950/40">
                <div class="grid gap-4 xl:grid-cols-12">
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('User ID') }}</label>
                        <input v-model="overrideForm.user_id" type="text" required :placeholder="t('User ID')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="xl:col-span-3">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Endpoint Category') }}</label>
                        <AppSelect v-model="overrideForm.category" :options="categoryOptions" :placeholder="t('Select category')" />
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Requests') }}</label>
                        <input v-model.number="overrideForm.max_attempts" type="number" min="1" max="100000" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Window Seconds') }}</label>
                        <input v-model.number="overrideForm.window_seconds" type="number" min="1" max="86400" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Expires After Hours') }}</label>
                        <input v-model.number="overrideForm.expires_in_hours" type="number" min="1" max="8760" :placeholder="t('Optional')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="flex items-end xl:col-span-1">
                        <button type="submit" :disabled="overrideForm.processing" class="btn-primary-admin w-full rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-60">
                            {{ overrideForm.processing ? t('Saving...') : t('Add') }}
                        </button>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-surface-800">
                <table class="min-w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-surface-800/80">
                            <th class="rounded-tl-xl border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('User') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Category') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Max Requests') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-center text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Window') }}</th>
                            <th class="border-b border-gray-100 px-5 py-4 text-left text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Expires') }}</th>
                            <th class="rounded-tr-xl border-b border-gray-100 px-5 py-4 text-right text-xs font-semibold uppercase text-gray-700 dark:border-surface-700 dark:text-gray-400">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="overrides.data.length === 0">
                            <td colspan="6" class="px-6 py-14 text-center">
                                <div class="space-y-1">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('No overrides') }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Add a custom record above when one user needs a different rate limit than their tier.') }}</p>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="override in overrides.data"
                            :key="override.id"
                            class="transition hover:bg-primary-50/40 dark:hover:bg-primary-900/10"
                        >
                            <td class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                                <div class="space-y-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ override.user?.name ?? `#${override.user_id}` }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ override.user?.email ?? t('Email unavailable') }}</p>
                                </div>
                            </td>
                            <td class="border-b border-gray-100 px-5 py-4 text-sm text-gray-700 dark:border-surface-800 dark:text-gray-200">{{ categories[override.category] ?? override.category }}</td>
                            <td class="border-b border-gray-100 px-5 py-4 text-center font-mono text-sm text-gray-700 dark:border-surface-800 dark:text-gray-200">{{ override.max_attempts }}</td>
                            <td class="border-b border-gray-100 px-5 py-4 text-center dark:border-surface-800">
                                <p class="font-mono text-sm text-gray-700 dark:text-gray-200">{{ override.window_seconds }}s</p>
                                <p class="text-[11px] text-gray-400">{{ formatWindow(override.window_seconds) }}</p>
                            </td>
                            <td class="border-b border-gray-100 px-5 py-4 text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                                {{ override.expires_at ? formatDateTime(override.expires_at) : t('Never') }}
                            </td>
                            <td class="border-b border-gray-100 px-5 py-4 text-right dark:border-surface-800">
                                <Tooltip :content="t('Delete Override')" placement="top">
                                    <button
                                        type="button"
                                        @click="confirmRemoveOverride(override)"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-danger-600 transition hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-950/30"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </Tooltip>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="overrides.links" />
        </div>
        </section>
    </div>


    <ActionConfirmModal
        :open="Boolean(confirmBanTarget)"
        :title="t('Remove IP ban?')"
        :message="t('This IP address will be unblocked and can access the protected endpoints again.')"
        :confirm-label="t('Remove Ban')"
        :cancel-label="t('Cancel')"
        @cancel="confirmBanTarget = null"
        @confirm="removeBan"
    />

    <ActionConfirmModal
        :open="Boolean(confirmOverrideTarget)"
        :title="t('Delete rate limit override?')"
        :message="t('This user-specific rate limit override will be removed and the account will fall back to the normal tier limits.')"
        :confirm-label="t('Delete Override')"
        :cancel-label="t('Cancel')"
        @cancel="confirmOverrideTarget = null"
        @confirm="removeOverride"
    />
</template>
