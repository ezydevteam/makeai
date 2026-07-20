<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings } from '../../types'

withDefaults(defineProps<{
    settings: AssistantSettings
    /** Show the chat-history / new-chat controls (chat panel only). */
    showHistory?: boolean
    /**
     * Home's header: just the assistant name, transparent and borderless, so the hero
     * artwork behind it runs unbroken to the top of the window. The avatar isn't repeated
     * here because Home already shows a large one right below.
     */
    minimal?: boolean
}>(), {
    showHistory: false,
    minimal: false,
})

defineEmits<{
    (e: 'close'): void
    (e: 'history'): void
    (e: 'new-chat'): void
}>()

const { t } = useTranslate()
</script>

<template>
    <!-- z-10 keeps the header above the Home hero artwork; it has no background of its own
         in minimal mode, so the artwork shows through it. -->
    <div
        class="ai-header relative z-10 flex shrink-0 items-center gap-3 px-4 py-3"
        :class="minimal ? '' : 'border-b border-gray-200 dark:border-gray-700'"
    >
        <!-- Avatar -->
        <div
            v-if="!minimal"
            class="w-9 h-9 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0 flex items-center justify-center"
        >
            <img
                v-if="settings.avatar_url"
                :src="settings.avatar_url"
                :alt="settings.assistant_name ?? ''"
                class="w-full h-full object-cover"
            />
            <svg v-else class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
            </svg>
        </div>

        <!-- Name (+ designation, except on Home where the greeting below says it better) -->
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                {{ settings.assistant_name ?? 'AI Assistant' }}
            </div>
            <div v-if="!minimal" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ settings.designation ?? t('Your AI Helper') }}
            </div>
        </div>

        <!-- New chat -->
        <button
            v-if="showHistory"
            class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0"
            :title="t('New chat')"
            :aria-label="t('New chat')"
            @click="$emit('new-chat')"
        >
            <i class="ti ti-edit text-[17px]"></i>
        </button>

        <!-- Chat history -->
        <button
            v-if="showHistory"
            class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0"
            :title="t('Chat history')"
            :aria-label="t('Chat history')"
            @click="$emit('history')"
        >
            <i class="ti ti-history text-[17px]"></i>
        </button>

        <!-- Close button — shown on every tab, Home included. It's the only way out when the
             panel is a full-height rail (header-button mode), where there's no bubble to
             click a second time. -->
        <button
            class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0"
            :aria-label="t('Close')"
            @click="$emit('close')"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
