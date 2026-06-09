<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { useChat, ChatProduct } from '@/Composables/useChat'

const { t } = useTranslate()
const chat = inject<ReturnType<typeof useChat>>('chat')!

const showAll = ref(false)

const topProducts = computed(() => chat.products.value.slice(0, 2))
const moreProducts = computed(() => chat.products.value.slice(2))

const getProductIconClass = (icon?: string | null) => {
    const value = icon?.trim()
    if (!value) return 'ti ti-grid-dots'

    if (value === 'ti-world-search' || value === 'ti ti-world-search') {
        return 'ti ti-world-www'
    }

    if (value.startsWith('ti ')) return value
    if (value.startsWith('ti-')) return `ti ${value}`
    return `ti ti-${value}`
}

const selectProduct = (p: ChatProduct) => {
    chat.newChat(p)
}
</script>

<template>
    <div class="flex-1 flex items-center justify-center overflow-y-auto px-6 py-8">
        <div class="max-w-[768px] w-full text-center">
            <div class="mb-6">
                <div class="w-14 h-14 mx-auto mb-5 rounded-2xl bg-black/5 dark:bg-white/5 flex items-center justify-center">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-gray-700 dark:text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                    </svg>
                </div>
                <h1 class="text-[26px] font-semibold text-[#1a1a1a] dark:text-[#e8e6e3] mb-2">{{ t('How can I help you today?') }}</h1>
                <p class="text-[15px] text-[#6e6a65] dark:text-white/40">
                    {{ chat.selectedProduct.value
                        ? t('Using :product mode', { product: chat.selectedProduct.value.name })
                        : t('Choose a product below or start typing')
                    }}
                </p>
            </div>

            <!-- Product Grid: 3+3+2 -->
            <div v-if="!chat.selectedProduct.value && chat.products.value.length" class="grid grid-cols-3 gap-3 mb-6">
                <button
                    v-for="p in chat.products.value"
                    :key="p.slug"
                    class="flex flex-col gap-2 p-4 rounded-xl bg-white dark:bg-white/5 border border-black/5 dark:border-white/5 hover:border-black/10 dark:hover:border-white/10 hover:shadow-sm transition-all text-left group"
                    @click="chat.newChat(p)"
                >
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: p.color_hex + '18', color: p.color_hex }">
                        <i :class="getProductIconClass(p.icon)" class="text-[18px]"></i>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-[#1a1a1a] dark:text-white/80">{{ p.name }}</div>
                        <div class="text-[11px] text-[#6e6a65] dark:text-white/40 mt-1 leading-snug line-clamp-2">{{ p.system_prompt?.split('.')[0] || '' }}</div>
                    </div>
                </button>
            </div>

            <!-- Starter Prompts -->
            <div v-if="chat.selectedProduct.value" class="flex flex-wrap justify-center gap-2">
                <button
                    v-for="(prompt, i) in chat.selectedProduct.value.starter_prompts?.slice(0, 6)"
                    :key="i"
                    class="px-4 py-2.5 rounded-xl bg-white dark:bg-white/5 border border-black/5 dark:border-white/5 text-[14px] text-[#6e6a65] dark:text-white/50 hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
                    @click="chat.sendMessage(prompt, chat.selectedProduct.value!.slug)"
                >
                    {{ prompt }}
                </button>
            </div>
        </div>
    </div>
</template>
