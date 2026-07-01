<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { defineAsyncComponent, ref, computed } from 'vue'
import AppSelect from '@/Components/AppSelect.vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { badgeClass } from '@/Composables/useBadge'

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))

defineOptions({ layout: UserDashboardLayout })

declare const route: (name: string, params?: unknown) => string

interface Department { id: number; name: string }
interface Ticket {
    id: number
    ticket_number: string
    subject: string
    status: string
    priority: string
    last_reply_at: string | null
    updated_at: string
    department?: Department
}

const props = defineProps<{
    tickets: { data: Ticket[]; links: { url: string | null; label: string; active: boolean }[] }
    departments: Department[]
    filters: { status?: string; search?: string }
    settings: { max_attachments_per_reply: number; max_attachment_size_mb: number; allowed_attachment_types: string }
}>()

const { t } = useTranslate()
const status = ref(props.filters.status ?? 'all')
const search = ref(props.filters.search ?? '')
const showCreate = ref(false)
const attachmentInputRef = ref<HTMLInputElement | null>(null)
const form = useForm({
    subject: '',
    department_id: props.departments[0]?.id ?? null,
    priority: 'medium',
    message: '',
    attachments: [] as File[],
})

const departmentOptions = computed(() => props.departments.map(d => ({ value: d.id, label: d.name })))
const priorityOptions = [{ value: 'low', label: t('Low') }, { value: 'medium', label: t('Medium') }, { value: 'high', label: t('High') }]
const statusOptions = [
    { value: 'all', label: t('All statuses') },
    { value: 'open', label: t('Open') },
    { value: 'in_progress', label: t('In Progress') },
    { value: 'waiting_user', label: t('Waiting User') },
    { value: 'resolved', label: t('Resolved') },
    { value: 'closed', label: t('Closed') },
]

const filter = () => {
    router.get(route('user.dashboard.support.index'), {
        status: status.value !== 'all' ? status.value : undefined,
        search: search.value.trim() !== '' ? search.value.trim() : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const setFiles = (event: Event) => {
    form.attachments = Array.from((event.target as HTMLInputElement).files ?? [])
}

const openAttachmentPicker = () => {
    attachmentInputRef.value?.click()
}

const submit = () => {
    form.post(route('user.dashboard.support.tickets.store'), {
        forceFormData: true,
        onSuccess: () => {
            showCreate.value = false
            form.reset()
        },
    })
}

</script>

<template>
    <Head :title="t('Support')" />

    <div>
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Create tickets and follow replies from the support team.') }}</p>
            </div>
            <button
                type="button"
                @click="showCreate = !showCreate"
                :class="showCreate ? 'border border-red-500 bg-red-500 text-white hover:bg-red-400' : 'bg-primary-500 text-white'"
                class="inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold transition"
            >
                <i :class="showCreate ? 'ti ti-x mr-2' : 'ti ti-plus mr-2'" ></i>
                {{ showCreate ? t('Close') : t('New Ticket') }}
            </button>
        </div>

        <form v-if="showCreate" @submit.prevent="submit" class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-3">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Subject') }}</label>
                    <input v-model="form.subject" type="text" :placeholder="t('Brief summary of your issue')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <span v-if="form.errors.subject" class="mt-1 block text-sm text-red-600">{{ form.errors.subject }}</span>
                </div>
                <div>
                    <AppSelect v-model="form.department_id" :options="departmentOptions" :label="t('Department')" :error="form.errors.department_id" />
                </div>
                <div>
                    <AppSelect v-model="form.priority" :options="priorityOptions" :label="t('Priority')" :error="form.errors.priority" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Attachments') }}</label>
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-2 py-1 dark:border-surface-700 dark:bg-surface-800">
                        <button type="button" @click="openAttachmentPicker" class="inline-flex items-center justify-center rounded-full border border-primary-200 bg-primary-50 px-4 py-1 text-sm font-medium text-primary-600 transition hover:bg-primary-100 dark:border-primary-500/30 dark:bg-primary-500/15 dark:text-primary-300 dark:hover:bg-primary-500/25">
                            {{ t('Choose files') }}
                        </button>
                        <span class="min-w-0 flex-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ form.attachments.length ? t(':count file(s) selected', { count: String(form.attachments.length) }) : t('No files chosen') }}
                        </span>
                        <input ref="attachmentInputRef" type="file" multiple @change="setFiles" class="hidden">
                    </div>
                    <span class="mt-1 block text-xs text-gray-500">{{ t('Max :count files, :size MB each.', { count: String(settings.max_attachments_per_reply), size: String(settings.max_attachment_size_mb) }) }}</span>
                </div>
            </div>
            <div class="mt-4">
                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Message') }}</span>
                <RichEditor v-model="form.message" variant="comment" />
                <span v-if="form.errors.message" class="mt-1 block text-sm text-red-600">{{ form.errors.message }}</span>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center bg-primary-500 text-white rounded-full px-5 py-2.5 text-sm font-semibold disabled:opacity-60">
                    {{ form.processing ? t('Creating...') : t('Create Ticket') }}
                </button>
            </div>
        </form>

        <section class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <div class="relative z-20 flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-end lg:justify-between dark:border-surface-800">
                <div class="relative flex-1">
                    <i class="ti ti-search pointer-events-none absolute inset-y-0 left-4 flex items-center text-gray-400"></i>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('Search by subject, ticket number, or department')"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-11 pr-4 text-sm text-gray-900 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        @keyup.enter="filter"
                    >
                </div>
                <div class="min-w-48">
                    <AppSelect v-model="status" :options="statusOptions" @update:model-value="filter" />
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Ticket') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Department') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Priority') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Last Reply') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-primary-50/40 dark:hover:bg-surface-800">
                            <td class="px-4 py-4">
                                <Link :href="route('user.dashboard.support.tickets.show', ticket.ticket_number)" class="font-semibold text-gray-900 hover:text-primary-600 dark:text-white">{{ ticket.subject }}</Link>
                                <div class="text-xs text-gray-500">{{ ticket.ticket_number }}</div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.department?.name }}</td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.priority)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.priority) }}</span></td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.status) }}</span></td>
                            <td class="px-4 py-4 text-sm text-gray-500">{{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(ticket.last_reply_at ?? ticket.updated_at)) }}</td>
                            <td class="px-4 py-4">
                                <Link
                                    :href="route('user.dashboard.support.tickets.show', ticket.ticket_number)"
                                    class="inline-flex items-center justify-center rounded-full border border-primary-500 px-4 py-2 text-xs font-semibold !text-primary-600 transition hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-500/10"
                                >
                                    {{ t('View') }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="tickets.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">{{ t('No support tickets found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
