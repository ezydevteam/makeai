<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface Setting {
    key: string
    label: string
    type: string
    default: unknown
    value: unknown
    options?: string[]
    description?: string
    group?: string
}

interface AddonConfig {
    name: string
    slug: string
    version: string
    description?: string
}

interface CustomModel {
    id: string
    name: string
    description: string
    model: string
    system_prompt: string
}

type FormValue = string | number | boolean | null | File | (string | number)[] | CustomModel[] | Record<string, string>

interface SettingGroup {
    key: string
    title: string
    description: string
    settings: Setting[]
}

const props = defineProps<{
    addon: AddonConfig
    settings: Setting[]
    aiModels?: Array<{ value: string; label: string; provider: string }>
    modes?: Array<{ slug: string; name: string }>
}>()

const { t } = useTranslate()

// Prepare form values, making sure custom_models is initialized as an array and mode_default_models as an object
const initialFormValues = Object.fromEntries(
    props.settings.map((setting) => {
        let val = setting.value ?? setting.default
        if (setting.key === 'custom_models') {
            if (typeof val === 'string') {
                try {
                    val = JSON.parse(val)
                } catch {
                    val = []
                }
            }
            if (!Array.isArray(val)) {
                val = []
            }
        }
        if (setting.key === 'mode_default_models') {
            if (typeof val === 'string') {
                try {
                    val = JSON.parse(val)
                } catch {
                    val = {}
                }
            }
            if (typeof val !== 'object' || val === null) {
                val = {}
            }
        }
        return [setting.key, val as FormValue]
    }),
)

const form = useForm<Record<string, FormValue>>(initialFormValues)
const formErrors = computed(() => form.errors as Record<string, string | undefined>)

const resolveBoolean = (value: unknown): boolean => {
    return value === true || value === 1 || value === '1' || value === 'true'
}

const selectOptions = (setting: Setting): Array<{ value: string; label: string }> => {
    return (setting.options ?? []).map((option) => ({
        value: option,
        label: option,
    }))
}

const getFormValue = (key: string): FormValue => form[key]
const setFormValue = (key: string, value: FormValue): void => {
    form[key] = value
}
const setBooleanValue = (key: string): void => {
    form[key] = !resolveBoolean(form[key])
}
const setFormValueByKey = (key: string, value: FormValue): void => {
    form[key] = value
}
const setNumberValue = (key: string, value: string): void => {
    form[key] = value === '' ? null : Number(value)
}

const titleCase = (value: string): string => {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (match) => match.toUpperCase())
}

const groupMeta = (groupKey: string): { title: string; description: string } => {
    const group = groupKey.toLowerCase()

    if (group === 'general') {
        return {
            title: t('General Settings'),
            description: t('Configure status, activation, and high-level routing parameters for the AI chatbot addon.'),
        }
    }
    if (group === 'ai') {
        return {
            title: t('AI Model Controls'),
            description: t('Define user permissions for model selection, custom models, and set the default AI fallback model.'),
        }
    }
    if (group === 'guests') {
        return {
            title: t('Guest Settings & Limits'),
            description: t('Configure limits and access rules for guest users visiting the chatbot.'),
        }
    }
    if (group === 'free_tier') {
        return {
            title: t('Free Tier Limits'),
            description: t('Control message cost, token limits, history size, and upload allowances for free users.'),
        }
    }
    if (group.startsWith('plan_')) {
        const planSlug = groupKey.replace('plan_', '').replace('_tier', '')
        const planName = titleCase(planSlug)
        return {
            title: t(`${planName} Plan Limits`),
            description: t(`Set the chat allowances and usage rules for the ${planName} plan.`),
        }
    }
    if (group === 'technical') {
        return {
            title: t('Technical Visibility'),
            description: t('Decide which lower-level usage details are exposed to end users inside the chatbot experience.'),
        }
    }
    return {
        title: titleCase(groupKey),
        description: t('Manage the settings in this section.'),
    }
}

// Group all settings EXCEPT custom_models and mode_default_models, which are managed by our custom UI builders
const groupedSettings = computed<SettingGroup[]>(() => {
    const groups = new Map<string, Setting[]>()

    for (const setting of props.settings) {
        if (setting.key === 'custom_models' || setting.key === 'mode_default_models') {
            continue
        }
        const rawKey = setting.group || 'general'
        const key = rawKey === 'technical' ? 'general' : rawKey
        groups.set(key, [...(groups.get(key) ?? []), setting])
    }

    return Array.from(groups.entries()).map(([key, settings]) => {
        const meta = groupMeta(key)
        return {
            key,
            title: meta.title,
            description: meta.description,
            settings,
        }
    })
})

const booleanSettings = (settings: Setting[]): Setting[] => {
    return settings.filter((setting) => setting.type === 'boolean')
}

const nonBooleanSettings = (settings: Setting[]): Setting[] => {
    return settings.filter((setting) => setting.type !== 'boolean')
}

const inlineAiSettings = (group: SettingGroup): Setting[] => {
    if (group.key !== 'ai') {
        return []
    }
    return nonBooleanSettings(group.settings).filter((setting) => setting.key === 'default_chat_model')
}

const guestToggleSetting = (group: SettingGroup): Setting | null => {
    if (group.key !== 'guests') {
        return null
    }
    return group.settings.find((setting) => setting.key === 'allow_guest_messages') ?? null
}

const guestAccessEnabled = (group: SettingGroup): boolean => {
    const setting = guestToggleSetting(group)
    return setting ? resolveBoolean(getFormValue(setting.key)) : true
}

const visibleBooleanSettings = (group: SettingGroup): Setting[] => {
    if (group.key === 'guests') {
        return booleanSettings(group.settings).filter((setting) => setting.key !== 'allow_guest_messages')
    }
    if (group.key === 'ai') {
        return booleanSettings(group.settings).filter((setting) => setting.key !== 'show_custom_models')
    }
    return booleanSettings(group.settings)
}

const stackedSettings = (group: SettingGroup): Setting[] => {
    if (group.key !== 'ai') {
        if (group.key === 'guests') {
            return nonBooleanSettings(group.settings).filter((setting) => setting.key !== 'allow_guest_messages')
        }
        return nonBooleanSettings(group.settings)
    }
    return nonBooleanSettings(group.settings).filter((setting) => setting.key !== 'default_chat_model' && setting.key !== 'mode_default_models')
}

// Custom model helper actions
const customModels = computed<CustomModel[]>({
    get: () => (form.custom_models as CustomModel[]) || [],
    set: (val) => {
        form.custom_models = val
    },
})

const addCustomModel = () => {
    const defaultBaseModel = props.aiModels?.[0]?.value || 'gpt-4o-mini'
    const newModel: CustomModel = {
        id: 'custom-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
        name: '',
        description: '',
        model: defaultBaseModel,
        system_prompt: '',
    }
    customModels.value = [...customModels.value, newModel]
}

const removeCustomModel = (index: number) => {
    const models = [...customModels.value]
    models.splice(index, 1)
    customModels.value = models
}

// Mode Specific Models computed options and actions
const modeModelOptions = computed(() => {
    const list: Array<{ value: string; label: string }> = []
    
    // Custom models first
    const cmList = (form.custom_models as CustomModel[]) || []
    for (const cm of cmList) {
        if (cm && cm.id && cm.name) {
            list.push({
                value: cm.id,
                label: `${cm.name} (${t('Custom')})`,
            })
        }
    }

    // Standard provider models
    if (props.aiModels) {
        for (const am of props.aiModels) {
            list.push({
                value: am.value,
                label: `${am.label} (${am.provider})`,
            })
        }
    }

    return list
})

const setModeModel = (modeSlug: string, modelValue: unknown): void => {
    const current = { ...(form.mode_default_models as Record<string, string>) }
    if (modelValue && typeof modelValue === 'string') {
        current[modeSlug] = modelValue
    } else if (modelValue && typeof modelValue === 'number') {
        current[modeSlug] = String(modelValue)
    } else {
        delete current[modeSlug]
    }
    form.mode_default_models = current
}

const save = () => {
    form.post(route('admin.addons.settings.save', { slug: props.addon.slug }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`${addon.name} ${t('Settings')} — Admin`" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ addon.name }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ addon.description || t('Configure chatbot options, model selection, limits, plans, and customized system models.') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.addons')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                >
                    {{ t('Back to Addons') }}
                </Link>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-lg btn-primary px-5 py-2.5 text-sm font-semibold disabled:opacity-60"
                    @click="save"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Settings') }}
                </button>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="save">
            <template v-for="group in groupedSettings" :key="group.key">
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900"
                >
                <div class="mb-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ group.title }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ group.description }}</p>
                        </div>
                        <button
                            v-if="guestToggleSetting(group)"
                            type="button"
                            role="switch"
                            :aria-checked="guestAccessEnabled(group)"
                            class="relative mt-1 inline-flex h-6 w-11 shrink-0 rounded-full transition"
                            :class="guestAccessEnabled(group) ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="setBooleanValue(guestToggleSetting(group)!.key)"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="guestAccessEnabled(group) ? 'translate-x-5' : 'translate-x-0.5'"
                            ></span>
                        </button>
                    </div>
                </div>

                <div v-if="visibleBooleanSettings(group).length || inlineAiSettings(group).length" class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="setting in visibleBooleanSettings(group)"
                        :key="setting.key"
                        class="rounded-xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-800 dark:bg-surface-800/60"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ setting.label }}</h3>
                                <p v-if="setting.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ setting.description }}</p>
                                <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Enable or disable this behavior for the chatbot.') }}</p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="resolveBoolean(getFormValue(setting.key))"
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                                :class="resolveBoolean(getFormValue(setting.key)) ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                @click="setBooleanValue(setting.key)"
                            >
                                <span
                                    class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                    :class="resolveBoolean(getFormValue(setting.key)) ? 'translate-x-5' : 'translate-x-0.5'"
                                ></span>
                            </button>
                        </div>
                        <p v-if="formErrors[setting.key]" class="mt-2 text-xs text-danger-600">{{ formErrors[setting.key] }}</p>
                    </div>

                    <div
                        v-for="setting in inlineAiSettings(group)"
                        :key="setting.key"
                        class="rounded-xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-800 dark:bg-surface-800/60"
                    >
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ setting.label }}
                            <span v-if="setting.description" class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">{{ setting.description }}</span>

                            <AppSelect
                                :model-value="String(getFormValue(setting.key) ?? '')"
                                :options="aiModels ?? []"
                                :placeholder="t('Select a model...')"
                                class="mt-3"
                                @update:model-value="setFormValue(setting.key, $event)"
                            />
                        </label>
                        <p v-if="formErrors[setting.key]" class="mt-2 text-xs text-danger-600">{{ formErrors[setting.key] }}</p>
                    </div>
                </div>

                <div
                    v-if="stackedSettings(group).length && (group.key !== 'guests' || guestAccessEnabled(group))"
                    class="grid grid-cols-1 gap-5"
                    :class="group.key.startsWith('plan_') || group.key === 'guests' ? 'md:grid-cols-2 xl:grid-cols-3' : 'md:grid-cols-2'"
                >
                    <div
                        v-for="setting in stackedSettings(group)"
                        :key="setting.key"
                        class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/50"
                    >
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ setting.label }}
                            <span v-if="setting.description" class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">{{ setting.description }}</span>

                            <AppSelect
                                v-if="setting.type === 'select'"
                                :model-value="String(getFormValue(setting.key) ?? '')"
                                :options="selectOptions(setting)"
                                :placeholder="t('Select an option...')"
                                class="mt-2"
                                @update:model-value="setFormValue(setting.key, $event)"
                            />

                            <input
                                v-else-if="setting.type === 'integer'"
                                :value="getFormValue(setting.key) ?? ''"
                                type="number"
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                @input="setNumberValue(setting.key, ($event.target as HTMLInputElement).value)"
                            >

                            <textarea
                                v-else-if="setting.type === 'textarea'"
                                :value="String(getFormValue(setting.key) ?? '')"
                                rows="4"
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                @input="setFormValue(setting.key, ($event.target as HTMLTextAreaElement).value)"
                            ></textarea>

                            <input
                                v-else
                                :value="String(getFormValue(setting.key) ?? '')"
                                :type="setting.type === 'encrypted' ? 'password' : 'text'"
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                @input="setFormValue(setting.key, ($event.target as HTMLInputElement).value)"
                            >
                        </label>
                        <p v-if="formErrors[setting.key]" class="mt-2 text-xs text-danger-600">{{ formErrors[setting.key] }}</p>
                    </div>
                </div>

                <!-- Custom model Controls array builder inside AI Model section -->
                <div v-if="group.key === 'ai'" class="mt-8 border-t border-gray-100 dark:border-surface-800 pt-6">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-md font-bold text-gray-900 dark:text-white">{{ t('Custom Models Manager') }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Allow administrators to create custom models, rename base models, and prepend specific system instructions.') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="resolveBoolean(form.show_custom_models)"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                            :class="resolveBoolean(form.show_custom_models) ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="setBooleanValue('show_custom_models')"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="resolveBoolean(form.show_custom_models) ? 'translate-x-5' : 'translate-x-0.5'"
                            ></span>
                        </button>
                    </div>

                    <div v-if="resolveBoolean(form.show_custom_models)" class="space-y-4 mt-6">
                        <div v-if="customModels.length === 0" class="rounded-xl border border-dashed border-gray-200 dark:border-surface-800 p-8 text-center text-sm text-gray-400">
                            {{ t('No custom models defined yet. Click "Add Custom Model" to create one.') }}
                        </div>
                        
                        <div 
                            v-for="(model, index) in customModels" 
                            :key="model.id"
                            class="relative rounded-xl border border-gray-100 bg-gray-50/50 p-5 dark:border-surface-800 dark:bg-surface-800/40 space-y-4"
                        >
                            <button
                                type="button"
                                class="absolute top-4 right-4 flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                :title="t('Remove Model')"
                                @click="removeCustomModel(index)"
                            >
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ t('Model Name') }}</label>
                                    <input
                                        v-model="model.name"
                                        type="text"
                                        required
                                        placeholder="e.g. My Premium GPT-4o"
                                        class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                    >
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ t('Base AI Model') }}</label>
                                    <AppSelect
                                        v-model="model.model"
                                        :options="aiModels ?? []"
                                        :placeholder="t('Select base model...')"
                                        class="mt-2"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ t('Short Description') }}</label>
                                <input
                                    v-model="model.description"
                                    type="text"
                                    placeholder="e.g. Custom instructions for support chat"
                                    class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                >
                                <p class="mt-1 text-[11px] text-gray-400">{{ t('Displayed as a subtitle in the model selector dropdown.') }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ t('System Prompt (Prepend Instructions)') }}</label>
                                <textarea
                                    v-model="model.system_prompt"
                                    rows="3"
                                    placeholder="e.g. You are a marketing expert. Keep responses punchy and focused on conversion rate optimization."
                                    class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                ></textarea>
                                <p class="mt-1 text-[11px] text-gray-400">{{ t('These system instructions will be automatically prepended to the chat history prompt.') }}</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-4 py-2 border border-dashed border-gray-300 dark:border-surface-800 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-400 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                            @click="addCustomModel"
                        >
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ t('Add Custom Model') }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Mode Specific Models Card -->
            <div v-if="group.key === 'ai' && modes && modes.length" class="mb-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-4">
                    <h3 class="text-md font-bold text-gray-900 dark:text-white">{{ t('Mode Specific Default Models') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Override the default AI model for each chatbot mode individually. If left unset, it falls back to the default AI model.') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="mode in modes"
                        :key="mode.slug"
                        class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-surface-800 dark:bg-surface-800/40 flex flex-col justify-between"
                    >
                        <div>
                            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider block mb-1">{{ t('Mode') }}</span>
                            <h4 class="text-sm font-bold text-gray-800 dark:text-white mb-3">{{ mode.name }}</h4>
                        </div>
                        
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">{{ t('Active Model') }}</label>
                            <AppSelect
                                :model-value="String((form.mode_default_models as Record<string, string>)[mode.slug] ?? '')"
                                :options="modeModelOptions"
                                :placeholder="t('Default Fallback')"
                                @update:model-value="setModeModel(mode.slug, $event)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>
        </form>
    </div>
</template>
