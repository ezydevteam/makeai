<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

interface NotificationSettings {
    notifications_enabled: boolean
    notifications_driver: 'reverb' | 'pusher' | 'polling'
    notifications_polling_interval: number
    reverb: {
        app_id: string
        app_key: string
        host: string
        port: number
        scheme: 'http' | 'https'
        secret_configured: boolean
    }
    pusher: {
        app_id: string
        key: string
        cluster: string
        secret_configured: boolean
    }
}

const props = defineProps<{
    settings: NotificationSettings
    roles: Array<{ id: number; name: string; slug: string }>
    recommendations: string[]
}>()

const { t } = useTranslate()
const toastr = useToastr()
const testing = ref(false)

const form = useForm({
    notifications_enabled: props.settings.notifications_enabled,
    notifications_driver: props.settings.notifications_driver,
    notifications_polling_interval: props.settings.notifications_polling_interval,
    reverb: {
        app_id: props.settings.reverb.app_id,
        app_key: props.settings.reverb.app_key,
        app_secret: '',
        host: props.settings.reverb.host,
        port: props.settings.reverb.port,
        scheme: props.settings.reverb.scheme,
    },
    pusher: {
        app_id: props.settings.pusher.app_id,
        key: props.settings.pusher.key,
        secret: '',
        cluster: props.settings.pusher.cluster,
    },
})

const driverOptions = computed(() => [
    { value: 'reverb', label: t('Reverb') },
    { value: 'pusher', label: t('Pusher') },
    { value: 'polling', label: t('Polling Only') },
])

const schemeOptions = computed(() => [
    { value: 'http', label: t('HTTP') },
    { value: 'https', label: t('HTTPS') },
])

const pollingIntervalOptions = computed(() => [
    { value: '10000', label: t('10 seconds') },
    { value: '15000', label: t('15 seconds') },
    { value: '30000', label: t('30 seconds') },
    { value: '60000', label: t('60 seconds') },
])

const pollingIntervalValue = computed<string>({
    get: () => String(form.notifications_polling_interval),
    set: (value) => {
        form.notifications_polling_interval = Number(value)
    },
})

const connectionStats = computed(() => ({
    enabled: form.notifications_enabled,
    driver: form.notifications_driver,
    reverbReady: Boolean(form.reverb.app_key && form.reverb.host),
    pusherReady: Boolean(form.pusher.key && form.pusher.cluster),
}))

function submit() {
    form.post(route('admin.notifications.settings.update'), { preserveScroll: true })
}

function csrf() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

async function testConnection() {
    testing.value = true

    try {
        const response = await fetch(route('admin.notifications.test'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
        })

        const json = await response.json()

        if (!response.ok || !json.success) {
            toastr.warning(json.message ?? t('Notification connection is not ready.'))
            return
        }

        toastr.success(json.message ?? t('Notification connection looks ready.'))
    } finally {
        testing.value = false
    }
}
</script>

<template>
    <Head :title="t('Notification Settings')" />

    <div class="max-w-7xl mx-auto space-y-6">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ t('Notification Settings') }}</h1>
                <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-300">
                    {{ t('Control in-app notification delivery for admins and users, choose the realtime driver, and verify whether your current notification transport is ready.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('admin.notifications.index')"
                    class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-200 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
                >
                    <i class="ti ti-arrow-left mr-2"></i>
                    {{ t('Back') }}
                </Link>
                <button
                    type="button"
                    :disabled="testing"
                    class="inline-flex items-center rounded-xl border border-primary-200 bg-white px-4 py-2.5 text-sm font-medium text-primary-700 shadow-sm transition hover:bg-primary-50 disabled:opacity-60 dark:border-primary-900/40 dark:bg-surface-900 dark:text-primary-300 dark:hover:bg-primary-900/20"
                    @click="testConnection"
                >
                    {{ testing ? t('Testing...') : t('Test Connection') }}
                </button>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="btn-primary rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-60"
                    @click="submit"
                >
                    {{ form.processing ? t('Saving...') : t('Save Settings') }}
                </button>
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Delivery Controls') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose how notifications are delivered and how frequently fallback refresh should run.') }}</p>
                </div>

                <div class="grid gap-6 p-6 md:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 dark:border-surface-700 dark:bg-surface-950/50">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable Notifications') }}</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Hide notification bells and stop delivery when disabled.') }}</span>
                        </span>
                        <button
                            type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            :class="form.notifications_enabled ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.notifications_enabled = !form.notifications_enabled"
                        >
                            <span
                                class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                                :class="form.notifications_enabled ? 'translate-x-5' : 'translate-x-1'"
                            />
                        </button>
                    </label>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Driver') }}</label>
                        <AppSelect
                            v-model="form.notifications_driver"
                            :options="driverOptions"
                            :placeholder="t('Select driver')"
                        />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Reverb is preferred for Laravel-native realtime delivery. Polling is the safest fallback.') }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Polling Interval') }}</label>
                        <AppSelect
                            v-model="pollingIntervalValue"
                            :options="pollingIntervalOptions"
                            :placeholder="t('Select interval')"
                        />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Shorter intervals feel faster but increase request frequency.') }}</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 dark:border-surface-700 dark:bg-surface-950/50">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Connection Check') }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Use the test button to validate whether the currently saved driver configuration looks complete enough for delivery.') }}</p>
                    </div>
                </div>
            </section>

            <section
                v-if="form.notifications_driver === 'reverb'"
                class="rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900"
            >
                <div class="border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Reverb Configuration') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure the built-in Laravel Reverb transport used for realtime notification broadcasting.') }}</p>
                </div>

                <div class="grid gap-6 p-6 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App ID') }}</label>
                        <input v-model="form.reverb.app_id" type="text" :placeholder="t('Enter Reverb app ID')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App Key') }}</label>
                        <input v-model="form.reverb.app_key" type="text" :placeholder="t('Enter Reverb app key')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App Secret') }}</label>
                        <input v-model="form.reverb.app_secret" type="password" :placeholder="props.settings.reverb.secret_configured ? t('Stored securely - leave blank to keep') : t('Enter Reverb app secret')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Host') }}</label>
                        <input v-model="form.reverb.host" type="text" :placeholder="t('e.g. 127.0.0.1')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Port') }}</label>
                        <input v-model.number="form.reverb.port" type="number" min="1" max="65535" :placeholder="t('e.g. 8080')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Scheme') }}</label>
                        <AppSelect
                            v-model="form.reverb.scheme"
                            :options="schemeOptions"
                            :placeholder="t('Select scheme')"
                        />
                    </div>
                </div>
            </section>

            <section
                v-if="form.notifications_driver === 'pusher'"
                class="rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900"
            >
                <div class="border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Pusher Configuration') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use this only when the environment is wired for Pusher-compatible delivery instead of Reverb.') }}</p>
                </div>

                <div class="grid gap-6 p-6 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App ID') }}</label>
                        <input v-model="form.pusher.app_id" type="text" :placeholder="t('Enter Pusher app ID')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Key') }}</label>
                        <input v-model="form.pusher.key" type="text" :placeholder="t('Enter Pusher key')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Secret') }}</label>
                        <input v-model="form.pusher.secret" type="password" :placeholder="props.settings.pusher.secret_configured ? t('Stored securely - leave blank to keep') : t('Enter Pusher secret')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Cluster') }}</label>
                        <input v-model="form.pusher.cluster" type="text" :placeholder="t('e.g. mt1')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 shadow-sm placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
