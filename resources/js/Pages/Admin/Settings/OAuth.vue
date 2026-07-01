<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface SocialLoginProvider {
    provider: 'google' | 'github' | 'facebook' | 'reddit' | 'twitter' | 'linkedin'
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
    socialLoginProviders: SocialLoginProvider[]
    settings: {
        social_share_blog_style: 'icon' | 'icon-label'
    }
}>()

const { t } = useTranslate()

const form = useForm({
    settings: { ...props.settings },
    social_login_providers: props.socialLoginProviders.map((provider) => ({ ...provider, client_secret: '' })),
})

const oauthDisplayModeOptions = computed(() => [
    { value: 'icon', label: t('Icon only') },
    { value: 'icon-label', label: t('Icon + label') },
])

const submitLabel = computed(() => {
    return form.processing ? t('Saving...') : t('Save settings')
})

const submit = () => {
    form.post(route('admin.oauth.settings.update'), {
        preserveScroll: true,
    })
}

const copiedProvider = ref<string | null>(null)

const copyRedirectUrl = (providerName: string, url: string) => {
    navigator.clipboard.writeText(url).then(() => {
        copiedProvider.value = providerName
        setTimeout(() => {
            if (copiedProvider.value === providerName) {
                copiedProvider.value = null
            }
        }, 2000)
    })
}
</script>

<template>
    <Head :title="t('OAuth Settings')" />

    <div class="py-6">
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('OAuth Settings') }}</h1>
                        <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                            {{ t('Authentication') }}
                        </span>
                    </div>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Enable OAuth registration and login providers for user authentication pages.') }}
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-60"
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

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Frontend Display') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Control how social-auth related share buttons appear on supported frontend pages.') }}</p>
                </div>

                <div class="p-6">
                    <div class="max-w-xl">
                        <AppSelect v-model="form.settings.social_share_blog_style" :options="oauthDisplayModeOptions" :label="t('Display mode')" />
                        <p class="mt-1 text-xs text-gray-500">{{ t('Controls how social share buttons are displayed on supported frontend pages.') }}</p>
                        <p v-if="form.errors['settings.social_share_blog_style']" class="mt-1 text-xs text-danger-600">
                            {{ form.errors['settings.social_share_blog_style'] }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Login Providers') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Enable each OAuth provider and manage the client credentials used for registration and sign-in flows.') }}</p>
                </div>

                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <div
                        v-for="(provider, index) in form.social_login_providers"
                        :key="provider.provider"
                        class="rounded-xl border border-gray-200 bg-gray-50/80 shadow-sm dark:border-surface-700 dark:bg-surface-800/50"
                    >
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-700">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(provider.label) }}</div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Configure the OAuth application credentials for this provider.') }}</p>
                                </div>
                                <span
                                    :class="provider.enabled
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                        : 'bg-white text-gray-600 dark:bg-surface-800 dark:text-gray-300'"
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                >
                                    {{ provider.enabled ? t('Enabled') : t('Disabled') }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5 dark:border-surface-700 dark:bg-surface-900">
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ t('Enable login') }}</span>
                                <button
                                    type="button"
                                    :aria-pressed="provider.enabled"
                                    :class="provider.enabled ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                                    @click="provider.enabled = !provider.enabled"
                                >
                                    <span :class="provider.enabled ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                </button>
                            </div>
                            <p v-if="form.errors[`social_login_providers.${index}.enabled`]" class="mt-2 text-xs text-danger-600">
                                {{ form.errors[`social_login_providers.${index}.enabled`] }}
                            </p>
                        </div>

                        <div class="grid gap-4 p-5">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Client ID') }}</span>
                                <input v-model="provider.client_id" type="text" autocomplete="off" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" :placeholder="provider.client_id_configured ? provider.client_id_masked : t('OAuth client ID')">
                                <p v-if="provider.client_id_configured" class="mt-1 text-xs text-gray-500">
                                    {{ t('A client ID is already saved. Enter a new value only to replace it.') }}
                                </p>
                                <p v-if="form.errors[`social_login_providers.${index}.client_id`]" class="mt-1 text-xs text-danger-600">
                                    {{ form.errors[`social_login_providers.${index}.client_id`] }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Client secret') }}</span>
                                <input v-model="provider.client_secret" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" :placeholder="provider.client_secret_configured ? provider.client_secret_masked : t('OAuth client secret')">
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ provider.client_secret_configured ? t('A secret is already saved. Enter a new secret only to replace it.') : t('This secret will be encrypted before storage.') }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Redirect URL') }}</span>
                                <div class="relative flex items-center">
                                    <input
                                        :value="provider.redirect_url"
                                        type="text"
                                        readonly
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-3 pr-10 py-2 text-sm text-gray-500 focus:bg-white focus:text-gray-900 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:focus:bg-surface-900 dark:focus:text-white"
                                        @focus="($event.target as HTMLInputElement).select()"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        @click="copyRedirectUrl(provider.provider, provider.redirect_url)"
                                        :title="t('Copy redirect URL')"
                                    >
                                        <i
                                            :class="copiedProvider === provider.provider ? 'ti ti-check text-green-600 dark:text-green-400' : 'ti ti-copy'"
                                            class="text-base transition-colors duration-200"
                                        />
                                    </button>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
