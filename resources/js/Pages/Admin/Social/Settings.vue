<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SocialFollow from '@/Components/SocialFollow.vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { SocialFollowProfile } from '@/types'

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

const previewProfiles = computed<SocialFollowProfile[]>(() => form.profiles
    .filter((profile) => profile.is_active && profile.profile_url)
    .sort((a, b) => Number(a.sort_order) - Number(b.sort_order))
    .map((profile) => ({
        platform: profile.platform,
        label: profile.label,
        unit: profile.unit,
        url: profile.profile_url || '#',
        count: Number(profile.manual_count ?? profile.count ?? 0),
    })))

const submit = () => {
    form.post(route('admin.social.settings.update'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Social Media Settings')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Social Media Settings') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage social profile links and public follow counters for footer, sidebar, and reusable social blocks.') }}
                </p>
            </div>
            <button
                type="button"
                :disabled="form.processing"
                class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? t('Saving...') : t('Save settings') }}
            </button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default display mode') }}</span>
                            <select v-model="form.settings.social_follow_display_mode" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="icons">{{ t('Icons only') }}</option>
                                <option value="counts">{{ t('Icon and count') }}</option>
                                <option value="cards">{{ t('Full cards') }}</option>
                            </select>
                            <p v-if="form.errors['settings.social_follow_display_mode']" class="mt-1 text-xs text-danger-600">
                                {{ form.errors['settings.social_follow_display_mode'] }}
                            </p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Refresh interval in hours') }}</span>
                            <input v-model="form.settings.social_follow_refresh_hours" type="number" min="1" max="168" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">{{ t('Used by the social:refresh scheduler when API fetching is configured.') }}</p>
                        </label>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Follow counters') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use manual counts for reliable marketplace demos. Enable API fetching only after provider keys are added.') }}</p>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div v-for="(profile, index) in form.profiles" :key="profile.platform" class="grid gap-4 p-5 lg:grid-cols-[180px_minmax(0,1fr)_130px_120px]">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ t(profile.label) }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ t(profile.unit) }}</div>
                                <label class="mt-4 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <input v-model="profile.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                    <span>{{ t('Show publicly') }}</span>
                                </label>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block md:col-span-2">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Profile URL') }}</span>
                                    <input v-model="profile.profile_url" type="url" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('https://example.com/profile')">
                                    <p v-if="form.errors[`profiles.${index}.profile_url`]" class="mt-1 text-xs text-danger-600">
                                        {{ form.errors[`profiles.${index}.profile_url`] }}
                                    </p>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Manual count') }}</span>
                                    <input v-model="profile.manual_count" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Count source') }}</span>
                                    <select v-model="profile.count_source" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                        <option value="manual">{{ t('Manual') }}</option>
                                        <option value="api">{{ t('API') }}</option>
                                    </select>
                                </label>

                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300">
                                    <input v-model="profile.fetch_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                    <span>{{ t('Allow API refresh') }}</span>
                                </label>

                                <template v-if="profile.count_source === 'api'">
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

                                    <label class="block">
                                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Profile, page, or channel ID') }}</span>
                                        <input v-model="profile.external_id" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Optional API identifier')">
                                    </label>
                                </template>

                                <p v-if="profile.last_error" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                                    {{ profile.last_error }}
                                </p>
                            </div>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sort') }}</span>
                                <input v-model="profile.sort_order" type="number" min="0" max="999" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>

                            <div class="flex items-start justify-end">
                                <span :class="profile.is_active ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'" class="rounded-full px-3 py-1 text-xs font-semibold">
                                    {{ profile.is_active ? t('Visible') : t('Hidden') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Live preview') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Preview uses active profiles with profile URLs.') }}</p>
                    <div class="mt-5">
                        <SocialFollow :profiles="previewProfiles" :style="form.settings.social_follow_display_mode" />
                        <p v-if="previewProfiles.length === 0" class="rounded-lg bg-gray-50 px-3 py-4 text-center text-sm text-gray-500 dark:bg-surface-800 dark:text-gray-400">
                            {{ t('No active social profiles yet.') }}
                        </p>
                    </div>
                </section>

                <section class="rounded-xl border border-primary-100 bg-primary-50 p-5 shadow-sm dark:border-primary-900/40 dark:bg-primary-900/10">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Recommendation') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ t('Keep manual counters as the default because many social platforms restrict follower-count APIs. This avoids fake demo data and keeps marketplace installs stable.') }}
                    </p>
                </section>
            </aside>
        </div>
    </div>
</template>
