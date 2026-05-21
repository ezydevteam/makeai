<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    settings: {
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
    roles: Array<{ id: number, name: string, slug: string }>
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

const submit = () => {
    form.post(route('admin.notifications.settings.update'), { preserveScroll: true })
}

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

const testConnection = async () => {
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

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Notification Settings') }}</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Control in-app notification delivery for users and admins. Turn the system off here to hide bells and stop queued delivery.') }}
                    </p>
                </div>
                <div class="flex gap-3">
                    <button type="button" :disabled="testing" class="rounded-lg border border-primary-200 px-4 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-50 disabled:opacity-60" @click="testConnection">
                        {{ testing ? t('Testing...') : t('Test connection') }}
                    </button>
                    <button type="button" :disabled="form.processing" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60" @click="submit">
                        {{ form.processing ? t('Saving...') : t('Save settings') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="grid gap-5 md:grid-cols-3">
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable notifications') }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ t('Master switch for all in-app notifications.') }}</span>
                            </span>
                            <input v-model="form.notifications_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Driver') }}</span>
                            <select v-model="form.notifications_driver" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="reverb">{{ t('Reverb') }}</option>
                                <option value="pusher">{{ t('Pusher') }}</option>
                                <option value="polling">{{ t('Polling fallback') }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Polling interval') }}</span>
                            <input v-model="form.notifications_polling_interval" type="number" min="10000" max="300000" step="1000" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <span class="mt-1 block text-xs text-gray-500">{{ t('Milliseconds. 30000 means every 30 seconds.') }}</span>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Reverb credentials') }}</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App ID') }}</span>
                            <input v-model="form.reverb.app_id" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App Key') }}</span>
                            <input v-model="form.reverb.app_key" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App Secret') }}</span>
                            <input v-model="form.reverb.app_secret" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="settings.reverb.secret_configured ? t('Configured - leave blank to keep') : t('Enter app secret')">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Host') }}</span>
                            <input v-model="form.reverb.host" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Port') }}</span>
                            <input v-model="form.reverb.port" type="number" min="1" max="65535" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Scheme') }}</span>
                            <select v-model="form.reverb.scheme" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="http">http</option>
                                <option value="https">https</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Pusher fallback') }}</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('App ID') }}</span>
                            <input v-model="form.pusher.app_id" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Key') }}</span>
                            <input v-model="form.pusher.key" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Secret') }}</span>
                            <input v-model="form.pusher.secret" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="settings.pusher.secret_configured ? t('Configured - leave blank to keep') : t('Enter secret')">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Cluster') }}</span>
                            <input v-model="form.pusher.cluster" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-xl border border-primary-100 bg-primary-50 p-5 shadow-sm dark:border-primary-900/40 dark:bg-primary-900/10">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Recommendations') }}</h2>
                    <ul class="mt-3 space-y-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        <li v-for="item in recommendations" :key="item">{{ item }}</li>
                    </ul>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Admin roles') }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-for="role in roles" :key="role.id" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                            {{ role.name }}
                        </span>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
