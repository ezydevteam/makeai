<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { AssistantSettings } from '../types'

const page = usePage()

/*
 * `aiAssistant` is NULL whenever the assistant must not be offered to this visitor —
 * the addon is inactive, or they fail the admin's show_to rule. Visibility is decided
 * server-side; there is deliberately no client-side gating left here beyond "is the
 * prop present", so there is nothing to keep in sync with the backend.
 */
const settings = computed(() => (page.props.aiAssistant as AssistantSettings | null) ?? null)

// The full-page chatbot already is a chat surface; a floating bubble on top of it is noise.
const isChatPage = computed(() => {
    const url = page.url
    return url === '/chat' || url.startsWith('/chat/') || url.startsWith('/chat?')
})

const aiAssistantComponents = import.meta.glob<{ default: Component }>('./Assistant/*.vue')

const FloatingAssistant = computed(() => {
    if (!settings.value || isChatPage.value) return null
    const loader = aiAssistantComponents['./Assistant/FloatingAssistant.vue']
    if (!loader) return null
    return defineAsyncComponent(loader)
})
</script>

<template>
    <component :is="FloatingAssistant" v-if="FloatingAssistant" />
</template>
