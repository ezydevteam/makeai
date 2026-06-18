<script setup lang="ts">
import { computed } from 'vue'
import AppSelect from '@/Components/AppSelect.vue'

export interface ToolField {
    id?: string
    key?: string
    name?: string
    label: string
    type: string
    placeholder?: string
    options?: Array<string | { label: string; value: string }>
    required?: boolean
    default?: string | number | boolean | string[]
    min?: number
    max?: number
    step?: number
    rows?: number
    max_length?: number
}

const props = defineProps<{
    fields: ToolField[]
    modelValue: Record<string, unknown>
    languages?: Array<{ code: string; name: string }>
    models?: Array<{ slug: string; name: string; provider: string }>
    disabled?: boolean
    fieldErrors?: Record<string, string>
}>()

const emit = defineEmits<{
    'update:modelValue': [value: Record<string, unknown>]
    submit: []
}>()

const values = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

const fieldName = (field: ToolField): string => field.name || field.key || field.id || ''

const fieldError = (field: ToolField): string => {
    if (!props.fieldErrors) return ''
    const name = fieldName(field)
    return props.fieldErrors[name] || ''
}

const normalizedOptions = (field: ToolField) => {
    if (field.type === 'tone_select') {
        const tones = ['Professional', 'Friendly', 'Casual', 'Formal', 'Humorous', 'Persuasive', 'Inspirational', 'Empathetic']
        return tones.map(t => ({ label: t, value: t.toLowerCase() }))
    }
    if (field.type === 'length_select') {
        if (field.options && field.options.length > 0) {
            return field.options
        }
        return [
            { label: 'Short (~100 words)', value: 'short' },
            { label: 'Medium (~300 words)', value: 'medium' },
            { label: 'Long (~600 words)', value: 'long' },
            { label: 'Very Long (~1200 words)', value: 'very_long' },
        ]
    }
    if (field.type === 'language_select') {
        return (props.languages || []).map((language) => ({ label: language.name, value: language.name }))
    }
    if (field.type === 'model_select') {
        return (props.models || []).map((model) => ({ label: `${model.name} (${model.provider})`, value: model.slug }))
    }

    return (field.options || []).map(o => typeof o === 'string' ? { label: o, value: o } : o)
}

const updateValue = (name: string, value: unknown) => {
    values.value = { ...values.value, [name]: value }
}

const addTag = (field: ToolField, event: KeyboardEvent) => {
    const input = event.target as HTMLInputElement
    const name = fieldName(field)
    const tag = input.value.trim()
    if (!tag) return

    const current = Array.isArray(values.value[name]) ? values.value[name] as string[] : []
    updateValue(name, [...current, tag])
    input.value = ''
}

const removeTag = (field: ToolField, tag: string) => {
    const name = fieldName(field)
    const current = Array.isArray(values.value[name]) ? values.value[name] as string[] : []
    updateValue(name, current.filter((item) => item !== tag))
}

const toggleMultiSelectOption = (field: ToolField, optionValue: string) => {
    const name = fieldName(field)
    const current = Array.isArray(values.value[name]) ? values.value[name] as string[] : []
    updateValue(name, current.includes(optionValue)
        ? current.filter(v => v !== optionValue)
        : [...current, optionValue])
}

const readFile = (field: ToolField, event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => updateValue(fieldName(field), {
        name: file.name,
        type: file.type,
        size: file.size,
        content: reader.result,
    })

    if (field.type === 'image_upload') reader.readAsDataURL(file)
    else reader.readAsText(file)
}

const inputClass = 'w-full px-4 py-2.5 border rounded-xl text-sm transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 bg-white dark:bg-white/[0.03] border-gray-300 dark:border-white/10 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600'
const inputClassError = 'border-red-500/50 focus:ring-red-500/40 dark:border-red-500/50 dark:focus:ring-red-500/40'
</script>

<template>
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div v-for="field in fields" :key="fieldName(field)">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {{ field.label }}
                <span v-if="field.required" class="text-danger-500">*</span>
            </label>

            <!-- textarea / code_input -->
            <textarea
                v-if="field.type === 'textarea' || field.type === 'code_input'"
                :value="String(values[fieldName(field)] ?? '')"
                :rows="field.type === 'code_input' ? 8 : (field.rows || 4)"
                :maxlength="field.max_length"
                :placeholder="field.placeholder || `Enter ${field.label.toLowerCase()}...`"
                :required="field.required"
                :disabled="disabled"
                :class="[inputClass, { 'font-mono': field.type === 'code_input' }, fieldError(field) ? inputClassError : '']"
                class="resize-y"
                @input="updateValue(fieldName(field), ($event.target as HTMLTextAreaElement).value)"
            />

            <!-- select / tone_select / language_select / length_select / model_select -->
            <AppSelect
                v-else-if="['select', 'tone_select', 'language_select', 'length_select', 'model_select'].includes(field.type)"
                :model-value="String(values[fieldName(field)] ?? '') || null"
                :options="normalizedOptions(field)"
                :placeholder="field.placeholder || `Select ${field.label.toLowerCase()}...`"
                :disabled="disabled"
                :error="fieldError(field)"
                @update:model-value="updateValue(fieldName(field), $event)"
            />

            <!-- number -->
            <input
                v-else-if="field.type === 'number'"
                :value="String(values[fieldName(field)] ?? '')"
                type="number"
                :min="field.min"
                :max="field.max"
                :step="field.step"
                :placeholder="field.placeholder || `Enter ${field.label.toLowerCase()}...`"
                :required="field.required"
                :disabled="disabled"
                :class="[inputClass, fieldError(field) ? inputClassError : '']"
                @input="updateValue(fieldName(field), ($event.target as HTMLInputElement).value)"
            />

            <!-- slider -->
            <div v-else-if="field.type === 'slider'" class="space-y-2">
                <input
                    :value="Number(values[fieldName(field)] ?? field.default ?? 0)"
                    type="range"
                    :min="field.min ?? 0"
                    :max="field.max ?? 1"
                    :step="field.step ?? 0.1"
                    :disabled="disabled"
                    class="w-full accent-primary-500"
                    @input="updateValue(fieldName(field), Number(($event.target as HTMLInputElement).value))"
                />
                <div class="text-xs text-gray-500">{{ values[fieldName(field)] ?? field.default ?? 0 }}</div>
            </div>

            <!-- toggle -->
            <label v-else-if="field.type === 'toggle'" class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    :checked="Boolean(values[fieldName(field)])"
                    :disabled="disabled"
                    class="w-4 h-4 rounded border-gray-300 dark:border-white/20 bg-white dark:bg-white/[0.03] text-primary-500 focus:ring-primary-500/40"
                    @change="updateValue(fieldName(field), ($event.target as HTMLInputElement).checked)"
                />
                <span>{{ values[fieldName(field)] ? 'Enabled' : 'Disabled' }}</span>
            </label>

            <!-- color -->
            <input
                v-else-if="field.type === 'color'"
                :value="String(values[fieldName(field)] ?? field.default ?? '#10b981')"
                type="color"
                :disabled="disabled"
                class="w-full h-11 px-2 py-1 bg-white dark:bg-white/[0.03] border border-gray-300 dark:border-white/10 rounded-xl"
            />

            <!-- tags_input -->
            <div v-else-if="field.type === 'tags_input'" class="space-y-2">
                <input
                    type="text"
                    :placeholder="field.placeholder || 'Type and press Enter'"
                    :disabled="disabled"
                    :class="[inputClass, fieldError(field) ? inputClassError : '']"
                    @keydown.enter.prevent="addTag(field, $event)"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tag in (Array.isArray(values[fieldName(field)]) ? values[fieldName(field)] as string[] : [])"
                        :key="tag"
                        type="button"
                        class="px-2.5 py-1 rounded-lg bg-primary-500/10 text-primary-700 dark:text-primary-300 border border-primary-500/20 text-xs"
                        @click="removeTag(field, tag)"
                    >
                        {{ tag }} x
                    </button>
                </div>
            </div>

            <!-- file_upload / image_upload -->
            <input
                v-else-if="field.type === 'file_upload' || field.type === 'image_upload'"
                :accept="field.type === 'image_upload' ? 'image/*' : undefined"
                type="file"
                :required="field.required"
                :disabled="disabled"
                class="w-full px-4 py-2.5 bg-white dark:bg-white/[0.03] border border-gray-300 dark:border-white/10 rounded-xl text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-500/20 file:px-3 file:py-1.5 file:text-primary-700 dark:file:text-primary-200"
                @change="readFile(field, $event)"
            />

            <!-- date -->
            <input
                v-else-if="field.type === 'date'"
                :value="String(values[fieldName(field)] ?? '')"
                type="date"
                :min="field.min != null ? String(field.min) : undefined"
                :max="field.max != null ? String(field.max) : undefined"
                :required="field.required"
                :disabled="disabled"
                :class="[inputClass, fieldError(field) ? inputClassError : '']"
                @input="updateValue(fieldName(field), ($event.target as HTMLInputElement).value)"
            />

            <!-- datetime_local -->
            <input
                v-else-if="field.type === 'datetime_local'"
                :value="String(values[fieldName(field)] ?? '')"
                type="datetime-local"
                :required="field.required"
                :disabled="disabled"
                :class="[inputClass, fieldError(field) ? inputClassError : '']"
                @input="updateValue(fieldName(field), ($event.target as HTMLInputElement).value)"
            />

            <!-- radio -->
            <div v-else-if="field.type === 'radio'" class="flex flex-wrap gap-3">
                <label
                    v-for="option in normalizedOptions(field)"
                    :key="option.value"
                    class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer"
                >
                    <input
                        type="radio"
                        :name="fieldName(field)"
                        :value="option.value"
                        :checked="String(values[fieldName(field)] ?? '') === option.value"
                        :disabled="disabled"
                        class="w-4 h-4 border-gray-300 dark:border-white/20 bg-white dark:bg-white/[0.03] text-primary-500 focus:ring-primary-500/40"
                        @change="updateValue(fieldName(field), option.value)"
                    />
                    {{ option.label }}
                </label>
            </div>

            <!-- multi_select -->
            <div v-else-if="field.type === 'multi_select'" class="flex flex-wrap gap-2">
                <label
                    v-for="option in normalizedOptions(field)"
                    :key="option.value"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm cursor-pointer transition-colors border"
                    :class="(Array.isArray(values[fieldName(field)]) && (values[fieldName(field)] as string[]).includes(option.value))
                        ? 'bg-primary-500/15 text-primary-700 dark:text-primary-300 border-primary-500/30'
                        : 'bg-gray-50 dark:bg-white/[0.03] text-gray-600 dark:text-gray-400 border-gray-200 dark:border-white/10 hover:border-gray-400 dark:hover:border-white/20'"
                    @click="toggleMultiSelectOption(field, option.value)"
                >
                    <input
                        type="checkbox"
                        :checked="Array.isArray(values[fieldName(field)]) && (values[fieldName(field)] as string[]).includes(option.value)"
                        :disabled="disabled"
                        class="sr-only"
                    />
                    {{ option.label }}
                </label>
            </div>

            <!-- hidden -->
            <input
                v-else-if="field.type === 'hidden'"
                :value="String(values[fieldName(field)] ?? field.default ?? '')"
                type="hidden"
            />

            <!-- fallback: url, text, or unknown type -->
            <input
                v-else
                :value="String(values[fieldName(field)] ?? '')"
                :type="field.type === 'url' ? 'url' : 'text'"
                :maxlength="field.max_length"
                :placeholder="field.placeholder || `Enter ${field.label.toLowerCase()}...`"
                :required="field.required"
                :disabled="disabled"
                :class="[inputClass, fieldError(field) ? inputClassError : '']"
                @input="updateValue(fieldName(field), ($event.target as HTMLInputElement).value)"
            />

            <!-- Per-field error -->
            <p v-if="fieldError(field)" class="mt-1 text-xs text-red-400">{{ fieldError(field) }}</p>
        </div>

        <slot />
    </form>
</template>
