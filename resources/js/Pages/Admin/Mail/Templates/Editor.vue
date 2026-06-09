<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import RichEditor from '@/Components/RichEditor.vue'
import { useTranslate } from '@/Composables/useTranslate'

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

interface SelectOption {
    value: string
    label: string
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

const statusValue = ref<string>(form.is_active ? '1' : '0')

const statusOptions = computed<SelectOption[]>(() => [
    { value: '1', label: t('Active') },
    { value: '0', label: t('Disabled') },
])

const variableGuide = computed<Record<string, string>>(() => ({
    '{site_name}': t('Name of your platform'),
    '{site_url}': t('Full URL of your site'),
    '{user_name}': t('Full name of recipient'),
    '{otp_code}': t('Verification code for authentication flows'),
    '{plan_name}': t('Subscription plan name'),
    '{credits}': t('Credit amount for billing or top-up emails'),
}))

const pageTitle = computed(() => props.template.id ? t('Edit Template') : t('New Template'))
const submitLabel = computed(() => props.template.id ? t('Save Changes') : t('Create'))

watch(statusValue, (value) => {
    form.is_active = value === '1'
})

watch(() => form.is_active, (value) => {
    statusValue.value = value ? '1' : '0'
})

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
</script>

<template>
    <Head :title="pageTitle" />

    <div class="px-6 py-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
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

                <div class="flex flex-col gap-3 sm:flex-row">
                    <Link
                        :href="route('admin.mail.templates.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back') }}
                    </Link>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit"
                    >
                        <i class="ti ti-device-floppy text-base"></i>
                        {{ submitLabel }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Template Details') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Define the visible name, delivery state, and email metadata for this template.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                            <div class="md:col-span-1">
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Display Name') }}</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="t('e.g. Monthly Product Update')"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                            </div>

                            <div class="md:col-span-1">
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Slug') }}</label>
                                <input
                                    v-model="form.slug"
                                    :readonly="!props.template.id"
                                    type="text"
                                    :placeholder="t('e.g. monthly_update')"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 font-mono text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 read-only:cursor-not-allowed read-only:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30 dark:read-only:bg-surface-800"
                                >
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    {{ props.template.id ? t('You can adjust the slug for existing templates if needed.') : t('The slug is generated automatically from the display name.') }}
                                </p>
                                <p v-if="form.errors.slug" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.slug }}</p>
                            </div>

                            <div class="md:col-span-2 grid grid-cols-1 gap-6 xl:grid-cols-12">
                                <div class="xl:col-span-8">
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Email Subject') }}</label>
                                <input
                                    v-model="form.subject"
                                    type="text"
                                    :placeholder="t('e.g. Welcome back, {user_name}')"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                                >
                                <p v-if="form.errors.subject" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.subject }}</p>
                            </div>
                                <div class="xl:col-span-4">
                                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Status') }}</label>
                                    <AppSelect v-model="statusValue" :options="statusOptions" :placeholder="t('Select status')" />
                                    <p v-if="form.errors.is_active" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.is_active }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Email Content') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Design the body of the email using rich text and supported merge variables.') }}</p>
                        </div>

                        <div class="p-6">
                            <RichEditor v-model="form.content" />
                            <p v-if="form.errors.content" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ form.errors.content }}</p>
                        </div>
                    </div>
                </form>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Variable Guide') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use these placeholders to personalize outgoing emails.') }}</p>
                        </div>

                        <div class="space-y-4 p-6">
                            <div
                                v-for="(description, token) in variableGuide"
                                :key="token"
                                class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-surface-800"
                            >
                                <p class="font-mono text-sm font-semibold text-primary-700 dark:text-primary-300">{{ token }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Template Notes') }}</h2>
                        </div>

                        <div class="space-y-3 p-6 text-sm text-gray-500 dark:text-gray-400">
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
