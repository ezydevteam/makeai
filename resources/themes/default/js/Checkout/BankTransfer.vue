<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Layout from '@themes/default/js/Layouts/AppLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface PaymentPayload {
    ulid: string
    status: string
    gateway: string
    formatted_amount: string
    subtotal_formatted: string
    vat_amount: number
    vat_percentage: number
    vat_formatted: string
    plan_total_formatted: string
    processing_fee_amount: number
    processing_fee_formatted: string
    plan: {
        name: string | null
        slug: string | null
    }
    billing_cycle: string | null
}

const props = defineProps<{
    payment: PaymentPayload
    instructions: string | null
}>()

const { t } = useTranslate()
const page = usePage()

// The site header is hidden here (hide_header from CheckoutController), so the logo is
// rendered in the page — this is still a payment step, and a page asking for money with
// no branding on it is exactly what a phishing page looks like.
const branding = computed(() => (page.props.branding as { site_name?: string; site_logo_light?: string; site_logo_dark?: string }) ?? {})
const logoLight = computed(() => String(branding.value.site_logo_light || ''))
const logoDark = computed(() => String(branding.value.site_logo_dark || logoLight.value))

const fileName = ref('')
const form = useForm({
    proof: null as File | null,
    reference: '',
    note: '',
})

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null

    form.proof = file
    fileName.value = file?.name ?? ''
}

const submitProof = () => {
    form.post(`/checkout/bank-transfer/${props.payment.ulid}/proof`, {
        forceFormData: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Bank Transfer')" />

    <Layout>
        <!-- Soft primary wash down both edges. Fixed so it covers the viewport however far
             the instructions scroll, and pointer-events-none so it never eats a click. -->
        <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
            <div class="absolute inset-y-0 left-0 w-1/3 max-w-md bg-gradient-to-r from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
            <div class="absolute inset-y-0 right-0 w-1/3 max-w-md bg-gradient-to-l from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
        </div>

        <!--
            Centred with flex, never `mx-auto`: app.ts injects
            `main .mx-auto { max-width: var(--page-width) !important }` on every non-admin
            page, which silently overrides any max-w-* set here.
        -->
        <div class="relative z-10 w-full pt-8 md:pt-12 pb-12">
            <div class="mb-8 flex justify-center px-4">
                <Link href="/" class="inline-flex items-center">
                    <img v-if="logoLight" :src="logoLight" :alt="branding.site_name || 'Logo'" class="h-9 w-auto dark:hidden" />
                    <img v-if="logoDark" :src="logoDark" :alt="branding.site_name || 'Logo'" class="hidden h-9 w-auto dark:block" />
                    <span v-if="!logoLight && !logoDark" class="text-xl font-black text-gray-900 dark:text-white">{{ branding.site_name }}</span>
                </Link>
            </div>

            <div class="flex justify-center px-4 sm:px-6">
                <div class="w-full max-w-xl space-y-5">
                    <div class="text-center">
                        <h1 class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ t('Bank transfer details') }}</h1>
                    </div>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <h2 class="mb-4 text-lg font-black text-gray-900 dark:text-white">{{ t('Payment instructions') }}</h2>

                        <!--
                            The only two figures that survive from the old order summary, and the
                            only two the buyer has to carry into their banking app: what to send,
                            and the reference that lets an admin match it back to this order. The
                            plan breakdown was priced and agreed on the previous screen — repeating
                            it here just buries these two.
                        -->
                        <div class="mb-5 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-end justify-between gap-4">
                                <span class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Amount to transfer') }}</span>
                                <span class="text-2xl font-black text-gray-900 dark:text-white">{{ payment.formatted_amount }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-4 border-t border-gray-200 pt-3 dark:border-surface-700">
                                <span class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Payment ID') }}</span>
                                <span class="text-right font-mono text-xs font-bold text-gray-900 dark:text-white">{{ payment.ulid }}</span>
                            </div>
                        </div>

                        <div class="whitespace-pre-line rounded-xl border border-primary-100 bg-primary-50 p-5 text-sm font-medium leading-7 text-gray-700 dark:border-primary-900/40 dark:bg-primary-900/30 dark:text-gray-200">
                            {{ instructions || t('Bank transfer instructions are not configured yet. Please contact support before sending payment.') }}
                        </div>

                        <form class="mt-6 space-y-4" @submit.prevent="submitProof">
                            <label class="block">
                                <span class="mb-1 block text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Transaction reference') }}</span>
                                <input v-model="form.reference" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary-400 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder-gray-500" :placeholder="t('Bank transaction ID or sender account')" />
                                <p v-if="form.errors.reference" class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400">{{ form.errors.reference }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Upload payment proof') }}</span>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full rounded-xl border border-gray-200 px-2 py-1 text-sm file:mr-3 file:rounded-xl file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-sm file:font-bold file:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:file:border-primary-900/40 dark:file:bg-primary-900/30 dark:file:text-primary-400" @change="onFileChange" />
                                <p class="mt-1 text-xs font-medium text-gray-400 dark:text-gray-500">{{ fileName || t('JPG, PNG, WebP, or PDF up to 5 MB') }}</p>
                                <p v-if="form.errors.proof" class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400">{{ form.errors.proof }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Note') }}</span>
                                <textarea v-model="form.note" rows="4" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary-400 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder-gray-500" :placeholder="t('Optional details for admin review')" />
                                <p v-if="form.errors.note" class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400">{{ form.errors.note }}</p>
                            </label>

                            <!-- Kept from the removed summary card: manual activation is not obvious,
                             and someone who transfers and then waits with no explanation opens a
                             support ticket. -->
                            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-400">
                                {{ t('Your plan will be activated after admin verifies this payment.') }}
                            </p>

                            <button :disabled="form.processing" type="submit" class="w-full rounded-xl btn-primary shadow-lg shadow-primary-600/20 transition disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none">
                                <span v-if="form.processing">
                                    <i class="ti ti-loader animate-spin"></i>
                                    {{ t('Submitting...') }}
                                </span>
                                <span v-else>
                                    {{ t('Submit Proof') }}
                                </span>
                            </button>
                        </form>
                    </section>

                    <div class="text-center">
                        <Link href="/pricing" class="text-sm font-medium text-gray-300 underline-offset-4 transition hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400">
                            <i class="ti ti-arrow-left"></i>
                            {{ t('Back to pricing') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
