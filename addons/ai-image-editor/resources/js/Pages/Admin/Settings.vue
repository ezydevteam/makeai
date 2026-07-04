<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

type ProviderKey = 'inpaint' | 'outpaint' | 'bg_remove' | 'upscale' | 'style_transfer' | 'object_remove'

interface ImageEditorSettings {
    enabled: boolean
    inpaint_provider: string
    outpaint_provider: string
    bg_remove_provider: string
    upscale_provider: string
    style_provider: string
    object_remove_provider: string
    stability_api_key: string
    replicate_api_key: string
    remove_bg_api_key: string
    clipdrop_api_key: string
    credits_inpaint: number
    credits_outpaint: number
    credits_bg_remove: number
    credits_upscale: number
    credits_style_transfer: number
    credits_object_remove: number
    credits_color_correction: number
    credits_text_overlay: number
    max_input_size_mb: number
    max_output_dimension: number
    history_limit_per_image: number
    auto_save_to_library: boolean
}

interface ApiKeyStatus {
    stability_api_key: string
    replicate_api_key: string
    remove_bg_api_key: string
    clipdrop_api_key: string
}

const props = defineProps<{
    settings: ImageEditorSettings
    operationsStatus?: Partial<Record<ProviderKey, boolean>>
    apiKeyStatus?: Partial<ApiKeyStatus>
}>()

const form = useForm<ImageEditorSettings>({
    ...props.settings,
    stability_api_key: '',
    replicate_api_key: '',
    remove_bg_api_key: '',
    clipdrop_api_key: '',
})

function save() {
    form.put(route('addon.ie.admin.settings'), { preserveScroll: true })
}

const providerFormKeys: Record<ProviderKey, keyof ImageEditorSettings> = {
    inpaint: 'inpaint_provider',
    outpaint: 'outpaint_provider',
    bg_remove: 'bg_remove_provider',
    upscale: 'upscale_provider',
    style_transfer: 'style_provider',
    object_remove: 'object_remove_provider',
}

const providerSections = [
    {
        key: 'inpaint',
        label: t('Inpainting'),
        description: t('Remove or replace objects inside a masked region.'),
    },
    {
        key: 'outpaint',
        label: t('Outpainting'),
        description: t('Extend images beyond the original frame.'),
    },
    {
        key: 'bg_remove',
        label: t('Background Removal'),
        description: t('Strip the background for product and portrait workflows.'),
    },
    {
        key: 'upscale',
        label: t('Upscaling'),
        description: t('Increase image resolution for higher-quality exports.'),
    },
    {
        key: 'style_transfer',
        label: t('Style Transfer'),
        description: t('Apply a reference style to the uploaded image.'),
    },
    {
        key: 'object_remove',
        label: t('Object Removal'),
        description: t('Remove unwanted objects with minimal visible trace.'),
    },
] as const

const providerOptions: Record<ProviderKey, { value: string; label: string }[]> = {
    inpaint: [
        { value: 'stability', label: t('Stability AI') },
        { value: 'replicate', label: t('Replicate (SD)') },
    ],
    outpaint: [
        { value: 'stability', label: t('Stability AI') },
        { value: 'replicate', label: t('Replicate') },
    ],
    bg_remove: [
        { value: 'remove_bg', label: t('Remove.bg') },
        { value: 'clipdrop', label: t('Clipdrop') },
    ],
    upscale: [
        { value: 'replicate', label: t('Replicate (Real-ESRGAN)') },
    ],
    style_transfer: [
        { value: 'stability', label: t('Stability AI') },
        { value: 'replicate', label: t('Replicate') },
    ],
    object_remove: [
        { value: 'stability', label: t('Stability AI') },
        { value: 'clipdrop', label: t('Clipdrop') },
    ],
}

const opsStatus = computed<Partial<Record<ProviderKey, boolean>>>(() => props.operationsStatus ?? {})
const apiKeyStatus = computed<Partial<ApiKeyStatus>>(() => props.apiKeyStatus ?? {})
</script>

<template>
    <Head :title="t('Image Editor Settings')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Image Editor Settings') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage providers, API keys, credit costs, and safety limits from one unified settings page.') }}
                </p>
            </div>
            <button type="button" :disabled="form.processing" class="rounded-lg btn-primary disabled:opacity-60" @click="save">
                {{ form.processing ? t('Saving...') : t('Save Changes') }}
            </button>
        </div>

        <form class="space-y-5" @submit.prevent="save">
            <div class="space-y-5">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <div class="mb-4">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('General') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Enable or disable the addon without changing its saved provider configuration.') }}
                            </p>
                        </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Image Editor') }}</span>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('When disabled, the public editor stays hidden while settings remain saved.') }}</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.enabled"
                                    class="relative inline-flex h-6 w-11 rounded-full transition"
                                    :class="form.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                    @click="form.enabled = !form.enabled"
                                >
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                </button>
                        </label>

                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-save edited images to library') }}</span>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Save completed edits automatically after each successful operation.') }}</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.auto_save_to_library"
                                    class="relative inline-flex h-6 w-11 rounded-full transition"
                                    :class="form.auto_save_to_library ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                    @click="form.auto_save_to_library = !form.auto_save_to_library"
                                >
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.auto_save_to_library ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                </button>
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <div class="mb-4">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Provider Configuration') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Choose the engine for each editing mode and keep API credentials in one place.') }}
                            </p>
                        </div>

                    <div class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="section in providerSections"
                                :key="section.key"
                                class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ section.label }}</h3>
                                            <span
                                                v-if="opsStatus[section.key]"
                                                class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                            >
                                                {{ t('Configured') }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                            >
                                                {{ t('Needs API key') }}
                                            </span>
                                        </div>
                                        <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">{{ section.description }}</p>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                        {{ t('Provider') }}
                                    </label>
                                    <AppSelect
                                        v-model="form[providerFormKeys[section.key]]"
                                        :options="providerOptions[section.key]"
                                    />
                                </div>
                            </div>
                    </div>

                    <div class="mt-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('API Keys') }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ t('Store provider credentials securely. Leave a field blank to keep the current encrypted key.') }}
                        </p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Stability AI') }}</span>
                                <input
                                    v-model="form.stability_api_key"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="apiKeyStatus.stability_api_key || t('Enter new key to replace the saved value')"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Replicate') }}</span>
                                <input
                                    v-model="form.replicate_api_key"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="apiKeyStatus.replicate_api_key || t('Enter new key to replace the saved value')"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Remove.bg') }}</span>
                                <input
                                    v-model="form.remove_bg_api_key"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="apiKeyStatus.remove_bg_api_key || t('Enter new key to replace the saved value')"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Clipdrop') }}</span>
                                <input
                                    v-model="form.clipdrop_api_key"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="apiKeyStatus.clipdrop_api_key || t('Enter new key to replace the saved value')"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </label>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <div class="mb-4">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Credits & Limits') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Tune credit usage and safety caps for each edit workflow.') }}
                            </p>
                        </div>

                    <div class="space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Credits Per Operation') }}</h3>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Inpainting') }}</span>
                                        <input v-model.number="form.credits_inpaint" type="number" min="0" :placeholder="t('15')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Outpainting') }}</span>
                                        <input v-model.number="form.credits_outpaint" type="number" min="0" :placeholder="t('15')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background Removal') }}</span>
                                        <input v-model.number="form.credits_bg_remove" type="number" min="0" :placeholder="t('5')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Upscaling') }}</span>
                                        <input v-model.number="form.credits_upscale" type="number" min="0" :placeholder="t('20')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Style Transfer') }}</span>
                                        <input v-model.number="form.credits_style_transfer" type="number" min="0" :placeholder="t('20')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Object Removal') }}</span>
                                        <input v-model.number="form.credits_object_remove" type="number" min="0" :placeholder="t('15')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Color Correction') }}</span>
                                        <input v-model.number="form.credits_color_correction" type="number" min="0" :placeholder="t('0')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Text Overlay') }}</span>
                                        <input v-model.number="form.credits_text_overlay" type="number" min="0" :placeholder="t('0')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Limits') }}</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Input Size (MB)') }}</span>
                                        <input v-model.number="form.max_input_size_mb" type="number" min="1" :placeholder="t('10')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Output Dimension (px)') }}</span>
                                        <input v-model.number="form.max_output_dimension" type="number" min="1" :placeholder="t('4096')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('History Limit Per Image') }}</span>
                                        <input v-model.number="form.history_limit_per_image" type="number" min="1" :placeholder="t('20')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </form>
    </div>
</template>
