<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

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
const { formatDateTime } = useDateFormat()
const checkUpdatesForm = useForm({})
const applyUpdateForm = useForm({})
const rollbackForm = useForm({})

const summaryCards = computed(() => [
    {
        key: 'current',
        label: t('Current version'),
        value: props.update.current_version,
        tone: 'text-primary-700 dark:text-primary-300',
        icon: 'ti ti-package',
    },
    {
        key: 'latest',
        label: t('Latest version'),
        value: props.update.latest_version ?? t('Up to date'),
        tone: props.update.update_available
            ? 'text-blue-700 dark:text-blue-300'
            : 'text-gray-900 dark:text-white',
        icon: 'ti ti-cloud-download',
    },
    {
        key: 'checked',
        label: t('Last checked'),
        value: props.update.last_checked ? formatDateTime(props.update.last_checked) : t('Never'),
        tone: 'text-gray-900 dark:text-white',
        icon: 'ti ti-clock-hour-4',
    },
])
</script>

<template>
    <Head :title="t('One-Click Updates')" />

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('One-Click Updates') }}</h1>
                    <span
                        :class="update.update_available
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                            : 'bg-green-100 text-green-600'"
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em]"
                    >
                        {{ update.update_available ? t('Update Available') : t('Up To Date') }}
                    </span>
                </div>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Check your installed version, compare it against the latest Envato release, and apply or roll back updates when needed.') }}
                </p>
            </div>

            <button
                type="button"
                :disabled="checkUpdatesForm.processing"
                class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-60"
                @click="checkUpdatesForm.post(route('admin.system.check-updates'), { preserveScroll: true })"
            >
                <svg v-if="checkUpdatesForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <i v-else class="ti ti-refresh text-base"></i>
                <span>{{ checkUpdatesForm.processing ? t('Checking...') : t('Check for Updates') }}</span>
            </button>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Update Status') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Review the installed version, latest release, and recovery window before making changes.') }}</p>
            </div>

            <div class="space-y-6 p-6">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <article
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                                <p :class="card.tone" class="mt-3 break-all font-mono text-sm font-semibold">{{ card.value }}</p>
                            </div>
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-300">
                                <i :class="card.icon" class="text-lg"></i>
                            </span>
                        </div>
                    </article>
                </div>

                <div
                    v-if="update.update_available"
                    class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900/40 dark:bg-blue-900/20"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                <i class="ti ti-arrow-up-right text-xl"></i>
                            </span>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">{{ t('Update Available') }}</p>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    {{ t('Version :version is ready to install. The updater will download the release package and apply it automatically.', { version: update.latest_version ?? t('the latest release') }) }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="applyUpdateForm.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:opacity-60"
                            @click="applyUpdateForm.post(route('admin.system.apply-update'), { preserveScroll: true })"
                        >
                            <svg v-if="applyUpdateForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <i v-else class="ti ti-download text-base"></i>
                            <span>{{ applyUpdateForm.processing ? t('Downloading & Installing...') : t('Download & Install Update') }}</span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="update.rollback_available"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/40 dark:bg-amber-900/20"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                <i class="ti ti-history text-xl"></i>
                            </span>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">{{ t('Rollback Available') }}</p>
                                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                    {{ t('A previous version can still be restored. This recovery window remains available for 24 hours after the latest update.') }}
                                </p>
                                <p v-if="update.rollback_time" class="mt-2 font-mono text-xs text-amber-700/80 dark:text-amber-200/80">
                                    {{ formatDateTime(update.rollback_time) }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="rollbackForm.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-700 disabled:opacity-60"
                            @click="rollbackForm.post(route('admin.system.rollback-update'), { preserveScroll: true })"
                        >
                            <svg v-if="rollbackForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <i v-else class="ti ti-history-toggle text-base"></i>
                            <span>{{ rollbackForm.processing ? t('Rolling back...') : t('Rollback to Previous Version') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>

</template>
