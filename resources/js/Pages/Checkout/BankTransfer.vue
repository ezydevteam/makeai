<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
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
}

const props = defineProps<{
    payment: PaymentPayload
    instructions: string | null
}>()

const { t } = useTranslate()
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
        <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold uppercase tracking-widest text-primary-600">{{ t('Manual Payment') }}</p>
                    <h1 class="mt-2 text-3xl font-black text-gray-900">{{ t('Bank transfer details') }}</h1>
                </div>
                <Link href="/pricing" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-primary-300 hover:text-primary-600">
                    {{ t('Back to pricing') }}
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-black text-gray-900">{{ t('Payment instructions') }}</h2>
                    <div class="whitespace-pre-line rounded-xl border border-primary-100 bg-primary-50 p-5 text-sm font-medium leading-7 text-gray-700">
                        {{ instructions || t('Bank transfer instructions are not configured yet. Please contact support before sending payment.') }}
                    </div>

                    <form class="mt-6 space-y-4" @submit.prevent="submitProof">
                        <label class="block">
                            <span class="mb-1 block text-sm font-bold text-gray-700">{{ t('Transaction reference') }}</span>
                            <input v-model="form.reference" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-400 focus:ring-primary-100" :placeholder="t('Bank transaction ID or sender account')" />
                            <p v-if="form.errors.reference" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.reference }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-bold text-gray-700">{{ t('Upload payment proof') }}</span>
                            <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-bold file:text-primary-700" @change="onFileChange" />
                            <p class="mt-1 text-xs font-medium text-gray-400">{{ fileName || t('JPG, PNG, WebP, or PDF up to 5 MB') }}</p>
                            <p v-if="form.errors.proof" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.proof }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-bold text-gray-700">{{ t('Note') }}</span>
                            <textarea v-model="form.note" rows="4" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-primary-400 focus:ring-primary-100" :placeholder="t('Optional details for admin review')" />
                            <p v-if="form.errors.note" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.note }}</p>
                        </label>

                        <button :disabled="form.processing" type="submit" class="w-full rounded-xl btn-primary shadow-lg shadow-primary-600/20 transition disabled:bg-gray-300 disabled:shadow-none">
                            {{ form.processing ? t('Uploading...') : t('Submit proof') }}
                        </button>
                    </form>
                </section>

                <aside class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-5 text-lg font-black text-gray-900">{{ t('Order summary') }}</h2>
                    <div class="space-y-4 text-sm font-medium text-gray-600">
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Plan') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ payment.plan.name }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Billing') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ payment.billing_cycle }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Subtotal') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ payment.subtotal_formatted }}</span>
                        </div>
                        <div v-if="payment.vat_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('Plan VAT') }} ({{ payment.vat_percentage }}%)</span>
                            <span class="text-right font-bold text-gray-900">{{ payment.vat_formatted }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Plan total') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ payment.plan_total_formatted }}</span>
                        </div>
                        <div v-if="payment.processing_fee_amount > 0" class="flex justify-between gap-4">
                            <span>{{ t('Processing fee') }}</span>
                            <span class="text-right font-bold text-gray-900">{{ payment.processing_fee_formatted }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>{{ t('Payment ID') }}</span>
                            <span class="text-right font-mono text-xs font-bold text-gray-900">{{ payment.ulid }}</span>
                        </div>
                    </div>
                    <div class="my-5 border-t border-gray-100"></div>
                    <div class="flex items-end justify-between gap-4">
                        <span class="text-sm font-bold text-gray-500">{{ t('Payment total') }}</span>
                        <span class="text-3xl font-black text-gray-900">{{ payment.formatted_amount }}</span>
                    </div>
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-800">
                        {{ t('Your plan will be activated after admin verifies this payment.') }}
                    </p>
                </aside>
            </div>
        </div>
    </Layout>
</template>
