<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface Field {
    key: string
    label: string
    type: 'text' | 'password' | 'textarea'
}

interface Gateway {
    id: number
    slug: string
    name: string
    description: string | null
    is_enabled: boolean
    is_test_mode: boolean
    processing_fee_type: 'none' | 'percentage' | 'fixed'
    processing_fee_value: string | number
    processing_fee_currency: string
    sort_order: number
    fields: Field[]
    webhook_url: string | null
    credentials: Record<string, { configured: boolean; value: string }>
}

const props = defineProps<{
    gateways: Gateway[]
    currencies: string[]
}>()

const { t } = useTranslate()
const localGateways = ref<Gateway[]>([...props.gateways])
const selectedSlug = ref(localGateways.value[0]?.slug ?? '')
const selectedGateway = computed(() => localGateways.value.find((gateway) => gateway.slug === selectedSlug.value) ?? localGateways.value[0])
const draggingId = ref<number | null>(null)

const form = useForm({
    is_enabled: false,
    is_test_mode: true,
    processing_fee_type: 'none' as 'none' | 'percentage' | 'fixed',
    processing_fee_value: '0',
    processing_fee_currency: 'USD',
    credentials: {} as Record<string, string>,
    settings: {},
})

const resetForm = () => {
    if (!selectedGateway.value) return

    form.clearErrors()
    form.defaults({
        is_enabled: selectedGateway.value.is_enabled,
        is_test_mode: selectedGateway.value.is_test_mode,
        processing_fee_type: selectedGateway.value.processing_fee_type,
        processing_fee_value: String(selectedGateway.value.processing_fee_value ?? 0),
        processing_fee_currency: selectedGateway.value.processing_fee_currency,
        credentials: Object.fromEntries(selectedGateway.value.fields.map((field) => [field.key, ''])),
        settings: {},
    })
    form.reset()
}

watch(() => props.gateways, (gateways) => {
    localGateways.value = [...gateways]
    if (!localGateways.value.find((gateway) => gateway.slug === selectedSlug.value)) {
        selectedSlug.value = localGateways.value[0]?.slug ?? ''
    }
}, { deep: true })

watch(selectedSlug, resetForm, { immediate: true })

const configuredLabel = (field: Field) => {
    const configured = selectedGateway.value?.credentials[field.key]?.configured

    return configured ? t('Configured') : t('Not set')
}

const submit = () => {
    if (!selectedGateway.value) return

    form.post(route('admin.payment-gateways.update', selectedGateway.value.id), {
        preserveScroll: true,
    })
}

const onDrop = (targetId: number) => {
    if (draggingId.value === null || draggingId.value === targetId) {
        draggingId.value = null
        return
    }

    const fromIndex = localGateways.value.findIndex((gateway) => gateway.id === draggingId.value)
    const toIndex = localGateways.value.findIndex((gateway) => gateway.id === targetId)

    if (fromIndex === -1 || toIndex === -1) return

    const [moved] = localGateways.value.splice(fromIndex, 1)
    localGateways.value.splice(toIndex, 0, moved)
    draggingId.value = null

    router.post(route('admin.payment-gateways.sort'), {
        gateways: localGateways.value.map((gateway) => gateway.id),
    }, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Payment Gateways')" />

    <AdminLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ t('Payment Gateways') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Enable gateways, set fees, add API keys, and drag to sort checkout priority.') }}</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                    <aside class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <button
                            v-for="gateway in localGateways"
                            :key="gateway.id"
                            type="button"
                            draggable="true"
                            @dragstart="draggingId = gateway.id"
                            @dragover.prevent
                            @drop="onDrop(gateway.id)"
                            @click="selectedSlug = gateway.slug"
                            :class="selectedSlug === gateway.slug ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300' : 'border-transparent text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800'"
                            class="mb-2 flex w-full cursor-grab items-center justify-between rounded-lg border px-4 py-3 text-left transition"
                        >
                            <span>
                                <span class="block text-sm font-semibold">{{ gateway.name }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ gateway.is_enabled ? t('Enabled') : t('Disabled') }}</span>
                            </span>
                            <span :class="gateway.is_enabled ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'" class="rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wider">
                                {{ gateway.sort_order }}
                            </span>
                        </button>
                    </aside>

                    <form v-if="selectedGateway" class="space-y-6" @submit.prevent="submit">
                        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedGateway.name }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ selectedGateway.description }}</p>
                                </div>
                                <button type="submit" :disabled="form.processing" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60">
                                    {{ form.processing ? t('Saving...') : t('Save gateway') }}
                                </button>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                    {{ t('Enable gateway') }}
                                    <input v-model="form.is_enabled" type="checkbox" class="peer sr-only" />
                                    <span class="relative h-6 w-11 rounded-full bg-gray-200 transition after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-primary-500 peer-checked:after:translate-x-5 dark:bg-gray-700"></span>
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                    {{ t('Test mode') }}
                                    <input v-model="form.is_test_mode" type="checkbox" class="peer sr-only" />
                                    <span class="relative h-6 w-11 rounded-full bg-gray-200 transition after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-primary-500 peer-checked:after:translate-x-5 dark:bg-gray-700"></span>
                                </label>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Processing fee') }}</h3>
                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Fee type') }}</span>
                                    <select v-model="form.processing_fee_type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                        <option value="none">{{ t('None') }}</option>
                                        <option value="percentage">{{ t('Percentage') }}</option>
                                        <option value="fixed">{{ t('Fixed') }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Fee value') }}</span>
                                    <input v-model="form.processing_fee_value" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Fee currency') }}</span>
                                    <select v-model="form.processing_fee_currency" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                        <option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option>
                                    </select>
                                </label>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('API credentials') }}</h3>
                            <div v-if="selectedGateway.webhook_url" class="mb-4 rounded-lg border border-primary-100 bg-primary-50 p-4 dark:border-primary-900/40 dark:bg-primary-900/10">
                                <span class="mb-1 block text-sm font-semibold text-primary-800 dark:text-primary-200">{{ t('Webhook URL') }}</span>
                                <code class="break-all text-sm font-semibold text-gray-700 dark:text-gray-200">{{ selectedGateway.webhook_url }}</code>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <label v-for="field in selectedGateway.fields" :key="field.key" class="block" :class="{ 'md:col-span-2': field.type === 'textarea' }">
                                    <span class="mb-1 flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ field.label }}
                                        <span class="text-xs text-gray-400">{{ configuredLabel(field) }}</span>
                                    </span>
                                    <textarea v-if="field.type === 'textarea'" v-model="form.credentials[field.key]" rows="4" :placeholder="t('Leave blank to keep existing value')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                    <input v-else v-model="form.credentials[field.key]" :type="field.type === 'password' ? 'password' : 'text'" :placeholder="t('Leave blank to keep existing value')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                            </div>
                        </section>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
