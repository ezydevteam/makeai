<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, h as createElement, type VNode } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@themes/default/js/Layouts/AppLayout.vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AssetPreviewModal from '../../Components/AssetPreviewModal.vue'
import PromptComposer from '../../Components/PromptComposer.vue'
import StudioExamples from '../../Components/StudioExamples.vue'
import ToolPanel from '../../Components/ToolPanel.vue'
import ResultGrid from '../../Components/ResultGrid.vue'
import EditorModal from '../../Components/EditorModal.vue'
import {
    defaultParams,
    useImageJobs,
    type AspectRatio,
    type Asset,
    type JobRef,
    type Model,
    type Op,
    type ParamMap,
    type Preset,
    type StudioFeatures,
    type StudioLimits,
    type TrackedJob,
} from '../../Composables/useImageJobs'
import { imageErrorMessage, toastImageError } from '../../Composables/useImageErrors'

/**
 * The Studio serves two audiences from one route: a signed-in user working
 * inside the app (dashboard sidebar) and a guest who landed here from the
 * marketing page (site header + footer). Inertia's layout-function form picks
 * the shell from the shared `auth` prop at render time.
 *
 * `page` is the page VNode built by Inertia — its props bag is the page's
 * Inertia props, and Inertia types it loosely, hence the documented untyped
 * access below (the only escape hatch allowed by the spec).
 */
defineOptions({
    layout: (h: typeof createElement, page: VNode) => {
        const auth = page.props?.auth as { user?: unknown } | undefined

        return h(auth?.user ? UserDashboardLayout : AppLayout, () => page)
    },
})

const { t } = useTranslate()

/** One admin-authored "Get started with" card (`landing_examples`). */
interface StudioExample {
    title: string
    description: string
    image: string
    prompt: string
}

const props = withDefaults(
    defineProps<{
        operations: Op[]
        models: Model[]
        defaultModel: string | null
        allowModelChoice: boolean
        aspectRatios: AspectRatio[]
        presets: Preset[]
        maxBatchSize: number
        limits: StudioLimits
        features: StudioFeatures
        recentAssets: Asset[]
        isGuest: boolean
        /** Admin-set studio headline (`studio_heading`). */
        heading?: string
        /** Admin-set greeting shown above the headline (`studio_subheading`). */
        subheading?: string
        /** Same list the landing page shows — shown until there is a result. */
        examples?: StudioExample[]
        /** `?prompt=` hand-off from the landing page: prefill and fire once. */
        autoPrompt?: string | null
        /**
         * The model and aspect the visitor picked on the landing page's prompt panel,
         * carried across the hop so those chips are not dead controls. Both are
         * validated server-side against what is actually on offer, so a hand-typed
         * query string cannot select a model the admin disabled.
         */
        autoModel?: string | null
        autoAspect?: string | null
    }>(),
    { heading: '', subheading: '', examples: () => [], autoPrompt: null, autoModel: null, autoAspect: null },
)

/* ------------------------------------------------------------------ *
 * Contracts shared with child components. These interfaces are kept
 * structurally identical to the children's emit payloads so the events
 * type-check without importing types across single-file components.
 * ------------------------------------------------------------------ */
interface GeneratePayload {
    prompt: string
    negative_prompt?: string
    model?: string
    aspect?: string
    count?: number
    seed?: number
    preset?: string
    reference_file?: File | null
}

type TileActionType =
    | 'focus'
    | 'preview'
    | 'upscale'
    | 'bg_remove'
    | 'edit'
    | 'variations'
    | 'download'
    | 'favorite'
    | 'delete'

interface TileAction {
    type: TileActionType
    asset: Asset
    op?: Op
}

interface EditorSubmit {
    op: Op
    params: ParamMap
    mask: Blob | null
}

interface GenerateBody {
    prompt: string
    negative_prompt?: string
    model?: string
    aspect?: string
    count?: number
    seed?: number
    preset?: string
    reference_asset_ulid?: string
}

interface JobResponse {
    success: boolean
    job: JobRef
}

interface AssetResponse {
    success: boolean
    asset: Asset
}

interface FavoriteResponse {
    success: boolean
    is_favorite: boolean
}

interface OpFiles {
    mask?: Blob | null
    reference?: File | null
}

/* ------------------------------------------------------------------ *
 * State
 * ------------------------------------------------------------------ */
const results = ref<Asset[]>([...props.recentAssets])
const focusedUlid = ref<string | null>(props.recentAssets[0]?.ulid ?? null)
const activeOp = ref<Op | null>(null)
const editorAsset = ref<Asset | null>(null)
const previewAsset = ref<Asset | null>(null)
const toolsOpen = ref(false)

const prompt = ref('')
const composer = ref<InstanceType<typeof PromptComposer> | null>(null)

const generating = ref(false)
const panelBusy = ref(false)
const editorBusy = ref(false)

const composerError = ref<string | null>(null)
const panelError = ref<string | null>(null)
const editorError = ref<string | null>(null)
const studioNotice = ref<string | null>(null)

// Set when a guest exhausts the free daily allowance — swaps the composer for a
// signup call-to-action instead of showing them a raw error.
const guestWall = ref<string | null>(null)

const jobs = useImageJobs({
    onCompleted: (_job: TrackedJob, assets: Asset[]) => addAssets(assets),
})

const focusedAsset = computed<Asset | null>(
    () => results.value.find((asset) => asset.ulid === focusedUlid.value) ?? results.value[0] ?? null,
)

const activeKey = computed(() => activeOp.value?.key ?? null)

/**
 * The composer is busy for the whole generation, not just the POST that queues it.
 * `generating` alone only covers the dispatch — it flips back the moment the server
 * hands back a job ulid, which re-enabled the submit button while the image was still
 * being rendered. Scoped to `generate` jobs on purpose: an upscale running in the
 * background is no reason to stop someone writing their next prompt.
 */
const composerBusy = computed(
    () => generating.value || jobs.pending.value.some((job) => job.operation === 'generate'),
)

const studioHeading = computed(() => props.heading.trim() || t('What can I do for you?'))
const studioSubheading = computed(() => props.subheading.trim())

/** The canvas only appears once there is something to show — before that, examples. */
const hasWorkspace = computed(
    () => results.value.length > 0 || jobs.pending.value.length > 0 || jobs.failed.value.length > 0,
)

/* ------------------------------------------------------------------ *
 * Tools
 *
 * The tool list, its costs and its locked/available state come entirely from
 * the `operations` prop (i.e. from OperationRegistry). An operation the admin
 * disables or re-gates simply stops appearing here — nothing about the toolkit
 * is fixed in the markup.
 * ------------------------------------------------------------------ */
const GROUP_LABELS: Record<string, string> = {
    create: t('Create'),
    enhance: t('Enhance'),
    adjust: t('Adjust'),
}

interface ToolGroup {
    key: string
    label: string
    ops: Op[]
}

/**
 * A tool whose engine has no API key behind it is hidden outright rather than shown
 * greyed-out: an operator's missing Stability key is not the visitor's problem, and a
 * dead row they can never click is worse than no row at all. The server still refuses
 * the op (503) if one is somehow reached, so this is presentation only.
 */
const usableOps = computed<Op[]>(() => props.operations.filter((op) => op.available))

/** Variations re-run the prompt, so the offer only stands while generate is on offer. */
const canVariations = computed(() => usableOps.value.some((op) => op.key === 'generate'))

const toolGroups = computed<ToolGroup[]>(() => {
    const order = ['create', 'enhance', 'adjust']
    const seen = new Map<string, Op[]>()

    for (const op of usableOps.value) {
        const bucket = seen.get(op.group) ?? []
        bucket.push(op)
        seen.set(op.group, bucket)
    }

    return [...seen.keys()]
        .sort((a, b) => {
            const ia = order.indexOf(a)
            const ib = order.indexOf(b)

            return (ia === -1 ? order.length : ia) - (ib === -1 ? order.length : ib)
        })
        .map((key) => ({
            key,
            label: GROUP_LABELS[key] ?? key,
            ops: seen.get(key) ?? [],
        }))
})

/** Free tools carry no credit cost — worth saying out loud in the picker. A free-tier
 * tool the admin has priced no longer counts as free, and a hidden one is not on offer. */
const freeToolCount = computed(
    () => usableOps.value.filter((op) => op.billing === 'free' && op.credits === 0).length,
)

/* ------------------------------------------------------------------ *
 * Helpers
 * ------------------------------------------------------------------ */
/**
 * The message for an inline slot — no toast. Used where the error already has a dedicated
 * place on screen that the visitor is looking at (the guest wall).
 */
function errorText(error: unknown, fallback: string): string {
    return imageErrorMessage(error, fallback)
}

/**
 * The message AND a toast. Everything that can fail out of view — a queued operation, a
 * favourite toggled from the grid, a delete — goes through here, so a refusal (demo mode's
 * block included) is announced rather than written into a panel that may be scrolled away.
 */
function reportError(error: unknown, fallback: string): string {
    return toastImageError(error, fallback)
}

function errorStatus(error: unknown): number | null {
    return (error as { response?: { status?: number } }).response?.status ?? null
}

/**
 * A guest who has spent their free daily allowance gets a 429 (or a 401 on an op
 * the admin left behind login). That is the moment to ask for the signup — not
 * before they have seen the thing work.
 */
function handleGuestWall(error: unknown): boolean {
    if (!props.isGuest) return false

    const status = errorStatus(error)
    if (status !== 429 && status !== 401) return false

    guestWall.value = errorText(error, t('You have used your free images for today.'))

    return true
}

function addAssets(incoming: Asset[], focusFirst = true): void {
    if (incoming.length === 0) return

    const incomingIds = new Set(incoming.map((asset) => asset.ulid))
    results.value = [...incoming, ...results.value.filter((asset) => !incomingIds.has(asset.ulid))]

    if (focusFirst) focusedUlid.value = incoming[0].ulid
}

function flashNotice(message: string): void {
    studioNotice.value = message
    window.setTimeout(() => {
        if (studioNotice.value === message) studioNotice.value = null
    }, 4000)
}

function signin(): void {
    window.location.href = route('login')
}

async function uploadReference(file: File): Promise<string | null> {
    const formData = new FormData()
    // `image` is the field UploadImageRequest validates and the controller reads
    // ($request->file('image')). It also tolerates `file`, but nothing else — posting
    // under any other key looks to the server like no file was attached at all.
    formData.append('image', file)

    const { data } = await axios.post<AssetResponse>(route('addon.aip.user.upload'), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    })

    return data.asset?.ulid ?? null
}

/* ------------------------------------------------------------------ *
 * Generate
 * ------------------------------------------------------------------ */
async function generate(payload: GeneratePayload): Promise<void> {
    if (generating.value) return

    generating.value = true
    composerError.value = null

    try {
        const body: GenerateBody = { prompt: payload.prompt }
        if (payload.negative_prompt) body.negative_prompt = payload.negative_prompt
        if (payload.model) body.model = payload.model
        if (payload.aspect) body.aspect = payload.aspect
        if (payload.count) body.count = payload.count
        if (payload.seed !== undefined) body.seed = payload.seed
        if (payload.preset) body.preset = payload.preset

        if (payload.reference_file) {
            const ulid = await uploadReference(payload.reference_file)
            if (ulid) body.reference_asset_ulid = ulid
        }

        const { data } = await axios.post<JobResponse>(route('addon.aip.user.generate'), body)

        jobs.track(data.job, {
            operation: 'generate',
            label: t('Generating'),
            expected: payload.count ?? 1,
            retry: () => void generate(payload),
        })
    } catch (error) {
        if (!handleGuestWall(error)) {
            composerError.value = reportError(error, t("We could not start the generation. Please try again."))
        }
    } finally {
        generating.value = false
    }
}

/* ------------------------------------------------------------------ *
 * Examples + the ?prompt= hand-off from the landing page
 * ------------------------------------------------------------------ */
function onExampleSelect(example: StudioExample): void {
    prompt.value = example.prompt
    void nextTick(() => composer.value?.submit())
}

onMounted(() => {
    window.addEventListener('keydown', onWindowKeydown)

    const seedPrompt = props.autoPrompt?.trim()
    if (!seedPrompt) return

    // The guest clicked "Generate" on the landing page: they expect to arrive
    // mid-generation, not to have to press the button a second time.
    prompt.value = seedPrompt
    void nextTick(() => composer.value?.submit())
})

onBeforeUnmount(() => window.removeEventListener('keydown', onWindowKeydown))

// Escape backs out one layer at a time: the tools picker sits over the panel, so it goes
// first. The editor modal owns its own Escape handling.
function onWindowKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') return

    if (toolsOpen.value) toolsOpen.value = false
    else if (activeOp.value) clearActiveOp()
}

/* ------------------------------------------------------------------ *
 * Operations (async /ops and synchronous /tools)
 * ------------------------------------------------------------------ */
async function dispatchOp(op: Op, asset: Asset, params: ParamMap, files?: OpFiles): Promise<boolean> {
    if (op.locked) {
        if (props.isGuest) signin()
        else flashNotice(t('Upgrade your plan to use this tool.'))

        return false
    }

    if (!op.available) {
        flashNotice(t('This tool is not configured yet.'))

        return false
    }

    return op.async ? runAsyncOp(op, asset, params, files) : runSyncTool(op, asset, params)
}

async function runAsyncOp(op: Op, asset: Asset, params: ParamMap, files?: OpFiles): Promise<boolean> {
    const url = route('addon.aip.user.ops', { operation: op.key })
    const hasFiles = Boolean(files?.mask || files?.reference)

    try {
        let data: JobResponse

        if (hasFiles) {
            const formData = new FormData()
            formData.append('asset_ulid', asset.ulid)
            Object.entries(params).forEach(([key, value]) => formData.append(`params[${key}]`, String(value)))
            if (files?.mask) formData.append('mask', files.mask, 'mask.png')
            if (files?.reference) formData.append('reference', files.reference)

            data = (await axios.post<JobResponse>(url, formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data
        } else {
            data = (await axios.post<JobResponse>(url, { asset_ulid: asset.ulid, params })).data
        }

        jobs.track(data.job, {
            operation: op.key,
            label: op.label,
            expected: 1,
            retry: () => void runAsyncOp(op, asset, params, files),
        })

        return true
    } catch (error) {
        panelError.value = reportError(error, t("The operation could not be started."))
        editorError.value = panelError.value

        return false
    }
}

async function runSyncTool(op: Op, asset: Asset, params: ParamMap): Promise<boolean> {
    try {
        const { data } = await axios.post<AssetResponse>(route('addon.aip.user.tools', { operation: op.key }), {
            asset_ulid: asset.ulid,
            params,
        })

        addAssets([data.asset])

        return true
    } catch (error) {
        panelError.value = reportError(error, t("The operation failed. Please try again."))

        return false
    }
}

/* ------------------------------------------------------------------ *
 * Tool selection
 * ------------------------------------------------------------------ */
function onToolSelect(op: Op): void {
    toolsOpen.value = false
    studioNotice.value = null

    if (op.locked) {
        if (props.isGuest) signin()
        else flashNotice(t('Upgrade your plan to use this tool.'))

        return
    }

    if (!op.available) {
        flashNotice(t('This tool is not configured yet.'))

        return
    }

    // Generation always lives in the always-visible composer.
    if (op.tier === 'generate' || op.key === 'generate') {
        activeOp.value = null
        composer.value?.focus()

        return
    }

    // Mask / outpaint ops open the editor over the currently focused image.
    if (op.inputs.includes('mask') || op.key === 'outpaint') {
        if (!focusedAsset.value) {
            flashNotice(t('Generate or select an image first.'))

            return
        }

        activeOp.value = null
        editorError.value = null
        editorAsset.value = focusedAsset.value

        return
    }

    // Everything else runs from the parameter panel against the focused image.
    panelError.value = null
    activeOp.value = op
}

/** Back to plain generation. Clearing the op also closes the panel — it renders on it. */
function clearActiveOp(): void {
    activeOp.value = null
    panelError.value = null
}

/* ------------------------------------------------------------------ *
 * Tool panel
 * ------------------------------------------------------------------ */
async function onPanelRun(params: ParamMap): Promise<void> {
    const op = activeOp.value
    const asset = focusedAsset.value
    if (!op || !asset) return

    panelBusy.value = true
    panelError.value = null

    try {
        await dispatchOp(op, asset, params)
    } finally {
        panelBusy.value = false
    }
}

/* ------------------------------------------------------------------ *
 * Result tiles
 * ------------------------------------------------------------------ */
async function onTileAction(action: TileAction): Promise<void> {
    const { asset } = action

    switch (action.type) {
        case 'focus':
            focusedUlid.value = asset.ulid
            break

        case 'preview':
            focusedUlid.value = asset.ulid
            previewAsset.value = asset
            break

        case 'upscale':
        case 'bg_remove':
            if (action.op) await dispatchOp(action.op, asset, defaultParams(action.op))
            break

        case 'edit':
            editorError.value = null
            focusedUlid.value = asset.ulid
            editorAsset.value = asset
            break

        case 'variations':
            if (asset.prompt) {
                await generate({
                    prompt: asset.prompt,
                    negative_prompt: asset.negative_prompt ?? undefined,
                    model: asset.model ?? undefined,
                })
            }
            break

        case 'download':
            window.open(route('addon.aip.user.assets.download', { asset: asset.ulid }), '_blank')
            break

        case 'favorite':
            await toggleFavorite(asset)
            break

        case 'delete':
            await deleteAsset(asset)
            break
    }
}

async function toggleFavorite(asset: Asset): Promise<void> {
    try {
        const { data } = await axios.post<FavoriteResponse>(
            route('addon.aip.user.assets.favorite', { asset: asset.ulid }),
        )
        const target = results.value.find((item) => item.ulid === asset.ulid)
        if (target) target.is_favorite = data.is_favorite
    } catch (error) {
        flashNotice(reportError(error, t("Could not update favorite.")))
    }
}

async function deleteAsset(asset: Asset): Promise<void> {
    try {
        await axios.delete(route('addon.aip.user.assets.destroy', { asset: asset.ulid }))
        results.value = results.value.filter((item) => item.ulid !== asset.ulid)
        if (focusedUlid.value === asset.ulid) focusedUlid.value = results.value[0]?.ulid ?? null
        // The image it was showing no longer exists.
        if (previewAsset.value?.ulid === asset.ulid) previewAsset.value = null
    } catch (error) {
        flashNotice(reportError(error, t("Could not delete this image.")))
    }
}

/**
 * The preview modal's buttons are the tile's buttons, so they run the same handler.
 * Favourite mutates the asset object in `results`, which the modal is holding by
 * reference, so its star updates without any extra plumbing.
 */
function onPreviewAction(type: TileActionType, asset: Asset): void {
    void onTileAction({ type, asset })
}

/* ------------------------------------------------------------------ *
 * Editor modal
 * ------------------------------------------------------------------ */
async function onEditorSubmit(payload: EditorSubmit): Promise<void> {
    const asset = editorAsset.value
    if (!asset) return

    editorBusy.value = true
    editorError.value = null

    try {
        const ok = await dispatchOp(payload.op, asset, payload.params, { mask: payload.mask })
        if (ok) editorAsset.value = null
    } finally {
        editorBusy.value = false
    }
}
</script>

<template>
    <Head :title="t('AI Image Studio')" />

    <!-- The two shells this page renders in have different container contracts, so the
         page shell is guest-only. UserDashboardLayout already centres <main> inside
         `max-w-7xl px-6` next to the sidebar — a second container here would double the
         horizontal padding and paint a stray background block inside the content column
         (Library.vue relies on this and roots itself at a bare `space-y-6`). AppLayout
         gives the page nothing, so the guest view has to supply its own. -->
    <div :class="isGuest ? 'bg-gray-50/60 py-10 dark:bg-gray-950 sm:py-14' : ''">
        <div class="space-y-6" :class="isGuest ? 'mx-auto w-full max-w-6xl px-6' : ''">
            <!-- ============================================================ *
             * STUDIO — the prompt and everything that frames it. No card of its
             * own: the composer below is already a card, and wrapping a card in a
             * card just doubles the border, background and padding.
             * ============================================================ -->
            <section class="py-4 sm:py-8">
                <!-- Guests get the "free to try" reassurance here. Signed-in users get
                     nothing: their credit balance and library both live in the dashboard
                     chrome already, and repeating them over the composer only crowds it. -->
                <div v-if="isGuest && !guestWall" class="mb-8 flex items-center justify-center sm:mb-10">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50/60 px-3 py-1 text-xs font-semibold text-primary-700 dark:border-primary-800/50 dark:bg-primary-500/10 dark:text-primary-300"
                    >
                        <i class="ti ti-sparkles text-sm"></i>
                        {{ t('Free to try — no credit card, no signup') }}
                    </span>
                </div>

                <!-- Heading -->
                <p
                    v-if="studioSubheading"
                    class="mb-2 text-center text-lg font-medium text-gray-500 sm:text-xl dark:text-gray-400"
                >
                    {{ studioSubheading }}
                </p>
                <h1
                    class="text-center text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl md:text-4xl dark:text-white"
                    :class="activeOp ? 'mb-5' : 'mb-8 sm:mb-10'"
                >
                    {{ studioHeading }}
                </h1>

                <!-- The selected tool. The parameter panel it opens is a slide-over, so
                     on a narrow screen it can be scrolled away from — this keeps the
                     current tool named next to the prompt, and gives a way back to plain
                     generation without hunting for the panel's own close button. -->
                <div v-if="activeOp" class="mb-8 flex justify-center sm:mb-10">
                    <span
                        class="inline-flex max-w-full items-center gap-2 rounded-full border border-primary-200 bg-primary-50 py-1.5 pl-3 pr-1.5 text-xs font-semibold text-primary-700 dark:border-primary-700/60 dark:bg-primary-500/10 dark:text-primary-300"
                    >
                        <i :class="activeOp.icon" class="shrink-0 text-sm"></i>
                        <span class="truncate">{{ activeOp.label }}</span>
                        <Tooltip :content="t('Clear tool')">
                            <button
                                type="button"
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-primary-500 transition hover:bg-primary-100 hover:text-primary-800 dark:text-primary-400 dark:hover:bg-primary-500/20 dark:hover:text-primary-200"
                                :aria-label="t('Clear :tool', { tool: activeOp.label })"
                                @click="clearActiveOp"
                            >
                                <i class="ti ti-x text-[11px]"></i>
                            </button>
                        </Tooltip>
                    </span>
                </div>

                <!-- ONLY the prompt is held to a reading measure. The heading above and the
                     example grid below run the full column width.

                     Centred with `justify-center`, NOT `mx-auto`: app.ts injects
                     `main .mx-auto { max-width: var(--page-width) !important }` on every
                     frontend page to enforce the theme's container width, and that would
                     beat `max-w-3xl` and stretch the prompt back out. -->
                <div class="flex justify-center">
                    <div class="w-full max-w-3xl">
                        <!-- Composer, or the signup wall once a guest's free run is spent.
                             rounded-2xl, not 3xl: it stands in for the composer, and a 24px
                             radius nested inside the card's 16px reads as a mistake. -->
                        <div
                            v-if="guestWall"
                            class="rounded-2xl border border-primary-200 bg-white p-8 text-center shadow-sm dark:border-primary-800/50 dark:bg-surface-900"
                        >
                            <div
                                class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400"
                            >
                                <i class="ti ti-lock-open text-2xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ t('Create a free account to keep going') }}
                            </h2>
                            <p class="mx-auto mt-2 max-w-md text-sm text-gray-600 dark:text-gray-400">
                                {{ guestWall }}
                                {{ t('Sign up to keep generating, save everything to your library, and unlock the full toolkit.') }}
                            </p>
                            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                                <a
                                    :href="route('register')"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700"
                                >
                                    <i class="ti ti-user-plus text-base"></i>
                                    {{ t('Create free account') }}
                                </a>
                                <a
                                    :href="route('login')"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                >
                                    {{ t('Sign in') }}
                                </a>
                            </div>
                            <p v-if="results.length > 0" class="mt-4 text-xs text-gray-500 dark:text-gray-500">
                                {{ t('Your images from this session are still below — sign up to save them.') }}
                            </p>
                        </div>

                        <PromptComposer
                            v-else
                            ref="composer"
                            v-model="prompt"
                            :models="models"
                            :default-model="autoModel ?? defaultModel"
                            :allow-model-choice="allowModelChoice"
                            :aspect-ratios="aspectRatios"
                            :default-aspect="autoAspect"
                            :presets="presets"
                            :max-batch-size="maxBatchSize"
                            :features="features"
                            :is-guest="isGuest"
                            :busy="composerBusy"
                            :error="composerError"
                            @generate="generate"
                            @tools="toolsOpen = true"
                        />

                        <!-- The job was accepted but nothing has picked it up. Almost always a
                             queue worker that is not running, or one started without this
                             addon's queue (jobs are dispatched to `media`). -->
                        <div
                            v-if="jobs.isStalled.value"
                            class="mt-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800/40 dark:bg-amber-500/10 dark:text-amber-300"
                            role="status"
                        >
                            <i class="ti ti-clock-exclamation mt-0.5 shrink-0"></i>
                            <span>
                                {{ t('Your image is queued but has not started yet. If this does not clear, the site\'s background worker may not be running.') }}
                            </span>
                        </div>

                        <!-- Transient notice. Belongs to the composer, so it shares its measure. -->
                        <div
                            v-if="studioNotice"
                            class="mt-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800/40 dark:bg-amber-500/10 dark:text-amber-300"
                            role="status"
                        >
                            <i class="ti ti-info-circle"></i>
                            {{ studioNotice }}
                        </div>
                    </div>
                </div>

                <!-- Examples — full column width, like the results grid they turn into.
                     Shown until there is something in the workspace, and hidden behind the
                     guest wall: with no composer mounted, a card would do nothing. -->
                <div v-if="!hasWorkspace && !guestWall" class="mt-10">
                    <StudioExamples :examples="examples" :busy="composerBusy" @select="onExampleSelect" />
                </div>
            </section>

            <!-- ============================================================ *
             * WORKSPACE — results, skeletons and failures. The one section card on
             * the page, matching Library's card treatment rather than the square
             * full-bleed band it used to be. Spacing comes from the parent's
             * space-y-6, so no margin of its own.
             * ============================================================ -->
            <section
                v-if="hasWorkspace"
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-surface-800 dark:bg-gray-900"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ t('Your results') }}
                    </h2>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                        @click="toolsOpen = true"
                    >
                        <i class="ti ti-adjustments text-sm"></i>
                        {{ t('Tools') }}
                    </button>
                </div>

                <ResultGrid
                    :assets="results"
                    :pending-jobs="jobs.pending.value"
                    :failed-jobs="jobs.failed.value"
                    :operations="operations"
                    :is-guest="isGuest"
                    :focused-ulid="focusedUlid"
                    @action="onTileAction"
                    @retry="jobs.retry"
                    @dismiss="jobs.dismiss"
                />

                <!-- Session strip -->
                <div v-if="results.length > 1" class="mt-6 border-t border-gray-200 pt-4 dark:border-surface-800">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ t('This session') }}
                    </h3>
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <!-- shrink-0 must sit on the flex child, which is now the Tooltip
                             wrapper, not the button — otherwise the strip squashes its
                             thumbnails instead of scrolling. -->
                        <Tooltip
                            v-for="asset in results"
                            :key="asset.ulid"
                            :content="asset.prompt ?? t('Result')"
                            class="shrink-0"
                        >
                            <button
                                type="button"
                                class="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border transition"
                                :class="asset.ulid === focusedUlid
                                    ? 'border-primary-400 ring-2 ring-primary-200 dark:border-primary-600 dark:ring-primary-500/20'
                                    : 'border-gray-200 hover:border-primary-300 dark:border-surface-700'"
                                @click="focusedUlid = asset.ulid"
                            >
                                <img
                                    :src="asset.thumb_url ?? asset.url"
                                    :alt="asset.prompt ?? t('Result')"
                                    class="h-full w-full object-cover"
                                />
                                <i
                                    v-if="asset.is_favorite"
                                    class="ti ti-star-filled absolute right-1 top-1 text-xs text-amber-400 drop-shadow"
                                ></i>
                            </button>
                        </Tooltip>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- ============================================================ *
     * TOOLS — every operation the admin has enabled, grouped.
     * ============================================================ -->
    <div v-if="toolsOpen" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="toolsOpen = false"></div>

        <div
            class="relative flex max-h-[85vh] w-full flex-col overflow-hidden rounded-t-3xl border border-gray-200 bg-white shadow-2xl sm:max-w-3xl sm:rounded-3xl dark:border-surface-700 dark:bg-surface-900"
            role="dialog"
            aria-modal="true"
            :aria-label="t('Tools')"
        >
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ t('Tools') }}</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        <template v-if="freeToolCount > 0">
                            {{ t('Keep working on your image without leaving the page — :count of these are free.', { count: String(freeToolCount) }) }}
                        </template>
                        <template v-else>
                            {{ t('Keep working on your image without leaving the page.') }}
                        </template>
                    </p>
                </div>

                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                    :aria-label="t('Close')"
                    @click="toolsOpen = false"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-5">
                <p v-if="usableOps.length === 0" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ t('No tools are available yet.') }}
                </p>

                <div v-for="group in toolGroups" :key="group.key" class="mb-6 last:mb-0">
                    <h3 class="mb-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ group.label }}
                    </h3>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="op in group.ops"
                            :key="op.key"
                            type="button"
                            class="group flex items-start gap-3 rounded-2xl border bg-white p-3.5 text-left transition dark:bg-surface-900"
                            :class="op.key === activeKey
                                ? 'border-primary-400 ring-2 ring-primary-200 dark:border-primary-600 dark:ring-primary-500/20'
                                : 'border-gray-200 hover:border-primary-300 hover:shadow-sm dark:border-surface-700 dark:hover:border-primary-700'"
                            @click="onToolSelect(op)"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 transition group-hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-400"
                            >
                                <i :class="op.icon" class="text-lg"></i>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ op.label }}
                                    </span>
                                    <Tooltip v-if="op.locked" :content="t('Sign in to use this tool')">
                                        <i class="ti ti-lock shrink-0 text-xs text-gray-400"></i>
                                    </Tooltip>
                                </span>

                                <span
                                    v-if="op.description"
                                    class="mt-0.5 block text-xs leading-snug text-gray-500 dark:text-gray-400"
                                >
                                    {{ op.description }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tool panel slide-over -->
    <div v-if="activeOp" class="fixed inset-0 z-40 lg:inset-y-0 lg:left-auto lg:right-0">
        <div class="absolute inset-0 bg-black/40 lg:hidden" @click="clearActiveOp"></div>
        <div class="absolute inset-x-0 bottom-0 h-[70vh] lg:inset-y-0 lg:right-0 lg:h-full lg:w-80">
            <ToolPanel
                :op="activeOp"
                :limits="limits"
                :target-asset="focusedAsset"
                :is-guest="isGuest"
                :busy="panelBusy"
                :error="panelError"
                @run="onPanelRun"
                @close="clearActiveOp"
                @signin="signin"
            />
        </div>
    </div>

    <!-- Preview modal — the Library's lightbox, on the Studio's results. -->
    <AssetPreviewModal
        :asset="previewAsset"
        :models="models"
        :allow-model-choice="allowModelChoice"
        :operations="operations"
        :can-variations="canVariations"
        @close="previewAsset = null"
        @action="onPreviewAction"
    />

    <!-- Editor modal -->
    <EditorModal
        v-if="editorAsset"
        :asset="editorAsset"
        :operations="operations"
        :limits="limits"
        :is-guest="isGuest"
        :busy="editorBusy"
        :error="editorError"
        @submit="onEditorSubmit"
        @close="editorAsset = null"
        @signin="signin"
    />
</template>
