<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
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

type FormValue = string | number | boolean | null | File | (string | number)[]

type SettingGroup = {
    key: string
    title: string
    description: string
    settings: Setting[]
}

const props = defineProps<{
    addon: AddonConfig
    settings: Setting[]
    aiModels?: Array<{ value: string; label: string; provider: string }>
}>()
const { t } = useTranslate()

const form = useForm<Record<string, FormValue>>(
    Object.fromEntries(props.settings.map((setting) => [setting.key, (setting.value ?? setting.default) as FormValue])),
)

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

const setNumberValue = (key: string, value: string): void => {
    form[key] = value === '' ? null : Number(value)
}

const setFileValue = (key: string, event: Event): void => {
    const file = (event.target as HTMLInputElement | null)?.files?.[0] ?? null
    form[key] = file
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
            title: t('General'),
            description: t('Core visibility and page behavior settings for this addon.'),
        }
    }

    if (group === 'ai') {
        return {
            title: t('AI Model Controls'),
            description: t('Choose how model selection and default AI behavior should work for chatbot sessions.'),
        }
    }

    if (group === 'guests') {
        return {
            title: t('Guest Access'),
            description: t('Define whether guests can chat and how tightly their usage should be limited.'),
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

const groupedSettings = computed<SettingGroup[]>(() => {
    const groups = new Map<string, Setting[]>()

    for (const setting of props.settings) {
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

    return nonBooleanSettings(group.settings).filter((setting) => setting.key === 'default_chat_model' || setting.key === 'model')
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
    if (group.key !== 'guests') {
        return booleanSettings(group.settings)
    }

    return booleanSettings(group.settings).filter((setting) => setting.key !== 'allow_guest_messages')
}

const stackedSettings = (group: SettingGroup): Setting[] => {
    if (group.key !== 'ai') {
        if (group.key === 'guests') {
            return nonBooleanSettings(group.settings).filter((setting) => setting.key !== 'allow_guest_messages')
        }

        return nonBooleanSettings(group.settings)
    }

    return nonBooleanSettings(group.settings).filter((setting) => setting.key !== 'default_chat_model' && setting.key !== 'model')
}

const save = () => form.post(route('admin.addons.settings.save', { slug: props.addon.slug }), {
    preserveScroll: true,
    forceFormData: props.settings.some((setting) => setting.type === 'file'),
})
</script>

<template>
    <Head :title="`${addon.name} ${t('Settings')} â€” Admin`" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ addon.name }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ addon.description || t('Configure this addon from a cleaner, grouped settings page built for easier admin setup.') }}
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
                    class="inline-flex items-center gap-2 rounded-lg btn-primary-admin px-5 py-2.5 text-sm font-semibold disabled:opacity-60"
                    @click="save"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Settings') }}
                </button>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="save">
            <section
                v-for="group in groupedSettings"
                :key="group.key"
                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900"
            >
                <div class="mb-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ group.title }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ group.description }}</p>
                        </div>
                        <AppSwitch
                            v-if="guestToggleSetting(group)"
                            :model-value="guestAccessEnabled(group)"
                            @update:model-value="setBooleanValue(guestToggleSetting(group)!.key)"
                            class="mt-1"
                        />
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
                                <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Enable or disable this behavior for the addon.') }}</p>
                            </div>
                            <AppSwitch
                                :model-value="resolveBoolean(getFormValue(setting.key))"
                                @update:model-value="setBooleanValue(setting.key)"
                            />
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

                            <AppSelect
                                v-else-if="(setting.key === 'default_chat_model' || setting.key === 'model') && aiModels?.length"
                                :model-value="String(getFormValue(setting.key) ?? '')"
                                :options="aiModels"
                                :placeholder="t('Select a model...')"
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
                                v-else-if="setting.type === 'file'"
                                type="file"
                                class="mt-2 block w-full rounded-lg border border-dashed border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300"
                                @change="setFileValue(setting.key, $event)"
                            >

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
            </section>
        </form>
    </div>
</template>
