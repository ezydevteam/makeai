<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

type SearchResult = {
    key: string
    title: string
    excerpt: string
    url: string
    meta: string
}

type SearchGroup = {
    type: string
    label: string
    results: SearchResult[]
}

const props = withDefaults(defineProps<{
    context?: 'public' | 'admin'
    compact?: boolean
    enableLiveSearch?: boolean
    showSuggestions?: boolean
}>(), {
    context: 'public',
    compact: false,
    enableLiveSearch: true,
    showSuggestions: true,
})

const { t } = useTranslate()
const query = ref('')
const groups = ref<SearchGroup[]>([])
const suggestions = ref<string[]>([])
const recent = ref<string[]>([])
const loading = ref(false)
const open = ref(false)
const activeIndex = ref(0)
const root = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)
let debounceTimer: number | undefined
let controller: AbortController | null = null

const storageKey = computed(() => `makeai:${props.context}:live-search:recent`)
const flattenedResults = computed(() => groups.value.flatMap((group) => group.results))
const hasResults = computed(() => flattenedResults.value.length > 0)
const showSuggestions = computed(() => query.value.trim().length < 3)
const visibleSuggestions = computed(() => {
    const merged = [...recent.value, ...suggestions.value]

    return Array.from(new Set(merged.map((item) => item.trim()).filter(Boolean))).slice(0, 6)
})

watch(query, () => {
    window.clearTimeout(debounceTimer)
    activeIndex.value = 0

    if (!props.enableLiveSearch) {
        groups.value = []
        loading.value = false
        open.value = false
        return
    }

    if (query.value.trim().length < 3) {
        groups.value = []
        loading.value = false
        open.value = props.showSuggestions
        return
    }

    debounceTimer = window.setTimeout(search, 300)
})

const search = async () => {
    const term = query.value.trim()

    if (term.length < 3 || !props.enableLiveSearch) return

    controller?.abort()
    controller = new AbortController()
    loading.value = true

    try {
        const params = new URLSearchParams({ q: term, context: props.context })
        const response = await fetch(`/live-search?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
        const payload = await response.json()
        groups.value = payload.data?.groups ?? []
        suggestions.value = payload.data?.suggestions ?? []
        open.value = true
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            groups.value = []
        }
    } finally {
        loading.value = false
    }
}

const loadSuggestions = async () => {
    try {
        const params = new URLSearchParams({ context: props.context })
        const response = await fetch(`/live-search?${params.toString()}`, { headers: { Accept: 'application/json' } })
        const payload = await response.json()
        suggestions.value = payload.data?.suggestions ?? []
    } catch {
        suggestions.value = []
    }
}

const focus = () => {
    open.value = props.enableLiveSearch && props.showSuggestions
    if (props.enableLiveSearch && props.showSuggestions && suggestions.value.length === 0) {
        void loadSuggestions()
    }
}

const focusInput = () => {
    input.value?.focus()
    input.value?.select()
    focus()
}

const chooseSuggestion = (suggestion: string) => {
    query.value = suggestion
    void nextTick(search)
}

const chooseResult = (result: SearchResult) => {
    remember(query.value.trim())
    open.value = false
    router.visit(result.url)
}

const remember = (term: string) => {
    if (term.length < 3) return

    recent.value = [term, ...recent.value.filter((item) => item !== term)].slice(0, 5)
    localStorage.setItem(storageKey.value, JSON.stringify(recent.value))
}

const navigate = (direction: 1 | -1) => {
    if (!hasResults.value) return

    const max = flattenedResults.value.length - 1
    activeIndex.value = activeIndex.value + direction

    if (activeIndex.value < 0) activeIndex.value = max
    if (activeIndex.value > max) activeIndex.value = 0
}

const submit = () => {
    const selected = flattenedResults.value[activeIndex.value]

    if (selected) {
        chooseResult(selected)
        return
    }

    if (query.value.trim().length >= 3) {
        if (props.enableLiveSearch) {
            void search()
            return
        }

        router.visit(`/search?q=${encodeURIComponent(query.value.trim())}`)
    }
}

const closeOnEscape = () => {
    if (query.value.trim().length > 0) {
        query.value = ''
        groups.value = []
        loading.value = false
        open.value = props.enableLiveSearch && props.showSuggestions
        return
    }

    open.value = false
    input.value?.blur()
}

const onDocumentClick = (event: MouseEvent) => {
    if (!root.value?.contains(event.target as Node)) {
        open.value = false
    }
}

const onShortcut = (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key === '/') {
        event.preventDefault()
        focusInput()
    }
}

onMounted(() => {
    try {
        const stored = JSON.parse(localStorage.getItem(storageKey.value) ?? '[]')
        recent.value = Array.isArray(stored) ? stored.filter((item) => typeof item === 'string') : []
    } catch {
        recent.value = []
    }

    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onShortcut)
})

onUnmounted(() => {
    controller?.abort()
    window.clearTimeout(debounceTimer)
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onShortcut)
})
</script>

<template>
    <div
        ref="root"
        class="relative transition-[width,transform] duration-200 ease-out"
        :class="compact ? 'w-full' : 'w-full max-w-[30rem] focus-within:scale-[1.01]'"
    >
        <label class="sr-only" :for="`live-search-${context}`">{{ t('Search') }}</label>
        <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
            :id="`live-search-${context}`"
            ref="input"
            v-model="query"
            type="search"
            autocomplete="off"
            data-global-search
            :placeholder="t('Search...')"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 ps-10 pe-24 text-sm text-gray-900 shadow-sm transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            @focus="focus"
            @keydown.down.prevent="navigate(1)"
            @keydown.up.prevent="navigate(-1)"
            @keydown.enter.prevent="submit"
            @keydown.esc.prevent="closeOnEscape"
        >
        <span
            v-if="!loading && !query"
            class="pointer-events-none absolute end-3 top-1/2 inline-flex h-6 -translate-y-1/2 items-center gap-1 rounded-md border border-gray-200 bg-white px-2 text-[11px] font-medium text-gray-400 shadow-sm dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
        >
            <span>Ctrl</span>
            <span>/</span>
        </span>
        <svg v-if="loading" class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>

        <Transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-1">
            <div v-if="open" class="absolute inset-inline-start-0 top-full z-50 mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div v-if="showSuggestions && props.showSuggestions" class="p-3">
                    <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Suggested searches') }}</p>
                    <button
                        v-for="suggestion in visibleSuggestions"
                        :key="suggestion"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-start text-sm text-gray-700 transition-colors hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-primary-900/20"
                        @click="chooseSuggestion(suggestion)"
                    >
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ suggestion }}</span>
                    </button>
                    <p v-if="visibleSuggestions.length === 0" class="px-2 py-3 text-sm text-gray-500">{{ t('Type at least 3 characters to search.') }}</p>
                </div>

                <div v-else-if="hasResults" class="max-h-[28rem] overflow-y-auto p-2">
                    <div v-for="group in groups" :key="group.type" class="py-1">
                        <p class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ group.label }}</p>
                        <button
                            v-for="result in group.results"
                            :key="result.key"
                            type="button"
                            class="w-full rounded-lg px-3 py-2.5 text-start transition-colors"
                            :class="flattenedResults[activeIndex]?.key === result.key ? 'bg-primary-50 text-primary-800 dark:bg-primary-900/30 dark:text-primary-100' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800'"
                            @mouseenter="activeIndex = flattenedResults.findIndex((item) => item.key === result.key)"
                            @click="chooseResult(result)"
                        >
                            <span class="flex items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold">{{ result.title }}</span>
                                    <span class="live-search-excerpt mt-0.5 block line-clamp-2 text-xs text-gray-500 dark:text-gray-400" v-html="result.excerpt" />
                                </span>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:bg-surface-800 dark:text-gray-400">{{ result.meta }}</span>
                            </span>
                        </button>
                    </div>
                </div>

                <div v-else class="p-6 text-center">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ loading ? t('Searching...') : t('No results found') }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ t('Try a different keyword.') }}</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.live-search-excerpt :deep(mark) {
    border-radius: 0.25rem;
    background: rgb(16 185 129 / 0.16);
    color: inherit;
    padding-inline: 0.125rem;
}
</style>
