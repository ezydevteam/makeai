<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings } from '../../types'

defineProps<{
    isOpen: boolean
    settings: AssistantSettings
    unreadCount: number
}>()

defineEmits<{
    (e: 'click'): void
}>()

const { t } = useTranslate()
</script>

<template>
    <button
        class="ai-trigger-fab relative flex h-14 w-14 cursor-pointer items-center justify-center rounded-full border-0 shadow-lg transition-all duration-200 hover:scale-105"
        :aria-label="isOpen ? t('Close assistant') : t('Open assistant')"
        @click="$emit('click')"
    >
        <!-- The uploaded avatar IS the bubble, filling the circle edge to edge. The clipping
             lives on this inner wrapper, not the button, so the unread badge below isn't
             clipped along with it. Falls back to a robot glyph on the accent colour. -->
        <span
            v-if="!isOpen"
            class="flex h-full w-full items-center justify-center overflow-hidden rounded-full"
        >
            <img
                v-if="settings.avatar_url"
                :src="settings.avatar_url"
                :alt="settings.assistant_name ?? ''"
                class="h-full w-full object-cover"
            />
            <i v-else class="ti ti-robot text-2xl text-white"></i>
        </span>

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
