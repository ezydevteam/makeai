<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface SelectOption {
    value: string
    label: string
}

const props = defineProps<{
    settings: Record<string, string | number | null>
    configuredSecrets: Record<string, boolean>
}>()

const { t } = useTranslate()
const page = usePage()

const form = useForm({
    mail_driver: props.settings.mail_driver || 'smtp',
    mail_host: props.settings.mail_host || '',
    mail_port: props.settings.mail_port || 587,
    mail_username: props.settings.mail_username || '',
    mail_password: '',
    mail_encryption: props.settings.mail_encryption || 'tls',
    mail_from_address: props.settings.mail_from_address || '',
    mail_from_name: props.settings.mail_from_name || '',
    mailgun_domain: props.settings.mailgun_domain || '',
    mailgun_secret: '',
    mailgun_endpoint: props.settings.mailgun_endpoint || 'api.mailgun.net',
    ses_key: props.settings.ses_key || '',
    ses_secret: '',
    ses_region: props.settings.ses_region || 'us-east-1',
    postmark_token: '',
    sendgrid_api_key: '',
})

const testForm = useForm({
    email: '',
})

const driverOptions = computed<SelectOption[]>(() => [
    { value: 'smtp', label: t('SMTP') },
    { value: 'mailgun', label: t('Mailgun') },
    { value: 'ses', label: t('Amazon SES') },
    { value: 'postmark', label: t('Postmark') },
    { value: 'sendgrid', label: t('SendGrid') },
    { value: 'log', label: t('Log (For Testing)') },
])

const encryptionOptions = computed<SelectOption[]>(() => [
    { value: 'tls', label: t('TLS') },
    { value: 'ssl', label: t('SSL') },
    { value: 'null', label: t('None') },
])

const providerStats = computed(() => ({
    driver: String(form.mail_driver).toUpperCase(),
    sender: form.mail_from_address || t('Not configured'),
    secured: Object.values(props.configuredSecrets).filter(Boolean).length,
}))

const flashSuccess = computed(() => String(page.props.flash?.success ?? ''))
const flashError = computed(() => String(page.props.flash?.error ?? ''))

const submit = () => {
    form.post(route('admin.mail.update'), { preserveScroll: true })
}

const sendTest = () => {
    testForm.post(route('admin.mail.test'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Mail Settings')" />

        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Mail Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure your email delivery provider, sender identity, and test outgoing messages.') }}</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <Link
                    :href="route('admin.mail.templates.index')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-mail text-base"></i>
                    {{ t('Templates') }}
                </Link>
                <Link
                    :href="route('admin.mail.logs.index')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-history text-base"></i>
                    {{ t('Logs') }}
                </Link>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                    @click="submit"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Configuration') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Delivery Provider') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose the service used to send transactional and notification emails.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Mail Driver') }}</label>
                            <AppSelect v-model="form.mail_driver" :options="driverOptions" :placeholder="t('Select a driver')" />
                            <p v-if="form.errors.mail_driver" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.mail_driver }}</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('From Address') }}</label>
                            <input
                                v-model="form.mail_from_address"
                                type="email"
                                :placeholder="t('hello@example.com')"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                            >
                            <p v-if="form.errors.mail_from_address" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.mail_from_address }}</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('From Name') }}</label>
                            <input
                                v-model="form.mail_from_name"
                                type="text"
                                :placeholder="t('Your Team')"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                            >
                            <p v-if="form.errors.mail_from_name" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.mail_from_name }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="form.mail_driver === 'smtp'" class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('SMTP Credentials') }}</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Host') }}</label>
                            <input v-model="form.mail_host" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Port') }}</label>
                            <input v-model="form.mail_port" type="number" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Encryption') }}</label>
                            <AppSelect v-model="form.mail_encryption" :options="encryptionOptions" :placeholder="t('Select encryption')" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Username') }}</label>
                            <input v-model="form.mail_username" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Password') }}</label>
                            <input
                                v-model="form.mail_password"
                                type="password"
                                :placeholder="configuredSecrets.mail_password ? t('Stored securely - leave blank to keep') : ''"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                            >
                        </div>
                    </div>
                </div>

                <div v-if="form.mail_driver === 'mailgun'" class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Mailgun API') }}</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Domain') }}</label>
                            <input v-model="form.mailgun_domain" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Endpoint') }}</label>
                            <AppSelect
                                v-model="form.mailgun_endpoint"
                                :options="[
                                    { value: 'api.mailgun.net', label: t('US (api.mailgun.net)') },
                                    { value: 'api.eu.mailgun.net', label: t('EU (api.eu.mailgun.net)') },
                                ]"
                                :placeholder="t('Select endpoint')"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Secret Key') }}</label>
                            <input
                                v-model="form.mailgun_secret"
                                type="password"
                                :placeholder="configuredSecrets.mailgun_secret ? t('Stored securely - leave blank to keep') : ''"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                            >
                        </div>
                    </div>
                </div>

                <div v-if="form.mail_driver === 'ses'" class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Amazon SES') }}</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Access Key ID') }}</label>
                            <input v-model="form.ses_key" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Region') }}</label>
                            <input v-model="form.ses_region" type="text" :placeholder="t('us-east-1')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Secret Access Key') }}</label>
                            <input
                                v-model="form.ses_secret"
                                type="password"
                                :placeholder="configuredSecrets.ses_secret ? t('Stored securely - leave blank to keep') : ''"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                            >
                        </div>
                    </div>
                </div>

                <div v-if="form.mail_driver === 'postmark'" class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Postmark API') }}</h2>
                    </div>
                    <div class="p-6">
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Server Token') }}</label>
                        <input
                            v-model="form.postmark_token"
                            type="password"
                            :placeholder="configuredSecrets.postmark_token ? t('Stored securely - leave blank to keep') : ''"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                        >
                    </div>
                </div>

                <div v-if="form.mail_driver === 'sendgrid'" class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('SendGrid API') }}</h2>
                    </div>
                    <div class="p-6">
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('API Key') }}</label>
                        <input
                            v-model="form.sendgrid_api_key"
                            type="password"
                            :placeholder="configuredSecrets.sendgrid_api_key ? t('Stored securely - leave blank to keep') : ''"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                        >
                    </div>
                </div>
            </form>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Connectivity Test') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Save your settings first, then send a test email to verify provider connectivity.') }}</p>
                    </div>

                    <form @submit.prevent="sendTest" class="space-y-4 p-6">
                        <div
                            v-if="flashSuccess"
                            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-900/10 dark:text-emerald-300"
                        >
                            {{ flashSuccess }}
                        </div>

                        <div
                            v-if="flashError"
                            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/30 dark:bg-red-900/10 dark:text-red-300"
                        >
                            {{ flashError }}
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Recipient Email') }}</label>
                            <input
                                v-model="testForm.email"
                                type="email"
                                :placeholder="t('test@example.com')"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm transition placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                            >
                            <p v-if="testForm.errors.email" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ testForm.errors.email }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="testForm.processing"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-surface-700 dark:hover:bg-surface-600"
                        >
                            <i class="ti ti-send text-base"></i>
                            {{ testForm.processing ? t('Sending...') : t('Send Test Mail') }}
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border border-primary-200 bg-primary-50 p-6 shadow-sm dark:border-primary-900/30 dark:bg-primary-900/10">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-500 text-white shadow-sm">
                            <i class="ti ti-mail-bolt text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Delivery Advice') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ t('For better deliverability, use a dedicated provider such as Mailgun, Postmark, SES, or SendGrid instead of basic shared SMTP.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>
