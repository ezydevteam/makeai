<script setup lang="ts">
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps<{
    disabled: boolean
    isAdmin: boolean
}>()

const emit = defineEmits<{
    (e: 'send', text: string): void
}>()

const page = usePage()
const $t = (key: string, replace?: Record<string, string | number>) => {
    const translations = (page.props.translations ?? {}) as Record<string, string>
    let text = translations[key] ?? key
    if (replace) {
        for (const [k, v] of Object.entries(replace)) {
            text = text.replace(new RegExp(`:${k}`, 'g'), String(v))
        }
    }
    return text
}

const input = ref('')
const textarea = ref<HTMLTextAreaElement | null>(null)

function onSubmit() {
    const text = input.value.trim()
    if (!text || props.disabled) return

    emit('send', text)
    input.value = ''

    // Reset height
    if (textarea.value) {
        textarea.value.style.height = 'auto'
    }
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        onSubmit()
    }
}

function autoResize() {
    const el = textarea.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}
</script>

<template>
    <div class="ai-input flex items-end gap-2 px-3 py-3 border-t border-gray-200 dark:border-gray-700 shrink-0">
        <textarea
            ref="textarea"
            v-model="input"
            :placeholder="$t('Type a message...')"
            :disabled="disabled"
            rows="1"
            class="flex-1 resize-none bg-gray-100 dark:bg-gray-800 border-0 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--ai-accent,#1F75FE)] max-h-[120px]"
            @keydown="onKeydown"
            @input="autoResize"
        />
        <button
            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors disabled:opacity-40"
            :disabled="disabled || !input.trim()"
            :style="{ background: input.trim() ? 'var(--ai-accent, #1F75FE)' : '#e5e7eb', color: input.trim() ? '#fff' : '#9ca3af' }"
            @click="onSubmit"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
            </svg>
        </button>
    </div>
</template>
