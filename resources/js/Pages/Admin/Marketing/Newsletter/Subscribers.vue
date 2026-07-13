<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

defineOptions({ layout: AdminLayout })

interface SubscriberItem {
    id: number
    email: string
    name: string | null
    status: string
    created_at: string
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PaginatedCollection<T> {
    data: T[]
    links: PaginationLink[]
    search?: string
}

interface NewsletterStats {
    total: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    active: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    unsubscribed: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    campaigns: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
}

const props = defineProps<{
    subscribers: PaginatedCollection<SubscriberItem>
    stats: NewsletterStats
    filters?: {
        search?: string
        status?: 'all' | 'subscribed' | 'unsubscribed'
    }
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()

const deleteSubscriberId = ref<number | null>(null)
const subscriberSearchInput = ref<HTMLInputElement | null>(null)
const subscriberSearch = ref(props.filters?.search || '')
const subscriberStatus = ref<'all' | 'subscribed' | 'unsubscribed'>(props.filters?.status || 'all')
const searchFocused = ref(false)
const filterDebounce = ref<number | null>(null)

const subscriberStatusOptions = [
    { value: 'all', label: t('All status') },
    { value: 'subscribed', label: t('Subscribed') },
    { value: 'unsubscribed', label: t('Unsubscribed') },
]

const hasActiveFilters = computed(() => subscriberSearch.value.trim().length > 0 || subscriberStatus.value !== 'all')

const filterSubscribers = () => {
    const query: Record<string, string> = {}

    if (subscriberSearch.value.trim()) {
        query.search = subscriberSearch.value.trim()
    }
    if (subscriberStatus.value && subscriberStatus.value !== 'all') {
        query.status = subscriberStatus.value
    }

    router.get(route('admin.newsletter.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch([subscriberSearch, subscriberStatus], () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }

    filterDebounce.value = window.setTimeout(() => {
        filterSubscribers()
    }, 250) as unknown as number
})

const clearSubscriberSearch = () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }
    subscriberSearch.value = ''
    filterSubscribers()
}

const deleteSubscriber = (id: number) => {
    deleteSubscriberId.value = id
}

const confirmDeleteSubscriber = () => {
    if (deleteSubscriberId.value === null) return

    useForm({}).delete(route('admin.newsletter.subscriber.delete', deleteSubscriberId.value), {
        onFinish: () => {
            deleteSubscriberId.value = null
        },
    })
}

const clearSubscriberFilters = () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }
    subscriberSearch.value = ''
    subscriberStatus.value = 'all'
    filterSubscribers()
}

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const isTypingTarget = target?.tagName === 'INPUT' || target?.tagName === 'TEXTAREA' || target?.tagName === 'SELECT' || target?.isContentEditable

    if (event.key === '/' && !deleteSubscriberId.value && !isTypingTarget) {
        event.preventDefault()
        subscriberSearchInput.value?.focus()
        subscriberSearchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && !deleteSubscriberId.value) {
        if (document.activeElement === subscriberSearchInput.value) {
            event.preventDefault()
            subscriberSearch.value = ''
            subscriberSearchInput.value?.blur()
            return
        }

        if (hasActiveFilters.value) {
            event.preventDefault()
            clearSubscriberFilters()
        }
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Newsletter Subscribers')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Subscribers') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage newsletter subscribers and contact lists.') }}
                </p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div v-if="stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                :title="t('Total Subscribers')"
                :value="stats.total.value"
                :comparison="stats.total.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-users text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Active')"
                :value="stats.active.value"
                :comparison="stats.active.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.active.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-checkbox text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Unsubscribed')"
                :value="stats.unsubscribed.value"
                :comparison="stats.unsubscribed.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.unsubscribed.comparison.type"
                color="danger"
            >
                <template #icon>
                    <i class="ti ti-x text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Campaigns Sent')"
                :value="stats.campaigns.value"
                :comparison="stats.campaigns.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.campaigns.comparison.type"
                color="warning"
            >
                <template #icon>
                    <i class="ti ti-mail text-lg"></i>
                </template>
            </StatsCard>
        </div>

        <div class="space-y-6">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between border-b border-gray-200 px-6 py-4 dark:border-surface-700">
                    <div class="flex-1 min-w-[240px] md:max-w-sm">
                        <label class="sr-only">{{ t('Search subscribers') }}</label>
                        <div class="relative">
                            <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                            <input
                                ref="subscriberSearchInput"
                                v-model="subscriberSearch"
                                type="text"
                                :placeholder="t('Search subscribers...')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-16 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            >
                            <span
                                v-if="!subscriberSearch && !searchFocused"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                            >/</span>
                            <button
                                v-if="subscriberSearch"
                                type="button"
                                class="absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                                :aria-label="t('Clear search')"
                                @click="clearSubscriberSearch"
                            >
                                <i class="ti ti-x text-sm"></i>
                            </button>
                        </div>
                    </div>
                    <div class="shrink-0 w-full sm:w-48">
                        <AppSelect v-model="subscriberStatus" :options="subscriberStatusOptions" :placeholder="t('All status')" />
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-surface-700">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3 text-left">{{ t('Email') }}</th>
                                <th class="px-6 py-3 text-left">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-left">{{ t('Date') }}</th>
                                <th class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                            <tr v-for="subscriber in props.subscribers.data" :key="subscriber.id" class="transition-colors hover:bg-primary-50/40 dark:hover:bg-primary-500/5">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ subscriber.email }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ subscriber.name || t('Anonymous') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="subscriber.status === 'subscribed'
                                            ? 'bg-success-100 text-success-700'
                                            : 'bg-red-100 text-red-700'"
                                    >
                                        {{ t(subscriber.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(subscriber.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Tooltip :content="t('Remove subscriber')" placement="top">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-100 bg-white text-gray-500 shadow-sm transition-all hover:bg-red-50 hover:text-red-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                                            @click="deleteSubscriber(subscriber.id)"
                                        >
                                            <i class="ti ti-trash text-base"></i>
                                        </button>
                                    </Tooltip>
                                </td>
                            </tr>
                            <tr v-if="props.subscribers.data.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-400">
                                    {{ subscriberSearch || subscriberStatus !== 'all' ? t('No subscribers match these filters.') : t('No subscribers found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Pagination class="mt-2" :links="subscribers.links" />
        </div>
    </div>

    <ActionConfirmModal
        :open="deleteSubscriberId !== null"
        :title="t('Remove subscriber?')"
        :message="t('This subscriber will be removed from the newsletter list.')"
        :confirm-label="t('Remove')"
        @cancel="deleteSubscriberId = null"
        @confirm="confirmDeleteSubscriber"
    />
</template>
