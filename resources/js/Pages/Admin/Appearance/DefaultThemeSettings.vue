<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { FONT_FAMILY_SELECT_OPTIONS } from '@/config/fontFamilies'

defineOptions({ layout: AdminLayout })

type ThemeConfig = {
    name: string
    slug: string
    version?: string
}

const props = defineProps<{
    theme: ThemeConfig
    settings: Record<string, string>
}>()

const { t } = useTranslate()
const fontSizeOptions = ['12px', '13px', '14px', '15px', '16px', '18px', '20px'].map((size) => ({ value: size, label: size }))
const headingWeightOptions = ['400', '500', '600', '700', '800'].map((weight) => ({ value: weight, label: weight }))
const lineHeightOptions = ['1.25', '1.375', '1.5', '1.625', '1.75', '2'].map((value) => ({ value, label: value }))
const letterSpacingOptions = [
    { value: 'tighter', label: 'Tighter' },
    { value: 'tight', label: 'Tight' },
    { value: 'normal', label: 'Normal' },
    { value: 'wide', label: 'Wide' },
    { value: 'wider', label: 'Wider' },
]
const borderRadiusOptions = ['0px', '8px', '12px', '16px', '20px', '999px'].map((value) => ({ value, label: value }))
const containerWidthOptions = [
    { value: 'full', label: 'Full Width' },
    { value: '1080px', label: '1080px' },
    { value: '1280px', label: '1280px' },
    { value: '1536px', label: '1536px' },
]
const gradientDirectionOptions = [
    { value: 'to bottom', label: 'Top to Bottom' },
    { value: 'to right', label: 'Left to Right' },
    { value: 'to bottom right', label: 'Top Left to Bottom Right' },
    { value: 'to bottom left', label: 'Top Right to Bottom Left' },
]
const bgSizeOptions = [
    { value: 'cover', label: 'Cover' },
    { value: 'contain', label: 'Contain' },
    { value: 'auto', label: 'Auto' },
]
const bgRepeatOptions = [
    { value: 'no-repeat', label: 'No Repeat' },
    { value: 'repeat', label: 'Repeat' },
    { value: 'repeat-x', label: 'Repeat X' },
    { value: 'repeat-y', label: 'Repeat Y' },
]
const colorFields = [
    { key: 'primary_color', label: 'Primary' },
    { key: 'secondary_color', label: 'Secondary' },
    { key: 'accent_color', label: 'Accent' },
    { key: 'bg_color', label: 'Page BG' },
    { key: 'surface_color', label: 'Surface' },
    { key: 'text_primary_color', label: 'Text Primary' },
    { key: 'text_secondary_color', label: 'Text Muted' },
    { key: 'link_color', label: 'Link' },
    { key: 'button_color', label: 'Button' },
    { key: 'button_hover_color', label: 'Button Hover' },
    { key: 'header_background', label: 'Header BG' },
    { key: 'footer_background', label: 'Footer BG' },
] as const
const get = (key: string, fallback: string) => props.settings[key] || fallback

const form = useForm({
    settings: {
        primary_color: get('primary_color', '#10b981'),
        secondary_color: get('secondary_color', '#3b82f6'),
        accent_color: get('accent_color', '#8b5cf6'),
        bg_color: get('bg_color', '#f0fdf8'),
        surface_color: get('surface_color', '#ffffff'),
        text_primary_color: get('text_primary_color', '#111827'),
        text_secondary_color: get('text_secondary_color', '#6b7280'),
        link_color: get('link_color', '#3b82f6'),
        button_color: get('button_color', '#10b981'),
        button_hover_color: get('button_hover_color', '#059669'),
        header_background: get('header_background', '#ffffff'),
        footer_background: get('footer_background', '#ecfdf5'),
        font_body: get('font_body', 'Inter'),
        heading_font: get('heading_font', 'Plus Jakarta Sans'),
        base_font_size: get('base_font_size', '15px'),
        heading_weight: get('heading_weight', '700'),
        line_height: get('line_height', '1.5'),
        letter_spacing: get('letter_spacing', 'normal'),
        border_radius: get('border_radius', '12px'),
        container_width: get('container_width', '1280px'),
        bg_gradient: get('bg_gradient', ''),
        bg_gradient_direction: get('bg_gradient_direction', 'to bottom'),
        bg_image: get('bg_image', ''),
        bg_size: get('bg_size', 'cover'),
        bg_repeat: get('bg_repeat', 'no-repeat'),
        bg_attachment: get('bg_attachment', 'scroll'),
        bg_position: get('bg_position', 'center'),
    },
})

const s = form.settings
const submit = () => form.post(route('admin.themes.settings.save', { slug: props.theme.slug }))

const previewStyle = computed(() => ({
    '--page-bg': s.bg_gradient
        ? `linear-gradient(${s.bg_gradient_direction}, ${s.bg_color}, ${s.bg_gradient})`
        : s.bg_color,
    '--surface': s.surface_color,
    '--primary': s.primary_color,
    '--secondary': s.secondary_color,
    '--accent': s.accent_color,
    '--text': s.text_primary_color,
    '--muted': s.text_secondary_color,
    '--link': s.link_color,
    '--button': s.button_color,
    '--button-hover': s.button_hover_color,
    '--header': s.header_background,
    '--footer': s.footer_background,
    '--radius': s.border_radius,
    '--body-font': s.font_body,
    '--heading-font': s.heading_font,
    '--heading-weight': s.heading_weight,
    '--font-size': s.base_font_size,
    '--line-height': s.line_height,
    '--letter-spacing': s.letter_spacing,
}))
</script>

<template>
    <Head :title="t('Theme Settings')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Theme Settings') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">{{ props.theme.name }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage the public-facing default theme colors, typography, layout scale, and background treatment without duplicate controls.') }}</p>
            </div>
            <div class="flex items-center gap-3 self-start">
                <Link
                    :href="route('admin.themes')"
                    class="inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-900"
                >
                    <i class="ti ti-arrow-left mr-1"></i>
                    {{ t('Back') }}
                </Link>
                <button type="button" :disabled="form.processing" class="rounded-lg btn-primary disabled:opacity-60" @click="submit">
                    {{ form.processing ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>
        </div>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 dark:border-emerald-900/30 dark:bg-emerald-900/10">
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">{{ t('Core Colors') }}</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div v-for="field in colorFields" :key="field.key" class="block">
                                    <AppColorPicker v-model="s[field.key]" :label="t(field.label)" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-5 dark:border-sky-900/30 dark:bg-sky-900/10">
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">{{ t('Typography') }}</h2>
                            <div class="mt-4 grid gap-4">
                                <AppSelect v-model="s.font_body" :label="t('Body font')" :options="FONT_FAMILY_SELECT_OPTIONS" live-search />
                                <AppSelect v-model="s.heading_font" :label="t('Heading font')" :options="FONT_FAMILY_SELECT_OPTIONS" live-search />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppSelect v-model="s.base_font_size" :label="t('Base size')" :options="fontSizeOptions" />
                                    <AppSelect v-model="s.heading_weight" :label="t('Heading weight')" :options="headingWeightOptions" />
                                    <AppSelect v-model="s.line_height" :label="t('Line height')" :options="lineHeightOptions" />
                                    <AppSelect v-model="s.letter_spacing" :label="t('Letter spacing')" :options="letterSpacingOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="space-y-6">
                        <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-5 dark:border-amber-900/30 dark:bg-amber-900/10">
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">{{ t('Layout & Background') }}</h2>
                            <div class="mt-4 grid gap-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppSelect v-model="s.border_radius" :label="t('Border radius')" :options="borderRadiusOptions" />
                                    <AppSelect v-model="s.container_width" :label="t('Container width')" :options="containerWidthOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppColorPicker v-model="s.bg_gradient" :label="t('Gradient color')" />
                                    <AppSelect v-model="s.bg_gradient_direction" :label="t('Gradient direction')" :options="gradientDirectionOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background image URL') }}</span>
                                        <input v-model="s.bg_image" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('https://...')" />
                                    </label>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <AppSelect v-model="s.bg_size" :label="t('Size')" :options="bgSizeOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                        <AppSelect v-model="s.bg_repeat" :label="t('Repeat')" :options="bgRepeatOptions.map((option) => ({ value: option.value, label: t(option.label) }))" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-violet-100 bg-violet-50/70 p-5 dark:border-violet-900/30 dark:bg-violet-900/10">
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">{{ t('Theme Summary') }}</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Body Font') }}</div>
                                    <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ s.font_body }}</div>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Container Width') }}</div>
                                    <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ s.container_width }}</div>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Primary') }}</div>
                                    <div class="mt-2 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                        <span class="inline-block h-4 w-4 rounded-full border border-black/10" :style="{ backgroundColor: s.primary_color }"></span>
                                        {{ s.primary_color }}
                                    </div>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ t('Background') }}</div>
                                    <div class="mt-2 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                        <span class="inline-block h-4 w-4 rounded-full border border-black/10" :style="{ backgroundColor: s.bg_color }"></span>
                                        {{ s.bg_color }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
