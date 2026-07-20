<script setup lang="ts">
import { computed, ref } from 'vue'
import axios from 'axios'
import { useTranslate } from '@/Composables/useTranslate'
import AppModal from '@/Components/UI/AppModal.vue'
import AppIconSelect from '@/Components/Admin/IconClassSelect.vue'

/* ── Types ───────────────────────────────────────────────── */
export type RepeaterRow = Record<string, string>

export interface RepeaterFieldDef {
    key: string
    label: string
    type: 'text' | 'textarea' | 'image' | 'icon'
    placeholder?: string
}

/** The shape `addon.aip.admin.media.store` returns. */
interface MediaUploadResponse {
    success: boolean
    url: string
    path: string
}

const props = withDefaults(
    defineProps<{
        modelValue: RepeaterRow[]
        fields: RepeaterFieldDef[]
        addLabel: string
        max?: number
        /** Overrides the default "No items yet — add one" copy. */
        emptyText?: string
    }>(),
    {
        max: 0,
        emptyText: '',
    }
)

const emit = defineEmits<{
    (e: 'update:modelValue', value: RepeaterRow[]): void
}>()

const { t } = useTranslate()

/* ── Upload constraints (mirror the server rules) ─────────── */
const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'] as const
const ACCEPT_ATTR = ACCEPTED_MIME.join(',')
const MAX_BYTES = 4 * 1024 * 1024

/* ── State ───────────────────────────────────────────────── */
const rows = computed<RepeaterRow[]>(() => props.modelValue ?? [])
const canAdd = computed(() => !props.max || rows.value.length < props.max)
const deleteIndex = ref<number | null>(null)
const brokenImages = ref<Record<string, boolean>>({})

/** Per-cell upload state, keyed `${index}:${key}`. Reset whenever rows reorder. */
const uploading = ref<Record<string, boolean>>({})
const progress = ref<Record<string, number>>({})
const uploadErrors = ref<Record<string, string>>({})
const dragging = ref<Record<string, boolean>>({})

/**
 * Storage paths for images *we* uploaded, keyed by the URL we stored in the row.
 * Keyed by URL rather than by cell so it survives reordering. This is only a
 * fast path: it is per-page-load, so an image uploaded before the last save is
 * absent here. Removal no longer depends on it — see `removeImage`.
 */
const uploadedPaths = ref<Record<string, string>>({})

/**
 * The last file attempted per cell, so a failed upload can be retried without
 * making the operator re-pick it. Deliberately a plain Map: a File in reactive
 * state would get wrapped in a proxy for no benefit.
 */
const retryFiles = new Map<string, File>()

/* ── Helpers ─────────────────────────────────────────────── */
function clone(): RepeaterRow[] {
    return rows.value.map((row) => ({ ...row }))
}

function blankRow(): RepeaterRow {
    const row: RepeaterRow = {}
    for (const field of props.fields) row[field.key] = ''
    return row
}

function valueOf(row: RepeaterRow, key: string): string {
    return row[key] ?? ''
}

function cellKey(index: number, key: string): string {
    return `${index}:${key}`
}

/** Index-keyed cell state is only valid for a given ordering — drop it on any reshuffle. */
function resetCellState(): void {
    brokenImages.value = {}
    uploading.value = {}
    progress.value = {}
    uploadErrors.value = {}
    dragging.value = {}
    retryFiles.clear()
}

/* ── Mutations ───────────────────────────────────────────── */
function addRow(): void {
    if (!canAdd.value) return
    emit('update:modelValue', [...clone(), blankRow()])
}

function updateField(index: number, key: string, value: string): void {
    const next = clone()
    if (!next[index]) return
    next[index][key] = value
    const cell = cellKey(index, key)
    if (value) brokenImages.value[cell] = false
    uploadErrors.value[cell] = ''
    emit('update:modelValue', next)
}

function move(index: number, direction: -1 | 1): void {
    const target = index + direction
    if (target < 0 || target >= rows.value.length) return
    const next = clone()
    const [row] = next.splice(index, 1)
    next.splice(target, 0, row)
    resetCellState()
    emit('update:modelValue', next)
}

function confirmDelete(): void {
    if (deleteIndex.value === null) return
    const next = clone()
    next.splice(deleteIndex.value, 1)
    deleteIndex.value = null
    resetCellState()
    emit('update:modelValue', next)
}

/* ── Previews ────────────────────────────────────────────── */
function isBroken(index: number, key: string): boolean {
    return brokenImages.value[cellKey(index, key)] === true
}

function markBroken(index: number, key: string): void {
    brokenImages.value = { ...brokenImages.value, [cellKey(index, key)]: true }
}

function rowTitle(row: RepeaterRow, index: number): string {
    const label = valueOf(row, 'title').trim()
    return label || `${t('Item')} ${index + 1}`
}

/* ── Upload ──────────────────────────────────────────────── */
function isUploading(index: number, key: string): boolean {
    return uploading.value[cellKey(index, key)] === true
}

function uploadProgress(index: number, key: string): number {
    return progress.value[cellKey(index, key)] ?? 0
}

function errorOf(index: number, key: string): string {
    return uploadErrors.value[cellKey(index, key)] ?? ''
}

function isDragging(index: number, key: string): boolean {
    return dragging.value[cellKey(index, key)] === true
}

/**
 * Pull a usable message out of whatever axios threw: a 422 carries the server's
 * own validation copy (already translated), a request with no response means the
 * network dropped, anything else falls back to a generic line.
 */
function uploadErrorMessage(error: unknown): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string } | undefined

        if (typeof data?.message === 'string' && data.message !== '') {
            return data.message
        }

        if (!error.response) {
            return t('Upload failed — check your connection and try again.')
        }
    }

    return t('Upload failed. Please try again.')
}

/** Client-side mirror of the server rules, so an obvious mistake fails instantly. */
function localValidationError(file: File): string {
    if (!ACCEPTED_MIME.includes(file.type as (typeof ACCEPTED_MIME)[number])) {
        return t('Use a JPG, PNG, WebP or AVIF image.')
    }

    if (file.size > MAX_BYTES) {
        return t('The image may not be larger than 4 MB.')
    }

    return ''
}

async function upload(index: number, key: string, file: File): Promise<void> {
    const cell = cellKey(index, key)
    if (uploading.value[cell]) return

    retryFiles.set(cell, file)

    const invalid = localValidationError(file)
    if (invalid) {
        uploadErrors.value[cell] = invalid
        return
    }

    uploading.value[cell] = true
    uploadErrors.value[cell] = ''
    progress.value[cell] = 0

    const body = new FormData()
    body.append('image', file)

    try {
        const { data } = await axios.post<MediaUploadResponse>(
            route('addon.aip.admin.media.store'),
            body,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (event) => {
                    progress.value[cell] = event.total
                        ? Math.round((event.loaded / event.total) * 100)
                        : 0
                },
            },
        )

        if (!data?.url) {
            throw new Error(t('Upload failed. Please try again.'))
        }

        // Remember the path so Remove can delete the file we just created.
        uploadedPaths.value[data.url] = data.path
        retryFiles.delete(cell)
        updateField(index, key, data.url)
    } catch (error) {
        uploadErrors.value[cell] = uploadErrorMessage(error)
    } finally {
        uploading.value[cell] = false
    }
}

function onPick(index: number, key: string, event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    // Clear the input so re-picking the same file still fires `change`.
    input.value = ''
    if (file) void upload(index, key, file)
}

function onDrop(index: number, key: string, event: DragEvent): void {
    dragging.value[cellKey(index, key)] = false
    if (isUploading(index, key)) return

    const file = event.dataTransfer?.files?.[0]
    if (file) void upload(index, key, file)
}

function retry(index: number, key: string): void {
    const file = retryFiles.get(cellKey(index, key))
    if (file) void upload(index, key, file)
}

/**
 * Clear the field and ask the server to clean up the file behind it.
 *
 * We always send the current value as `url` — an image uploaded before the last
 * save is not in `uploadedPaths` (that map is per-page-load), and gating the
 * destroy call on it is what used to leave orphans on disk. The server resolves
 * the URL itself and confines the delete to the addon's own directory, so an
 * external URL the operator pasted is a safe no-op. We still send `path` when we
 * happen to have it — the server prefers it and it saves a lookup.
 */
async function removeImage(index: number, key: string): Promise<void> {
    const row = rows.value[index]
    if (!row) return

    const url = valueOf(row, key)
    const path = url ? (uploadedPaths.value[url] ?? '') : ''

    const cell = cellKey(index, key)
    updateField(index, key, '')
    uploadErrors.value[cell] = ''
    progress.value[cell] = 0
    retryFiles.delete(cell)

    if (!url) return

    delete uploadedPaths.value[url]

    try {
        await axios.delete(route('addon.aip.admin.media.destroy'), {
            data: path ? { url, path } : { url },
        })
    } catch {
        // The field is already cleared, which is what the operator asked for. A
        // failed cleanup is not worth blocking them with an error.
    }
}
</script>

<template>
    <div class="space-y-3">
        <!-- Empty state -->
        <div
            v-if="rows.length === 0"
            class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center dark:border-surface-700"
        >
            <i class="ti ti-stack-2 text-2xl text-gray-300 dark:text-gray-600"></i>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ emptyText || t('No items yet — add one.') }}
            </p>
        </div>

        <!-- Rows -->
        <div
            v-for="(row, index) in rows"
            :key="index"
            class="rounded-xl border border-gray-100 bg-gray-50/70 p-3 dark:border-surface-800 dark:bg-surface-800/60 sm:p-4"
        >
            <div class="mb-3 flex items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-gray-200 text-[11px] font-semibold text-gray-600 dark:bg-surface-700 dark:text-gray-300">
                        {{ index + 1 }}
                    </span>
                    <span class="truncate text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ rowTitle(row, index) }}
                    </span>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 dark:border-surface-700 dark:text-gray-400 dark:hover:bg-surface-800"
                        :disabled="index === 0"
                        :title="t('Move up')"
                        :aria-label="t('Move up')"
                        @click="move(index, -1)"
                    >
                        <i class="ti ti-arrow-up"></i>
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 dark:border-surface-700 dark:text-gray-400 dark:hover:bg-surface-800"
                        :disabled="index === rows.length - 1"
                        :title="t('Move down')"
                        :aria-label="t('Move down')"
                        @click="move(index, 1)"
                    >
                        <i class="ti ti-arrow-down"></i>
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-danger-200 text-danger-600 transition-colors hover:bg-danger-50 dark:border-danger-800 dark:text-danger-400 dark:hover:bg-danger-900/20"
                        :title="t('Remove')"
                        :aria-label="t('Remove')"
                        @click="deleteIndex = index"
                    >
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="field in fields"
                    :key="field.key"
                    :class="field.type === 'textarea' || field.type === 'image' ? 'sm:col-span-2' : ''"
                >
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ field.label }}
                    </label>

                    <!-- textarea -->
                    <textarea
                        v-if="field.type === 'textarea'"
                        :value="valueOf(row, field.key)"
                        rows="2"
                        :placeholder="field.placeholder"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        @input="updateField(index, field.key, ($event.target as HTMLTextAreaElement).value)"
                    ></textarea>

                    <!-- image: uploader + thumbnail + URL fallback -->
                    <div v-else-if="field.type === 'image'" class="space-y-2">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
                            <!-- Live thumbnail — uploaded or pasted, same preview. -->
                            <div class="flex h-24 w-full shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-100 sm:h-auto sm:min-h-[6rem] sm:w-24 dark:border-surface-700 dark:bg-surface-800">
                                <img
                                    v-if="valueOf(row, field.key) && !isBroken(index, field.key)"
                                    :src="valueOf(row, field.key)"
                                    :alt="field.label"
                                    class="h-full w-full object-cover"
                                    @error="markBroken(index, field.key)"
                                />
                                <i
                                    v-else
                                    class="text-xl text-gray-400 dark:text-gray-500"
                                    :class="isBroken(index, field.key) ? 'ti ti-photo-off' : 'ti ti-photo'"
                                ></i>
                            </div>

                            <!-- Drop-zone / picker -->
                            <label
                                class="flex flex-1 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed px-3 py-4 text-center transition-colors"
                                :class="[
                                    isUploading(index, field.key)
                                        ? 'cursor-wait border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-800/60'
                                        : isDragging(index, field.key)
                                            ? 'border-primary-400 bg-primary-50 dark:border-primary-500 dark:bg-primary-900/20'
                                            : 'border-gray-300 bg-white hover:border-primary-400 hover:bg-primary-50/40 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-500 dark:hover:bg-primary-900/10',
                                ]"
                                @dragover.prevent="dragging[cellKey(index, field.key)] = true"
                                @dragenter.prevent="dragging[cellKey(index, field.key)] = true"
                                @dragleave.prevent="dragging[cellKey(index, field.key)] = false"
                                @drop.prevent="onDrop(index, field.key, $event)"
                            >
                                <input
                                    type="file"
                                    class="sr-only"
                                    :accept="ACCEPT_ATTR"
                                    :disabled="isUploading(index, field.key)"
                                    @change="onPick(index, field.key, $event)"
                                />

                                <template v-if="isUploading(index, field.key)">
                                    <i class="ti ti-loader-2 animate-spin text-lg text-primary-500"></i>
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                        {{ t('Uploading…') }} {{ uploadProgress(index, field.key) }}%
                                    </span>
                                    <span class="mt-1 h-1 w-full max-w-[12rem] overflow-hidden rounded-full bg-gray-200 dark:bg-surface-700">
                                        <span
                                            class="block h-full rounded-full bg-primary-500 transition-all"
                                            :style="{ width: `${uploadProgress(index, field.key)}%` }"
                                        ></span>
                                    </span>
                                </template>

                                <template v-else>
                                    <i class="ti ti-cloud-upload text-lg text-gray-400 dark:text-gray-500"></i>
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                        {{ valueOf(row, field.key) ? t('Replace image') : t('Upload image') }}
                                    </span>
                                    <span class="text-[11px] text-gray-400">
                                        {{ t('Drag & drop, or click to browse. JPG, PNG, WebP or AVIF, up to 4 MB.') }}
                                    </span>
                                </template>
                            </label>
                        </div>

                        <!-- Upload error + retry -->
                        <p
                            v-if="errorOf(index, field.key)"
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg bg-danger-50 px-2.5 py-1.5 text-xs text-danger-700 dark:bg-danger-900/20 dark:text-danger-300"
                        >
                            <i class="ti ti-alert-circle"></i>
                            <span>{{ errorOf(index, field.key) }}</span>
                            <button
                                type="button"
                                class="font-semibold underline underline-offset-2 hover:no-underline"
                                :disabled="isUploading(index, field.key)"
                                @click="retry(index, field.key)"
                            >
                                {{ t('Try again') }}
                            </button>
                        </p>

                        <!-- Fallback: an externally-hosted URL still works. -->
                        <div class="flex items-center gap-2">
                            <input
                                :value="valueOf(row, field.key)"
                                type="text"
                                :disabled="isUploading(index, field.key)"
                                :placeholder="field.placeholder || t('…or paste an image URL')"
                                :aria-label="t('Image URL')"
                                class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:disabled:bg-surface-900"
                                @input="updateField(index, field.key, ($event.target as HTMLInputElement).value)"
                            />
                            <button
                                v-if="valueOf(row, field.key)"
                                type="button"
                                class="flex shrink-0 items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-2 text-xs font-medium text-gray-600 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800"
                                :disabled="isUploading(index, field.key)"
                                :title="t('Remove image')"
                                @click="removeImage(index, field.key)"
                            >
                                <i class="ti ti-x"></i>
                                <span class="hidden sm:inline">{{ t('Remove') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- icon: searchable Tabler picker with live preview -->
                    <div v-else-if="field.type === 'icon'" class="flex items-center gap-2">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-100 dark:border-surface-700 dark:bg-surface-800">
                            <i
                                class="text-base text-gray-600 dark:text-gray-300"
                                :class="valueOf(row, field.key) || 'ti ti-square-dashed'"
                            ></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <AppIconSelect
                                :model-value="valueOf(row, field.key)"
                                :placeholder="field.placeholder || t('Search icons…')"
                                @update:model-value="updateField(index, field.key, $event)"
                            />
                        </div>
                    </div>

                    <!-- text -->
                    <input
                        v-else
                        :value="valueOf(row, field.key)"
                        type="text"
                        :placeholder="field.placeholder"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        @input="updateField(index, field.key, ($event.target as HTMLInputElement).value)"
                    />
                </div>
            </div>
        </div>

        <!-- Add -->
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800"
                :disabled="!canAdd"
                @click="addRow"
            >
                <i class="ti ti-plus"></i> {{ addLabel }}
            </button>
            <span v-if="max" class="text-xs text-gray-400">
                {{ rows.length }} / {{ max }}
            </span>
        </div>

        <!-- Delete confirm -->
        <AppModal
            :open="deleteIndex !== null"
            max-width="max-w-sm"
            :title="t('Remove this item?')"
            :confirm-text="t('Remove')"
            confirm-variant="delete"
            @close="deleteIndex = null"
            @confirm="confirmDelete"
        >
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ t('This row will be removed from the list. The change is applied when you save the settings.') }}
            </p>
        </AppModal>
    </div>
</template>
