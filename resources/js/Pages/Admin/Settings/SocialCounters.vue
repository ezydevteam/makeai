<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface PlatformOption {
    platform: string
    label: string
    unit: string
}

interface EditableProfile {
    platform: string
    label: string
    unit: string
    profile_url: string | null
    count: number
    manual_count: number | null
    count_source: 'manual' | 'api'
    fetch_enabled: boolean
    api_key?: string | null
    api_key_configured: boolean
    external_id: string | null
    sort_order: number
    is_active: boolean
    last_fetched_at: string | null
    last_error: string | null
}

const props = defineProps<{
    platforms: PlatformOption[]
    profiles: EditableProfile[]
    settings: {
        social_follow_display_mode: 'icons' | 'counts' | 'cards'
        social_follow_refresh_hours: number
    }
}>()

const { t } = useTranslate()

const form = useForm({
    settings: { ...props.settings },
    profiles: props.profiles.map((profile) => ({ ...profile, api_key: '' })),
})

const pageTitle = computed(() => t('Social Counters'))
const pageDescription = computed(() => t('Manage social profile links and public follow counters for footer, sidebar, and reusable social blocks.'))
const submitLabel = computed(() => {
    return form.processing ? t('Saving...') : t('Save settings')
})

const followDisplayModeOptions = computed(() => [
    { value: 'icons', label: t('Icons only') },
    { value: 'counts', label: t('Icon and count') },
    { value: 'cards', label: t('Full cards') },
])

const countSourceOptions = computed(() => [
    { value: 'manual', label: t('Manual') },
    { value: 'api', label: t('API') },
])

const submit = () => {
    form.post(route('admin.social-counters.settings.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ pageTitle }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ pageDescription }}
                </p>
            </div>
            <button
                type="button"
                :disabled="form.processing"
                class="shrink-0 btn-primary-admin inline-flex items-center justify-center gap-2 disabled:opacity-60"
                @click="submit"
            >
                <svg
                    v-if="form.processing"
                    class="h-4 w-4 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <i v-else class="ti ti-device-floppy text-base"></i>
                <span>{{ submitLabel }}</span>
            </button>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <AppSelect v-model="form.settings.social_follow_display_mode" :options="followDisplayModeOptions" :label="t('Default display mode')" />
                    <p v-if="form.errors['settings.social_follow_display_mode']" class="mt-1 text-xs text-danger-600">
                        {{ form.errors['settings.social_follow_display_mode'] }}
                    </p>
                </div>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Refresh interval in hours') }}</span>
                    <input v-model="form.settings.social_follow_refresh_hours" type="number" min="1" max="168" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1 text-xs text-gray-500">{{ t('Used by the social:refresh scheduler when API fetching is configured.') }}</p>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Follow counters') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use manual counts for reliable marketplace demos. Enable API fetching only after provider keys are added.') }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="(profile, index) in form.profiles" :key="profile.platform" class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800/50">
                    <div class="mb-4">
                        <div class="font-semibold text-gray-900 dark:text-white">{{ t(profile.label) }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ t(profile.unit) }}</div>
                        <div class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-surface-700 dark:bg-surface-800">
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ t('Show publicly') }}</span>
                            <AppSwitch v-model="profile.is_active" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Profile URL') }}</span>
                            <input v-model="profile.profile_url" type="url" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('https://example.com/profile')">
                            <p v-if="form.errors[`profiles.${index}.profile_url`]" class="mt-1 text-xs text-danger-600">
                                {{ form.errors[`profiles.${index}.profile_url`] }}
                            </p>
                        </label>

                        <div>
                            <AppSelect v-model="profile.count_source" :options="countSourceOptions" :label="t('Count source')" />
                        </div>

                        <label v-if="profile.count_source === 'manual'" class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Manual count') }}</span>
                            <input v-model="profile.manual_count" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>

                        <label v-else class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Profile, page, or channel ID') }}</span>
                            <input v-model="profile.external_id" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Optional API identifier')">
                            <p v-if="form.errors[`profiles.${index}.external_id`]" class="mt-1 text-xs text-danger-600">
                                {{ form.errors[`profiles.${index}.external_id`] }}
                            </p>
                        </label>

                        <template v-if="profile.count_source === 'api'">
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-surface-700 dark:bg-surface-800">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Allow API refresh') }}</span>
                                <AppSwitch v-model="profile.fetch_enabled" />
                            </div>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('API key or access token') }}</span>
                                <input v-model="profile.api_key" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="profile.api_key_configured ? t('Configured - leave blank to keep') : t('Enter API key')">
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ profile.api_key_configured ? t('A key is already saved. Enter a new key only to replace it.') : t('This secret will be encrypted before storage.') }}
                                </p>
                                <p v-if="form.errors[`profiles.${index}.api_key`]" class="mt-1 text-xs text-danger-600">
                                    {{ form.errors[`profiles.${index}.api_key`] }}
                                </p>
                            </label>
                        </template>

                        <p v-if="profile.last_error" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                            {{ profile.last_error }}
                        </p>
                    </div>

                    <label class="mt-4 block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sort') }}</span>
                        <input v-model="profile.sort_order" type="number" min="0" max="999" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </div>
        </section>
    </div>

</template>
