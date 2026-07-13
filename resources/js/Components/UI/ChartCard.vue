<script setup lang="ts">
/**
 * Presentational card chrome for dashboard chart/list panels.
 *
 * Renders the shared border/padding/shadow shell + a standard header (title
 * plus an optional #action slot). The panel body — canvas, list, or empty
 * state — is provided via the default slot and stays owned by the parent, so
 * chart template refs and per-panel logic are untouched. Grid/flex/height
 * modifiers pass straight through to the root via Vue's class fallthrough.
 */
defineProps<{ title?: string }>()
</script>

<template>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
        <div v-if="title || $slots.action" class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ title }}</h3>
            <slot name="action" />
        </div>
        <slot />
    </div>
</template>
