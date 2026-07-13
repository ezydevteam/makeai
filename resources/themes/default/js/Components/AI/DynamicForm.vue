<script setup lang="ts">
import { computed } from 'vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'

export interface ToolField {
    id?: string
    key?: string
    name?: string
    label: string
    type: string
    placeholder?: string
    options?: string | Array<string | { label: string; value: string }>
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

const normalizedOptions = (field: ToolField): Array<{ label: string; value: string | number | null }> => {
    const mapOption = (o: string | { label?: string; value?: string | number | boolean } | unknown): { label: string; value: string | number | null } => {
        if (typeof o === 'string') {
            return { label: o, value: o }
        }
        if (o && typeof o === 'object') {
            const obj = o as { label?: string | number; value?: string | number | boolean }
            const label = obj.label ?? obj.value ?? ''
            const value = obj.value ?? obj.label ?? ''
            return { label: String(label), value: value !== undefined && value !== null ? (value as string | number) : null }
        }
        return { label: String(o ?? ''), value: o !== undefined && o !== null ? String(o) : null }
    }

    if (field.type === 'tone_select') {
        const tones = ['Professional', 'Friendly', 'Casual', 'Formal', 'Humorous', 'Persuasive', 'Inspirational', 'Empathetic']
        return tones.map(t => ({ label: t, value: t.toLowerCase() }))
    }
    if (field.type === 'length_select') {
        let optArray: unknown[] = []
        if (field.options) {
            if (Array.isArray(field.options)) {
                optArray = field.options as unknown[]
            } else if (typeof field.options === 'string') {
                try {
                    const parsed = JSON.parse(field.options)
                    optArray = Array.isArray(parsed) ? parsed : (parsed && typeof parsed === 'object' ? Object.values(parsed) : [])
                } catch {}
            } else if (typeof field.options === 'object') {
                optArray = Object.values(field.options)
            }
        }
        if (optArray.length > 0) {
            return optArray.map(mapOption)
        }
        return [
            { label: 'Short (~100 words)', value: 'short' },
            { label: 'Medium (~300 words)', value: 'medium' },
            { label: 'Long (~600 words)', value: 'long' },
            { label: 'Very Long (~1200 words)', value: 'very_long' },
        ]
    }
    if (field.type === 'language_select') {
        let optArray: unknown[] = []
        if (field.options) {
            if (Array.isArray(field.options)) {
                optArray = field.options as unknown[]
            } else if (typeof field.options === 'string') {
                try {
                    const parsed = JSON.parse(field.options)
                    optArray = Array.isArray(parsed) ? parsed : (parsed && typeof parsed === 'object' ? Object.values(parsed) : [])
                } catch {
                    optArray = field.options.split(',').map(s => s.trim()).filter(Boolean)
                }
            } else if (typeof field.options === 'object') {
                optArray = Object.values(field.options)
            }
        }
        if (optArray.length > 0) {
            return optArray.map(mapOption)
        }

        // Fallback popular languages
        const defaultLanguages = [
            'English', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Dutch', 'Russian',
            'Chinese (Simplified)', 'Chinese (Traditional)', 'Japanese', 'Korean', 'Arabic', 'Hindi',
            'Tamil', 'Bangla', 'Urdu', 'Turkish', 'Polish', 'Swedish', 'Vietnamese', 'Indonesian', 'Thai'
        ]
        return defaultLanguages.map(l => ({ label: l, value: l }))
    }
    if (field.type === 'audience_select') {
        let optArray: unknown[] = []
        if (field.options) {
            if (Array.isArray(field.options)) {
                optArray = field.options as unknown[]
            } else if (typeof field.options === 'string') {
                try {
                    const parsed = JSON.parse(field.options)
                    optArray = Array.isArray(parsed) ? parsed : (parsed && typeof parsed === 'object' ? Object.values(parsed) : [])
                } catch {
                    optArray = field.options.split(',').map(s => s.trim()).filter(Boolean)
                }
            } else if (typeof field.options === 'object') {
                optArray = Object.values(field.options)
            }
        }
        if (optArray.length > 0) {
            return optArray.map(mapOption)
        }

        // Default popular audiences
        const defaultAudiences = [
            'General Public', 'Beginners', 'Professionals', 'Business Owners', 'Social Workers',
            'Developers', 'Marketers', 'Students', 'Teachers', 'Seniors', 'Tech Savvy', 'Parents', 'Children'
        ]
        return defaultAudiences.map(a => ({ label: a, value: a }))
    }
    if (field.type === 'model_select') {
        return (props.models || []).map((model) => ({ label: `${model.name} (${model.provider})`, value: model.slug }))
    }

    const rawOptions = field.options
    let parsed: unknown[] = []
    if (rawOptions) {
        if (Array.isArray(rawOptions)) {
            parsed = rawOptions as unknown[]
        } else if (typeof rawOptions === 'string') {
            try {
                const decoded = JSON.parse(rawOptions)
                if (Array.isArray(decoded)) {
                    parsed = decoded
                } else if (decoded && typeof decoded === 'object') {
                    parsed = Object.values(decoded)
                } else {
                    parsed = rawOptions.split(',').map(s => s.trim()).filter(Boolean)
                }
            } catch {
                parsed = rawOptions.split(',').map(s => s.trim()).filter(Boolean)
            }
        } else if (typeof rawOptions === 'object') {
            parsed = Object.values(rawOptions)
        }
    }
    return parsed.map(mapOption)
}

const updateValue = (name: string, value: unknown) => {
    values.value = { ...values.value, [name]: value }
}

const isPercentSlider = (field: ToolField) => {
    return String(field.min ?? '').includes('%') ||
           String(field.max ?? '').includes('%') ||
           String(field.default ?? '').includes('%');
}

const getSliderValue = (field: ToolField) => {
    const raw = values.value[fieldName(field)] ?? field.default ?? 0;
    return parseFloat(String(raw));
}

const formatSliderDisplay = (field: ToolField) => {
    const val = values.value[fieldName(field)] ?? field.default ?? 0;
    const str = String(val);
    if (isPercentSlider(field) && !str.includes('%')) {
        return str + '%';
    }
    return str;
}

const handleSliderInput = (field: ToolField, val: number) => {
    const name = fieldName(field)
    if (isPercentSlider(field)) {
        updateValue(name, val + '%')
    } else {
        updateValue(name, val)
    }
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

const isChecked = (field: ToolField) => {
    const val = values.value[fieldName(field)];
    if (val === undefined || val === null) return false;
    if (typeof val === 'boolean') return val;
    const str = String(val).toLowerCase();
    return str === 'true' || str === '1' || str === 'on' || str === 'yes';
}

const inputClass = 'w-full px-4 py-2.5 border border-gray-200 bg-white rounded-xl text-sm transition-all focus:outline-none focus:!ring-1 focus:!ring-primary-500/40 text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-600'
const inputClassError = 'border-red-500/50 focus:ring-red-500/40 dark:border-red-500/50 dark:focus:ring-red-500/40'
</script>

<template>
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div v-for="field in fields" :key="fieldName(field)">
            <label v-if="field.type !== 'toggle' && field.type !== 'checkbox' && field.type !== 'hidden'" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
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

            <!-- select / tone_select / language_select / length_select / model_select / audience_select -->
            <AppSelect
                v-else-if="['select', 'tone_select', 'language_select', 'length_select', 'model_select', 'audience_select'].includes(field.type)"
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
                    :value="getSliderValue(field)"
                    type="range"
                    :min="parseFloat(String(field.min ?? 0))"
                    :max="parseFloat(String(field.max ?? (isPercentSlider(field) ? 100 : 1)))"
                    :step="parseFloat(String(field.step ?? (isPercentSlider(field) ? 1 : 0.1)))"
                    :disabled="disabled"
                    class="w-full accent-primary-500"
                    @input="handleSliderInput(field, Number(($event.target as HTMLInputElement).value))"
                />
                <div class="text-xs text-gray-500">{{ formatSliderDisplay(field) }}</div>
            </div>

            <!-- toggle / checkbox -->
            <div v-else-if="field.type === 'toggle' || field.type === 'checkbox'" class="flex items-center justify-between py-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ field.label }}
                    <span v-if="field.required" class="text-danger-500">*</span>
                </span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        :checked="isChecked(field)"
                        :disabled="disabled"
                        class="sr-only peer"
                        @change="updateValue(fieldName(field), ($event.target as HTMLInputElement).checked)"
                    />
                    <div class="h-6 w-11 rounded-full bg-gray-200 peer-checked:!bg-primary-500 dark:bg-white/10 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:after:border-white/10 dark:after:bg-gray-400 dark:peer-checked:after:bg-white"></div>
                </label>
            </div>

            <!-- color -->
            <AppColorPicker
                v-else-if="field.type === 'color'"
                :model-value="String(values[fieldName(field)] ?? field.default ?? '#10b981')"
                :disabled="disabled"
                :error="fieldError(field)"
                @update:model-value="updateValue(fieldName(field), $event)"
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
                    :key="option.value ?? ''"
                    class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer"
                >
                    <input
                        type="radio"
                        :name="fieldName(field)"
                        :value="option.value"
                        :checked="String(values[fieldName(field)] ?? '').toLowerCase() === String(option.value).toLowerCase()"
                        :disabled="disabled"
                        class="w-4 h-4 border-gray-300 dark:!border-gray-800 bg-white dark:!bg-white/[0.03] text-primary-500 focus:ring-primary-500/40 accent-primary-500"
                        @change="updateValue(fieldName(field), option.value)"
                    />
                    {{ option.label }}
                </label>
            </div>

            <!-- multi_select -->
            <AppSelect
                v-else-if="field.type === 'multi_select'"
                :model-value="Array.isArray(values[fieldName(field)]) ? values[fieldName(field)] as (string | number)[] : []"
                :options="normalizedOptions(field)"
                :placeholder="field.placeholder || `Select ${field.label.toLowerCase()}...`"
                :disabled="disabled"
                :error="fieldError(field)"
                multiple
                @update:model-value="updateValue(fieldName(field), $event)"
            />

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
