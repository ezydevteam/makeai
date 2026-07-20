<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

interface TargetOption {
    value: string
    label: string
}

interface OptionField {
    key: string
    label: string
    type: string
    options: { value: string; label: string }[]
    default: string
}

interface Generator {
    type: string
    label: string
    group: string
    requires_target: boolean
    targets: TargetOption[]
    uses_ai: boolean
    option_fields: OptionField[]
}

interface RecentBatch {
    id: number
    label: string
    target_label: string | null
    requested_count: number
    inserted_count: number
    status: string
    created_at: string | null
}

const props = defineProps<{
    generators: Generator[]
    limits: Record<string, number>
    defaults: { language: string; tone: string; backdate_days: number }
    recent: RecentBatch[]
}>()

// Mirror the option sets the AI tools expose for their language_select / tone_select fields
// (resources/themes/default/js/Components/AI/DynamicForm.vue), so FakerAI's style controls
// speak the same vocabulary as the rest of the app.
const LANGUAGES = [
    'English', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Dutch', 'Russian',
    'Chinese (Simplified)', 'Chinese (Traditional)', 'Japanese', 'Korean', 'Arabic', 'Hindi',
    'Tamil', 'Bangla', 'Urdu', 'Turkish', 'Polish', 'Swedish', 'Vietnamese', 'Indonesian', 'Thai',
]
const TONES = ['Professional', 'Friendly', 'Casual', 'Formal', 'Humorous', 'Persuasive', 'Inspirational', 'Empathetic']

const languageOptions: SelectOption[] = LANGUAGES.map((l) => ({ value: l, label: l }))
const toneOptions: SelectOption[] = TONES.map((tone) => ({ value: tone, label: tone }))

// The "everything" sentinel that leads every target list.
const ALL = '*'

const form = useForm({
    type: props.generators[0]?.type ?? '',
    target: [ALL] as string[],
    count: 10,
    // Honour the addon's configured defaults when they match a listed option, else fall back
    // to a sensible in-list value (the admin's free-text default may not be a listed choice).
    language: LANGUAGES.includes(props.defaults.language) ? props.defaults.language : 'English',
    tone: TONES.includes(props.defaults.tone) ? props.defaults.tone : 'Friendly',
    prompt: '',
    // Type-specific option fields (e.g. testimonial source), keyed by field key.
    options: {} as Record<string, string>,
})

// The "what to generate" dropdown, with a divider whenever the group changes so related
// types read together (Blog, Tools, …).
const generatorOptions = computed<SelectOption[]>(() => {
    let lastGroup: string | null = null

    return props.generators.map((g) => {
        const dividerBefore = lastGroup !== null && lastGroup !== g.group
        lastGroup = g.group

        return { value: g.type, label: g.label, dividerBefore }
    })
})

const selected = computed<Generator | undefined>(() =>
    props.generators.find((g) => g.type === form.type),
)

const targetOptions = computed<SelectOption[]>(() =>
    (selected.value?.targets ?? []).map((tgt) => ({ value: tgt.value, label: tgt.label })),
)

const requiresTarget = computed(() => selected.value?.requires_target ?? false)
const usesAi = computed(() => selected.value?.uses_ai ?? false)
const maxCount = computed(() => props.limits[form.type] ?? 200)
const noTargets = computed(() => requiresTarget.value && targetOptions.value.length <= 1)
const optionFields = computed<OptionField[]>(() => selected.value?.option_fields ?? [])

// When the type changes, seed a sensible target (the "spread across all" pseudo-option is
// always first), clamp the count to the new type's ceiling, and reset option fields to their
// defaults for the newly selected type.
watch(
    () => form.type,
    () => {
        form.target = requiresTarget.value ? [ALL] : []
        if (form.count > maxCount.value) form.count = maxCount.value

        const opts: Record<string, string> = {}
        for (const field of optionFields.value) opts[field.key] = field.default
        form.options = opts
    },
    { immediate: true },
)

// "All" behaves like a chip that owns the whole selection: picking it clears the specific
// items, and picking a specific item drops it. Emptying the box falls back to "All" rather
// than leaving an unsubmittable form.
watch(
    () => form.target,
    (next, prev) => {
        if (!requiresTarget.value || !Array.isArray(next)) return

        if (next.length === 0) {
            form.target = [ALL]
            return
        }

        if (!next.includes(ALL) || next.length === 1) return

        const justPickedAll = !(prev ?? []).includes(ALL)
        form.target = justPickedAll ? [ALL] : next.filter((v) => v !== ALL)
    },
)

const statusTone: Record<string, string> = {
    completed: 'text-emerald-600 dark:text-emerald-400',
    processing: 'text-blue-600 dark:text-blue-400',
    pending: 'text-amber-600 dark:text-amber-400',
    failed: 'text-red-600 dark:text-red-400',
}

const submit = () => {
    form.transform((data) => ({
        ...data,
        target: requiresTarget.value ? data.target : [],
    })).post(route('addon.faker-ai.admin.generate'), {
        preserveScroll: true,
    })
}

const inputClass =
    'w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-primary-500'
</script>

<template>
    <Head title="FakerAI" />

    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ t('FakerAI — Generator') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('Populate your site with realistic sample data. Every run is logged and can be deleted.') }}
            </p>
        </div>

        <form
            class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            @submit.prevent="submit"
        >
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ t('What to generate') }}
                </label>
                <AppSelect v-model="form.type" :options="generatorOptions" />
            </div>

            <div v-if="requiresTarget">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ t('Target') }}
                </label>
                <AppSelect v-model="form.target" :options="targetOptions" multiple live-search />
                <p v-if="noTargets" class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">
                    {{ t('Nothing to attach to yet — create the parent items (tools, posts or articles) first.') }}
                </p>
                <p v-else class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ t('Pick as many as you like — the count is spread evenly across them.') }}
                </p>
                <p v-if="form.errors.target" class="mt-1 text-xs text-red-600">{{ form.errors.target }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ t('How many') }}
                </label>
                <input
                    v-model.number="form.count"
                    type="number"
                    min="1"
                    :max="maxCount"
                    :class="inputClass"
                />
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ t('Up to') }} {{ maxCount.toLocaleString() }}.
                    <span v-if="!usesAi">{{ t('Spread across the selected target(s).') }}</span>
                </p>
                <p v-if="form.errors.count" class="mt-1 text-xs text-red-600">{{ form.errors.count }}</p>
            </div>

            <!-- Type-specific options (e.g. testimonial source). -->
            <div v-for="field in optionFields" :key="field.key">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ t(field.label) }}
                </label>
                <AppSelect v-model="form.options[field.key]" :options="field.options" />
            </div>

            <!-- AI-authored types get style controls; pure-counter types (views, shares, ratings) don't call the AI. -->
            <template v-if="usesAi">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('Language') }}
                        </label>
                        <AppSelect v-model="form.language" :options="languageOptions" live-search />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('Tone') }}
                        </label>
                        <AppSelect v-model="form.tone" :options="toneOptions" />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ t('Extra guidance (optional)') }}
                    </label>
                    <textarea
                        v-model="form.prompt"
                        rows="3"
                        :class="inputClass"
                        :placeholder="t('e.g. mention specific features, keep it enthusiastic, B2B audience…')"
                    />
                </div>
            </template>

            <div class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800">
                <p class="text-xs text-gray-400">
                    <i class="ti ti-robot" />
                    {{ usesAi
                        ? t('AI runs on the internal system account.')
                        : t('No AI used — counters only.') }}
                </p>
                <button
                    type="submit"
                    :disabled="form.processing || noTargets"
                    class="inline-flex items-center gap-1.5 btn-primary-admin disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <i class="ti ti-wand" />
                    {{ t('Generate') }}
                </button>
            </div>
        </form>

        <div v-if="recent.length" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ t('Recent generations') }}</h2>
                <Link :href="route('addon.faker-ai.admin.history')" class="text-xs text-primary-600 hover:underline">
                    {{ t('View all') }}
                </Link>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                <li v-for="b in recent" :key="b.id" class="flex items-center justify-between py-2 text-sm">
                    <div class="min-w-0">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ b.label }}</span>
                        <span v-if="b.target_label" class="text-gray-400"> · {{ b.target_label }}</span>
                    </div>
                    <div class="flex items-center gap-3 whitespace-nowrap">
                        <span class="text-gray-500">{{ b.inserted_count }} / {{ b.requested_count }}</span>
                        <span :class="statusTone[b.status] ?? 'text-gray-500'" class="capitalize">{{ b.status }}</span>
                        <span class="text-gray-400">{{ b.created_at }}</span>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
