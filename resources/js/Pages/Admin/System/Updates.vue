<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

type UpdateStatus = {
    current_version: string
    latest_version: string | null
    update_available: boolean
    last_checked: string | null
    rollback_available: boolean
    rollback_time: string | null
}

const props = defineProps<{
    update: UpdateStatus
}>()

const { t } = useTranslate()
const checkUpdatesForm = useForm({})
const applyUpdateForm = useForm({})
const rollbackForm = useForm({})
</script>

<template>
    <Head :title="t('One-Click Updates')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('One-Click Updates') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('Check, download, and install the latest version from Envato Marketplace.') }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Update Status') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Compare installed version against the latest release.') }}</p>
                </div>
                <button type="button" :disabled="checkUpdatesForm.processing"
                    class="inline-flex items-center gap-2 rounded-lg btn-primary text-sm disabled:opacity-60"
                    @click="checkUpdatesForm.post(route('admin.system.check-updates'), { preserveScroll: true })">
                    <svg v-if="checkUpdatesForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{ checkUpdatesForm.processing ? t('Checking...') : t('Check for Updates') }}
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Current version') }}</div>
                    <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ update.current_version }}</div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Latest version') }}</div>
                    <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ update.latest_version || '—' }}</div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Last checked') }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ update.last_checked || t('Never') }}</div>
                </div>
            </div>

            <div v-if="update.update_available" class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/20">
                <div class="flex items-start gap-3">
                    <span class="text-lg">🎉</span>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">{{ t('Update Available') }}</p>
                        <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                            {{ t('Version :version is available. Click below to download and install automatically.', { version: update.latest_version ?? '' }) }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <button type="button" :disabled="applyUpdateForm.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:opacity-60"
                                @click="applyUpdateForm.post(route('admin.system.apply-update'), { preserveScroll: true })">
                                <svg v-if="applyUpdateForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                {{ applyUpdateForm.processing ? t('Downloading & Installing...') : t('Download & Install Update') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="update.rollback_available" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                <div class="flex items-start gap-3">
                    <span class="text-lg">⚠️</span>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">{{ t('Rollback Available') }}</p>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            {{ t('A previous version can be restored. This option expires 24 hours after the update.') }}
                            <span v-if="update.rollback_time" class="block mt-1 font-mono text-xs">({{ update.rollback_time }})</span>
                        </p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <button type="button" :disabled="rollbackForm.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-700 disabled:opacity-60"
                                @click="rollbackForm.post(route('admin.system.rollback-update'), { preserveScroll: true })">
                                <svg v-if="rollbackForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                {{ rollbackForm.processing ? t('Rolling back...') : t('Rollback to Previous Version') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
