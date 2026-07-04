<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

interface Language {
    id: number
    name: string
}

interface TranslationItem {
    id: number
    key: string
    value: string
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface DisplayPaginationLink {
    key: string
    url: string | null
    label: string
    active: boolean
    isEllipsis: boolean
    isPrev: boolean
    isNext: boolean
}

interface PaginatedTranslations {
    data: TranslationItem[]
    from: number
    to: number
    total: number
    links: PaginationLink[]
}

type PendingAction =
    | { type: 'search'; value: string }
    | { type: 'ai-one'; id: number }
    | { type: 'ai-all' }
    | { type: 'navigate' }
    | null

const props = defineProps<{
    language: Language
    translations: PaginatedTranslations
    filters: {
        search?: string
    }
}>()

const { t } = useTranslate()
const toast = useToastr()
const search = ref(props.filters.search || '')
const searchInputRef = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)
const rows = ref<TranslationItem[]>([])
const originalValues = ref<Record<number, string>>({})
const savingAll = ref(false)
const translatingAi = ref<number | null>(null)
const translatingAiAll = ref(false)
const syncedSearch = ref(props.filters.search || '')
const allowNavigation = ref(false)
const confirmModalOpen = ref(false)
const confirmModalTitle = ref('')
const confirmModalMessage = ref('')
const confirmModalConfirmLabel = ref('')
const confirmModalVariant = ref<'danger' | 'primary'>('primary')
const pendingAction = ref<PendingAction>(null)
let removeNavigationGuard: (() => void) | undefined

const debounce = <T extends unknown[]>(fn: (...args: T) => void, delay: number) => {
    let timeoutId: ReturnType<typeof setTimeout>

    return (...args: T) => {
        clearTimeout(timeoutId)
        timeoutId = setTimeout(() => fn(...args), delay)
    }
}

const hasUnsavedChanges = computed(() => rows.value.some((translation) => isDirty(translation)))
const dirtyRows = computed(() => rows.value.filter((translation) => isDirty(translation)))
const dirtyCount = computed(() => dirtyRows.value.length)
const filledCount = computed(() => rows.value.filter((translation) => Boolean((translation.value ?? '').trim())).length)
const missingCount = computed(() => rows.value.length - filledCount.value)
const hasSearchActive = computed(() => search.value.trim().length > 0)
const paginationDisplayLinks = computed<DisplayPaginationLink[]>(() => {
    const links = props.translations.links

    if (links.length <= 3) {
        return []
    }

    const prev = links[0]
    const next = links[links.length - 1]
    const pageLinks = links.slice(1, -1).map((link, index) => ({
        ...link,
        page: Number.parseInt(link.label.replace(/<[^>]+>/g, '').trim(), 10),
        originalIndex: index,
    })).filter((link) => Number.isFinite(link.page))
    const leadingPages = pageLinks.slice(0, 3)
    const trailingPages = pageLinks.slice(-2)
    const visiblePages = new Set<number>([
        ...leadingPages.map((link) => link.page),
        ...trailingPages.map((link) => link.page),
    ])

    const result: DisplayPaginationLink[] = [
        {
            key: 'prev',
            url: prev.url,
            label: 'prev',
            active: prev.active,
            isEllipsis: false,
            isPrev: true,
            isNext: false,
        },
    ]

    let lastVisiblePage = 0

    for (const link of pageLinks) {
        if (!visiblePages.has(link.page)) {
            continue
        }

        if (lastVisiblePage && link.page - lastVisiblePage > 1) {
            result.push({
                key: `ellipsis-${lastVisiblePage}-${link.page}`,
                url: null,
                label: '...',
                active: false,
                isEllipsis: true,
                isPrev: false,
                isNext: false,
            })
        }

        result.push({
            key: `page-${link.page}`,
            url: link.url,
            label: String(link.page),
            active: link.active,
            isEllipsis: false,
            isPrev: false,
            isNext: false,
        })

        lastVisiblePage = link.page
    }

    result.push({
        key: 'next',
        url: next.url,
        label: 'next',
        active: next.active,
        isEllipsis: false,
        isPrev: false,
        isNext: true,
    })

    return result
})

const resetRows = () => {
    rows.value = props.translations.data.map((translation) => ({ ...translation }))
    originalValues.value = Object.fromEntries(rows.value.map((translation) => [translation.id, translation.value ?? '']))
}

const isDirty = (translation: TranslationItem) => (translation.value ?? '') !== (originalValues.value[translation.id] ?? '')

const closeConfirmModal = () => {
    confirmModalOpen.value = false
    pendingAction.value = null
}

const openConfirmModal = (options: {
    title: string
    message: string
    confirmLabel: string
    variant?: 'danger' | 'primary'
    action: PendingAction
}) => {
    confirmModalTitle.value = options.title
    confirmModalMessage.value = options.message
    confirmModalConfirmLabel.value = options.confirmLabel
    confirmModalVariant.value = options.variant ?? 'primary'
    pendingAction.value = options.action
    confirmModalOpen.value = true
}

const navigateToSearch = (value: string) => {
    syncedSearch.value = value
    allowNavigation.value = true
    router.get(route('admin.translations.index', props.language.id), { search: value }, {
        preserveState: true,
        replace: true,
        onFinish: () => {
            allowNavigation.value = false
        },
    })
}

const clearSearch = () => {
    search.value = ''
}

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''

const runAiTranslate = async (id: number) => {
    translatingAi.value = id
    try {
        const res = await fetch(route('admin.translations.ai', id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        })
        const json = await res.json()
        if (!json.success) throw new Error(json.message)
        const row = rows.value.find((r) => r.id === id)
        if (row) row.value = json.value
        toast.success(t('AI translation applied.'))
    } catch (e: any) {
        toast.error(e.message || t('AI translation failed.'))
    } finally {
        translatingAi.value = null
    }
}

const runAiTranslateAll = async () => {
    translatingAiAll.value = true
    try {
        const res = await fetch(route('admin.translations.ai_all', props.language.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        })
        const json = await res.json()
        if (!json.success && !json.empty) throw new Error(json.message)
        if (json.empty) {
            toast.info(json.message || t('No translations needed processing.'))
            return
        }
        for (const item of json.items) {
            const row = rows.value.find((r) => r.id === item.id)
            if (row) row.value = item.value
        }
        toast.success(t('AI translation applied.'))
    } catch (e: any) {
        toast.error(e.message || t('AI batch translation failed.'))
    } finally {
        translatingAiAll.value = false
    }
}

const handlePendingAction = () => {
    const action = pendingAction.value

    closeConfirmModal()

    if (!action) {
        return
    }

    if (action.type === 'search') {
        navigateToSearch(action.value)
        return
    }

    if (action.type === 'ai-one') {
        runAiTranslate(action.id)
        return
    }

    if (action.type === 'ai-all') {
        runAiTranslateAll()
        return
    }

    allowNavigation.value = true
    window.removeEventListener('beforeunload', warnBeforeUnload)
    window.history.back()
}

watch(search, debounce((value: string) => {
    if (value === syncedSearch.value) {
        return
    }

    if (hasUnsavedChanges.value) {
        search.value = syncedSearch.value
        openConfirmModal({
            title: t('Discard unsaved changes?'),
            message: t('You have unsaved translation changes. Continue without saving them?'),
            confirmLabel: t('Discard Changes'),
            variant: 'danger',
            action: { type: 'search', value },
        })
        return
    }

    navigateToSearch(value)
}, 300))

watch(() => props.translations.data, resetRows, { immediate: true })

const saveAllChanges = () => {
    if (savingAll.value) return

    if (!dirtyRows.value.length) {
        toast.info(t('No changes to save.'))
        return
    }

    const changedTranslations = dirtyRows.value.map((translation) => ({
        id: translation.id,
        value: translation.value,
    }))

    savingAll.value = true
    allowNavigation.value = true
    window.removeEventListener('beforeunload', warnBeforeUnload)
    router.post(route('admin.translations.bulk_update', props.language.id), { translations: changedTranslations }, {
        preserveScroll: true,
        only: ['translations', 'flash', 'errors'],
        onSuccess: () => {
            originalValues.value = {
                ...originalValues.value,
                ...Object.fromEntries(changedTranslations.map((translation) => [translation.id, translation.value ?? ''])),
            }
        },
        onError: () => {
            toast.error(t('Save failed. Please try again.'))
        },
        onFinish: () => {
            allowNavigation.value = false
            savingAll.value = false
            window.addEventListener('beforeunload', warnBeforeUnload)
        },
    })
}

const aiTranslate = (id: number) => {
    runAiTranslate(id)
}

const aiTranslateAll = () => {
    openConfirmModal({
        title: t('Translate missing phrases with AI?'),
        message: hasUnsavedChanges.value
            ? t('AI will attempt to translate missing strings and your current unsaved edits will be discarded. Continue?')
            : t('AI will attempt to translate all missing strings for this language. Continue?'),
        confirmLabel: t('Translate Missing'),
        action: { type: 'ai-all' },
    })
}

const handleBackNavigation = () => {
    if (hasUnsavedChanges.value) {
        openConfirmModal({
            title: t('Leave this page?'),
            message: t('You have unsaved translation changes. Continue without saving them?'),
            confirmLabel: t('Leave Page'),
            variant: 'danger',
            action: { type: 'navigate' },
        })
        return
    }

    window.history.back()
}

const warnBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!hasUnsavedChanges.value) {
        return
    }

    event.preventDefault()
    event.returnValue = ''
}

onMounted(() => {
    window.addEventListener('beforeunload', warnBeforeUnload)
    window.addEventListener('keydown', handleKeydown)
    removeNavigationGuard = router.on('before', (event) => {
        if (allowNavigation.value || !hasUnsavedChanges.value) {
            return
        }

        event.preventDefault()

        openConfirmModal({
            title: t('Leave this page?'),
            message: t('You have unsaved translation changes. Continue without saving them?'),
            confirmLabel: t('Leave Page'),
            variant: 'danger',
            action: { type: 'navigate' },
        })
    })
})

onUnmounted(() => {
    window.removeEventListener('beforeunload', warnBeforeUnload)
    window.removeEventListener('keydown', handleKeydown)
    removeNavigationGuard?.()
})

function isTypingTarget(target: EventTarget | null) {
    if (!(target instanceof HTMLElement)) {
        return false
    }

    const tagName = target.tagName.toLowerCase()

    return tagName === 'input'
        || tagName === 'textarea'
        || tagName === 'select'
        || target.isContentEditable
}

function handleKeydown(event: KeyboardEvent) {
    if (confirmModalOpen.value) {
        return
    }

    if (event.key === '/') {
        if (event.metaKey || event.ctrlKey || event.altKey || isTypingTarget(event.target)) {
            return
        }

        event.preventDefault()
        searchInputRef.value?.focus()
        searchInputRef.value?.select()
        return
    }

    if (event.key === 'Escape' && hasSearchActive.value) {
        if (isTypingTarget(event.target) && event.target !== searchInputRef.value) {
            return
        }

        clearSearch()
    }
}
</script>

<template>
    <Head :title="t('Translations — :language', { language: language.name })" />
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t(':language Translations', { language: language.name }) }}
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review keys, edit translated strings, and use AI to fill missing phrases from one unified admin workspace.') }}
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <Link
                    :href="route('admin.languages.index')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    <span>{{ t('Back') }}</span>
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-medium text-violet-700 transition-colors hover:border-violet-300 hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-violet-900/50 dark:bg-violet-900/20 dark:text-violet-300"
                    :disabled="savingAll || translatingAiAll"
                    @click="aiTranslateAll"
                >
                    <svg v-if="translatingAiAll" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    <i v-else class="ti ti-sparkles text-sm"></i>
                    {{ t('AI Translate') }}
                </button>
                <button
                    type="button"
                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="savingAll"
                    @click="saveAllChanges"
                >
                    <svg v-if="savingAll" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{ savingAll ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="w-full xl:max-w-md">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                ref="searchInputRef"
                                v-model="search"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :placeholder="t('Search by key or translation...')"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            />
                            <span
                                v-if="!search && !searchFocused"
                                class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
                            >
                                /
                            </span>
                            <button
                                v-if="search"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                :title="t('Clear search')"
                                @click="clearSearch"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                            {{ t(':count filled', { count: filledCount }) }}
                        </span>
                        <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            {{ t(':count missing', { count: missingCount }) }}
                        </span>
                        <span
                            v-if="dirtyCount > 0"
                            class="inline-flex rounded-full bg-primary-100 px-3 py-1 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                        >
                            {{ t(':count unsaved', { count: dirtyCount }) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-3 border-b border-gray-100 bg-gray-50 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-surface-800 dark:bg-surface-950/40 dark:text-gray-400">
                <div class="col-span-12 lg:col-span-4">{{ t('Original / Key') }}</div>
                <div class="col-span-12 lg:col-span-8">{{ t('Translation') }}</div>
            </div>

            <div v-if="rows.length" class="divide-y divide-gray-100 dark:divide-surface-800">
                <div
                    v-for="translation in rows"
                    :key="translation.id"
                    class="grid grid-cols-12 gap-4 px-6 py-5 transition-colors hover:bg-primary-50/40 dark:hover:bg-primary-900/10"
                >
                    <div class="col-span-12 lg:col-span-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Source key') }}</div>
                        <p class="mt-2 break-words rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 font-mono text-xs leading-5 text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            {{ translation.key }}
                        </p>
                    </div>

                    <div class="col-span-12 lg:col-span-8">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                {{ t('Translated value') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="isDirty(translation)"
                                    class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                >
                                    {{ t('Unsaved') }}
                                </span>
                                <Tooltip :content="t('AI Auto-Fill')" placement="top">
                                    <button
                                        type="button"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 text-violet-700 transition-colors hover:border-violet-300 hover:bg-violet-100 dark:border-violet-900/50 dark:bg-violet-900/20 dark:text-violet-300"
                                        :disabled="translatingAi !== null"
                                        @click="aiTranslate(translation.id)"
                                    >
                                        <svg v-if="translatingAi === translation.id" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                        <i v-else class="ti ti-sparkles text-sm"></i>
                                    </button>
                                </Tooltip>
                            </div>
                        </div>

                        <textarea
                            v-model="translation.value"
                            rows="3"
                            class="block w-full resize-y rounded-lg border bg-gray-50 px-3 py-2.5 text-sm leading-6 text-gray-900 transition-all focus:border-primary-500 focus:outline-none dark:bg-surface-800 dark:text-white"
                            :class="isDirty(translation)
                                ? 'border-primary-300 ring-2 ring-primary-100 dark:border-primary-700 dark:ring-primary-900/30'
                                : 'border-gray-200 dark:border-surface-700'"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div v-else class="px-6 py-14 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                    <i class="ti ti-language text-2xl"></i>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">{{ t('No translations found') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Try a different search term or sync more phrases for this language.') }}</p>
            </div>

            <div v-if="translations.links.length > 3" class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800 dark:bg-surface-950/40">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Showing :from-:to of :count phrases', { from: translations.from, to: translations.to, count: translations.total }) }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <template v-for="link in paginationDisplayLinks" :key="link.key">
                        <span
                            v-if="link.isEllipsis"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                        >
                            {{ link.label }}
                        </span>
                        <Link
                            v-else
                            :href="link.url || '#'"
                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="[
                            link.active
                                ? 'border-primary-600 bg-primary-600 text-white'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300',
                            !link.url ? 'cursor-not-allowed opacity-50' : '',
                        ]"
                        >
                            <template v-if="link.isPrev">
                                <i class="ti ti-chevron-left text-sm"></i>
                            </template>
                            <template v-else-if="link.isNext">
                                <i class="ti ti-chevron-right text-sm"></i>
                            </template>
                            <template v-else>
                                {{ link.label }}
                            </template>
                        </Link>
                    </template>
                </div>
            </div>
        </section>
    </div>


    <ActionConfirmModal
        :open="confirmModalOpen"
        :title="confirmModalTitle"
        :message="confirmModalMessage"
        :confirm-label="confirmModalConfirmLabel"
        :cancel-label="t('Cancel')"
        :variant="confirmModalVariant"
        @cancel="closeConfirmModal"
        @confirm="handlePendingAction"
    />
</template>
