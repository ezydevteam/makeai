<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import { computed, ref } from 'vue'
import { applyPurchaseCodeMask } from '@/lib/purchaseCode'

defineOptions({ layout: AdminLayout })

type LicenseStatus = {
    verified: boolean
    type: number
    type_label: string
    buyer: string
    purchase_date: string
    last_reverify: string
    domain_ok: boolean
    in_grace_period: boolean
    grace_expired: boolean
    grace_hours_remaining: number
    grace_started_at: string | null
}

const props = defineProps<{
    status: LicenseStatus
}>()

const page = usePage()
const { t } = useTranslate()

const activateForm = useForm({
    purchase_code: '',
})

const reverifyForm = useForm({})
const deactivateForm = useForm({})

const typeBadgeClass = computed(() =>
    props.status.type === 2
        ? 'bg-purple-100 text-purple-700 border border-purple-200'
        : 'bg-blue-100 text-blue-700 border border-blue-200',
)

const isVerifying = computed(() => activateForm.processing || reverifyForm.processing || deactivateForm.processing)

const showDeactivateConfirm = ref(false)

function formatDate(dateStr: string): string {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function formatGraceTime(hours: number): string {
    if (hours <= 0) return t('Expiring soon')
    const days = Math.floor(hours / 24)
    const remainingHours = hours % 24
    if (days > 0) {
        return `${days}d ${remainingHours}h ${t('remaining')}`
    }
    return `${remainingHours}h ${t('remaining')}`
}

function onPurchaseCodeInput(e: Event) {
    activateForm.purchase_code = applyPurchaseCodeMask((e.target as HTMLInputElement).value, true)
}
</script>

<template>
    <Head :title="t('License Settings')" />

    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <!-- Developer test mode warning banner -->
        <div
            v-if="page.props.licenseTestMode"
            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300"
        >
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p class="font-semibold">{{ t('Developer license test mode is active') }}</p>
                    <p class="mt-1 text-xs">
                        {{ t('This must NEVER be true in a production environment. Remove the LICENSE_TEST_MODE flag from your .env file immediately if this is a live customer site.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('License Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Activate your Envato purchase code to unlock the application.') }}</p>
            </div>
        </div>

        <!-- Grace Period Warning Banner -->
        <div
            v-if="status.in_grace_period"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900/30 dark:bg-amber-900/20"
        >
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <p class="font-semibold text-amber-800 dark:text-amber-200">{{ t('License re-verification failed') }}</p>
                <p class="mt-1 text-amber-700 dark:text-amber-300">
                    {{ t('Your license could not be re-verified. All frontend features will be blocked when the grace period expires.') }}
                    <strong>{{ formatGraceTime(status.grace_hours_remaining) }}</strong>
                </p>
            </div>
        </div>

        <!-- Grace Expired Banner -->
        <div
            v-if="status.grace_expired"
            class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm dark:border-danger-900/30 dark:bg-danger-900/20"
        >
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-danger-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div>
                    <p class="font-semibold text-danger-700 dark:text-danger-200">{{ t('License Grace Period Expired') }}</p>
                    <p class="mt-1 text-danger-600 dark:text-danger-300">
                        {{ t('The grace period has expired. The entire frontend is now blocked until you re-activate your license.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Verified License Info -->
        <section
            v-if="status.verified"
            class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
        >
            <div class="mb-5">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('License Status') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ t('Your license is active and verified.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <span class="text-xs font-medium uppercase text-gray-500">{{ t('License Type') }}</span>
                    <p class="mt-1">
                        <span :class="['inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-semibold', typeBadgeClass]">
                            {{ status.type === 2 ? '👑 ' : '✅ ' }}
                            {{ status.type_label }}
                        </span>
                    </p>
                </div>

                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <span class="text-xs font-medium uppercase text-gray-500">{{ t('Buyer') }}</span>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ status.buyer || '—' }}</p>
                </div>

                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <span class="text-xs font-medium uppercase text-gray-500">{{ t('Purchase Date') }}</span>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ formatDate(status.purchase_date) }}</p>
                </div>

                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <span class="text-xs font-medium uppercase text-gray-500">{{ t('Last Re-verify') }}</span>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ formatDate(status.last_reverify) }}</p>
                </div>

                <div class="rounded-lg border sm:col-span-2 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800"
                    :class="status.domain_ok ? 'border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/10' : 'border-red-200 bg-red-50 dark:border-red-700 dark:bg-red-900/10'">
                    <span class="text-xs font-medium uppercase text-gray-500">{{ t('Domain Binding') }}</span>
                    <p class="mt-1 text-sm font-semibold" :class="status.domain_ok ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                        {{ status.domain_ok ? t('Domain matches') : t('Domain mismatch — license was activated on a different domain') }}
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex flex-wrap gap-3">
                <button
                    type="button"
                    :disabled="reverifyForm.processing"
                    @click="reverifyForm.post(route('admin.license.reverify'), { preserveScroll: true })"
                    class="inline-flex items-center gap-2 rounded-lg btn-primary-admin shadow-sm transition-colors disabled:opacity-60"
                >
                    <svg v-if="reverifyForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{ reverifyForm.processing ? t('Re-verifying...') : t('Re-verify Now') }}
                </button>

                <button
                    type="button"
                    :disabled="deactivateForm.processing"
                    @click="showDeactivateConfirm = true"
                    class="inline-flex items-center gap-2 rounded-xl border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 shadow-sm transition-colors hover:bg-red-50 disabled:opacity-60 dark:!border-red-900/60 dark:!bg-red-900/40 dark:!text-red-500 dark:hover:!bg-red-900/60"
                >
                    {{ deactivateForm.processing ? t('Deactivating...') : t('Deactivate License') }}
                </button>
            </div>
        </section>

        <!-- Activate License Form (unverified state) -->
        <section
            v-else
            class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
        >
            <div class="mb-5">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Activate License') }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('Enter your Envato purchase code to activate this installation. You can find your purchase code in your') }}
                    <a href="https://codecanyon.net/downloads" target="_blank" rel="noopener" class="text-primary-600 underline hover:text-primary-500">CodeCanyon {{ t('downloads') }}</a>.
                </p>
            </div>

            <form @submit.prevent="activateForm.post(route('admin.license.activate'), { preserveScroll: true })" class="space-y-4">
                <div>
                    <label for="purchase_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Purchase Code') }}
                    </label>
                    <input
                        id="purchase_code"
                        :value="activateForm.purchase_code"
                        @input="onPurchaseCodeInput"
                        type="text"
                        autocomplete="off"
                        :placeholder="'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'"
                        class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        :class="{ 'border-danger-400 dark:border-danger-500': activateForm.errors.purchase_code }"
                    />
                    <span v-if="activateForm.errors.purchase_code" class="mt-1 block text-xs text-danger-600">{{ activateForm.errors.purchase_code }}</span>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="activateForm.processing"
                        class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary-admin shadow-lg transition-colors disabled:opacity-60"
                    >
                        <svg v-if="activateForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        <span>{{ activateForm.processing ? t('Activating...') : t('Activate License') }}</span>
                    </button>
                </div>
            </form>
        </section>

        <!-- Domain Mismatch Warning -->
        <section
            v-if="status.verified && !status.domain_ok"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-700 dark:bg-amber-900/20"
        >
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p class="font-semibold text-amber-800 dark:text-amber-200">{{ t('Domain mismatch detected') }}</p>
                    <p class="mt-1 text-amber-700 dark:text-amber-300">
                        {{ t('This license was activated on a different domain. Re-verification may fail. Deactivate and re-activate on this domain to fix this.') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Info Panel -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="mb-5">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('About License Types') }}</h2>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-900/30 dark:bg-blue-900/20">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-blue-800 dark:text-blue-200">
                        <span class="text-base">✅</span> {{ t('Regular License') }}
                    </h3>
                    <p class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        {{ t('Single end product, end users not charged. Enables all core AI features.') }}
                    </p>
                </div>

                <div class="rounded-lg border border-purple-100 bg-purple-50 p-4 dark:border-purple-900/30 dark:bg-purple-900/20">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-purple-800 dark:text-purple-200">
                        <span class="text-base">👑</span> {{ t('Extended License') }}
                    </h3>
                    <p class="mt-2 text-sm text-purple-700 dark:text-purple-300">
                        {{ t('End users can be charged. Unlocks subscription plans, billing, affiliate program, and all Pro features.') }}
                    </p>
                </div>
            </div>
        </section>

        <ActionConfirmModal
            :open="showDeactivateConfirm"
            title="Deactivate License"
            message="Deactivating the license will block the entire frontend. Are you sure?"
            confirm-label="Deactivate"
            :processing="deactivateForm.processing"
            @cancel="showDeactivateConfirm = false"
            @confirm="deactivateForm.post(route('admin.license.deactivate'), { preserveScroll: true, onSuccess: () => { showDeactivateConfirm = false } })"
        />
    </div>
</template>
