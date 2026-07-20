import axios from 'axios'
import { computed, onUnmounted, ref, type ComputedRef, type Ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

/* ------------------------------------------------------------------ *
 * Shared domain types — these mirror the addon's HTTP contract.
 * Every component in the Studio imports its types from here so the
 * shape of an Asset / Op / Model / Preset is declared exactly once.
 * ------------------------------------------------------------------ */

export interface Asset {
    ulid: string
    url: string
    thumb_url: string | null
    width: number | null
    height: number | null
    mime: string
    bytes: number
    prompt: string | null
    negative_prompt: string | null
    model: string | null
    provider: string | null
    seed: number | null
    operation: string | null
    source: string
    is_favorite: boolean
    folder_id: number | null
    parent_ulid: string | null
    created_at: string
}

/** Tool-rail grouping — mirrors OperationRegistry::GROUP_*. */
export type OpGroup = 'create' | 'enhance' | 'adjust'

/** Engine that runs the op — mirrors OperationRegistry::TIER_*. */
export type OpTier = 'generate' | 'provider' | 'local'

/** How the op is charged — mirrors OperationRegistry::BILLING_*. */
export type OpBilling = 'media' | 'flat' | 'free'

/** What the request must carry (registry `inputs`). */
export type OpInput = 'image' | 'mask' | 'prompt' | 'reference' | 'text'

export interface Op {
    key: string
    label: string
    /** One-line "what this does", from OperationRegistry. Translated server-side. */
    description: string
    icon: string
    group: OpGroup
    tier: OpTier
    billing: OpBilling
    inputs: OpInput[]
    credits: number
    async: boolean
    /** false = the engine behind it has no API key configured. */
    available: boolean
    /** true = the user's access level does not permit it (guest / plan gate). */
    locked: boolean
    access: string
}

export interface ModelSupports {
    negative_prompt: boolean
    seed: boolean
    reference: boolean
    batch: boolean
}

export interface Model {
    /** Public alias (`google-nano-banana`) — never the provider's own model id. */
    id: string
    name: string
    provider: string
    credits: number
    sizes: string[]
    supports: ModelSupports
}

export interface Preset {
    slug: string
    name: string
    prompt_suffix: string
    thumb_url: string | null
}

/**
 * An asset records the operation that produced it as a registry key (`bg_remove`), which
 * is not a label. Resolve it against the live operation list to get the admin-facing
 * name ("Remove background"); fall back to a humanised key for an operation that has
 * since been disabled, so an old asset never shows a bare `bg_remove`.
 */
export function operationLabel(operations: Op[], key: string | null | undefined): string | null {
    if (!key) return null

    const known = operations.find((op) => op.key === key)
    if (known) return known.label

    const words = key.replace(/[_-]+/g, ' ').trim()

    return words.charAt(0).toUpperCase() + words.slice(1)
}

/**
 * An asset records the model it was made with as a public alias (`google-nano-banana`),
 * which is an identifier, not a label. Resolve it against the catalogue the admin
 * configured to get the display name and provider they chose.
 *
 * Falls back to the alias itself when the model is not in the catalogue — an admin can
 * disable a model after images were made with it, and the alias still beats an empty
 * field. It is safe to show: it is already the public name for that model.
 */
export function modelLabel(
    models: Model[],
    id: string | null | undefined,
): { name: string; provider: string | null } | null {
    if (!id) return null

    const known = models.find((model) => model.id === id)

    return known
        ? { name: known.name, provider: known.provider }
        : { name: id, provider: null }
}

export interface AspectRatio {
    key: string
    label: string
    width: number
    height: number
}

export interface StudioLimits {
    maxInputSizeMb: number
    maxInputDimension: number
    maxOutputDimension: number
}

export interface StudioFeatures {
    promptEnhancer: boolean
    negativePrompt: boolean
    seed: boolean
}

/* ------------------------------------------------------------------ *
 * Operation parameters
 * ------------------------------------------------------------------ */

export type ParamValue = string | number | boolean | null
export type ParamMap = Record<string, ParamValue>

export type ToolFieldType = 'text' | 'textarea' | 'number' | 'range' | 'select' | 'color' | 'toggle'

export interface ToolFieldOption {
    value: ParamValue
    label: string
}

export interface ToolField {
    name: string
    /** Source (English) string — rendered through t() at display time. */
    label: string
    type: ToolFieldType
    default: ParamValue
    min?: number
    max?: number
    step?: number
    options?: ToolFieldOption[]
    placeholder?: string
    help?: string
    /** Clamp against a limit coming from the server (`limits.maxOutputDimension`). */
    clampTo?: 'maxOutputDimension'
}

/**
 * Parameter schema per operation key.
 *
 * This is *presentation* only — it never decides whether an op exists, what it
 * costs or who may run it (that all comes from the `operations` prop). An op
 * with no entry here still renders: `fieldsFor()` derives a usable form from
 * its registry `inputs`, so a new operation added server-side works on day one.
 */
export const OPERATION_FIELDS: Record<string, ToolField[]> = {
    upscale: [
        {
            name: 'scale',
            label: 'Scale factor',
            type: 'select',
            default: 4,
            options: [
                { value: 2, label: '2×' },
                { value: 4, label: '4×' },
            ],
        },
    ],
    outpaint: [
        { name: 'left', label: 'Left', type: 'number', default: 0, min: 0, max: 2000, step: 8 },
        { name: 'right', label: 'Right', type: 'number', default: 0, min: 0, max: 2000, step: 8 },
        { name: 'up', label: 'Up', type: 'number', default: 0, min: 0, max: 2000, step: 8 },
        { name: 'down', label: 'Down', type: 'number', default: 0, min: 0, max: 2000, step: 8 },
        { name: 'prompt', label: 'Prompt', type: 'text', default: '', placeholder: 'What should appear in the new area?' },
    ],
    inpaint: [
        { name: 'prompt', label: 'Prompt', type: 'textarea', default: '', placeholder: 'What should fill the masked area?' },
        { name: 'negative_prompt', label: 'Negative prompt', type: 'text', default: '' },
    ],
    bg_replace: [
        { name: 'prompt', label: 'New background', type: 'textarea', default: '', placeholder: 'Describe the background to place behind the subject' },
    ],
    style_transfer: [
        { name: 'fidelity', label: 'Fidelity', type: 'range', default: 0.5, min: 0, max: 1, step: 0.1 },
    ],
    resize: [
        { name: 'width', label: 'Width', type: 'number', default: 1024, min: 1, max: 16000, clampTo: 'maxOutputDimension' },
        { name: 'height', label: 'Height', type: 'number', default: 1024, min: 1, max: 16000, clampTo: 'maxOutputDimension' },
        { name: 'keep_aspect', label: 'Keep aspect ratio', type: 'toggle', default: true },
    ],
    crop: [
        { name: 'x', label: 'X', type: 'number', default: 0, min: 0 },
        { name: 'y', label: 'Y', type: 'number', default: 0, min: 0 },
        { name: 'width', label: 'Width', type: 'number', default: 512, min: 1 },
        { name: 'height', label: 'Height', type: 'number', default: 512, min: 1 },
    ],
    rotate: [
        {
            name: 'degrees',
            label: 'Rotate',
            type: 'select',
            default: 90,
            options: [
                { value: 0, label: '0°' },
                { value: 90, label: '90°' },
                { value: 180, label: '180°' },
                { value: 270, label: '270°' },
            ],
        },
        {
            name: 'flip',
            label: 'Flip',
            type: 'select',
            default: 'none',
            options: [
                { value: 'none', label: 'None' },
                { value: 'h', label: 'Horizontal' },
                { value: 'v', label: 'Vertical' },
            ],
        },
    ],
    compress: [
        { name: 'quality', label: 'Quality', type: 'range', default: 80, min: 1, max: 100, step: 1 },
        { name: 'target_kb', label: 'Target size (KB)', type: 'number', default: 0, min: 0, help: 'Leave at 0 to use the quality slider.' },
    ],
    convert: [
        {
            name: 'format',
            label: 'Format',
            type: 'select',
            default: 'webp',
            options: [
                { value: 'png', label: 'PNG' },
                { value: 'jpg', label: 'JPG' },
                { value: 'webp', label: 'WebP' },
                { value: 'avif', label: 'AVIF' },
            ],
        },
    ],
    watermark: [
        { name: 'text', label: 'Text', type: 'text', default: '', placeholder: '© Your brand' },
        {
            name: 'position',
            label: 'Position',
            type: 'select',
            default: 'bottom-right',
            options: [
                { value: 'top-left', label: 'Top left' },
                { value: 'top-right', label: 'Top right' },
                { value: 'center', label: 'Center' },
                { value: 'bottom-left', label: 'Bottom left' },
                { value: 'bottom-right', label: 'Bottom right' },
            ],
        },
        { name: 'opacity', label: 'Opacity', type: 'range', default: 60, min: 5, max: 100, step: 5 },
        { name: 'size', label: 'Size', type: 'number', default: 24, min: 8, max: 200 },
        { name: 'color', label: 'Color', type: 'color', default: '#ffffff' },
    ],
    text_overlay: [
        { name: 'text', label: 'Text', type: 'text', default: '', placeholder: 'Enter text' },
        { name: 'font_size', label: 'Font size', type: 'number', default: 48, min: 8, max: 400 },
        { name: 'font_color', label: 'Color', type: 'color', default: '#ffffff' },
        { name: 'x', label: 'X position', type: 'number', default: 0, min: 0 },
        { name: 'y', label: 'Y position', type: 'number', default: 50, min: 0 },
    ],
    color_correction: [
        { name: 'brightness', label: 'Brightness', type: 'range', default: 0, min: -100, max: 100, step: 1 },
        { name: 'contrast', label: 'Contrast', type: 'range', default: 0, min: -100, max: 100, step: 1 },
        { name: 'saturation', label: 'Saturation', type: 'range', default: 0, min: -100, max: 100, step: 1 },
        { name: 'hue', label: 'Hue', type: 'range', default: 0, min: 0, max: 360, step: 1 },
        { name: 'sharpness', label: 'Sharpness', type: 'range', default: 0, min: 0, max: 100, step: 1 },
    ],
}

/** Fields the op needs, falling back to a form derived from its registry inputs. */
export function fieldsFor(op: Op): ToolField[] {
    const schema = OPERATION_FIELDS[op.key]
    if (schema) {
        return schema
    }

    const derived: ToolField[] = []

    if (op.inputs.includes('prompt')) {
        derived.push({ name: 'prompt', label: 'Prompt', type: 'textarea', default: '', placeholder: 'Describe what you want' })
    }

    if (op.inputs.includes('text')) {
        derived.push({ name: 'text', label: 'Text', type: 'text', default: '', placeholder: 'Enter text' })
    }

    return derived
}

export function defaultParams(op: Op): ParamMap {
    const params: ParamMap = {}
    fieldsFor(op).forEach((field) => {
        params[field.name] = field.default
    })

    return params
}

/* ------------------------------------------------------------------ *
 * Job polling
 * ------------------------------------------------------------------ */

export type JobStatus = 'queued' | 'processing' | 'completed' | 'failed'

/** `{success, job:{ulid,status}}` — what /generate and /ops/{operation} return. */
export interface JobRef {
    ulid: string
    status: JobStatus
}

/** GET /ai-image/jobs/{ulid}/status */
export interface JobStatusResponse {
    status: JobStatus
    error: string | null
    assets: Asset[]
}

export interface TrackedJob {
    ulid: string
    operation: string
    /** Human label for the skeleton tile / error card. */
    label: string
    /** How many result tiles this job will produce — drives the skeletons. */
    expected: number
    status: JobStatus
    error: string | null
    startedAt: number
    /** Consecutive network failures — a flaky poll must not kill the job. */
    networkErrors: number
    /**
     * Still `queued` long after it was accepted — i.e. no worker has picked it up.
     * RunImageJob calls markProcessing() the instant it starts, so a job that never
     * leaves `queued` is not slow, it is unattended: nothing is consuming its queue.
     * Left polling (a real backlog does eventually drain) but reported to the user,
     * because the alternative is a spinner that runs for the full timeout and then
     * says "took too long", which diagnoses nothing.
     */
    stalled: boolean
    /** Re-dispatches the exact request that created this job. */
    retry?: () => void
}

export interface TrackJobMeta {
    operation: string
    label: string
    expected?: number
    retry?: () => void
}

export interface UseImageJobsOptions {
    onCompleted?: (job: TrackedJob, assets: Asset[]) => void
    onFailed?: (job: TrackedJob) => void
    /** Give up on a job that never settles (default 10 min). */
    timeoutMs?: number
}

export interface UseImageJobs {
    jobs: Ref<TrackedJob[]>
    pending: ComputedRef<TrackedJob[]>
    failed: ComputedRef<TrackedJob[]>
    isBusy: ComputedRef<boolean>
    /** True once any in-flight job has sat unclaimed on its queue — see TrackedJob.stalled. */
    isStalled: ComputedRef<boolean>
    track: (job: JobRef, meta: TrackJobMeta) => TrackedJob
    dismiss: (ulid: string) => void
    retry: (ulid: string) => void
    stop: () => void
}

const BASE_DELAY_MS = 1500
const MAX_DELAY_MS = 8000
const BACKOFF_FACTOR = 1.35
const MAX_NETWORK_ERRORS = 4

/**
 * How long a job may sit at `queued` before we say so. Generous enough to ride out a
 * genuine backlog on a busy site, short enough that a misconfigured worker is obvious
 * long before the 10-minute timeout.
 */
const STALLED_AFTER_MS = 45_000

/**
 * One poller for every in-flight job.
 *
 * A single timer walks the whole pending set each tick and backs off as the
 * wait grows, instead of one interval per tile — twenty queued variants stay
 * one request per tick, not twenty timers hammering the server.
 */
export function useImageJobs(options: UseImageJobsOptions = {}): UseImageJobs {
    const { t } = useTranslate()

    const timeoutMs = options.timeoutMs ?? 10 * 60 * 1000

    const jobs = ref<TrackedJob[]>([])
    const pending = computed(() => jobs.value.filter((job) => job.status === 'queued' || job.status === 'processing'))
    const failed = computed(() => jobs.value.filter((job) => job.status === 'failed'))
    const isBusy = computed(() => pending.value.length > 0)
    const isStalled = computed(() => pending.value.some((job) => job.stalled))

    let timer: ReturnType<typeof setTimeout> | null = null
    let delay = BASE_DELAY_MS
    let disposed = false

    function schedule(): void {
        if (disposed || timer !== null || pending.value.length === 0) {
            return
        }

        timer = setTimeout(() => {
            timer = null
            void tick()
        }, delay)
    }

    async function tick(): Promise<void> {
        const inFlight = pending.value.slice()

        if (inFlight.length === 0) {
            delay = BASE_DELAY_MS

            return
        }

        await Promise.all(inFlight.map((job) => poll(job)))

        delay = Math.min(Math.round(delay * BACKOFF_FACTOR), MAX_DELAY_MS)
        schedule()
    }

    function markFailed(job: TrackedJob, message: string): void {
        job.status = 'failed'
        job.error = message
        options.onFailed?.(job)
    }

    async function poll(job: TrackedJob): Promise<void> {
        if (Date.now() - job.startedAt > timeoutMs) {
            markFailed(job, t('This job took too long to finish. Please try again.'))

            return
        }

        try {
            const { data } = await axios.get<JobStatusResponse>(
                route('addon.aip.user.jobs.status', { job: job.ulid }),
            )

            job.networkErrors = 0
            job.status = data.status

            // Never claimed by a worker. Not "slow" — unattended.
            job.stalled = data.status === 'queued' && Date.now() - job.startedAt > STALLED_AFTER_MS

            if (data.status === 'completed') {
                remove(job.ulid)
                options.onCompleted?.(job, Array.isArray(data.assets) ? data.assets : [])

                return
            }

            if (data.status === 'failed') {
                markFailed(job, data.error || t('The operation failed. Please try again.'))
            }
        } catch {
            job.networkErrors += 1

            if (job.networkErrors >= MAX_NETWORK_ERRORS) {
                markFailed(job, t('We lost contact with the server while this job was running.'))
            }
        }
    }

    function remove(ulid: string): void {
        jobs.value = jobs.value.filter((job) => job.ulid !== ulid)
    }

    function track(job: JobRef, meta: TrackJobMeta): TrackedJob {
        const tracked: TrackedJob = {
            ulid: job.ulid,
            operation: meta.operation,
            label: meta.label,
            expected: Math.max(1, meta.expected ?? 1),
            status: job.status ?? 'queued',
            error: null,
            startedAt: Date.now(),
            networkErrors: 0,
            stalled: false,
            retry: meta.retry,
        }

        jobs.value = [tracked, ...jobs.value.filter((existing) => existing.ulid !== tracked.ulid)]

        // A fresh job restarts the backoff — the first result usually lands fast.
        delay = BASE_DELAY_MS
        schedule()

        return tracked
    }

    function dismiss(ulid: string): void {
        remove(ulid)
    }

    function retry(ulid: string): void {
        const job = jobs.value.find((candidate) => candidate.ulid === ulid)

        if (!job) {
            return
        }

        const again = job.retry
        remove(ulid)
        again?.()
    }

    function stop(): void {
        disposed = true

        if (timer !== null) {
            clearTimeout(timer)
            timer = null
        }
    }

    onUnmounted(stop)

    return { jobs, pending, failed, isBusy, isStalled, track, dismiss, retry, stop }
}
