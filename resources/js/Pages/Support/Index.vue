<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'
import RichEditor from '@/Components/RichEditor.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

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
    filters: { status?: string }
    settings: { max_attachments_per_reply: number; max_attachment_size_mb: number; allowed_attachment_types: string }
}>()

const { t } = useTranslate()
const status = ref(props.filters.status ?? '')
const showCreate = ref(false)
const form = useForm({
    subject: '',
    department_id: props.departments[0]?.id ?? null,
    priority: 'medium',
    message: '',
    attachments: [] as File[],
})

const filter = () => {
    router.get(route('support.index'), { status: status.value }, { preserveState: true, replace: true })
}

const setFiles = (event: Event) => {
    form.attachments = Array.from((event.target as HTMLInputElement).files ?? [])
}

const submit = () => {
    form.post(route('support.tickets.store'), {
        forceFormData: true,
        onSuccess: () => {
            showCreate.value = false
            form.reset()
        },
    })
}

const badgeClass = (value: string) => ({
    open: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-violet-100 text-violet-700',
    waiting_user: 'bg-amber-100 text-amber-700',
    resolved: 'bg-primary-100 text-primary-700',
    closed: 'bg-gray-100 text-gray-600',
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-700',
    high: 'bg-amber-100 text-amber-700',
    urgent: 'bg-red-100 text-red-700',
}[value] ?? 'bg-gray-100 text-gray-600')
</script>

<template>
    <Head :title="t('Support')" />

    <div class="mx-auto max-w-7xl px-6 py-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Create tickets and follow replies from the support team.') }}</p>
            </div>
            <button type="button" @click="showCreate = !showCreate" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                {{ showCreate ? t('Close') : t('New Ticket') }}
            </button>
        </div>

        <form v-if="showCreate" @submit.prevent="submit" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="grid gap-4 md:grid-cols-3">
                <label class="block md:col-span-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Subject') }}</span>
                    <input v-model="form.subject" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <span v-if="form.errors.subject" class="mt-1 block text-sm text-red-600">{{ form.errors.subject }}</span>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Department') }}</span>
                    <select v-model="form.department_id" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Priority') }}</span>
                    <select v-model="form.priority" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <option value="low">{{ t('Low') }}</option>
                        <option value="medium">{{ t('Medium') }}</option>
                        <option value="high">{{ t('High') }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Attachments') }}</span>
                    <input type="file" multiple @change="setFiles" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <span class="mt-1 block text-xs text-gray-500">{{ t('Max :count files, :size MB each.', { count: String(settings.max_attachments_per_reply), size: String(settings.max_attachment_size_mb) }) }}</span>
                </label>
            </div>
            <div class="mt-4">
                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Message') }}</span>
                <RichEditor v-model="form.message" variant="comment" />
                <span v-if="form.errors.message" class="mt-1 block text-sm text-red-600">{{ form.errors.message }}</span>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">
                    {{ form.processing ? t('Creating...') : t('Create Ticket') }}
                </button>
            </div>
        </form>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row md:items-center dark:border-surface-800">
                <select v-model="status" @change="filter" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="open">{{ t('Open') }}</option>
                    <option value="in_progress">{{ t('In Progress') }}</option>
                    <option value="waiting_user">{{ t('Waiting User') }}</option>
                    <option value="resolved">{{ t('Resolved') }}</option>
                    <option value="closed">{{ t('Closed') }}</option>
                </select>
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-primary-50/40 dark:hover:bg-surface-800">
                            <td class="px-4 py-4">
                                <Link :href="route('support.tickets.show', ticket.ticket_number)" class="font-semibold text-gray-900 hover:text-primary-600 dark:text-white">{{ ticket.subject }}</Link>
                                <div class="text-xs text-gray-500">{{ ticket.ticket_number }}</div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ ticket.department?.name }}</td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.priority)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.priority) }}</span></td>
                            <td class="px-4 py-4"><span :class="badgeClass(ticket.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ t(ticket.status) }}</span></td>
                            <td class="px-4 py-4 text-sm text-gray-500">{{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(ticket.last_reply_at ?? ticket.updated_at)) }}</td>
                        </tr>
                        <tr v-if="tickets.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-500">{{ t('No support tickets found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
