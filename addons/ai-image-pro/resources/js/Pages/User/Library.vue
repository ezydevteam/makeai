<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import Pagination from '@/Components/UI/Pagination.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { modelLabel, operationLabel, type Model, type Op } from '../../Composables/useImageJobs'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

interface Asset {
    ulid: string
    url: string
    thumb_url: string | null
    width: number
    height: number
    mime: string
    bytes: number
    prompt: string | null
    negative_prompt: string | null
    model: string | null
    provider: string | null
    seed: number | null
    operation: string
    source: string
    is_favorite: boolean
    folder_id: number | null
    parent_ulid: string | null
    created_at: string
}

interface Folder {
    id: number
    name: string
    color?: string
    assets_count?: number
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Paginator<T> {
    data: T[]
    links: PaginationLink[]
    from: number | null
    to: number | null
    total: number | null
    current_page: number | null
    last_page: number | null
}

interface Filters {
    q: string | null
    folder_id: number | null
    source: string | null
    favorite: boolean | string | null
    model: string | null
}

interface Storage {
    usedMb: number
    capMb: number
}

const props = defineProps<{
    assets: Paginator<Asset>
    folders: Folder[]
    filters: Filters
    storage: Storage
    /** Distinct model slugs this user has generated with — feeds the filter. */
    models: string[]
    /** The admin's catalogue, used to turn those slugs into display names. */
    modelCatalog: Model[]
    /** Off = which model ran is not the user's decision, so the preview does not name it. */
    allowModelChoice: boolean
    /** The live operation list — turns an asset's `bg_remove` key into its real name. */
    operations: Op[]
}>()

/** Display name for a stored slug, falling back to the slug for a retired model. */
const nameOf = (slug: string | null) => modelLabel(props.modelCatalog, slug)

const studioRoute = route('addon.aip.user.studio')
const libraryRoute = route('addon.aip.user.library')

/* ── Filters ─────────────────────────────────────────────── */
const filterState = reactive<Filters>({
    q: props.filters.q ?? '',
    folder_id: props.filters.folder_id ?? null,
    source: props.filters.source ?? null,
    favorite: props.filters.favorite ? true : null,
    model: props.filters.model ?? null,
})

const listLoading = ref(false)
const listError = ref('')

/**
 * These values are matched against `aip_assets.source` verbatim, so they must be the exact
 * strings the backend writes — the only three that exist:
 *
 *   generated — a generation with no input image (GenerationService)
 *   derived   — produced from an existing image by an operation (GenerationService with an
 *               input asset, ProviderOperationService, ToolController)
 *   uploaded  — the user's own file (StudioController::upload)
 */
const sourceOptions = computed(() => [
    { value: '', label: t('All sources') },
    { value: 'generated', label: t('Generated') },
    { value: 'derived', label: t('Edited') },
    { value: 'uploaded', label: t('Uploaded') },
])

/** The same labels in the preview, so it never prints the raw `derived`. */
function sourceName(source: string): string {
    return sourceOptions.value.find((option) => option.value === source)?.label ?? source
}

// Filter by the display name, not the raw slug the value still carries.
const modelOptions = computed(() => [
    { value: '', label: t('All models') },
    ...props.models.map((m) => ({ value: m, label: nameOf(m)?.name ?? m })),
])

function buildQuery(): Record<string, string> {
    const query: Record<string, string> = {}
    if (filterState.q) query.q = String(filterState.q)
    if (filterState.folder_id != null) query.folder_id = String(filterState.folder_id)
    if (filterState.source) query.source = String(filterState.source)
    if (filterState.favorite) query.favorite = '1'
    if (filterState.model) query.model = String(filterState.model)
    return query
}

function applyFilters() {
    listError.value = ''
    router.get(libraryRoute, buildQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['assets', 'filters', 'storage'],
        onStart: () => { listLoading.value = true },
        onFinish: () => { listLoading.value = false },
        onError: () => { listError.value = t('Failed to load images. Please try again.') },
    })
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => filterState.q, () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})
watch(() => [filterState.folder_id, filterState.source, filterState.favorite, filterState.model], applyFilters)

function selectFolder(id: number | null) {
    filterState.folder_id = id
}

function resetFilters() {
    filterState.q = ''
    filterState.source = null
    filterState.favorite = null
    filterState.model = null
    filterState.folder_id = null
}

const hasActiveFilters = computed(() =>
    !!(filterState.q || filterState.source || filterState.favorite || filterState.model || filterState.folder_id != null),
)

/* ── Storage meter ───────────────────────────────────────── */
const showStorage = computed(() => props.storage.capMb > 0)
const storagePct = computed(() => {
    if (props.storage.capMb <= 0) return 0
    return Math.min(100, Math.round((props.storage.usedMb / props.storage.capMb) * 100))
})

/* ── Selection / bulk ────────────────────────────────────── */
const selected = ref<Set<string>>(new Set())
const selectMode = ref(false)
const bulkBusy = ref(false)
const bulkError = ref('')

function toggleSelect(ulid: string) {
    const next = new Set(selected.value)
    if (next.has(ulid)) next.delete(ulid)
    else next.add(ulid)
    selected.value = next
}

function clearSelection() {
    selected.value = new Set()
    selectMode.value = false
}

function selectAllVisible() {
    selected.value = new Set(props.assets.data.map((a) => a.ulid))
}

const selectedCount = computed(() => selected.value.size)

async function runBulk(action: 'delete' | 'favorite' | 'unfavorite' | 'move', folderId?: number | null) {
    if (selectedCount.value === 0) return
    bulkBusy.value = true
    bulkError.value = ''
    try {
        await axios.post(route('addon.aip.user.assets.bulk'), {
            action,
            ulids: Array.from(selected.value),
            folder_id: typeof folderId === 'number' ? folderId : null,
        })
        clearSelection()
        moveModalOpen.value = false
        router.reload({ only: ['assets', 'folders', 'storage'] })
    } catch (e) {
        bulkError.value = t('Bulk action failed. Please try again.')
    } finally {
        bulkBusy.value = false
    }
}

/**
 * Bulk delete is the only bulk action that cannot be undone — star/unstar and move are all
 * reversible in one click — so it is the only one that asks first. Single-asset delete and
 * folder delete already confirm; this was the gap.
 */
const bulkDeleteOpen = ref(false)

async function confirmBulkDelete() {
    await runBulk('delete')
    // Closed on failure too: runBulk surfaces the error in the toolbar, and holding a modal
    // open over an error the user cannot see from inside it just traps them.
    bulkDeleteOpen.value = false
}

const moveModalOpen = ref(false)
const moveTargetFolder = ref<number | null>(null)

/* ── Per-asset actions ───────────────────────────────────── */
const rowBusy = ref<string | null>(null)
const rowError = ref('')

async function toggleFavorite(asset: Asset) {
    rowBusy.value = asset.ulid
    rowError.value = ''
    try {
        const { data } = await axios.post(route('addon.aip.user.assets.favorite', asset.ulid))
        asset.is_favorite = !!data.is_favorite
        if (lightboxAsset.value && lightboxAsset.value.ulid === asset.ulid) {
            lightboxAsset.value.is_favorite = asset.is_favorite
        }
    } catch (e) {
        rowError.value = t('Could not update favorite.')
    } finally {
        rowBusy.value = null
    }
}

const deleteTarget = ref<Asset | null>(null)
const deleteBusy = ref(false)

async function confirmDelete() {
    if (!deleteTarget.value) return
    deleteBusy.value = true
    rowError.value = ''
    try {
        await axios.delete(route('addon.aip.user.assets.destroy', deleteTarget.value.ulid))
        if (lightboxAsset.value && lightboxAsset.value.ulid === deleteTarget.value.ulid) {
            lightboxAsset.value = null
        }
        deleteTarget.value = null
        router.reload({ only: ['assets', 'folders', 'storage'] })
    } catch (e) {
        rowError.value = t('Could not delete image.')
    } finally {
        deleteBusy.value = false
    }
}

function downloadUrl(asset: Asset): string {
    return route('addon.aip.user.assets.download', asset.ulid)
}

function openInStudio(asset: Asset): string {
    return `${studioRoute}?asset=${asset.ulid}`
}

/* ── Lightbox ────────────────────────────────────────────── */
const lightboxAsset = ref<Asset | null>(null)

/** The operation's real name — never the raw `bg_remove` key the asset stores. */
const lightboxTitle = computed(
    () => operationLabel(props.operations, lightboxAsset.value?.operation) ?? t('Preview'),
)

function openLightbox(asset: Asset) {
    if (selectMode.value) {
        toggleSelect(asset.ulid)
        return
    }
    lightboxAsset.value = asset
}

/* ── Folder CRUD ─────────────────────────────────────────── */
const folderModalOpen = ref(false)
const folderModalMode = ref<'create' | 'rename'>('create')
const folderName = ref('')
const folderColor = ref('#6366f1')
const folderEditing = ref<Folder | null>(null)
const folderBusy = ref(false)
const folderError = ref('')

function openCreateFolder() {
    folderModalMode.value = 'create'
    folderName.value = ''
    folderColor.value = '#6366f1'
    folderEditing.value = null
    folderError.value = ''
    folderModalOpen.value = true
}

function openRenameFolder(folder: Folder) {
    folderModalMode.value = 'rename'
    folderName.value = folder.name
    folderColor.value = folder.color ?? '#6366f1'
    folderEditing.value = folder
    folderError.value = ''
    folderModalOpen.value = true
}

async function submitFolder() {
    if (!folderName.value.trim()) {
        folderError.value = t('Please enter a folder name.')
        return
    }
    folderBusy.value = true
    folderError.value = ''
    try {
        const payload = { name: folderName.value.trim(), color: folderColor.value }
        if (folderModalMode.value === 'create') {
            await axios.post(route('addon.aip.user.folders.store'), payload)
        } else if (folderEditing.value) {
            await axios.put(route('addon.aip.user.folders.update', folderEditing.value.id), payload)
        }
        folderModalOpen.value = false
        router.reload({ only: ['folders'] })
    } catch (e) {
        folderError.value = t('Could not save folder.')
    } finally {
        folderBusy.value = false
    }
}

const folderDeleteTarget = ref<Folder | null>(null)
const folderDeleteBusy = ref(false)

async function confirmDeleteFolder() {
    if (!folderDeleteTarget.value) return
    folderDeleteBusy.value = true
    folderError.value = ''
    try {
        await axios.delete(route('addon.aip.user.folders.destroy', folderDeleteTarget.value.id))
        if (filterState.folder_id === folderDeleteTarget.value.id) {
            filterState.folder_id = null
        }
        folderDeleteTarget.value = null
        router.reload({ only: ['folders', 'assets'] })
    } catch (e) {
        folderError.value = t('Could not delete folder.')
    } finally {
        folderDeleteBusy.value = false
    }
}

/* ── Formatting helpers ──────────────────────────────────── */
function formatBytes(bytes: number): string {
    if (!bytes) return '0 KB'
    const kb = bytes / 1024
    if (kb < 1024) return `${kb.toFixed(0)} KB`
    return `${(kb / 1024).toFixed(1)} MB`
}

function formatDate(value: string): string {
    if (!value) return ''
    const d = new Date(value)
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString()
}
</script>

<template>
    <Head :title="t('Image Library')" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Image Library') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Browse, organise and download every image you created.') }}</p>
            </div>
            <Link :href="studioRoute" class="btn-primary rounded-full">
                <i class="ti ti-sparkles"></i>
                {{ t('Open Studio') }}
            </Link>
        </div>

        <!-- Storage meter -->
        <div v-if="showStorage" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ t('Storage used') }}</span>
                <span class="text-gray-500 dark:text-gray-400">
                    {{ t(':used MB of :cap MB', { used: storage.usedMb, cap: storage.capMb }) }}
                </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-surface-800">
                <div
                    class="h-full rounded-full transition-all"
                    :class="storagePct >= 90 ? 'bg-danger-500' : storagePct >= 70 ? 'bg-warning-500' : 'bg-primary-500'"
                    :style="{ width: `${storagePct}%` }"
                ></div>
            </div>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row">
            <!-- Folder sidebar -->
            <aside class="w-full shrink-0 space-y-1 lg:w-60">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">{{ t('Folders') }}</span>
                    <Tooltip :content="t('New folder')">
                        <button type="button" class="rounded-full p-1 text-primary-600 hover:text-primary-700 dark:text-primary-400" :aria-label="t('New folder')" @click="openCreateFolder">
                            <i class="ti ti-folder-plus text-base"></i>
                        </button>
                    </Tooltip>
                </div>

                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm"
                    :class="filterState.folder_id === null ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800'"
                    @click="selectFolder(null)"
                >
                    <i class="ti ti-photo text-base"></i>
                    <span class="flex-1 truncate">{{ t('All Images') }}</span>
                </button>

                <div
                    v-for="folder in folders"
                    :key="folder.id"
                    class="group flex items-center gap-1 rounded-lg pr-1"
                    :class="filterState.folder_id === folder.id ? 'bg-primary-50 dark:bg-primary-900/30' : 'hover:bg-gray-50 dark:hover:bg-surface-800'"
                >
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 items-center gap-2 px-3 py-2 text-left text-sm"
                        :class="filterState.folder_id === folder.id ? 'font-medium text-primary-700 dark:text-primary-300' : 'text-gray-600 dark:text-gray-300'"
                        @click="selectFolder(folder.id)"
                    >
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: folder.color || '#6366f1' }"></span>
                        <span class="flex-1 truncate">{{ folder.name }}</span>
                        <span v-if="folder.assets_count != null" class="shrink-0 text-xs text-gray-400">{{ folder.assets_count }}</span>
                    </button>
                    <!-- Wrappers own the show-on-hover, for the same reason as the star badge:
                         Tooltip's inline `display: inline-flex` would override `hidden`. -->
                    <span class="hidden shrink-0 group-hover:block">
                        <Tooltip :content="t('Rename')">
                            <button type="button" class="rounded-full p-1 text-gray-400 hover:text-primary-600" :aria-label="t('Rename')" @click.stop="openRenameFolder(folder)">
                                <i class="ti ti-pencil text-sm"></i>
                            </button>
                        </Tooltip>
                    </span>
                    <span class="hidden shrink-0 group-hover:block">
                        <Tooltip :content="t('Delete')">
                            <button type="button" class="rounded-full p-1 text-gray-400 hover:text-danger-600" :aria-label="t('Delete')" @click.stop="folderDeleteTarget = folder">
                                <i class="ti ti-trash text-sm"></i>
                            </button>
                        </Tooltip>
                    </span>
                </div>
            </aside>

            <!-- Main -->
            <div class="min-w-0 flex-1 space-y-4">
                <!-- Filters -->
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                    <label class="relative">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input
                            v-model="filterState.q"
                            type="text"
                            :placeholder="t('Search prompt...')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </label>
                    <AppSelect v-model="filterState.source" :options="sourceOptions" :placeholder="t('All sources')" />
                    <AppSelect v-model="filterState.model" :options="modelOptions" :placeholder="t('All models')" :live-search="models.length > 8" />
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition-colors"
                        :class="filterState.favorite ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300' : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                        @click="filterState.favorite = filterState.favorite ? null : true"
                    >
                        <i class="ti" :class="filterState.favorite ? 'ti-star-filled' : 'ti-star'"></i>
                        {{ t('Starred') }}
                    </button>
                </div>

                <!-- Bulk toolbar -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800"
                            @click="selectMode ? clearSelection() : (selectMode = true)"
                        >
                            <i class="ti" :class="selectMode ? 'ti-x' : 'ti-checkbox'"></i>
                            {{ selectMode ? t('Cancel') : t('Select') }}
                        </button>
                        <template v-if="selectMode">
                            <button type="button" class="text-sm text-primary-600 hover:underline dark:text-primary-400" @click="selectAllVisible">{{ t('Select all') }}</button>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ t(':n selected', { n: selectedCount }) }}</span>
                        </template>
                    </div>

                    <div v-if="selectMode && selectedCount > 0" class="flex flex-wrap items-center gap-2">
                        <button type="button" :disabled="bulkBusy" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-60 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800" @click="runBulk('favorite')">
                            <i class="ti ti-star"></i> {{ t('Star') }}
                        </button>
                        <button type="button" :disabled="bulkBusy" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-60 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800" @click="runBulk('unfavorite')">
                            <i class="ti ti-star-off"></i> {{ t('Remove star') }}
                        </button>
                        <button type="button" :disabled="bulkBusy || folders.length === 0" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-60 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800" @click="moveTargetFolder = null; moveModalOpen = true">
                            <i class="ti ti-folder"></i> {{ t('Move') }}
                        </button>
                        <button type="button" :disabled="bulkBusy" class="rounded-lg border border-danger-200 px-3 py-1.5 text-sm text-danger-600 hover:bg-danger-50 disabled:opacity-60 dark:border-danger-800 dark:text-danger-400 dark:hover:bg-danger-900/20" @click="bulkDeleteOpen = true">
                            <i class="ti ti-trash"></i> {{ t('Delete') }}
                        </button>
                    </div>
                </div>

                <div v-if="bulkError" class="rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-900/20 dark:text-danger-300">{{ bulkError }}</div>
                <div v-if="rowError" class="rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-900/20 dark:text-danger-300">{{ rowError }}</div>

                <!-- List error + retry -->
                <div v-if="listError" class="rounded-2xl border border-danger-200 bg-danger-50 p-6 text-center dark:border-danger-800 dark:bg-danger-900/20">
                    <p class="text-sm text-danger-700 dark:text-danger-300">{{ listError }}</p>
                    <button type="button" class="mt-3 rounded-lg btn-primary" @click="applyFilters">{{ t('Retry') }}</button>
                </div>

                <!-- Loading -->
                <div v-else-if="listLoading" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-for="n in 8" :key="n" class="aspect-square animate-pulse rounded-xl bg-gray-100 dark:bg-surface-800"></div>
                </div>

                <!-- Empty -->
                <div v-else-if="assets.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-gray-900">
                    <i class="ti ti-photo-off mb-3 text-4xl text-gray-300 dark:text-gray-600"></i>
                    <template v-if="hasActiveFilters">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('No images match your filters.') }}</p>
                        <button type="button" class="mt-3 rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800" @click="resetFilters">{{ t('Clear filters') }}</button>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Your library is empty.') }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create your first image in the Studio.') }}</p>
                        <Link :href="studioRoute" class="mt-4 inline-flex rounded-full btn-primary">
                            <i class="ti ti-sparkles"></i>
                            {{ t('Open Studio') }}
                        </Link>
                    </template>
                </div>

                <!-- Grid -->
                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="asset in assets.data"
                        :key="asset.ulid"
                        class="group relative overflow-hidden rounded-xl border bg-gray-50 transition-all dark:bg-surface-800"
                        :class="selected.has(asset.ulid) ? 'border-primary-500 ring-2 ring-primary-500/40' : 'border-gray-200 dark:border-surface-700'"
                    >
                        <button type="button" class="block aspect-square w-full" @click="openLightbox(asset)">
                            <img
                                v-if="asset.thumb_url || asset.url"
                                :src="asset.thumb_url || asset.url"
                                :alt="asset.prompt || asset.operation"
                                loading="lazy"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="flex h-full w-full items-center justify-center text-gray-300">
                                <i class="ti ti-photo text-3xl"></i>
                            </span>
                        </button>

                        <!-- Selection checkbox -->
                        <button
                            v-if="selectMode"
                            type="button"
                            class="absolute left-2 top-2 flex h-6 w-6 items-center justify-center rounded-md border-2 bg-white/90 dark:bg-surface-900/90"
                            :class="selected.has(asset.ulid) ? 'border-primary-500 text-primary-600' : 'border-gray-300 text-transparent'"
                            @click.stop="toggleSelect(asset.ulid)"
                        >
                            <i class="ti ti-check text-sm"></i>
                        </button>

                        <!-- Star badge. The positioning lives on this wrapper, NOT on Tooltip:
                             Tooltip's root carries a scoped `position: relative` and an inline
                             `display: inline-flex`, both of which are unlayered and so beat any
                             Tailwind `absolute`/`hidden` passed down to it. -->
                        <div class="absolute right-2 top-2">
                            <Tooltip :content="asset.is_favorite ? t('Remove star') : t('Star')">
                                <button
                                    type="button"
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-white/85 text-amber-500 shadow-sm transition hover:bg-white disabled:opacity-60 dark:bg-surface-900/85"
                                    :disabled="rowBusy === asset.ulid"
                                    :aria-label="asset.is_favorite ? t('Remove star') : t('Star')"
                                    @click.stop="toggleFavorite(asset)"
                                >
                                    <i class="ti" :class="asset.is_favorite ? 'ti-star-filled' : 'ti-star'"></i>
                                </button>
                            </Tooltip>
                        </div>

                        <!-- Hover actions -->
                        <div v-if="!selectMode" class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-end gap-1 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 transition-opacity group-hover:opacity-100">
                            <Tooltip :content="t('Download')" class="pointer-events-auto">
                                <a :href="downloadUrl(asset)" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-700 hover:bg-white" :aria-label="t('Download')">
                                    <i class="ti ti-download text-sm"></i>
                                </a>
                            </Tooltip>
                            <Tooltip :content="t('Open in Studio')" class="pointer-events-auto">
                                <Link :href="openInStudio(asset)" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-700 hover:bg-white" :aria-label="t('Open in Studio')">
                                    <i class="ti ti-wand text-sm"></i>
                                </Link>
                            </Tooltip>
                            <Tooltip :content="t('Delete')" class="pointer-events-auto">
                                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-danger-600 hover:bg-white" :aria-label="t('Delete')" @click.stop="deleteTarget = asset">
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <Pagination
                    v-if="assets.links && assets.links.length > 3"
                    :links="assets.links"
                    :from="assets.from"
                    :to="assets.to"
                    :total="assets.total"
                    :current-page="assets.current_page"
                    :last-page="assets.last_page"
                />
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <AppModal
        :open="lightboxAsset !== null"
        max-width="max-w-5xl"
        :cancel-text="null"
        @close="lightboxAsset = null"
    >
        <template #header>
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <!-- The asset stores the registry key (`bg_remove`); show the operation's name. -->
                <h3 class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ lightboxTitle }}</h3>
                <Tooltip :content="t('Close')">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="lightboxAsset = null"><i class="ti ti-x text-lg"></i></button>
                </Tooltip>
            </div>
        </template>

        <div v-if="lightboxAsset" class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div class="flex items-center justify-center rounded-xl bg-gray-100 p-2 dark:bg-surface-800">
                <img :src="lightboxAsset.url" :alt="lightboxAsset.prompt || ''" class="max-h-[65vh] w-auto rounded-lg object-contain" />
            </div>
            <div class="space-y-4 text-sm">
                <div v-if="lightboxAsset.prompt">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Prompt') }}</p>
                    <p class="text-gray-700 dark:text-gray-200">{{ lightboxAsset.prompt }}</p>
                </div>
                <div v-if="lightboxAsset.negative_prompt">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Negative Prompt') }}</p>
                    <p class="text-gray-700 dark:text-gray-200">{{ lightboxAsset.negative_prompt }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <!-- Model and provider are shown only when the admin lets users choose the
                         model. Otherwise which engine ran is not the user's decision, and the
                         operator may not want it named — so the rows are omitted, not greyed.
                         The name comes from the catalogue: the asset stores a raw provider slug
                         (`gemini-3.1-flash-image-preview`), which is not a label. -->
                    <div v-if="allowModelChoice && nameOf(lightboxAsset.model)"><dt class="text-xs text-gray-400">{{ t('Model') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ nameOf(lightboxAsset.model)?.name }}</dd></div>
                    <div v-if="allowModelChoice && nameOf(lightboxAsset.model)?.provider"><dt class="text-xs text-gray-400">{{ t('Provider') }}</dt><dd class="capitalize text-gray-700 dark:text-gray-200">{{ nameOf(lightboxAsset.model)?.provider }}</dd></div>
                    <div v-if="lightboxAsset.seed != null"><dt class="text-xs text-gray-400">{{ t('Seed') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ lightboxAsset.seed }}</dd></div>
                    <div><dt class="text-xs text-gray-400">{{ t('Dimensions') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ lightboxAsset.width }}×{{ lightboxAsset.height }}</dd></div>
                    <div><dt class="text-xs text-gray-400">{{ t('Size') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ formatBytes(lightboxAsset.bytes) }}</dd></div>
                    <div><dt class="text-xs text-gray-400">{{ t('Format') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ lightboxAsset.mime }}</dd></div>
                    <div><dt class="text-xs text-gray-400">{{ t('Source') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ sourceName(lightboxAsset.source) }}</dd></div>
                    <div><dt class="text-xs text-gray-400">{{ t('Created') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ formatDate(lightboxAsset.created_at) }}</dd></div>
                </dl>

                <!-- Parent lineage -->
                <div v-if="lightboxAsset.parent_ulid" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-surface-700 dark:bg-surface-800">
                    <p class="text-xs text-gray-400">{{ t('Derived from') }}</p>
                    <Link :href="`${libraryRoute}?open=${lightboxAsset.parent_ulid}`" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        <i class="ti ti-arrow-back-up"></i> {{ t('View source image') }}
                    </Link>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <a :href="downloadUrl(lightboxAsset)" class="inline-flex items-center gap-1.5 rounded-full bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700">
                        <i class="ti ti-download"></i> {{ t('Download') }}
                    </a>
                    <Link :href="openInStudio(lightboxAsset)" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-200 dark:hover:bg-surface-800">
                        <i class="ti ti-wand"></i> {{ t('Open in Studio') }}
                    </Link>
                    <button type="button" :disabled="rowBusy === lightboxAsset.ulid" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-surface-700 dark:text-gray-200 dark:hover:bg-surface-800" @click="toggleFavorite(lightboxAsset)">
                        <i class="ti" :class="lightboxAsset.is_favorite ? 'ti-star-filled text-amber-500' : 'ti-star'"></i> {{ lightboxAsset.is_favorite ? t('Starred') : t('Star') }}
                    </button>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-danger-200 px-4 py-2 text-sm font-medium text-danger-600 hover:bg-danger-50 dark:border-danger-800 dark:text-danger-400 dark:hover:bg-danger-900/20" @click="deleteTarget = lightboxAsset">
                        <i class="ti ti-trash"></i> {{ t('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    </AppModal>

    <!-- Move to folder modal -->
    <AppModal
        :open="moveModalOpen"
        max-width="max-w-md"
        :title="t('Move to folder')"
        :confirm-text="t('Move')"
        :confirm-loading="bulkBusy"
        @close="moveModalOpen = false"
        @confirm="runBulk('move', moveTargetFolder)"
    >
        <AppSelect
            v-model="moveTargetFolder"
            :options="[{ value: '', label: t('No folder (remove)') }, ...folders.map((f) => ({ value: f.id, label: f.name }))]"
            :placeholder="t('Choose a folder')"
        />
    </AppModal>

    <!-- Folder create/rename modal -->
    <AppModal
        :open="folderModalOpen"
        max-width="max-w-md"
        :title="folderModalMode === 'create' ? t('New folder') : t('Rename folder')"
        :confirm-text="t('Save')"
        :confirm-loading="folderBusy"
        @close="folderModalOpen = false"
        @confirm="submitFolder"
    >
        <div class="space-y-3">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</span>
                <input v-model="folderName" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" @keyup.enter="submitFolder" />
            </label>
            <AppColorPicker v-model="folderColor" :label="t('Color')" />
            <p v-if="folderError" class="text-sm text-danger-600 dark:text-danger-400">{{ folderError }}</p>
        </div>
    </AppModal>

    <!-- Bulk delete confirm -->
    <ActionConfirmModal
        :open="bulkDeleteOpen"
        :title="t('Delete :n images?', { n: String(selectedCount) })"
        :message="t('The images and their files will be permanently removed. This cannot be undone.')"
        :confirm-label="t('Delete')"
        :processing="bulkBusy"
        :processing-label="t('Deleting…')"
        variant="danger"
        @cancel="bulkDeleteOpen = false"
        @confirm="confirmBulkDelete"
    />

    <!-- Delete asset confirm -->
    <AppModal
        :open="deleteTarget !== null"
        max-width="max-w-sm"
        :title="t('Delete image?')"
        :subtitle="t('This cannot be undone.')"
        :confirm-text="t('Delete')"
        confirm-variant="delete"
        :confirm-loading="deleteBusy"
        @close="deleteTarget = null"
        @confirm="confirmDelete"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ t('The image and its file will be permanently removed.') }}</p>
    </AppModal>

    <!-- Delete folder confirm -->
    <AppModal
        :open="folderDeleteTarget !== null"
        max-width="max-w-sm"
        :title="t('Delete folder?')"
        :confirm-text="t('Delete')"
        confirm-variant="delete"
        :confirm-loading="folderDeleteBusy"
        @close="folderDeleteTarget = null"
        @confirm="confirmDeleteFolder"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ t('Images inside this folder will not be deleted, only unfiled.') }}</p>
        <p v-if="folderError" class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ folderError }}</p>
    </AppModal>
</template>
