<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
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

const driverStatus = computed(() => {
    if (!form.notifications_enabled) {
        return {
            label: t('Disabled'),
            className: 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300',
        }
    }

    if (form.notifications_driver === 'reverb') {
        return connectionStats.value.reverbReady
            ? {
                label: t('Reverb Ready'),
                className: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
            }
            : {
                label: t('Reverb Incomplete'),
                className: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            }
    }

    if (form.notifications_driver === 'pusher') {
        return connectionStats.value.pusherReady
            ? {
                label: t('Pusher Ready'),
                className: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
            }
            : {
                label: t('Pusher Incomplete'),
                className: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            }
    }

    return {
        label: t('Polling Only'),
        className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    }
})

function submit() {
    form.post(route('admin.notifications.settings.update'), {
        preserveScroll: true,
        // Surface validation failures. The reverb/pusher credential fields are
        // now required when their driver is selected; without this the save just
        // silently does nothing and the admin has no idea a field is missing.
        onError: (errors) => {
            const first = Object.values(errors)[0]
            if (first) {
                toastr.error(Array.isArray(first) ? String(first[0]) : String(first))
            }
        },
    })
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

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Notification Settings') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Control in-app notification delivery for admins and users, choose the realtime driver, and verify whether your current notification transport is ready.') }}
                </p>
            </div>

            <div class="shrink-0 flex flex-wrap gap-3">
                <Link
                    :href="route('admin.notifications.index')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>
                <button
                    type="button"
                    :disabled="testing"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary-200 bg-white px-4 py-2 text-sm font-medium text-primary-700 transition hover:bg-primary-50 disabled:opacity-60 dark:border-primary-900/40 dark:bg-gray-800 dark:text-primary-300 dark:hover:bg-primary-900/20"
                    @click="testConnection"
                >
                    <i class="ti ti-plug-connected text-base"></i>
                    {{ testing ? t('Testing...') : t('Test') }}
                </button>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="btn-primary-admin inline-flex items-center justify-center gap-2 disabled:opacity-60"
                    @click="submit"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Settings') }}
                </button>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900 shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Delivery Controls') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose how notifications are delivered and how frequently fallback refresh should run.') }}</p>
            </div>

            <div class="grid gap-6 p-6 md:grid-cols-2">
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
            </div>
        </section>

        <section
            v-if="form.notifications_driver === 'reverb'"
            class="rounded-2xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900 shadow-sm"
        >
            <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Reverb Configuration') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure the built-in Laravel Reverb transport used for realtime notification broadcasting.') }}</p>
            </div>

            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App ID') }}</label>
                    <input v-model="form.reverb.app_id" type="text" :placeholder="t('Enter Reverb app ID')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App Key') }}</label>
                    <input v-model="form.reverb.app_key" type="text" :placeholder="t('Enter Reverb app key')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App Secret') }}</label>
                    <input v-model="form.reverb.app_secret" type="password" :placeholder="props.settings.reverb.secret_configured ? t('Stored securely - leave blank to keep') : t('Enter Reverb app secret')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Host') }}</label>
                    <input v-model="form.reverb.host" type="text" :placeholder="t('e.g. 127.0.0.1')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Port') }}</label>
                    <input v-model.number="form.reverb.port" type="number" min="1" max="65535" :placeholder="t('e.g. 8080')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
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
            class="rounded-2xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900 shadow-sm"
        >
            <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Pusher Configuration') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use this only when the environment is wired for Pusher-compatible delivery instead of Reverb.') }}</p>
            </div>

            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App ID') }}</label>
                    <input v-model="form.pusher.app_id" type="text" :placeholder="t('Enter Pusher app ID')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Key') }}</label>
                    <input v-model="form.pusher.key" type="text" :placeholder="t('Enter Pusher key')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Secret') }}</label>
                    <input v-model="form.pusher.secret" type="password" :placeholder="props.settings.pusher.secret_configured ? t('Stored securely - leave blank to keep') : t('Enter Pusher secret')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Cluster') }}</label>
                    <input v-model="form.pusher.cluster" type="text" :placeholder="t('e.g. mt1')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:focus:ring-primary-900/30">
                </div>
            </div>
        </section>
    </div>

</template>
