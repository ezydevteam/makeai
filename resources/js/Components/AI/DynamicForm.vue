<script setup lang="ts">
import { computed } from 'vue'

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

const normalizedOptions = (field: ToolField) => {
    if (field.type === 'tone_select') {
        return ['Professional', 'Friendly', 'Casual', 'Formal', 'Humorous', 'Persuasive', 'Inspirational', 'Empathetic']
    }
    if (field.type === 'length_select') {
        return [
            { label: 'Short - about 100 words', value: 'short' },
            { label: 'Medium - about 300 words', value: 'medium' },
            { label: 'Long - about 600 words', value: 'long' },
            { label: 'Very Long - about 1200 words', value: 'very_long' },
        ]
    }
    if (field.type === 'language_select') {
        return (props.languages || []).map((language) => ({ label: language.name, value: language.name }))
    }
    if (field.type === 'model_select') {
        return (props.models || []).map((model) => ({ label: `${model.name} (${model.provider})`, value: model.slug }))
    }

    return field.options || []
}

const optionPair = (option: string | { label: string; value: string }) => {
    return typeof option === 'string' ? { label: option, value: option } : option
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
</script>

<template>
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div v-for="field in fields" :key="fieldName(field)">
            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                {{ field.label }}
                <span v-if="field.required" class="text-danger-500">*</span>
            </label>

            <textarea
                v-if="field.type === 'textarea' || field.type === 'code_input'"
                :value="String(values[fieldName(field)] ?? '')"
                :rows="field.type === 'code_input' ? 8 : (field.rows || 4)"
                :maxlength="field.max_length"
                :placeholder="field.placeholder"
                :required="field.required"
                :disabled="disabled"
                class="w-full px-4 py-2.5 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all resize-y shadow-sm"
                :class="{ 'font-mono': field.type === 'code_input' }"
                @input="updateValue(fieldName(field), ($event.target as HTMLTextAreaElement).value)"
            />

            <select
                v-else-if="['select', 'tone_select', 'language_select', 'length_select', 'model_select'].includes(field.type)"
                :value="String(values[fieldName(field)] ?? '')"
                :required="field.required"
                :disabled="disabled"
                class="w-full px-4 py-2.5 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all shadow-sm"
                @change="updateValue(fieldName(field), ($event.target as HTMLSelectElement).value)"
            >
                <option value="" class="bg-surface-900">Select...</option>
                <option v-for="option in normalizedOptions(field)" :key="optionPair(option).value" :value="optionPair(option).value" class="bg-surface-900">
                    {{ optionPair(option).label }}
                </option>
            </select>

            <input
                v-else-if="field.type === 'number'"
                :value="String(values[fieldName(field)] ?? '')"
                type="number"
                :min="field.min"
                :max="field.max"
                :step="field.step"
                :placeholder="field.placeholder"
                :required="field.required"
                :disabled="disabled"
                class="w-full px-4 py-2.5 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all shadow-sm"
                @input="updateValue(fieldName(field), ($event.target as HTMLInputElement).value)"
            />

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

            <label v-else-if="field.type === 'toggle'" class="inline-flex items-center gap-3 text-sm text-gray-300">
                <input
                    type="checkbox"
                    :checked="Boolean(values[fieldName(field)])"
                    :disabled="disabled"
                    class="w-4 h-4 rounded border-white/20 bg-white/[0.03] text-primary-500 focus:ring-primary-500/40"
                    @change="updateValue(fieldName(field), ($event.target as HTMLInputElement).checked)"
                />
                <span>{{ values[fieldName(field)] ? 'Enabled' : 'Disabled' }}</span>
            </label>

            <input
                v-else-if="field.type === 'color'"
                :value="String(values[fieldName(field)] ?? field.default ?? '#10b981')"
                type="color"
                :disabled="disabled"
                class="w-full h-11 px-2 py-1 bg-white/[0.03] border border-white/10 rounded-xl"
                @input="updateValue(fieldName(field), ($event.target as HTMLInputElement).value)"
            />

            <div v-else-if="field.type === 'tags_input'" class="space-y-2">
                <input
                    type="text"
                    :placeholder="field.placeholder || 'Type and press Enter'"
                    :disabled="disabled"
                    class="w-full px-4 py-2.5 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all shadow-sm"
                    @keydown.enter.prevent="addTag(field, $event)"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tag in (Array.isArray(values[fieldName(field)]) ? values[fieldName(field)] as string[] : [])"
                        :key="tag"
                        type="button"
                        class="px-2.5 py-1 rounded-lg bg-primary-500/10 text-primary-300 border border-primary-500/20 text-xs"
                        @click="removeTag(field, tag)"
                    >
                        {{ tag }} x
                    </button>
                </div>
            </div>

            <input
                v-else-if="field.type === 'file_upload' || field.type === 'image_upload'"
                :accept="field.type === 'image_upload' ? 'image/*' : undefined"
                type="file"
                :required="field.required"
                :disabled="disabled"
                class="w-full px-4 py-2.5 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-gray-300 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-500/20 file:px-3 file:py-1.5 file:text-primary-200"
                @change="readFile(field, $event)"
            />

            <input
                v-else
                :value="String(values[fieldName(field)] ?? '')"
                :type="field.type === 'url' ? 'url' : 'text'"
                :maxlength="field.max_length"
                :placeholder="field.placeholder"
                :required="field.required"
                :disabled="disabled"
                class="w-full px-4 py-2.5 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all shadow-sm"
                @input="updateValue(fieldName(field), ($event.target as HTMLInputElement).value)"
            />
        </div>

        <slot />
    </form>
</template>

