<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppIconSelect from '@/Components/IconClassSelect.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

type TabId = 'general' | 'ai' | 'persona' | 'access' | 'prompts' | 'automations'

interface SelectOptionItem {
    value: string
    label: string
}

interface AddonSetting {
    key: string
    type: string
    label: string
    value: string | number | boolean | File | null
    default: string | number | boolean | null
    group?: string | null
    options?: string[]
}

interface AutomationRule {
    id: number
    trigger: string
    response: string
    match_type: 'contains' | 'exact'
    is_active: boolean
}

interface AiModelOption {
    value: string
    label: string
    provider: string
}

interface AiProviderOption {
    value: string
    label: string
}

interface PagePropsShape {
    settings?: AddonSetting[]
    rules?: AutomationRule[]
    addon?: {
        slug?: string
    }
    aiModels?: AiModelOption[]
    aiProviders?: AiProviderOption[]
    aiAssistant?: {
        default_provider?: string
        default_model?: string
    }
}

const { t } = useTranslate()
const page = usePage<PagePropsShape>()

const tabs: Array<{ id: TabId; label: string }> = [
    { id: 'general', label: t('General') },
    { id: 'ai', label: t('AI Model') },
    { id: 'persona', label: t('Persona') },
    { id: 'access', label: t('Access') },
    { id: 'prompts', label: t('System Prompts') },
    { id: 'automations', label: t('Message Automation') },
]

const activeTab = ref<TabId>('general')
const saving = ref(false)
const saved = ref(false)

const addonSettings = computed(() => page.props.settings ?? [])
const rules = computed(() => page.props.rules ?? [])
const addonSlug = computed(() => page.props.addon?.slug ?? 'ai-assistant')
const aiModels = computed(() => page.props.aiModels ?? [])
const defaultProvider = computed(() => page.props.aiAssistant?.default_provider ?? 'openai')
const defaultModel = computed(() => page.props.aiAssistant?.default_model ?? 'gpt-4o-mini')

const form = computed<Record<string, AddonSetting['value']>>(() => {
    const data: Record<string, AddonSetting['value']> = {}

    for (const setting of addonSettings.value) {
        data[setting.key] = setting.value
    }

    return data
})

const providerOptions = computed(() => [
    { value: '', label: t('Use default provider (:provider)', { provider: defaultProvider.value }) },
    ...(page.props.aiProviders ?? []),
])

const selectedProvider = computed(() => {
    return String(addonSettings.value.find((setting) => setting.key === 'provider')?.value ?? '')
})

const activeProvider = computed(() => selectedProvider.value || defaultProvider.value)

const filteredAiModels = computed(() => {
    const models = aiModels.value.filter((model) => model.provider === activeProvider.value)

    return [
        { value: '', label: t('Use default model (:model)', { model: defaultModel.value }), provider: activeProvider.value },
        ...models,
    ]
})

const showModal = ref(false)
const isEditing = ref(false)
const currentRuleId = ref<number | null>(null)
const processingRule = ref(false)
const deletingRuleId = ref<number | null>(null)
const deletingRule = ref(false)

const ruleForm = ref<{
    trigger: string
    response: string
    match_type: 'contains' | 'exact'
    is_active: boolean
}>({
    trigger: '',
    response: '',
    match_type: 'contains',
    is_active: true,
})

const hasRules = computed(() => rules.value.length > 0)

const currentTabTitle = computed(() => {
    return tabs.find((tab) => tab.id === activeTab.value)?.label ?? t('Settings')
})

const currentTabDescription = computed(() => {
    switch (activeTab.value) {
        case 'general':
            return t('Control the widget visibility, icon, position, and frontend behavior.')
        case 'ai':
            return t('Choose the provider, model, and runtime parameters for assistant replies.')
        case 'persona':
            return t('Define the assistant identity, greeting, and avatar shown to users.')
        case 'access':
            return t('Limit who can use the assistant and how many messages they can send.')
        case 'prompts':
            return t('Configure server-side prompts used for frontend and admin conversations.')
        case 'automations':
            return t('Create instant replies for common questions before an AI request is made.')
        default:
            return ''
    }
})

const groupedSettings = (group: TabId) => addonSettings.value.filter((setting) => (setting.group ?? 'general') === group)

const tabSettingOrder: Record<TabId, string[]> = {
    general: ['enabled', 'admin_enabled', 'position', 'widget_icon', 'accent_color', 'auto_open', 'allow_file_upload', 'allowed_file_types'],
    ai: ['provider', 'model', 'max_tokens', 'temperature', 'custom_api_key'],
    persona: ['assistant_name', 'designation', 'avatar_url', 'greeting_message'],
    access: ['show_to', 'daily_message_limit'],
    prompts: ['system_prompt_frontend', 'system_prompt_admin'],
    automations: [],
}

function orderedSettings(group: TabId) {
    const settings = groupedSettings(group)
    const order = tabSettingOrder[group]

    return [...settings].sort((left, right) => {
        const leftIndex = order.indexOf(left.key)
        const rightIndex = order.indexOf(right.key)
        const normalizedLeft = leftIndex === -1 ? Number.MAX_SAFE_INTEGER : leftIndex
        const normalizedRight = rightIndex === -1 ? Number.MAX_SAFE_INTEGER : rightIndex

        return normalizedLeft - normalizedRight
    })
}

function fieldSpanClass(setting: AddonSetting) {
    if (setting.type === 'encrypted') {
        return 'md:col-span-2'
    }

    if (setting.key.startsWith('system_prompt_')) {
        return ''
    }

    return ''
}

const generalSwitchKeys = ['enabled', 'admin_enabled', 'auto_open', 'allow_file_upload']

const generalSwitchSettings = computed(() => {
    return orderedSettings('general').filter((setting) => generalSwitchKeys.includes(setting.key))
})

const generalNonSwitchSettings = computed(() => {
    return orderedSettings('general').filter((setting) => !generalSwitchKeys.includes(setting.key))
})

function setValue(key: string, value: AddonSetting['value']) {
    const setting = addonSettings.value.find((item) => item.key === key)

    if (setting) {
        setting.value = value
    }
}

function toggleBoolean(key: string) {
    const setting = addonSettings.value.find((item) => item.key === key)

    if (setting) {
        setValue(key, !Boolean(setting.value))
    }
}

function formatOptionLabel(option: string) {
    return t(option.replace(/[-_]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()))
}

function getFileUrl(value: AddonSetting['value']) {
    if (!value) {
        return ''
    }

    if (typeof value === 'string') {
        if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/storage/')) {
            return value
        }

        return `/storage/${value}`
    }

    if (value instanceof File) {
        return URL.createObjectURL(value)
    }

    return ''
}

function handleFileChange(key: string, event: Event) {
    const files = (event.target as HTMLInputElement).files

    if (files?.length) {
        setValue(key, files[0])
    }
}

function removeFile(key: string) {
    setValue(key, null)
}

function handleProviderChange(value: string | number) {
    const normalizedValue = String(value)
    setValue('provider', normalizedValue)

    const currentModel = String(addonSettings.value.find((setting) => setting.key === 'model')?.value ?? '')
    if (!currentModel) {
        return
    }

    const nextProvider = normalizedValue || defaultProvider.value
    const isValid = aiModels.value.some((model) => model.value === currentModel && model.provider === nextProvider)

    if (!isValid) {
        setValue('model', '')
    }
}

function save() {
    saving.value = true
    saved.value = false

    router.post(route('admin.addons.settings.save', { slug: addonSlug.value }), form.value, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true
            window.setTimeout(() => {
                saved.value = false
            }, 3000)
        },
        onFinish: () => {
            saving.value = false
        },
    })
}

function openCreateModal() {
    isEditing.value = false
    currentRuleId.value = null
    ruleForm.value = {
        trigger: '',
        response: '',
        match_type: 'contains',
        is_active: true,
    }
    showModal.value = true
}

function openEditModal(rule: AutomationRule) {
    isEditing.value = true
    currentRuleId.value = rule.id
    ruleForm.value = {
        trigger: rule.trigger,
        response: rule.response,
        match_type: rule.match_type,
        is_active: Boolean(rule.is_active),
    }
    showModal.value = true
}

function closeRuleModal() {
    if (processingRule.value) {
        return
    }

    showModal.value = false
}

async function saveRule() {
    if (!ruleForm.value.trigger || !ruleForm.value.response) {
        return
    }

    processingRule.value = true

    try {
        const url = isEditing.value
            ? route('addon.ai-assistant.admin.rules.update', { rule: currentRuleId.value })
            : route('addon.ai-assistant.admin.rules.store')

        const response = await fetch(url, {
            method: isEditing.value ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(ruleForm.value),
        })

        if (response.ok) {
            showModal.value = false
            router.reload({ only: ['rules'] })
        }
    } finally {
        processingRule.value = false
    }
}

function requestDeleteRule(id: number) {
    deletingRuleId.value = id
}

async function confirmDeleteRule() {
    if (!deletingRuleId.value) {
        return
    }

    deletingRule.value = true

    try {
        const response = await fetch(route('addon.ai-assistant.admin.rules.delete', { rule: deletingRuleId.value }), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
        })

        if (response.ok) {
            deletingRuleId.value = null
            router.reload({ only: ['rules'] })
        }
    } finally {
        deletingRule.value = false
    }
}

async function toggleRuleActive(rule: AutomationRule) {
    await fetch(route('addon.ai-assistant.admin.rules.update', { rule: rule.id }), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
            trigger: rule.trigger,
            response: rule.response,
            match_type: rule.match_type,
            is_active: !rule.is_active,
        }),
    })

    router.reload({ only: ['rules'] })
}

function inputClasses() {
    return 'block w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white'
}
</script>

<template>
    <div class="mx-auto w-full sm:max-w-7xl">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Assistant Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure the floating AI assistant widget for frontend and admin usage.') }}</p>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="saving"
                @click="save"
            >
                <i v-if="saving" class="ti ti-loader-2 animate-spin text-base"></i>
                <i v-else class="ti ti-device-floppy text-base"></i>
                {{ saving ? t('Saving...') : saved ? t('Saved!') : t('Save Settings') }}
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ currentTabTitle }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ currentTabDescription }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="activeTab === tab.id
                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:bg-gray-900/60 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                <div v-show="activeTab === 'general'" class="space-y-6">
                    <div v-if="generalSwitchSettings.length" class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="setting in generalSwitchSettings"
                            :key="setting.key"
                            class="rounded-xl border border-gray-100 p-4 dark:border-gray-800"
                        >
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <label class="text-sm font-medium text-gray-900 dark:text-white">{{ setting.label }}</label>
                                    <p v-if="setting.default !== null && setting.default !== undefined" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Default') }}: {{ setting.default }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                                    :class="Boolean(setting.value) ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'"
                                    @click="toggleBoolean(setting.key)"
                                >
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="Boolean(setting.value) ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                    <template v-for="setting in generalNonSwitchSettings" :key="setting.key">
                        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800" :class="fieldSpanClass(setting)">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="md:max-w-sm">
                                    <label class="text-sm font-medium text-gray-900 dark:text-white">{{ setting.label }}</label>
                                    <p v-if="setting.default !== null && setting.default !== undefined && setting.type !== 'boolean'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Default') }}: {{ setting.default }}
                                    </p>
                                </div>

                                <div class="w-full md:max-w-md">
                                    <AppSelect
                                        v-if="setting.type === 'select'"
                                        :model-value="String(setting.value ?? '')"
                                        :options="(setting.options ?? []).map((option) => ({ value: option, label: formatOptionLabel(option) }))"
                                        @update:model-value="setValue(setting.key, String($event))"
                                    />

                                    <AppColorPicker
                                        v-else-if="setting.type === 'color'"
                                        :model-value="String(setting.value ?? '#1F75FE')"
                                        @update:model-value="setValue(setting.key, $event)"
                                    />

                                    <AppIconSelect
                                        v-else-if="setting.type === 'icon'"
                                        :model-value="String(setting.value ?? 'ti ti-robot')"
                                        @update:model-value="setValue(setting.key, $event)"
                                    />

                                    <input
                                        v-else
                                        type="text"
                                        :class="inputClasses()"
                                        :value="String(setting.value ?? '')"
                                        :placeholder="setting.default === null ? '' : String(setting.default)"
                                        @change="setValue(setting.key, ($event.target as HTMLInputElement).value)"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                    </div>

                    <div v-if="!groupedSettings('general').length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No settings in this section.') }}
                    </div>
                </div>

                <div v-show="activeTab === 'ai'" class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                    <template v-for="setting in orderedSettings('ai')" :key="setting.key">
                        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800" :class="fieldSpanClass(setting)">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="md:max-w-sm">
                                    <label class="text-sm font-medium text-gray-900 dark:text-white">{{ setting.label }}</label>
                                    <p v-if="setting.default !== null && setting.default !== undefined" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Default') }}: {{ setting.default }}
                                    </p>
                                </div>

                                <div class="w-full md:max-w-md">
                                    <AppSelect
                                        v-if="setting.key === 'provider'"
                                        :model-value="String(setting.value ?? '')"
                                        :options="providerOptions"
                                        :placeholder="t('Select a provider')"
                                        @update:model-value="handleProviderChange"
                                    />

                                    <AppSelect
                                        v-else-if="setting.key === 'model'"
                                        :model-value="String(setting.value ?? '')"
                                        :options="filteredAiModels"
                                        :placeholder="t('Select a model')"
                                        @update:model-value="setValue(setting.key, String($event))"
                                    />

                                    <input
                                        v-else-if="setting.type === 'integer'"
                                        type="number"
                                        :class="inputClasses()"
                                        :value="Number(setting.value ?? setting.default ?? 0)"
                                        @change="setValue(setting.key, parseInt(($event.target as HTMLInputElement).value, 10) || 0)"
                                    />

                                    <input
                                        v-else
                                        type="text"
                                        :class="inputClasses()"
                                        :value="String(setting.value ?? '')"
                                        :placeholder="setting.default === null ? '' : String(setting.default)"
                                        @change="setValue(setting.key, ($event.target as HTMLInputElement).value)"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                    </div>

                    <div v-if="!groupedSettings('ai').length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No settings in this section.') }}
                    </div>
                </div>

                <div v-show="activeTab === 'persona'" class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                    <template v-for="setting in orderedSettings('persona')" :key="setting.key">
                        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800" :class="fieldSpanClass(setting)">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="md:max-w-sm">
                                    <label class="text-sm font-medium text-gray-900 dark:text-white">{{ setting.label }}</label>
                                    <p v-if="setting.default !== null && setting.default !== undefined && setting.type !== 'file'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Default') }}: {{ setting.default }}
                                    </p>
                                </div>

                                <div class="w-full md:max-w-md">
                                    <textarea
                                        v-if="setting.type === 'textarea'"
                                        rows="4"
                                        :class="inputClasses()"
                                        :value="String(setting.value ?? '')"
                                        :placeholder="setting.default === null ? '' : String(setting.default)"
                                        @change="setValue(setting.key, ($event.target as HTMLTextAreaElement).value)"
                                    />

                                    <template v-else-if="setting.type === 'file'">
                                        <div v-if="setting.value" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                            <div class="flex items-center gap-4">
                                                <img :src="getFileUrl(setting.value)" :alt="t('Avatar preview')" class="h-16 w-16 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                                                <div class="min-w-0">
                                                    <p v-if="setting.value instanceof File" class="truncate text-xs text-gray-500 dark:text-gray-400">
                                                        {{ setting.value.name }} ({{ (setting.value.size / 1024).toFixed(1) }} KB)
                                                    </p>
                                                    <button
                                                        type="button"
                                                        class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100 dark:border-red-900/30 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50"
                                                        @click="removeFile(setting.key)"
                                                    >
                                                        {{ t('Remove') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <input
                                            v-else
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp,image/gif"
                                            :class="inputClasses()"
                                            @change="handleFileChange(setting.key, $event)"
                                        />
                                    </template>

                                    <input
                                        v-else
                                        type="text"
                                        :class="inputClasses()"
                                        :value="String(setting.value ?? '')"
                                        :placeholder="setting.default === null ? '' : String(setting.default)"
                                        @change="setValue(setting.key, ($event.target as HTMLInputElement).value)"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                    </div>

                    <div v-if="!groupedSettings('persona').length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No settings in this section.') }}
                    </div>
                </div>

                <div v-show="activeTab === 'access'" class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                    <template v-for="setting in orderedSettings('access')" :key="setting.key">
                        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800" :class="fieldSpanClass(setting)">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="md:max-w-sm">
                                    <label class="text-sm font-medium text-gray-900 dark:text-white">{{ setting.label }}</label>
                                    <p v-if="setting.default !== null && setting.default !== undefined && setting.type !== 'select'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Default') }}: {{ setting.default }}
                                    </p>
                                </div>

                                <div class="w-full md:max-w-md">
                                    <AppSelect
                                        v-if="setting.type === 'select'"
                                        :model-value="String(setting.value ?? '')"
                                        :options="(setting.options ?? []).map((option) => ({ value: option, label: formatOptionLabel(option) }))"
                                        @update:model-value="setValue(setting.key, String($event))"
                                    />

                                    <input
                                        v-else-if="setting.type === 'integer'"
                                        type="number"
                                        :class="inputClasses()"
                                        :value="Number(setting.value ?? setting.default ?? 0)"
                                        @change="setValue(setting.key, parseInt(($event.target as HTMLInputElement).value, 10) || 0)"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                    </div>

                    <div v-if="!groupedSettings('access').length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No settings in this section.') }}
                    </div>
                </div>

                <div v-show="activeTab === 'prompts'" class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                    <template v-for="setting in orderedSettings('prompts')" :key="setting.key">
                        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800" :class="fieldSpanClass(setting)">
                            <div class="mb-3">
                                <label class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ setting.label }}
                                </label>
                            </div>

                            <textarea
                                rows="8"
                                :class="`${inputClasses()} font-mono`"
                                :value="String(setting.value ?? '')"
                                @change="setValue(setting.key, ($event.target as HTMLTextAreaElement).value)"
                            />
                        </div>
                    </template>
                    </div>

                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 dark:border-blue-900/30 dark:bg-blue-950/20">
                        <div class="flex gap-3">
                            <i class="ti ti-info-circle text-xl text-blue-600 dark:text-blue-400"></i>
                            <div>
                                <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-300">{{ t('Available Template Variables') }}</h3>
                                <p class="mt-1 text-xs leading-relaxed text-blue-700 dark:text-blue-400">
                                    {{ t('Use these placeholders in prompts and they will be replaced with live values at runtime.') }}
                                </p>
                                <div class="mt-3 grid gap-3 text-xs md:grid-cols-2">
                                    <div class="flex gap-2">
                                        <code class="rounded bg-blue-100/80 px-1.5 py-0.5 font-mono font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">{site_name}</code>
                                        <span class="text-gray-600 dark:text-gray-400">{{ t('The name of your site') }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <code class="rounded bg-blue-100/80 px-1.5 py-0.5 font-mono font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">{user_name}</code>
                                        <span class="text-gray-600 dark:text-gray-400">{{ t("Current user's name or Guest") }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <code class="rounded bg-blue-100/80 px-1.5 py-0.5 font-mono font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">{user_email}</code>
                                        <span class="text-gray-600 dark:text-gray-400">{{ t("Current user's email or Guest Email") }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <code class="rounded bg-blue-100/80 px-1.5 py-0.5 font-mono font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">{current_page}</code>
                                        <span class="text-gray-600 dark:text-gray-400">{{ t('Current page URL or path') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!groupedSettings('prompts').length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No settings in this section.') }}
                    </div>
                </div>

                <div v-show="activeTab === 'automations'" class="space-y-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Message Automation Rules') }}</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Predefine responses for common user messages. Matching rules reply instantly without calling the AI model.') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary px-4 py-2 text-sm font-medium text-white"
                            @click="openCreateModal"
                        >
                            <i class="ti ti-plus text-base"></i>
                            {{ t('Add Rule') }}
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-100 dark:border-gray-800">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">{{ t('Trigger Pattern') }}</th>
                                        <th class="px-6 py-3">{{ t('Match Mode') }}</th>
                                        <th class="px-6 py-3">{{ t('Instant Response') }}</th>
                                        <th class="px-6 py-3 text-center">{{ t('Status') }}</th>
                                        <th class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-800">
                                    <tr v-for="rule in rules" :key="rule.id" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                        <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-900 dark:text-white">
                                            "{{ rule.trigger }}"
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="inline-flex rounded-md px-2 py-1 text-xs font-medium"
                                                :class="rule.match_type === 'exact'
                                                    ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300'
                                                    : 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300'"
                                            >
                                                {{ rule.match_type === 'exact' ? t('Exact') : t('Contains') }}
                                            </span>
                                        </td>
                                        <td class="max-w-xs px-6 py-4 text-gray-700 dark:text-gray-300">
                                            <p class="truncate">{{ rule.response }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button
                                                type="button"
                                                class="relative inline-flex h-6 w-11 rounded-full transition"
                                                :class="rule.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'"
                                                @click="toggleRuleActive(rule)"
                                            >
                                                <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="rule.is_active ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <Tooltip :content="t('Edit')" placement="top">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                                        :aria-label="t('Edit')"
                                                        @click="openEditModal(rule)"
                                                    >
                                                        <i class="ti ti-edit text-base"></i>
                                                    </button>
                                                </Tooltip>
                                                <Tooltip :content="t('Delete')" placement="top">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-500 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                                        :aria-label="t('Delete')"
                                                        @click="requestDeleteRule(rule.id)"
                                                    >
                                                        <i class="ti ti-trash text-base"></i>
                                                    </button>
                                                </Tooltip>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="!hasRules">
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center gap-2">
                                                <i class="ti ti-messages text-3xl text-gray-300 dark:text-gray-600"></i>
                                                <span>{{ t('No automation rules created yet.') }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ActionConfirmModal
        :open="deletingRuleId !== null"
        :title="t('Delete automation rule?')"
        :message="t('This automation rule will be removed permanently.')"
        :confirm-label="t('Delete')"
        :processing-label="t('Deleting...')"
        :processing="deletingRule"
        variant="danger"
        @cancel="deletingRuleId = null"
        @confirm="confirmDeleteRule"
    />

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="closeRuleModal">
        <div class="flex max-h-[90vh] w-full sm:max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ isEditing ? t('Edit Automation Rule') : t('Create Automation Rule') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Define a trigger phrase and an instant reply before the AI request runs.') }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    :disabled="processingRule"
                    @click="closeRuleModal"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <div class="space-y-4 overflow-y-auto p-5">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Trigger / Question Phrase') }}</label>
                    <input
                        v-model="ruleForm.trigger"
                        type="text"
                        :placeholder="t('e.g. hello, pricing, support')"
                        :class="inputClasses()"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Matching Mode') }}</label>
                    <AppSelect
                        v-model="ruleForm.match_type"
                        :options="[
                            { value: 'contains', label: t('Contains') },
                            { value: 'exact', label: t('Exact Match') },
                        ]"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Instant Predefined Reply') }}</label>
                    <textarea
                        v-model="ruleForm.response"
                        rows="4"
                        :placeholder="t('Enter your instant answer...')"
                        :class="inputClasses()"
                    />
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Supported variables') }}:
                        <code class="font-mono text-primary-600 dark:text-primary-400">{site_name}</code>,
                        <code class="font-mono text-primary-600 dark:text-primary-400">{user_name}</code>,
                        <code class="font-mono text-primary-600 dark:text-primary-400">{current_page}</code>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="relative inline-flex h-6 w-11 rounded-full transition"
                        :class="ruleForm.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'"
                        @click="ruleForm.is_active = !ruleForm.is_active"
                    >
                        <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="ruleForm.is_active ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Enabled') }}</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                <button
                    type="button"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    :disabled="processingRule"
                    @click="closeRuleModal"
                >
                    {{ t('Cancel') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="processingRule || !ruleForm.trigger || !ruleForm.response"
                    @click="saveRule"
                >
                    <i v-if="processingRule" class="ti ti-loader-2 animate-spin text-base"></i>
                    {{ processingRule ? (isEditing ? t('Saving...') : t('Creating...')) : (isEditing ? t('Save Rule') : t('Create Rule')) }}
                </button>
            </div>
        </div>
    </div>
</template>
