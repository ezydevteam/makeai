<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

interface ApiKey {
    id: number
    provider: string
    is_active: boolean
    created_at: string | null
}

const props = defineProps<{
    apiKeys: ApiKey[]
}>()

const { formatDate } = useDateFormat()
const { t } = useTranslate()
const toast = useToastr()

const form = useForm({
    provider: '',
    api_key: '',
})

const apiKeys = ref<ApiKey[]>([...props.apiKeys])
const showForm = ref(false)
const confirmDelete = ref<ApiKey | null>(null)
const deleting = ref(false)

const providers = [
    { value: 'openai', label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'google', label: 'Google' },
    { value: 'xai', label: 'xAI' },
    { value: 'deepseek', label: 'DeepSeek' },
    { value: 'openrouter', label: 'OpenRouter' },
    { value: 'groq', label: 'Groq' },
    { value: 'mistral', label: 'Mistral' },
]

const providerOptions = computed(() => providers)

const providerLabels: Record<string, string> = {
    openai: 'OpenAI',
    anthropic: 'Anthropic',
    google: 'Google',
    xai: 'xAI',
    deepseek: 'DeepSeek',
    openrouter: 'OpenRouter',
    groq: 'Groq',
    mistral: 'Mistral',
}

const displayProvider = (provider: string) => providerLabels[provider.toLowerCase()] ?? provider

const submit = () => {
    form.post(route('user.dashboard.api-keys.store'), {
        onSuccess: () => {
            form.reset()
            showForm.value = false
        },
    })
}

const askDelete = (key: ApiKey) => {
    confirmDelete.value = key
}

const deleteKey = () => {
    if (!confirmDelete.value) return

    deleting.value = true
    router.delete(route('user.dashboard.api-keys.destroy', confirmDelete.value.id), {
        onSuccess: () => {
            toast.success(t('API key deleted successfully.'))
            confirmDelete.value = null
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            toast.error(firstError || t('Failed to delete API key.'))
        },
        onFinish: () => {
            deleting.value = false
        },
    })
}

watch(
    () => props.apiKeys,
    (keys) => {
        apiKeys.value = [...keys]
    },
    { immediate: true },
)
</script>

<template>
    <Head :title="$t('API Keys')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('API Keys') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('Add your own API keys to bypass platform credits.') }}</p>
            </div>
            <button @click="showForm = !showForm" class="rounded-full btn-primary">
                <i class="ti ti-plus inline mr-1"></i> {{ $t('Add key') }}
            </button>
        </div>

        <div v-if="showForm" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <AppSelect
                        v-model="form.provider"
                        :options="providerOptions"
                        :label="$t('Provider')"
                        :error="form.errors.provider"
                        :required="true"
                        :placeholder="$t('Select a provider')"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('API key') }}</label>
                    <input v-model="form.api_key" type="text" required :placeholder="$t('sk-... or similar')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-mono text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <p v-if="form.errors.api_key" class="mt-1 text-xs text-red-500">{{ form.errors.api_key }}</p>
                </div>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-[#1F75FE] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1a65e0] transition disabled:opacity-60">
                    {{ form.processing ? $t('Saving...') : $t('Save key') }}
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <div v-if="apiKeys.length === 0" class="px-6 py-16 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                        <i class="ti ti-key text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-sm text-gray-500">{{ $t('No API keys added yet.') }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $t('Add your own keys to use AI providers without using platform credits.') }}</p>
                </div>
                <div v-for="key in apiKeys" :key="key.id" class="flex items-center justify-between px-6 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ displayProvider(key.provider) }}</span>
                            <span v-if="key.is_active" class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                {{ $t('Active') }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-400">{{ $t('Added') }} {{ key.created_at ? formatDate(key.created_at) : '' }}</p>
                    </div>
                    <button @click="askDelete(key)" class="rounded-full w-8 h-8 text-gray-400 hover:bg-red-50 hover:!text-red-500 transition">
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="confirmDelete !== null"
            :title="$t('Remove API key?')"
            :message="confirmDelete ? $t('Are you sure you want to remove the :provider API key?', { provider: displayProvider(confirmDelete.provider) }) : ''"
            :confirm-label="$t('Remove')"
            :cancel-label="$t('Cancel')"
            :processing-label="$t('Removing...')"
            :processing="deleting"
            variant="danger"
            @cancel="confirmDelete = null"
            @confirm="deleteKey"
        />
    </div>
</template>
