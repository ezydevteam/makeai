<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
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

interface SocialLoginProvider {
    provider: 'google' | 'github' | 'facebook' | 'reddit' | 'twitter'
    label: string
    enabled: boolean
    client_id: string
    client_id_configured: boolean
    client_id_masked: string
    client_secret: string
    client_secret_configured: boolean
    client_secret_masked: string
    redirect_url: string
}

const props = defineProps<{
    mode: 'follow' | 'oauth'
    platforms: PlatformOption[]
    profiles: EditableProfile[]
    socialLoginProviders: SocialLoginProvider[]
    settings: {
        social_follow_display_mode: 'icons' | 'counts' | 'cards'
        social_follow_refresh_hours: number
        social_share_blog_style: 'icon' | 'icon-label'
    }
}>()

const { t } = useTranslate()

const isFollowMode = computed(() => props.mode === 'follow')
const isOAuthMode = computed(() => props.mode === 'oauth')

const form = useForm({
    settings: { ...props.settings },
    social_login_providers: props.socialLoginProviders.map((provider) => ({ ...provider, client_secret: '' })),
    profiles: props.profiles.map((profile) => ({ ...profile, api_key: '' })),
})

const pageTitle = computed(() => (isOAuthMode.value ? t('OAuth Settings') : t('Social Media Settings')))
const pageDescription = computed(() => (
    isOAuthMode.value
        ? t('Enable OAuth registration and login providers for user authentication pages.')
        : t('Manage social profile links and public follow counters for footer, sidebar, and reusable social blocks.')
))
const submitLabel = computed(() => {
    if (form.processing) {
        return t('Saving...')
    }

    return isOAuthMode.value ? t('Save settings') : t('Save settings')
})

const oauthDisplayModeOptions = computed(() => [
    { value: 'icon', label: t('Icon only') },
    { value: 'icon-label', label: t('Icon + label') },
])
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
    form.post(
        isOAuthMode.value ? route('admin.oauth.settings.update') : route('admin.social.settings.update'),
        { preserveScroll: true }
    )
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="px-6 py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ pageTitle }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ pageDescription }}
                </p>
            </div>
            <button
                type="button"
                :disabled="form.processing"
                class="rounded-lg btn-primary shadow-sm transition disabled:opacity-60"
                @click="submit"
            >
                {{ submitLabel }}
            </button>
        </div>

        <div class="space-y-6">
            <div class="space-y-6">
                <section v-if="isFollowMode" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
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

                <section v-if="isOAuthMode" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div>
                        <AppSelect v-model="form.settings.social_share_blog_style" :options="oauthDisplayModeOptions" :label="t('Display mode')" />
                            <p class="mt-1 text-xs text-gray-500">{{ t('Controls how social share buttons are displayed on supported frontend pages.') }}</p>
                            <p v-if="form.errors['settings.social_share_blog_style']" class="mt-1 text-xs text-danger-600">
                                {{ form.errors['settings.social_share_blog_style'] }}
                            </p>
                    </div>
                </section>

                <section v-if="isOAuthMode" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="(provider, index) in form.social_login_providers" :key="provider.provider" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800/50">
                            <div class="mb-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ t(provider.label) }}</div>
                                <div class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-surface-700 dark:bg-surface-800">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ t('Enable login') }}</span>
                                    <button type="button" :aria-pressed="provider.enabled" :class="provider.enabled ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200" @click="provider.enabled = !provider.enabled">
                                        <span :class="provider.enabled ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                    </button>
                                </div>
                                <p v-if="form.errors[`social_login_providers.${index}.enabled`]" class="mt-2 text-xs text-danger-600">
                                    {{ form.errors[`social_login_providers.${index}.enabled`] }}
                                </p>
                            </div>

                            <div class="grid gap-4">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Client ID') }}</span>
                                    <input v-model="provider.client_id" type="text" autocomplete="off" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="provider.client_id_configured ? provider.client_id_masked : t('OAuth client ID')">
                                    <p v-if="provider.client_id_configured" class="mt-1 text-xs text-gray-500">
                                        {{ t('A client ID is already saved. Enter a new value only to replace it.') }}
                                    </p>
                                    <p v-if="form.errors[`social_login_providers.${index}.client_id`]" class="mt-1 text-xs text-danger-600">
                                        {{ form.errors[`social_login_providers.${index}.client_id`] }}
                                    </p>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Client secret') }}</span>
                                    <input v-model="provider.client_secret" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="provider.client_secret_configured ? provider.client_secret_masked : t('OAuth client secret')">
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ provider.client_secret_configured ? t('A secret is already saved. Enter a new secret only to replace it.') : t('This secret will be encrypted before storage.') }}
                                    </p>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Redirect URL') }}</span>
                                    <input :value="provider.redirect_url" type="text" readonly class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300">
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="isFollowMode" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Follow counters') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use manual counts for reliable marketplace demos. Enable API fetching only after provider keys are added.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="(profile, index) in form.profiles" :key="profile.platform" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800/50">
                            <div class="mb-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ t(profile.label) }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ t(profile.unit) }}</div>
                                <div class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-surface-700 dark:bg-surface-800">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ t('Show publicly') }}</span>
                                    <button type="button" :aria-pressed="profile.is_active" :class="profile.is_active ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200" @click="profile.is_active = !profile.is_active">
                                        <span :class="profile.is_active ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                    </button>
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
                                        <button type="button" :aria-pressed="profile.fetch_enabled" :class="profile.fetch_enabled ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200" @click="profile.fetch_enabled = !profile.fetch_enabled">
                                            <span :class="profile.fetch_enabled ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                        </button>
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

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sort') }}</span>
                                <input v-model="profile.sort_order" type="number" min="0" max="999" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </div>
</template>
