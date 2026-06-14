<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'

const props = defineProps<{
    isOpen: boolean
    settings: Record<string, any>
    unreadCount: number
}>()

defineEmits<{
    (e: 'click'): void
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
</script>

<template>
    <button
        class="ai-trigger-fab w-14 h-14 rounded-full flex items-center justify-center shadow-lg border-0 cursor-pointer transition-all duration-200 hover:scale-105 relative"
        :aria-label="isOpen ? $t('Close assistant') : $t('Open assistant')"
        @click="$emit('click')"
    >
        <!-- Chat icon -->
        <i v-if="!isOpen" :class="[settings.widget_icon || 'ti ti-robot', 'text-2xl text-white']"></i>
        <!-- Close icon -->
        <svg v-else class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>

        <!-- Unread badge -->
        <span
            v-if="unreadCount > 0"
            class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
        >
            {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
    </button>
</template>

<style scoped>
.ai-trigger-fab {
    background: var(--ai-accent, #1F75FE);
}
</style>
