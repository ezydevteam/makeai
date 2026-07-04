<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useToastr } from '@/Composables/useToastr'

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))

defineOptions({ layout: AdminLayout })

type NewsletterTab = 'subscribers' | 'campaigns' | 'settings' | 'popup'
type AudienceValue = 'subscribers' | 'users_all' | 'users_active' | 'users_inactive' | 'users_pro' | 'users_free'
type CampaignStatus = 'draft' | 'sending' | 'sent' | string

interface SubscriberItem {
    id: number
    email: string
    name: string | null
    status: string
    created_at: string
}

interface CampaignItem {
    id: number
    subject: string
    audience: AudienceValue
    content: string
    status: CampaignStatus
    created_at: string
    sent_at: string | null
    recipient_count: number
    sent_count: number | null
    failed_count: number | null
    opened_count: number | null
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
    users_all?: number
    users_active?: number
    users_inactive?: number
    users_pro?: number
    users_free?: number
}

interface NewsletterSettings {
    newsletter_driver?: string | null
    mailchimp_server_prefix?: string | null
    mailchimp_list_id?: string | null
    mailchimp_double_optin?: boolean | null
    mailchimp_tags?: string | null
    newsletter_double_optin?: boolean | null
    newsletter_enable_popup?: boolean | null
    newsletter_popup_trigger?: string | null
    newsletter_popup_trigger_value?: string | number | null
    newsletter_popup_title?: string | null
    newsletter_popup_description?: string | null
    newsletter_popup_placeholder?: string | null
    newsletter_popup_submit_text?: string | null
    newsletter_popup_success_message?: string | null
    newsletter_popup_bg_color?: string | null
    newsletter_popup_show_mobile?: boolean | null
    newsletter_popup_cookie_duration?: string | number | null
    newsletter_popup_hide_for_logged_in?: boolean | null
}

const props = defineProps<{
    subscribers: PaginatedCollection<SubscriberItem>
    campaigns: PaginatedCollection<CampaignItem>
    stats: NewsletterStats
    settings: NewsletterSettings
    configuredSecrets: Record<string, boolean>
    isMailConfigured: boolean
    filters?: {
        search?: string
        status?: 'all' | 'subscribed' | 'unsubscribed'
    }
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const toast = useToastr()

const activeTab = ref<NewsletterTab>('subscribers')
const showCampaignModal = ref(false)
const editingCampaignId = ref<number | null>(null)
const sendTargetId = ref<number | null>(null)
const deleteTargetId = ref<number | null>(null)
const deleteSubscriberId = ref<number | null>(null)
const subscriberSearchInput = ref<HTMLInputElement | null>(null)
const subscriberSearch = ref(props.filters?.search || '')
const subscriberStatus = ref<'all' | 'subscribed' | 'unsubscribed'>(props.filters?.status || 'all')
const searchFocused = ref(false)
const filterDebounce = ref<number | null>(null)
const testTargetId = ref<number | null>(null)
const testCampaignForm = useForm({})
const retryTargetId = ref<number | null>(null)
const retryCampaignForm = useForm({})

const tabOptions = [
    { value: 'subscribers', label: t('Subscribers') },
    { value: 'campaigns', label: t('Campaigns') },
    { value: 'settings', label: t('Integrations') },
    { value: 'popup', label: t('Popup') },
] as const

const audienceOptions = [
    { value: 'subscribers', label: t('Newsletter subscribers'), countKey: 'active' },
    { value: 'users_all', label: t('All opted-in users'), countKey: 'users_all' },
    { value: 'users_active', label: t('Active users'), countKey: 'users_active' },
    { value: 'users_inactive', label: t('Inactive users'), countKey: 'users_inactive' },
    { value: 'users_pro', label: t('Pro users'), countKey: 'users_pro' },
    { value: 'users_free', label: t('Free users'), countKey: 'users_free' },
] as const

const driverOptions = [
    { value: 'internal', label: t('Internal only') },
    { value: 'mailchimp', label: t('Mailchimp only') },
    { value: 'both', label: t('Internal + Mailchimp') },
]

const popupTriggerOptions = [
    { value: 'time_delay', label: t('Time delay') },
    { value: 'scroll_depth', label: t('Scroll depth') },
    { value: 'exit_intent', label: t('Exit intent') },
    { value: 'page_views', label: t('Page views') },
    { value: 'first_visit', label: t('First visit only') },
]

const subscriberStatusOptions = [
    { value: 'all', label: t('All status') },
    { value: 'subscribed', label: t('Subscribed') },
    { value: 'unsubscribed', label: t('Unsubscribed') },
]

const campaignForm = useForm({
    subject: '',
    audience: 'subscribers' as AudienceValue,
    content: '',
})

const sendCampaignForm = useForm({})

const settingsForm = useForm({
    newsletter_driver: props.settings.newsletter_driver || 'internal',
    mailchimp_api_key: '',
    mailchimp_server_prefix: props.settings.mailchimp_server_prefix || '',
    mailchimp_list_id: props.settings.mailchimp_list_id || '',
    mailchimp_double_optin: props.settings.mailchimp_double_optin ?? false,
    mailchimp_tags: props.settings.mailchimp_tags || '',
    newsletter_double_optin: props.settings.newsletter_double_optin ?? false,
    newsletter_enable_popup: props.settings.newsletter_enable_popup ?? false,
    newsletter_popup_trigger: props.settings.newsletter_popup_trigger || 'time_delay',
    newsletter_popup_trigger_value: String(props.settings.newsletter_popup_trigger_value || '5'),
    newsletter_popup_title: props.settings.newsletter_popup_title || t('Subscribe to our Newsletter'),
    newsletter_popup_description: props.settings.newsletter_popup_description || t('Get the latest updates delivered directly to your inbox.'),
    newsletter_popup_placeholder: props.settings.newsletter_popup_placeholder || t('Enter your email address'),
    newsletter_popup_submit_text: props.settings.newsletter_popup_submit_text || t('Subscribe'),
    newsletter_popup_success_message: props.settings.newsletter_popup_success_message || t('Thanks for subscribing!'),
    newsletter_popup_bg_color: props.settings.newsletter_popup_bg_color || '#ffffff',
    newsletter_popup_show_mobile: props.settings.newsletter_popup_show_mobile ?? true,
    newsletter_popup_cookie_duration: Number(props.settings.newsletter_popup_cookie_duration || 30),
    newsletter_popup_hide_for_logged_in: props.settings.newsletter_popup_hide_for_logged_in ?? true,
})

const selectedTabLabel = computed(() => tabOptions.find((tab) => tab.value === activeTab.value)?.label ?? t('Subscribers'))
const primaryActionLabel = computed(() => {
    if (activeTab.value === 'campaigns') return t('Create Campaign')
    if (activeTab.value === 'settings' || activeTab.value === 'popup') return t('Save Settings')
    return ''
})
const canShowPrimaryAction = computed(() => activeTab.value !== 'subscribers')
const usesExternalDriver = computed(() => settingsForm.newsletter_driver !== 'internal')
const modalTitle = computed(() => editingCampaignId.value ? t('Edit Campaign') : t('Create Campaign'))
const audienceLabel = (audience: string) => audienceOptions.find((option) => option.value === audience)?.label || t('Newsletter subscribers')

const formatNumber = (value: number | undefined) => new Intl.NumberFormat().format(value ?? 0)

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
    if (activeTab.value !== 'subscribers') {
        return
    }

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

const openCreateCampaign = () => {
    editingCampaignId.value = null
    campaignForm.reset()
    campaignForm.audience = 'subscribers'
    showCampaignModal.value = true
}

const closeCampaignModal = () => {
    showCampaignModal.value = false
    editingCampaignId.value = null
    campaignForm.reset()
}

const submitCampaign = () => {
    campaignForm.post(route('admin.newsletter.campaign.store'), {
        onSuccess: closeCampaignModal,
    })
}

const editCampaign = (campaign: CampaignItem) => {
    editingCampaignId.value = campaign.id
    campaignForm.subject = campaign.subject
    campaignForm.audience = campaign.audience
    campaignForm.content = campaign.content
    showCampaignModal.value = true
}

const updateCampaign = () => {
    if (editingCampaignId.value === null) return

    campaignForm.post(route('admin.newsletter.campaign.update', editingCampaignId.value), {
        onSuccess: closeCampaignModal,
    })
}

const queueCampaign = (id: number) => {
    if (!props.isMailConfigured) {
        toast.error(t('Mail is not configured. Please configure mail settings first.'))
        return
    }
    sendTargetId.value = id
}

const confirmSendCampaign = () => {
    if (sendTargetId.value === null) return

    sendCampaignForm.post(route('admin.newsletter.campaign.send', sendTargetId.value), {
        onFinish: () => {
            sendTargetId.value = null
        },
    })
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

const deleteCampaign = (id: number) => {
    deleteTargetId.value = id
}

const confirmDeleteCampaign = () => {
    if (deleteTargetId.value === null) return

    useForm({}).delete(route('admin.newsletter.campaign.delete', deleteTargetId.value), {
        onFinish: () => {
            deleteTargetId.value = null
        },
    })
}

const queueTestCampaign = (id: number) => {
    if (!props.isMailConfigured) {
        toast.error(t('Mail is not configured. Please configure mail settings first.'))
        return
    }
    testTargetId.value = id
}

const confirmTestCampaign = () => {
    if (testTargetId.value === null) return

    testCampaignForm.post(route('admin.newsletter.campaign.test', testTargetId.value), {
        onFinish: () => {
            testTargetId.value = null
        },
    })
}

const retryCampaign = (id: number) => {
    retryTargetId.value = id
    retryCampaignForm.post(route('admin.newsletter.campaign.retry', id), {
        onFinish: () => {
            retryTargetId.value = null
        },
    })
}

const saveSettings = () => {
    settingsForm.post(route('admin.newsletter.settings.save'))
}

const handlePrimaryAction = () => {
    if (activeTab.value === 'campaigns') {
        openCreateCampaign()
        return
    }

    if (activeTab.value === 'settings' || activeTab.value === 'popup') {
        saveSettings()
    }
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

    if (event.key === '/' && activeTab.value === 'subscribers' && !showCampaignModal.value && !sendTargetId.value && !testTargetId.value && !deleteTargetId.value && !deleteSubscriberId.value && !isTypingTarget) {
        event.preventDefault()
        subscriberSearchInput.value?.focus()
        subscriberSearchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && activeTab.value === 'subscribers' && !showCampaignModal.value && !sendTargetId.value && !testTargetId.value && !deleteTargetId.value && !deleteSubscriberId.value) {
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
    <Head :title="t('Newsletter')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Newsletter') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage subscribers, campaigns, integrations, and popup capture from one place.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    v-if="canShowPrimaryAction"
                    type="button"
                    class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium disabled:opacity-60"
                    :disabled="settingsForm.processing"
                    @click="handlePrimaryAction"
                >
                    <i :class="activeTab === 'campaigns' ? 'ti ti-plus text-base' : 'ti ti-device-floppy text-base'"></i>
                    <span>{{ primaryActionLabel }}</span>
                </button>
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

        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-2 shadow-card dark:border-surface-700 dark:bg-surface-900">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabOptions"
                    :key="tab.value"
                    type="button"
                    class="rounded-xl px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === tab.value ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800'"
                    @click="activeTab = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div v-if="activeTab === 'subscribers'" class="space-y-6">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between border-b border-gray-200 px-6 py-4 dark:border-surface-700">
                    <div class="flex-1 min-w-[240px]">
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
                    <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-52 lg:flex-none">
                            <AppSelect v-model="subscriberStatus" :options="subscriberStatusOptions" :placeholder="t('All status')" />
                        </div>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 w-full sm:w-auto"
                            @click="clearSubscriberFilters"
                        >
                            <i class="ti ti-rotate-clockwise text-base"></i>
                            {{ t('Reset') }}
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-surface-700">
                        <thead class="bg-gray-50 dark:bg-surface-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Email') }}</th>
                                <th class="px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Date') }}</th>
                                <th class="px-6 py-3 text-right text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
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

        <div v-if="activeTab === 'campaigns'" class="space-y-6 w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="campaign in campaigns.data"
                    :key="campaign.id"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card dark:border-surface-700 dark:bg-surface-900"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ campaign.subject }}</h2>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="campaign.status === 'sent'
                                        ? 'bg-green-500/10 text-green-600'
                                        : campaign.status === 'sending'
                                            ? 'bg-yellow-500/10 text-yellow-600'
                                            : 'bg-gray-500/10 text-gray-600'"
                                >
                                    {{ t(campaign.status) }}
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Created: :date', { date: formatDate(campaign.created_at) }) }}
                                <span v-if="campaign.sent_at">{{ t(' • Sent: :date', { date: formatDate(campaign.sent_at) }) }}</span>
                            </p>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                {{ audienceLabel(campaign.audience) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <button
                                v-if="campaign.status === 'draft'"
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-100 bg-white text-gray-500 shadow-sm transition-all hover:bg-gray-50 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-primary-400"
                                :title="t('Edit campaign')"
                                @click="editCampaign(campaign)"
                            >
                                <i class="ti ti-pencil text-base"></i>
                            </button>
                            <button
                                v-if="campaign.status === 'draft'"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-primary-100 px-3 py-2 text-xs font-medium text-primary-700 transition hover:bg-primary-200 disabled:opacity-60"
                                :disabled="sendCampaignForm.processing"
                                @click="queueCampaign(campaign.id)"
                            >
                                <i v-if="sendCampaignForm.processing && sendTargetId === campaign.id" class="ti ti-loader-2 animate-spin text-sm"></i>
                                <i v-else class="ti ti-pointer-collaboration-2 text-sm"></i>
                                <span>{{ sendCampaignForm.processing && sendTargetId === campaign.id ? t('Queueing...') : t('Queue Send') }}</span>
                            </button>
                            <button
                                v-if="campaign.status === 'sent'"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:text-gray-200 dark:hover:bg-surface-800 disabled:opacity-60"
                                :disabled="testCampaignForm.processing"
                                @click="queueTestCampaign(campaign.id)"
                            >
                                <i v-if="testCampaignForm.processing && testTargetId === campaign.id" class="ti ti-loader-2 animate-spin text-sm"></i>
                                <i v-else class="ti ti-send text-sm"></i>
                                <span>{{ testCampaignForm.processing && testTargetId === campaign.id ? t('Sending...') : t('Send Test') }}</span>
                            </button>
                            <button
                                v-if="campaign.status === 'sent' && (campaign.failed_count || 0) > 0"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-amber-100 px-3 py-2 text-xs font-medium text-amber-700 transition hover:bg-amber-200 disabled:opacity-60"
                                :disabled="retryCampaignForm.processing"
                                @click="retryCampaign(campaign.id)"
                            >
                                <i v-if="retryCampaignForm.processing && retryTargetId === campaign.id" class="ti ti-loader-2 animate-spin text-sm"></i>
                                <i v-else class="ti ti-rotate-clockwise-2 text-sm"></i>
                                <span>{{ retryCampaignForm.processing && retryTargetId === campaign.id ? t('Retrying...') : t('Retry Failed') }}</span>
                            </button>
                            <Tooltip v-if="campaign.status !== 'sending'" :content="t('Delete campaign')" placement="top">
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-100 bg-white text-gray-500 shadow-sm transition-all hover:bg-red-50 hover:text-red-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                                    @click="deleteCampaign(campaign.id)"
                                >
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-surface-800">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Recipients') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatNumber(campaign.recipient_count) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-surface-800">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Sent') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatNumber(campaign.sent_count || 0) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-surface-800">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Failed') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatNumber(campaign.failed_count || 0) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-surface-800">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Opened') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatNumber(campaign.opened_count || 0) }}</p>
                        </div>
                    </div>
                </article>

                <div v-if="campaigns.data.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center text-sm text-gray-400 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    {{ t('No campaigns found.') }}
                </div>
            </div>

            <Pagination class="mt-2" :links="campaigns.links" />
        </div>

        <div v-if="activeTab === 'settings'" class="space-y-6 w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <AppSelect v-model="settingsForm.newsletter_driver" :options="driverOptions" :label="t('Newsletter driver')" />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Choose how new subscribers are stored and synced.') }}</p>
                    </div>

                    <label v-if="usesExternalDriver" class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Mailchimp API key') }}</span>
                        <input
                            v-model="settingsForm.mailchimp_api_key"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="configuredSecrets.mailchimp_api_key ? t('Stored securely - leave blank to keep') : t('e.g. 1234567890abcdef-us21')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </label>

                    <label v-if="usesExternalDriver" class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Server prefix') }}</span>
                        <input v-model="settingsForm.mailchimp_server_prefix" type="text" :placeholder="t('e.g. us21')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label v-if="usesExternalDriver" class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Audience / List ID') }}</span>
                        <input v-model="settingsForm.mailchimp_list_id" type="text" :placeholder="t('e.g. 1a2b3c4d5e')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label v-if="usesExternalDriver" class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default tags') }}</span>
                        <input v-model="settingsForm.mailchimp_tags" type="text" :placeholder="t('website_signup, ai_user')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Comma-separated tags to apply to new subscribers.') }}</p>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Double opt-in') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Require email confirmation before adding subscribers.') }}</p>
                        </div>
                        <button type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors" :class="settingsForm.newsletter_double_optin ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" :aria-pressed="settingsForm.newsletter_double_optin" @click="settingsForm.newsletter_double_optin = !settingsForm.newsletter_double_optin">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="settingsForm.newsletter_double_optin ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                    </div>

                    <div v-if="usesExternalDriver" class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Mailchimp double opt-in') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Send a confirmation email to new subscribers.') }}</p>
                        </div>
                        <button type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors" :class="settingsForm.mailchimp_double_optin ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" :aria-pressed="settingsForm.mailchimp_double_optin" @click="settingsForm.mailchimp_double_optin = !settingsForm.mailchimp_double_optin">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="settingsForm.mailchimp_double_optin ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="activeTab === 'popup'" class="space-y-6 w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Enable newsletter popup') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Show a popup to encourage visitors to subscribe.') }}</p>
                    </div>
                    <button type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors" :class="settingsForm.newsletter_enable_popup ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" :aria-pressed="settingsForm.newsletter_enable_popup" @click="settingsForm.newsletter_enable_popup = !settingsForm.newsletter_enable_popup">
                        <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="settingsForm.newsletter_enable_popup ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                </div>
            </section>

            <section v-if="settingsForm.newsletter_enable_popup" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <AppSelect v-model="settingsForm.newsletter_popup_trigger" :options="popupTriggerOptions" :label="t('Trigger type')" />
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Trigger value') }}</span>
                        <input v-model="settingsForm.newsletter_popup_trigger_value" type="text" :placeholder="t('e.g. 5')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</span>
                        <input v-model="settingsForm.newsletter_popup_title" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Success message') }}</span>
                        <input v-model="settingsForm.newsletter_popup_success_message" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</span>
                        <textarea v-model="settingsForm.newsletter_popup_description" rows="3" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Input placeholder') }}</span>
                        <input v-model="settingsForm.newsletter_popup_placeholder" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Submit button text') }}</span>
                        <input v-model="settingsForm.newsletter_popup_submit_text" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Cookie duration (days)') }}</span>
                        <input v-model="settingsForm.newsletter_popup_cookie_duration" type="number" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <div class="block">
                        <AppColorPicker v-model="settingsForm.newsletter_popup_bg_color" :label="t('Background color')" />
                    </div>
                </div>
            </section>

            <section v-if="settingsForm.newsletter_enable_popup" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Show on mobile devices') }}</p>
                        </div>
                        <button type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors" :class="settingsForm.newsletter_popup_show_mobile ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" :aria-pressed="settingsForm.newsletter_popup_show_mobile" @click="settingsForm.newsletter_popup_show_mobile = !settingsForm.newsletter_popup_show_mobile">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="settingsForm.newsletter_popup_show_mobile ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                    </div>

                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Hide for logged-in users') }}</p>
                        </div>
                        <button type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors" :class="settingsForm.newsletter_popup_hide_for_logged_in ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" :aria-pressed="settingsForm.newsletter_popup_hide_for_logged_in" @click="settingsForm.newsletter_popup_hide_for_logged_in = !settingsForm.newsletter_popup_hide_for_logged_in">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="settingsForm.newsletter_popup_hide_for_logged_in ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showCampaignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm">
            <div class="w-full max-w-4xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-surface-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ modalTitle }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Prepare the subject, audience, and message body before sending.') }}</p>
                    </div>
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-surface-800 dark:hover:text-white" @click="closeCampaignModal">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <form class="space-y-6 p-6" @submit.prevent="editingCampaignId ? updateCampaign() : submitCampaign()">
                    <div class="grid gap-6 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Subject') }}</span>
                            <input v-model="campaignForm.subject" type="text" :placeholder="t('Weekly AI Updates')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" required>
                        </label>

                        <div>
                            <AppSelect v-model="campaignForm.audience" :options="audienceOptions.map((option) => ({ value: option.value, label: `${option.label} (${stats[option.countKey as keyof NewsletterStats] ?? 0})` }))" :label="t('Audience')" />
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('User audiences only include active, non-banned users with email marketing enabled.') }}</p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Available Variables') }}</p>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{user_name}, {user_email}, {unsubscribe_url}, {site_name}, {site_url}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Content') }}</label>
                            <RichEditor v-model="campaignForm.content" variant="minimal" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-surface-700">
                        <button type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:text-gray-200 dark:hover:bg-surface-800" @click="closeCampaignModal">
                            {{ t('Cancel') }}
                        </button>
                        <button type="submit" :disabled="campaignForm.processing" class="btn-primary rounded-xl px-4 py-2 text-sm font-medium disabled:opacity-60">
                            {{ editingCampaignId ? t('Update Campaign') : t('Save Draft') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <ActionConfirmModal
            :open="sendTargetId !== null"
            :title="t('Send campaign?')"
            :message="t('This campaign will be queued for delivery to the selected audience.')"
            :confirm-label="t('Queue Send')"
            :processing-label="t('Queueing...')"
            :processing="sendCampaignForm.processing"
            variant="primary"
            @cancel="sendTargetId = null"
            @confirm="confirmSendCampaign"
        />

        <ActionConfirmModal
            :open="testTargetId !== null"
            :title="t('Send test campaign?')"
            :message="t('A test email will be sent to the administrator email address to verify the layout.')"
            :confirm-label="t('Send Test')"
            :processing-label="t('Sending...')"
            :processing="testCampaignForm.processing"
            variant="primary"
            @cancel="testTargetId = null"
            @confirm="confirmTestCampaign"
        />

        <ActionConfirmModal
            :open="deleteTargetId !== null"
            :title="t('Delete campaign?')"
            :message="t('This campaign and all its recipient records will be permanently deleted.')"
            :confirm-label="t('Delete')"
            @cancel="deleteTargetId = null"
            @confirm="confirmDeleteCampaign"
        />

        <ActionConfirmModal
            :open="deleteSubscriberId !== null"
            :title="t('Remove subscriber?')"
            :message="t('This subscriber will be removed from the newsletter list.')"
            :confirm-label="t('Remove')"
            @cancel="deleteSubscriberId = null"
            @confirm="confirmDeleteSubscriber"
        />
</template>
