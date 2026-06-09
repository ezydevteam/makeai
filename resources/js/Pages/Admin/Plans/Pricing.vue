<template>
    <Head :title="t('Pricing Settings')" />

    <AdminLayout>
        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ t('Pricing Settings') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Control global pricing display, billing cycles, and frontend pricing copy.') }}
                        </p>
                    </div>

                    <Link
                        :href="route('admin.plans.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back to Plans') }}
                    </Link>
                </div>

                <form @submit.prevent="submit">
                    <div class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Display Settings') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose which billing cycles are visible and edit the text shown in pricing components.') }}</p>
                        </div>

                        <div class="space-y-6 p-6">
                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    {{ t('Show Monthly') }}
                                    <input v-model="form.pricing_show_monthly" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    {{ t('Show Yearly') }}
                                    <input v-model="form.pricing_show_yearly" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    {{ t('Show Lifetime') }}
                                    <input v-model="form.pricing_show_lifetime" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Default Currency') }}</span>
                                    <AppSelect
                                        v-model="form.pricing_currency_code"
                                        :options="currencyOptions"
                                        :placeholder="t('Select currency')"
                                        live-search
                                    />
                                    <p v-if="form.errors.pricing_currency_code" class="mt-1 text-xs text-danger-600">{{ form.errors.pricing_currency_code }}</p>
                                </label>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Trial Button Text') }}</span>
                                    <input v-model="form.pricing_trial_button_text" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    <p v-if="form.errors.pricing_trial_button_text" class="mt-1 text-xs text-danger-600">{{ form.errors.pricing_trial_button_text }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Featured Label Text') }}</span>
                                    <input v-model="form.pricing_featured_label_text" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    <p v-if="form.errors.pricing_featured_label_text" class="mt-1 text-xs text-danger-600">{{ form.errors.pricing_featured_label_text }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Checkout Button Text') }}</span>
                                    <input v-model="form.pricing_checkout_button_text" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                                    <p v-if="form.errors.pricing_checkout_button_text" class="mt-1 text-xs text-danger-600">{{ form.errors.pricing_checkout_button_text }}</p>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-900/40">
                            <Link
                                :href="route('admin.plans.index')"
                                class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            >
                                {{ t('Cancel') }}
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <i class="ti ti-device-floppy text-base"></i>
                                {{ form.processing ? t('Saving...') : t('Save Settings') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppSelect from '@/Components/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface PricingSettings {
    pricing_show_monthly: boolean
    pricing_show_yearly: boolean
    pricing_show_lifetime: boolean
    pricing_currency_code: string
    pricing_trial_button_text: string
    pricing_featured_label_text: string
    pricing_checkout_button_text: string
}

declare const route: (name: string, params?: unknown) => string

const props = defineProps<{
    currencies: string[]
    settings: PricingSettings
}>()

const { t } = useTranslate()
const currencyOptions = computed(() => props.currencies.map((currency) => ({
    value: currency,
    label: currency,
})))

const form = useForm({
    pricing_show_monthly: props.settings.pricing_show_monthly,
    pricing_show_yearly: props.settings.pricing_show_yearly,
    pricing_show_lifetime: props.settings.pricing_show_lifetime,
    pricing_currency_code: props.settings.pricing_currency_code,
    pricing_trial_button_text: props.settings.pricing_trial_button_text,
    pricing_featured_label_text: props.settings.pricing_featured_label_text,
    pricing_checkout_button_text: props.settings.pricing_checkout_button_text,
})

const submit = () => {
    form.post(route('admin.plans.settings'), {
        preserveScroll: true,
    })
}
</script>
