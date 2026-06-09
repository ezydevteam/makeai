<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useCommandPalette, type PaletteItem } from '@/Composables/useCommandPalette'

const {
    query, isOpen, selectedIndex: idx,
    results, flatItems, close, selectNext, selectPrev, execute,
} = useCommandPalette()

const searchInput = ref<HTMLInputElement | null>(null)

watch(isOpen, (val) => {
    if (val) {
        requestAnimationFrame(() => {
            searchInput.value?.focus()
        })
    }
})

function onItemClick(item: PaletteItem) {
    if (item.action) {
        item.action()
        close()
    }
}

function isSectionHeader(item: PaletteItem): boolean {
    return item.id.startsWith('header-')
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[100] flex items-start justify-center bg-black/45 pt-[15vh] backdrop-blur-sm"
                @click.self="close"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                >
                    <div class="mx-4 w-full max-w-xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                        <!-- Search -->
                        <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <i class="ti ti-search text-lg text-gray-400"></i>
                            <input
                                ref="searchInput"
                                v-model="query"
                                type="text"
                                placeholder="Search tools, documents, settings..."
                                class="flex-1 border-none bg-transparent text-base text-gray-900 outline-none placeholder:text-gray-400 dark:text-white"
                                @keydown.arrow-down.prevent="selectNext"
                                @keydown.arrow-up.prevent="selectPrev"
                                @keydown.enter.prevent="execute"
                                @keydown.escape="close"
                            />
                            <kbd class="rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-400 dark:border-gray-700 dark:bg-gray-800">Esc</kbd>
                        </div>

                        <!-- Results -->
                        <div class="max-h-80 overflow-y-auto p-2">
                            <template v-for="(item, i) in results" :key="item.id">
                                <!-- Section header -->
                                <div
                                    v-if="isSectionHeader(item)"
                                    class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-widest text-gray-400"
                                >
                                    {{ item.group }}
                                </div>

                                <!-- Regular item -->
                                <button
                                    v-else
                                    :class="[
                                        'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors',
                                        idx === i
                                            ? 'bg-[#1F75FE]/10 text-[#1F75FE] dark:bg-[#1F75FE]/20 dark:text-blue-300'
                                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800',
                                    ]"
                                    @click="onItemClick(item)"
                                    @mouseenter="idx = i"
                                >
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-base"
                                        :style="item.color ? { background: item.color + '20', color: item.color } : { background: '#f3f4f6', color: '#6b7280' }"
                                    >
                                        <i :class="item.icon || 'ti ti-wand'" class="text-lg"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold truncate">{{ item.label }}</span>
                                        <span v-if="item.sublabel" class="block text-xs text-gray-400 truncate mt-0.5">{{ item.sublabel }}</span>
                                    </span>
                                </button>
                            </template>

                            <div v-if="flatItems.length === 0 && query" class="px-4 py-12 text-center text-sm text-gray-400">
                                <i class="ti ti-mood-empty text-2xl mb-2 block"></i>
                                No results for "{{ query }}"
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center gap-3 border-t border-gray-100 px-5 py-2.5 dark:border-gray-800">
                            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 dark:bg-gray-800 text-[9px] font-semibold">↑↓</span>
                                Navigate
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 dark:bg-gray-800 text-[9px] font-semibold">↵</span>
                                Select
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 dark:bg-gray-800 text-[9px] font-semibold">Esc</span>
                                Dismiss
                            </span>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
