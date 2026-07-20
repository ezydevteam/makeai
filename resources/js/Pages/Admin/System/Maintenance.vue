<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { defineAsyncComponent, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { fromDateTimeLocalInput, toDateTimeLocalInput } from '@/lib/datetime'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

type MaintenanceSettings = {
    maintenance_title: string
    maintenance_message: string
    maintenance_estimated_restoration_time: string | null
    maintenance_allowed_ips: string
    maintenance_background_image: string | null
    maintenance_background_image_url: string | null
}

const props = defineProps<{
    maintenance: MaintenanceSettings
    status: {
        is_maintenance: boolean
    }
}>()

const { t } = useTranslate()
const confirmOpen = ref(false)
const selectedBackground = ref<File | null>(null)
const backgroundPreview = ref<string | null>(props.maintenance.maintenance_background_image_url)

const maintenanceForm = useForm({
    ...props.maintenance,
    // The server sends an instant; the picker needs the admin's local wall clock.
    maintenance_estimated_restoration_time: toDateTimeLocalInput(props.maintenance.maintenance_estimated_restoration_time),
    maintenance_background_image: null as File | null,
    remove_maintenance_background_image: false,
})
const toggleForm = useForm({})

const saveMaintenance = () => {
    maintenanceForm.maintenance_background_image = selectedBackground.value

    maintenanceForm.transform((data) => ({
        ...data,
        maintenance_estimated_restoration_time: fromDateTimeLocalInput(data.maintenance_estimated_restoration_time),
    }))

    maintenanceForm.post(route('admin.system.maintenance.settings'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            selectedBackground.value = null
            maintenanceForm.remove_maintenance_background_image = false
        },
    })
}

const toggleMaintenance = () => {
    toggleForm.post(route('admin.system.maintenance.toggle'), {
        preserveScroll: true,
        onFinish: () => { confirmOpen.value = false },
    })
}

const selectBackground = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    selectedBackground.value = file
    maintenanceForm.remove_maintenance_background_image = false
    backgroundPreview.value = file ? URL.createObjectURL(file) : props.maintenance.maintenance_background_image_url
}

const removeBackground = () => {
    selectedBackground.value = null
    backgroundPreview.value = null
    maintenanceForm.remove_maintenance_background_image = true
}
</script>

<template>
    <Head :title="t('Maintenance Mode')" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Maintenance Mode') }}</h1>
                    <span
                        :class="status.is_maintenance
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                            : 'bg-gray-200 text-gray-700 dark:bg-surface-800 dark:text-gray-300'"
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em]"
                    >
                        {{ status.is_maintenance ? t('On') : t('Off') }}
                    </span>
                </div>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ t('Customize the page visitors see while the platform is temporarily unavailable.') }}</p>
            </div>

            <button
                type="button"
                :class="status.is_maintenance
                    ? 'btn-primary-admin'
                    : 'bg-red-600 hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300 dark:bg-red-600 dark:hover:bg-red-500'"
                class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all disabled:opacity-60"
                :disabled="toggleForm.processing"
                @click="confirmOpen = true"
            >
                <svg v-if="toggleForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span v-else>
                    <i :class="status.is_maintenance ? 'ti ti-tools' : 'ti ti-tools-off'" class="mr-1"></i>
                    {{ status.is_maintenance ? t('Go Live') : t('Go Maintenance') }}
                </span>
            </button>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Settings') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Configure the maintenance page content and access controls.') }}</p>
                </div>
                <button
                    type="button"
                    :disabled="maintenanceForm.processing"
                    class="shrink-0 btn-primary-admin inline-flex items-center justify-center gap-2 disabled:opacity-60"
                    @click="saveMaintenance"
                >
                    <svg v-if="maintenanceForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <i v-else class="ti ti-device-floppy text-base"></i>
                    <span>{{ maintenanceForm.processing ? t('Saving...') : t('Save Settings') }}</span>
                </button>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Page title') }}
                        <input v-model="maintenanceForm.maintenance_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="maintenanceForm.errors.maintenance_title" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_title }}</span>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Estimated restoration time') }}
                        <input v-model="maintenanceForm.maintenance_estimated_restoration_time" type="datetime-local" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="maintenanceForm.errors.maintenance_estimated_restoration_time" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_estimated_restoration_time }}</span>
                    </label>
                    <label class="col-span-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Allowed IPs') }}
                        <input v-model="maintenanceForm.maintenance_allowed_ips" type="text" :placeholder="t('Comma-separated IP addresses')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="maintenanceForm.errors.maintenance_allowed_ips" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_allowed_ips }}</span>
                    </label>
                </div>

                <div class="mt-5">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Maintenance message') }}</label>
                    <RichEditor v-model="maintenanceForm.maintenance_message" variant="comment" />
                    <span v-if="maintenanceForm.errors.maintenance_message" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_message }}</span>
                </div>

                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Background image') }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Optional image used behind the standalone maintenance page.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="cursor-pointer rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-300 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200">
                                {{ t('Choose Image') }}
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="selectBackground">
                            </label>
                            <button v-if="backgroundPreview" type="button" class="rounded-xl px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50" @click="removeBackground">{{ t('Remove') }}</button>
                        </div>
                    </div>
                    <img v-if="backgroundPreview" :src="backgroundPreview" :alt="t('Maintenance background preview')" class="mt-4 h-36 w-full rounded-lg object-cover">
                    <span v-if="maintenanceForm.errors.maintenance_background_image" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_background_image }}</span>
                </div>
            </div>
        </section>
    </div>


        <AppModal
            :open="confirmOpen"
            :title="status.is_maintenance ? t('Disable maintenance mode?') : t('Enable maintenance mode?')"
            :subtitle="status.is_maintenance ? t('Visitors will be able to access the platform again.') : t('Visitors will see the maintenance page while the admin panel remains available.')"
            :confirm-text="status.is_maintenance ? t('Go Live') : t('Enter Maintenance')"
            :confirm-loading="toggleForm.processing"
            confirm-variant="admin"
            @close="confirmOpen = false"
            @confirm="toggleMaintenance"
        />
</template>
