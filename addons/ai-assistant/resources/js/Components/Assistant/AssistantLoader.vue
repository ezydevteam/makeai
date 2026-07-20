<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'

/**
 * Shared loading state for the widget's panels. A single, consistent spinner so every tab
 * (Home featured, Help list/search, article reader) reads the same way while it fetches.
 */
withDefaults(defineProps<{
    label?: string | null
    /** 'block' fills the panel and centres; 'inline' is a small spinner for tight spots. */
    variant?: 'block' | 'inline'
}>(), {
    label: null,
    variant: 'block',
})

const { t } = useTranslate()
</script>

<template>
    <span v-if="variant === 'inline'" class="ai-spinner ai-spinner--sm" aria-hidden="true"></span>

    <div v-else class="flex flex-1 flex-col items-center justify-center gap-3 py-12 text-center">
        <span class="ai-spinner" aria-hidden="true"></span>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ label ?? t('Loading…') }}</p>
    </div>
</template>

<style scoped>
.ai-spinner {
    display: inline-block;
    width: 26px;
    height: 26px;
    border: 2.5px solid color-mix(in srgb, var(--ai-accent, #1F75FE) 25%, transparent);
    border-top-color: var(--ai-accent, #1F75FE);
    border-radius: 9999px;
    animation: ai-spin 0.7s linear infinite;
}

.ai-spinner--sm {
    width: 15px;
    height: 15px;
    border-width: 2px;
}

@keyframes ai-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
