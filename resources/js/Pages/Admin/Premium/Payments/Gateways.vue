<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppSelect from '@/Components/AppSelect.vue'
import { useToastr } from '@/Composables/useToastr'
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
const toast = useToastr()
const localGateways = ref<Gateway[]>([...props.gateways])
const selectedSlug = ref(localGateways.value[0]?.slug ?? '')
const selectedGateway = computed(() => localGateways.value.find((gateway) => gateway.slug === selectedSlug.value) ?? localGateways.value[0])
const draggingId = ref<number | null>(null)
const copiedWebhookSlug = ref<string | null>(null)
const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

const feeTypeOptions = computed(() => [
    { label: t('None'), value: 'none' },
    { label: t('Percentage'), value: 'percentage' },
    { label: t('Fixed'), value: 'fixed' },
])

const currencyOptions = computed(() => props.currencies.map((currency) => ({
    label: currency,
    value: currency,
})))

const configuredFieldCount = computed(() => {
    if (!selectedGateway.value) return 0

    return selectedGateway.value.fields.filter((field) => selectedGateway.value?.credentials[field.key]?.configured).length
})

const hasMissingCredentialsForEnable = computed(() => {
    if (!selectedGateway.value) return false

    return selectedGateway.value.fields.some((field) => {
        const currentValue = String(form.credentials[field.key] ?? '').trim()
        const existingValue = selectedGateway.value?.credentials[field.key]?.value ?? ''
        const isConfigured = selectedGateway.value?.credentials[field.key]?.configured ?? false
        const maskedValue = maskCredential(existingValue)

        return !currentValue || (isConfigured && currentValue === maskedValue)
    })
})

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
        credentials: Object.fromEntries(selectedGateway.value.fields.map((field) => {
            const existingValue = selectedGateway.value?.credentials[field.key]?.value ?? ''
            const isConfigured = selectedGateway.value?.credentials[field.key]?.configured ?? false

            return [field.key, isConfigured ? maskCredential(existingValue) : '']
        })),
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

const maskCredential = (value: string) => {
    if (!value) return ''
    if (value.includes('*')) return value
    if (value.length <= 4) return '*'.repeat(value.length)
    if (value.length <= 8) return `${value.slice(0, 2)}****${value.slice(-2)}`

    return `${value.slice(0, 3)}****${value.slice(-3)}`
}

const credentialDisplayValue = (field: Field) => {
    const rawValue = String(form.credentials[field.key] ?? '')
    const existingValue = selectedGateway.value?.credentials[field.key]?.value ?? ''

    if (rawValue.trim().length > 0) {
        return rawValue
    }

    return maskCredential(existingValue)
}

const toggleGatewayEnabled = () => {
    const nextValue = !form.is_enabled

    if (!nextValue) {
        form.is_enabled = false
        return
    }

    if (hasMissingCredentialsForEnable.value) {
        toast.error(t('Add the required API credentials before enabling this gateway.'))
        return
    }

    form.is_enabled = true
}

const copyWebhookUrl = async () => {
    if (!selectedGateway.value?.webhook_url) return

    try {
        await navigator.clipboard.writeText(selectedGateway.value.webhook_url)
        copiedWebhookSlug.value = selectedGateway.value.slug
        window.setTimeout(() => {
            if (copiedWebhookSlug.value === selectedGateway.value?.slug) {
                copiedWebhookSlug.value = null
            }
        }, 1800)
    } catch {
        toast.error(t('Unable to copy webhook URL.'))
    }
}

const submit = () => {
    if (!selectedGateway.value) return

    if (form.is_enabled && hasMissingCredentialsForEnable.value) {
        toast.error(t('Add the required API credentials before enabling this gateway.'))
        return
    }

    const credentialPayload = Object.fromEntries(selectedGateway.value.fields.map((field) => {
        const currentValue = String(form.credentials[field.key] ?? '').trim()
        const existingValue = selectedGateway.value?.credentials[field.key]?.value ?? ''
        const maskedValue = maskCredential(existingValue)

        return [field.key, currentValue === maskedValue ? '' : currentValue]
    }))

    form.transform((data) => ({
        ...data,
        credentials: credentialPayload,
    })).post(route('admin.payment-gateways.update', selectedGateway.value.id), {
        preserveScroll: true,
    })
}

const persistGatewayOrder = async () => {
    await fetch(route('admin.payment-gateways.sort'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            gateways: localGateways.value.map((gateway) => gateway.id),
        }),
    })
}

const onDrop = async (targetId: number) => {
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
    await persistGatewayOrder()
}

const moveGateway = async (gatewayId: number, direction: 'up' | 'down') => {
    const currentIndex = localGateways.value.findIndex((gateway) => gateway.id === gatewayId)

    if (currentIndex === -1) return

    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1

    if (targetIndex < 0 || targetIndex >= localGateways.value.length) return

    const [moved] = localGateways.value.splice(currentIndex, 1)
    localGateways.value.splice(targetIndex, 0, moved)
    await persistGatewayOrder()
}
</script>

<template>
    <Head :title="t('Payment Gateways')" />

    <AdminLayout>
        <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Payment Gateways') }}</h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Enable gateways, manage credentials, and define checkout priority from one place.') }}
                        </p>
                    </div>
                    <button
                        v-if="selectedGateway"
                        type="button"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit"
                    >
                        <i :class="form.processing ? 'ti ti-loader-2 animate-spin' : 'ti ti-device-floppy'" class="text-base"></i>
                        {{ form.processing ? t('Saving...') : t('Save Gateway') }}
                    </button>
                </section>

                <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                    <aside class="self-start border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Gateways') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Drag items to reorder checkout priority.') }}</p>
                        </div>

                        <div class="space-y-2 p-3">
                            <button
                                v-for="gateway in localGateways"
                                :key="gateway.id"
                                type="button"
                                draggable="true"
                                class="w-full cursor-grab rounded-lg border px-4 py-3 text-left transition"
                                :class="selectedSlug === gateway.slug
                                    ? 'border-primary-200 bg-primary-50 shadow-sm dark:border-primary-800 dark:bg-primary-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/70'"
                                @dragstart="draggingId = gateway.id"
                                @dragover.prevent
                                @drop="onDrop(gateway.id)"
                                @click="selectedSlug = gateway.slug"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <i class="ti ti-credit-card text-base text-gray-400 dark:text-gray-500"></i>
                                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ gateway.name }}</p>
                                        </div>
                                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ gateway.description || t('No description available.') }}</p>
                                    </div>

                                    <div class="flex flex-col gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                                            :disabled="localGateways[0]?.id === gateway.id"
                                            @click.stop="moveGateway(gateway.id, 'up')"
                                        >
                                            <i class="ti ti-chevron-up text-xs"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                                            :disabled="localGateways[localGateways.length - 1]?.id === gateway.id"
                                            @click.stop="moveGateway(gateway.id, 'down')"
                                        >
                                            <i class="ti ti-chevron-down text-xs"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="gateway.is_enabled
                                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ gateway.is_enabled ? t('Enabled') : t('Disabled') }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="gateway.is_test_mode
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'"
                                    >
                                        {{ gateway.is_test_mode ? t('Test') : t('Live') }}
                                    </span>
                                </div>
                            </button>
                        </div>
                    </aside>

                    <form v-if="selectedGateway" class="space-y-6" @submit.prevent="submit">
                        <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                                                <i class="ti ti-credit-card text-lg"></i>
                                            </span>
                                            <div>
                                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedGateway.name }}</h2>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ selectedGateway.description || t('Configure the payment flow, credentials, and fees for this gateway.') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="grid gap-4 p-6 md:grid-cols-3">
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</p>
                                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ form.is_enabled ? t('Enabled for checkout') : t('Hidden from checkout') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Use this to instantly show or hide the gateway.') }}</p>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Mode') }}</p>
                                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ form.is_test_mode ? t('Test mode active') : t('Live mode active') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep sandbox keys in test mode and production keys in live mode.') }}</p>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Configured fields') }}</p>
                                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ t(':count of :total ready', { count: configuredFieldCount, total: selectedGateway.fields.length }) }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Existing secrets stay unchanged when you leave a field blank.') }}</p>
                                </div>
                            </div>
                        </section>

                        <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Gateway Settings') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose visibility, environment mode, and payment fee behavior.') }}</p>
                            </div>

                            <div class="grid gap-6 p-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                                        <span>{{ t('Enable gateway') }}</span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.is_enabled"
                                            class="app-switch"
                                            @click="toggleGatewayEnabled"
                                        >
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                    </label>

                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                                        <span>{{ t('Test mode') }}</span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.is_test_mode"
                                            class="app-switch"
                                            @click="form.is_test_mode = !form.is_test_mode"
                                        >
                                            <span class="app-switch__thumb"></span>
                                        </button>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Fee type') }}</span>
                                        <AppSelect
                                            v-model="form.processing_fee_type"
                                            :options="feeTypeOptions"
                                            :placeholder="t('Select fee type')"
                                        />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Fee currency') }}</span>
                                        <AppSelect
                                            v-model="form.processing_fee_currency"
                                            :options="currencyOptions"
                                            :placeholder="t('Select currency')"
                                            live-search
                                        />
                                    </label>

                                    <label class="block md:col-span-2">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Fee value') }}</span>
                                        <input
                                            v-model="form.processing_fee_value"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :placeholder="t('Enter fee value')"
                                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        />
                                    </label>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Fee Preview') }}</h3>
                                    <div class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ t('Type') }}</span>
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{
                                                    form.processing_fee_type === 'percentage'
                                                        ? t('Percentage')
                                                        : form.processing_fee_type === 'fixed'
                                                            ? t('Fixed')
                                                            : t('None')
                                                }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ t('Value') }}</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ form.processing_fee_value || '0' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ t('Currency') }}</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ form.processing_fee_currency || '-' }}</span>
                                        </div>
                                    </div>
                                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Use percentage for variable fees or fixed for a flat amount added at checkout.') }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('API Credentials') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Add only the values you want to replace. Existing secrets remain untouched when left blank.') }}</p>
                            </div>

                            <div class="space-y-6 p-6">
                                <div v-if="selectedGateway.webhook_url" class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-blue-600 shadow-sm dark:bg-blue-900/30 dark:text-blue-300">
                                            <i class="ti ti-webhook text-base"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200">{{ t('Webhook URL') }}</p>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-md border border-blue-200 bg-white px-2 py-1 text-[11px] font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200 dark:hover:bg-blue-900/30"
                                                    @click="copyWebhookUrl"
                                                >
                                                    <i :class="copiedWebhookSlug === selectedGateway.slug ? 'ti ti-check' : 'ti ti-copy'" class="text-xs"></i>
                                                    {{ copiedWebhookSlug === selectedGateway.slug ? t('Copied') : t('Copy') }}
                                                </button>
                                            </div>
                                            <code class="mt-2 block break-all text-xs text-gray-700 dark:text-gray-200">{{ selectedGateway.webhook_url }}</code>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <label
                                        v-for="field in selectedGateway.fields"
                                        :key="field.key"
                                        class="block"
                                        :class="{ 'md:col-span-2': field.type === 'textarea' }"
                                    >
                                        <span class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                                            <span>{{ field.label }}</span>
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide"
                                                :class="configuredLabel(field) === t('Configured')
                                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300'"
                                            >
                                                {{ configuredLabel(field) }}
                                            </span>
                                        </span>

                                        <textarea
                                            v-if="field.type === 'textarea'"
                                            v-model="form.credentials[field.key]"
                                            rows="4"
                                            :placeholder="t('Leave blank to keep existing value')"
                                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        />
                                        <input
                                            v-else
                                            :value="credentialDisplayValue(field)"
                                            :type="field.type === 'password' ? 'password' : 'text'"
                                            :placeholder="selectedGateway.credentials[field.key]?.configured ? t('Enter a new value to replace the current one') : t('Enter credential value')"
                                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            @input="form.credentials[field.key] = ($event.target as HTMLInputElement).value"
                                        />
                                    </label>
                                </div>
                            </div>
                        </section>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
