<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Layouts/AppLayout.vue'
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
const gatewayLabel = computed(() => props.payment.gateway.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()))
</script>

<template>
    <Head :title="t('Payment Pending')" />

    <Layout>
        <div class="mx-auto flex max-w-3xl flex-col items-center px-4 py-16 text-center sm:px-6">
            <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" /></svg>
            </div>

            <p class="text-sm font-bold uppercase tracking-widest text-primary-600">{{ t('Temporary Pending Transaction') }}</p>
            <h1 class="mt-2 text-3xl font-black text-gray-900">{{ t('Payment is waiting for confirmation') }}</h1>
            <p class="mt-3 max-w-2xl text-sm font-medium leading-6 text-gray-500">
                {{ payment.proof_uploaded ? t('Your payment proof has been submitted. Admin will review it and activate your plan after verification.') : t('Your payment session was created and is waiting for gateway confirmation.') }}
            </p>

            <div class="mt-8 w-full rounded-xl border border-gray-200 bg-white p-6 text-left shadow-sm">
                <div class="grid gap-4 text-sm font-medium text-gray-600 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Payment ID') }}</p>
                        <p class="mt-1 break-all font-mono text-xs font-bold text-gray-900">{{ payment.ulid }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Status') }}</p>
                        <p class="mt-1 font-bold capitalize text-amber-700">{{ payment.status }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Gateway') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ gatewayLabel }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Subtotal') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ payment.subtotal_formatted }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Plan VAT') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ payment.vat_amount > 0 ? `${payment.vat_formatted} (${payment.vat_percentage}%)` : payment.vat_formatted }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Plan Total') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ payment.plan_total_formatted }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Processing Fee') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ payment.processing_fee_formatted }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Payment Total') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ payment.formatted_amount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Plan') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ payment.plan.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ t('Billing') }}</p>
                        <p class="mt-1 font-bold text-gray-900">{{ payment.billing_cycle }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Link href="/dashboard" class="rounded-xl bg-primary-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-primary-600/20 transition hover:bg-primary-500">
                    {{ t('Go to dashboard') }}
                </Link>
                <Link href="/pricing" class="rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-bold text-gray-700 transition hover:border-primary-300 hover:text-primary-600">
                    {{ t('Back to pricing') }}
                </Link>
            </div>
        </div>
    </Layout>
</template>
