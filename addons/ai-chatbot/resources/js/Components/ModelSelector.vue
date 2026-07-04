<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { usePage } from '@inertiajs/vue3'
import type { useChat } from '../Composables/useChat'
import { useTranslate } from '@/Composables/useTranslate'
import { useAiModel } from '@/Composables/useAiModel'

const chat = inject<ReturnType<typeof useChat>>('chat')!
const page = usePage()
const { t } = useTranslate()
const open = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

const { friendlyModelName, modelProviderName } = useAiModel()

onClickOutside(dropdownRef, () => { open.value = false })

const allowModelSelect = computed(() => {
    const chatbot = page.props.chatbot as any
    if (page.props.allow_model_select !== undefined) {
        return (page.props.allow_model_select as boolean)
    }
    if (chatbot?.allowModelSelect !== undefined) {
        return (chatbot.allowModelSelect as boolean)
    }
    return true
})

const availableModels = computed<string[]>(() => {
    const chatbot = page.props.chatbot as any
    if (page.props.available_models !== undefined) {
        return (page.props.available_models as string[]) || []
    }
    if (chatbot?.availableModels !== undefined) {
        return (chatbot.availableModels as string[]) || []
    }
    return []
})

const defaultModel = computed<string>(() => {
    const chatbot = page.props.chatbot as any
    if (page.props.default_chat_model !== undefined) {
        return (page.props.default_chat_model as string)
    }
    if (chatbot?.defaultChatModel !== undefined) {
        return (chatbot.defaultChatModel as string)
    }
    return availableModels.value[0] || ''
})

const showProviderModels = computed(() => {
    const chatbot = page.props.chatbot as any
    if (page.props.show_provider_models !== undefined) {
        return (page.props.show_provider_models as boolean)
    }
    if (chatbot?.showProviderModels !== undefined) {
        return (chatbot.showProviderModels as boolean)
    }
    return true
})

const showCustomModels = computed(() => {
    const chatbot = page.props.chatbot as any
    if (page.props.show_custom_models !== undefined) {
        return (page.props.show_custom_models as boolean)
    }
    if (chatbot?.showCustomModels !== undefined) {
        return (chatbot.showCustomModels as boolean)
    }
    return false
})

const customModels = computed(() => {
    const chatbot = page.props.chatbot as any
    if (page.props.custom_models !== undefined) {
        return (page.props.custom_models as Array<{ id: string; name: string; description: string; model: string }>) || []
    }
    if (chatbot?.customModels !== undefined) {
        return (chatbot.customModels as Array<{ id: string; name: string; description: string; model: string }>) || []
    }
    return []
})

const siteName = computed(() => (page.props.branding as any)?.site_name || (page.props.app as any)?.name || 'MakeAI')

function friendlyName(model: string): string {
    return friendlyModelName(model, customModels.value)
}

const providerGroups = computed(() => {
    const groups: Record<string, string[]> = {}
    const all = [...availableModels.value]
    if (chat.selectedMode.value?.preferred_models) {
        for (const m of chat.selectedMode.value.preferred_models) {
            if (!all.includes(m)) all.push(m)
        }
    }
    for (const m of all) {
        const provider = modelProviderName(m)
        if (!groups[provider]) groups[provider] = []
        groups[provider].push(m)
    }
    return groups
})

function resolveModel(): string {
    const selected = chat.selectedModel.value

    if (showCustomModels.value && customModels.value.length) {
        const custom = customModels.value.find(cm => cm.id === selected)
        if (custom) return custom.name
    }

    if (showProviderModels.value && selected && availableModels.value.includes(selected)) {
        return friendlyName(selected)
    }

    const preferred = chat.selectedMode.value?.preferred_models
    if (preferred && preferred.length) {
        for (const m of preferred) {
            if (showCustomModels.value && customModels.value.some(cm => cm.id === m)) {
                chat.selectedModel.value = m
                return friendlyName(m)
            }
            if (showProviderModels.value && availableModels.value.includes(m)) {
                chat.selectedModel.value = m
                return friendlyName(m)
            }
        }
    }

    if (showProviderModels.value && defaultModel.value && availableModels.value.includes(defaultModel.value)) {
        chat.selectedModel.value = defaultModel.value
        return friendlyName(defaultModel.value)
    }

    if (showCustomModels.value && customModels.value.length > 0) {
        chat.selectedModel.value = customModels.value[0].id
        return customModels.value[0].name
    }

    if (showProviderModels.value && availableModels.value.length > 0) {
        chat.selectedModel.value = availableModels.value[0]
        return friendlyName(availableModels.value[0])
    }

    return 'No model'
}

const displayModel = computed(() => resolveModel())

const selectModel = (model: string) => {
    chat.selectedModel.value = model
    open.value = false
}

const toggle = () => { open.value = !open.value }
</script>

<template>
    <div v-if="allowModelSelect" class="relative shrink-0">
        <button
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-black/5 dark:bg-white/8 hover:bg-black/10 dark:hover:bg-white/12 text-[11px] font-medium text-[#6e6a65] dark:text-white/40 transition-colors whitespace-nowrap"
            :class="{ '!text-amber-600 dark:!text-amber-400': displayModel === 'No model' }"
            @click="toggle"
        >
            <span>{{ displayModel }}</span>
            <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </button>

        <div v-if="open" ref="dropdownRef" class="absolute bottom-full right-0 mb-1.5 min-w-[190px] max-h-[280px] overflow-y-auto bg-white dark:bg-[#252525] border border-black/5 dark:border-white/10 rounded-xl shadow-xl py-1 z-50">
            <!-- Custom Models Section -->
            <template v-if="showCustomModels && customModels.length">
                <div class="px-3 py-1.5 text-[10px] font-semibold text-primary-500 uppercase tracking-wider">{{ t(':site_name Models', { site_name: siteName }) }}</div>
                <button
                    v-for="cm in customModels"
                    :key="cm.id"
                    class="block w-full px-3 py-1.5 pl-5 text-left text-xs hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                    :class="{ 'bg-black/5 dark:bg-white/10 font-medium': chat.selectedModel.value === cm.id, 'text-[#1a1a1a] dark:text-white/70': true }"
                    @click="selectModel(cm.id)"
                >
                    <div class="font-medium text-gray-900 dark:text-white">{{ cm.name }}</div>
                    <div v-if="cm.description" class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">{{ cm.description }}</div>
                </button>
                <div class="border-t border-black/5 dark:border-white/10 my-1"></div>
            </template>

            <!-- Standard Models Grouped by Provider -->
            <template v-if="showProviderModels && Object.keys(providerGroups).length">
                <div v-for="(models, provider) in providerGroups" :key="provider">
                    <div class="px-3 py-1.5 text-[10px] font-semibold text-[#b0aca8] dark:text-white/30 uppercase tracking-wider">{{ provider }}</div>
                    <button
                        v-for="m in models"
                        :key="m"
                        class="block w-full px-3 py-1.5 pl-5 text-left text-xs hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                        :class="{ 'bg-black/5 dark:bg-white/10 font-medium': chat.selectedModel.value === m, 'text-[#1a1a1a] dark:text-white/70': true }"
                        @click="selectModel(m)"
                    >
                        {{ friendlyName(m) }}
                    </button>
                </div>
            </template>

            <div v-else-if="!showCustomModels || !customModels.length" class="px-3 py-2 text-xs text-[#b0aca8] dark:text-white/20">{{ 'No models available' }}</div>
        </div>
    </div>
</template>
