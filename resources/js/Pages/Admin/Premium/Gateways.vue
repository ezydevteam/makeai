<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useToastr } from '@/Composables/useToastr'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

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
    sort_order: number
    fields: Field[]
    webhook_url: string | null
    credentials: Record<string, { configured: boolean; value: string }>
}

const props = defineProps<{
    gateways: Gateway[]
}>()

const { t } = useTranslate()
const { currencySymbol } = useNumberFormat()
const toast = useToastr()
const localGateways = ref<Gateway[]>([...props.gateways])
const selectedSlug = ref(localGateways.value[0]?.slug ?? '')
const selectedGateway = computed(() => localGateways.value.find((gateway) => gateway.slug === selectedSlug.value) ?? localGateways.value[0])
const draggingId = ref<number | null>(null)
const dragOverId = ref<number | null>(null)
const copiedWebhookSlug = ref<string | null>(null)
const editModalOpen = ref(false)

function maskCredential(value: string): string {
    if (!value) return ''
    if (value.includes('*')) return value
    if (value.length <= 4) return '*'.repeat(value.length)
    if (value.length <= 8) return `${value.slice(0, 2)}****${value.slice(-2)}`

    return `${value.slice(0, 3)}****${value.slice(-3)}`
}

const openEditModal = (gateway: Gateway) => {
    selectedSlug.value = gateway.slug
    editModalOpen.value = true
}

const closeEditModal = () => {
    editModalOpen.value = false
}

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

const feeTypeOptions = computed(() => [
    { label: t('No fee'), value: 'none' },
    { label: t('Percentage'), value: 'percentage' },
    { label: t('Fixed'), value: 'fixed' },
])

// Affix shown inside the Fee value input: % for a percentage fee, the store
// currency symbol for a fixed fee, nothing for "No fee".
const feeValueAffix = computed(() => {
    if (form.processing_fee_type === 'percentage') return '%'
    if (form.processing_fee_type === 'fixed') return currencySymbol.value
    return ''
})

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
    credentials: {} as Record<string, string>,
})

const resetForm = () => {
    if (!selectedGateway.value) return

    form.clearErrors()
    form.defaults({
        is_enabled: selectedGateway.value.is_enabled,
        is_test_mode: selectedGateway.value.is_test_mode,
        processing_fee_type: selectedGateway.value.processing_fee_type,
        processing_fee_value: String(selectedGateway.value.processing_fee_value ?? 0),
        credentials: Object.fromEntries(selectedGateway.value.fields.map((field) => {
            const existingValue = selectedGateway.value?.credentials[field.key]?.value ?? ''
            const isConfigured = selectedGateway.value?.credentials[field.key]?.configured ?? false

            return [field.key, isConfigured ? maskCredential(existingValue) : '']
        })),
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
        onSuccess: () => {
            closeEditModal()
        }
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
    dragOverId.value = null
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
        <div class="max-w-5xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-5">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Payment Gateways') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Enable gateways, manage credentials, and define checkout priority from one place.') }}
                </p>
            </div>

            <div class="border border-gray-100 bg-white shadow-sm sm:rounded-2xl dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Gateways') }}</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Drag items by the handle to reorder checkout priority.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table :class="{ 'is-dragging': draggingId !== null }" class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="w-12 px-4 py-3 text-center">{{ t('Sort') }}</th>
                                <th class="px-6 py-3">{{ t('Gateway') }}</th>
                                <th class="px-6 py-3 text-center">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-center">{{ t('Mode') }}</th>
                                <th class="w-24 px-6 py-3 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                            <tr
                                v-for="gateway in localGateways"
                                :key="gateway.id"
                                draggable="true"
                                :class="[
                                    'bg-white dark:bg-gray-800 transition-all duration-200',
                                    draggingId === gateway.id ? 'opacity-30 bg-gray-100/50 dark:bg-gray-700/50' : '',
                                    (dragOverId === gateway.id && draggingId !== gateway.id) ? 'rounded-lg bg-primary-50/80 dark:bg-primary-950/40 outline outline-dashed outline-primary-500 dark:outline-primary-400 outline-offset-[-2px] scale-[0.99] shadow-inner' : 'hover:bg-primary-50/40 dark:hover:bg-white/[0.03]'
                                ]"
                                @dragstart="draggingId = gateway.id"
                                @dragenter.prevent="dragOverId = gateway.id"
                                @dragover.prevent="dragOverId = gateway.id"
                                @dragleave="dragOverId = dragOverId === gateway.id ? null : dragOverId"
                                @dragend="draggingId = null; dragOverId = null"
                                @drop="onDrop(gateway.id)"
                            >
                                <td class="px-4 py-4 text-center align-middle">
                                    <div class="cursor-grab text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                        <i class="ti ti-menu text-lg"></i>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-middle">
                                    <div class="min-w-[150px]">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ gateway.name }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ gateway.description || t('No description available.') }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center align-middle">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide"
                                        :class="gateway.is_enabled
                                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ gateway.is_enabled ? t('Enabled') : t('Disabled') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center align-middle">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide"
                                        :class="gateway.is_test_mode
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'"
                                    >
                                        {{ gateway.is_test_mode ? t('Test') : t('Live') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right align-middle">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                        @click="openEditModal(gateway)"
                                    >
                                        <i class="ti ti-edit text-base"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AppModal
            :open="editModalOpen && !!selectedGateway"
            :title="t('Edit Gateway: :name', { name: selectedGateway.name })"
            :subtitle="t('Configure visibility, environment mode, and credentials.')"
            has-form
            :confirm-text="t('Save Gateway')"
            :confirm-loading="form.processing"
            :confirm-loading-text="t('Saving...')"
            confirm-icon="ti ti-device-floppy"
            confirm-variant="admin"
            @close="closeEditModal"
            @submit="submit"
        >
            <!-- Basic Config -->
            <div class="space-y-4">
                <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ t('Gateway Settings') }}</h4>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                        <span>{{ t('Enable gateway') }}</span>
                        <AppSwitch
                            :model-value="form.is_enabled"
                            @update:model-value="toggleGatewayEnabled"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                        <span>{{ t('Test mode') }}</span>
                        <AppSwitch
                            v-model="form.is_test_mode"
                        />
                    </label>

                    <div class="block">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Fee type') }}</span>
                        <AppSelect
                            v-model="form.processing_fee_type"
                            :options="feeTypeOptions"
                            :placeholder="t('Select fee type')"
                        />
                    </div>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Fee value') }}</span>
                        <div class="relative">
                            <span
                                v-if="feeValueAffix"
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm font-medium text-gray-400 dark:text-gray-500"
                            >{{ feeValueAffix }}</span>
                            <input
                                v-model="form.processing_fee_value"
                                type="number"
                                min="0"
                                step="0.01"
                                :disabled="form.processing_fee_type === 'none'"
                                :placeholder="t('Enter fee value')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :class="feeValueAffix ? 'pl-9' : 'pl-4'"
                            />
                        </div>
                    </label>
                </div>
            </div>

            <!-- API Credentials fields -->
            <div class="space-y-4 border-t border-gray-100 pt-5 dark:border-surface-800">
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ t('API Credentials') }}</h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Existing secrets are masked. Add a new value to replace them, or leave blank to keep.') }}</p>
                </div>

                <div class="grid gap-4 grid-cols-1">
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

            <!-- Webhook section -->
            <div v-if="selectedGateway.webhook_url" class="rounded-xl border border-blue-100 bg-blue-50/50 p-4 dark:border-blue-900/30 dark:bg-blue-900/10">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-blue-600 shadow-sm dark:bg-blue-900/30 dark:text-blue-300">
                        <i class="ti ti-webhook text-base"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-blue-900 dark:text-blue-200">{{ t('Webhook URL') }}</p>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl border border-blue-200 bg-white px-2 py-1 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200 dark:hover:bg-blue-900/30"
                                @click="copyWebhookUrl"
                            >
                                <i :class="copiedWebhookSlug === selectedGateway.slug ? 'ti ti-check' : 'ti ti-copy'" class="text-xs"></i>
                                {{ copiedWebhookSlug === selectedGateway.slug ? t('Copied') : t('Copy') }}
                            </button>
                        </div>
                        <code class="mt-2 block break-all text-xs text-gray-700 dark:text-gray-200 select-all">{{ selectedGateway.webhook_url }}</code>
                    </div>
                </div>
            </div>
        </AppModal>
    </AdminLayout>
</template>

<style scoped>
.is-dragging td * {
    pointer-events: none !important;
}
</style>
