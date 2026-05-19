<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichEditor from '@/Components/RichEditor.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

interface Option { id: number; name: string }
interface Reply { id: number; author_type: string; author_name: string; content: string; is_internal_note: boolean; attachments?: { name: string; url: string }[]; created_at: string }
interface Canned { id: number; title: string; content: string; department_id?: number | null }

const props = defineProps<{
    ticket: { ticket_number: string; subject: string; status: string; priority: string; department_id: number; assigned_to?: number | null; user?: { name: string; email: string }; replies: Reply[] }
    departments: Option[]
    admins: Option[]
    cannedResponses: Canned[]
    settings: { ai_reply_suggestion: boolean }
}>()

const { t } = useTranslate()
const form = useForm({ message: '', is_internal_note: false, is_ai_draft: false, attachments: [] as File[] })
const stateForm = useForm({ status: props.ticket.status, priority: props.ticket.priority, department_id: props.ticket.department_id, assigned_to: props.ticket.assigned_to ?? null as number | null })
const sanitize = (html: string) => html
    .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
    .replace(/\son\w+\s*=\s*(".*?"|'.*?'|[^\s>]+)/gi, '')
    .replace(/\s(href|src)\s*=\s*("|\')?\s*javascript:[^"'>\s]*(\2)?/gi, '')
const setFiles = (event: Event) => { form.attachments = Array.from((event.target as HTMLInputElement).files ?? []) }
const sendReply = () => form.post(route('admin.support.tickets.reply', props.ticket.ticket_number), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('message', 'attachments') })
const updateState = () => stateForm.post(route('admin.support.tickets.state', props.ticket.ticket_number), { preserveScroll: true })
const insertCanned = (content: string) => { form.message = `${form.message}\n${content}`.trim() }
const suggestReply = async () => {
    const response = await fetch(route('admin.support.tickets.suggest-reply', props.ticket.ticket_number), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' } })
    const data = await response.json()
    if (data.success) {
        form.message = data.data.content
        form.is_ai_draft = true
    }
}
</script>

<template>
    <Head :title="ticket.subject" />
    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <Link :href="route('admin.support.tickets.index')" class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ t('Back to tickets') }}</Link>
                <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ ticket.subject }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ ticket.ticket_number }} · {{ ticket.user?.name }} · {{ ticket.user?.email }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="space-y-4">
                <article v-for="reply in ticket.replies" :key="reply.id" :class="reply.is_internal_note ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white dark:bg-surface-900 dark:border-surface-800'" class="rounded-xl border p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ reply.author_name }}</div>
                            <div class="text-xs text-gray-500">{{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(reply.created_at)) }}</div>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="reply.is_internal_note ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'">{{ reply.is_internal_note ? t('Internal note') : t(reply.author_type) }}</span>
                    </div>
                    <div class="prose prose-sm max-w-none dark:prose-invert" v-html="sanitize(reply.content)"></div>
                    <div v-if="reply.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                        <a v-for="attachment in reply.attachments" :key="attachment.url" :href="attachment.url" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-primary-50">{{ attachment.name }}</a>
                    </div>
                </article>

                <form @submit.prevent="sendReply" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ form.is_internal_note ? t('Internal Note') : t('Reply') }}</h2>
                        <div class="flex flex-wrap gap-2">
                            <select @change="insertCanned(($event.target as HTMLSelectElement).value)" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="">{{ t('Insert canned response') }}</option>
                                <option v-for="response in cannedResponses" :key="response.id" :value="response.content">{{ response.title }}</option>
                            </select>
                            <button v-if="settings.ai_reply_suggestion" type="button" @click="suggestReply" class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-500">{{ t('AI Suggest Reply') }}</button>
                        </div>
                    </div>
                    <RichEditor v-model="form.message" variant="comment" />
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.is_internal_note" type="checkbox" class="rounded border-gray-300"> {{ t('Internal note') }}</label>
                        <input type="file" multiple @change="setFiles" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </div>
                    <button type="submit" :disabled="form.processing" class="mt-4 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">{{ form.processing ? t('Sending...') : t('Send') }}</button>
                </form>
            </section>

            <aside class="space-y-4">
                <form @submit.prevent="updateState" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Ticket Controls') }}</h2>
                    <div class="space-y-4">
                        <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Status') }}</span><select v-model="stateForm.status" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option v-for="item in ['open', 'in_progress', 'waiting_user', 'resolved', 'closed']" :key="item" :value="item">{{ t(item) }}</option></select></label>
                        <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Priority') }}</span><select v-model="stateForm.priority" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option v-for="item in ['low', 'medium', 'high', 'urgent']" :key="item" :value="item">{{ t(item) }}</option></select></label>
                        <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Department') }}</span><select v-model="stateForm.department_id" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                        <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Assigned agent') }}</span><select v-model="stateForm.assigned_to" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option :value="null">{{ t('Unassigned') }}</option><option v-for="item in admins" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                    </div>
                    <button type="submit" class="mt-5 w-full rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ t('Update Ticket') }}</button>
                </form>
            </aside>
        </div>
    </div>
</template>
