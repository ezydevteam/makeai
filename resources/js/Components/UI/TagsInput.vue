<script setup lang="ts">
import { computed, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = withDefaults(defineProps<{
    modelValue: string
    label?: string
    placeholder?: string
    suggestions?: string[]
}>(), {
    label: '',
    placeholder: '',
    suggestions: () => [],
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const { t } = useTranslate()
const draft = ref('')

const tags = computed(() => props.modelValue.split(',').map((tag) => tag.trim()).filter(Boolean))
const filteredSuggestions = computed(() => {
    const query = draft.value.trim().toLowerCase()

    if (!query) {
        return []
    }

    return props.suggestions
        .filter((tag) => !tags.value.includes(tag))
        .filter((tag) => tag.toLowerCase().includes(query))
        .slice(0, 8)
})

const syncTags = (items: string[]) => {
    emit('update:modelValue', items.join(', '))
}

const addDraftTag = () => {
    const value = draft.value.trim()

    if (!value) {
        return
    }

    const nextTags = [...tags.value]

    if (!nextTags.includes(value)) {
        nextTags.push(value)
        syncTags(nextTags)
    }

    draft.value = ''
}

const removeTag = (index: number) => {
    const nextTags = [...tags.value]
    nextTags.splice(index, 1)
    syncTags(nextTags)
}

const selectSuggestion = (tag: string) => {
    draft.value = tag
    addDraftTag()
}

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault()
        addDraftTag()
        return
    }

    if (event.key === 'Backspace' && !draft.value && tags.value.length) {
        event.preventDefault()
        removeTag(tags.value.length - 1)
    }
}
</script>

<template>
    <div>
        <label v-if="label" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ label }}</label>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 dark:border-surface-700 dark:bg-surface-800">
            <div v-if="tags.length" class="mb-2 flex flex-wrap gap-2">
                <span v-for="(tag, index) in tags" :key="`${tag}-${index}`" class="inline-flex items-center gap-2 rounded-full bg-primary-100 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                    {{ tag }}
                    <button type="button" class="text-primary-600 transition-colors hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-100" :aria-label="t('Remove tag')" @click="removeTag(index)">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </span>
            </div>

            <input
                v-model="draft"
                type="text"
                class="w-full border-0 bg-transparent px-0 py-0 text-sm text-gray-900 outline-none ring-0 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
                :placeholder="placeholder"
                @keydown="handleKeydown"
                @blur="addDraftTag"
            >

            <div v-if="filteredSuggestions.length" class="mt-3 flex flex-wrap gap-2 border-t border-gray-200 pt-3 dark:border-surface-700">
                <button
                    v-for="tag in filteredSuggestions"
                    :key="tag"
                    type="button"
                    class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-700 transition-colors hover:border-primary-300 hover:text-primary-600 dark:border-surface-600 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-600 dark:hover:text-primary-300"
                    @mousedown.prevent="selectSuggestion(tag)"
                >
                    {{ tag }}
                </button>
            </div>
        </div>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ t('Press Enter or comma to add tags.') }}</p>
    </div>
</template>
