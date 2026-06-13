<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const page = usePage()

const tabs = [
    { id: 'general', label: 'General' },
    { id: 'ai', label: 'AI Model' },
    { id: 'persona', label: 'Persona' },
    { id: 'access', label: 'Access' },
    { id: 'prompts', label: 'System Prompts' },
] as const

const activeTab = ref<string>('general')

const addonSettings = computed(() => {
    return ((page.props as any).settings ?? []) as any[]
})

const addonSlug = computed(() => ((page.props as any).addon?.slug ?? 'ai-assistant') as string)

const aiModels = computed(() => ((page.props as any).aiModels ?? []) as { value: string; label: string; provider: string }[])

const form = computed(() => {
    const data: Record<string, any> = {}
    for (const s of addonSettings.value) {
        data[s.key] = s.value
    }
    return data
})

const groupSettings = (group: string) => addonSettings.value.filter((s: any) => (s.group ?? 'general') === group)

const saving = ref(false)
const saved = ref(false)

async function save() {
    saving.value = true
    saved.value = false

    try {
        const slug = addonSlug.value

        await fetch(route('admin.addons.settings.save', { slug }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(form.value),
        })

        saved.value = true
        setTimeout(() => { saved.value = false }, 3000)
    } finally {
        saving.value = false
    }
}

function setValue(key: string, value: any) {
    if (addonSettings.value) {
        const s = addonSettings.value.find((s: any) => s.key === key)
        if (s) s.value = value
    }
}

function toggleBoolean(key: string) {
    const current = addonSettings.value.find((s: any) => s.key === key)
    if (current) setValue(key, !current.value)
}
</script>

<template>
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Assistant Settings') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Configure the floating AI assistant widget.') }}</p>
            </div>
            <button
                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                @click="save"
                :disabled="saving"
            >
                {{ saving ? t('Saving...') : saved ? t('Saved!') : t('Save Settings') }}
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                :class="activeTab === tab.id
                    ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- General tab -->
        <div v-show="activeTab === 'general'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <template v-for="s in groupSettings('general')" :key="s.key">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ s.label }}</label>

                    <!-- boolean -->
                    <template v-if="s.type === 'boolean'">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer" :checked="s.value" @change="toggleBoolean(s.key)" />
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600" />
                        </label>
                    </template>

                    <!-- select -->
                    <select
                        v-else-if="s.type === 'select'"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :value="s.value"
                        @change="setValue(s.key, ($event.target as HTMLSelectElement).value)"
                    >
                        <option v-for="opt in (s.options ?? [])" :key="opt" :value="opt">{{ opt }}</option>
                    </select>

                    <!-- color -->
                    <input
                        v-else-if="s.type === 'color'"
                        type="color"
                        class="w-14 h-10 rounded border border-gray-300 dark:border-gray-600 cursor-pointer"
                        :value="s.value ?? '#1F75FE'"
                        @change="setValue(s.key, ($event.target as HTMLInputElement).value)"
                    />

                    <!-- default (string) -->
                    <input
                        v-else
                        type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :value="s.value ?? ''"
                        :placeholder="s.default"
                        @change="setValue(s.key, ($event.target as HTMLInputElement).value)"
                    />

                    <p v-if="s.default !== null && s.default !== undefined && s.type !== 'boolean' && s.type !== 'select'" class="text-xs text-gray-400 mt-1">
                        {{ t('Default') }}: {{ s.default }}
                    </p>
                </div>
            </template>
            <div v-if="!groupSettings('general').length" class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">
                {{ t('No settings in this section.') }}
            </div>
        </div>

        <!-- AI Model tab -->
        <div v-show="activeTab === 'ai'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <template v-for="s in groupSettings('ai')" :key="s.key">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ s.label }}
                        <span v-if="s.secret" class="text-xs text-gray-400 ml-1">({{ t('server only') }})</span>
                    </label>

                    <AppSelect
                        v-if="s.key === 'model'"
                        :model-value="s.value ?? s.default"
                        :options="aiModels"
                        placeholder="Select a model..."
                        @update:model-value="setValue(s.key, $event)"
                    />
                    <input
                        v-else-if="s.type === 'integer'"
                        type="number"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :value="s.value ?? s.default"
                        @change="setValue(s.key, parseInt(($event.target as HTMLInputElement).value) || 0)"
                    />
                    <input
                        v-else
                        type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :value="s.value ?? ''"
                        :placeholder="s.default"
                        @change="setValue(s.key, ($event.target as HTMLInputElement).value)"
                    />

                    <p v-if="s.default !== null && s.default !== undefined" class="text-xs text-gray-400 mt-1">
                        {{ t('Default') }}: {{ s.default }}
                    </p>
                </div>
            </template>
            <div v-if="!groupSettings('ai').length" class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">
                {{ t('No settings in this section.') }}
            </div>
        </div>

        <!-- Persona tab -->
        <div v-show="activeTab === 'persona'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <template v-for="s in groupSettings('persona')" :key="s.key">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ s.label }}</label>

                    <textarea
                        v-if="s.type === 'textarea'"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        rows="3"
                        :value="s.value ?? ''"
                        :placeholder="s.default"
                        @change="setValue(s.key, ($event.target as HTMLTextAreaElement).value)"
                    />
                    <input
                        v-else
                        type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :value="s.value ?? ''"
                        :placeholder="s.default"
                        @change="setValue(s.key, ($event.target as HTMLInputElement).value)"
                    />

                    <p v-if="s.default !== null && s.default !== undefined" class="text-xs text-gray-400 mt-1">
                        {{ t('Default') }}: {{ s.default }}
                    </p>
                </div>
            </template>
            <div v-if="!groupSettings('persona').length" class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">
                {{ t('No settings in this section.') }}
            </div>
        </div>

        <!-- Access tab -->
        <div v-show="activeTab === 'access'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <template v-for="s in groupSettings('access')" :key="s.key">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ s.label }}</label>

                    <select
                        v-if="s.type === 'select'"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :value="s.value"
                        @change="setValue(s.key, ($event.target as HTMLSelectElement).value)"
                    >
                        <option v-for="opt in (s.options ?? [])" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                    <input
                        v-else-if="s.type === 'integer'"
                        type="number"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :value="s.value ?? s.default"
                        @change="setValue(s.key, parseInt(($event.target as HTMLInputElement).value) || 0)"
                    />

                    <p v-if="s.default !== null && s.default !== undefined && s.type !== 'select'" class="text-xs text-gray-400 mt-1">
                        {{ t('Default') }}: {{ s.default }}
                    </p>
                </div>
            </template>
            <div v-if="!groupSettings('access').length" class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">
                {{ t('No settings in this section.') }}
            </div>
        </div>

        <!-- System Prompts tab -->
        <div v-show="activeTab === 'prompts'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <template v-for="s in groupSettings('prompts')" :key="s.key">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ s.label }}
                        <span class="text-xs text-gray-400 ml-1">({{ t('server only') }})</span>
                    </label>

                    <textarea
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white font-mono"
                        rows="8"
                        :value="s.value ?? ''"
                        @change="setValue(s.key, ($event.target as HTMLTextAreaElement).value)"
                    />
                </div>
            </template>
            <div v-if="!groupSettings('prompts').length" class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">
                {{ t('No settings in this section.') }}
            </div>
        </div>
    </div>
</template>
