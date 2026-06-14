<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import { computed } from 'vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{
    settings: Record<string, any>
    operationsStatus?: Record<string, boolean>
}>()

const form = useForm({ ...props.settings })

function save() {
    form.put(route('addon.ie.admin.settings'))
}

const providerFormKeys: Record<string, string> = {
    inpaint: 'inpaint_provider',
    outpaint: 'outpaint_provider',
    bg_remove: 'bg_remove_provider',
    upscale: 'upscale_provider',
    style_transfer: 'style_provider',
    object_remove: 'object_remove_provider',
}

const providerSections = computed(() => ({
    inpaint: t('Inpainting'),
    outpaint: t('Outpainting'),
    bg_remove: t('Background Removal'),
    upscale: t('Upscaling'),
    style_transfer: t('Style Transfer'),
    object_remove: t('Object Removal'),
}))

const opsStatus = computed(() => props.operationsStatus || {} as Record<string, boolean>)

const providerOptions: Record<string, { value: string; label: string }[]> = {
    inpaint: [
        { value: 'stability', label: 'Stability AI' },
        { value: 'replicate', label: 'Replicate (SD)' },
    ],
    outpaint: [
        { value: 'stability', label: 'Stability AI' },
        { value: 'replicate', label: 'Replicate' },
    ],
    bg_remove: [
        { value: 'remove_bg', label: 'Remove.bg' },
        { value: 'clipdrop', label: 'Clipdrop' },
    ],
    upscale: [
        { value: 'replicate', label: 'Replicate (Real-ESRGAN)' },
    ],
    style_transfer: [
        { value: 'stability', label: 'Stability AI' },
        { value: 'replicate', label: 'Replicate' },
    ],
    object_remove: [
        { value: 'stability', label: 'Stability AI' },
        { value: 'clipdrop', label: 'Clipdrop' },
    ],
}
</script>

<template>
    <Head :title="t('Image Editor Settings')" />

    <div class="p-6 max-w-2xl space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('Image Editor Settings') }}</h1>
            <button @click="save" :disabled="form.processing" class="btn btn-sm btn-primary">
                {{ t('Save') }}
            </button>
        </div>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('General') }}</h3>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.enabled" class="toggle" />
                <span class="text-sm">{{ t('Enable Image Editor') }}</span>
            </label>
        </section>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('Provider Configuration') }}</h3>

            <div class="space-y-4">
                <div v-for="(label, slug) in providerSections" :key="slug" class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">{{ label }}</span>
                        <span v-if="opsStatus[slug]" class="badge badge-green">✓ {{ t('Configured') }}</span>
                        <span v-else class="badge badge-amber">⚠ {{ t('Not configured') }}</span>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">{{ t('Provider') }}</label>
                        <AppSelect
                            v-model="form[providerFormKeys[slug]]"
                            :options="providerOptions[slug] || []"
                        />
                    </div>
                </div>
            </div>

            <details class="mt-2">
                <summary class="text-xs text-gray-500 cursor-pointer">{{ t('API Keys') }}</summary>
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <div>
                        <label class="text-xs text-gray-500">{{ t('Stability AI') }}</label>
                        <input v-model="form.stability_api_key" class="input text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">{{ t('Replicate') }}</label>
                        <input v-model="form.replicate_api_key" class="input text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">{{ t('Remove.bg') }}</label>
                        <input v-model="form.remove_bg_api_key" class="input text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">{{ t('Clipdrop') }}</label>
                        <input v-model="form.clipdrop_api_key" class="input text-sm" />
                    </div>
                </div>
            </details>
        </section>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('Credits Per Operation') }}</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs">{{ t('Inpainting') }}</label><input type="number" v-model.number="form.credits_inpaint" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Outpainting') }}</label><input type="number" v-model.number="form.credits_outpaint" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Background Removal') }}</label><input type="number" v-model.number="form.credits_bg_remove" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Upscaling') }}</label><input type="number" v-model.number="form.credits_upscale" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Style Transfer') }}</label><input type="number" v-model.number="form.credits_style_transfer" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Object Removal') }}</label><input type="number" v-model.number="form.credits_object_remove" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Color Correction') }}</label><input type="number" v-model.number="form.credits_color_correction" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Text Overlay') }}</label><input type="number" v-model.number="form.credits_text_overlay" class="input w-full" /></div>
            </div>
        </section>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('Limits') }}</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs">{{ t('Max Input Size (MB)') }}</label><input type="number" v-model.number="form.max_input_size_mb" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Max Output Dimension (px)') }}</label><input type="number" v-model.number="form.max_output_dimension" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('History Limit Per Image') }}</label><input type="number" v-model.number="form.history_limit_per_image" class="input w-full" /></div>
            </div>
        </section>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('Advanced') }}</h3>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.auto_save_to_library" class="toggle" />
                <span class="text-sm">{{ t('Auto-save edited images to library') }}</span>
            </label>
        </section>
    </div>
</template>
