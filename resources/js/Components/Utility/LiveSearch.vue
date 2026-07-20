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
const showShortcut = computed(() => props.context === 'admin' && !props.compact)
const hasWideTrailingControls = computed(() => props.context === 'admin' && !props.compact)
const wrapperClass = computed(() => {
    if (props.compact) {
        return 'w-full'
    }

    if (props.context === 'admin') {
        return 'w-full max-w-[30rem] lg:focus-within:max-w-[34rem]'
    }

    return 'w-full max-w-[30rem] lg:focus-within:max-w-[32rem]'
})
const inputClass = computed(() => props.context === 'admin'
    ? `w-full rounded-full border border-gray-200/80 bg-white/92 py-2.5 ps-10 ${hasWideTrailingControls.value ? 'pe-24' : 'pe-10'} text-sm text-gray-900 shadow-sm shadow-gray-200/20 transition-all placeholder:text-gray-400 focus:border-primary-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800/90 dark:text-white dark:placeholder:text-gray-500 dark:shadow-black/10 dark:focus:border-primary-500/40 dark:focus:bg-surface-800`
    : 'w-full rounded-xl border border-gray-200 bg-white py-2.5 ps-10 pe-10 text-sm text-gray-900 shadow-sm shadow-gray-200/20 transition-all placeholder:text-gray-400 focus:border-primary-300 focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500 dark:shadow-black/10 dark:focus:border-primary-500/40')
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

const clearQuery = () => {
    query.value = ''
    groups.value = []
    suggestions.value = []
    loading.value = false
    activeIndex.value = 0
    open.value = props.enableLiveSearch && props.showSuggestions
    void nextTick(() => {
        focusInput()
    })
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
    }
}

const closeOnEscape = () => {
    if (query.value.trim().length > 0) {
        clearQuery()
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
        class="relative transition-[max-width] duration-200 ease-out"
        :class="wrapperClass"
    >
        <label class="sr-only" :for="`live-search-${context}`">{{ t('Search') }}</label>
        <i class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-[15px] leading-none text-gray-400 ti ti-search"></i>
        <input
            :id="`live-search-${context}`"
            ref="input"
            v-model="query"
            type="text"
            inputmode="search"
            enterkeyhint="search"
            autocomplete="off"
            spellcheck="false"
            data-global-search
            :placeholder="t('Search...')"
            :class="inputClass"
            @focus="focus"
            @keydown.down.prevent="navigate(1)"
            @keydown.up.prevent="navigate(-1)"
            @keydown.enter.prevent="submit"
            @keydown.esc.prevent="closeOnEscape"
        >
        <div
            class="absolute end-2 top-1/2 flex -translate-y-1/2 items-center justify-end"
            :class="hasWideTrailingControls ? 'w-18' : 'w-7'"
        >
            <button
                v-if="query"
                type="button"
                class="inline-flex h-5 w-5 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                :aria-label="t('Clear search')"
                @click="clearQuery"
            >
                <i class="ti ti-x text-[14px] leading-none"></i>
            </button>
            <span
                v-else-if="showShortcut && !loading"
                class="pointer-events-none inline-flex h-5 items-center gap-1 rounded-md border border-gray-200/80 bg-white/95 px-1.5 text-[10px] font-medium text-gray-400 shadow-sm shadow-gray-200/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500 dark:shadow-black/10"
            >
                <span>Ctrl</span>
                <span>/</span>
            </span>
            <i v-else-if="loading" class="pointer-events-none animate-spin text-[15px] leading-none text-primary-500 ti ti-loader-2"></i>
        </div>

        <Transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-1">
            <div v-if="open" class="absolute inset-inline-start-0 top-full z-50 mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div v-if="showSuggestions && props.showSuggestions" class="p-2">
                    <button
                        v-for="suggestion in visibleSuggestions"
                        :key="suggestion"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-start text-sm text-gray-700 transition-colors hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-primary-900/20"
                        @click="chooseSuggestion(suggestion)"
                    >
                        <i class="ti ti-history text-sm text-gray-400"></i>
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
                                    <span class="live-search-excerpt mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-gray-400" v-html="result.excerpt" />
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
