<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
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
    proof_uploaded: boolean
    created_at: string | null
}

const props = defineProps<{
    payment: PaymentPayload
}>()

const { t } = useTranslate()
const page = usePage()

// The site header is hidden here (hide_header from CheckoutController), so the logo is
// rendered in the page — this is the last screen of a payment flow, and an unbranded page
// about someone's money is what phishing looks like.
const branding = computed(() => (page.props.branding as { site_name?: string; site_logo_light?: string; site_logo_dark?: string }) ?? {})
const logoLight = computed(() => String(branding.value.site_logo_light || ''))
const logoDark = computed(() => String(branding.value.site_logo_dark || logoLight.value))

const gatewayLabel = computed(() => props.payment.gateway.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()))
</script>

<template>
    <Head :title="t('Payment Pending')" />

    <Layout>
        <!-- Soft primary wash down both edges, matching the rest of the payment flow. -->
        <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
            <div class="absolute inset-y-0 left-0 w-1/3 max-w-md bg-gradient-to-r from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
            <div class="absolute inset-y-0 right-0 w-1/3 max-w-md bg-gradient-to-l from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
        </div>

        <!--
            Centred with flex, never `mx-auto`: app.ts injects
            `main .mx-auto { max-width: var(--page-width) !important }` on every non-admin
            page, which silently overrides any max-w-* set here.
        -->
        <div class="relative z-10 w-full pt-8 md:pt-12 pb-16">
            <div class="mb-8 flex justify-center px-4">
                <Link href="/" class="inline-flex items-center">
                    <img v-if="logoLight" :src="logoLight" :alt="branding.site_name || 'Logo'" class="h-9 w-auto dark:hidden" />
                    <img v-if="logoDark" :src="logoDark" :alt="branding.site_name || 'Logo'" class="hidden h-9 w-auto dark:block" />
                    <span v-if="!logoLight && !logoDark" class="text-xl font-black text-gray-900 dark:text-white">{{ branding.site_name }}</span>
                </Link>
            </div>

            <div class="flex justify-center px-4 sm:px-6">
                <div class="w-full max-w-xl">
                    <div class="flex flex-col items-center text-center">
                        <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" /></svg>
                        </div>

                        <h1 class="text-3xl font-black text-gray-900 dark:text-white">{{ t('Payment is waiting for confirmation') }}</h1>
                        <p class="mt-3 text-sm font-medium leading-6 text-gray-500 dark:text-gray-400">
                            {{ payment.proof_uploaded ? t('Your payment proof has been submitted. Admin will review it and activate your plan after verification.') : t('Your payment session was created and is waiting for gateway confirmation.') }}
                        </p>
                    </div>

                    <section class="mt-8 rounded-2xl border border-gray-200 bg-white p-6 text-left shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <!--
                            Amount and Payment ID lead, as on the bank transfer screen: these are
                            the two a buyer quotes when they ask where their money went. The price
                            breakdown that used to fill this card — subtotal, VAT, plan total,
                            processing fee — was settled at checkout and only buried them.
                        -->
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800/60">
                            <div class="flex items-end justify-between gap-4">
                                <span class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Payment total') }}</span>
                                <span class="text-2xl font-black text-gray-900 dark:text-white">{{ payment.formatted_amount }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-4 border-t border-gray-200 pt-3 dark:border-surface-700">
                                <span class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ t('Payment ID') }}</span>
                                <span class="break-all text-right font-mono text-xs font-bold text-gray-900 dark:text-white">{{ payment.ulid }}</span>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 text-sm font-medium sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Plan') }}</p>
                                <p class="mt-1 font-bold text-gray-900 dark:text-white">{{ payment.plan.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Billing') }}</p>
                                <p class="mt-1 font-bold capitalize text-gray-900 dark:text-white">{{ payment.billing_cycle }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Gateway') }}</p>
                                <p class="mt-1 font-bold text-gray-900 dark:text-white">{{ gatewayLabel }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Status') }}</p>
                                <p class="mt-1 font-bold capitalize text-amber-700 dark:text-amber-400">{{ payment.status }}</p>
                            </div>
                        </div>
                    </section>

                    <Link href="/user/dashboard/billing" class="mt-6 block w-full rounded-xl btn-primary text-center shadow-lg shadow-primary-600/20 transition">
                        {{ t('Go to dashboard') }}
                        <i class="ti ti-arrow-up-right"></i>
                    </Link>

                    <div class="mt-5 text-center">
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
