<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface Setting { key: string; label: string; type: string; default: unknown; value: unknown; options?: string[]; description?: string }
interface AddonConfig { name: string; slug: string; version: string }
type FormValue = string | number | boolean | null

const props = defineProps<{ addon: AddonConfig; settings: Setting[] }>()
const { t } = useTranslate()

const form = useForm<Record<string, FormValue>>(
    Object.fromEntries(props.settings.map((s) => [s.key, (s.value ?? s.default) as FormValue])),
)

const save = () => form.post(route('admin.addons.settings.save', { slug: props.addon.slug }), {
    preserveScroll: true,
})

const resolveBoolean = (value: unknown): boolean => {
    return value === true || value === 1 || value === '1' || value === 'true'
}

const selectOptions = (setting: Setting): Array<{ value: string; label: string }> => {
    return (setting.options ?? []).map((opt) => ({ value: opt, label: opt }))
}

const getFormValue = (key: string): FormValue => form[key]

const setFormValue = (key: string, value: FormValue): void => {
    form[key] = value
}

const setBooleanValue = (key: string): void => {
    form[key] = !resolveBoolean(form[key])
}

const setNumberValue = (key: string, value: string): void => {
    form[key] = value === '' ? null : Number(value)
}
</script>

<template>
    <Head :title="`${addon.name} ${t('Settings')} — Admin`" />
    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="space-y-6 py-6">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <Link :href="route('admin.addons')" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:border-primary-200 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300">
                        <i class="ti ti-arrow-left text-lg"></i>
                    </Link>
                    <div>
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                            <i class="ti ti-puzzle text-sm"></i>
                            {{ t('Addon Settings') }}
                        </div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ addon.name }} {{ t('Settings') }}</h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Version') }} {{ addon.version }}</p>
                    </div>
                </div>
                <div class="hidden md:flex items-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <i class="ti ti-settings text-primary-500"></i>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Unified admin layout') }}</span>
                </div>
            </div>

            <form @submit.prevent="save" class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Addon Configuration') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Adjust the addon settings below. Each field uses a simple buyer-friendly control.') }}</p>
                        </div>
                        <Tooltip :content="t('Save settings')" placement="left">
                            <button type="submit" :disabled="form.processing" class="btn-primary inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="ti ti-device-floppy text-base"></i>
                                {{ form.processing ? t('Saving...') : t('Save Settings') }}
                            </button>
                        </Tooltip>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div v-for="setting in settings" :key="setting.key" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:border-primary-200 hover:shadow-md dark:border-surface-700 dark:bg-surface-900">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-900 dark:text-white">{{ setting.label }}</label>
                            <p v-if="setting.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ setting.description }}</p>
                        </div>

                        <div v-if="setting.type === 'boolean'" class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ resolveBoolean(getFormValue(setting.key)) ? t('Enabled') : t('Disabled') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Toggle this option on or off.') }}</p>
                            </div>
                            <button type="button" @click="setBooleanValue(setting.key)" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors" :class="resolveBoolean(getFormValue(setting.key)) ? 'bg-primary-500' : 'bg-gray-300 dark:bg-surface-700'">
                                <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="resolveBoolean(getFormValue(setting.key)) ? 'translate-x-5' : 'translate-x-0.5'"></span>
                            </button>
                        </div>

                        <AppSelect
                            v-else-if="setting.type === 'select'"
                            :model-value="String(getFormValue(setting.key) ?? '')"
                            :options="selectOptions(setting)"
                            :placeholder="t('Select an option...')"
                            @update:model-value="setFormValue(setting.key, $event)"
                        />

                        <input
                            v-else-if="setting.type === 'integer'"
                            :value="getFormValue(setting.key) ?? ''"
                            type="number"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            @input="setNumberValue(setting.key, ($event.target as HTMLInputElement).value)"
                        >

                        <textarea
                            v-else-if="setting.type === 'textarea'"
                            :value="String(getFormValue(setting.key) ?? '')"
                            rows="4"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            @input="setFormValue(setting.key, ($event.target as HTMLTextAreaElement).value)"
                        ></textarea>

                        <input
                            v-else
                            :value="String(getFormValue(setting.key) ?? '')"
                            type="text"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            @input="setFormValue(setting.key, ($event.target as HTMLInputElement).value)"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <Link :href="route('admin.addons')" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
                        {{ t('Cancel') }}
                    </Link>
                    <button type="submit" :disabled="form.processing" class="btn-primary inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60">
                        <i class="ti ti-device-floppy text-base"></i>
                        {{ form.processing ? t('Saving...') : t('Save Settings') }}
                    </button>
                </div>
            </form>
            </div>
        </div>
</template>
