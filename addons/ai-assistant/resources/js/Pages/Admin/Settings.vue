<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@inertiajs/core'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'

defineOptions({ layout: AdminLayout })

type TabId = 'general' | 'persona' | 'ai' | 'access' | 'prompts' | 'automations'

interface AddonSetting {
    key: string
    type: string
    label: string
    value: string | number | boolean | File | null
    default: string | number | boolean | null
    group?: string | null
    options?: string[]
    description?: string | null
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
    /** DISPLAY name, e.g. "Google Gemini" — not a slug. Don't compare against it. */
    provider: string
    /** Raw provider slug, e.g. "google". This is what settings store. */
    provider_slug?: string
}

interface AiProviderOption {
    value: string
    label: string
}

interface AssistantMeta {
    pro_available: boolean
    quota_mode: boolean
    kb_installed: boolean
    /** The site-wide AI defaults the assistant falls back to when its own are empty. */
    default_provider: string
    default_model: string
}

type PagePropsShape = PageProps & {
    settings?: AddonSetting[]
    rules?: AutomationRule[]
    addon?: { slug?: string }
    aiModels?: AiModelOption[]
    aiProviders?: AiProviderOption[]
    aiAssistantMeta?: AssistantMeta | null
}

const { t } = useTranslate()
const page = usePage<PagePropsShape>()

/*
 | Each tab maps to one or more manifest groups. The old "General" and "Widget" tabs were
 | split arbitrarily — behaviour in one, panels in the other — so they're merged here and
 | the options regrouped by what an operator is actually trying to do.
 */
const TAB_GROUPS: Record<TabId, string[]> = {
    general: ['general', 'panels', 'uploads', 'channels', 'legal'],
    // Persona is the assistant's own identity only — social links and the privacy note are
    // properties of the SITE, not of the assistant's character, so they live under General.
    persona: ['persona'],
    ai: ['ai'],
    access: ['access'],
    prompts: ['prompts'],
    automations: [],
}

/** Explicit order within a tab; anything unlisted falls to the end. */
const SETTING_ORDER: string[] = [
    // general
    'enabled', 'admin_enabled', 'auto_open', 'greeting_on_first_visit', 'position', 'accent_color',
    // panels
    'enable_home', 'enable_help', 'enable_message', 'enable_slash_commands',
    'enable_knowledge_base', 'enable_csat', 'enable_emoji',
    // uploads
    'allow_file_upload', 'allowed_file_types', 'max_upload_size_mb',
    // persona
    'assistant_name', 'designation', 'avatar_url', 'greeting_message',
    // channels
    'social_whatsapp', 'social_telegram', 'social_facebook', 'social_instagram', 'social_website',
    // legal
    'show_legal_note', 'privacy_url', 'terms_url',
    // ai
    'provider', 'model', 'max_tokens', 'temperature',
    // access
    'show_to', 'guest_daily_message_limit', 'daily_message_limit', 'pro_daily_message_limit',
    // prompts
    'system_prompt_frontend', 'system_prompt_admin',
]

/** Section headings inside a tab, so a long list reads as related blocks. */
const GROUP_LABELS: Record<string, string> = {
    general: 'Widget',
    panels: 'Panels & Features',
    uploads: 'File Uploads',
    persona: 'Identity',
    channels: 'Social Channels',
    legal: 'Privacy & Terms',
    ai: 'Model',
    access: 'Access',
    prompts: 'System Prompts',
}

const tabs: Array<{ id: TabId; label: string; description: string }> = [
    { id: 'general', label: t('General'), description: t('Where the widget appears, which panels it offers, uploads, social channels and the privacy note.') },
    { id: 'persona', label: t('Persona'), description: t('The assistant’s name, avatar and greeting.') },
    { id: 'ai', label: t('AI Model'), description: t('Which provider and model answer, and how long replies may be.') },
    { id: 'access', label: t('Access'), description: t('Who may use the assistant and how many messages they get per day.') },
    { id: 'prompts', label: t('System Prompts'), description: t('Server-side instructions that shape every reply. Never shown to users.') },
    { id: 'automations', label: t('Message Automation'), description: t('Instant canned replies for common questions, answered without calling the AI.') },
]

const activeTab = ref<TabId>('general')
const saving = ref(false)
const saved = ref(false)

const addonSettings = computed(() => page.props.settings ?? [])
const rules = computed(() => page.props.rules ?? [])
const addonSlug = computed(() => page.props.addon?.slug ?? 'ai-assistant')
const aiModels = computed(() => page.props.aiModels ?? [])

const meta = computed<AssistantMeta>(() => page.props.aiAssistantMeta ?? {
    pro_available: true,
    quota_mode: false,
    kb_installed: true,
    default_provider: 'openai',
    default_model: '',
})

const currentTab = computed(() => tabs.find((tab) => tab.id === activeTab.value))

const form = computed<Record<string, AddonSetting['value']>>(() => {
    const data: Record<string, AddonSetting['value']> = {}
    for (const setting of addonSettings.value) {
        data[setting.key] = setting.value
    }
    return data
})

// ─── setting lookup / ordering ───────────────────────────────

function settingByKey(key: string): AddonSetting | undefined {
    return addonSettings.value.find((setting) => setting.key === key)
}

/**
 * Settings that can't work on this install are hidden rather than offered as dead knobs.
 * The backend enforces the same conditions independently.
 */
function isApplicable(key: string): boolean {
    switch (key) {
        case 'pro_daily_message_limit':
            return meta.value.pro_available
        case 'enable_help':
        case 'enable_knowledge_base':
            return meta.value.kb_installed
        default:
            return true
    }
}

function groupSettings(group: string): AddonSetting[] {
    return addonSettings.value
        .filter((setting) => (setting.group ?? 'general') === group)
        .filter((setting) => isApplicable(setting.key))
        // provider/model get a bespoke pair of selects, so they're excluded from the
        // generic renderer and drawn by hand in the AI tab.
        .filter((setting) => !['provider', 'model'].includes(setting.key))
        .sort((a, b) => {
            const ai = SETTING_ORDER.indexOf(a.key)
            const bi = SETTING_ORDER.indexOf(b.key)
            return (ai === -1 ? 1e9 : ai) - (bi === -1 ? 1e9 : bi)
        })
}

/** Groups on this tab that actually have something to show. */
const visibleGroups = computed(() =>
    TAB_GROUPS[activeTab.value].filter((group) => groupSettings(group).length > 0),
)

// ─── mutation ────────────────────────────────────────────────

function setValue(key: string, value: AddonSetting['value']) {
    const setting = settingByKey(key)
    if (setting) setting.value = value
}

function boolValue(setting: AddonSetting): boolean {
    return Boolean(setting.value)
}

/** Fields that need the full width of the two-column grid to be usable. */
function isWideField(setting: AddonSetting): boolean {
    return setting.type === 'textarea' || setting.type === 'file'
}

function formatOptionLabel(option: string) {
    return t(option.replace(/[-_]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()))
}

/** `pro_only` is meaningless without a sellable paid tier, so don't offer it. */
function settingOptions(setting: AddonSetting) {
    return (setting.options ?? [])
        .filter((option) => !(setting.key === 'show_to' && option === 'pro_only' && !meta.value.pro_available))
        .map((option) => ({ value: option, label: formatOptionLabel(option) }))
}

// ─── AI provider / model ─────────────────────────────────────

const providerSetting = computed(() => settingByKey('provider'))
const modelSetting = computed(() => settingByKey('model'))

const selectedProvider = computed(() => String(providerSetting.value?.value ?? ''))

/**
 * The provider whose models the dropdown should list.
 *
 * When the addon's own provider is empty the assistant falls back to the SITE default, so
 * that's what the model list must be filtered by. It used to fall back to a hardcoded
 * 'openai', which meant that on any install defaulting to another provider (this one
 * defaults to openrouter) the model list was filtered against a provider that had no active
 * models — so the dropdown came up empty and no model could be picked.
 */
const activeProvider = computed(() => selectedProvider.value || meta.value.default_provider)

const providerOptions = computed(() => [
    { value: '', label: t('Use the site default (:provider)', { provider: meta.value.default_provider }) },
    ...(page.props.aiProviders ?? []),
])

/**
 * The `provider` field on a shared model is a DISPLAY name ("Google Gemini", "OpenRouter"),
 * while settings store a slug ("google", "openrouter"). Comparing the two matched nothing,
 * which is why the model list came up empty even with 17 models available. Match on the
 * slug, falling back to a case-insensitive compare for any payload that predates it.
 */
function modelProviderMatches(model: AiModelOption, provider: string): boolean {
    if (model.provider_slug) return model.provider_slug === provider
    return model.provider.toLowerCase() === provider.toLowerCase()
}

const modelOptions = computed(() => [
    { value: '', label: t('Use the site default model') },
    ...aiModels.value
        .filter((model) => modelProviderMatches(model, activeProvider.value))
        .map((model) => ({ value: model.value, label: model.label })),
])

function normalizeSelect(value: string | number | (string | number)[] | null): string {
    return Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '')
}

/** Changing provider clears a model that doesn't belong to it, so the pair can't drift. */
function onProviderChange(value: string | number | (string | number)[] | null) {
    const next = normalizeSelect(value)
    setValue('provider', next)

    const currentModel = String(modelSetting.value?.value ?? '')
    if (!currentModel) return

    const provider = next || meta.value.default_provider
    const stillValid = aiModels.value.some(
        (m) => m.value === currentModel && modelProviderMatches(m, provider),
    )
    if (!stillValid) setValue('model', '')
}

// ─── avatar ──────────────────────────────────────────────────

function isFileValue(value: AddonSetting['value']): value is File {
    return typeof File !== 'undefined' && value instanceof File
}

function getFileUrl(value: AddonSetting['value']): string {
    if (!value) return ''

    if (typeof value === 'string') {
        return mediaUrl(value)
    }

    return isFileValue(value) ? URL.createObjectURL(value) : ''
}

function fileMeta(value: AddonSetting['value']): string {
    if (!isFileValue(value)) return ''
    return `${value.name} (${(value.size / 1024).toFixed(1)} KB)`
}

function handleFileChange(key: string, event: Event) {
    const files = (event.target as HTMLInputElement).files
    if (files?.length) setValue(key, files[0])
}

// ─── save ────────────────────────────────────────────────────

function save() {
    saving.value = true
    saved.value = false

    router.post(route('admin.addons.settings.save', { slug: addonSlug.value }), form.value, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true
            window.setTimeout(() => { saved.value = false }, 3000)
        },
        onFinish: () => { saving.value = false },
    })
}

// ─── automation rules ────────────────────────────────────────

const showRuleModal = ref(false)
const isEditingRule = ref(false)
const currentRuleId = ref<number | null>(null)
const processingRule = ref(false)
const pendingDeleteRule = ref<AutomationRule | null>(null)
const deletingRule = ref(false)

const ruleForm = ref<{ trigger: string; response: string; match_type: 'contains' | 'exact'; is_active: boolean }>({
    trigger: '',
    response: '',
    match_type: 'contains',
    is_active: true,
})

const ruleValid = computed(() => Boolean(ruleForm.value.trigger.trim() && ruleForm.value.response.trim()))

const matchTypeOptions = computed(() => [
    { value: 'contains', label: t('Contains — fires when the message includes the trigger') },
    { value: 'exact', label: t('Exact — fires only on an exact match') },
])

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function openCreateRule() {
    isEditingRule.value = false
    currentRuleId.value = null
    ruleForm.value = { trigger: '', response: '', match_type: 'contains', is_active: true }
    showRuleModal.value = true
}

function openEditRule(rule: AutomationRule) {
    isEditingRule.value = true
    currentRuleId.value = rule.id
    ruleForm.value = {
        trigger: rule.trigger,
        response: rule.response,
        match_type: rule.match_type,
        is_active: Boolean(rule.is_active),
    }
    showRuleModal.value = true
}

function closeRuleModal() {
    if (!processingRule.value) showRuleModal.value = false
}

async function saveRule() {
    if (!ruleValid.value) return

    processingRule.value = true

    try {
        const url = isEditingRule.value
            ? route('addon.ai-assistant.admin.rules.update', { rule: currentRuleId.value })
            : route('addon.ai-assistant.admin.rules.store')

        const response = await fetch(url, {
            method: isEditingRule.value ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(ruleForm.value),
        })

        if (response.ok) {
            showRuleModal.value = false
            router.reload({ only: ['rules'] })
        }
    } finally {
        processingRule.value = false
    }
}

async function confirmDeleteRule() {
    const rule = pendingDeleteRule.value
    if (!rule) return

    deletingRule.value = true

    try {
        const response = await fetch(route('addon.ai-assistant.admin.rules.delete', { rule: rule.id }), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf() },
        })

        if (response.ok) {
            pendingDeleteRule.value = null
            router.reload({ only: ['rules'] })
        }
    } finally {
        deletingRule.value = false
    }
}

async function toggleRuleActive(rule: AutomationRule) {
    await fetch(route('addon.ai-assistant.admin.rules.update', { rule: rule.id }), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify({
            trigger: rule.trigger,
            response: rule.response,
            match_type: rule.match_type,
            is_active: !rule.is_active,
        }),
    })

    router.reload({ only: ['rules'] })
}

const inputClass = 'block w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white'
</script>

<template>
    <div class="mx-auto w-full max-w-5xl px-4 sm:px-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Assistant') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Configure the assistant widget shown on your site and in the admin panel.') }}
                </p>
            </div>

            <button
                type="button"
                class="btn-primary-admin inline-flex shrink-0 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="saving"
                @click="save"
            >
                <i v-if="saving" class="ti ti-loader-2 animate-spin text-base"></i>
                <i v-else-if="saved" class="ti ti-check text-base"></i>
                <i v-else class="ti ti-device-floppy text-base"></i>
                {{ saving ? t('Saving...') : saved ? t('Saved!') : t('Save Settings') }}
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card dark:border-surface-700 dark:bg-surface-900">
            <!-- Tabs -->
            <div class="border-b border-gray-100 px-5 pt-4 dark:border-surface-800">
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                        :class="activeTab === tab.id
                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <p class="px-1 pb-4 pt-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ currentTab?.description }}
                </p>
            </div>

            <div class="p-5 sm:p-6">
                <!-- ── Settings tabs (generic renderer) ───────────────────── -->
                <div v-if="activeTab !== 'automations'" class="space-y-8">
                    <!-- Access gets a plain-language explanation: the interaction between
                         "who can use it" and the three daily limits is not self-evident. -->
                    <div
                        v-if="activeTab === 'access'"
                        class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-200"
                    >
                        <p class="font-medium">{{ t('How limits work') }}</p>
                        <ul class="mt-2 space-y-1 text-[13px] leading-relaxed">
                            <li>• {{ t('Each visitor gets one daily allowance, matched to who they are. It resets at midnight.') }}</li>
                            <li>• {{ t('Guests are counted per IP address; signed-in users are counted per account.') }}</li>
                            <li>• {{ t('Set a limit to 0 for unlimited messages.') }}</li>
                            <li v-if="!meta.pro_available">
                                • {{ t('The Pro tier is hidden because subscriptions are not enabled on this install.') }}
                            </li>
                        </ul>
                    </div>

                    <section v-for="group in visibleGroups" :key="group">
                        <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            {{ t(GROUP_LABELS[group] ?? group) }}
                        </h3>

                        <div class="grid gap-5 md:grid-cols-2">
                            <template v-for="setting in groupSettings(group)" :key="setting.key">
                                <!-- Switch -->
                                <div
                                    v-if="setting.type === 'boolean'"
                                    class="flex items-start justify-between gap-4 rounded-xl border border-gray-100 p-4 dark:border-gray-800"
                                >
                                    <div class="min-w-0">
                                        <label class="text-sm font-medium text-gray-900 dark:text-white">{{ t(setting.label) }}</label>
                                        <p v-if="setting.description" class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                            {{ t(setting.description) }}
                                        </p>
                                    </div>

                                    <AppSwitch
                                        :model-value="boolValue(setting)"
                                        :aria-label="t(setting.label)"
                                        @update:model-value="setValue(setting.key, $event)"
                                    />
                                </div>

                                <!-- Everything else: label, then description, then the field -->
                                <div v-else :class="isWideField(setting) ? 'md:col-span-2' : ''">
                                    <label class="block text-sm font-medium text-gray-900 dark:text-white">
                                        {{ t(setting.label) }}
                                    </label>
                                    <p v-if="setting.description" class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                        {{ t(setting.description) }}
                                    </p>

                                    <div class="mt-2">
                                        <AppSelect
                                            v-if="setting.type === 'select'"
                                            :model-value="String(setting.value ?? '')"
                                            :options="settingOptions(setting)"
                                            @update:model-value="setValue(setting.key, normalizeSelect($event))"
                                        />

                                        <AppColorPicker
                                            v-else-if="setting.type === 'color'"
                                            :model-value="String(setting.value ?? '#1F75FE')"
                                            @update:model-value="setValue(setting.key, $event)"
                                        />

                                        <textarea
                                            v-else-if="setting.type === 'textarea'"
                                            rows="5"
                                            :class="inputClass"
                                            :value="String(setting.value ?? '')"
                                            :placeholder="setting.default === null ? '' : String(setting.default)"
                                            @change="setValue(setting.key, ($event.target as HTMLTextAreaElement).value)"
                                        ></textarea>

                                        <input
                                            v-else-if="setting.type === 'integer'"
                                            type="number"
                                            min="0"
                                            :class="inputClass"
                                            :value="Number(setting.value ?? setting.default ?? 0)"
                                            @change="setValue(setting.key, parseInt(($event.target as HTMLInputElement).value, 10) || 0)"
                                        />

                                        <!-- Avatar -->
                                        <template v-else-if="setting.type === 'file'">
                                            <div v-if="setting.value" class="mb-3 flex items-center gap-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
                                                <img
                                                    :src="getFileUrl(setting.value)"
                                                    :alt="t('Avatar preview')"
                                                    class="h-16 w-16 rounded-lg border border-gray-200 object-cover dark:border-gray-700"
                                                />
                                                <div class="min-w-0">
                                                    <p v-if="fileMeta(setting.value)" class="truncate text-xs text-gray-500 dark:text-gray-400">
                                                        {{ fileMeta(setting.value) }}
                                                    </p>
                                                    <button
                                                        type="button"
                                                        class="mt-1 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100 dark:border-red-900/30 dark:bg-red-950/30 dark:text-red-300"
                                                        @click="setValue(setting.key, null)"
                                                    >
                                                        {{ t('Remove') }}
                                                    </button>
                                                </div>
                                            </div>

                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 dark:text-gray-300 dark:file:bg-gray-800 dark:file:text-gray-200"
                                                @change="handleFileChange(setting.key, $event)"
                                            />
                                        </template>

                                        <input
                                            v-else
                                            type="text"
                                            :class="inputClass"
                                            :value="String(setting.value ?? '')"
                                            :placeholder="setting.default === null ? '' : String(setting.default)"
                                            @change="setValue(setting.key, ($event.target as HTMLInputElement).value)"
                                        />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

                    <!-- Provider + model live together: the model list depends on the provider. -->
                    <section v-if="activeTab === 'ai' && providerSetting && modelSetting">
                        <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            {{ t('Provider') }}
                        </h3>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ t('AI Provider') }}</label>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Leave on the site default unless the assistant should use a different provider than the rest of the site.') }}
                                </p>
                                <div class="mt-2">
                                    <AppSelect
                                        :model-value="selectedProvider"
                                        :options="providerOptions"
                                        :placeholder="t('Select a provider')"
                                        @update:model-value="onProviderChange"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ t('AI Model') }}</label>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Only models from the selected provider are listed. Reading text from images needs a vision-capable model.') }}
                                </p>
                                <div class="mt-2">
                                    <AppSelect
                                        :model-value="String(modelSetting.value ?? '')"
                                        :options="modelOptions"
                                        :placeholder="t('Select a model')"
                                        @update:model-value="setValue('model', normalizeSelect($event))"
                                    />
                                    <p v-if="modelOptions.length <= 1" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                        {{ t('No active models found for this provider. Add an API key and enable a chat model under AI → Models.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div v-if="!visibleGroups.length && activeTab !== 'ai'" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No settings in this section.') }}
                    </div>
                </div>

                <!-- ── Automation rules ───────────────────────────────────── -->
                <div v-else class="space-y-4">
                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="btn-primary-admin inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-white"
                            @click="openCreateRule"
                        >
                            <i class="ti ti-plus text-base"></i>
                            {{ t('Add Rule') }}
                        </button>
                    </div>

                    <div v-if="!rules.length" class="rounded-xl border border-dashed border-gray-200 py-14 text-center dark:border-gray-700">
                        <i class="ti ti-message-2-bolt mb-2 block text-3xl text-gray-300 dark:text-gray-600"></i>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ t('No automation rules yet.') }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ t('Add one to answer a common question instantly, without spending AI credits.') }}
                        </p>
                    </div>

                    <div v-else class="overflow-hidden rounded-xl border border-gray-100 dark:border-gray-800">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-3">{{ t('Trigger') }}</th>
                                        <th class="px-4 py-3">{{ t('Reply') }}</th>
                                        <th class="px-4 py-3">{{ t('Match') }}</th>
                                        <th class="px-4 py-3">{{ t('Active') }}</th>
                                        <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <tr v-for="rule in rules" :key="rule.id" class="align-top">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ rule.trigger }}</td>
                                        <td class="max-w-xs px-4 py-3 text-gray-600 dark:text-gray-300">
                                            <span class="line-clamp-2">{{ rule.response }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ t(rule.match_type) }}</td>
                                        <td class="px-4 py-3">
                                            <AppSwitch
                                                :model-value="Boolean(rule.is_active)"
                                                :aria-label="t('Active')"
                                                @update:model-value="toggleRuleActive(rule)"
                                            />
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800"
                                                :aria-label="t('Edit')"
                                                @click="openEditRule(rule)"
                                            >
                                                <i class="ti ti-pencil text-base"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                                :aria-label="t('Delete')"
                                                @click="pendingDeleteRule = rule"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rule editor -->
        <AppModal
            :open="showRuleModal"
            :title="isEditingRule ? t('Edit Rule') : t('Add Rule')"
            :subtitle="t('When a message matches the trigger, the assistant replies instantly — no AI call, no credits spent.')"
            :confirm-text="isEditingRule ? t('Save Changes') : t('Add Rule')"
            :confirm-loading="processingRule"
            :confirm-loading-text="t('Saving...')"
            :confirm-disabled="!ruleValid"
            max-width="max-w-xl"
            @close="closeRuleModal"
            @confirm="saveRule"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ t('Trigger') }}</label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('The words to look for in the user’s message. Case and punctuation are ignored.') }}
                    </p>
                    <input v-model="ruleForm.trigger" type="text" :class="[inputClass, 'mt-2']" :placeholder="t('e.g. refund policy')" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ t('Match Type') }}</label>
                    <div class="mt-2">
                        <AppSelect
                            :model-value="ruleForm.match_type"
                            :options="matchTypeOptions"
                            @update:model-value="ruleForm.match_type = normalizeSelect($event) as 'contains' | 'exact'"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ t('Reply') }}</label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Supports {site_name}, {user_name}, {user_email} and {current_page}.') }}
                    </p>
                    <textarea v-model="ruleForm.response" rows="4" :class="[inputClass, 'mt-2']" :placeholder="t('e.g. Our refund policy is 30 days.')"></textarea>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                    <label class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Active') }}</label>
                    <AppSwitch v-model="ruleForm.is_active" :aria-label="t('Active')" />
                </div>
            </div>
        </AppModal>

        <ActionConfirmModal
            :open="pendingDeleteRule !== null"
            :title="t('Delete rule?')"
            :message="t('This automation rule will be permanently removed.')"
            :confirm-label="t('Delete')"
            :processing="deletingRule"
            :processing-label="t('Deleting…')"
            variant="danger"
            @cancel="pendingDeleteRule = null"
            @confirm="confirmDeleteRule"
        />
    </div>
</template>
