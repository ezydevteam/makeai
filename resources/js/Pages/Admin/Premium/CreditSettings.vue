<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

interface BonusTier {
    min_amount: number
    bonus_percent: number
}

const props = defineProps<{
    settings: {
        credit_price_per_unit: number
        credit_topup_minimum: number
        credit_topup_quick_amounts: number[]
        credit_topup_bonus_tiers: BonusTier[]
        credit_topup_enabled: boolean
    }
}>()

const { t } = useTranslate()
const { formatCurrency, currencySymbol } = useNumberFormat()
const form = useForm({
    credit_price_per_unit: props.settings.credit_price_per_unit,
    credit_topup_minimum: props.settings.credit_topup_minimum,
    credit_topup_quick_amounts: [...props.settings.credit_topup_quick_amounts].sort((a, b) => a - b),
    credit_topup_bonus_tiers: props.settings.credit_topup_bonus_tiers
        .map((tier) => ({ ...tier }))
        .sort((a, b) => a.min_amount - b.min_amount),
    credit_topup_enabled: props.settings.credit_topup_enabled,
})

const newQuickAmount = ref('')
const newBonusTier = ref({ min_amount: '', bonus_percent: '' })

const submit = () => {
    form.put(route('admin.credit-settings.update'), {
        preserveScroll: true,
    })
}

const currencyLabel = (amount: number) => formatCurrency(amount)

const addQuickAmount = () => {
    const amount = Number.parseFloat(newQuickAmount.value)

    if (!Number.isFinite(amount) || amount <= 0) {
        return
    }

    if (form.credit_topup_quick_amounts.includes(amount)) {
        newQuickAmount.value = ''
        return
    }

    form.credit_topup_quick_amounts.push(amount)
    form.credit_topup_quick_amounts.sort((a, b) => a - b)
    newQuickAmount.value = ''
}

const removeQuickAmount = (index: number) => {
    form.credit_topup_quick_amounts.splice(index, 1)
}

const addBonusTier = () => {
    const minAmount = Number.parseFloat(newBonusTier.value.min_amount)
    const bonusPercent = Number.parseInt(newBonusTier.value.bonus_percent, 10)

    if (!Number.isFinite(minAmount) || minAmount <= 0 || !Number.isInteger(bonusPercent) || bonusPercent < 1 || bonusPercent > 100) {
        return
    }

    form.credit_topup_bonus_tiers.push({
        min_amount: minAmount,
        bonus_percent: bonusPercent,
    })
    form.credit_topup_bonus_tiers.sort((a, b) => a.min_amount - b.min_amount)
    newBonusTier.value = { min_amount: '', bonus_percent: '' }
}

const removeBonusTier = (index: number) => {
    form.credit_topup_bonus_tiers.splice(index, 1)
}
</script>

<template>
    <Head :title="t('Credit Top-up')" />

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Credit Top-up') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure credit top-up pricing, entry points, and bonus rules.') }}</p>
            </div>

            <button
                type="button"
                class="shrink-0 btn-primary-admin inline-flex items-center gap-2 disabled:opacity-60"
                :disabled="form.processing"
                @click="submit"
            >
                <i class="ti ti-device-floppy text-base"></i>
                {{ form.processing ? t('Saving...') : t('Save Settings') }}
            </button>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="space-y-5">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-5 dark:border-surface-800 dark:bg-surface-950/60">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Top-Up Availability') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control whether users can purchase extra credits from the frontend.') }}</p>
                            </div>
                            <AppSwitch v-model="form.credit_topup_enabled" />
                        </div>
                    </div>

                    <div v-if="form.credit_topup_enabled" class="space-y-5 pt-1">
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Price Per Credit Unit') }}</span>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-400">{{ currencySymbol }}</span>
                                    <input
                                        v-model="form.credit_price_per_unit"
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        max="999.9999"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-8 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    />
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('How much a single credit costs, in your store currency.') }}</p>
                                <p v-if="form.errors.credit_price_per_unit" class="mt-1 text-xs text-danger-600">{{ form.errors.credit_price_per_unit }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Minimum Top-Up Amount') }}</span>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-400">{{ currencySymbol }}</span>
                                    <input
                                        v-model="form.credit_topup_minimum"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max="999999.99"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-8 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    />
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Lowest amount a user can top up in a single purchase.') }}</p>
                                <p v-if="form.errors.credit_topup_minimum" class="mt-1 text-xs text-danger-600">{{ form.errors.credit_topup_minimum }}</p>
                            </label>
                        </div>

                        <div class="rounded-xl border border-gray-100 p-5 dark:border-surface-800">
                            <div class="mb-4">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Quick Select Amounts') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Predefined top-up amounts users can pick without typing a custom value.') }}</p>
                            </div>

                            <div v-if="form.credit_topup_quick_amounts.length > 0" class="mb-4 flex flex-wrap gap-2">
                                <div
                                    v-for="(amount, index) in form.credit_topup_quick_amounts"
                                    :key="`${amount}-${index}`"
                                    class="inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-700 dark:bg-primary-900/20 dark:text-primary-300"
                                >
                                    <span>{{ currencyLabel(amount) }}</span>
                                    <button
                                        type="button"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full text-primary-600 transition hover:bg-primary-100 dark:text-primary-300 dark:hover:bg-primary-900/30"
                                        :aria-label="t('Remove amount')"
                                        @click="removeQuickAmount(index)"
                                    >
                                        <i class="ti ti-x text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <div class="relative flex-1">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-400">{{ currencySymbol }}</span>
                                    <input
                                        v-model="newQuickAmount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        :placeholder="t('Add amount')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-8 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        @keydown.enter.prevent="addQuickAmount"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700"
                                    @click="addQuickAmount"
                                >
                                    <i class="ti ti-plus text-base"></i>
                                    {{ t('Add Amount') }}
                                </button>
                            </div>
                            <p v-if="form.errors.credit_topup_quick_amounts" class="mt-2 text-xs text-danger-600">{{ form.errors.credit_topup_quick_amounts }}</p>
                        </div>

                        <div class="rounded-xl border border-gray-100 p-5 dark:border-surface-800">
                            <div class="mb-4">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Bonus Credit Tiers') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Add optional incentives that grant bonus credits at larger purchase thresholds.') }}</p>
                            </div>

                            <div v-if="form.credit_topup_bonus_tiers.length > 0" class="mb-4 space-y-3">
                                <div
                                    v-for="(tier, index) in form.credit_topup_bonus_tiers"
                                    :key="`${tier.min_amount}-${tier.bonus_percent}-${index}`"
                                    class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800 dark:bg-surface-950/60"
                                >
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ t('Spend :amount or more', { amount: currencyLabel(tier.min_amount) }) }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ t('Reward the purchase with :percent bonus credits.', { percent: `${tier.bonus_percent}%` }) }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 self-start rounded-lg px-2 py-1 text-sm font-medium text-danger-600 transition hover:bg-danger-50 dark:hover:bg-danger-950/20"
                                        @click="removeBonusTier(index)"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                        {{ t('Remove') }}
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-400">{{ currencySymbol }}</span>
                                    <input
                                        v-model="newBonusTier.min_amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        :placeholder="t('Min amount')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-8 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        @keydown.enter.prevent="addBonusTier"
                                    />
                                </div>
                                <div class="relative">
                                    <input
                                        v-model="newBonusTier.bonus_percent"
                                        type="number"
                                        step="1"
                                        min="1"
                                        max="100"
                                        :placeholder="t('Bonus %')"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-4 pr-8 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        @keydown.enter.prevent="addBonusTier"
                                    />
                                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4 text-sm text-gray-400">%</span>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700"
                                    @click="addBonusTier"
                                >
                                    <i class="ti ti-plus text-base"></i>
                                    {{ t('Add Tier') }}
                                </button>
                            </div>
                            <p v-if="form.errors.credit_topup_bonus_tiers" class="mt-2 text-xs text-danger-600">{{ form.errors.credit_topup_bonus_tiers }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </form>
    </div>
</template>
