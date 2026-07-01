<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

interface NotificationGroup {
    key: string
    icon: string
    label: string
    description: string
    in_app: boolean
    email: boolean
}

const props = defineProps<{
    groups: NotificationGroup[]
    preferences: {
        in_app: Record<string, boolean>
        email: Record<string, boolean>
    }
}>()

// Initialize form with current preferences
const form = useForm({
    in_app: { ...props.preferences.in_app },
    email: { ...props.preferences.email },
})

// Watch for changes to update form data
const localGroups = ref<NotificationGroup[]>([...props.groups])

watch(localGroups, (newGroups) => {
    newGroups.forEach((group) => {
        form.in_app[group.key] = group.in_app
        form.email[group.key] = group.email
    })
}, { deep: true })

const savePreferences = () => {
    form.put(route('user.dashboard.notifications.preferences.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Notification Preferences')" />

    <div class="mx-auto max-w-3xl space-y-8 py-8 px-4 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Notification Preferences') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('Control how and when you receive notifications.') }}</p>
        </div>

        <!-- Info Banner -->
        <section class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-blue-800 dark:text-blue-300">
                    {{ t('Security-critical emails (password reset, 2FA codes) are always sent to keep your account safe.') }}
                </p>
            </div>
        </section>

        <!-- Notification Groups -->
        <section class="rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-200 p-6 dark:border-surface-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Notification Groups') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ t('Choose which notifications you want to receive via in-app and email.') }}</p>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-surface-700">
                <div
                    v-for="group in localGroups"
                    :key="group.key"
                    class="p-6"
                >
                    <div class="flex items-start gap-4">
                        <!-- Icon -->
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                            <i :class="group.icon" class="text-xl"></i>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ group.label }}</h3>
                            <p class="mt-0.5 text-sm text-gray-500">{{ group.description }}</p>
                        </div>

                        <!-- Toggles -->
                        <div class="flex items-center gap-6 shrink-0">
                            <!-- In-App Toggle -->
                            <div class="flex flex-col items-center gap-1.5">
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input
                                        v-model="group.in_app"
                                        type="checkbox"
                                        class="peer sr-only"
                                    />
                                    <div class="h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-primary-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2 dark:bg-surface-700 dark:peer-checked:bg-primary-600 dark:peer-focus:ring-offset-surface-900 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:after:border-surface-600 dark:after:bg-surface-400 dark:peer-checked:after:bg-white"></div>
                                </label>
                                <span class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('In-App') }}</span>
                            </div>

                            <!-- Email Toggle -->
                            <div class="flex flex-col items-center gap-1.5">
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input
                                        v-model="group.email"
                                        type="checkbox"
                                        class="peer sr-only"
                                    />
                                    <div class="h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-primary-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2 dark:bg-surface-700 dark:peer-checked:bg-primary-600 dark:peer-focus:ring-offset-surface-900 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:after:border-surface-600 dark:after:bg-surface-400 dark:peer-checked:after:bg-white"></div>
                                </label>
                                <span class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Email') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="border-t border-gray-200 p-6 dark:border-surface-700">
                <button
                    @click="savePreferences"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50 transition-colors"
                >
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{ t('Save Preferences') }}
                </button>
            </div>
        </section>
    </div>
</template>
