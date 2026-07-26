<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useCommandPalette, isSectionHeader, type PaletteItem } from '@/Composables/useCommandPalette'
import { useTranslate } from '@/Composables/useTranslate'

const {
    query, isOpen, selectedIndex: idx, activeTab, tabs, totalMatches,
    results, flatItems, close, setTab, execute,
} = useCommandPalette()

const { t } = useTranslate()

const searchInput = ref<HTMLInputElement | null>(null)
const resultsPane = ref<HTMLElement | null>(null)

watch(isOpen, (val) => {
    if (val) {
        requestAnimationFrame(() => {
            searchInput.value?.focus()
        })
    }
})

// Keep the highlighted row in view when the cursor is driven by the keyboard.
watch(idx, async () => {
    await nextTick()
    resultsPane.value?.querySelector('[data-selected="true"]')?.scrollIntoView({ block: 'nearest' })
})

function onItemClick(item: PaletteItem, index: number) {
    idx.value = index
    execute()
}

/**
 * Tab keys and group names reach the view as variables, so `translations:sync`
 * cannot pick them up at the call site — these maps carry the literal source
 * strings. They are functions rather than constants so a language switch on a
 * persistent layout still re-renders translated.
 */
function tabLabel(key: string, fallback: string): string {
    const labels: Record<string, string> = {
        all: t('All'),
        actions: t('Actions'),
        navigation: t('Navigate'),
        tools: t('AI Tools'),
        documents: t('Documents'),
        chats: t('Chats'),
        admin: t('Admin'),
    }

    return labels[key] ?? fallback
}

function groupLabel(group: string): string {
    const labels: Record<string, string> = {
        Actions: t('Actions'),
        Navigation: t('Navigation'),
        'AI Tools': t('AI Tools'),
        'Recent Documents': t('Recent Documents'),
        'Recent Chats': t('Recent Chats'),
        Admin: t('Admin'),
    }

    return labels[group] ?? group
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
            <!--
                `frontend-theme-vars` carries the admin's configured palette. Teleported
                to <body>, this sits outside `.frontend-theme`, so every `primary-*`
                utility below would otherwise resolve against the stock app.css blue.
            -->
            <div
                v-if="isOpen"
                class="frontend-theme-vars fixed inset-0 z-[100] flex items-start justify-center bg-black/45 pt-[12vh] backdrop-blur-sm"
                @click.self="close"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                >
                    <div class="mx-4 w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                        <!-- Search -->
                        <div class="flex items-center gap-3 px-5 py-4">
                            <i class="ti ti-search text-lg text-gray-400"></i>
                            <input
                                ref="searchInput"
                                v-model="query"
                                type="text"
                                :placeholder="t('Search tools, documents, settings...')"
                                :aria-label="t('Search')"
                                class="flex-1 border-none bg-transparent text-base text-gray-900 outline-none placeholder:text-gray-400 dark:text-white"
                            />
                            <button
                                v-if="query"
                                type="button"
                                class="inline-flex justify-center items-center w-6 h-6 rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-white"
                                :aria-label="t('Clear search')"
                                @click="query = ''"
                            >
                                <i class="ti ti-x text-sm"></i>
                            </button>
                            <kbd class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-400 dark:border-gray-700 dark:bg-gray-800">Esc</kbd>
                        </div>

                        <!-- Tabs -->
                        <div class="border-y border-gray-100 px-3 dark:border-gray-800" role="tablist">
                            <div class="-mb-px flex gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeTab === tab.key"
                                    :class="[
                                        'inline-flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors',
                                        activeTab === tab.key
                                            ? 'border-primary-500 text-primary-500'
                                            : tab.count === 0
                                                ? 'border-transparent text-gray-300 hover:text-gray-400 dark:text-gray-600 dark:hover:text-gray-500'
                                                : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200',
                                    ]"
                                    @click="setTab(tab.key)"
                                >
                                    <i :class="tab.icon" class="text-sm"></i>
                                    <span>{{ tabLabel(tab.key, tab.label) }}</span>
                                    <span
                                        :class="[
                                            'rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none',
                                            activeTab === tab.key
                                                ? 'bg-primary-500/10 text-primary-500'
                                                : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500',
                                        ]"
                                    >{{ tab.count }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Results -->
                        <div ref="resultsPane" class="max-h-[22rem] overflow-y-auto p-2">
                            <template v-for="(item, i) in results" :key="item.id">
                                <!-- Section header (All tab, no query) -->
                                <div
                                    v-if="isSectionHeader(item)"
                                    class="px-3 pb-1 pt-3 text-[10px] font-bold uppercase tracking-widest text-gray-400"
                                >
                                    {{ groupLabel(item.group) }}
                                </div>

                                <!-- Regular item -->
                                <button
                                    v-else
                                    type="button"
                                    :data-selected="idx === i"
                                    :class="[
                                        'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors',
                                        idx === i
                                            ? 'bg-primary-500/10 text-primary-500 dark:bg-primary-500/20 dark:text-primary-300'
                                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800',
                                    ]"
                                    @click="onItemClick(item, i)"
                                    @mouseenter="idx = i"
                                >
                                    <!-- Tool colors come from the database; everything else falls back to neutral tokens. -->
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-base"
                                        :class="item.color ? '' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
                                        :style="item.color ? { background: item.color + '20', color: item.color } : undefined"
                                    >
                                        <i :class="item.icon || 'ti ti-wand'" class="text-lg"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold">{{ item.label }}</span>
                                        <span v-if="item.sublabel" class="mt-0.5 block truncate text-xs text-gray-400">{{ item.sublabel }}</span>
                                    </span>
                                    <span v-if="activeTab === 'all' && query" class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-gray-300 dark:text-gray-600">{{ groupLabel(item.group) }}</span>
                                </button>
                            </template>

                            <!-- Empty: nothing in this tab, but hits elsewhere -->
                            <div v-if="flatItems.length === 0" class="px-4 py-12 text-center">
                                <i class="ti ti-mood-empty mb-2 block text-2xl text-gray-300"></i>
                                <p class="text-sm text-gray-400">
                                    <template v-if="query">{{ t('No results for ":query"', { query }) }}</template>
                                    <template v-else>{{ t('Nothing here yet') }}</template>
                                </p>
                                <button
                                    v-if="activeTab !== 'all' && totalMatches > 0"
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-primary-500 transition-colors hover:bg-primary-500/10"
                                    @click="setTab('all')"
                                >
                                    <i class="ti ti-arrow-back-up text-sm"></i>
                                    {{ t('One result in other tabs|:count results in other tabs', { count: totalMatches }) }}
                                </button>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center gap-3 border-t border-gray-100 px-5 py-2.5 dark:border-gray-800">
                            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-gray-100 text-[9px] font-semibold dark:bg-gray-800">↑↓</span>
                                {{ t('Navigate') }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                <span class="inline-flex h-5 items-center justify-center rounded bg-gray-100 px-1.5 text-[9px] font-semibold dark:bg-gray-800">Tab</span>
                                {{ t('Switch tab') }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-gray-100 text-[9px] font-semibold dark:bg-gray-800">↵</span>
                                {{ t('Select') }}
                            </span>
                            <span class="ml-auto inline-flex items-center gap-1 text-[10px] text-gray-400">
                                <span class="inline-flex h-5 items-center justify-center rounded bg-gray-100 px-1.5 text-[9px] font-semibold dark:bg-gray-800">?</span>
                                {{ t('Shortcuts') }}
                            </span>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
