<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

defineOptions({ layout: AdminLayout })

interface MailTemplatePayload {
    id: number | null
    slug: string
    name: string
    subject: string
    content: string
    is_active: boolean
    is_system: boolean
    category: string
}

const props = defineProps<{
    template: MailTemplatePayload
}>()

const { t } = useTranslate()

const form = useForm({
    slug: props.template.slug,
    name: props.template.name,
    subject: props.template.subject,
    content: props.template.content,
    is_active: props.template.is_active,
})

const variableDescriptions = computed<Record<string, string>>(() => ({
    '{site_name}': t('Name of your platform'),
    '{site_url}': t('Full URL of your site'),
    '{user_name}': t('Full name of recipient'),
    '{user_email}': t('Email address of the recipient'),
    '{otp_code}': t('Verification code for authentication flows'),
    '{plan_name}': t('Subscription plan name'),
    '{amount}': t('Formatted money amount'),
    '{reason}': t('Reason given by the administrator, when there is one'),
    '{credits}': t('Credit amount for balance and referral emails'),
    '{message}': t('Body of the announcement'),
    '{method}': t('Payout method, e.g. PayPal or bank transfer'),
    '{confirm_url}': t('Double opt-in confirmation link'),
    '{unsubscribe_url}': t('Unsubscribe link'),
    '{campaign_subject}': t('Subject line of the campaign'),
    '{campaign_title}': t('Headline of the campaign'),
    '{campaign_content}': t('Body of the campaign'),
    '{ticket_number}': t('Support ticket reference, e.g. TKT-2026-00042'),
    '{ticket_subject}': t('Subject line of the support ticket'),
    '{ticket_priority}': t('Support ticket priority'),
    '{ticket_department}': t('Support department the ticket belongs to'),
    '{ticket_url}': t('Link to the ticket'),
    '{customer_name}': t('Name of the customer, for support team emails'),
    '{export_name}': t('File name of the finished export'),
    '{download_url}': t('Download link for the finished export'),
    '{expires_at}': t('Date the export download link stops working'),
    '{post_title}': t('Title of the post a comment was left on'),
    '{comment_url}': t('Link to the approved comment'),
    '{tool_name}': t('Name of the reviewed AI tool'),
    '{review_url}': t('Link to the approved tool review'),
    '{maintenance_title}': t('Headline configured on the maintenance screen'),
    '{maintenance_message}': t('Maintenance notice text, with formatting removed'),
    '{restoration_time}': t('Estimated time the platform comes back, or "as soon as possible"'),
}))

// Available in every template, whatever the category.
const COMMON_VARIABLES = ['{site_name}', '{site_url}', '{user_name}']

// What each category's templates actually receive. Showing an auth template the
// ticket variables just invites someone to use a placeholder that will never be
// substituted and ships as a literal "{ticket_number}" in a real email.
const CATEGORY_VARIABLES: Record<string, string[]> = {
    auth: ['{otp_code}', '{user_email}'],
    account: ['{user_email}', '{credits}', '{message}'],
    subscription: ['{plan_name}', '{amount}', '{reason}'],
    newsletter: ['{confirm_url}', '{unsubscribe_url}', '{campaign_subject}', '{campaign_title}', '{campaign_content}'],
    affiliate: ['{amount}', '{method}'],
    support: ['{ticket_number}', '{ticket_subject}', '{ticket_priority}', '{ticket_department}', '{ticket_url}', '{customer_name}'],
    export: ['{export_name}', '{download_url}', '{expires_at}'],
    content: ['{post_title}', '{comment_url}', '{tool_name}', '{review_url}'],
    // Only the "going offline" template receives these; the all-clear has no
    // window details to report, so it falls back to the common set.
    maintenance: ['{maintenance_title}', '{maintenance_message}', '{restoration_time}'],
}

const category = computed(() => props.template.category)

// A custom template isn't bound to any platform flow, so we can't narrow it —
// show the full set and let the author pick.
const isCustomCategory = computed(() => !CATEGORY_VARIABLES[category.value])

const variableGuide = computed<Array<{ token: string; description: string }>>(() => {
    const descriptions = variableDescriptions.value

    const tokens = isCustomCategory.value
        ? Object.keys(descriptions)
        : [...COMMON_VARIABLES, ...CATEGORY_VARIABLES[category.value]]

    return tokens
        .filter((token) => descriptions[token])
        .map((token) => ({ token, description: descriptions[token] }))
})

const variableGuideHint = computed(() => isCustomCategory.value
    ? t('Every placeholder the platform can substitute. Custom templates are not tied to one flow, so pick the ones your send provides.')
    : t('Placeholders available to :category templates. Anything else will not be substituted.', { category: category.value }))

const pageTitle = computed(() => props.template.id ? t('Edit Template') : t('New Template'))
const submitLabel = computed(() => props.template.id ? t('Save Changes') : t('Create'))

watch(() => form.name, (value) => {
    if (props.template.id) {
        return
    }

    form.slug = value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s_-]/g, '')
        .replace(/[\s-]+/g, '_')
        .replace(/^_+|_+$/g, '')
})

const submit = () => {
    if (props.template.id) {
        form.post(route('admin.mail.templates.update', props.template.id), { preserveScroll: true })
        return
    }

    form.post(route('admin.mail.templates.store'), { preserveScroll: true })
}

const editorRef = ref<any>(null)
const aiLoading = ref<string | null>(null)
const aiError = ref(false)
const toast = useToastr()
const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''

// Shown only when nothing is highlighted. With a selection, RichEditor swaps to
// its own built-in list (improve/shorten/expand/...), which is why these are all
// whole-document actions.
const mailTemplateAiAssistActions = [
    {
        key: 'generate_content',
        label: t('Generate content'),
        description: t('Write a professional email body.'),
    },
    {
        key: 'improve_content',
        label: t('Improve content'),
        description: t('Rewrite the whole body for clarity.'),
    },
    {
        key: 'generate_subject',
        label: t('Generate subject'),
        description: t('Create a concise email subject line.'),
    },
]

// RichEditor's built-in highlighted-text actions. They come back as HTML that
// replaces the selection.
const SELECTION_AI_ACTIONS = [
    'improve_selection',
    'shorten_selection',
    'expand_selection',
    'rephrase_selection',
    'translate_selection',
    'change_tone',
    'summarize_selection',
    'fix_grammar',
]

const runAiAssist = async (action: string) => {
    if (aiLoading.value) return

    aiLoading.value = action
    aiError.value = false
    const selectedText = editorRef.value?.getSelectedText() ?? ''

    try {
        const response = await fetch(route('admin.mail.templates.ai-assist'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                action,
                subject: form.subject,
                content: form.content,
                selected_text: selectedText,
            }),
        })
        const payload = await response.json()

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || t('AI assist failed.'))
        }

        const generated: string = payload.data?.content ?? ''

        if (action === 'generate_subject') {
            form.subject = generated
        } else if (action === 'improve_content') {
            // Whole-body rewrite: RichEditor watches modelValue, so assigning
            // form.content re-renders the editor.
            form.content = generated
        } else if (action === 'generate_content' || action === 'continue_writing') {
            editorRef.value?.insertAtCursor(generated)
        } else if (SELECTION_AI_ACTIONS.includes(action)) {
            editorRef.value?.replaceSelection(generated)
        } else {
            // Never claim success for an action nothing here knows how to apply.
            throw new Error(t('AI assist failed.'))
        }

        toast.success(t('AI assist applied.'))
    } catch (error: any) {
        aiError.value = true
        toast.error(error.message || t('AI assist failed.'))
    } finally {
        aiLoading.value = null
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ pageTitle }}</h1>
                        <span
                            :class="props.template.is_system ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300' : 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300'"
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                        >
                            {{ props.template.is_system ? t('System') : t('Custom') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create or edit reusable email content with merge variables and rich formatting.') }}</p>
                </div>

                <div class="shrink-0 flex gap-3">
                    <Link
                        :href="route('admin.mail.templates.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-200 hover:bg-gray-50 hover:text-gray-700 dark:border-surface-700 dark:bg-surface-900/20 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back') }}
                    </Link>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="btn-primary-admin inline-flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit"
                    >
                        <i class="ti ti-device-floppy text-base"></i>
                        {{ form.processing ? t('Saving...') : submitLabel }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Template Details') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Define the visible name, delivery state, and email metadata for this template.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                            <div class="md:col-span-1">
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ t('Display Name') }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="t('e.g. Monthly Product Update')"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                            </div>

                            <div class="md:col-span-1">
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ t('Slug') }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.slug"
                                    :readonly="!props.template.id"
                                    type="text"
                                    :placeholder="t('e.g. monthly_update')"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 font-mono text-sm text-gray-700 transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 read-only:cursor-not-allowed read-only:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30 dark:read-only:bg-surface-800"
                                >
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    {{ props.template.id ? t('You can adjust the slug for existing templates if needed.') : t('The slug is generated automatically from the display name.') }}
                                </p>
                                <p v-if="form.errors.slug" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.slug }}</p>
                            </div>

                            <div class="md:col-span-2 grid grid-cols-1 gap-6 xl:grid-cols-12">
                                <div class="xl:col-span-8">
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ t('Email Subject') }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.subject"
                                    type="text"
                                    :placeholder="t('e.g. Welcome back, {user_name}')"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                >
                                <p v-if="form.errors.subject" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.subject }}</p>
                            </div>
                                <div class="xl:col-span-4">
                                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Status') }}</label>
                                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-[0.6875rem] dark:border-surface-700 dark:bg-surface-900">
                                        <span class="text-sm font-medium" :class="form.is_active ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500'">
                                            {{ form.is_active ? t('Active') : t('Disabled') }}
                                        </span>
                                        <AppSwitch v-model="form.is_active" :aria-label="t('Status')" />
                                    </div>
                                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                        {{ form.is_active ? t('This template is being sent.') : t('Disabled: the platform will skip this email entirely.') }}
                                    </p>
                                    <p v-if="form.errors.is_active" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.is_active }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ t('Email Content') }}
                                <span class="text-red-500">*</span>
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Design the body of the email using rich text and supported merge variables.') }}</p>
                        </div>

                        <div class="p-6">
                            <RichEditor
                                ref="editorRef"
                                v-model="form.content"
                                variant="full"
                                ai-assist
                                :ai-assist-actions="mailTemplateAiAssistActions"
                                :ai-assist-loading-key="aiLoading"
                                :ai-assist-error="aiError"
                                :ai-assist-label="t('AI Assist')"
                                :ai-assist-loading-label="t('Working...')"
                                @ai-assist="runAiAssist"
                            />
                            <p v-if="form.errors.content" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ form.errors.content }}</p>
                        </div>
                    </div>
                </form>

                <!--
                    The whole column is the sticky unit, not the guide card alone: a
                    sticky element stays in flow, so sticking just the card slid it down
                    over the Template Notes card below it. Capping the column to the
                    viewport and letting the guide flex into the leftover height keeps
                    both cards on screen, with only the variable list scrolling.
                -->
                <div class="space-y-6 xl:sticky xl:top-6 xl:flex xl:max-h-[calc(100vh-3rem)] xl:flex-col">
                    <div class="flex max-h-[calc(100vh-3rem)] flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800 xl:max-h-none xl:min-h-0 xl:flex-1">
                        <div class="shrink-0 border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Available shortcodes') }}</h2>
                                <span
                                    v-if="!isCustomCategory"
                                    class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-surface-800 dark:text-gray-300"
                                >
                                    {{ category }}
                                </span>
                            </div>
                        </div>

                        <div class="flex-1 space-y-4 overflow-y-auto px-6 py-4">
                            <div
                                v-for="variable in variableGuide"
                                :key="variable.token"
                                class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-surface-800"
                            >
                                <p class="font-mono text-sm font-semibold text-primary-700 dark:text-primary-300">{{ variable.token }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ variable.description }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800 xl:shrink-0">
                        <div class="border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Template Notes') }}</h2>
                        </div>
                        <div class="space-y-3 px-6 py-3 text-sm text-gray-500 dark:text-gray-400">
                            <p>{{ t('System templates support built-in platform flows such as authentication, billing, and account notices.') }}</p>
                            <p>{{ t('Custom templates are ideal for newsletters, announcements, or manual outreach campaigns.') }}</p>
                            <p>{{ t('Keep subject lines concise and make sure the message still reads well if a variable is empty.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
