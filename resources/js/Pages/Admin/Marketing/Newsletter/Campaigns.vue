<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useToastr } from '@/Composables/useToastr'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

defineOptions({ layout: AdminLayout })

type AudienceValue = 'subscribers' | 'users_all' | 'users_active' | 'users_inactive' | 'users_pro' | 'users_free'
type CampaignStatus = 'draft' | 'sending' | 'sent' | string

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
}

interface NewsletterStats {
    total: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    queued: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    sent: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    failed: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    active?: number
    users_all?: number
    users_active?: number
    users_inactive?: number
    users_pro?: number
    users_free?: number
}

const props = defineProps<{
    campaigns: PaginatedCollection<CampaignItem>
    stats: NewsletterStats
    isMailConfigured: boolean
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const toast = useToastr()
const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const showCampaignModal = ref(false)
const editingCampaignId = ref<number | null>(null)
const sendTargetId = ref<number | null>(null)
const deleteTargetId = ref<number | null>(null)
const testTargetId = ref<number | null>(null)

const testCampaignForm = useForm({})
const retryTargetId = ref<number | null>(null)
const retryCampaignForm = useForm({})
const sendCampaignForm = useForm({})

const showFailuresModal = ref(false)
const failuresLoading = ref(false)
const failuresSubject = ref('')
const failuresTotal = ref(0)
const failuresRecipients = ref<{ email: string; name: string | null; error: string | null; failed_at: string | null }[]>([])

const viewFailures = async (campaign: CampaignItem) => {
    showFailuresModal.value = true
    failuresLoading.value = true
    failuresRecipients.value = []
    failuresSubject.value = campaign.subject
    failuresTotal.value = campaign.failed_count || 0

    try {
        const response = await fetch(route('admin.newsletter.campaign.failures', campaign.id), {
            headers: { Accept: 'application/json' },
        })

        if (!response.ok) {
            throw new Error()
        }

        const data = await response.json()
        failuresRecipients.value = data.recipients ?? []
        failuresTotal.value = data.failed_count ?? failuresTotal.value
    } catch {
        toast.error(t('Unable to load failed recipients.'))
    } finally {
        failuresLoading.value = false
    }
}

const campaignForm = useForm({
    subject: '',
    audience: 'subscribers' as AudienceValue,
    content: '',
})

const audienceOptions = [
    { value: 'subscribers', label: t('Newsletter subscribers'), countKey: 'active' },
    { value: 'users_all', label: t('All opted-in users'), countKey: 'users_all' },
    { value: 'users_active', label: t('Active users'), countKey: 'users_active' },
    { value: 'users_inactive', label: t('Inactive users'), countKey: 'users_inactive' },
    { value: 'users_pro', label: t('Premium users'), countKey: 'users_pro' },
    { value: 'users_free', label: t('Free users'), countKey: 'users_free' },
] as const

// Pro/Free audience targeting only makes sense when a paid tier exists. Keep the
// full audienceOptions above for label resolution of existing campaigns, but hide
// those two from the create/edit dropdown when Pro isn't available.
const visibleAudienceOptions = computed(() =>
    audienceOptions.filter((option) =>
        isProAvailable.value || !['users_pro', 'users_free'].includes(option.value)
    )
)

const modalTitle = computed(() => editingCampaignId.value ? t('Edit Campaign') : t('Create Campaign'))
const audienceLabel = (audience: string) => audienceOptions.find((option) => option.value === audience)?.label || t('Newsletter subscribers')
const formatNumber = (value: number | undefined) => new Intl.NumberFormat().format(value ?? 0)

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
</script>

<template>
    <Head :title="t('Newsletter Campaigns')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Campaigns') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Design, schedule, and send email campaigns to your subscribers.') }}
                </p>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                <button
                    type="button"
                    class="shrink-0 btn-primary-admin"
                    @click="openCreateCampaign"
                >
                    <i class="ti ti-plus"></i>
                    {{ t('Create Campaign') }}
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div v-if="stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                :title="t('Total Campaigns')"
                :value="stats.total.value"
                :comparison="stats.total.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-mail text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Queued')"
                :value="stats.queued.value"
                color="warning"
            >
                <template #icon>
                    <i class="ti ti-loader text-lg animate-spin"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Sent')"
                :value="stats.sent.value"
                :comparison="stats.sent.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.sent.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-checkbox text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Failed')"
                :value="stats.failed.value"
                :comparison="stats.failed.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.failed.comparison.type"
                color="danger"
            >
                <template #icon>
                    <i class="ti ti-alert-triangle text-lg"></i>
                </template>
            </StatsCard>
        </div>

        <div class="space-y-6">
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
                            <button
                                v-if="campaign.status === 'sent' && (campaign.failed_count || 0) > 0"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-amber-200 px-3 py-2 text-xs font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-900/40 dark:text-amber-300 dark:hover:bg-amber-950/30"
                                @click="viewFailures(campaign)"
                            >
                                <i class="ti ti-alert-triangle text-sm"></i>
                                <span>{{ t('View Failures') }}</span>
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
    </div>

    <AppModal
        :open="showCampaignModal"
        max-width="max-w-4xl"
        :title="modalTitle"
        :subtitle="t('Prepare the subject, audience, and message body before sending.')"
        has-form
        :confirm-text="editingCampaignId ? t('Update Campaign') : t('Save Draft')"
        :confirm-loading="campaignForm.processing"
        confirm-loading-text="Saving..."
        @close="closeCampaignModal"
        @submit="editingCampaignId ? updateCampaign() : submitCampaign()"
    >
        <div class="grid gap-6 md:grid-cols-2">
            <label class="block md:col-span-2">
                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Subject') }}</span>
                <input v-model="campaignForm.subject" type="text" :placeholder="t('Weekly AI Updates')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" required>
            </label>

            <div>
                <AppSelect
                    v-model="campaignForm.audience"
                    :options="visibleAudienceOptions.map((option) => {
                        const statVal = stats[option.countKey as keyof NewsletterStats];
                        const count = (statVal && typeof statVal === 'object') ? statVal.value : (statVal ?? 0);
                        return { value: option.value, label: `${option.label} (${count})` };
                    })"
                    :label="t('Audience')"
                />
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('User audiences only include active, non-banned users with email marketing enabled.') }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Available Variables') }}</p>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{user_name}, {user_email}, {unsubscribe_url}, {site_name}, {site_url}</p>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Content') }}</label>
                <RichEditor v-model="campaignForm.content" variant="comment" />
            </div>
        </div>
    </AppModal>

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

    <AppModal
        :open="showFailuresModal"
        max-width="max-w-3xl"
        :title="t('Failed Recipients')"
        :subtitle="failuresSubject"
        :cancel-text="t('Close')"
        @close="showFailuresModal = false"
    >
        <div v-if="failuresLoading" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
            <i class="ti ti-loader-2 animate-spin text-lg"></i>
            <p class="mt-2">{{ t('Loading failed recipients...') }}</p>
        </div>
        <div v-else class="space-y-3">
            <p v-if="failuresTotal > failuresRecipients.length" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                {{ t('Showing the most recent :shown of :total failures.', { shown: failuresRecipients.length, total: failuresTotal }) }}
            </p>
            <div v-if="failuresRecipients.length" class="max-h-[26rem] overflow-y-auto rounded-xl border border-gray-100 dark:border-surface-800">
                <div
                    v-for="(recipient, index) in failuresRecipients"
                    :key="index"
                    class="border-b border-gray-100 px-4 py-3 last:border-0 dark:border-surface-800"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ recipient.email }}</span>
                        <span v-if="recipient.failed_at" class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ formatDate(recipient.failed_at) }}</span>
                    </div>
                    <p class="mt-1 break-words text-xs text-red-600 dark:text-red-400">{{ recipient.error || t('No error detail recorded.') }}</p>
                </div>
            </div>
            <p v-else class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No failed recipients found.') }}</p>
        </div>
    </AppModal>
</template>
