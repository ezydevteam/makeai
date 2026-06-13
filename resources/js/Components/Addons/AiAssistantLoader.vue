<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const settings = computed(() => page.props.aiAssistant as Record<string, any> | null)

const aiAssistantComponents = import.meta.glob<{ default: Component }>(
    '../../../../addons/ai-assistant/resources/js/Components/Assistant/*.vue'
)

const FloatingAssistant = computed(() => {
    if (!settings.value) return null
    const key = '../../../../addons/ai-assistant/resources/js/Components/Assistant/FloatingAssistant.vue'
    const loader = aiAssistantComponents[key]
    if (!loader) return null
    return defineAsyncComponent(loader)
})
</script>

<template>
    <component :is="FloatingAssistant" v-if="FloatingAssistant" />
</template>
