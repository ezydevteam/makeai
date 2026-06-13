<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AssistantTrigger from './AssistantTrigger.vue'
import AssistantWindow from './AssistantWindow.vue'

const page = usePage()
const settings = computed(() => (page.props.aiAssistant as Record<string, any>) ?? null)
const isOpen = ref(false)

const sessionId = ref('')
if (typeof sessionStorage !== 'undefined') {
    const existing = sessionStorage.getItem('ai_assistant_session_id')
    if (existing) {
        sessionId.value = existing
    } else {
        sessionId.value = 'ai_' + Math.random().toString(36).slice(2) + Date.now().toString(36)
        sessionStorage.setItem('ai_assistant_session_id', sessionId.value)
    }
}

const toggle = () => {
    isOpen.value = !isOpen.value
}

const close = () => {
    isOpen.value = false
}
</script>

<template>
    <div
        v-if="settings"
        class="ai-assistant-root fixed z-50 flex flex-col items-end gap-3"
        :class="settings.position === 'bottom-left' ? 'bottom-6 left-6 items-start' : 'bottom-6 right-6 items-end'"
    >
        <AssistantWindow
            v-if="isOpen"
            :settings="settings"
            :session-id="sessionId"
            :style="{ '--ai-accent': settings.accent_color ?? '#1F75FE' }"
            @close="close"
        />

        <AssistantTrigger
            :is-open="isOpen"
            :settings="settings"
            :unread-count="0"
            :style="{ '--ai-accent': settings.accent_color ?? '#1F75FE' }"
            @click="toggle"
        />
    </div>
</template>
