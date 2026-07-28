<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import DynamicForm, { type ToolField } from '@themes/default/js/Components/AI/DynamicForm.vue'
import OutputPanel from '@themes/default/js/Components/AI/OutputPanel.vue'
import FavoriteButton from '@themes/default/js/Components/FavoriteButton.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'
import { useStream } from '@/Composables/useStream'
import { useRateLimit } from '@/Composables/useRateLimit'
import { useToolPageShortcuts } from '@/Composables/useKeyboardShortcuts'
import AdSection from '@themes/default/js/Components/AdSection.vue'
import SocialShare from '@themes/default/js/Components/SocialShare.vue'
import { mediaUrl } from '@/lib/media'

defineOptions({ layout: UserLayout })

interface ToolData {
    id: number
    name: string
    slug: string
    description: string
    category?: { name: string; slug: string; icon?: string; color?: string; access_level?: string }
    icon?: string
    color?: string
    output_type?: string
    access_level: string
    supports_brand_voice?: boolean
    fields: ToolField[] | string | Record<string, ToolField>
    about_content?: string
    how_it_works?: Array<{ step?: number; icon?: string; title: string; description: string }> | string | Record<string, unknown>
    usage_examples?: Array<{ title: string; input: Record<string, unknown>; output: string }> | string | Record<string, unknown>
    faq_items?: Array<{ question: string; answer: string }> | string | Record<string, unknown>
    avg_rating?: number
    review_count?: number
    views_count?: number
    usage_count?: number
    max_variants?: number
    show_about: boolean
    show_how_it_works: boolean
    show_usage_examples: boolean
    show_faqs: boolean
    show_reviews: boolean
    show_related_tools: boolean
    favorites_count?: number
    is_favorited?: boolean
}

interface RestoredHistory {
    ulid: string
    field_values: Record<string, unknown>
    output: string
    model: string
    provider: string
}

// The `seo` prop from AiToolController carries two contracts: the nested og/twitter
// objects that app.blade.php renders for crawlers, and the flat keys this component
// reads to keep the head fresh across SPA navigation. Only the flat half is used here.
interface ToolSeo {
    title: string
    title_page: string
    description: string
    keywords?: string | null
    canonical: string
    og_title?: string
    og_description?: string
    og_image?: string | null
    og_type?: string
    twitter_card?: string
}

const props = defineProps<{
    tool: ToolData
    seo: ToolSeo
    schemas: Array<Record<string, unknown>>
    relatedTools: Array<{ name: string; slug: string; description: string; icon?: string; color?: string; avg_rating?: number }>
    reviews: Array<any>
    reviewsPagination: { current_page: number; last_page: number; next_page_url: string | null } | null
    reviewStats: { distribution: Record<number, { count: number; percent: number }> }
    userReview: any
    estimatedCredits: any
    showCreditCosts: boolean
    languages: Array<{ code: string; name: string }>
    models: Array<{ slug: string; name: string; provider: string }>
    authUser: { id: number; name: string; credits: string; is_pro?: boolean } | null
    hasBrandVoice?: boolean
    canReview: boolean
    restoredHistory?: RestoredHistory | null
    effectiveMaxTokens: number
}>()

const formValues = ref<Record<string, unknown>>({})

// Brand voice: shown only on tools that support it, to signed-in users. The
// control lets the user opt out for a single generation; the actual voice text
// lives on their profile and is applied server-side by PromptBuilder.
const useBrandVoice = ref(true)
const showBrandVoice = computed(() => Boolean(props.tool.supports_brand_voice) && Boolean(props.authUser?.id))

const buildFields = (extra: Record<string, unknown> = {}): Record<string, unknown> => ({
    ...formValues.value,
    ...(showBrandVoice.value ? { use_brand_voice: useBrandVoice.value } : {}),
    ...extra,
})
const activeTab = ref('about')
const reviewRating = ref(5)
const hoverRating = ref(0)
const reviewComment = ref('')
const reviewMessage = ref('')
const reviewSubmitting = ref(false)
// Local mirror of the `canReview` prop so a generation completing in this page
// session can open the review form without a reload. Never latches back to false:
// the gate is "has used at least once", which no client-side event can undo.
const reviewUnlocked = ref(props.canReview)
watch(() => props.canReview, (val) => {
    if (val) reviewUnlocked.value = true
})
const reviewSort = ref('helpful')
const sortOptions = computed(() => [
    { value: 'helpful', label: t('Most Helpful') },
    { value: 'recent', label: t('Most Recent') },
    { value: 'highest', label: t('Highest Rated') },
    { value: 'lowest', label: t('Lowest Rated') },
])
const ratingOptions = computed(() => [
    { value: 5, label: '5 ' + t('stars') },
    { value: 4, label: '4 ' + t('stars') },
    { value: 3, label: '3 ' + t('stars') },
    { value: 2, label: '2 ' + t('stars') },
    { value: 1, label: '1 ' + t('stars') },
])
const reviews = ref<Array<any>>([...props.reviews])
const reviewsPage = ref(props.reviewsPagination?.current_page || 1)
const reviewsLastPage = ref(props.reviewsPagination?.last_page || 1)
const reviewsLoading = ref(false)
const expandedExamples = ref<Record<number, boolean>>({})
const reviewStats = ref(props.reviewStats)

const isVariationsMode = ref(false)

const formatReviewDate = (dateStr: string) => {
    if (!dateStr) return ''
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(dateStr))
    } catch {
        return ''
    }
}
const activeVariationTab = ref(0)
const mainStream = useStream()
const variationStreams = [useStream(), useStream(), useStream()]

const activeStream = computed(() => {
    return isVariationsMode.value ? variationStreams[activeVariationTab.value] : mainStream
})

const activeOutput = computed(() => activeStream.value.output.value)
const activeReasoning = computed(() => activeStream.value.reasoning.value)
const activeIsReasoning = computed(() => activeStream.value.isReasoning.value)
const activeIsStreaming = computed(() => activeStream.value.isStreaming.value)
const activeUsage = computed(() => activeStream.value.usage.value)
const activeSavedDocument = computed(() => activeStream.value.savedDocument.value)
const activeError = computed(() => activeStream.value.error.value)

const isAnyStreaming = computed(() => {
    return mainStream.isStreaming.value || variationStreams.some(s => s.isStreaming.value)
})

const { t } = useTranslate()
const toast = useToastr()
const { isLimited, isNearLimit, remaining, formattedCountdown, parseHeaders } = useRateLimit()

const page = usePage()
const toolPageSettings = computed(() => {
    const settings = page.props.frontendToolPageSettings as Record<string, any> || {}
    return {
        layout: settings.layout || 'default',
        hide_breadcrumbs: settings.hide_breadcrumbs === true || settings.hide_breadcrumbs === '1',
        hide_rating: settings.hide_rating === true || settings.hide_rating === '1',
        hide_rating_count: settings.hide_rating_count === true || settings.hide_rating_count === '1',
        hide_share: settings.hide_share === true || settings.hide_share === '1',
        hide_favorite: settings.hide_favorite === true || settings.hide_favorite === '1',
        hide_category: settings.hide_category === true || settings.hide_category === '1',
        hide_labels: settings.hide_labels === true || settings.hide_labels === '1',
        hide_usage_count: settings.hide_usage_count === true || settings.hide_usage_count === '1'
    }
})

const allStreams = [mainStream, ...variationStreams]
allStreams.forEach((s) => {
    watch(s.error, (val) => {
        if (val) toast.error(val)
    })
    // The `canReview` prop is evaluated once at page render, so a user's first
    // generation left the review form locked until a full reload. Two signals open
    // it in place instead, and both land strictly after TokenGuard has written the
    // completed AiUsageLog row that the review gate queries — neither can unlock
    // a form the server would then reject.
    //
    // `usage` is the fast path: GenerateController echoes it immediately after the
    // TokenGuard::after() call that writes the row.
    watch(s.usage, (val) => {
        if (val) reviewUnlocked.value = true
    })

    // A stream that ended with output and no error IS the unlock. No SSE frame to
    // wait on, no request to make, nothing the network can withhold.
    //
    // Earlier attempts asked the server to confirm first — via the usage frame, then
    // an Inertia partial reload, then a JSON endpoint. All three worked locally and
    // none survived production, because each depends on something a real host can
    // interfere with: output buffering in front of PHP, page caches on an HTML-ish
    // GET, and a route table that a freshly added route is missing from until
    // route:cache is rebuilt.
    //
    // Optimism is safe here because the client is not the authority. The server gate
    // in ToolReviewController::store() is, and it is unchanged. The worst case is a
    // form that opens for someone whose generation was not billed, and whose submit
    // then returns a clear "you can review this tool after using it at least once" —
    // strictly better than a form that never opens for anyone.
    watch(s.isStreaming, (streaming, wasStreaming) => {
        if (streaming || !wasStreaming) return
        if (s.error.value || !s.output.value.trim()) return

        reviewUnlocked.value = true
    })
})

const isSidebarOpen = ref(false)
const refineInstruction = ref('Improve this output')

const refinementPresets = [
    { label: 'Improve Writing', instruction: 'Improve the overall writing quality, clarity, and flow while keeping the core message and structure.', icon: 'ti ti-sparkles' },
    { label: 'Make Professional', instruction: 'Rewrite this content in a professional, polite, and executive-level tone.', icon: 'ti ti-briefcase' },
    { label: 'Make Casual/Engaging', instruction: 'Rewrite this in a friendly, conversational, and highly engaging tone.', icon: 'ti ti-mood-smile' },
    { label: 'Make Shorter', instruction: 'Condense this text to make it more concise and to the point, removing unnecessary fluff.', icon: 'ti ti-arrows-minimize' },
    { label: 'Make Longer', instruction: 'Expand on this content with more details, elaborating on key points and adding depth.', icon: 'ti ti-arrows-maximize' },
    { label: 'Fix Grammar & Spelling', instruction: 'Review the text, correcting all spelling errors, grammatical mistakes, and punctuation errors.', icon: 'ti ti-spellcheck' }
]

const selectPreset = (preset: { instruction: string }) => {
    refineInstruction.value = preset.instruction
}

const closeSidebar = () => {
    isSidebarOpen.value = false
}

const applyRefinement = async () => {
    if (!refineInstruction.value.trim() || activeStream.value.isStreaming.value) return

    const modelField = fields.value.find(f => f.type === 'model_select')
    const model = modelField ? String(formValues.value[fieldName(modelField)] || '') : ''

    const contentToRefine = activeStream.value.output.value

    // Close sidebar once streaming starts so user can see it in output panel
    closeSidebar()

    await activeStream.value.generate({
        slug: props.tool.slug,
        fields: buildFields(),
        model,
        action: 'refine',
        refine_content: contentToRefine,
        refine_instruction: refineInstruction.value
    })
}

const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const routeTo = (name: string, params?: unknown): string => route(name, params)

const formatViews = (views: number): string => {
    if (views >= 1000000) return `${(views / 1000000).toFixed(1)}M`
    if (views >= 1000) return `${(views / 1000).toFixed(1)}K`
    return views.toString()
}

// Meta row visibility (category • uses • rating). Kept as named flags because the raw
// conditions were repeated inline in two layouts, and the duplication hid a bug: the first
// divider used "category && (usage || rating)", so with a 0 usage count a tool with both a
// category and a rating rendered BOTH dividers as "• •". A divider must only sit between
// two adjacent items that are actually visible.
const showCategoryMeta = computed(() => Boolean(props.tool.category) && !toolPageSettings.value.hide_category)
const showUsageMeta = computed(() => Boolean(props.tool.usage_count || props.tool.views_count) && !toolPageSettings.value.hide_usage_count)
const showRatingMeta = computed(() => !toolPageSettings.value.hide_rating && Boolean(props.tool.avg_rating))
const showCategoryUsageDivider = computed(() => showCategoryMeta.value && showUsageMeta.value)
const showRatingDivider = computed(() => (showCategoryMeta.value || showUsageMeta.value) && showRatingMeta.value)

// Fraction of the 5-star bar to fill amber, so the stars reflect avg_rating precisely
// (a 4.3 shows 86% filled) rather than a single always-full star.
const ratingPercent = computed(() => {
    const value = Math.max(0, Math.min(5, Number(props.tool.avg_rating) || 0))
    return (value / 5) * 100
})

const shareUrl = computed(() => typeof window !== 'undefined' ? window.location.href : '')

const fields = computed<ToolField[]>(() => {
    if (!props.tool.fields) return []
    if (typeof props.tool.fields === 'string') {
        try { return JSON.parse(props.tool.fields) } catch { return [] }
    }
    return Array.isArray(props.tool.fields) ? props.tool.fields : Object.values(props.tool.fields)
})

const dynamicFields = computed<ToolField[]>(() => {
    return fields.value.map(field => {
        if (field.type === 'length_select') {
            const max = props.effectiveMaxTokens || 2000
            const toWords = (pct: number) => {
                const words = Math.round((max * pct) / 1.3)
                return Math.max(10, Math.round(words / 10) * 10)
            }
            return {
                ...field,
                options: [
                    { label: `Short (~${toWords(0.07)} words)`, value: 'short' },
                    { label: `Medium (~${toWords(0.20)} words)`, value: 'medium' },
                    { label: `Long (~${toWords(0.40)} words)`, value: 'long' },
                    { label: `Very Long (~${toWords(0.80)} words)`, value: 'very_long' },
                ]
            }
        }
        return field
    })
})

const normalizeArray = <T,>(value: unknown): T[] => {
    if (!value) return []
    if (Array.isArray(value)) return value as T[]
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value)
            if (Array.isArray(parsed)) return parsed as T[]
            if (parsed && typeof parsed === 'object') return Object.values(parsed) as T[]
        } catch { return [] }
    }
    if (typeof value === 'object') return Object.values(value as Record<string, T>)
    return []
}

const howItWorks = computed(() => normalizeArray<{ step?: number; icon?: string; title?: string; description?: string }>(props.tool.how_it_works))
const usageExamples = computed(() => normalizeArray<{ title?: string; input?: Record<string, unknown>; output?: unknown }>(props.tool.usage_examples))
const faqItems = computed(() => normalizeArray<{ question?: string; answer?: string }>(props.tool.faq_items))

const fieldName = (field: ToolField): string => field.name || field.key || field.id || ''

const canSubmit = computed(() => {
    if (isAnyStreaming.value || isLimited.value) return false
    return fields.value.filter(f => f.required).every(f => {
        const v = formValues.value[fieldName(f)]
        return v !== null && v !== undefined && String(v).trim() !== ''
    })
})

// Single source of truth for the tool's resolved access level. Mirrors the backend
// AiTool::getEffectiveAccessLevel() ('inherit' falls through to the category level).
// Valid values: guest | login | premium | plan:* — there is no 'public'.
const effectiveAccessLevel = computed(() => {
    const level = props.tool.access_level || 'inherit'
    return level === 'inherit' ? (props.tool.category?.access_level || 'guest') : level
})

const needsLogin = computed(() => {
    const level = effectiveAccessLevel.value
    return (level === 'login' || level === 'premium' || level.startsWith('plan:')) && !props.authUser?.id
})

const requiresPremiumLevel = computed(() => {
    const level = effectiveAccessLevel.value
    return level === 'premium' || level.startsWith('plan:')
})

// A guest-accessible tool viewed by a signed-out visitor: no login and no credits are
// required (the server returns a length-capped free preview), so we show a "free preview"
// note instead of a credit estimate that would never actually be charged.
const isGuestFreePreview = computed(() => effectiveAccessLevel.value === 'guest' && !props.authUser?.id)

const needsPro = computed(() => {
    if (!requiresPremiumLevel.value) return false
    if (!isProAvailable.value) return false
    if (!props.authUser?.id) return true
    return !props.authUser.is_pro
})

// A premium/plan tool can never run when billing is unavailable (Regular license or
// subscriptions off). Show it as unavailable instead of a falsely-usable form — the
// server denies it anyway (checkAccess → pro_unavailable / 404).
const proUnavailable = computed(() => requiresPremiumLevel.value && !isProAvailable.value)

const canGenerate = computed(() => {
    if (needsLogin.value) return false
    if (needsPro.value) return false
    if (proUnavailable.value) return false
    return true
})

const bannerType = computed(() => {
    if (needsPro.value) return 'pro'
    if (proUnavailable.value) return 'unavailable'
    if (needsLogin.value) return 'login'
    return null
})

const bannerClass = computed(() => {
    switch (bannerType.value) {
        case 'pro': return 'border-accent-500/20 bg-accent-500/10 text-accent-700 dark:border-accent-500/20 dark:bg-accent-500/10 dark:text-accent-300'
        case 'unavailable': return 'border-gray-300/60 bg-gray-100 text-gray-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'
        case 'login': return 'border-amber-500/20 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300'
        default: return ''
    }
})

const bannerIcon = computed(() => {
    switch (bannerType.value) {
        case 'pro': return 'ti ti-crown'
        case 'unavailable': return 'ti ti-lock'
        case 'login': return 'ti ti-login'
        default: return ''
    }
})

const bannerTitle = computed(() => {
    switch (bannerType.value) {
        case 'pro': return t('Pro subscription required')
        case 'unavailable': return t('This tool is not available on this site')
        case 'login': return t('Login required to generate')
        default: return ''
    }
})

const bannerAction = computed(() => {
    switch (bannerType.value) {
        case 'pro': return t('Upgrade to Pro')
        case 'login': return t('Sign in now')
        default: return ''
    }
})

const bannerLink = computed(() => {
    switch (bannerType.value) {
        case 'pro': return routeTo('pricing')
        case 'login': return routeTo('login')
        default: return '#'
    }
})

const contentTabsVisible = computed(() => (
    hasAbout.value || hasHowItWorks.value || hasUsageExamples.value ||
    hasFaqs.value || Boolean(props.tool.show_reviews) ||
    (Boolean(props.tool.show_related_tools) && props.relatedTools.length > 0)
))

const hasAbout = computed(() => props.tool.show_about && String(props.tool.about_content || '').trim() !== '')
const hasHowItWorks = computed(() => props.tool.show_how_it_works && howItWorks.value.some(s => String(s.title || '').trim() !== '' || String(s.description || '').trim() !== ''))
const hasUsageExamples = computed(() => props.tool.show_usage_examples && usageExamples.value.some(e => String(e.title || '').trim() !== '' || Object.keys(e.input || {}).length > 0 || exampleOutput(e.output).trim() !== ''))
const hasFaqs = computed(() => props.tool.show_faqs && faqItems.value.some(f => String(f.question || '').trim() !== '' || String(f.answer || '').trim() !== ''))
const accessBadgeLabel = computed(() => {
    const level = props.tool.access_level || 'inherit'
    const effectiveLevel = level === 'inherit' ? (props.tool.category?.access_level || 'guest') : level
    if (effectiveLevel === 'premium' || effectiveLevel.startsWith('plan:')) return t('Pro')
    if (effectiveLevel === 'login') return t('Login')
    return t('Free')
})

const accessBadgeClass = computed(() => {
    const level = props.tool.access_level || 'inherit'
    const effectiveLevel = level === 'inherit' ? (props.tool.category?.access_level || 'guest') : level
    if (effectiveLevel === 'premium' || effectiveLevel.startsWith('plan:')) {
        return 'border-accent-500/20 bg-gradient-to-r from-accent-500 to-primary-500 text-white shadow-sm'
    }
    if (effectiveLevel === 'login') {
        return 'border-sky-500/20 bg-sky-500/15 text-sky-800 dark:text-sky-200'
    }
    return 'border-emerald-500/20 bg-emerald-500/15 text-emerald-800 dark:text-emerald-200'
})

const lengthMultipliers: Record<string, number> = { short: 0.5, medium: 1, long: 2, very_long: 4 }

const selectedModel = computed(() => {
    const mf = fields.value.find(f => f.type === 'model_select')
    return mf ? String(formValues.value[fieldName(mf)] || '') : ''
})

const selectedLength = computed(() => {
    const lf = fields.value.find(f => f.type === 'length_select')
    return lf ? String(formValues.value[fieldName(lf)] || 'medium') : 'medium'
})

const dynamicCredits = ref<any>(props.estimatedCredits)
const estimateIsByok = computed(() => Boolean(dynamicCredits.value?.byok))

const fetchEstimate = async () => {
    if (!props.showCreditCosts) return
    try {
        const params = new URLSearchParams({ slug: props.tool.slug })
        if (selectedModel.value) params.set('model', selectedModel.value)
        params.set('output_length', selectedLength.value)
        const resp = await fetch(`/api/v1/generate/estimate?${params}`, { headers: { Accept: 'application/json' } })
        const data = await resp.json()
        if (data.success) dynamicCredits.value = data.data
    } catch {
        if (props.estimatedCredits) {
            // A BYOK estimate is always $0 — never scale it back up to a credit cost.
            if (props.estimatedCredits.byok) {
                dynamicCredits.value = { ...props.estimatedCredits }
                return
            }
            const m = lengthMultipliers[selectedLength.value] ?? 1
            dynamicCredits.value = {
                estimated_credits: Math.max(1, Math.round((props.estimatedCredits.estimated_credits || 0) * m)),
                estimated_tokens: props.estimatedCredits.estimated_tokens ? Math.max(1, Math.round(props.estimatedCredits.estimated_tokens * m)) : 0,
            }
        }
    }
}

let estimateDebounce: ReturnType<typeof setTimeout> | null = null
watch([selectedModel, selectedLength], () => {
    if (estimateDebounce) clearTimeout(estimateDebounce)
    estimateDebounce = setTimeout(fetchEstimate, 300)
})

onMounted(() => {
    if (props.restoredHistory) {
        formValues.value = { ...props.restoredHistory.field_values }
        if (props.restoredHistory.output) {
            mainStream.output.value = props.restoredHistory.output
        }
        const modelField = fields.value.find(f => f.type === 'model_select')
        if (modelField) {
            const mName = fieldName(modelField)
            if (!formValues.value[mName] && props.restoredHistory.model) {
                formValues.value[mName] = props.restoredHistory.model
            }
        }
    } else {
        for (const field of fields.value) {
            formValues.value[fieldName(field)] = typeof field.default === 'boolean' ? field.default : (field.default ?? '')
        }
    }
    mainStream.onHeaders(parseHeaders)
    variationStreams.forEach(s => s.onHeaders(parseHeaders))

    // Reconcile the server-rendered estimate (default model, medium length, no BYOK
    // awareness) with the actual initial selection so the displayed cost is accurate
    // from first paint — including a $0 "own API key" case for BYOK users.
    fetchEstimate()

    const urlTab = new URLSearchParams(window.location.search).get('tab')
    const validTabs = ['about', 'how', 'examples', 'faqs', 'reviews', 'related']
    if (urlTab && validTabs.includes(urlTab)) {
        const visible: Record<string, boolean> = {
            about: hasAbout.value, how: hasHowItWorks.value,
            examples: hasUsageExamples.value, faqs: hasFaqs.value,
            reviews: props.tool.show_reviews,
            related: props.tool.show_related_tools && !!props.relatedTools.length,
        }
        if (visible[urlTab]) { activeTab.value = urlTab; return }
    }

    if (hasAbout.value) activeTab.value = 'about'
    else if (hasHowItWorks.value) activeTab.value = 'how'
    else if (hasUsageExamples.value) activeTab.value = 'examples'
    else if (hasFaqs.value) activeTab.value = 'faqs'
    else if (props.tool.show_reviews) activeTab.value = 'reviews'
    else if (props.relatedTools.length) activeTab.value = 'related'
})

watch(activeTab, (newTab) => {
    const url = new URL(window.location.href)
    url.searchParams.set('tab', newTab)
    window.history.replaceState({}, '', url.toString())
})

const runGenerate = () => {
    // canGenerate already encodes full entitlement (login / pro / plan / availability)
    // and the Generate button is disabled unless it is true, so no further gating is
    // needed here — the non-entitled never reach this path (they see the banner CTA).
    if (!canSubmit.value || !canGenerate.value) return

    const modelField = fields.value.find(f => f.type === 'model_select')
    const model = modelField ? String(formValues.value[fieldName(modelField)] || '') : ''
    isVariationsMode.value = false
    mainStream.generate({ slug: props.tool.slug, fields: buildFields(), model })
}

const generateVariations = async () => {
    // Same gating as runGenerate — canGenerate is the single source of truth.
    if (isAnyStreaming.value || !canGenerate.value) return

    isVariationsMode.value = true
    activeVariationTab.value = 0

    const modelField = fields.value.find(f => f.type === 'model_select')
    const model = modelField ? String(formValues.value[fieldName(modelField)] || '') : ''

    await Promise.all(variationStreams.map((stream, index) => {
        return stream.generate({
            slug: props.tool.slug,
            fields: buildFields({ variation_index: index }),
            model
        })
    }))
}

const regenerate = () => {
    if (isVariationsMode.value) {
        generateVariations()
    } else {
        runGenerate()
    }
}

const handleDocumentSaved = (document: Record<string, unknown>) => {
    activeStream.value.savedDocument.value = document
}

useToolPageShortcuts({
    onGenerate: () => { runGenerate() },
    onRegenerate: () => { regenerate() },
    onCopy: () => {
        const output = activeOutput.value
        if (output) {
            navigator.clipboard.writeText(output).catch(() => {})
        }
    },
    onOpenInEditor: () => {
        if (page.props.auth?.user && activeSavedDocument.value?.id) {
            router.visit(`/documents/${activeSavedDocument.value.id}/edit`)
        }
    },
})
const applyingExampleIndex = ref<number | null>(null)

const applyExample = (example: any, index: number) => {
    if (applyingExampleIndex.value !== null) return
    applyingExampleIndex.value = index

    setTimeout(() => {
        const rawInput = example.input || {}
        const parsedInput: Record<string, any> = {}

        const fields = (props.tool.fields && typeof props.tool.fields === 'object')
            ? (Array.isArray(props.tool.fields) ? props.tool.fields : Object.values(props.tool.fields))
            : []

        fields.forEach((field: any) => {
            const name = field.name || field.key || field.id || ''
            if (!name) return

            let val = rawInput[name]
            if (val === undefined) {
                // Try case-insensitive matching
                const matchedKey = Object.keys(rawInput).find(k => k.toLowerCase() === name.toLowerCase())
                if (matchedKey !== undefined) {
                    val = rawInput[matchedKey]
                }
            }

            if (val === undefined) return

            // If the field is multi_select or tags_input, and val is a string, split by comma
            if (['multi_select', 'tags_input'].includes(field.type) && typeof val === 'string') {
                val = val.split(',').map((s: string) => s.trim()).filter(Boolean)
            }

            parsedInput[name] = val
        })

        // Copy other keys
        Object.keys(rawInput).forEach((k) => {
            if (parsedInput[k] === undefined) {
                parsedInput[k] = rawInput[k]
            }
        })

        formValues.value = { ...formValues.value, ...parsedInput }
        applyingExampleIndex.value = null

        toast.success(t('Example data loaded successfully!'))

        const formElement = document.querySelector('form')
        if (formElement) {
            formElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
    }, 500)
}

const getFieldLabel = (key: string): string => {
    const fields = (props.tool.fields && typeof props.tool.fields === 'object')
        ? (Array.isArray(props.tool.fields) ? props.tool.fields : Object.values(props.tool.fields))
        : []

    const field = fields.find((f: any) => {
        const name = f.name || f.key || f.id || ''
        return name.toLowerCase() === key.toLowerCase()
    })

    if (field && field.label) {
        return field.label
    }
    // Fallback: capitalize first letter of each word and replace underscores with spaces
    return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const exampleOutput = (output: unknown): string => String(output ?? '')

const truncatedOutput = (output: unknown, index: number): string => {
    const text = exampleOutput(output)
    return (expandedExamples.value[index] || text.length <= 200) ? text : `${text.slice(0, 200)}...`
}

const fetchReviews = async (page = 1, append = false) => {
    reviewsLoading.value = true
    try {
        const resp = await fetch(`/api/v1/tools/${props.tool.slug}/reviews?sort=${reviewSort.value}&page=${page}`, { headers: { Accept: 'application/json' } })
        const data = await resp.json()
        if (!resp.ok) throw new Error(data.message || t('Reviews could not be loaded.'))
        reviews.value = append ? [...reviews.value, ...data.data.data] : data.data.data
        reviewsPage.value = data.data.current_page
        reviewsLastPage.value = data.data.last_page
        reviewStats.value = data.meta || reviewStats.value
    } catch (err) {
        toast.error(err instanceof Error ? err.message : t('Reviews could not be loaded.'))
    } finally { reviewsLoading.value = false }
}

const changeReviewSort = () => fetchReviews(1, false)
const loadMoreReviews = () => { if (reviewsPage.value < reviewsLastPage.value) fetchReviews(reviewsPage.value + 1, true) }

const voteReview = async (review: any, isHelpful: boolean) => {
    if (!props.authUser) { toast.warning(t('Please sign in to vote on reviews.')); return }
    try {
        const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
        const resp = await fetch(`/api/v1/tools/reviews/${review.id}/vote`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.pop() || '') : '' },
            credentials: 'same-origin', body: JSON.stringify({ is_helpful: isHelpful }),
        })
        const data = await resp.json()
        if (!resp.ok) throw new Error(data.message || t('Vote could not be recorded.'))
        review.helpful_count = data.data?.helpful_count ?? review.helpful_count
        toast.success(data.message || t('Vote recorded.'))
    } catch (err) { toast.error(err instanceof Error ? err.message : t('Vote could not be recorded.')) }
}

const submitReview = async () => {
    reviewSubmitting.value = true; reviewMessage.value = ''
    try {
        const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
        const resp = await fetch(`/api/v1/tools/${props.tool.slug}/reviews`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.pop() || '') : '' },
            credentials: 'same-origin', body: JSON.stringify({ rating: reviewRating.value, comment: reviewComment.value }),
        })
        const data = await resp.json()
        if (!resp.ok) throw new Error(data.message || t('Review could not be submitted.'))
        reviewMessage.value = data.message || t('Review submitted.'); reviewComment.value = ''
        toast.success(reviewMessage.value)
    } catch (err) {
        reviewMessage.value = err instanceof Error ? err.message : t('Review could not be submitted.')
        toast.error(reviewMessage.value)
    } finally { reviewSubmitting.value = false }
}

const isShareOpen = ref(false)
const shareCopied = ref(false)

const closeShareDropdown = () => {
    isShareOpen.value = false
}

onMounted(() => {
    document.addEventListener('click', closeShareDropdown)
})

onUnmounted(() => {
    document.removeEventListener('click', closeShareDropdown)
})

const copyToolLink = () => {
    navigator.clipboard.writeText(props.seo.canonical || window.location.href).then(() => {
        shareCopied.value = true
        setTimeout(() => {
            shareCopied.value = false
        }, 2000)
    }).catch(() => {})
}
</script>

<template>
    <!-- Site-free title portion only: Inertia's head manager runs the global title
         callback (app.ts) over a <title> child just as it does over the :title prop,
         appending " | <site>". Passing seo.title (which already ends in the site name)
         would render "… | MakeAI | MakeAI". The result matches app.blade.php's
         server-rendered <title inertia>, which uses the full seo.title. -->
    <Head>
        <title>{{ seo.title_page || tool.name }}</title>
        <meta name="description" :content="seo.description || tool.description" />
        <meta v-if="seo.keywords" name="keywords" :content="seo.keywords" />
        <link rel="canonical" :href="seo.canonical" />
        <meta property="og:title" :content="seo.og_title || seo.title_page" />
        <meta property="og:description" :content="seo.og_description || seo.description" />
        <meta v-if="seo.og_image" property="og:image" :content="seo.og_image" />
        <meta property="og:url" :content="seo.canonical" />
        <meta property="og:type" :content="seo.og_type || 'website'" />
        <meta name="twitter:card" :content="seo.twitter_card || 'summary_large_image'" />
        <component v-for="(schema, i) in schemas" :key="i" :is="'script'" type="application/ld+json" v-html="JSON.stringify(schema)" />
    </Head>

    <div class="relative flex-1 min-h-0 lg:overflow-hidden">
        <div class="relative mx-auto flex min-h-0 flex-col px-4 py-6 sm:px-6" :class="[
            toolPageSettings.layout === 'minimalist' ? 'max-w-4xl' : 'max-w-7xl',
            'layout-' + toolPageSettings.layout
        ]">
            <!-- Header Card (Default Layout) -->
            <div v-if="toolPageSettings.layout === 'default'" class="mb-6 rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-card dark:border-white/5 dark:bg-[#111827] sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-3" :class="{ 'lg:justify-end': toolPageSettings.hide_breadcrumbs }">
                    <div v-if="!toolPageSettings.hide_breadcrumbs" class="flex flex-wrap items-center gap-2 text-sm">
                        <Link :href="routeTo('home')" class="inline-flex items-center gap-1.5 text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-primary-400">
                            <i class="ti ti-home"></i>
                        </Link>
                        <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                        <Link :href="routeTo('ai.tools.index')" class="text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-primary-400">{{ t('AI Tools') }}</Link>
                        <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                        <Link v-if="tool.category" :href="routeTo('ai.tools.category', tool.category.slug)" class="text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-primary-400">{{ tool.category.name }}</Link>
                        <i v-if="tool.category" class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                        <span class="text-gray-700 dark:text-gray-300">{{ tool.name }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Share Dropdown Button -->
                        <div v-if="!toolPageSettings.hide_share" class="relative">
                            <button
                                type="button"
                                @click.stop="isShareOpen = !isShareOpen"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                            >
                                <i class="ti ti-share text-[13px]"></i>
                                {{ t('Share') }}
                            </button>
                            <div v-if="isShareOpen" class="absolute right-0 mt-2 w-max min-w-[180px] rounded-2xl border border-gray-200 bg-white p-3 shadow-lg z-50 dark:border-white/10 dark:bg-gray-800">
                                <SocialShare :url="seo.canonical || shareUrl" :title="tool.name" :style="'icon-label'" :layout="'vertical'" :networks="['facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'copy']" />
                            </div>
                        </div>

                        <Tooltip v-if="!toolPageSettings.hide_favorite" :content="tool.is_favorited ? t('Remove from favorites') : t('Add to favorites')" placement="bottom">
                            <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" show-count size="sm" />
                        </Tooltip>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div class="card relative overflow-hidden bg-white dark:bg-white/[0.03]">
                        <div class="relative flex flex-col gap-3">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-14 w-14 sm:mt-2 shrink-0 items-center justify-center rounded-2xl border shadow-sm" :style="{ backgroundColor: (tool.color || '#1F75FE') + '15', borderColor: (tool.color || '#1F75FE') + '25' }">
                                        <i :class="[tool.icon || 'ti ti-wand', 'text-[28px]']" :style="{ color: tool.color || '#1F75FE' }"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h1 class="font-heading text-[2rem] font-black tracking-tight text-gray-900 dark:text-white">{{ tool.name }}</h1>
                                            <span v-if="!toolPageSettings.hide_labels" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-[0.18em] whitespace-nowrap shadow-sm" :class="accessBadgeClass">
                                                {{ accessBadgeLabel }}
                                            </span>
                                        </div>

                                        <!-- Simple Metadata Row just below title -->
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400 mt-1 mb-2">
                                            <!-- Category -->
                                            <span v-if="showCategoryMeta" class="flex items-center gap-1">
                                                <i class="ti ti-folder text-base text-gray-400"></i>
                                                <Link :href="routeTo('ai.tools.category', tool.category.slug)" class="font-medium text-gray-600 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 transition-colors no-underline">
                                                    {{ tool.category.name }}
                                                </Link>
                                            </span>

                                            <!-- Divider -->
                                            <span v-if="showCategoryUsageDivider" class="text-[10px] leading-none text-gray-300 dark:text-surface-700 -mx-1.5">•</span>

                                            <!-- Usage Count -->
                                            <span v-if="showUsageMeta" class="flex items-center gap-1">
                                                <i class="ti ti-player-play text-base text-gray-400"></i>
                                                <span>{{ formatViews(tool.usage_count || tool.views_count || 0) }} {{ t('uses') }}</span>
                                            </span>

                                            <!-- Divider -->
                                            <span v-if="showRatingDivider" class="text-[10px] leading-none text-gray-300 dark:text-surface-700 -mx-1.5">•</span>

                                            <!-- Rating -->
                                            <span v-if="showRatingMeta" class="flex items-center gap-1">
                                                <span class="relative inline-block whitespace-nowrap text-base leading-none" aria-hidden="true"><span class="text-gray-300 dark:text-surface-600"><i class="ti ti-star-filled"></i></span><span class="absolute inset-y-0 left-0 overflow-hidden whitespace-nowrap text-amber-400" :style="{ width: ratingPercent + '%' }"><i class="ti ti-star-filled"></i></span></span>
                                                <span class="font-bold text-gray-700 dark:text-gray-200">{{ Number(tool.avg_rating).toFixed(1) }}</span>
                                                <span class="text-xs text-gray-400" v-if="tool.review_count">({{ tool.review_count }})</span>
                                            </span>
                                        </div>

                                        <p class="max-w-5xl text-[15px] text-gray-500 dark:text-gray-400">
                                            {{ tool.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card-less Header (Modern & Minimalist Layouts) -->
            <div v-else-if="toolPageSettings.layout === 'modern' || toolPageSettings.layout === 'minimalist'" class="mb-8" :class="[
                toolPageSettings.layout === 'minimalist' ? 'max-w-4xl mx-auto w-full' : ''
            ]">
                <!-- Top Row (Breadcrumbs & Meta) -->
                <div v-if="toolPageSettings.layout !== 'modern'" class="flex flex-wrap items-center justify-between gap-4 mb-3">
                    <!-- Breadcrumbs -->
                    <div v-if="!toolPageSettings.hide_breadcrumbs" class="flex flex-wrap items-center gap-2 text-sm">
                        <Link :href="routeTo('home')" class="inline-flex items-center gap-1.5 text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-white">
                            <i class="ti ti-home"></i>
                        </Link>
                        <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                        <Link :href="routeTo('ai.tools.index')" class="text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-white">{{ t('AI Tools') }}</Link>
                        <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                        <Link v-if="tool.category" :href="routeTo('ai.tools.category', tool.category.slug)" class="text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-primary-400">{{ tool.category.name }}</Link>
                        <i v-if="tool.category" class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                        <span class="text-gray-700 dark:text-gray-300">{{ tool.name }}</span>
                    </div>

                    <!-- Meta details (Ratings & Favorite button) -->
                    <div class="flex items-center gap-2" :class="{ 'ml-auto': toolPageSettings.hide_breadcrumbs }">
                        <Tooltip v-if="(tool.usage_count || tool.views_count) && toolPageSettings.layout !== 'minimalist' && toolPageSettings.layout !== 'modern'" :content="t('Total views')" placement="bottom">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                <i class="ti ti-eye text-[13px] text-gray-600 dark:text-gray-200"></i>
                                {{ formatViews(tool.usage_count || tool.views_count || 0) }}
                            </span>
                        </Tooltip>
                        <Tooltip v-if="!toolPageSettings.hide_rating && tool.avg_rating && toolPageSettings.layout !== 'minimalist' && toolPageSettings.layout !== 'modern'" :content="t('Average rating')" placement="bottom">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                <i class="ti ti-star-filled text-[13px] text-warning-400"></i>
                                {{ (tool.avg_rating || 0).toFixed(1) }}
                            </span>
                        </Tooltip>

                        <!-- Share Dropdown Button -->
                        <div v-if="!toolPageSettings.hide_share" class="relative">
                            <button
                                type="button"
                                @click.stop="isShareOpen = !isShareOpen"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                            >
                                <i class="ti ti-share text-[13px]"></i>
                                {{ t('Share') }}
                            </button>
                            <div v-if="isShareOpen" class="absolute right-0 mt-2 w-max min-w-[180px] rounded-2xl border border-gray-200 bg-white p-3 shadow-lg z-50 dark:border-white/10 dark:bg-gray-800">
                                <SocialShare :url="seo.canonical || shareUrl" :title="tool.name" :style="'icon-label'" :layout="'vertical'" :networks="['facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'copy']" />
                            </div>
                        </div>

                        <Tooltip v-if="!toolPageSettings.hide_favorite" :content="tool.is_favorited ? t('Remove from favorites') : t('Add to favorites')" placement="bottom">
                            <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" show-count size="sm" />
                        </Tooltip>
                    </div>
                </div>

                <!-- Title & Description Row / Card -->
                <div :class="[
                    toolPageSettings.layout === 'modern'
                        ? 'card p-6 rounded-2xl border border-gray-100 bg-gradient-to-br from-blue-500/5 to-purple-500/5 dark:border-white/5 dark:from-blue-500/[0.02] dark:to-purple-500/[0.02] shadow-sm'
                        : ''
                ]">
                    <!-- Inside Card Top Row for Modern Layout only (Breadcrumbs, Share, Favorite) -->
                    <div v-if="toolPageSettings.layout === 'modern'" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between pb-4 mb-3 border-b border-gray-100 dark:border-white/5" :class="{ 'lg:justify-end': toolPageSettings.hide_breadcrumbs }">
                        <div v-if="!toolPageSettings.hide_breadcrumbs" class="flex flex-wrap items-center gap-2 text-sm">
                            <Link :href="routeTo('home')" class="inline-flex items-center gap-1.5 text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-white">
                                <i class="ti ti-home"></i>
                            </Link>
                            <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                            <Link :href="routeTo('ai.tools.index')" class="text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-white">{{ t('AI Tools') }}</Link>
                            <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                            <Link v-if="tool.category" :href="routeTo('ai.tools.category', tool.category.slug)" class="text-gray-500 transition-colors hover:text-primary-600 dark:hover:text-primary-400">{{ tool.category.name }}</Link>
                            <i v-if="tool.category" class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-xs"></i>
                            <span class="text-gray-700 dark:text-gray-300">{{ tool.name }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Share Dropdown Button -->
                            <div v-if="!toolPageSettings.hide_share" class="relative">
                                <button
                                    type="button"
                                    @click.stop="isShareOpen = !isShareOpen"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                                >
                                    <i class="ti ti-share text-[13px]"></i>
                                    {{ t('Share') }}
                                </button>
                                <div v-if="isShareOpen" class="absolute right-0 mt-2 w-max min-w-[180px] rounded-2xl border border-gray-200 bg-white p-3 shadow-lg z-50 dark:border-white/10 dark:bg-gray-800">
                                    <SocialShare :url="seo.canonical || shareUrl" :title="tool.name" :style="'icon-label'" :layout="'vertical'" :networks="['facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'copy']" />
                                </div>
                            </div>

                            <Tooltip v-if="!toolPageSettings.hide_favorite" :content="tool.is_favorited ? t('Remove from favorites') : t('Add to favorites')" placement="bottom">
                                <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" show-count size="sm" />
                            </Tooltip>
                        </div>
                    </div>

                    <!-- Title & Description Row -->
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 sm:mt-2 shrink-0 items-center justify-center rounded-2xl shadow-xs" :class="[
                            toolPageSettings.layout === 'modern'
                                ? 'bg-gradient-to-br from-blue-500 to-purple-500 text-white border border-transparent'
                                : 'border'
                        ]" :style="toolPageSettings.layout === 'modern' ? {} : { backgroundColor: (tool.color || '#1F75FE') + '15', borderColor: (tool.color || '#1F75FE') + '25' }">
                            <i :class="[tool.icon || 'ti ti-wand', 'text-[28px]', toolPageSettings.layout === 'modern' ? 'text-white' : '']" :style="toolPageSettings.layout === 'modern' ? {} : { color: tool.color || '#1F75FE' }"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="font-heading text-[2rem] font-black tracking-tight text-gray-900 dark:text-white">{{ tool.name }}</h1>
                                <Tooltip v-if="tool.category && !toolPageSettings.hide_category && toolPageSettings.layout !== 'minimalist' && toolPageSettings.layout !== 'modern'" :content="t('Tool category')" placement="bottom">
                                    <Link :href="routeTo('ai.tools.category', tool.category.slug)" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-[11px] font-medium text-gray-500 shadow-xs transition-colors hover:text-primary-600 hover:border-primary-200 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:text-primary-400 dark:hover:border-primary-500/30 no-underline">
                                        <i v-if="tool.category.icon" :class="tool.category.icon" class="text-[13px]"></i>
                                        {{ tool.category.name }}
                                    </Link>
                                </Tooltip>
                                <span v-if="!toolPageSettings.hide_labels" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-[0.18em] whitespace-nowrap shadow-xs" :class="accessBadgeClass">
                                    {{ accessBadgeLabel }}
                                </span>
                            </div>

                            <!-- Simple Metadata Row just below title (Minimalist & Modern layouts) -->
                            <div v-if="toolPageSettings.layout === 'minimalist' || toolPageSettings.layout === 'modern'" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400 mt-1.5 mb-2">
                                <!-- Category -->
                                <span v-if="showCategoryMeta" class="flex items-center gap-1">
                                    <i class="ti ti-folder text-base text-gray-400"></i>
                                    <Link :href="routeTo('ai.tools.category', tool.category.slug)" class="font-medium text-gray-600 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 transition-colors no-underline">
                                        {{ tool.category.name }}
                                    </Link>
                                </span>

                                <!-- Divider -->
                                <span v-if="showCategoryUsageDivider" class="text-[10px] leading-none text-gray-300 dark:text-surface-700 -mx-1.5">•</span>

                                <!-- Usage Count -->
                                <span v-if="showUsageMeta" class="flex items-center gap-1">
                                    <i class="ti ti-player-play text-base text-gray-400"></i>
                                    <span>{{ formatViews(tool.usage_count || tool.views_count || 0) }} {{ t('uses') }}</span>
                                </span>

                                <!-- Divider -->
                                <span v-if="showRatingDivider" class="text-[10px] leading-none text-gray-300 dark:text-surface-700 -mx-1.5">•</span>

                                <!-- Rating -->
                                <span v-if="showRatingMeta" class="flex items-center gap-1">
                                    <span class="relative inline-block whitespace-nowrap text-base leading-none" aria-hidden="true"><span class="text-gray-300 dark:text-surface-600"><i class="ti ti-star-filled"></i></span><span class="absolute inset-y-0 left-0 overflow-hidden whitespace-nowrap text-amber-400" :style="{ width: ratingPercent + '%' }"><i class="ti ti-star-filled"></i></span></span>
                                    <span class="font-bold text-gray-700 dark:text-gray-200">{{ Number(tool.avg_rating).toFixed(1) }}</span>
                                    <span class="text-xs text-gray-400" v-if="tool.review_count">({{ tool.review_count }})</span>
                                </span>
                            </div>

                            <p class="max-w-5xl text-[15px] text-gray-500 dark:text-gray-400 mt-1">
                                {{ tool.description }}
                            </p>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Creative places this inside the left column, under the title card (below),
                 because that layout has no full-width band above the grid to sit in. -->
            <AdSection v-if="toolPageSettings.layout !== 'creative'" zone="tool_page_top" bare class="mx-auto mb-4 w-full max-w-[728px]" />

            <div class="grid min-h-0 gap-6 lg:grid-cols-12" :class="[
                toolPageSettings.layout === 'minimalist' ? 'max-w-4xl mx-auto w-full' : '',
                toolPageSettings.layout === 'modern' ? 'lg:gap-8' : ''
            ]">
                <!-- Left Column (Parameters Form) -->
                <div class="min-h-0 min-w-0 w-full lg:col-span-4" :class="[
                    toolPageSettings.layout === 'creative' ? 'flex flex-col gap-6' : ''
                ]">
                    <!-- Integrated Header for Creative Layout -->
                    <div v-if="toolPageSettings.layout === 'creative'" class="relative rounded-2xl border border-gray-200 bg-linear-to-b from-gray-50/50 to-white p-6 shadow-sm dark:border-white/5 dark:from-white/[0.02] dark:to-white/[0.01]">
                        <!-- Colored blur container to prevent overflow while keeping dropdown visible outside the card -->
                        <div class="absolute inset-0 overflow-hidden rounded-2xl pointer-events-none">
                            <!-- Colored blur background effect -->
                            <div class="absolute -right-16 -top-16 h-32 w-32 rounded-full blur-3xl opacity-20 dark:opacity-30" :style="{ backgroundColor: tool.color || '#1F75FE' }"></div>
                        </div>

                        <!-- Breadcrumbs -->
                        <div v-if="!toolPageSettings.hide_breadcrumbs" class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500 mb-4">
                            <Link :href="routeTo('home')" class="text-gray-500 hover:text-primary-600 dark:hover:!text-white">
                                <i class="ti ti-home"></i>
                            </Link>
                            <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-[10px]"></i>
                            <Link :href="routeTo('ai.tools.index')" class="text-gray-500 hover:text-primary-600 dark:hover:!text-white">{{ t('AI Tools') }}</Link>
                            <i class="ti ti-chevron-right text-gray-400 dark:text-gray-600 text-[10px]"></i>
                            <span class="text-gray-700 dark:text-gray-300 line-clamp-1">{{ tool.name }}</span>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border shadow-sm" :style="{ backgroundColor: (tool.color || '#1F75FE') + '15', borderColor: (tool.color || '#1F75FE') + '25' }">
                                <i :class="[tool.icon || 'ti ti-wand', 'text-2xl']" :style="{ color: tool.color || '#1F75FE' }"></i>
                            </div>
                            <div class="min-w-0">
                                <h1 class="font-heading text-xl font-bold text-gray-900 dark:text-white leading-none">{{ tool.name }}</h1>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <Link v-if="tool.category && !toolPageSettings.hide_category" :href="routeTo('ai.tools.category', tool.category.slug)" class="inline-flex items-center gap-1 text-[11px] text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors no-underline">
                                        <i v-if="tool.category.icon" :class="tool.category.icon"></i>
                                        {{ tool.category.name }}
                                    </Link>
                                    <span v-if="!toolPageSettings.hide_labels" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[8px] font-bold uppercase tracking-wider" :class="accessBadgeClass">
                                        {{ accessBadgeLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ tool.description }}</p>

                        <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-white/5">
                            <div class="flex items-center gap-3">
                                <Tooltip v-if="(tool.usage_count || tool.views_count) && !toolPageSettings.hide_usage_count" :content="t('Total views')" placement="bottom">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        <i class="ti ti-eye text-[13px] text-gray-500 dark:text-gray-400"></i>
                                        {{ formatViews(tool.usage_count || tool.views_count || 0) }}
                                    </span>
                                </Tooltip>
                                <Tooltip v-if="!toolPageSettings.hide_rating && tool.avg_rating" :content="t('Average rating')" placement="bottom">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        <i class="ti ti-star-filled text-[13px] text-warning-400"></i>
                                        {{ (tool.avg_rating || 0).toFixed(1) }}
                                    </span>
                                </Tooltip>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Share Dropdown Button -->
                                <div v-if="!toolPageSettings.hide_share" class="relative">
                                    <button
                                        type="button"
                                        @click.stop="isShareOpen = !isShareOpen"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-[11px] font-bold text-gray-700 transition-all hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                                    >
                                        <i class="ti ti-share text-[12px]"></i>
                                        {{ t('Share') }}
                                    </button>
                                    <div v-if="isShareOpen" class="absolute right-0 mt-2 w-72 rounded-2xl border border-gray-200 bg-white p-3.5 shadow-lg z-50 dark:border-white/10 dark:bg-gray-800">
                                        <SocialShare :url="seo.canonical || shareUrl" :title="tool.name" :style="'icon-label'" :networks="['facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'copy']" />
                                    </div>
                                </div>
                                <Tooltip v-if="!toolPageSettings.hide_favorite" :content="tool.is_favorited ? t('Remove from favorites') : t('Add to favorites')" placement="bottom">
                                    <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" show-count size="sm" />
                                </Tooltip>
                            </div>
                        </div>
                    </div>

                    <!-- Creative layout: the top slot lives here, directly under the title
                         card in the left column. The zone shares one creative with the other
                         layouts (a 728x90 leaderboard), so it just fills the column width and
                         scales down proportionally rather than being capped. -->
                    <AdSection v-if="toolPageSettings.layout === 'creative'" zone="tool_page_top" bare class="w-full" />

                    <!-- Parameters Form Card -->
                    <div :class="[
                        toolPageSettings.layout === 'modern'
                            ? 'card lg:sticky lg:top-6 flex flex-col lg:h-full lg:max-h-[calc(100vh-9rem)] lg:overflow-hidden rounded-2xl border border-gray-100 bg-gradient-to-br from-blue-500/5 to-purple-500/5 dark:border-white/5 dark:from-blue-500/[0.02] dark:to-purple-500/[0.02] shadow-sm w-full max-w-full'
                            : 'card lg:sticky lg:top-6 flex flex-col lg:h-full lg:max-h-[calc(100vh-9rem)] lg:overflow-hidden rounded-2xl border border-gray-100 bg-white/90 dark:border-white/5 dark:bg-white/[0.03] w-full max-w-full'
                    ]">
                        <!-- Form Header -->
                        <div class="shrink-0 border-b border-gray-100 px-6 py-4 dark:border-white/5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <i :class="['ti ti-sparkles', 'text-[20px]']" :style="{ color: tool.color || '#1F75FE' }"></i>
                                    <div class="min-w-0 flex-1">
                                        <h6 class="text-sm font-semibold text-gray-700 dark:text-white">{{ t('Prompt Parameters') }}</h6>
                                    </div>
                                </div>
                                <Tooltip v-if="hasUsageExamples && usageExamples.length > 0" :content="t('Try example')" placement="left">
                                    <button type="button" :disabled="applyingExampleIndex !== null" class="text-gray-400 transition-colors hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 disabled:opacity-50" @click="applyExample(usageExamples[0], 0)">
                                        <i class="ti ti-refresh text-base"></i>
                                    </button>
                                </Tooltip>
                            </div>
                        </div>

                        <!-- Form Body -->
                        <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5">
                            <div v-if="!canGenerate" class="mb-4 rounded-xl border px-4 py-3 text-xs" :class="bannerClass">
                                <div class="flex items-center gap-2 font-medium">
                                    <i :class="bannerIcon" class="text-[14px]"></i>
                                    {{ bannerTitle }}
                                </div>
                                <div class="mt-1">
                                    <Link :href="bannerLink" class="underline font-semibold hover:no-underline">
                                        {{ bannerAction }}
                                    </Link>
                                </div>
                            </div>
                            <DynamicForm v-model="formValues" :fields="dynamicFields" :languages="languages" :models="models" :disabled="isAnyStreaming" @submit="runGenerate">
                                <div v-if="showCreditCosts && dynamicCredits" class="mb-4 rounded-xl border px-4 py-3 text-xs" :class="isGuestFreePreview || estimateIsByok ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-primary-100 bg-primary-50 text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300'">
                                    <div class="flex items-center gap-2 font-medium">
                                        <i :class="isGuestFreePreview ? 'ti ti-gift' : (estimateIsByok ? 'ti ti-key' : 'ti ti-receipt-2')" class="text-[14px]"></i>
                                        {{ isGuestFreePreview ? t('Free preview') : t('Estimated cost') }}
                                    </div>
                                    <div v-if="isGuestFreePreview" class="mt-1 text-emerald-600 dark:text-emerald-400">
                                        {{ t('No login or credits required. Output may be limited.') }}
                                    </div>
                                    <div v-else-if="estimateIsByok" class="mt-1 text-emerald-600 dark:text-emerald-400">
                                        {{ t('Free — using your own API key') }}
                                    </div>
                                    <div v-else class="mt-1">
                                        <span class="font-semibold">~{{ dynamicCredits.estimated_credits }}</span> {{ t('credits') }}
                                        <span v-if="dynamicCredits.estimated_tokens"> · ~{{ dynamicCredits.estimated_tokens }} {{ t('tokens') }}</span>
                                    </div>
                                </div>
                                <div v-if="activeError" class="mb-4 flex items-start gap-2 rounded-xl border border-danger-500/20 bg-danger-500/10 px-4 py-3 text-sm text-danger-600 dark:text-danger-400">
                                    <i class="ti ti-alert-triangle mt-0.5 shrink-0"></i>
                                    <div>{{ activeError }}</div>
                                </div>
                            </DynamicForm>
                        </div>

                        <!-- Form Footer -->
                        <div class="shrink-0 border-t border-gray-100 bg-white/95 py-4 backdrop-blur-md dark:border-white/5 dark:bg-[#101418]/90 px-6">
                            <!-- Brand voice: on tools that support it, for signed-in users -->
                            <div v-if="showBrandVoice" class="mb-3 flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5 dark:border-white/5 dark:bg-white/5">
                                <div class="flex items-center gap-2 text-xs">
                                    <i class="ti ti-palette text-primary-500"></i>
                                    <span class="font-medium text-gray-700 dark:text-gray-200">{{ t('Use my brand voice') }}</span>
                                </div>
                                <label v-if="hasBrandVoice" class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" v-model="useBrandVoice" class="peer sr-only" />
                                    <div class="h-5 w-9 rounded-full bg-gray-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:bg-primary-500 peer-checked:after:translate-x-4 dark:bg-gray-600"></div>
                                </label>
                                <a v-else :href="route('user.dashboard.profile')" class="whitespace-nowrap text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ t('Set it up') }}</a>
                            </div>
                            <button type="button" :disabled="!canSubmit || !canGenerate" class="btn-primary w-full justify-center rounded-xl py-3 text-sm font-semibold shadow-lg disabled:cursor-not-allowed disabled:opacity-50" @click="runGenerate">
                                <svg v-if="isAnyStreaming" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <template v-else-if="isLimited">
                                    <i class="ti ti-alarm"></i>{{ t('Try again in :time', { time: formattedCountdown }) }}
                                </template>
                                <template v-else>
                                    <i class="ti ti-wand"></i>{{ t('Generate Content') }}
                                </template>
                            </button>
                            <div v-if="isNearLimit && !isLimited" class="mt-2 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                                {{ t(':count requests remaining', { count: remaining }) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Output Panel & Nested Tabs) -->
                <div class="min-h-0 min-w-0 w-full flex flex-col gap-3 lg:col-span-8">
                    <!-- Variation Tabs -->
                    <div v-if="isVariationsMode && (activeOutput || isAnyStreaming)" class="flex gap-2 overflow-x-auto pb-px" :class="[
                        toolPageSettings.layout === 'default'
                            ? 'border-b border-gray-200 dark:border-white/5'
                            : 'mb-1 hide-scrollbar'
                    ]">
                        <button
                            v-for="(stream, index) in variationStreams"
                            :key="index"
                            @click="activeVariationTab = index"
                            :class="[
                                activeVariationTab === index
                                    ? (toolPageSettings.layout === 'default'
                                        ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400 font-bold'
                                        : 'bg-primary-500 text-white font-bold px-3 py-1.5 rounded-lg shadow-sm')
                                    : (toolPageSettings.layout === 'default'
                                        ? 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium'
                                        : 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium px-3 py-1.5 rounded-lg'),
                                toolPageSettings.layout === 'default' ? 'px-4 py-2.5 text-xs' : 'text-xs transition-all'
                            ]"
                            class="whitespace-nowrap flex items-center gap-1.5"
                        >
                            <span class="inline-flex h-2 w-2 rounded-full" :class="stream.isStreaming.value ? 'bg-primary-500 animate-pulse' : 'bg-gray-300 dark:bg-gray-600'"></span>
                            {{ t('Variation :num', { num: index + 1 }) }}
                        </button>
                    </div>

                    <!-- Output Panel Card Wrapper -->
                    <div :class="[
                        toolPageSettings.layout === 'modern'
                            ? 'card flex-1 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500/5 to-purple-500/5 shadow-sm backdrop-blur-md dark:from-blue-500/[0.02] dark:to-purple-500/[0.02] dark:shadow-none relative w-full max-w-full'
                            : (toolPageSettings.layout === 'creative'
                                ? 'card flex-1 overflow-hidden rounded-2xl bg-white/90 dark:bg-white/[0.03] w-full max-w-full'
                                : 'card flex-1 overflow-hidden rounded-2xl bg-white/90 dark:bg-white/[0.03] w-full max-w-full')
                    ]" :style="[
                        toolPageSettings.layout === 'creative'
                            ? { boxShadow: `0 10px 30px -10px ${tool.color || '#1F75FE'}25`, border: `1px solid ${tool.color || '#1F75FE'}20` }
                            : {}
                    ]">
                        <!-- Gradient accent top line for Modern layout -->
                        <div v-if="toolPageSettings.layout === 'modern'" class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-purple-500 rounded-t-2xl z-10"></div>
                        <OutputPanel :output="activeOutput" :reasoning="activeReasoning" :is-reasoning="activeIsReasoning" :output-type="tool.output_type || 'markdown'" :loading="activeIsStreaming" :usage="activeUsage" :saved-document="activeSavedDocument" :show-credit-costs="showCreditCosts" :can-save="Boolean(authUser)" :slug="tool.slug" :default-title="`${tool.name} Output`" @document-saved="handleDocumentSaved" />
                    </div>

                    <!-- Action Buttons -->
                    <div v-if="activeOutput" class="flex flex-wrap gap-2 output-actions">
                        <button type="button" :disabled="isAnyStreaming || !canGenerate" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition-colors hover:!border-primary-200 hover:!bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:!bg-white/[0.03] dark:!text-gray-300 dark:hover:!border-gray-800 dark:hover:!text-white dark:hover:!bg-white/10 disabled:opacity-50" @click="regenerate">
                            <i class="ti ti-refresh text-[14px]"></i>
                            {{ t('Regenerate') }}
                        </button>

                        <Tooltip v-if="(tool.max_variants ?? 0) > 1" :content="t('Generates 3 alternatives simultaneously using 3x credits')" placement="top">
                            <button type="button" :disabled="isAnyStreaming || !canGenerate" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition-colors hover:!border-primary-200 hover:!bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:!bg-white/[0.03] dark:!text-gray-300 dark:hover:!border-gray-800 dark:hover:!text-white dark:hover:!bg-white/10 disabled:opacity-50" @click="generateVariations">
                                <svg v-if="isAnyStreaming && isVariationsMode" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <i v-else class="ti ti-layers-difference text-[14px]"></i>
                                {{ t('Get Variations') }}
                            </button>
                        </Tooltip>

                        <button type="button" :disabled="isAnyStreaming || !canGenerate" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition-colors hover:!border-primary-200 hover:!bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:!bg-white/[0.03] dark:!text-gray-300 dark:hover:!border-gray-800 dark:hover:!text-white dark:hover:!bg-white/10 disabled:opacity-50" @click="isSidebarOpen = true">
                            <i class="ti ti-sparkles text-[14px]"></i>
                            {{ t('Improve') }}
                        </button>

                        <Link v-if="authUser && activeSavedDocument?.id" :href="activeSavedDocument?.id ? routeTo('documents.edit', activeSavedDocument.id) : '#'" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition-colors hover:!border-primary-200 hover:!bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:!bg-white/[0.03] dark:!text-gray-300 dark:hover:!border-gray-800 dark:hover:!text-white dark:hover:!bg-white/10 no-underline">
                            <i class="ti ti-edit text-[14px]"></i>
                            {{ t('Edit in Editor') }}
                        </Link>
                    </div>

                    <!-- Creative layout: bottom slot sits after the output content and
                         before the nested tabs, matching the other layouts' placement. -->
                    <AdSection v-if="toolPageSettings.layout === 'creative'" zone="tool_page_bottom" bare class="mx-auto mt-6 w-full max-w-[728px]" />

                    <!-- Nested Content Tabs (Creative Layout) -->
                    <div v-if="contentTabsVisible && toolPageSettings.layout === 'creative'" class="mt-6 w-full max-w-full overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/5 dark:bg-white/[0.03]">
                        <!-- Nested Tabs Navigation -->
                        <div class="mb-4 flex gap-2 overflow-x-auto pb-px hide-scrollbar">
                            <button v-if="hasAbout" @click="activeTab = 'about'" :class="[activeTab === 'about' ? (toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold' : 'bg-primary-500 text-white font-bold') : 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium', toolPageSettings.layout === 'modern' ? 'px-4 py-1.5 rounded-full' : 'px-3 py-1.5 rounded-lg']" class="text-xs font-semibold transition-all whitespace-nowrap">{{ t('About') }}</button>
                            <button v-if="hasHowItWorks" @click="activeTab = 'how'" :class="[activeTab === 'how' ? (toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold' : 'bg-primary-500 text-white font-bold') : 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium', toolPageSettings.layout === 'modern' ? 'px-4 py-1.5 rounded-full' : 'px-3 py-1.5 rounded-lg']" class="text-xs font-semibold transition-all whitespace-nowrap">{{ t('How It Works') }}</button>
                            <button v-if="hasUsageExamples" @click="activeTab = 'examples'" :class="[activeTab === 'examples' ? (toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold' : 'bg-primary-500 text-white font-bold') : 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium', toolPageSettings.layout === 'modern' ? 'px-4 py-1.5 rounded-full' : 'px-3 py-1.5 rounded-lg']" class="text-xs font-semibold transition-all whitespace-nowrap">{{ t('Examples') }}</button>
                            <button v-if="hasFaqs" @click="activeTab = 'faqs'" :class="[activeTab === 'faqs' ? (toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold' : 'bg-primary-500 text-white font-bold') : 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium', toolPageSettings.layout === 'modern' ? 'px-4 py-1.5 rounded-full' : 'px-3 py-1.5 rounded-lg']" class="text-xs font-semibold transition-all whitespace-nowrap">{{ t('FAQs') }}</button>
                            <button v-if="tool.show_reviews && !toolPageSettings.hide_rating" @click="activeTab = 'reviews'" :class="[activeTab === 'reviews' ? (toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold' : 'bg-primary-500 text-white font-bold') : 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium', toolPageSettings.layout === 'modern' ? 'px-4 py-1.5 rounded-full' : 'px-3 py-1.5 rounded-lg']" class="text-xs font-semibold transition-all whitespace-nowrap">{{ t('Reviews') }}</button>
                                            <button v-if="tool.show_related_tools && relatedTools.length" @click="activeTab = 'related'" :class="[activeTab === 'related' ? (toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold' : 'bg-primary-500 text-white font-bold') : 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium', toolPageSettings.layout === 'modern' ? 'px-4 py-1.5 rounded-full' : 'px-3 py-1.5 rounded-lg']" class="text-xs font-semibold transition-all whitespace-nowrap">{{ t('Related') }}</button>
                        </div>

                        <!-- Nested Tabs Content -->
                        <Transition name="tab-slide" mode="out-in">
                            <div :key="activeTab" class="min-h-0">
                                <div v-if="activeTab === 'about' && hasAbout" class="space-y-6">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('About :tool', { tool: tool.name }) }}</h3>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('Learn more about this tool and what it can generate.') }}</p>
                                    </div>
                                    <section class="prose dark:prose-invert prose-sm max-w-none text-gray-700 dark:text-gray-300" v-html="tool.about_content"></section>
                                </div>

                                <div v-if="activeTab === 'how' && hasHowItWorks" class="space-y-6">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('How It Works') }}</h3>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('Follow these simple steps to get the best results.') }}</p>
                                    </div>
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <div v-for="(step, index) in howItWorks" :key="index" class="relative rounded-2xl border border-gray-200 bg-white p-6 dark:border-white/5 dark:bg-[#111827]/40 shadow-xs">
                                            <div class="absolute -top-3 -left-3 flex h-8 w-8 items-center justify-center rounded-full border border-primary-500/30 bg-primary-500/20 text-sm font-bold text-primary-600 dark:text-primary-400">
                                                {{ step.step || index + 1 }}
                                            </div>
                                            <i :class="[step.icon || 'ti-check', 'mb-4 block text-2xl']" :style="{ color: tool.color || '#10b981' }"></i>
                                            <h4 class="mb-2 font-semibold text-gray-900 dark:text-white leading-tight">{{ step.title }}</h4>
                                            <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ step.description }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="activeTab === 'examples' && hasUsageExamples" class="space-y-6">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Usage Examples') }}</h3>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('Explore sample inputs and outputs for this tool.') }}</p>
                                    </div>
                                    <div v-for="(example, index) in usageExamples" :key="index" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-white/5 dark:bg-white/[0.02] hover:border-primary-500/30 dark:hover:border-primary-500/30">
                                        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50/50 px-6 py-4 dark:border-white/5 dark:bg-white/5">
                                            <h4 class="font-medium text-gray-900 dark:text-white">{{ example.title }}</h4>
                                            <button
                                                :disabled="applyingExampleIndex !== null"
                                                @click="applyExample(example, Number(index))"
                                                class="try-example rounded-lg border border-primary-500/20 bg-primary-500/10 px-3 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-primary-500/20 dark:border-primary-500/30 dark:bg-primary-500/20 dark:text-primary-400 dark:hover:bg-primary-500/30 disabled:opacity-50"
                                            >
                                                {{ applyingExampleIndex === Number(index) ? t('Applying...') : t('Try this example') }}
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 divide-y divide-gray-200 md:grid-cols-2 md:divide-x md:divide-y-0 dark:divide-white/5">
                                            <div class="p-6">
                                                <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Input Data') }}</div>
                                                <div class="space-y-2">
                                                    <div v-for="(value, key) in example.input" :key="key" class="flex gap-2">
                                                        <span class="min-w-[100px] text-sm font-medium text-gray-500 dark:text-gray-400">{{ getFieldLabel(String(key)) }}:</span>
                                                        <span class="break-words text-sm text-gray-700 dark:text-gray-300">{{ value }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bg-gray-50/20 p-6 dark:bg-white/[0.01]">
                                                <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Generated Output') }}</div>
                                                <div class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ truncatedOutput(example.output, Number(index)) }}</div>
                                                <button v-if="exampleOutput(example.output).length > 200" type="button" class="mt-3 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" @click="expandedExamples[Number(index)] = !expandedExamples[Number(index)]">{{ expandedExamples[Number(index)] ? t('Show less') : t('See full output') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="activeTab === 'faqs' && hasFaqs" class="space-y-6">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Frequently Asked Questions') }}</h3>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('Quick answers to common queries about :tool.', { tool: tool.name }) }}</p>
                                    </div>
                                    <div class="space-y-4">
                                        <details v-for="(faq, index) in faqItems" :key="index" class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-white/5 dark:bg-white/[0.02] hover:border-primary-500/30 dark:hover:border-primary-500/30 dark:shadow-none">
                                            <summary class="list-none flex cursor-pointer items-center justify-between px-6 py-4 font-medium text-gray-900 group-open:bg-gray-50 dark:text-white dark:group-open:bg-[#111827]/40">
                                                {{ faq.question }}
                                                <i class="ti ti-chevron-down text-gray-400 transition-transform dark:text-gray-500 group-open:rotate-180"></i>
                                            </summary>
                                            <div class="border-t border-gray-200 bg-gray-50/50 px-6 py-4 text-xs leading-relaxed text-gray-600 dark:border-white/5 dark:bg-white/[0.01] dark:text-gray-400" v-html="faq.answer"></div>
                                        </details>
                                    </div>
                                </div>

                                <div v-if="activeTab === 'related'" class="space-y-6">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Related AI Tools') }}</h3>
                                        <p class="text-xs text-gray-500 mt-1">{{ t('Discover other helpful AI generators in our catalog.') }}</p>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                                        <Link
                                            v-for="tool in relatedTools"
                                            :key="tool.slug"
                                            :href="routeTo('ai.tools.show', tool.slug)"
                                            :class="[
                                                'block rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-md',
                                                toolPageSettings.layout === 'modern'
                                                    ? 'bg-gradient-to-br from-blue-500/5 to-purple-500/5 border border-white/10 dark:border-white/5 dark:from-blue-500/[0.01] dark:to-purple-500/[0.01]'
                                                    : (toolPageSettings.layout === 'creative'
                                                        ? 'bg-white/80 dark:bg-white/[0.02] shadow-xs hover:border-primary-500/30 dark:hover:border-primary-500/30'
                                                        : 'border border-gray-200 bg-white shadow-xs dark:border-white/5 dark:bg-white/[0.02] hover:border-primary-500/30 dark:hover:border-primary-500/30')
                                            ]"
                                            :style="[
                                                toolPageSettings.layout === 'creative'
                                                    ? { border: `1px solid ${tool.color || '#1F75FE'}20`, boxShadow: `0 4px 20px -10px ${tool.color || '#1F75FE'}15` }
                                                    : {}
                                            ]"
                                        >
                                            <i :class="[tool.icon || 'ti ti-wand', 'mb-3 block text-2xl text-primary-600 dark:text-primary-400']" :style="{ color: tool.color }"></i>
                                            <h4 class="mb-2 font-semibold text-gray-900 dark:text-white leading-tight">{{ tool.name }}</h4>
                                            <p class="line-clamp-2 text-xs text-gray-500">{{ tool.description }}</p>
                                        </Link>
                                    </div>
                                </div>

                                <div v-if="activeTab === 'reviews'" class="space-y-6">
                                    <div class="flex flex-wrap items-center justify-between gap-4">
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Reviews for :tool', { tool: tool.name }) }}</h3>
                                            <p class="text-xs text-gray-500">
                                                <span v-if="!tool.review_count || tool.review_count === 0">{{ t('No reviews for this tool yet.') }}</span>
                                                <template v-else>
                                                    {{ tool.avg_rating || 0 }}/5
                                                    <span v-if="!toolPageSettings.hide_rating_count"> {{ t('from') }} {{ tool.review_count || 0 }} {{ t('reviews') }}</span>
                                                    <span v-else> {{ t('reviews') }}</span>
                                                </template>
                                            </p>
                                        </div>
                                        <div v-if="tool.review_count && tool.review_count > 0" class="shrink-0 w-64">
                                            <AppSelect v-model="reviewSort" :options="sortOptions" @update:model-value="changeReviewSort" />
                                        </div>
                                    </div>
                                    <div v-if="tool.review_count && tool.review_count > 0" class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-white/5 dark:bg-white/[0.02]">
                                        <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="grid grid-cols-[52px_1fr_42px] items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span>{{ rating }} {{ t('star') }}</span>
                                            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:!bg-white/10"><div class="h-full rounded-full bg-warning-400" :style="{ width: `${reviewStats.distribution?.[rating]?.percent || 0}%` }"></div></div>
                                            <span class="text-end">{{ reviewStats.distribution?.[rating]?.percent || 0 }}%</span>
                                        </div>
                                    </div>
                                    <form v-if="authUser && reviewUnlocked" class="space-y-3" @submit.prevent="submitReview">
                                        <div class="flex items-center gap-1.5 mb-2">
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ t('Your Rating:') }}</span>
                                            <div class="flex items-center gap-1" @mouseleave="hoverRating = 0">
                                                <button
                                                    v-for="star in 5"
                                                    :key="star"
                                                    type="button"
                                                    @click="reviewRating = star"
                                                    @mouseover="hoverRating = star"
                                                    class="focus:outline-none transition-transform hover:scale-110"
                                                >
                                                    <i
                                                        class="ti text-xl cursor-pointer"
                                                        :class="[
                                                            (hoverRating > 0 ? star <= hoverRating : star <= reviewRating)
                                                                ? 'ti-star-filled text-warning-400'
                                                                : 'ti-star text-gray-300 dark:text-gray-600'
                                                        ]"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>
                                        <textarea v-model="reviewComment" rows="3" maxlength="2000" class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-xs text-gray-900 transition-all placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-white/10 dark:bg-[#111827]/40 dark:text-white dark:placeholder-gray-600" :placeholder="t('Share your experience with this tool')"></textarea>
                                        <div class="flex items-center gap-3">
                                            <button type="submit" :disabled="reviewSubmitting" class="btn-primary rounded-xl px-4 py-2 text-xs font-semibold disabled:opacity-50">{{ reviewSubmitting ? t('Submitting...') : t('Write Review') }}</button>
                                            <span v-if="reviewMessage" class="text-xs text-gray-500 dark:text-gray-400">{{ reviewMessage }}</span>
                                        </div>
                                    </form>
                                    <div v-else-if="authUser" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600 dark:border-white/5 dark:bg-[#111827]/40 dark:text-gray-400 shadow-xs">{{ t('Generate with this tool once to unlock review writing.') }}</div>
                                    <div v-if="reviews && reviews.length" class="space-y-4">
                                        <div v-for="review in reviews" :key="review.id" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-white/[0.02] dark:shadow-none">
                                            <div class="mb-3 flex items-start justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-gray-200 font-bold text-gray-600 dark:!bg-white/10 dark:!text-gray-300">
                                                        <img v-if="review.user?.avatar" :src="mediaUrl(review.user.avatar)" class="h-full w-full object-cover" />
                                                        <span v-else>{{ review.user?.name?.charAt(0) || 'U' }}</span>
                                                    </div>
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ review.user?.name || 'Anonymous' }}</span>
                                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">• {{ formatReviewDate(review.created_at) }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-1 text-xs text-warning-400">
                                                            <i v-for="star in 5" :key="star" :class="star <= review.rating ? 'ti ti-star-filled text-warning-400' : 'ti ti-star text-gray-300 dark:text-gray-600'"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="text-xs text-gray-500 hover:text-primary-600 dark:hover:text-primary-400" @click="voteReview(review, true)">{{ review.helpful_count || 0 }} {{ t('helpful') }}</button>
                                            </div>
                                            <p class="text-sm whitespace-pre-wrap text-gray-600 dark:text-gray-400">{{ review.comment }}</p>

                                            <!-- Redesigned Admin Reply -->
                                            <div v-if="review.admin_reply" class="mt-4 ml-6 pl-4 border-l-2 border-primary-500/30 dark:border-primary-500/20 space-y-2">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full bg-primary-500/10 text-xs font-bold text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                                                        <i class="ti ti-shield-check text-sm"></i>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white">{{ t('Support Team') }}</span>
                                                        <span class="inline-flex items-center rounded bg-primary-50 px-1.5 py-0.5 text-[9px] font-bold text-primary-600 dark:bg-primary-500/10 dark:text-primary-400 uppercase tracking-wide">{{ t('Admin') }}</span>
                                                        <span class="text-[10px] text-gray-400 dark:text-gray-500">• {{ formatReviewDate(review.updated_at) }}</span>
                                                    </div>
                                                </div>
                                                <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400 bg-gray-50/50 dark:bg-white/[0.01] rounded-xl border border-gray-100 dark:border-white/5 p-3 whitespace-pre-wrap">{{ review.admin_reply }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="rounded-2xl border border-gray-200 bg-gray-50 py-10 text-center dark:border-white/5 dark:bg-[#111827]/40 shadow-xs">
                                        <i class="ti ti-star mb-3 block text-3xl text-gray-400 dark:text-gray-600"></i>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('No reviews yet for this tool.') }}</p>
                                    </div>
                                    <div v-if="reviewsPage < reviewsLastPage" class="text-center">
                                        <button type="button" :disabled="reviewsLoading" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 transition-colors hover:border-primary-500/40 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-200 disabled:opacity-50" @click="loadMoreReviews">{{ reviewsLoading ? t('Loading...') : t('Load more reviews') }}</button>
                                    </div>
                                </div>


                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- Bottom Content (Default, Modern & Minimalist Layouts) -->
            <!-- Sits between the tool itself and the About/Reviews tabs, which is a natural
                 break in the page — it used to render at the very bottom, below everything.
                 Creative renders its own copy higher up, inside the output column. -->
            <AdSection v-if="toolPageSettings.layout !== 'creative'" zone="tool_page_bottom" bare class="mx-auto mt-8 w-full max-w-[728px]" />

            <div v-if="contentTabsVisible && (toolPageSettings.layout === 'default' || toolPageSettings.layout === 'minimalist' || toolPageSettings.layout === 'modern')" :class="[
                toolPageSettings.layout === 'minimalist'
                    ? 'mt-10 max-w-4xl mx-auto w-full space-y-12 pb-12'
                    : (toolPageSettings.layout === 'modern'
                        ? 'mt-10 rounded-2xl bg-gradient-to-br from-blue-500/5 to-purple-500/5 border border-white/20 p-5 shadow-lg backdrop-blur-md dark:border-white/5 dark:from-blue-500/[0.02] dark:to-purple-500/[0.02] dark:shadow-none'
                        : 'mt-10 rounded-2xl border border-gray-200 bg-white/85 px-4 py-4 shadow-card backdrop-blur-md dark:border-white/5 dark:bg-white/[0.03] sm:px-6')
            ]">
                <!-- Default & Modern Layout Tabs Navigation & Content -->
                <template v-if="toolPageSettings.layout === 'default' || toolPageSettings.layout === 'modern'">
                    <div :class="[
                        toolPageSettings.layout === 'modern'
                            ? 'mb-6 flex gap-2 overflow-x-auto pb-px hide-scrollbar'
                            : 'mb-4 flex gap-2 border-b border-gray-200 overflow-x-auto pb-px dark:border-white/5 hide-scrollbar'
                    ]">
                        <button v-if="hasAbout" @click="activeTab = 'about'" :class="[
                            activeTab === 'about'
                                ? (toolPageSettings.layout === 'modern'
                                    ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold px-4 py-1.5 rounded-full'
                                    : 'border-b-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 px-4 py-3 text-sm')
                                : (toolPageSettings.layout === 'modern'
                                    ? 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium px-4 py-1.5 rounded-full'
                                    : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium border-b-2 px-4 py-3 text-sm'),
                            'font-semibold transition-all whitespace-nowrap'
                        ]">{{ t('About') }}</button>
                        <button v-if="hasHowItWorks" @click="activeTab = 'how'" :class="[
                            activeTab === 'how'
                                ? (toolPageSettings.layout === 'modern'
                                    ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold px-4 py-1.5 rounded-full'
                                    : 'border-b-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 px-4 py-3 text-sm')
                                : (toolPageSettings.layout === 'modern'
                                    ? 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium px-4 py-1.5 rounded-full'
                                    : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium border-b-2 px-4 py-3 text-sm'),
                            'font-semibold transition-all whitespace-nowrap'
                        ]">{{ t('How It Works') }}</button>
                        <button v-if="hasUsageExamples" @click="activeTab = 'examples'" :class="[
                            activeTab === 'examples'
                                ? (toolPageSettings.layout === 'modern'
                                    ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold px-4 py-1.5 rounded-full'
                                    : 'border-b-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 px-4 py-3 text-sm')
                                : (toolPageSettings.layout === 'modern'
                                    ? 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium px-4 py-1.5 rounded-full'
                                    : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium border-b-2 px-4 py-3 text-sm'),
                            'font-semibold transition-all whitespace-nowrap'
                        ]">{{ t('Examples') }}</button>
                        <button v-if="hasFaqs" @click="activeTab = 'faqs'" :class="[
                            activeTab === 'faqs'
                                ? (toolPageSettings.layout === 'modern'
                                    ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold px-4 py-1.5 rounded-full'
                                    : 'border-b-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 px-4 py-3 text-sm')
                                : (toolPageSettings.layout === 'modern'
                                    ? 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium px-4 py-1.5 rounded-full'
                                    : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium border-b-2 px-4 py-3 text-sm'),
                            'font-semibold transition-all whitespace-nowrap'
                        ]">{{ t('FAQs') }}</button>
                        <button v-if="tool.show_reviews && !toolPageSettings.hide_rating" @click="activeTab = 'reviews'" :class="[
                            activeTab === 'reviews'
                                ? (toolPageSettings.layout === 'modern'
                                    ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold px-4 py-1.5 rounded-full'
                                    : 'border-b-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 px-4 py-3 text-sm')
                                : (toolPageSettings.layout === 'modern'
                                    ? 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium px-4 py-1.5 rounded-full'
                                    : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium border-b-2 px-4 py-3 text-sm'),
                            'font-semibold transition-all whitespace-nowrap'
                        ]">{{ t('Reviews') }}</button>
                        <button v-if="tool.show_related_tools && relatedTools.length" @click="activeTab = 'related'" :class="[
                            activeTab === 'related'
                                ? (toolPageSettings.layout === 'modern'
                                    ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold px-4 py-1.5 rounded-full'
                                    : 'border-b-primary-500 text-primary-600 dark:text-primary-400 font-bold border-b-2 px-4 py-3 text-sm')
                                : (toolPageSettings.layout === 'modern'
                                    ? 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-600 dark:text-gray-300 font-medium px-4 py-1.5 rounded-full'
                                    : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium border-b-2 px-4 py-3 text-sm'),
                            'font-semibold transition-all whitespace-nowrap'
                        ]">{{ t('Related') }}</button>
                    </div>

                    <Transition name="tab-slide" mode="out-in">
                        <div :key="activeTab" class="min-h-0">
                            <div v-if="activeTab === 'about' && hasAbout" class="space-y-6 pb-8">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('About :tool', { tool: tool.name }) }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ t('Learn more about this tool and what it can generate.') }}</p>
                                </div>
                                <section id="about" class="prose dark:prose-invert prose-sm max-w-none text-gray-700 dark:text-gray-300" v-html="tool.about_content"></section>
                            </div>

                            <div v-if="activeTab === 'how' && hasHowItWorks" class="space-y-6 pb-8">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('How It Works') }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ t('Follow these simple steps to get the best results.') }}</p>
                                </div>
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                                    <div v-for="(step, index) in howItWorks" :key="index" :class="[
                                        'relative rounded-2xl border p-6 shadow-xs',
                                        toolPageSettings.layout === 'modern'
                                            ? 'border-white/20 bg-white dark:border-white/5 dark:bg-[#111827]/40'
                                            : 'border-gray-200 bg-gray-50 dark:border-white/5 dark:bg-white/[0.02]'
                                    ]">
                                        <div class="absolute -top-3 -left-3 flex h-8 w-8 items-center justify-center rounded-full border border-primary-500/30 bg-primary-500/20 text-sm font-bold text-primary-600 dark:text-primary-400">
                                            {{ step.step || index + 1 }}
                                        </div>
                                        <i :class="[step.icon || 'ti-check', 'mb-4 block text-2xl']" :style="{ color: tool.color || '#10b981' }"></i>
                                        <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">{{ step.title }}</h4>
                                        <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-500">{{ step.description }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'examples' && hasUsageExamples" class="space-y-6 pb-8">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Usage Examples') }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ t('Explore sample inputs and outputs for this tool.') }}</p>
                                </div>
                                <div v-for="(example, index) in usageExamples" :key="index" :class="[
                                    'overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-white/[0.02] dark:shadow-none hover:border-primary-500/30 dark:hover:border-primary-500/30',
                                    toolPageSettings.layout === 'modern' ? 'border-white/20 dark:border-white/5' : 'border-gray-200 dark:border-white/5'
                                ]">
                                    <div :class="[
                                        'flex items-center justify-between border-b px-6 py-4 bg-gray-50 dark:bg-white/5',
                                        toolPageSettings.layout === 'modern' ? 'border-white/20 dark:border-white/5 bg-gray-50/50' : 'border-gray-200 dark:border-white/5'
                                    ]">
                                        <h4 class="font-medium text-gray-900 dark:text-white">{{ example.title }}</h4>
                                        <button
                                            :disabled="applyingExampleIndex !== null"
                                            @click="applyExample(example, Number(index))"
                                            class="rounded-lg border border-primary-500/20 bg-primary-500/10 px-3 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-primary-500/20 dark:border-primary-500/30 dark:bg-primary-500/20 dark:text-primary-400 dark:hover:bg-primary-500/30 disabled:opacity-50"
                                        >
                                            {{ applyingExampleIndex === Number(index) ? t('Applying...') : t('Try this example') }}
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-1 divide-y divide-gray-200 md:grid-cols-2 md:divide-x md:divide-y-0 dark:divide-white/5">
                                        <div class="p-6">
                                            <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Input Data') }}</div>
                                            <div class="space-y-2">
                                                <div v-for="(value, key) in example.input" :key="key" class="flex gap-2">
                                                    <span class="min-w-[100px] text-sm font-medium text-gray-500 dark:text-gray-400">{{ getFieldLabel(String(key)) }}:</span>
                                                    <span class="break-words text-sm text-gray-700 dark:text-gray-300">{{ value }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div :class="[
                                            'p-6',
                                            toolPageSettings.layout === 'modern' ? 'bg-gray-50/20 dark:bg-white/[0.01]' : 'bg-gray-50/50 dark:bg-white/[0.01]'
                                        ]">
                                            <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Generated Output') }}</div>
                                            <div class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ truncatedOutput(example.output, Number(index)) }}</div>
                                            <button v-if="exampleOutput(example.output).length > 200" type="button" class="mt-3 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" @click="expandedExamples[Number(index)] = !expandedExamples[Number(index)]">{{ expandedExamples[Number(index)] ? t('Show less') : t('See full output') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'faqs' && hasFaqs" class="space-y-6 pb-8">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Frequently Asked Questions') }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ t('Quick answers to common queries about :tool.', { tool: tool.name }) }}</p>
                                </div>
                                <div class="space-y-4">
                                    <details v-for="(faq, index) in faqItems" :key="index" :class="[
                                        'group overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-white/[0.02] dark:shadow-none hover:border-primary-500/30 dark:hover:border-primary-500/30',
                                        toolPageSettings.layout === 'modern' ? 'border-white/20 dark:border-white/5' : 'border-gray-200 dark:border-white/5'
                                    ]">
                                        <summary :class="[
                                            'list-none flex cursor-pointer items-center justify-between px-6 py-4 font-medium text-gray-900 group-open:bg-gray-50 dark:text-white',
                                            toolPageSettings.layout === 'modern' ? 'group-open:bg-[#111827]/40' : 'dark:group-open:bg-white/[0.03]'
                                        ]">
                                            {{ faq.question }}
                                            <i class="ti ti-chevron-down text-gray-400 transition-transform dark:text-gray-500 group-open:rotate-180"></i>
                                        </summary>
                                        <div :class="[
                                            'border-t bg-gray-50/50 px-6 py-4 text-sm leading-relaxed text-gray-600 dark:bg-white/[0.01] dark:text-gray-400',
                                            toolPageSettings.layout === 'modern' ? 'border-gray-200/60 dark:border-white/5' : 'border-gray-200 dark:border-white/5'
                                        ]" v-html="faq.answer"></div>
                                    </details>
                                </div>
                            </div>

                            <div v-if="activeTab === 'related'" class="space-y-6 pb-8">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Related AI Tools') }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ t('Discover other helpful AI generators in our catalog.') }}</p>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                                    <Link
                                        v-for="tool in relatedTools"
                                        :key="tool.slug"
                                        :href="routeTo('ai.tools.show', tool.slug)"
                                        :class="[
                                            'block rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-md',
                                            toolPageSettings.layout === 'modern'
                                                ? 'bg-gradient-to-br from-blue-500/5 to-purple-500/5 border border-white/10 dark:border-white/5 dark:from-blue-500/[0.01] dark:to-purple-500/[0.01]'
                                                : (toolPageSettings.layout === 'creative'
                                                    ? 'bg-white/80 dark:bg-white/[0.02] shadow-xs hover:border-primary-500/30 dark:hover:border-primary-500/30 hover:border-primary-500/30 dark:hover:border-primary-500/30'
                                                    : 'border border-gray-200 bg-white shadow-xs dark:border-white/5 dark:bg-white/[0.02] hover:border-primary-500/30 dark:hover:border-primary-500/30 hover:border-primary-500/30 dark:hover:border-primary-500/30')
                                        ]"
                                        :style="[
                                            toolPageSettings.layout === 'creative'
                                                ? { border: `1px solid ${tool.color || '#1F75FE'}20`, boxShadow: `0 4px 20px -10px ${tool.color || '#1F75FE'}15` }
                                                : {}
                                        ]"
                                    >
                                        <i :class="[tool.icon || 'ti ti-wand', 'mb-3 block text-2xl text-primary-600 dark:text-primary-400']" :style="{ color: tool.color }"></i>
                                        <h4 class="mb-2 font-semibold text-gray-900 dark:text-white leading-tight">{{ tool.name }}</h4>
                                        <p class="line-clamp-3 text-sm text-gray-500">{{ tool.description }}</p>
                                    </Link>
                                </div>
                            </div>

                            <div v-if="activeTab === 'reviews'" class="max-w-4xl space-y-6 pb-8">
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold" :class="toolPageSettings.layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text !text-transparent dark:from-blue-400 dark:to-purple-400 inline-block' : 'text-gray-900 dark:text-white'">{{ t('Reviews for :tool', { tool: tool.name }) }}</h3>
                                        <p class="text-sm text-gray-500">
                                            <span v-if="!tool.review_count || tool.review_count === 0">{{ t('No reviews for this tool yet.') }}</span>
                                            <template v-else>
                                                {{ tool.avg_rating || 0 }}/5
                                                <span v-if="!toolPageSettings.hide_rating_count"> {{ t('from') }} {{ tool.review_count || 0 }} {{ t('reviews') }}</span>
                                                <span v-else> {{ t('reviews') }}</span>
                                            </template>
                                        </p>
                                    </div>
                                    <div v-if="tool.review_count && tool.review_count > 0" class="flex items-center gap-2"><AppSelect v-model="reviewSort" :options="sortOptions" @update:model-value="changeReviewSort" /></div>
                                </div>
                                <div v-if="tool.review_count && tool.review_count > 0" :class="[
                                    'space-y-2 rounded-2xl border p-5',
                                    toolPageSettings.layout === 'modern'
                                        ? 'border-white/20 bg-white/50 dark:border-white/5 dark:bg-white/[0.02]'
                                        : 'border-gray-200 bg-gray-50 dark:border-white/5 dark:bg-white/[0.02]'
                                ]">
                                    <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="grid grid-cols-[52px_1fr_42px] items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ rating }} {{ t('star') }}</span>
                                        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:!bg-white/10"><div class="h-full rounded-full bg-warning-400" :style="{ width: `${reviewStats.distribution?.[rating]?.percent || 0}%` }"></div></div>
                                        <span class="text-end">{{ reviewStats.distribution?.[rating]?.percent || 0 }}%</span>
                                    </div>
                                </div>
                                <form v-if="authUser && reviewUnlocked" class="space-y-3" @submit.prevent="submitReview">
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ t('Your Rating:') }}</span>
                                        <div class="flex items-center gap-1" @mouseleave="hoverRating = 0">
                                            <button
                                                v-for="star in 5"
                                                :key="star"
                                                type="button"
                                                @click="reviewRating = star"
                                                @mouseover="hoverRating = star"
                                                class="focus:outline-none transition-transform hover:scale-110"
                                            >
                                                <i
                                                    class="ti text-xl cursor-pointer"
                                                    :class="[
                                                        (hoverRating > 0 ? star <= hoverRating : star <= reviewRating)
                                                            ? 'ti-star-filled text-warning-400'
                                                            : 'ti-star text-gray-300 dark:text-gray-600'
                                                    ]"
                                                ></i>
                                            </button>
                                        </div>
                                    </div>
                                    <textarea v-model="reviewComment" rows="3" maxlength="2000" class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 transition-all placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-white/10 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-600" :placeholder="t('Share your experience with this tool')"></textarea>
                                    <div class="flex items-center gap-3">
                                        <button type="submit" :disabled="reviewSubmitting" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-50">{{ reviewSubmitting ? t('Submitting...') : t('Write Review') }}</button>
                                        <span v-if="reviewMessage" class="text-xs text-gray-500 dark:text-gray-400">{{ reviewMessage }}</span>
                                    </div>
                                </form>
                                <div v-else-if="authUser" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-400 shadow-xs">{{ t('Generate with this tool once to unlock review writing.') }}</div>
                                <div v-if="reviews && reviews.length" class="space-y-4">
                                    <div v-for="review in reviews" :key="review.id" :class="[
                                        'rounded-2xl border p-6 shadow-sm dark:bg-white/[0.02] dark:shadow-none',
                                        toolPageSettings.layout === 'modern'
                                            ? 'border-white/20 bg-white/40'
                                            : 'border-gray-200 bg-white'
                                    ]">
                                        <div class="mb-3 flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-gray-200 font-bold text-gray-600 dark:!bg-white/10 dark:!text-gray-300">
                                                    <img v-if="review.user?.avatar" :src="mediaUrl(review.user.avatar)" class="h-full w-full object-cover" />
                                                    <span v-else>{{ review.user?.name?.charAt(0) || 'U' }}</span>
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ review.user?.name || 'Anonymous' }}</span>
                                                        <span class="text-[10px] text-gray-400 dark:text-gray-500">• {{ formatReviewDate(review.created_at) }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-1 text-xs text-warning-400">
                                                        <i v-for="star in 5" :key="star" :class="star <= review.rating ? 'ti ti-star-filled text-warning-400' : 'ti ti-star text-gray-300 dark:text-gray-600'"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="text-xs text-gray-500 hover:text-primary-600 dark:hover:text-primary-400" @click="voteReview(review, true)">{{ review.helpful_count || 0 }} {{ t('helpful') }}</button>
                                        </div>
                                        <p class="text-sm whitespace-pre-wrap text-gray-600 dark:text-gray-400">{{ review.comment }}</p>

                                        <!-- Redesigned Admin Reply -->
                                        <div v-if="review.admin_reply" class="mt-4 ml-6 pl-4 border-l-2 border-primary-500/30 dark:border-primary-500/20 space-y-2">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full bg-primary-500/10 text-xs font-bold text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                                                    <i class="ti ti-shield-check text-sm"></i>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ t('Support Team') }}</span>
                                                    <span class="inline-flex items-center rounded bg-primary-50 px-1.5 py-0.5 text-[9px] font-bold text-primary-600 dark:bg-primary-500/10 dark:text-primary-400 uppercase tracking-wide">{{ t('Admin') }}</span>
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500">• {{ formatReviewDate(review.updated_at) }}</span>
                                                </div>
                                            </div>
                                            <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400 bg-gray-50/50 dark:bg-white/[0.01] rounded-xl border border-gray-100 dark:border-white/5 p-3 whitespace-pre-wrap">{{ review.admin_reply }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="rounded-2xl border border-gray-200 bg-gray-50 py-10 text-center dark:border-white/5 dark:bg-white/[0.02]">
                                    <i class="ti ti-star mb-3 block text-4xl text-gray-400 dark:text-gray-600"></i>
                                    <p class="text-gray-500 dark:text-gray-400">{{ t('No reviews yet for this tool.') }}</p>
                                </div>
                                <div v-if="reviewsPage < reviewsLastPage" class="text-center">
                                    <button type="button" :disabled="reviewsLoading" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:border-primary-500/40 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-200 disabled:opacity-50" @click="loadMoreReviews">{{ reviewsLoading ? t('Loading...') : t('Load more reviews') }}</button>
                                </div>
                            </div>

                            <div v-if="activeTab === 'related'" class="grid grid-cols-1 gap-4 pb-8 sm:grid-cols-2 md:grid-cols-3">
                                <Link
                                    v-for="tool in relatedTools"
                                    :key="tool.slug"
                                    :href="routeTo('ai.tools.show', tool.slug)"
                                    :class="[
                                        'block rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-md',
                                        toolPageSettings.layout === 'modern'
                                            ? 'bg-gradient-to-br from-blue-500/5 to-purple-500/5 border border-white/10 dark:border-white/5 dark:from-blue-500/[0.01] dark:to-purple-500/[0.01]'
                                            : (toolPageSettings.layout === 'creative'
                                                ? 'bg-white/80 dark:bg-white/[0.02] shadow-xs hover:border-primary-500/30 dark:hover:border-primary-500/30 hover:border-primary-500/30 dark:hover:border-primary-500/30'
                                                : 'border border-gray-200 bg-white shadow-xs dark:border-white/5 dark:bg-white/[0.02] hover:border-primary-500/30 dark:hover:border-primary-500/30 hover:border-primary-500/30 dark:hover:border-primary-500/30')
                                    ]"
                                    :style="[
                                        toolPageSettings.layout === 'creative'
                                            ? { border: `1px solid ${tool.color || '#1F75FE'}20`, boxShadow: `0 4px 20px -10px ${tool.color || '#1F75FE'}15` }
                                            : {}
                                    ]"
                                >
                                    <i :class="[tool.icon || 'ti ti-wand', 'mb-3 block text-2xl text-primary-600 dark:text-primary-400']" :style="{ color: tool.color }"></i>
                                    <h4 class="mb-2 font-semibold text-gray-900 dark:text-white leading-tight">{{ tool.name }}</h4>
                                    <p class="line-clamp-3 text-sm text-gray-500">{{ tool.description }}</p>
                                </Link>
                            </div>
                        </div>
                    </Transition>
                </template>

                <!-- Minimalist Layout Sequential Page Content -->
                <div v-else-if="toolPageSettings.layout === 'minimalist'" class="space-y-12">
                    <!-- About Section -->
                    <section v-if="hasAbout" class="space-y-6">
                        <div class="min-w-0">
                            <h3 class="text-xl font-heading font-bold text-gray-900 dark:text-white">{{ t('About :tool', { tool: tool.name }) }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ t('Learn more about this tool and what it can generate.') }}</p>
                        </div>
                        <div class="prose dark:prose-invert prose-sm max-w-none text-gray-700 dark:text-gray-300" v-html="tool.about_content"></div>
                    </section>

                    <div v-if="hasAbout && (hasHowItWorks || hasUsageExamples || hasFaqs || tool.show_reviews || (tool.show_related_tools && relatedTools.length))" class="border-t border-gray-100 dark:border-white/5"></div>

                    <!-- How It Works Section -->
                    <section v-if="hasHowItWorks" class="space-y-6">
                        <div class="min-w-0">
                            <h3 class="text-xl font-heading font-bold text-gray-900 dark:text-white">{{ t('How It Works') }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ t('Follow these simple steps to get the best results.') }}</p>
                        </div>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div v-for="(step, index) in howItWorks" :key="index" class="relative rounded-2xl border border-gray-200 bg-white p-6 dark:border-white/5 dark:bg-white/[0.02]">
                                <div class="absolute -top-3 -left-3 flex h-8 w-8 items-center justify-center rounded-full border border-primary-500/30 bg-primary-500/20 text-sm font-bold text-primary-600 dark:text-primary-400">
                                    {{ step.step || index + 1 }}
                                </div>
                                <i :class="[step.icon || 'ti-check', 'mb-4 block text-2xl']" :style="{ color: tool.color || '#10b981' }"></i>
                                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">{{ step.title }}</h4>
                                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ step.description }}</p>
                            </div>
                        </div>
                    </section>

                    <div v-if="hasHowItWorks && (hasUsageExamples || hasFaqs || tool.show_reviews || (tool.show_related_tools && relatedTools.length))" class="border-t border-gray-100 dark:border-white/5"></div>

                    <!-- Examples Section -->
                    <section v-if="hasUsageExamples" class="space-y-6">
                        <div class="min-w-0">
                            <h3 class="text-xl font-heading font-bold text-gray-900 dark:text-white">{{ t('Usage Examples') }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ t('Explore sample inputs and outputs for this tool.') }}</p>
                        </div>
                        <div class="space-y-6">
                            <div v-for="(example, index) in usageExamples" :key="index" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/5 dark:bg-white/[0.02]">
                                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50/50 px-6 py-4 dark:border-white/5 dark:bg-white/5">
                                    <h4 class="font-medium text-gray-900 dark:text-white">{{ example.title }}</h4>
                                    <button
                                        :disabled="applyingExampleIndex !== null"
                                        @click="applyExample(example, Number(index))"
                                        class="rounded-lg border border-primary-500/20 bg-primary-500/10 px-3 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-primary-500/20 dark:border-primary-500/30 dark:bg-primary-500/20 dark:text-primary-400 dark:hover:bg-primary-500/30 disabled:opacity-50"
                                    >
                                        {{ applyingExampleIndex === Number(index) ? t('Applying...') : t('Try this example') }}
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 divide-y divide-gray-200 md:grid-cols-2 md:divide-x md:divide-y-0 dark:divide-white/5">
                                    <div class="p-6">
                                        <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Input Data') }}</div>
                                        <div class="space-y-2">
                                            <div v-for="(value, key) in example.input" :key="key" class="flex gap-2">
                                                <span class="min-w-[100px] text-sm font-medium text-gray-500 dark:text-gray-400">{{ getFieldLabel(String(key)) }}:</span>
                                                <span class="break-words text-sm text-gray-700 dark:text-gray-300">{{ value }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50/20 p-6 dark:bg-white/[0.01]">
                                        <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Generated Output') }}</div>
                                        <div class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ truncatedOutput(example.output, Number(index)) }}</div>
                                        <button v-if="exampleOutput(example.output).length > 200" type="button" class="mt-3 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" @click="expandedExamples[Number(index)] = !expandedExamples[Number(index)]">{{ expandedExamples[Number(index)] ? t('Show less') : t('See full output') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div v-if="hasUsageExamples && (hasFaqs || tool.show_reviews || (tool.show_related_tools && relatedTools.length))" class="border-t border-gray-100 dark:border-white/5"></div>

                    <!-- FAQs Section -->
                    <section v-if="hasFaqs" class="space-y-6">
                        <div class="min-w-0">
                            <h3 class="text-xl font-heading font-bold text-gray-900 dark:text-white">{{ t('Frequently Asked Questions') }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ t('Quick answers to common queries about :tool.', { tool: tool.name }) }}</p>
                        </div>
                        <div class="space-y-4">
                            <details
                                v-for="(faq, index) in faqItems"
                                :key="index"
                                class="group overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/5 dark:bg-white/[0.02]"
                                :open="index === 0"
                            >
                                <summary class="flex cursor-pointer items-center justify-between gap-4 px-6 py-4 font-semibold text-gray-900 select-none dark:text-white focus:outline-hidden [&::-webkit-details-marker]:hidden">
                                    <span>{{ faq.question }}</span>
                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center text-gray-400 transition-transform group-open:rotate-180">
                                        <i class="ti ti-chevron-down text-base"></i>
                                    </span>
                                </summary>
                                <div class="border-t border-gray-200 bg-gray-50/50 px-6 py-4 text-sm leading-relaxed text-gray-600 dark:border-white/5 dark:bg-white/[0.01] dark:text-gray-400" v-html="faq.answer"></div>
                            </details>
                        </div>
                    </section>

                    <div v-if="hasFaqs && (tool.show_reviews || (tool.show_related_tools && relatedTools.length))" class="border-t border-gray-100 dark:border-white/5"></div>

                    <!-- Reviews Section -->
                    <section v-if="tool.show_reviews" class="space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-heading font-bold text-gray-900 dark:text-white">{{ t('Reviews') }}</h3>
                                <p class="text-sm text-gray-500">
                                    <span v-if="!tool.review_count || tool.review_count === 0">{{ t('No reviews yet.') }}</span>
                                    <template v-else>
                                        {{ tool.avg_rating || 0 }}/5
                                        <span v-if="!toolPageSettings.hide_rating_count"> {{ t('from') }} {{ tool.review_count || 0 }} {{ t('reviews') }}</span>
                                        <span v-else> {{ t('reviews') }}</span>
                                    </template>
                                </p>
                            </div>
                            <div v-if="tool.review_count && tool.review_count > 0" class="flex items-center gap-2"><AppSelect v-model="reviewSort" :options="sortOptions" @update:model-value="changeReviewSort" /></div>
                        </div>
                        <div v-if="tool.review_count && tool.review_count > 0" class="space-y-2 rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/5 dark:bg-white/[0.02]">
                            <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="grid grid-cols-[52px_1fr_42px] items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ rating }} {{ t('star') }}</span>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:!bg-white/10"><div class="h-full rounded-full bg-warning-400" :style="{ width: `${reviewStats.distribution?.[rating]?.percent || 0}%` }"></div></div>
                                <span class="text-end">{{ reviewStats.distribution?.[rating]?.percent || 0 }}%</span>
                            </div>
                        </div>
                        <form v-if="authUser && reviewUnlocked" class="space-y-3" @submit.prevent="submitReview">
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ t('Your Rating:') }}</span>
                                <div class="flex items-center gap-1" @mouseleave="hoverRating = 0">
                                    <button
                                        v-for="star in 5"
                                        :key="star"
                                        type="button"
                                        @click="reviewRating = star"
                                        @mouseover="hoverRating = star"
                                        class="focus:outline-none transition-transform hover:scale-110"
                                    >
                                        <i
                                            class="ti text-xl cursor-pointer"
                                            :class="[
                                                (hoverRating > 0 ? star <= hoverRating : star <= reviewRating)
                                                    ? 'ti-star-filled text-warning-400'
                                                    : 'ti-star text-gray-300 dark:text-gray-600'
                                            ]"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                            <textarea v-model="reviewComment" rows="3" maxlength="2000" class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 transition-all placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-white/10 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-600" :placeholder="t('Share your experience with this tool')"></textarea>
                            <div class="flex items-center gap-3">
                                <button type="submit" :disabled="reviewSubmitting" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-50">{{ reviewSubmitting ? t('Submitting...') : t('Write Review') }}</button>
                                <span v-if="reviewMessage" class="text-xs text-gray-500 dark:text-gray-400">{{ reviewMessage }}</span>
                            </div>
                        </form>
                        <div v-else-if="authUser" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-400">{{ t('Generate with this tool once to unlock review writing.') }}</div>
                        <div v-if="reviews && reviews.length" class="space-y-4">
                            <div v-for="review in reviews" :key="review.id" class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-white/5 dark:bg-white/[0.02]">
                                <div class="mb-3 flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-gray-200 font-bold text-gray-600 dark:!bg-white/10 dark:!text-gray-300">
                                            <img v-if="review.user?.avatar" :src="mediaUrl(review.user.avatar)" class="h-full w-full object-cover" />
                                            <span v-else>{{ review.user?.name?.charAt(0) || 'U' }}</span>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ review.user?.name || 'Anonymous' }}</span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500">• {{ formatReviewDate(review.created_at) }}</span>
                                            </div>
                                            <div class="flex items-center gap-1 text-xs text-warning-400">
                                                <i v-for="star in 5" :key="star" :class="star <= review.rating ? 'ti ti-star-filled text-warning-400' : 'ti ti-star text-gray-300 dark:text-gray-600'"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="text-xs text-gray-500 hover:text-primary-600 dark:hover:text-primary-400" @click="voteReview(review, true)">{{ review.helpful_count || 0 }} {{ t('helpful') }}</button>
                                </div>
                                <p class="text-sm whitespace-pre-wrap text-gray-600 dark:text-gray-400">{{ review.comment }}</p>

                                <!-- Redesigned Admin Reply -->
                                <div v-if="review.admin_reply" class="mt-4 ml-6 pl-4 border-l-2 border-primary-500/30 dark:border-primary-500/20 space-y-2">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full bg-primary-500/10 text-xs font-bold text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                                            <i class="ti ti-shield-check text-sm"></i>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ t('Support Team') }}</span>
                                            <span class="inline-flex items-center rounded bg-primary-50 px-1.5 py-0.5 text-[9px] font-bold text-primary-600 dark:bg-primary-500/10 dark:text-primary-400 uppercase tracking-wide">{{ t('Admin') }}</span>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">• {{ formatReviewDate(review.updated_at) }}</span>
                                        </div>
                                    </div>
                                    <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400 bg-gray-50/50 dark:bg-white/[0.01] rounded-xl border border-gray-100 dark:border-white/5 p-3 whitespace-pre-wrap">{{ review.admin_reply }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-2xl border border-gray-200 bg-white py-10 text-center dark:border-white/5 dark:bg-white/[0.02]">
                            <i class="ti ti-star mb-3 block text-4xl text-gray-400 dark:text-gray-600"></i>
                            <p class="text-gray-500 dark:text-gray-400">{{ t('No reviews yet for this tool.') }}</p>
                        </div>
                        <div v-if="reviewsPage < reviewsLastPage" class="text-center">
                            <button type="button" :disabled="reviewsLoading" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:border-primary-500/40 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-200 disabled:opacity-50" @click="loadMoreReviews">{{ reviewsLoading ? t('Loading...') : t('Load more reviews') }}</button>
                        </div>
                    </section>

                    <div v-if="tool.show_reviews && (tool.show_related_tools && relatedTools.length)" class="border-t border-gray-100 dark:border-white/5"></div>

                    <!-- Related Tools Section -->
                    <section v-if="tool.show_related_tools && relatedTools.length" class="space-y-6">
                        <div class="min-w-0">
                            <h3 class="text-xl font-heading font-bold text-gray-900 dark:text-white">{{ t('Related AI Tools') }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ t('Discover other helpful AI generators in our catalog.') }}</p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                            <Link
                                v-for="tool in relatedTools"
                                :key="tool.slug"
                                :href="routeTo('ai.tools.show', tool.slug)"
                                :class="[
                                    toolPageSettings.layout === 'minimalist'
                                        ? 'block rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1'
                                        : 'block rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-md',
                                    toolPageSettings.layout === 'modern'
                                        ? 'bg-gradient-to-br from-blue-500/5 to-purple-500/5 border border-white/10 dark:border-white/5 dark:from-blue-500/[0.01] dark:to-purple-500/[0.01]'
                                        : (toolPageSettings.layout === 'creative'
                                            ? 'bg-white/80 dark:bg-white/[0.02] shadow-xs hover:border-primary-500/30 dark:hover:border-primary-500/30 hover:border-primary-500/30 dark:hover:border-primary-500/30'
                                            : (toolPageSettings.layout === 'minimalist'
                                                ? 'border border-gray-200 bg-white dark:border-white/5 dark:bg-white/[0.02] hover:!border-primary-500/30 dark:hover:!border-primary-500/30'
                                                : 'border border-gray-200 bg-white shadow-xs dark:border-white/5 dark:bg-white/[0.02] hover:border-primary-500/30 dark:hover:border-primary-500/30 hover:border-primary-500/30 dark:hover:border-primary-500/30'))
                                ]"
                                :style="[
                                    toolPageSettings.layout === 'creative'
                                        ? { border: `1px solid ${tool.color || '#1F75FE'}20`, boxShadow: `0 4px 20px -10px ${tool.color || '#1F75FE'}15` }
                                        : {}
                                ]"
                            >
                                <i :class="[tool.icon || 'ti ti-wand', 'mb-3 block text-2xl text-primary-600 dark:text-primary-400']" :style="{ color: tool.color }"></i>
                                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white leading-tight">{{ tool.name }}</h4>
                                <p class="line-clamp-3 text-sm text-gray-500">{{ tool.description }}</p>
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </div>

    </div>


    <!-- AI Refinement Sidebar Drawer -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isSidebarOpen"
                class="frontend-theme-vars fixed inset-0 z-50 flex justify-end bg-black/45 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="closeSidebar"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-300 ease-out transform"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-250 ease-in transform"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div class="relative w-full max-w-md border-l border-gray-200 bg-white p-6 shadow-2xl dark:border-white/5 dark:bg-[#111827] flex flex-col h-full">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-white/5">
                            <div class="flex items-center gap-2">
                                <i class="ti ti-sparkles text-xl text-primary-500"></i>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Improve Output') }}</h3>
                            </div>
                            <button type="button" class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400" @click="closeSidebar">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </div>

                        <!-- Content Area -->
                        <div class="flex-1 overflow-y-auto py-6 space-y-6">
                            <!-- Source Content Preview -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ t('Source Content') }}</label>
                                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 text-xs text-gray-500 dark:border-white/5 dark:bg-white/[0.02] max-h-24 overflow-y-auto line-clamp-4">
                                    {{ activeOutput }}
                                </div>
                            </div>

                            <!-- Presets -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ t('Refinement Presets') }}</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="preset in refinementPresets"
                                        :key="preset.label"
                                        type="button"
                                        class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-3 text-left text-xs font-medium text-gray-700 shadow-sm transition-all hover:border-primary-500 hover:bg-primary-50/50 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                                        @click="selectPreset(preset)"
                                    >
                                        <i :class="preset.icon" class="text-primary-500 text-sm"></i>
                                        {{ t(preset.label) }}
                                    </button>
                                </div>
                            </div>

                            <!-- Custom Prompt -->
                            <div class="flex-1 flex flex-col">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ t('Refinement Instruction') }}</label>
                                <textarea
                                    v-model="refineInstruction"
                                    rows="5"
                                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 transition-all placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-white/10 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-600"
                                    :placeholder="t('Explain how you want to improve or rewrite the output...')"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="border-t border-gray-100 pt-4 dark:border-white/5">
                            <button
                                type="button"
                                :disabled="!refineInstruction.trim() || activeIsStreaming || !canGenerate"
                                class="btn-primary w-full justify-center rounded-xl py-3 text-sm font-semibold shadow-lg disabled:cursor-not-allowed disabled:opacity-50"
                                @click="applyRefinement"
                            >
                                <svg v-if="activeIsStreaming" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <template v-else>
                                    <i class="ti ti-sparkles"></i>{{ t('Refine Output') }}
                                </template>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Glassmorphism Styles for Modern Layout */
.layout-modern :deep(input),
.layout-modern :deep(select),
.layout-modern :deep(textarea) {
    background-color: rgba(255, 255, 255, 0.45) !important;
    backdrop-filter: blur(8px) !important;
    -webkit-backdrop-filter: blur(8px) !important;
    border-color: rgba(229, 231, 235, 0.5) !important;
    transition: all 0.2s ease-in-out !important;
}

.dark .layout-modern :deep(input),
.dark .layout-modern :deep(select),
.dark .layout-modern :deep(textarea) {
    background-color: rgba(255, 255, 255, 0.02) !important;
    border-color: rgba(255, 255, 255, 0.05) !important;
}

.layout-modern :deep(input:focus),
.layout-modern :deep(select:focus),
.layout-modern :deep(textarea:focus) {
    background-color: rgba(255, 255, 255, 0.65) !important;
    border-color: var(--color-primary-500) !important;
    box-shadow: 0 0 0 3px rgba(31, 117, 254, 0.15) !important;
}

.dark .layout-modern :deep(input:focus),
.dark .layout-modern :deep(select:focus),
.dark .layout-modern :deep(textarea:focus) {
    background-color: rgba(255, 255, 255, 0.04) !important;
    box-shadow: 0 0 0 3px rgba(31, 117, 254, 0.25) !important;
}

/* De-border inner cards nested inside outer layout cards for Modern layout */
.layout-modern :deep(.card .card) {
    background-color: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

/* Scrollbar Hiding */
.hide-scrollbar::-webkit-scrollbar {
    display: none !important;
}
.hide-scrollbar {
    -ms-overflow-style: none !important;
    scrollbar-width: none !important;
}

.layout-modern :deep(.overflow-y-auto)::-webkit-scrollbar {
    display: none !important;
}
.layout-modern :deep(.overflow-y-auto) {
    -ms-overflow-style: none !important;
    scrollbar-width: none !important;
}

/* Modern Layout Headings: Gradient text from blue to purple */
.layout-modern h1,
.layout-modern h2,
.layout-modern h3,
.layout-modern h4,
.layout-modern h5,
.layout-modern h6,
.layout-modern :deep(h1),
.layout-modern :deep(h2),
.layout-modern :deep(h3),
.layout-modern :deep(h4),
.layout-modern :deep(h5),
.layout-modern :deep(h6) {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6) !important;
    background-clip: text !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
    color: transparent !important;
}

/* Modern Layout Primary Buttons: Gradient background from blue to purple */
.layout-modern .btn-primary,
.layout-modern :deep(.btn-primary) {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6) !important;
    border: none !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2) !important;
}
.layout-modern .btn-primary:hover:not(:disabled),
.layout-modern :deep(.btn-primary:hover:not(:disabled)) {
    opacity: 0.95 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3) !important;
}

/* Modern Layout Secondary Action buttons */
.layout-modern .output-actions button:not(.btn-primary):not([disabled]),
.layout-modern .output-actions a.inline-flex:not(.btn-primary),
.layout-modern .try-example,
.layout-modern :deep(.output-actions button:not(.btn-primary):not([disabled])),
.layout-modern :deep(.output-actions a.inline-flex:not(.btn-primary)),
.layout-modern :deep(.try-example) {
    background: linear-gradient(to bottom right, rgba(59, 130, 246, 0.05), rgba(139, 92, 246, 0.05)) !important;
    border: 1px solid rgba(59, 130, 246, 0.15) !important;
    transition: all 0.2s ease-in-out !important;
}
.layout-modern .output-actions button:not(.btn-primary):not([disabled]):hover,
.layout-modern .output-actions a.inline-flex:not(.btn-primary):hover,
.layout-modern .try-example:hover,
.layout-modern :deep(.output-actions button:not(.btn-primary):not([disabled]):hover),
.layout-modern :deep(.output-actions a.inline-flex:not(.btn-primary):hover),
.layout-modern :deep(.try-example:hover) {
    background: linear-gradient(to bottom right, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1)) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    transform: translateY(-1px) !important;
}

/* Smooth sliding tab transition */
.tab-slide-enter-active,
.tab-slide-leave-active {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.tab-slide-enter-from {
    opacity: 0;
    transform: translateX(12px);
}
.tab-slide-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}
</style>

