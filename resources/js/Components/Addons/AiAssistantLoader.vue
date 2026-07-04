<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const settings = computed(() => page.props.aiAssistant as Record<string, any> | null)

const isChatPage = computed(() => {
    const url = page.url
    return url === '/chat' || url.startsWith('/chat/') || url.startsWith('/chat?')
})

const aiAssistantComponents = import.meta.glob<{ default: Component }>(
    '../../../../addons/ai-assistant/resources/js/Components/Assistant/*.vue'
)

const FloatingAssistant = computed(() => {
    if (!settings.value || isChatPage.value) return null
    const key = '../../../../addons/ai-assistant/resources/js/Components/Assistant/FloatingAssistant.vue'
    const loader = aiAssistantComponents[key]
    if (!loader) return null
    return defineAsyncComponent(loader)
})
</script>

<template>
    <component :is="FloatingAssistant" v-if="FloatingAssistant" />
</template>
