<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import DynamicForm, { type ToolField } from '@/Components/AI/DynamicForm.vue'
import OutputPanel from '@/Components/AI/OutputPanel.vue'
import FavoriteButton from '@/Components/FavoriteButton.vue'
import LoginPromptModal from '@/Components/AI/LoginPromptModal.vue'
import UpgradeModal from '@/Components/AI/UpgradeModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'
import { useStream } from '@/Composables/useStream'
import { useRateLimit } from '@/Composables/useRateLimit'
import { useToolPageShortcuts } from '@/Composables/useKeyboardShortcuts'
import AdSection from '@/Components/AdSection.vue'
import SocialShare from '@/Components/SocialShare.vue'

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
    fields: ToolField[] | string | Record<string, ToolField>
    about_content?: string
    how_it_works?: Array<{ step?: number; icon?: string; title: string; description: string }> | string | Record<string, unknown>
    usage_examples?: Array<{ title: string; input: Record<string, unknown>; output: string }> | string | Record<string, unknown>
    faq_items?: Array<{ question: string; answer: string }> | string | Record<string, unknown>
    avg_rating?: number
    review_count?: number
    views_count?: number
    avg_latency_ms?: number
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

const props = defineProps<{
    tool: ToolData
    seo: Record<string, string>
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
    canReview: boolean
    restoredHistory?: RestoredHistory | null
    effectiveMaxTokens: number
}>()

const formValues = ref<Record<string, unknown>>({})
const activeTab = ref('about')
const reviewRating = ref(5)
const reviewComment = ref('')
const reviewMessage = ref('')
const reviewSubmitting = ref(false)
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

const allStreams = [mainStream, ...variationStreams]
allStreams.forEach((s) => {
    watch(s.error, (val) => {
        if (val) toast.error(val)
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
        fields: formValues.value,
        model,
        action: 'refine',
        refine_content: contentToRefine,
        refine_instruction: refineInstruction.value
    })
}

const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const showLoginModal = ref(false)
const showUpgradeModal = ref(false)

const routeTo = (name: string, params?: unknown): string => route(name, params)

const formatLatency = (ms: number): string => {
    if (ms < 1000) return `${ms}ms`
    const seconds = Math.round(ms / 1000)
    if (seconds < 60) return `${seconds}s`
    const minutes = Math.floor(seconds / 60)
    const remainingSeconds = seconds % 60
    return remainingSeconds > 0 ? `${minutes}m ${remainingSeconds}s` : `${minutes}m`
}

const formatViews = (views: number): string => {
    if (views >= 1000000) return `${(views / 1000000).toFixed(1)}M`
    if (views >= 1000) return `${(views / 1000).toFixed(1)}K`
    return views.toString()
}

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

const needsLogin = computed(() => {
    const level = props.tool.access_level || 'inherit'
    const effectiveLevel = level === 'inherit' ? (props.tool.category?.access_level || 'guest') : level
    return (effectiveLevel === 'login' || effectiveLevel === 'premium' || effectiveLevel.startsWith('plan:')) && !props.authUser?.id
})

const needsPro = computed(() => {
    const level = props.tool.access_level || 'inherit'
    const effectiveLevel = level === 'inherit' ? (props.tool.category?.access_level || 'guest') : level
    if (effectiveLevel !== 'premium' && !effectiveLevel.startsWith('plan:')) return false
    if (!isProAvailable.value) return false
    if (!props.authUser?.id) return true
    return !props.authUser.is_pro
})

const canGenerate = computed(() => {
    if (needsLogin.value) return false
    if (needsPro.value) return false
    return true
})

const bannerType = computed(() => {
    if (needsPro.value) return 'pro'
    if (needsLogin.value) {
        const level = props.tool.access_level || 'inherit'
        if (level === 'public') return 'free_limited'
        return 'login'
    }
    return null
})

const bannerClass = computed(() => {
    switch (bannerType.value) {
        case 'pro': return 'border-accent-500/20 bg-accent-500/10 text-accent-700 dark:border-accent-500/20 dark:bg-accent-500/10 dark:text-accent-300'
        case 'free_limited': return 'border-emerald-500/20 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300'
        case 'login': return 'border-amber-500/20 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300'
        default: return ''
    }
})

const bannerIcon = computed(() => {
    switch (bannerType.value) {
        case 'pro': return 'ti ti-crown'
        case 'free_limited': return 'ti ti-gift'
        case 'login': return 'ti ti-login'
        default: return ''
    }
})

const bannerTitle = computed(() => {
    switch (bannerType.value) {
        case 'pro': return t('Pro subscription required')
        case 'free_limited': return t('Free but limited access')
        case 'login': return t('Login required to generate')
        default: return ''
    }
})

const bannerAction = computed(() => {
    switch (bannerType.value) {
        case 'pro': return t('Upgrade to Pro')
        case 'free_limited': return t('Sign in for full access')
        case 'login': return t('Sign in now')
        default: return ''
    }
})

const bannerLink = computed(() => {
    switch (bannerType.value) {
        case 'pro': return routeTo('pricing')
        case 'free_limited': return routeTo('login')
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
    if (!canSubmit.value || !canGenerate.value) return
    const level = props.tool.access_level || 'inherit'
    const effectiveLevel = level === 'inherit' ? (props.tool.category?.access_level || 'guest') : level
    if (!props.authUser?.id && effectiveLevel !== 'guest') { showLoginModal.value = true; return }
    if (effectiveLevel === 'premium' || effectiveLevel.startsWith('plan:')) { showUpgradeModal.value = true; return }

    const modelField = fields.value.find(f => f.type === 'model_select')
    const model = modelField ? String(formValues.value[fieldName(modelField)] || '') : ''
    isVariationsMode.value = false
    mainStream.generate({ slug: props.tool.slug, fields: formValues.value, model })
}

const generateVariations = async () => {
    if (isAnyStreaming.value || !canGenerate.value) return
    const level = props.tool.access_level || 'inherit'
    const effectiveLevel = level === 'inherit' ? (props.tool.category?.access_level || 'guest') : level
    if (!props.authUser?.id && effectiveLevel !== 'guest') { showLoginModal.value = true; return }
    if (effectiveLevel === 'premium' || effectiveLevel.startsWith('plan:')) { showUpgradeModal.value = true; return }

    isVariationsMode.value = true
    activeVariationTab.value = 0

    const modelField = fields.value.find(f => f.type === 'model_select')
    const model = modelField ? String(formValues.value[fieldName(modelField)] || '') : ''

    await Promise.all(variationStreams.map((stream, index) => {
        return stream.generate({ 
            slug: props.tool.slug, 
            fields: { ...formValues.value, variation_index: index }, 
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
const applyExample = (example: { input?: Record<string, unknown> }) => { formValues.value = { ...formValues.value, ...(example.input || {}) } }
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
</script>

<template>
    <Head>
        <title>{{ seo.title || tool.name }}</title>
        <meta name="description" :content="seo.description || tool.description" />
        <meta name="keywords" :content="seo.keywords" />
        <link rel="canonical" :href="seo.canonical" />
        <meta property="og:title" :content="seo.og_title || seo.title" />
        <meta property="og:description" :content="seo.og_description || seo.description" />
        <meta property="og:image" :content="seo.og_image" />
        <meta property="og:url" :content="seo.canonical" />
        <meta property="og:type" :content="seo.og_type || 'website'" />
        <meta name="twitter:card" :content="seo.twitter_card || 'summary_large_image'" />
        <component v-for="(schema, i) in schemas" :key="i" :is="'script'" type="application/ld+json" v-html="JSON.stringify(schema)" />
    </Head>

    <div class="relative flex-1 min-h-0 overflow-hidden">
        <div class="relative mx-auto flex min-h-0 max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-card dark:border-white/5 dark:bg-[#111827] sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
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
                        <Tooltip v-if="tool.avg_latency_ms" :content="t('Average generation time')" placement="bottom">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                <i class="ti ti-clock text-[13px] text-gray-600 dark:text-gray-200"></i>
                                {{ formatLatency(tool.avg_latency_ms) }}
                            </span>
                        </Tooltip>
                        <Tooltip v-if="tool.views_count" :content="t('Total views')" placement="bottom">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                <i class="ti ti-eye text-[13px] text-gray-600 dark:text-gray-200"></i>
                                {{ formatViews(tool.views_count) }}
                            </span>
                        </Tooltip>
                        <Tooltip :content="t('Average rating')" placement="bottom">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-gray-100 bg-gray-50 px-2.5 py-2 text-xs font-bold text-gray-700 shadow-sm transition-all dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                <i class="ti ti-star-filled text-[13px] text-gray-600 dark:text-gray-200"></i>
                                {{ (tool.avg_rating || 0).toFixed(1) }}
                            </span>
                        </Tooltip>
                        <Tooltip :content="tool.is_favorited ? t('Remove from favorites') : t('Add to favorites')" placement="bottom">
                            <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" show-count size="sm" />
                        </Tooltip>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div class="card relative overflow-hidden bg-white dark:bg-white/[0.03]">
                        <div class="relative flex flex-col gap-3">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-gray-200 bg-surface-50 text-primary-600 shadow-sm dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                                        <i :class="[tool.icon || 'ti ti-wand', 'text-[28px]']" :style="{ color: tool.color || '#1F75FE' }"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h1 class="font-heading text-[2rem] font-black tracking-tight text-gray-900 dark:text-white">{{ tool.name }}</h1>
                                            <Tooltip v-if="tool.category" :content="t('Tool category')" placement="bottom">
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-[11px] font-medium text-gray-500 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                                                    <i v-if="tool.category.icon" :class="tool.category.icon" class="text-[13px]"></i>
                                                    {{ tool.category.name }}
                                                </span>
                                            </Tooltip>
                                            <span
                                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-[0.18em] whitespace-nowrap shadow-sm"
                                                :class="accessBadgeClass"
                                            >
                                                {{ accessBadgeLabel }}
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

            <AdSection zone="tool_page_top" class="mx-auto mb-4 w-full max-w-7xl" />

            <div class="grid min-h-0 grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="min-h-0 lg:col-span-4">
                    <div class="card sticky top-6 flex h-full max-h-[calc(100vh-9rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white/90 dark:border-white/5 dark:bg-white/[0.03]">
                        <div class="shrink-0 border-b border-gray-100 px-6 py-4 dark:border-white/5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <i :class="['ti ti-sparkles-2', 'text-[20px]']" :style="{ color: tool.color || '#1F75FE' }"></i>
                                    <div class="min-w-0 flex-1">
                                        <h6 class="text-sm font-semibold text-gray-700 dark:text-white">{{ t('Prompt Parameters') }}</h6>
                                    </div>
                                </div>
                                <Tooltip :content="t('Try example')" placement="left">
                                    <button type="button" class="text-gray-400 transition-colors hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400" @click="regenerate">
                                        <i class="ti ti-refresh text-[18px]"></i>
                                    </button>
                                </Tooltip>
                            </div>
                        </div>
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
                                <div v-if="showCreditCosts && dynamicCredits" class="mb-4 rounded-xl border px-4 py-3 text-xs" :class="tool.access_level === 'public' && !authUser ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-primary-100 bg-primary-50 text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300'">
                                    <div class="flex items-center gap-2 font-medium">
                                        <i :class="tool.access_level === 'public' && !authUser ? 'ti ti-gift' : 'ti ti-receipt-2'" class="text-[14px]"></i>
                                        {{ tool.access_level === 'public' && !authUser ? t('Free preview') : t('Estimated cost') }}
                                    </div>
                                    <div v-if="tool.access_level !== 'public' || authUser" class="mt-1">
                                        <span class="font-semibold">~{{ dynamicCredits.estimated_credits }}</span> {{ t('credits') }}
                                        <span v-if="dynamicCredits.estimated_tokens"> · ~{{ dynamicCredits.estimated_tokens }} {{ t('tokens') }}</span>
                                    </div>
                                    <div v-else class="mt-1 text-emerald-600 dark:text-emerald-400">
                                        {{ t('No login or credits required. Output may be limited.') }}
                                    </div>
                                </div>
                                <div v-if="activeError" class="mb-4 flex items-start gap-2 rounded-xl border border-danger-500/20 bg-danger-500/10 px-4 py-3 text-sm text-danger-600 dark:text-danger-400">
                                    <i class="ti ti-alert-triangle mt-0.5 shrink-0"></i>
                                    <div>{{ activeError }}</div>
                                </div>
                            </DynamicForm>
                        </div>
                        <div class="shrink-0 border-t border-gray-100 bg-white/95 px-6 py-4 backdrop-blur-md dark:border-white/5 dark:bg-[#101418]/90">
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

                <div class="min-h-0 lg:col-span-8 flex flex-col gap-3">
                    <!-- Variation Tabs -->
                    <div v-if="isVariationsMode && (activeOutput || isAnyStreaming)" class="flex border-b border-gray-200 dark:border-white/5 gap-2 overflow-x-auto pb-px">
                        <button 
                            v-for="(stream, index) in variationStreams" 
                            :key="index" 
                            @click="activeVariationTab = index" 
                            :class="[activeVariationTab === index ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400 font-bold' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-medium']" 
                            class="px-4 py-2.5 text-xs transition-colors whitespace-nowrap flex items-center gap-1.5"
                        >
                            <span class="inline-flex h-2 w-2 rounded-full" :class="stream.isStreaming.value ? 'bg-primary-500 animate-pulse' : 'bg-gray-300 dark:bg-gray-600'"></span>
                            {{ t('Variation :num', { num: index + 1 }) }}
                        </button>
                    </div>

                    <div class="card flex-1 overflow-hidden rounded-2xl bg-white/90 dark:bg-white/[0.03]">
                        <OutputPanel :output="activeOutput" :reasoning="activeReasoning" :is-reasoning="activeIsReasoning" :output-type="tool.output_type || 'markdown'" :loading="activeIsStreaming" :usage="activeUsage" :saved-document="activeSavedDocument" :show-credit-costs="showCreditCosts" :can-save="Boolean(authUser)" :slug="tool.slug" :default-title="`${tool.name} Output`" @document-saved="handleDocumentSaved" />
                    </div>
                    <div v-if="activeOutput" class="flex flex-wrap gap-2">
                        <button type="button" :disabled="isAnyStreaming || !canGenerate" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 disabled:opacity-50" @click="regenerate">
                            <i class="ti ti-refresh text-[14px]"></i>
                            {{ t('Regenerate') }}
                        </button>
                        
                        <Tooltip v-if="(tool.max_variants ?? 0) > 1" :content="t('Generates 3 alternatives simultaneously using 3x credits')" placement="top">
                            <button type="button" :disabled="isAnyStreaming || !canGenerate" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 disabled:opacity-50" @click="generateVariations">
                                <svg v-if="isAnyStreaming && isVariationsMode" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <i v-else class="ti ti-layers-difference text-[14px]"></i>
                                {{ t('Get Variations') }}
                            </button>
                        </Tooltip>

                        <button type="button" :disabled="isAnyStreaming || !canGenerate" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 disabled:opacity-50" @click="isSidebarOpen = true">
                            <i class="ti ti-sparkles text-[14px]"></i>
                            {{ t('Improve') }}
                        </button>

                        <Link v-if="authUser && activeSavedDocument?.id" :href="activeSavedDocument?.id ? routeTo('documents.edit', activeSavedDocument.id) : '#'" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 no-underline">
                            <i class="ti ti-edit text-[14px]"></i>
                            {{ t('Edit in Editor') }}
                        </Link>
                    </div>

                    <div v-if="activeOutput" class="mt-3 pt-3 border-t border-gray-100 dark:border-white/5">
                        <SocialShare
                            :url="shareUrl"
                            :title="tool.name"
                            :style="'icon'"
                        />
                    </div>
                </div>
            </div>

            <div v-if="contentTabsVisible" class="mt-10 rounded-2xl border border-gray-200 bg-white/85 px-4 py-4 shadow-card backdrop-blur-md dark:border-white/5 dark:bg-white/[0.03] sm:px-6">
                <div class="mb-4 flex gap-2 border-b border-gray-200 overflow-x-auto pb-px dark:border-white/5">
                    <button v-if="hasAbout" @click="activeTab = 'about'" :class="[activeTab === 'about' ? 'border-b-primary-500 text-primary-600 dark:text-primary-400' : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']" class="border-b-2 px-4 py-3 text-sm font-semibold transition-colors whitespace-nowrap">{{ t('About') }}</button>
                    <button v-if="hasHowItWorks" @click="activeTab = 'how'" :class="[activeTab === 'how' ? 'border-b-primary-500 text-primary-600 dark:text-primary-400' : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']" class="border-b-2 px-4 py-3 text-sm font-semibold transition-colors whitespace-nowrap">{{ t('How It Works') }}</button>
                    <button v-if="hasUsageExamples" @click="activeTab = 'examples'" :class="[activeTab === 'examples' ? 'border-b-primary-500 text-primary-600 dark:text-primary-400' : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']" class="border-b-2 px-4 py-3 text-sm font-semibold transition-colors whitespace-nowrap">{{ t('Examples') }}</button>
                    <button v-if="hasFaqs" @click="activeTab = 'faqs'" :class="[activeTab === 'faqs' ? 'border-b-primary-500 text-primary-600 dark:text-primary-400' : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']" class="border-b-2 px-4 py-3 text-sm font-semibold transition-colors whitespace-nowrap">{{ t('FAQs') }}</button>
                    <button v-if="tool.show_reviews" @click="activeTab = 'reviews'" :class="[activeTab === 'reviews' ? 'border-b-primary-500 text-primary-600 dark:text-primary-400' : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']" class="border-b-2 px-4 py-3 text-sm font-semibold transition-colors whitespace-nowrap">{{ t('Reviews') }}</button>
                    <button v-if="tool.show_related_tools && relatedTools.length" @click="activeTab = 'related'" :class="[activeTab === 'related' ? 'border-b-primary-500 text-primary-600 dark:text-primary-400' : 'border-b-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']" class="border-b-2 px-4 py-3 text-sm font-semibold transition-colors whitespace-nowrap">{{ t('Related') }}</button>
                </div>

                <section v-if="activeTab === 'about' && hasAbout" id="about" class="prose dark:prose-invert prose-sm max-w-none text-gray-700 dark:text-gray-300 pb-8" v-html="tool.about_content"></section>

                <div v-if="activeTab === 'how' && hasHowItWorks" class="grid grid-cols-1 gap-6 pb-8 md:grid-cols-3">
                    <div v-for="(step, index) in howItWorks" :key="index" class="relative rounded-2xl border border-gray-200 bg-gray-50 p-6 dark:border-white/5 dark:bg-white/[0.02]">
                        <div class="absolute -top-3 -left-3 flex h-8 w-8 items-center justify-center rounded-full border border-primary-500/30 bg-primary-500/20 text-sm font-bold text-primary-600 dark:text-primary-400">
                            {{ step.step || index + 1 }}
                        </div>
                        <i :class="[step.icon || 'ti-check', 'mb-4 block text-2xl']" :style="{ color: tool.color || '#10b981' }"></i>
                        <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">{{ step.title }}</h4>
                        <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-500">{{ step.description }}</p>
                    </div>
                </div>

                <div v-if="activeTab === 'examples' && hasUsageExamples" class="space-y-6 pb-8">
                    <div v-for="(example, index) in usageExamples" :key="index" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/5 dark:bg-white/[0.02] dark:shadow-none">
                        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-white/5 dark:bg-white/5">
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ example.title }}</h4>
                            <button @click="applyExample(example)" class="rounded-lg border border-primary-500/20 bg-primary-500/10 px-3 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-primary-500/20 dark:border-primary-500/30 dark:bg-primary-500/20 dark:text-primary-400 dark:hover:bg-primary-500/30">{{ t('Try this example') }}</button>
                        </div>
                        <div class="grid grid-cols-1 divide-y divide-gray-200 md:grid-cols-2 md:divide-x md:divide-y-0 dark:divide-white/5">
                            <div class="p-6">
                                <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Input Data') }}</div>
                                <div class="space-y-2">
                                    <div v-for="(value, key) in example.input" :key="key" class="flex gap-2">
                                        <span class="min-w-[100px] text-sm font-medium text-gray-500 dark:text-gray-400">{{ key }}:</span>
                                        <span class="break-words text-sm text-gray-700 dark:text-gray-300">{{ value }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50/50 p-6 dark:bg-white/[0.01]">
                                <div class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Generated Output') }}</div>
                                <div class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ truncatedOutput(example.output, Number(index)) }}</div>
                                <button v-if="exampleOutput(example.output).length > 200" type="button" class="mt-3 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" @click="expandedExamples[Number(index)] = !expandedExamples[Number(index)]">{{ expandedExamples[Number(index)] ? t('Show less') : t('See full output') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'faqs' && hasFaqs" class="max-w-4xl space-y-4 pb-8">
                    <details v-for="(faq, index) in faqItems" :key="index" class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/5 dark:bg-white/[0.02] dark:shadow-none">
                        <summary class="list-none flex cursor-pointer items-center justify-between px-6 py-4 font-medium text-gray-900 group-open:bg-gray-50 dark:text-white dark:group-open:bg-white/[0.03]">
                            {{ faq.question }}
                            <i class="ti ti-chevron-down text-gray-400 transition-transform dark:text-gray-500 group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-gray-200 bg-gray-50/50 px-6 py-4 text-sm leading-relaxed text-gray-600 dark:border-white/5 dark:bg-white/[0.01] dark:text-gray-400" v-html="faq.answer"></div>
                    </details>
                </div>

                <div v-if="activeTab === 'reviews'" class="max-w-4xl space-y-6 pb-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('User Reviews') }}</h3>
                            <p class="text-sm text-gray-500">{{ tool.avg_rating || 0 }}/5 {{ t('from') }} {{ tool.review_count || 0 }} {{ t('reviews') }}</p>
                        </div>
                        <div class="flex items-center gap-2"><AppSelect v-model="reviewSort" :options="sortOptions" @update:model-value="changeReviewSort" /></div>
                        <div v-if="authUser && canReview" class="flex items-center gap-2"><AppSelect v-model="reviewRating" :options="ratingOptions" /></div>
                    </div>
                    <div class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-white/5 dark:bg-white/[0.02]">
                        <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="grid grid-cols-[52px_1fr_42px] items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ rating }} {{ t('star') }}</span>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"><div class="h-full rounded-full bg-warning-400" :style="{ width: `${reviewStats.distribution?.[rating]?.percent || 0}%` }"></div></div>
                            <span class="text-end">{{ reviewStats.distribution?.[rating]?.percent || 0 }}%</span>
                        </div>
                    </div>
                    <form v-if="authUser && canReview" class="space-y-3" @submit.prevent="submitReview">
                        <textarea v-model="reviewComment" rows="3" maxlength="2000" class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 transition-all placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-white/10 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-600" :placeholder="t('Share your experience with this tool')"></textarea>
                        <div class="flex items-center gap-3">
                            <button type="submit" :disabled="reviewSubmitting" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-50">{{ reviewSubmitting ? t('Submitting...') : t('Write Review') }}</button>
                            <span v-if="reviewMessage" class="text-xs text-gray-500 dark:text-gray-400">{{ reviewMessage }}</span>
                        </div>
                    </form>
                    <div v-else-if="authUser" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-400">{{ t('Generate with this tool once to unlock review writing.') }}</div>
                    <div v-if="reviews && reviews.length" class="space-y-4">
                        <div v-for="review in reviews" :key="review.id" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-white/[0.02] dark:shadow-none">
                            <div class="mb-3 flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-gray-200 font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                        <img v-if="review.user?.avatar" :src="'/storage/' + review.user.avatar" class="h-full w-full object-cover" />
                                        <span v-else>{{ review.user?.name?.charAt(0) || 'U' }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ review.user?.name || 'Anonymous' }}</div>
                                        <div class="flex items-center gap-1 text-xs text-warning-400">
                                            <i v-for="star in 5" :key="star" :class="star <= review.rating ? 'ti-star text-warning-400' : 'ti-star text-gray-300 dark:text-gray-600'"></i>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="text-xs text-gray-500 hover:text-primary-600 dark:hover:text-primary-400" @click="voteReview(review, true)">{{ review.helpful_count || 0 }} {{ t('helpful') }}</button>
                            </div>
                            <p class="text-sm whitespace-pre-wrap text-gray-600 dark:text-gray-400">{{ review.comment }}</p>
                            <p v-if="review.admin_reply" class="mt-3 rounded-xl border border-primary-500/20 bg-primary-500/10 px-4 py-3 text-sm text-primary-700 dark:text-primary-100">{{ review.admin_reply }}</p>
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

                <div v-if="activeTab === 'related'" class="grid grid-cols-1 gap-4 pb-8 md:grid-cols-3">
                    <Link v-for="tool in relatedTools" :key="tool.slug" :href="routeTo('ai.tools.show', tool.slug)" class="block rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-colors hover:border-primary-500/30 dark:border-white/5 dark:bg-white/[0.02] dark:shadow-none">
                        <i :class="[tool.icon || 'ti ti-wand', 'mb-3 block text-2xl text-primary-600 dark:text-primary-400']"></i>
                        <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">{{ tool.name }}</h4>
                        <p class="line-clamp-3 text-sm text-gray-500">{{ tool.description }}</p>
                    </Link>
                </div>
            </div>
        </div>

        <AdSection zone="tool_page_bottom" class="mx-auto mb-4 w-full max-w-7xl" />
    </div>

    <LoginPromptModal :open="showLoginModal" @close="showLoginModal = false" />
    <UpgradeModal :open="showUpgradeModal" @close="showUpgradeModal = false" />

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
                class="fixed inset-0 z-50 flex justify-end bg-black/45 backdrop-blur-sm"
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
