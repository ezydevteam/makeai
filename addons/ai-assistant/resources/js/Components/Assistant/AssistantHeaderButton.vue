<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings } from '../../types'

/**
 * The header entry point, used when Widget Position is "header-button".
 *
 * Rendered via <Teleport> into whatever header slot the layout provides, so the addon
 * never has to reach into theme markup. Styled to sit among the other header icons
 * (notifications, dark mode, …) rather than to look like the floating bubble.
 */
defineProps<{
    settings: AssistantSettings
    isOpen: boolean
}>()

defineEmits<{ (e: 'click'): void }>()

const { t } = useTranslate()
</script>

<template>
    <button
        type="button"
        class="ai-header-btn relative flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 py-1 leading-none transition-colors hover:bg-black/5 dark:hover:bg-white/10"
        :class="{ 'ai-header-btn--active': isOpen }"
        :aria-label="isOpen ? t('Close assistant') : t('Open assistant')"
        :aria-expanded="isOpen"
        @click="$emit('click')"
    >
        <!-- The avatar doubles as the icon here, exactly as it does on the bubble. -->
        <span v-if="settings.avatar_url" class="h-6 w-6 shrink-0 overflow-hidden rounded-full">
            <img :src="settings.avatar_url" :alt="settings.assistant_name ?? ''" class="h-full w-full object-cover" />
        </span>
        <i v-else class="ti ti-robot text-xl leading-none"></i>

        <span class="max-w-[84px] truncate text-[10px] font-medium leading-none">
            {{ settings.assistant_name ?? t('Assistant') }}
        </span>
    </button>
</template>

<style scoped>
/*
 * Inherit the colour of the slot we're teleported into, rather than hardcoding a grey.
 * The theme sets that colour per its own rules — white while the header is transparent
 * over the hero, otherwise the configured header text colour — so the assistant matches
 * the notification bell and language switcher instead of staying dark on a dark hero.
 *
 * Declared before the --active rule so the accent tint still wins when the panel is open.
 */
.ai-header-btn {
    color: inherit;
}

.ai-header-btn--active {
    color: var(--ai-accent, #1F75FE);
    background-color: color-mix(in srgb, var(--ai-accent, #1F75FE) 12%, transparent);
}
</style>
