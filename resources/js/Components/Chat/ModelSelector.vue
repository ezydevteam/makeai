<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { usePage } from '@inertiajs/vue3'
import type { useChat } from '@/Composables/useChat'

const chat = inject<ReturnType<typeof useChat>>('chat')!
const page = usePage()
const open = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

onClickOutside(dropdownRef, () => { open.value = false })

const allowModelSelect = (page.props.allow_model_select as boolean) ?? true
const availableModels: string[] = (page.props.available_models as string[]) || []
const defaultModel = (page.props.default_chat_model as string) || 'gpt-4o-mini'

const friendlyMap: Record<string, string> = {
    'gpt-4o': 'GPT-4o',
    'gpt-4o-mini': 'GPT-4o Mini',
    'gpt-4-turbo': 'GPT-4 Turbo',
    'gpt-4': 'GPT-4',
    'gpt-3.5-turbo': 'GPT-3.5 Turbo',
    'o3-mini': 'o3 Mini',
    'o1-mini': 'o1 Mini',
    'o1': 'o1',
    'claude-3-opus': 'Claude 3 Opus',
    'claude-3-sonnet': 'Claude 3.5 Sonnet',
    'claude-3-haiku': 'Claude 3 Haiku',
    'claude-3-5-sonnet': 'Claude 3.5 Sonnet',
    'claude-3-5-haiku': 'Claude 3.5 Haiku',
    'claude-sonnet-4-5': 'Claude Sonnet 4.5',
    'claude-haiku-4-5': 'Claude Haiku 4.5',
    'gemini-2.5-flash': 'Gemini 2.5 Flash',
    'gemini-2.5-pro': 'Gemini 2.5 Pro',
    'gemini-2.0-flash': 'Gemini 2.0 Flash',
    'gemini-1.5-pro': 'Gemini 1.5 Pro',
    'gemini-1.5-flash': 'Gemini 1.5 Flash',
    'deepseek-v3': 'DeepSeek V3',
    'deepseek-r1': 'DeepSeek R1',
    'mistral-large': 'Mistral Large',
    'perplexity-sonar': 'Perplexity Sonar',
    'dall-e-3': 'DALL·E 3',
    'flux-pro': 'Flux Pro',
    'ideogram': 'Ideogram',
    'stability-sd3': 'Stable Diffusion 3',
}

function friendlyName(model: string): string {
    return friendlyMap[model] || model.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const providerGroups = computed(() => {
    const groups: Record<string, string[]> = {}
    const all = [...availableModels]
    if (chat.selectedProduct.value?.preferred_models) {
        for (const m of chat.selectedProduct.value.preferred_models) {
            if (!all.includes(m)) all.push(m)
        }
    }
    for (const m of all) {
        let provider = 'Other'
        if (m.startsWith('gpt') || m.startsWith('o1') || m.startsWith('o3') || m.startsWith('dall')) provider = 'OpenAI'
        else if (m.startsWith('claude')) provider = 'Anthropic'
        else if (m.startsWith('gemini')) provider = 'Google'
        else if (m.startsWith('deepseek')) provider = 'DeepSeek'
        else if (m.startsWith('mistral')) provider = 'Mistral'
        else if (m.startsWith('perplexity')) provider = 'Perplexity'
        else if (m.startsWith('flux')) provider = 'Flux'
        else if (m.startsWith('ideogram')) provider = 'Ideogram'
        else if (m.startsWith('stability')) provider = 'Stability'
        if (!groups[provider]) groups[provider] = []
        groups[provider].push(m)
    }
    return groups
})

function resolveModel(): string {
    const selected = chat.selectedModel.value
    if (selected && availableModels.includes(selected)) return friendlyName(selected)

    const preferred = chat.selectedProduct.value?.preferred_models
    if (preferred && preferred.length) {
        for (const m of preferred) {
            if (availableModels.includes(m)) {
                chat.selectedModel.value = m
                return friendlyName(m)
            }
        }
    }

    if (defaultModel && availableModels.includes(defaultModel)) {
        chat.selectedModel.value = defaultModel
        return friendlyName(defaultModel)
    }

    if (availableModels.length > 0) {
        chat.selectedModel.value = availableModels[0]
        return friendlyName(availableModels[0])
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
            <template v-if="Object.keys(providerGroups).length">
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
            <div v-else class="px-3 py-2 text-xs text-[#b0aca8] dark:text-white/20">{{ 'No models available' }}</div>
        </div>
    </div>
</template>
