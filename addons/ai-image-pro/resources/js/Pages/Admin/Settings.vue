<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import RepeaterField from '../../Components/Admin/RepeaterField.vue'
import type { RepeaterRow, RepeaterFieldDef } from '../../Components/Admin/RepeaterField.vue'
import SettingsSection from '../../Components/Admin/SettingsSection.vue'
import { mediaUrl } from '@/lib/media'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

/* ── Types ───────────────────────────────────────────────── */
interface AspectRatio {
    key: string
    label: string
    width: number
    height: number
}

interface AdminOp {
    key: string
    label: string
    group: string
    tier: string
    billing: string
    enabled: boolean
    access: string
    provider: string
    providers: string[]
    credits: number
    available: boolean
    requires_key: string | null
}

interface AccessLevel {
    value: string
    label: string
}

interface ImageModel {
    slug: string
    name: string
    provider: string
    is_active: boolean
    /** Real provider price per image, in USD. */
    cost_per_unit: number
    /** Credits one image costs when no override is set (USD × markup ÷ credit price). */
    derived_credits: number
    /** Admin's explicit credits-per-image price. Null = use the derived cost. */
    credits_override: number | null
}

interface Preset {
    id: number
    name: string
    slug: string
    prompt_suffix: string | null
    negative_prompt: string | null
    thumb_url: string | null
    is_active: boolean
    sort: number
}

interface FaqCategoryOption {
    id: number
    name: string
    faqs_count?: number
}

/* Landing row shapes (type aliases — they carry an implicit string index
   signature, so they stay compatible with the generic RepeaterRow). */
type LandingExample = { title: string; description: string; image: string; prompt: string }
type LandingFeature = { title: string; body: string; image: string; cta_text: string; cta_link: string; cta_icon: string }
type LandingUsecase = { icon: string; title: string; body: string }
type LandingBenefit = { icon: string; title: string; body: string }
type LandingStep = { title: string; body: string; image: string }

/** The only two widths the public page implements. */
type LandingPageWidth = 'default' | 'boxed'

interface ImageProSettings {
    enabled: boolean
    studio_access: string
    library_access: string
    generation_models: string[] | null
    default_model: string | null
    allow_user_model_choice: boolean
    max_batch_size: number
    aspect_ratios: AspectRatio[] | null
    enable_prompt_enhancer: boolean
    credits_prompt_enhancer: number
    enable_negative_prompt: boolean
    enable_seed: boolean
    max_input_size_mb: number
    max_input_dimension: number
    max_output_dimension: number
    guest_daily_limit: number
    user_daily_limit: number
    plan_daily_limits: Record<string, number> | null
    max_storage_mb_per_user: number
    retention_days_guest: number
    retention_days_free: number
    retention_days_paid: number
    auto_save_to_library: boolean
    mirror_to_documents: boolean
    thumbnail_width: number
    watermark_enabled: boolean
    watermark_logo_path: string
    watermark_text: string
    watermark_position: string
    watermark_opacity: number
    /* ── Studio ── */
    studio_heading: string
    studio_subheading: string
    /* ── Landing page ── */
    landing_page_width: LandingPageWidth
    landing_gradient_enabled: boolean
    landing_show_breadcrumb: boolean
    landing_hero_badge: string
    landing_hero_heading: string
    landing_hero_subheading: string
    landing_examples_enabled: boolean
    landing_examples_heading: string
    landing_examples: LandingExample[] | null
    landing_features_enabled: boolean
    landing_features_heading: string
    landing_features_subheading: string
    landing_features: LandingFeature[] | null
    landing_usecases_enabled: boolean
    landing_usecases_heading: string
    landing_usecases_subheading: string
    landing_usecases: LandingUsecase[] | null
    landing_benefits_enabled: boolean
    landing_benefits_heading: string
    landing_benefits_subheading: string
    landing_benefits: LandingBenefit[] | null
    landing_steps_enabled: boolean
    landing_steps_heading: string
    landing_steps_subheading: string
    landing_steps: LandingStep[] | null
    landing_about_enabled: boolean
    landing_about_heading: string
    landing_about_body: string
    landing_faq_enabled: boolean
    landing_faq_heading: string
    landing_faq_category_id: number
    landing_cta_enabled: boolean
    landing_cta_heading: string
    landing_cta_subheading: string
    landing_cta_button_text: string
    landing_cta_button_link: string
    /* ── SEO ── */
    seo_title: string
    seo_description: string
}

interface OperationOverride {
    enabled: boolean
    access: string
    provider: string
    credits: number
}

const props = defineProps<{
    settings: ImageProSettings
    apiKeyStatus: Record<string, string>
    operations: AdminOp[]
    accessLevels: AccessLevel[]
    imageModels: ImageModel[]
    presets: Preset[]
    /** Core FAQ categories, for the landing-page FAQ picker. */
    faqCategories?: FaqCategoryOption[]
    /** Active paid plans, for the per-plan daily-limit table. Empty when pro is off. */
    plans?: { slug: string; name: string }[]
    /**
     * Whether subscriptions exist at all (Extended license + subscriptions on). When
     * false the app runs in credit *quota* mode: there are no plans and no premium
     * tier, so plan-scoped controls are hidden rather than shown as dead knobs.
     */
    proAvailable?: boolean
}>()

/* ── Constants ───────────────────────────────────────────── */
const DEFAULT_ASPECTS: AspectRatio[] = [
    { key: '1:1', label: 'Square', width: 1024, height: 1024 },
    { key: '16:9', label: 'Landscape', width: 1344, height: 768 },
    { key: '9:16', label: 'Portrait', width: 768, height: 1344 },
    { key: '4:3', label: 'Standard', width: 1152, height: 896 },
    { key: '3:2', label: 'Photo', width: 1216, height: 832 },
    { key: '3:4', label: 'Tall', width: 896, height: 1152 },
    { key: '2:3', label: 'Book', width: 832, height: 1216 },
]

const providerLabels: Record<string, string> = {
    stability: t('Stability AI'),
    replicate: t('Replicate'),
    remove_bg: t('Remove.bg'),
    clipdrop: t('Clipdrop'),
    fal: t('fal.ai'),
    ideogram: t('Ideogram'),
    gd: t('Local (GD)'),
    model: t('AI Model'),
}
const providerLabel = (p: string) => providerLabels[p] ?? p

const groupLabels: Record<string, string> = {
    create: t('Create'),
    enhance: t('Enhance'),
    adjust: t('Adjust'),
}
const groupLabel = (g: string) => groupLabels[g] ?? g

const tierLabels: Record<string, string> = {
    generate: t('Generate'),
    provider: t('Provider'),
    local: t('Local'),
}

const apiKeyFields = [
    { key: 'stability_api_key', label: t('Stability AI API Key') },
    { key: 'replicate_api_key', label: t('Replicate API Key') },
    { key: 'remove_bg_api_key', label: t('Remove.bg API Key') },
    { key: 'clipdrop_api_key', label: t('Clipdrop API Key') },
    { key: 'fal_api_key', label: t('fal.ai API Key') },
    { key: 'ideogram_api_key', label: t('Ideogram API Key') },
] as const

/* ── Tabs ────────────────────────────────────────────────── */
const tabs = [
    { key: 'general', label: t('General'), icon: 'ti ti-settings' },
    { key: 'operations', label: t('Operations'), icon: 'ti ti-list-check' },
    { key: 'generation', label: t('Generation'), icon: 'ti ti-sparkles' },
    { key: 'providers', label: t('Providers'), icon: 'ti ti-key' },
    { key: 'limits', label: t('Limits'), icon: 'ti ti-shield' },
    { key: 'storage', label: t('Storage'), icon: 'ti ti-database' },
    { key: 'watermark', label: t('Watermark'), icon: 'ti ti-copyright' },
    { key: 'presets', label: t('Style Presets'), icon: 'ti ti-palette' },
    { key: 'landing', label: t('Landing Page'), icon: 'ti ti-layout-board' },
] as const

const activeTab = ref<string>('general')

/* ── Landing repeater field definitions ──────────────────── */
const exampleFields: RepeaterFieldDef[] = [
    { key: 'title', label: t('Title'), type: 'text', placeholder: t('Create data infographic on coffee') },
    { key: 'description', label: t('Description'), type: 'textarea', placeholder: t('Short two-line description shown under the title.') },
    { key: 'image', label: t('Thumbnail Image'), type: 'image' },
    { key: 'prompt', label: t('Prompt (sent to the Studio)'), type: 'textarea', placeholder: t('A photorealistic portrait of…') },
]

const featureFields: RepeaterFieldDef[] = [
    { key: 'title', label: t('Heading'), type: 'text', placeholder: t('Text to image in seconds') },
    { key: 'body', label: t('Body'), type: 'textarea' },
    { key: 'image', label: t('Image'), type: 'image' },
    { key: 'cta_text', label: t('Button Text'), type: 'text', placeholder: t('Try it now') },
    { key: 'cta_link', label: t('Button Link'), type: 'text', placeholder: '/ai-image/studio' },
    { key: 'cta_icon', label: t('Button Icon (revealed on hover)'), type: 'icon', placeholder: t('Search icons…') },
]

const usecaseFields: RepeaterFieldDef[] = [
    { key: 'icon', label: t('Icon'), type: 'icon', placeholder: t('Search icons…') },
    { key: 'title', label: t('Title'), type: 'text', placeholder: t('Marketing & advertising') },
    { key: 'body', label: t('Body'), type: 'textarea' },
]

const benefitFields: RepeaterFieldDef[] = [
    { key: 'icon', label: t('Icon'), type: 'icon', placeholder: t('Search icons…') },
    { key: 'title', label: t('Title'), type: 'text', placeholder: t('Speed that matches your ideas') },
    { key: 'body', label: t('Body'), type: 'textarea' },
]

const stepFields: RepeaterFieldDef[] = [
    { key: 'title', label: t('Title'), type: 'text', placeholder: t('Enter your text prompt') },
    { key: 'body', label: t('Body'), type: 'textarea' },
    { key: 'image', label: t('Image'), type: 'image' },
]

function toRows(list: RepeaterRow[] | null | undefined, fields: RepeaterFieldDef[]): RepeaterRow[] {
    if (!Array.isArray(list)) return []
    return list.map((item) => {
        const row: RepeaterRow = {}
        for (const field of fields) {
            row[field.key] = typeof item?.[field.key] === 'string' ? item[field.key] : ''
        }
        return row
    })
}

/* ── Options ─────────────────────────────────────────────── */
const accessOptions = computed(() => props.accessLevels.map((a) => ({ value: a.value, label: a.label })))
const pageWidthOptions = computed(() => [
    { value: 'default', label: t('Default') },
    { value: 'boxed', label: t('Boxed') },
])
/** AppSelect emits the broad value union; narrow it back to the two widths the page supports. */
function setPageWidth(value: string | number | null | (string | number)[]): void {
    form.landing_page_width = value === 'boxed' ? 'boxed' : 'default'
}
const faqCategoryOptions = computed(() => [
    { value: 0, label: t('None — hide the FAQ section') },
    ...(props.faqCategories ?? []).map((c) => ({
        value: c.id,
        label: c.faqs_count === undefined
            ? c.name
            : `${c.name} (${c.faqs_count} ${c.faqs_count === 1 ? t('question') : t('questions')})`,
    })),
])
const landingUrl = computed<string>(() => {
    try {
        return String(route('addon.aip.user.landing'))
    } catch {
        return ''
    }
})
/** Admins can always view the marketing page, even when they have studio access. */
const landingPreviewUrl = computed<string>(() => (landingUrl.value ? `${landingUrl.value}?preview=1` : ''))
const modelOptions = computed(() => props.imageModels.map((m) => ({
    value: m.slug,
    label: m.is_active ? m.name : `${m.name} (${t('inactive')})`,
})))
const defaultModelOptions = computed(() => [
    { value: '', label: t('First available model') },
    ...modelOptions.value,
])

/* ── Form ────────────────────────────────────────────────── */
const operationsInit: Record<string, OperationOverride> = {}
for (const op of props.operations) {
    operationsInit[op.key] = {
        enabled: op.enabled,
        access: op.access,
        provider: op.provider,
        credits: op.credits,
    }
}

/**
 * Per-image credit price, per model. Blank = charge the derived cost (USD × markup).
 * This is what the media-billed ops (generate / variations / edit) actually cost, which
 * is why it is edited per model rather than per operation: all three can run on any
 * model, and each model costs a different amount.
 */
const modelCreditsInit: Record<string, number | string> = {}
for (const model of props.imageModels) {
    modelCreditsInit[model.slug] = model.credits_override ?? ''
}

const form = useForm({
    enabled: props.settings.enabled,
    studio_access: props.settings.studio_access,
    library_access: props.settings.library_access,
    generation_models: props.settings.generation_models ?? [],
    default_model: props.settings.default_model ?? '',
    allow_user_model_choice: props.settings.allow_user_model_choice,
    max_batch_size: props.settings.max_batch_size,
    aspect_ratios: props.settings.aspect_ratios && props.settings.aspect_ratios.length
        ? props.settings.aspect_ratios.map((a) => ({ ...a }))
        : DEFAULT_ASPECTS.map((a) => ({ ...a })),
    enable_prompt_enhancer: props.settings.enable_prompt_enhancer,
    credits_prompt_enhancer: props.settings.credits_prompt_enhancer,
    enable_negative_prompt: props.settings.enable_negative_prompt,
    enable_seed: props.settings.enable_seed,
    stability_api_key: '',
    replicate_api_key: '',
    remove_bg_api_key: '',
    clipdrop_api_key: '',
    fal_api_key: '',
    ideogram_api_key: '',
    max_input_size_mb: props.settings.max_input_size_mb,
    max_input_dimension: props.settings.max_input_dimension,
    max_output_dimension: props.settings.max_output_dimension,
    guest_daily_limit: props.settings.guest_daily_limit,
    user_daily_limit: props.settings.user_daily_limit,
    plan_daily_limits: { ...(props.settings.plan_daily_limits ?? {}) } as Record<string, number | string>,
    max_storage_mb_per_user: props.settings.max_storage_mb_per_user,
    retention_days_guest: props.settings.retention_days_guest ?? 1,
    retention_days_free: props.settings.retention_days_free,
    retention_days_paid: props.settings.retention_days_paid,
    auto_save_to_library: props.settings.auto_save_to_library,
    mirror_to_documents: props.settings.mirror_to_documents,
    thumbnail_width: props.settings.thumbnail_width,
    watermark_enabled: props.settings.watermark_enabled,
    watermark_logo_path: props.settings.watermark_logo_path ?? '',
    watermark_text: props.settings.watermark_text,
    watermark_position: props.settings.watermark_position ?? 'bottom-right',
    watermark_opacity: props.settings.watermark_opacity ?? 60,
    operations: operationsInit,
    model_credits: modelCreditsInit,

    /* ── Studio ── */
    studio_heading: props.settings.studio_heading ?? '',
    studio_subheading: props.settings.studio_subheading ?? '',

    /* ── Landing: layout & style ── */
    landing_page_width: (props.settings.landing_page_width ?? 'default') as LandingPageWidth,
    landing_gradient_enabled: props.settings.landing_gradient_enabled ?? false,

    /* ── Landing: hero ── */
    landing_show_breadcrumb: props.settings.landing_show_breadcrumb ?? true,
    landing_hero_badge: props.settings.landing_hero_badge ?? '',
    landing_hero_heading: props.settings.landing_hero_heading ?? '',
    landing_hero_subheading: props.settings.landing_hero_subheading ?? '',

    /* ── Landing: examples ── */
    landing_examples_enabled: props.settings.landing_examples_enabled ?? true,
    landing_examples_heading: props.settings.landing_examples_heading ?? '',
    landing_examples: toRows(props.settings.landing_examples, exampleFields),

    /* ── Landing: features ── */
    landing_features_enabled: props.settings.landing_features_enabled ?? true,
    landing_features_heading: props.settings.landing_features_heading ?? '',
    landing_features_subheading: props.settings.landing_features_subheading ?? '',
    landing_features: toRows(props.settings.landing_features, featureFields),

    /* ── Landing: use cases ── */
    landing_usecases_enabled: props.settings.landing_usecases_enabled ?? true,
    landing_usecases_heading: props.settings.landing_usecases_heading ?? '',
    landing_usecases_subheading: props.settings.landing_usecases_subheading ?? '',
    landing_usecases: toRows(props.settings.landing_usecases, usecaseFields),

    /* ── Landing: benefits ── */
    landing_benefits_enabled: props.settings.landing_benefits_enabled ?? true,
    landing_benefits_heading: props.settings.landing_benefits_heading ?? '',
    landing_benefits_subheading: props.settings.landing_benefits_subheading ?? '',
    landing_benefits: toRows(props.settings.landing_benefits, benefitFields),

    /* ── Landing: how it works ── */
    landing_steps_enabled: props.settings.landing_steps_enabled ?? true,
    landing_steps_heading: props.settings.landing_steps_heading ?? '',
    landing_steps_subheading: props.settings.landing_steps_subheading ?? '',
    landing_steps: toRows(props.settings.landing_steps, stepFields),

    /* ── Landing: about ── */
    landing_about_enabled: props.settings.landing_about_enabled ?? true,
    landing_about_heading: props.settings.landing_about_heading ?? '',
    landing_about_body: props.settings.landing_about_body ?? '',

    /* ── Landing: FAQ ── */
    landing_faq_enabled: props.settings.landing_faq_enabled ?? true,
    landing_faq_heading: props.settings.landing_faq_heading ?? '',
    landing_faq_category_id: props.settings.landing_faq_category_id ?? 0,

    /* ── Landing: CTA banner ── */
    landing_cta_enabled: props.settings.landing_cta_enabled ?? true,
    landing_cta_heading: props.settings.landing_cta_heading ?? '',
    landing_cta_subheading: props.settings.landing_cta_subheading ?? '',
    landing_cta_button_text: props.settings.landing_cta_button_text ?? '',
    landing_cta_button_link: props.settings.landing_cta_button_link ?? '',

    /* ── SEO ── */
    seo_title: props.settings.seo_title ?? '',
    seo_description: props.settings.seo_description ?? '',
})

const savedFlash = ref(false)

function save() {
    form.transform((data) => ({
        ...data,
        default_model: data.default_model || null,
        // Drop blank/removed per-plan limits so the stored map only holds real overrides.
        plan_daily_limits: cleanPlanLimits(data.plan_daily_limits as Record<string, number | string>),
    })).put(route('addon.aip.admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            savedFlash.value = true
            setTimeout(() => { savedFlash.value = false }, 2500)
        },
    })
}

/** Keep only plan slugs the admin actually set to a non-negative number. */
function cleanPlanLimits(map: Record<string, number | string>): Record<string, number> {
    const out: Record<string, number> = {}
    for (const [slug, value] of Object.entries(map ?? {})) {
        if (value === '' || value === null || value === undefined) continue
        const n = Number(value)
        if (!Number.isNaN(n) && n >= 0) out[slug] = Math.floor(n)
    }
    return out
}

/* ── Watermark logo upload ───────────────────────────────── */
const uploadedLogoUrl = ref('')
const watermarkBusy = ref(false)
const watermarkError = ref('')

/** The uploaded URL this session, or a URL derived from the stored public path. */
const watermarkLogoPreview = computed<string>(() => {
    if (uploadedLogoUrl.value) return uploadedLogoUrl.value
    const path = form.watermark_logo_path
    return mediaUrl(path)
})

async function uploadWatermarkLogo(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return

    watermarkBusy.value = true
    watermarkError.value = ''
    try {
        const body = new FormData()
        body.append('image', file)
        const { data } = await axios.post<{ path: string; url: string }>(
            route('addon.aip.admin.watermark.store'), body,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        )
        form.watermark_logo_path = data.path
        uploadedLogoUrl.value = data.url
    } catch {
        watermarkError.value = t('Upload failed. Use a PNG or WebP image up to 4 MB.')
    } finally {
        watermarkBusy.value = false
    }
}

async function removeWatermarkLogo(): Promise<void> {
    const path = form.watermark_logo_path
    form.watermark_logo_path = ''
    uploadedLogoUrl.value = ''
    if (!path) return
    try {
        await axios.delete(route('addon.aip.admin.watermark.destroy'), { data: { path } })
    } catch {
        // The field is already cleared, which is what the admin asked for.
    }
}

const watermarkPositionOptions = computed(() => [
    { value: 'top-left', label: t('Top left') },
    { value: 'top-right', label: t('Top right') },
    { value: 'center', label: t('Center') },
    { value: 'bottom-left', label: t('Bottom left') },
    { value: 'bottom-right', label: t('Bottom right') },
])

/* ── Aspect ratio editor ─────────────────────────────────── */
function addAspect() {
    form.aspect_ratios.push({ key: '', label: '', width: 1024, height: 1024 })
}
function removeAspect(index: number) {
    form.aspect_ratios.splice(index, 1)
}

/* ── Presets CRUD ────────────────────────────────────────── */
const presetModalOpen = ref(false)
const presetMode = ref<'create' | 'edit'>('create')
const presetBusy = ref(false)
const presetError = ref('')
const presetEditing = ref<Preset | null>(null)
const presetForm = reactive({
    name: '',
    prompt_suffix: '',
    negative_prompt: '',
    is_active: true,
})

function openCreatePreset() {
    presetMode.value = 'create'
    presetEditing.value = null
    presetForm.name = ''
    presetForm.prompt_suffix = ''
    presetForm.negative_prompt = ''
    presetForm.is_active = true
    presetError.value = ''
    presetModalOpen.value = true
}

function openEditPreset(preset: Preset) {
    presetMode.value = 'edit'
    presetEditing.value = preset
    presetForm.name = preset.name
    presetForm.prompt_suffix = preset.prompt_suffix ?? ''
    presetForm.negative_prompt = preset.negative_prompt ?? ''
    presetForm.is_active = preset.is_active
    presetError.value = ''
    presetModalOpen.value = true
}

async function submitPreset() {
    if (!presetForm.name.trim()) {
        presetError.value = t('Please enter a preset name.')
        return
    }
    presetBusy.value = true
    presetError.value = ''
    try {
        const payload = { ...presetForm, name: presetForm.name.trim() }
        if (presetMode.value === 'create') {
            await axios.post(route('addon.aip.admin.presets.store'), payload)
        } else if (presetEditing.value) {
            await axios.put(route('addon.aip.admin.presets.update', presetEditing.value.id), payload)
        }
        presetModalOpen.value = false
        router.reload({ only: ['presets'] })
    } catch (e) {
        presetError.value = t('Could not save preset.')
    } finally {
        presetBusy.value = false
    }
}

const presetDeleteTarget = ref<Preset | null>(null)
const presetDeleteBusy = ref(false)

async function confirmDeletePreset() {
    if (!presetDeleteTarget.value) return
    presetDeleteBusy.value = true
    try {
        await axios.delete(route('addon.aip.admin.presets.destroy', presetDeleteTarget.value.id))
        presetDeleteTarget.value = null
        router.reload({ only: ['presets'] })
    } catch (e) {
        presetError.value = t('Could not delete preset.')
    } finally {
        presetDeleteBusy.value = false
    }
}

/* ── Media pricing helpers ───────────────────────────────── */

/** What one image on this model charges right now: the override if set, else derived. */
function effectiveCredits(model: ImageModel): number {
    const override = form.model_credits[model.slug]
    if (override === '' || override === null || override === undefined) return model.derived_credits
    const n = Number(override)
    return Number.isNaN(n) ? model.derived_credits : n
}

function isOverridden(model: ImageModel): boolean {
    const v = form.model_credits[model.slug]
    return v !== '' && v !== null && v !== undefined
}

/** The models the media-billed ops can actually run on (what the admin allowed). */
const pricedModels = computed<ImageModel[]>(() => {
    const allowed = form.generation_models
    if (!allowed || allowed.length === 0) return props.imageModels
    return props.imageModels.filter((m) => allowed.includes(m.slug))
})

/** Cheapest→dearest span, for the "Per image" hint on the media op rows. */
const mediaCostRange = computed<string>(() => {
    const values = pricedModels.value.map(effectiveCredits).filter((n) => n > 0)
    if (values.length === 0) return ''
    const min = Math.min(...values)
    const max = Math.max(...values)
    return min === max ? `${min}` : `${min}–${max}`
})

function opProviderOptions(op: AdminOp) {
    return op.providers.map((p) => ({ value: p, label: providerLabel(p) }))
}
</script>

<template>
    <Head :title="t('AI Image Pro Settings')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <!-- Header -->
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Image Pro Settings') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Control every operation, provider, credit cost, limit and storage rule for the image suite.') }}
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                <transition name="fade">
                    <span v-if="savedFlash" class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                        <i class="ti ti-check"></i> {{ t('Saved') }}
                    </span>
                </transition>
                <button type="button" :disabled="form.processing" class="btn-primary-admin disabled:opacity-60" @click="save">
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-5 flex flex-wrap gap-1 border-b border-gray-200 dark:border-surface-800">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors"
                :class="activeTab === tab.key
                    ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                @click="activeTab = tab.key"
            >
                <i :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
            </button>
        </div>

        <form class="space-y-5" @submit.prevent="save">
            <!-- ══ GENERAL ══ -->
            <section v-show="activeTab === 'general'" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('General') }}</h2>
                <div class="space-y-4">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable AI Image Pro') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('When disabled, all public routes are hidden while settings remain saved.') }}</p>
                        </div>
                        <AppSwitch v-model="form.enabled" :aria-label="t('Enable AI Image Pro')" />
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Studio Access Level') }}</label>
                            <AppSelect v-model="form.studio_access" :options="accessOptions" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Who can open the Studio. Plan levels appear automatically when plans exist.') }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Library Access Level') }}</label>
                            <AppSelect v-model="form.library_access" :options="accessOptions" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Who can open the saved-image Library.') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ══ OPERATIONS ══ -->
            <section v-show="activeTab === 'operations'" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <h2 class="mb-1 text-lg font-bold text-gray-900 dark:text-white">{{ t('Operations') }}</h2>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ t('Enable, gate, re-provider and re-price every operation. Credit cost applies only to flat-billed operations.') }}</p>

                <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-surface-800">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                        <thead class="bg-gray-50 dark:bg-surface-800/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Operation') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Enabled') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Access') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Provider') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Credits') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-surface-800 dark:bg-gray-900">
                            <tr v-for="op in operations" :key="op.key" class="align-top">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ op.label }}</span>
                                        <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:bg-surface-800 dark:text-gray-400">{{ groupLabel(op.group) }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="text-[11px] text-gray-400">{{ tierLabels[op.tier] ?? op.tier }} · {{ op.billing }}</span>
                                        <span v-if="!op.available" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                            <i class="ti ti-alert-triangle"></i> {{ t('Engine not configured') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <AppSwitch v-model="form.operations[op.key].enabled" :aria-label="op.label" />
                                </td>
                                <td class="px-4 py-3 min-w-[10rem]">
                                    <AppSelect v-model="form.operations[op.key].access" :options="accessOptions" />
                                </td>
                                <td class="px-4 py-3 min-w-[10rem]">
                                    <AppSelect
                                        v-if="op.providers.length"
                                        v-model="form.operations[op.key].provider"
                                        :options="opProviderOptions(op)"
                                    />
                                    <template v-else>
                                        <span class="text-sm text-gray-400">{{ providerLabel(op.provider) }}</span>
                                        <span v-if="op.billing === 'media'" class="mt-0.5 block text-[10px] leading-tight text-gray-400">
                                            <i class="ti ti-info-circle"></i>
                                            {{ t('Set in the Generation tab.') }}
                                        </span>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <!-- Media ops are priced per image per model (TokenGuard), not by a flat
                                         number here. Flat & free ops take an admin-set cost; 0 keeps a free op free. -->
                                    <span v-if="op.billing === 'media'" class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                        <p v-if="mediaCostRange" class="mb-0 font-medium text-gray-700 dark:text-gray-200">{{ mediaCostRange }}</p>
                                        {{ t('per image') }}
                                    </span>
                                    <template v-else>
                                        <input
                                            v-model.number="form.operations[op.key].credits"
                                            type="number"
                                            min="0"
                                            class="w-20 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        />
                                        <span v-if="op.billing === 'free'" class="mt-0.5 block text-[10px] text-gray-400">{{ t('0 = free') }}</span>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ══ GENERATION ══ -->
            <section v-show="activeTab === 'generation'" class="space-y-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="mb-1 text-lg font-bold text-gray-900 dark:text-white">{{ t('Studio') }}</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ t('The greeting and headline shown above the prompt box inside the Studio.') }}</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Studio Sub-heading') }}</span>
                            <input
                                v-model="form.studio_subheading"
                                type="text"
                                :placeholder="t('Hi, there!')"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                            <span class="mt-1 block text-xs text-gray-400">{{ t('A short greeting shown above the heading. Leave empty to hide it.') }}</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Studio Heading') }}</span>
                            <input
                                v-model="form.studio_heading"
                                type="text"
                                :placeholder="t('What can I do for you?')"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </label>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Models') }}</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enabled Image Models') }}</label>
                            <AppSelect
                                v-model="form.generation_models"
                                :options="modelOptions"
                                multiple
                                live-search
                                :placeholder="t('All active image models')"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Leave empty to allow every active image model.') }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Model') }}</label>
                            <AppSelect v-model="form.default_model" :options="defaultModelOptions" live-search />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Let users choose the model') }}</span>
                            <AppSwitch v-model="form.allow_user_model_choice" :aria-label="t('Let users choose the model')" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Variants per Generation') }}</span>
                            <input v-model.number="form.max_batch_size" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                    </div>
                </div>

                <!-- ── Per-image pricing for the media-billed ops ───────────────── -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="mb-1 text-lg font-bold text-gray-900 dark:text-white">
                        <i class="ti ti-coins text-violet-500"></i>
                        {{ t('Per-image Credit Price') }}
                    </h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('What Generate, Variations and Edit with Prompt charge for one image. These run on whichever model the user picks, so the price is set per model — leave a field blank to charge the actual cost derived from the provider price.') }}
                    </p>

                    <div v-if="pricedModels.length === 0" class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-400 dark:border-surface-700">
                        {{ t('No image models are available yet.') }}
                    </div>

                    <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="model in pricedModels"
                            :key="model.slug"
                            class="rounded-xl border border-gray-100 bg-gray-50/70 p-3 dark:border-surface-800 dark:bg-surface-800/60"
                        >
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate text-sm font-semibold text-gray-900 dark:text-white">{{ model.name }}</span>
                                <span v-if="!model.is_active" class="shrink-0 rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-surface-700 dark:text-gray-400">
                                    {{ t('Inactive') }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    v-model="form.model_credits[model.slug]"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :placeholder="String(model.derived_credits)"
                                    class="w-28 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                />
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ t('credits / image') }}</span>
                            </div>

                            <!-- The truth underneath: what it costs, and what is actually charged. -->
                            <div class="mt-2 space-y-0.5 border-t border-gray-200/70 pt-2 text-[11px] dark:border-surface-700">
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ t('Actual cost') }}:
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ model.derived_credits }}</span>
                                    {{ t('credits') }}
                                    <span v-if="model.cost_per_unit > 0" class="text-gray-400">(${{ model.cost_per_unit }}/image)</span>
                                </p>
                                <p v-if="isOverridden(model)" class="text-amber-600 dark:text-amber-400">
                                    <i class="ti ti-arrow-right"></i>
                                    {{ t('Charging') }}
                                    <span class="font-semibold">{{ effectiveCredits(model) }}</span>
                                    {{ t('credits — overridden') }}
                                </p>
                                <p v-else class="text-emerald-600 dark:text-emerald-400">
                                    <i class="ti ti-check"></i> {{ t('Charging the actual cost') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 text-[11px] text-gray-400">
                        <i class="ti ti-info-circle"></i>
                        {{ t('Actual cost = provider price × credit markup ÷ credit unit price, from Settings → AI. The Studio quotes and charges the same number shown here.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="mb-1 text-lg font-bold text-gray-900 dark:text-white">{{ t('Prompt Features') }}</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ t('Toggle the input fields and helpers shown in the Studio.') }}</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Prompt Enhancer') }}</span>
                            <AppSwitch v-model="form.enable_prompt_enhancer" :aria-label="t('Enable Prompt Enhancer')" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits: Prompt Enhancer') }}</span>
                            <input v-model.number="form.credits_prompt_enhancer" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Negative Prompt Field') }}</span>
                            <AppSwitch v-model="form.enable_negative_prompt" :aria-label="t('Enable Negative Prompt Field')" />
                        </label>
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Seed Field') }}</span>
                            <AppSwitch v-model="form.enable_seed" :aria-label="t('Enable Seed Field')" />
                        </label>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Image Aspect Ratios') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Selectable output ratios in the Studio.') }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800" @click="addAspect">
                            <i class="ti ti-plus"></i> {{ t('Add') }}
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="(ar, i) in form.aspect_ratios" :key="i" class="grid grid-cols-2 items-end gap-2 rounded-lg border border-gray-100 bg-gray-50/70 p-3 sm:grid-cols-5 dark:border-surface-800 dark:bg-surface-800/60">
                            <label class="block">
                                <span class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Key') }}</span>
                                <input v-model="ar.key" type="text" placeholder="16:9" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Label') }}</span>
                                <input v-model="ar.label" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Width') }}</span>
                                <input v-model.number="ar.width" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Height') }}</span>
                                <input v-model.number="ar.height" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <button type="button" class="flex h-9 items-center justify-center rounded-lg border border-danger-200 text-danger-600 hover:bg-danger-50 dark:border-danger-800 dark:text-danger-400 dark:hover:bg-danger-900/20" @click="removeAspect(i)">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                        <p v-if="form.aspect_ratios.length === 0" class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-sm text-gray-400 dark:border-surface-700">{{ t('No aspect ratios — built-in defaults will be used.') }}</p>
                    </div>
                </div>
            </section>

            <!-- ══ PROVIDERS ══ -->
            <section v-show="activeTab === 'providers'" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <h2 class="mb-1 text-lg font-bold text-gray-900 dark:text-white">{{ t('Provider API Keys') }}</h2>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ t('Stored encrypted. Leave a field blank to keep the current key unchanged.') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label v-for="field in apiKeyFields" :key="field.key" class="block">
                        <span class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ field.label }}
                            <span v-if="apiKeyStatus[field.key]" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">{{ t('Set') }}</span>
                            <span v-else class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-surface-800 dark:text-gray-400">{{ t('Not set') }}</span>
                        </span>
                        <input
                            v-model="(form as any)[field.key]"
                            type="text"
                            autocomplete="off"
                            :placeholder="apiKeyStatus[field.key] || t('Enter a new key')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </label>
                </div>
            </section>

            <!-- ══ LIMITS ══ -->
            <section v-show="activeTab === 'limits'" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Limits') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Upload Size (MB)') }}</span>
                        <input v-model.number="form.max_input_size_mb" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Input Dimension (px)') }}</span>
                        <input v-model.number="form.max_input_dimension" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('The longer side (width OR height).') }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Output Dimension (px)') }}</span>
                        <input v-model.number="form.max_output_dimension" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('Caps the longer side of a generated or edited image.') }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Guest Daily Operation Limit') }}</span>
                        <input v-model.number="form.guest_daily_limit" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('0 disables guest operations.') }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Free Users Daily Operation Limit') }}</span>
                        <input v-model.number="form.user_daily_limit" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('0 = unlimited.') }}</span>
                    </label>
                </div>

                <!-- Per-plan daily limits — only meaningful when subscriptions exist. -->
                <div class="mt-6 border-t border-gray-100 pt-5 dark:border-surface-800">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ t('Per-plan Daily Limits') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Override the logged-in limit for users on a specific plan. Leave blank to use the logged-in limit; 0 = unlimited for that plan.') }}
                    </p>

                    <div v-if="!proAvailable" class="mt-3 flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50/70 px-3 py-3 text-xs text-gray-500 dark:border-surface-700 dark:bg-surface-800/60 dark:text-gray-400">
                        <i class="ti ti-info-circle mt-0.5 shrink-0"></i>
                        <span>{{ t('Subscriptions are not enabled, so there are no plans to limit. Every signed-in user uses the logged-in limit above.') }}</span>
                    </div>
                    <div v-else-if="(plans ?? []).length === 0" class="mt-3 rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-400 dark:border-surface-700">
                        {{ t('No paid plans exist yet. Create plans to set per-plan limits.') }}
                    </div>
                    <div v-else class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label v-for="plan in plans" :key="plan.slug" class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ plan.name }}</span>
                            <input
                                v-model.number="form.plan_daily_limits[plan.slug]"
                                type="number"
                                min="0"
                                :placeholder="t('Use logged-in limit')"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </label>
                    </div>
                </div>
            </section>

            <!-- ══ STORAGE ══ -->
            <section v-show="activeTab === 'storage'" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <h2 class="mb-1 text-lg font-bold text-gray-900 dark:text-white">{{ t('Storage & Retention') }}</h2>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('How long generated images are kept, by who made them. The window is stamped when the image is created, so a change here only affects images made from now on.') }}
                </p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-delete — Guests (Days)') }}</span>
                        <input v-model.number="form.retention_days_guest" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('Visitors with no account, tracked by IP. 0 = keep forever.') }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-delete — Free Users (Days)') }}</span>
                        <input v-model.number="form.retention_days_free" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('Signed in, no paid plan. 0 = keep forever.') }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-delete — Premium Users (Days)') }}</span>
                        <input v-model.number="form.retention_days_paid" type="number" min="0" :disabled="!proAvailable" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:disabled:bg-surface-900" />
                        <span v-if="proAvailable" class="mt-1 block text-xs text-gray-400">{{ t('Signed in on an active paid plan. 0 = keep forever.') }}</span>
                        <span v-else class="mt-1 block text-xs text-gray-400">{{ t('Subscriptions are not enabled — signed-in users all use the free-user window.') }}</span>
                    </label>
                </div>

                <p class="mt-3 flex items-start gap-1.5 text-xs text-gray-400">
                    <i class="ti ti-info-circle mt-0.5 shrink-0"></i>
                    <span>{{ t('Auto-save to Library must be on for these windows to apply — with it off, every result is treated as throwaway and expires after 1 day.') }}</span>
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Library Storage per User (MB)') }}</span>
                        <input v-model.number="form.max_storage_mb_per_user" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('Counted from stored bytes. A user at the cap must delete images before generating more. 0 = unlimited.') }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Thumbnail Width (px)') }}</span>
                        <input v-model.number="form.thumbnail_width" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                    </label>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-save Results to Library') }}</span>
                        </div>
                        <AppSwitch v-model="form.auto_save_to_library" :aria-label="t('Auto-save Results to Library')" />
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Mirror Results to Documents') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Also list generated images in the core Documents library.') }}</p>
                        </div>
                        <AppSwitch v-model="form.mirror_to_documents" :aria-label="t('Mirror Results to Documents')" />
                    </label>
                </div>
            </section>

            <!-- ══ WATERMARK ══ -->
            <section v-show="activeTab === 'watermark'" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Watermark') }}</h2>
                <div class="space-y-4">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Watermark Free-User Downloads') }}</span>
                            <p v-if="proAvailable" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Paid users always download without a watermark.') }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Subscriptions are not enabled, so this applies to every user\'s downloads.') }}</p>
                        </div>
                        <AppSwitch v-model="form.watermark_enabled" :aria-label="t('Watermark Free-User Downloads')" />
                    </label>

                    <!-- Logo (takes precedence over text when set) -->
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60" :class="form.watermark_enabled ? '' : 'opacity-60'">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Watermark Logo') }}</span>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-[repeating-conic-gradient(#e5e7eb_0_25%,#fff_0_50%)] bg-[length:16px_16px] dark:border-surface-700">
                                <img v-if="watermarkLogoPreview" :src="watermarkLogoPreview" :alt="t('Watermark Logo')" class="h-full w-full object-contain" />
                                <i v-else class="ti ti-photo text-xl text-gray-400"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700" :class="watermarkBusy ? 'cursor-wait opacity-60' : ''">
                                        <i class="ti ti-upload"></i>
                                        {{ watermarkLogoPreview ? t('Replace logo') : t('Upload logo') }}
                                        <input type="file" accept=".png,.webp" class="sr-only" :disabled="watermarkBusy || !form.watermark_enabled" @change="uploadWatermarkLogo" />
                                    </label>
                                    <button v-if="watermarkLogoPreview" type="button" class="inline-flex items-center gap-1 rounded-lg border border-danger-200 px-2.5 py-2 text-xs font-medium text-danger-600 transition-colors hover:bg-danger-50 dark:border-danger-800 dark:text-danger-400 dark:hover:bg-danger-900/20" @click="removeWatermarkLogo">
                                        <i class="ti ti-x"></i> {{ t('Remove') }}
                                    </button>
                                </div>
                                <p class="mt-1.5 text-xs text-gray-400">{{ t('PNG (transparent) or WebP, up to 4 MB. When set, the logo is used instead of the text.') }}</p>
                                <p v-if="watermarkError" class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ watermarkError }}</p>
                            </div>
                        </div>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Watermark Text') }}</span>
                        <input v-model="form.watermark_text" type="text" :disabled="!form.watermark_enabled" :placeholder="t('Used when no logo is uploaded')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:disabled:bg-surface-900" />
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Position') }}</label>
                            <AppSelect v-model="form.watermark_position" :options="watermarkPositionOptions" :disabled="!form.watermark_enabled" />
                        </div>
                        <label class="block">
                            <span class="mb-1.5 flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Opacity') }}
                                <span class="text-xs text-gray-400">{{ form.watermark_opacity }}%</span>
                            </span>
                            <input v-model.number="form.watermark_opacity" type="range" min="0" max="100" step="5" :disabled="!form.watermark_enabled" class="w-full accent-primary-600" />
                        </label>
                    </div>
                </div>
            </section>

            <!-- ══ STYLE PRESETS ══ -->
            <section v-show="activeTab === 'presets'" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Style Presets') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Reusable prompt styles users can apply with one click.') }}</p>
                    </div>
                    <button type="button" class="rounded-lg btn-primary-admin" @click="openCreatePreset">
                        <i class="ti ti-plus"></i> {{ t('New Preset') }}
                    </button>
                </div>

                <div v-if="presets.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                    {{ t('No presets yet. Create one to get started.') }}
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="preset in presets" :key="preset.id" class="flex gap-3 rounded-xl border border-gray-100 bg-gray-50/70 p-3 dark:border-surface-800 dark:bg-surface-800/60">
                        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-gray-200 dark:bg-surface-700">
                            <img v-if="preset.thumb_url" :src="preset.thumb_url" :alt="preset.name" class="h-full w-full object-cover" />
                            <span v-else class="flex h-full w-full items-center justify-center text-gray-400"><i class="ti ti-palette text-xl"></i></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ preset.name }}</span>
                                <span v-if="!preset.is_active" class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] text-gray-500 dark:bg-surface-700">{{ t('Off') }}</span>
                            </div>
                            <p class="mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ preset.prompt_suffix }}</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="openEditPreset(preset)">{{ t('Edit') }}</button>
                                <button type="button" class="text-xs font-medium text-danger-600 hover:underline dark:text-danger-400" @click="presetDeleteTarget = preset">{{ t('Delete') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ══ LANDING PAGE ══ -->
            <section v-show="activeTab === 'landing'" class="space-y-5">
                <!-- Intro -->
                <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-surface-800 dark:from-surface-800/60 dark:to-gray-900">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Landing Page') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Every headline, image, list item and button on the public /ai-image page is edited here. Changes go live as soon as you save.') }}
                        </p>
                    </div>
                    <a
                        v-if="landingPreviewUrl"
                        :href="landingPreviewUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700"
                    >
                        <i class="ti ti-external-link"></i> {{ t('Preview page') }}
                    </a>
                </div>

                <!-- Layout & style -->
                <SettingsSection
                    :title="t('Layout & style')"
                    icon="ti ti-layout-distribute-horizontal"
                    :description="t('How wide the landing page runs, and the colour treatment applied to its headings and buttons.')"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Page Width') }}</label>
                            <AppSelect
                                :model-value="form.landing_page_width"
                                :options="pageWidthOptions"
                                @update:model-value="setPageWidth"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Boxed keeps long marketing copy readable on very wide screens.') }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Gradient Colour Scheme') }}</label>
                            <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                                <div class="flex min-w-0 items-center gap-3">
                                    <!-- Swatch of the exact treatment the toggle turns on. -->
                                    <span
                                        class="h-9 w-9 shrink-0 rounded-lg bg-gradient-to-br from-violet-500 to-blue-500 transition-opacity"
                                        :class="form.landing_gradient_enabled ? 'opacity-100' : 'opacity-30'"
                                        aria-hidden="true"
                                    ></span>
                                    <p class="min-w-0 text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('A purple-to-blue gradient to the landing page headings and primary buttons.') }}
                                    </p>
                                </div>
                                <AppSwitch v-model="form.landing_gradient_enabled" :aria-label="t('Gradient Colour Scheme')" />
                            </div>
                        </div>
                    </div>
                </SettingsSection>

                <!-- Hero -->
                <SettingsSection
                    :title="t('Hero')"
                    icon="ti ti-sparkles"
                    :description="t('The top of the page: breadcrumb, badge pill, headline and intro paragraph.')"
                >
                    <div class="space-y-4">
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Breadcrumb') }}</span>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Displays "Home / Tools / AI Image Generator" above the hero.') }}</p>
                            </div>
                            <AppSwitch v-model="form.landing_show_breadcrumb" :aria-label="t('Show Breadcrumb')" />
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Badge Pill') }}</span>
                                <input v-model="form.landing_hero_badge" type="text" :placeholder="t('AI Image Generator')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                <span class="mt-1 block text-xs text-gray-400">{{ t('Leave empty to hide the pill.') }}</span>
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hero Heading') }}</span>
                                <input v-model="form.landing_hero_heading" type="text" :placeholder="t('AI image generator online: Create images & photos from text')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hero Subheading') }}</span>
                            <textarea v-model="form.landing_hero_subheading" rows="3" :placeholder="t('The best AI image generator for creators…')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </label>
                    </div>
                </SettingsSection>

                <!-- Examples -->
                <SettingsSection
                    v-model="form.landing_examples_enabled"
                    toggleable
                    :title="t('Example Prompts')"
                    icon="ti ti-layout-grid"
                    :description="t('The &quot;Get started with&quot; cards. Clicking one opens the Studio with its prompt pre-filled. Shown on the landing page and in the Studio.')"
                >
                    <label class="mb-4 block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Heading') }}</span>
                        <input v-model="form.landing_examples_heading" type="text" :placeholder="t('Get started with')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                    </label>

                    <RepeaterField
                        v-model="form.landing_examples"
                        :fields="exampleFields"
                        :add-label="t('Add Example')"
                        :max="12"
                        :empty-text="t('No examples yet — add one.')"
                    />
                </SettingsSection>

                <!-- Features -->
                <SettingsSection
                    v-model="form.landing_features_enabled"
                    toggleable
                    :title="t('Feature Rows')"
                    icon="ti ti-columns-2"
                    :description="t('Alternating image/text rows. The layout flips automatically on every second row. Each button\'s icon stays hidden until the button is hovered.')"
                >
                    <div class="mb-4 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Heading') }}</span>
                            <input v-model="form.landing_features_heading" type="text" :placeholder="t('AI photo generator built for every creative project')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Subheading') }}</span>
                            <textarea v-model="form.landing_features_subheading" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </label>
                    </div>

                    <RepeaterField
                        v-model="form.landing_features"
                        :fields="featureFields"
                        :add-label="t('Add Feature Row')"
                        :max="10"
                        :empty-text="t('No feature rows yet — add one.')"
                    />
                </SettingsSection>

                <!-- Use cases -->
                <SettingsSection
                    v-model="form.landing_usecases_enabled"
                    toggleable
                    :title="t('Use Cases')"
                    icon="ti ti-target-arrow"
                    :description="t('Bordered cards in a 2-column grid, each with an icon, title and body.')"
                >
                    <div class="mb-4 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Heading') }}</span>
                            <input v-model="form.landing_usecases_heading" type="text" :placeholder="t('Generate image using AI for any project')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Subheading') }}</span>
                            <textarea v-model="form.landing_usecases_subheading" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </label>
                    </div>

                    <RepeaterField
                        v-model="form.landing_usecases"
                        :fields="usecaseFields"
                        :add-label="t('Add Use Case')"
                        :max="8"
                        :empty-text="t('No use cases yet — add one.')"
                    />
                    <p class="mt-2 text-xs text-gray-400">
                        {{ t('Pick an icon for each card from the searchable list — start typing to filter.') }}
                    </p>
                </SettingsSection>

                <!-- Benefits -->
                <SettingsSection
                    v-model="form.landing_benefits_enabled"
                    toggleable
                    :title="t('Benefits')"
                    icon="ti ti-award"
                    :description="t('Three centred columns with a large icon each. Three items look best.')"
                >
                    <div class="mb-4 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Heading') }}</span>
                            <input v-model="form.landing_benefits_heading" type="text" :placeholder="t('The best AI image generator that does more')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Subheading') }}</span>
                            <textarea v-model="form.landing_benefits_subheading" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </label>
                    </div>

                    <RepeaterField
                        v-model="form.landing_benefits"
                        :fields="benefitFields"
                        :add-label="t('Add Benefit')"
                        :max="6"
                        :empty-text="t('No benefits yet — add one.')"
                    />
                </SettingsSection>

                <!-- How it works -->
                <SettingsSection
                    v-model="form.landing_steps_enabled"
                    toggleable
                    :title="t('How It Works')"
                    icon="ti ti-list-numbers"
                    :description="t('Step cards with an image on top. They are numbered automatically (&quot;Step 1&quot;, &quot;Step 2&quot;…).')"
                >
                    <div class="mb-4 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Heading') }}</span>
                            <input v-model="form.landing_steps_heading" type="text" :placeholder="t('How to use the AI image generator')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Subheading') }}</span>
                            <textarea v-model="form.landing_steps_subheading" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </label>
                    </div>

                    <RepeaterField
                        v-model="form.landing_steps"
                        :fields="stepFields"
                        :add-label="t('Add Step')"
                        :max="6"
                        :empty-text="t('No steps yet — add one.')"
                    />
                </SettingsSection>

                <!-- About -->
                <SettingsSection
                    v-model="form.landing_about_enabled"
                    toggleable
                    :title="t('About Text')"
                    icon="ti ti-file-text"
                    :description="t('An optional SEO copy block shown after the steps. Leave the body empty to hide the section.')"
                >
                    <div class="space-y-4">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Heading') }}</span>
                            <input v-model="form.landing_about_heading" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Body') }}</span>
                            <textarea v-model="form.landing_about_body" rows="6" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </label>
                    </div>
                </SettingsSection>

                <!-- FAQ -->
                <SettingsSection
                    v-model="form.landing_faq_enabled"
                    toggleable
                    :title="t('FAQ')"
                    icon="ti ti-help-circle"
                    :description="t('An accordion built from your existing FAQ entries. Hidden automatically when the selected category has no active questions.')"
                >
                    <div class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Section Heading') }}</span>
                                <input v-model="form.landing_faq_heading" type="text" :disabled="!form.landing_faq_enabled" :placeholder="t('Frequently asked questions')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:disabled:bg-surface-900" />
                            </label>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('FAQ Category') }}</label>
                                <AppSelect
                                    :model-value="form.landing_faq_category_id"
                                    :options="faqCategoryOptions"
                                    :disabled="!form.landing_faq_enabled"
                                    live-search
                                    @update:model-value="form.landing_faq_category_id = Number($event)"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <i class="ti ti-info-circle"></i>
                                    {{ t('Questions come from the site\'s existing FAQ manager. Add or edit them under Content → FAQs; only active questions in this category appear, in their configured order.') }}
                                </p>
                                <p v-if="(faqCategories ?? []).length === 0" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                    <i class="ti ti-alert-triangle"></i>
                                    {{ t('No FAQ categories exist yet — create one in the FAQ manager first.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </SettingsSection>

                <!-- CTA banner -->
                <SettingsSection
                    v-model="form.landing_cta_enabled"
                    toggleable
                    :title="t('Call-to-action Banner')"
                    icon="ti ti-rocket"
                    :description="t('The closing gradient banner at the bottom of the page.')"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Heading') }}</span>
                            <input v-model="form.landing_cta_heading" type="text" :placeholder="t('Bring your ideas to life')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Subheading') }}</span>
                            <textarea v-model="form.landing_cta_subheading" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Button Text') }}</span>
                            <input v-model="form.landing_cta_button_text" type="text" :placeholder="t('Generate your image now')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Button Link') }}</span>
                            <input v-model="form.landing_cta_button_link" type="text" placeholder="/ai-image/studio" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            <span class="mt-1 block text-xs text-gray-400">{{ t('A relative path or a full URL.') }}</span>
                        </label>
                    </div>
                </SettingsSection>

                <!-- SEO -->
                <SettingsSection
                    :title="t('SEO')"
                    icon="ti ti-world-search"
                    :description="t('The title and meta description search engines show for the landing page.')"
                >
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-4">
                            <label class="block">
                                <span class="mb-1.5 flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Meta Title') }}
                                    <span class="text-xs" :class="form.seo_title.length > 60 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'">{{ form.seo_title.length }}/60</span>
                                </span>
                                <input v-model="form.seo_title" type="text" :placeholder="t('AI Image Generator — Create images from text')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Meta Description') }}
                                    <span class="text-xs" :class="form.seo_description.length > 160 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'">{{ form.seo_description.length }}/160</span>
                                </span>
                                <textarea v-model="form.seo_description" rows="3" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                            </label>
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">{{ t('Search Preview') }}</span>
                            <p class="truncate text-base font-medium text-blue-700 dark:text-blue-400">
                                {{ form.seo_title || t('AI Image Generator') }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-emerald-700 dark:text-emerald-500">{{ landingUrl || '/ai-image' }}</p>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ form.seo_description || t('Add a meta description to control the snippet shown in search results.') }}
                            </p>
                        </div>
                    </div>
                </SettingsSection>
            </section>
        </form>
    </div>

    <!-- Preset create/edit modal -->
    <AppModal
        :open="presetModalOpen"
        max-width="max-w-lg"
        :title="presetMode === 'create' ? t('New Preset') : t('Edit Preset')"
        :confirm-text="t('Save')"
        confirm-variant="admin"
        :confirm-loading="presetBusy"
        @close="presetModalOpen = false"
        @confirm="submitPreset"
    >
        <div class="space-y-3">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</span>
                <input v-model="presetForm.name" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Prompt Suffix') }}</span>
                <textarea v-model="presetForm.prompt_suffix" rows="2" :placeholder="t('e.g. cinematic lighting, ultra detailed')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Negative Prompt') }}</span>
                <textarea v-model="presetForm.negative_prompt" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
            </label>
            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3 dark:border-surface-800 dark:bg-surface-800/60">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Active') }}</span>
                <AppSwitch v-model="presetForm.is_active" :aria-label="t('Active')" />
            </label>
            <p v-if="presetError" class="text-sm text-danger-600 dark:text-danger-400">{{ presetError }}</p>
        </div>
    </AppModal>

    <!-- Preset delete confirm -->
    <AppModal
        :open="presetDeleteTarget !== null"
        max-width="max-w-sm"
        :title="t('Delete preset?')"
        :confirm-text="t('Delete')"
        confirm-variant="delete"
        :confirm-loading="presetDeleteBusy"
        @close="presetDeleteTarget = null"
        @confirm="confirmDeletePreset"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ t('This style preset will be removed for all users.') }}</p>
        <p v-if="presetError" class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ presetError }}</p>
    </AppModal>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
