<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

defineOptions({ layout: UserDashboardLayout })

interface BonusTier {
    min_amount: number
    bonus_percent: number
}

interface Gateway {
    id: number
    slug: string
    name: string
    description: string | null
    is_test_mode: boolean
    processing_fee_type: string | null
    processing_fee_value: string | number | null
}

const props = defineProps<{
    creditTopup: {
        enabled: boolean
        price_per_unit: number
        minimum: number
        quick_amounts: number[]
        bonus_tiers: BonusTier[]
    }
    gateways: Gateway[]
}>()

const { t } = useTranslate()
const { formatCurrency, currencySymbol } = useNumberFormat()
const page = usePage()

const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const currentUser = computed(() => (page.props.auth?.user as any) ?? {})
const currentBalance = computed(() => Number(currentUser.value.credits ?? 0))

const amount = ref<number | ''>('')
const selectedGatewaySlug = ref(props.gateways[0]?.slug ?? '')
const submitting = ref(false)

const selectedGateway = computed(() => props.gateways.find((g) => g.slug === selectedGatewaySlug.value) ?? null)

const numericAmount = computed(() => {
    const val = typeof amount.value === 'string' ? parseFloat(amount.value) : amount.value
    return isNaN(val) ? 0 : val
})

const isValidAmount = computed(() => numericAmount.value >= props.creditTopup.minimum)

const currentBonusTier = computed<BonusTier | null>(() => {
    const tiers = [...props.creditTopup.bonus_tiers].sort((a, b) => b.min_amount - a.min_amount)
    for (const tier of tiers) {
        if (numericAmount.value >= tier.min_amount) {
            return tier
        }
    }
    return null
})

const nextBonusTier = computed<BonusTier | null>(() => {
    const tiers = [...props.creditTopup.bonus_tiers].sort((a, b) => a.min_amount - b.min_amount)
    for (const tier of tiers) {
        if (numericAmount.value < tier.min_amount) {
            return tier
        }
    }
    return null
})

const baseCredits = computed(() => {
    if (numericAmount.value <= 0 || props.creditTopup.price_per_unit <= 0) return 0
    return Math.floor(numericAmount.value / props.creditTopup.price_per_unit)
})

const bonusPercent = computed(() => currentBonusTier.value?.bonus_percent ?? 0)

const bonusCredits = computed(() => Math.floor(baseCredits.value * (bonusPercent.value / 100)))

const totalCredits = computed(() => baseCredits.value + bonusCredits.value)

const processingFee = computed(() => {
    const gw = selectedGateway.value
    if (!gw || !gw.processing_fee_type || !gw.processing_fee_value) return 0

    const feeValue = Number(gw.processing_fee_value)
    if (gw.processing_fee_type === 'percentage') {
        return Math.round(numericAmount.value * (feeValue / 100) * 100) / 100
    }
    return feeValue
})

const totalAmount = computed(() => numericAmount.value + processingFee.value)

const formatCredits = (credits: number): string => {
    return credits.toLocaleString()
}

const selectQuickAmount = (value: number) => {
    amount.value = value
}

// Gateway sessions redirect to an EXTERNAL provider, which an Inertia XHR can't
// follow (CORS). Submit a real form so the browser follows the redirect natively.
const submit = () => {
    if (!isValidAmount.value || !selectedGateway.value) return

    submitting.value = true

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = route('user.dashboard.credit-topup.checkout')

    const fields: Record<string, string> = {
        _token: (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
        amount: String(numericAmount.value),
        gateway: selectedGateway.value.slug,
    }

    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = name
        input.value = value
        form.appendChild(input)
    }

    document.body.appendChild(form)
    form.submit()
}
</script>

<template>
    <Head :title="t('Buy Credits')" />

    <div class="space-y-6">
        <!-- Not available -->
        <div v-if="!isProAvailable" class="rounded-2xl border border-gray-200 bg-white p-10 text-center shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">{{ t('Premium subscriptions are not available on this installation.') }}</p>
        </div>

        <template v-else>
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Buy Credits') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('Current balance:') }}
                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCredits(currentBalance) }} {{ t('credits') }}</span>
            </p>
        </div>

        <!-- Disabled State -->
        <div v-if="!creditTopup.enabled" class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center dark:border-amber-800 dark:bg-amber-900/20">
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ t('Credit top-up is currently disabled. Please contact support.') }}</p>
        </div>

        <template v-else>
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <!-- Left Column: Amount & Gateways -->
                <div class="space-y-6">
                    <!-- Amount Input -->
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Enter amount') }}</h2>

                        <div class="relative mb-4">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-lg font-bold text-gray-400">{{ currencySymbol }}</span>
                            <input
                                v-model="amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-4 pl-10 pr-4 text-2xl font-bold text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :placeholder="t('0.00')"
                            />
                        </div>

                        <!-- Quick Select Buttons -->
                        <div v-if="creditTopup.quick_amounts.length" class="flex flex-wrap gap-2">
                            <button
                                v-for="qa in creditTopup.quick_amounts"
                                :key="qa"
                                type="button"
                                @click="selectQuickAmount(qa)"
                                :class="numericAmount === qa ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'border-gray-200 text-gray-700 hover:border-primary-200 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800'"
                                class="rounded-lg border px-4 py-2 text-sm font-bold transition"
                            >
                                {{ formatCurrency(qa) }}
                            </button>
                        </div>

                        <p v-if="numericAmount > 0 && !isValidAmount" class="mt-2 text-xs font-semibold text-red-600">
                            {{ t('Minimum amount is :amount', { amount: creditTopup.minimum.toFixed(2) }) }}
                        </p>
                    </section>

                    <!-- Credit Calculation -->
                    <section v-if="numericAmount > 0" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('You will receive') }}</h2>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('Rate') }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ currencySymbol }}{{ creditTopup.price_per_unit.toFixed(4) }} = 1 {{ t('credit') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('Base credits') }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCredits(baseCredits) }}</span>
                            </div>
                            <div v-if="bonusCredits > 0" class="flex items-center justify-between text-sm">
                                <span class="text-green-600 dark:text-green-400">{{ t('Bonus') }} (+{{ bonusPercent }}%)</span>
                                <span class="font-semibold text-green-600 dark:text-green-400">+{{ formatCredits(bonusCredits) }}</span>
                            </div>
                            <div class="border-t border-gray-100 pt-3 dark:border-surface-700">
                                <div class="flex items-center justify-between">
                                    <span class="text-base font-bold text-gray-900 dark:text-white">{{ t('Total credits') }}</span>
                                    <span class="text-2xl font-black text-primary-600">{{ formatCredits(totalCredits) }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Bonus Tiers Info -->
                    <section v-if="creditTopup.bonus_tiers.length" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">
                            <span class="mr-2">&#127873;</span>{{ t('Bonus Credits') }}
                        </h2>

                        <div class="space-y-2">
                            <div
                                v-for="tier in [...creditTopup.bonus_tiers].sort((a, b) => a.min_amount - b.min_amount)"
                                :key="tier.min_amount"
                                :class="currentBonusTier?.min_amount === tier.min_amount ? 'border-primary-200 bg-primary-50 dark:border-primary-800 dark:bg-primary-900/20' : 'border-gray-100 bg-gray-50 dark:border-surface-700 dark:bg-surface-800'"
                                class="flex items-center justify-between rounded-lg border p-3 transition"
                            >
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Spend :amount or more', { amount: formatCurrency(tier.min_amount) }) }}
                                </span>
                                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    +{{ tier.bonus_percent }}% {{ t('bonus') }}
                                </span>
                            </div>
                        </div>

                        <!-- Next tier hint -->
                        <div v-if="nextBonusTier && numericAmount > 0" class="mt-3 rounded-lg border border-dashed border-primary-200 bg-primary-50/50 p-3 dark:border-primary-800 dark:bg-primary-900/10">
                            <p class="text-xs font-semibold text-primary-700 dark:text-primary-300">
                                {{ t('Add :more more to unlock :percent% bonus credits!', {
                                    more: formatCurrency(nextBonusTier.min_amount - numericAmount),
                                    percent: String(nextBonusTier.bonus_percent),
                                }) }}
                            </p>
                        </div>
                    </section>

                </div>

                <!-- Right Column: Summary -->
                <aside class="lg:sticky lg:top-6 lg:self-start">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-5 text-lg font-bold text-gray-900 dark:text-white">{{ t('Summary') }}</h2>

                        <div class="space-y-3 text-sm font-medium text-gray-600 dark:text-gray-400">
                            <div class="flex justify-between gap-4">
                                <span>{{ t('Amount') }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(numericAmount) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>{{ t('Base credits') }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatCredits(baseCredits) }}</span>
                            </div>
                            <div v-if="bonusCredits > 0" class="flex justify-between gap-4 text-green-600 dark:text-green-400">
                                <span>{{ t('Bonus') }} (+{{ bonusPercent }}%)</span>
                                <span class="font-bold">+{{ formatCredits(bonusCredits) }}</span>
                            </div>
                            <div v-if="processingFee > 0" class="flex justify-between gap-4">
                                <span>{{ t('Processing fee') }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(processingFee) }}</span>
                            </div>
                        </div>

                        <div class="my-4 border-t border-gray-100 dark:border-surface-700"></div>

                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <span class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Total credits') }}</span>
                            </div>
                            <span class="text-3xl font-black text-primary-600">{{ formatCredits(totalCredits) }}</span>
                        </div>

                        <div v-if="numericAmount > 0" class="mt-1 flex items-end justify-between gap-4">
                            <span class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Total payment') }}</span>
                            <span class="text-lg font-black text-gray-900 dark:text-white">{{ formatCurrency(totalAmount) }}</span>
                        </div>

                        <!--
                            Moved out of the left column to sit with the action it belongs to,
                            as plain radios. Only rendered when there is a choice to make: with
                            one gateway enabled a selector is a control with a single option, so
                            the button names it instead.
                        -->
                        <fieldset v-if="gateways.length > 1" class="mt-5 border-t border-gray-100 pt-5 dark:border-surface-700">
                            <legend class="mb-3 text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Payment method') }}</legend>
                            <div class="space-y-1">
                                <label
                                    v-for="gateway in gateways"
                                    :key="gateway.id"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-gray-50 dark:hover:bg-surface-800"
                                >
                                    <input
                                        v-model="selectedGatewaySlug"
                                        type="radio"
                                        name="topup_gateway"
                                        :value="gateway.slug"
                                        class="h-4 w-4 shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-surface-600 dark:bg-surface-800"
                                    />
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ gateway.name }}</span>
                                    <!-- Only ever shown on a test-mode gateway, where mistaking it for live costs a real order. -->
                                    <span v-if="gateway.is_test_mode" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                        {{ t('Test') }}
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        <div v-else-if="!gateways.length" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                            {{ t('No payment gateway is available. Please contact support.') }}
                        </div>

                        <button
                            type="button"
                            :disabled="!isValidAmount || !selectedGateway || submitting"
                            @click="submit"
                            class="mt-5 w-full rounded-full bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-primary-600/20 transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:!bg-primary-300 disabled:shadow-none dark:disabled:bg-surface-700"
                        >
                            {{ submitting ? t('Processing...') : !isValidAmount ? t('Enter :min or more', { min: formatCurrency(creditTopup.minimum) }) : selectedGateway ? t('Proceed with :gateway', { gateway: selectedGateway.name }) : t('Proceed to Payment') }}
                        </button>

                        <p class="mt-3 text-center text-xs font-medium text-gray-400">
                            {{ t('Credits are added instantly after payment confirmation.') }}
                        </p>
                    </div>
                </aside>
            </div>
        </template>
        </template>
    </div>
</template>
