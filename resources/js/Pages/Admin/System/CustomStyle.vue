<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { FONT_FAMILY_SELECT_OPTIONS } from '@/config/fontFamilies'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    admin_settings: Record<string, string>
}>()

const { t } = useTranslate()

const fontSizeOptions = ['12px', '13px', '14px', '15px', '16px', '18px'].map((size) => ({ value: size, label: size }))
const headingWeightOptions = ['400', '500', '600', '700', '800'].map((weight) => ({ value: weight, label: weight }))

const get = (key: string, fallback: string) => props.admin_settings[key] || fallback

const form = useForm({
    scope: 'admin',
    settings: {
        primary_color: get('primary_color', '#10b981'),
        accent_color: get('accent_color', '#3b82f6'),
        bg_color: get('bg_color', '#f0fdf8'),
        text_primary_color: get('text_primary_color', '#111827'),
        sidebar_bg: get('sidebar_bg', '#0f1f17'),
        sidebar_text_color: get('sidebar_text_color', '#d1fae5'),
        navbar_bg: get('navbar_bg', '#ffffff'),
        navbar_text_color: get('navbar_text_color', '#111827'),
        font_family: get('font_family', 'Inter'),
        base_font_size: get('base_font_size', '14px'),
        heading_weight: get('heading_weight', '600'),
    },
})

const settings = form.settings
const submit = () => form.post(route('admin.appearance.update'))
</script>

<template>
    <Head :title="t('Admin Panel Appearance')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Admin Panel Appearance') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control the admin shell colors, typography, and navigation feel from one place.') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
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
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">{{ t('Brand Colors') }}</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <AppColorPicker v-model="settings.primary_color" :label="t('Primary')" />
                                <AppColorPicker v-model="settings.accent_color" :label="t('Accent')" />
                                <AppColorPicker v-model="settings.bg_color" :label="t('Body BG Color')" />
                                <AppColorPicker v-model="settings.text_primary_color" :label="t('Text Color')" />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/60">
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-700 dark:text-gray-200">{{ t('Sidebar') }}</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <AppColorPicker v-model="settings.sidebar_bg" :label="t('Background')" />
                                <AppColorPicker v-model="settings.sidebar_text_color" :label="t('Text')" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/60">
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-700 dark:text-gray-200">{{ t('Nav Bar') }}</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <AppColorPicker v-model="settings.navbar_bg" :label="t('Background')" />
                                <AppColorPicker v-model="settings.navbar_text_color" :label="t('Text')" />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-5 dark:border-sky-900/30 dark:bg-sky-900/10">
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">{{ t('Typography') }}</h2>
                            <div class="mt-4 grid gap-4">
                                <AppSelect v-model="settings.font_family" :label="t('Font family')" :options="FONT_FAMILY_SELECT_OPTIONS" live-search />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppSelect v-model="settings.base_font_size" :label="t('Base font size')" :options="fontSizeOptions" />
                                    <AppSelect v-model="settings.heading_weight" :label="t('Heading weight')" :options="headingWeightOptions" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
