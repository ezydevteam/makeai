<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantPanels, PanelId } from '../../types'

const props = defineProps<{
    panels: AssistantPanels
    active: PanelId
}>()

defineEmits<{
    (e: 'change', panel: PanelId): void
}>()

const { t } = useTranslate()

const tabs = computed(() => {
    const all: Array<{ id: PanelId; icon: string; label: string }> = [
        { id: 'home', icon: 'ti ti-home', label: t('Home') },
        { id: 'chat', icon: 'ti ti-message-2', label: t('Chat') },
        { id: 'help', icon: 'ti ti-help-circle', label: t('Help') },
    ]
    // Message isn't a primary tab — it's reached from Help/Home — so it's not listed here.
    return all.filter((tab) => props.panels[tab.id])
})
</script>

<template>
    <nav
        v-if="tabs.length > 1"
        class="flex shrink-0 items-stretch border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
    >
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] transition-colors"
            :class="active === tab.id
                ? 'text-[var(--ai-accent,#1F75FE)]'
                : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
            :aria-current="active === tab.id ? 'page' : undefined"
            @click="$emit('change', tab.id)"
        >
            <i :class="[tab.icon, 'text-lg']"></i>
            <span>{{ tab.label }}</span>
        </button>
    </nav>
</template>
