<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'
import DynamicForm, { type ToolField } from '@/Components/AI/DynamicForm.vue'
import OutputPanel from '@/Components/AI/OutputPanel.vue'
import FavoriteButton from '@/Components/FavoriteButton.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'
import { useStream } from '@/Composables/useStream'

defineOptions({ layout: UserLayout })

interface ToolData {
    id: number
    name: string
    slug: string
    description: string
    category?: { name: string; slug: string; icon?: string; color?: string }
    icon?: string
    color?: string
    output_type?: string
    requires_pro?: boolean
    access_level?: string
    fields: ToolField[] | string | Record<string, ToolField>
    about_content?: string
    how_it_works?: Array<{ step?: number; icon?: string; title: string; description: string }> | string | Record<string, unknown>
    usage_examples?: Array<{ title: string; input: Record<string, unknown>; output: string }> | string | Record<string, unknown>
    faq_items?: Array<{ question: string; answer: string }> | string | Record<string, unknown>
    avg_rating?: number
    review_count?: number
    show_about: boolean
    show_how_it_works: boolean
    show_usage_examples: boolean
    show_faqs: boolean
    show_reviews: boolean
    show_related_tools?: boolean
    favorites_count?: number
    is_favorited?: boolean
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
    authUser: { id: number; name: string; credits: string } | null
    canReview: boolean
}>()

const formValues = ref<Record<string, unknown>>({})
const activeTab = ref('about')
const reviewRating = ref(5)
const reviewComment = ref('')
const reviewMessage = ref('')
const reviewSubmitting = ref(false)
const reviewSort = ref('helpful')
const reviews = ref<Array<any>>([...props.reviews])
const reviewsPage = ref(props.reviewsPagination?.current_page || 1)
const reviewsLastPage = ref(props.reviewsPagination?.last_page || 1)
const reviewsLoading = ref(false)
const expandedExamples = ref<Record<number, boolean>>({})
const reviewStats = ref(props.reviewStats)

const { output, isStreaming, error, usage, savedDocument, generate } = useStream()
const { t } = useTranslate()
const toast = useToastr()

const routeTo = (name: string, params?: unknown): string => route(name, params)

const fields = computed<ToolField[]>(() => {
    if (!props.tool.fields) return []
    if (typeof props.tool.fields === 'string') {
        try {
            return JSON.parse(props.tool.fields)
        } catch {
            return []
        }
    }
    return Array.isArray(props.tool.fields) ? props.tool.fields : Object.values(props.tool.fields)
})

const normalizeArray = <T,>(value: unknown): T[] => {
    if (!value) return []
    if (Array.isArray(value)) return value as T[]

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value)
            if (Array.isArray(parsed)) return parsed as T[]
            if (parsed && typeof parsed === 'object') return Object.values(parsed) as T[]
        } catch {
            return []
        }
    }

    if (typeof value === 'object') return Object.values(value as Record<string, T>)

    return []
}

const howItWorks = computed(() => normalizeArray<{ step?: number; icon?: string; title?: string; description?: string }>(props.tool.how_it_works))
const usageExamples = computed(() => normalizeArray<{ title?: string; input?: Record<string, unknown>; output?: unknown }>(props.tool.usage_examples))
const faqItems = computed(() => normalizeArray<{ question?: string; answer?: string }>(props.tool.faq_items))

const fieldName = (field: ToolField): string => field.name || field.key || field.id || ''

const canSubmit = computed(() => {
    if (isStreaming.value) return false

    return fields.value
        .filter((field) => field.required)
        .every((field) => {
            const value = formValues.value[fieldName(field)]
            return value !== null && value !== undefined && String(value).trim() !== ''
        })
})

const contentTabsVisible = computed(() => (
    hasAbout.value
    || hasHowItWorks.value
    || hasUsageExamples.value
    || hasFaqs.value
    || props.tool.show_reviews
    || (props.tool.show_related_tools && props.relatedTools.length)
))

const hasAbout = computed(() => props.tool.show_about && String(props.tool.about_content || '').trim() !== '')
const hasHowItWorks = computed(() => props.tool.show_how_it_works && howItWorks.value.some((step) => (
    String(step.title || '').trim() !== '' || String(step.description || '').trim() !== ''
)))
const hasUsageExamples = computed(() => props.tool.show_usage_examples && usageExamples.value.some((example) => (
    String(example.title || '').trim() !== ''
    || Object.keys(example.input || {}).length > 0
    || exampleOutput(example.output).trim() !== ''
)))
const hasFaqs = computed(() => props.tool.show_faqs && faqItems.value.some((faq) => (
    String(faq.question || '').trim() !== '' || String(faq.answer || '').trim() !== ''
)))

onMounted(() => {
    for (const field of fields.value) {
        const name = fieldName(field)
        formValues.value[name] = typeof field.default === 'boolean' ? field.default : (field.default ?? '')
    }

    if (hasAbout.value) activeTab.value = 'about'
    else if (hasHowItWorks.value) activeTab.value = 'how'
    else if (hasUsageExamples.value) activeTab.value = 'examples'
    else if (hasFaqs.value) activeTab.value = 'faqs'
    else if (props.tool.show_reviews) activeTab.value = 'reviews'
    else if (props.relatedTools.length) activeTab.value = 'related'
})

const runGenerate = () => {
    if (!canSubmit.value) return
    generate({
        slug: props.tool.slug,
        fields: formValues.value,
        model: String(formValues.value.model || ''),
    })
}

const regenerate = () => runGenerate()

const handleDocumentSaved = (document: Record<string, unknown>) => {
    savedDocument.value = document
}

const applyExample = (example: { input?: Record<string, unknown> }) => {
    formValues.value = { ...formValues.value, ...(example.input || {}) }
}

const exampleOutput = (output: unknown): string => String(output ?? '')

const truncatedOutput = (output: unknown, index: number): string => {
    const text = exampleOutput(output)
    if (expandedExamples.value[index] || text.length <= 200) return text

    return `${text.slice(0, 200)}...`
}

const fetchReviews = async (page = 1, append = false) => {
    reviewsLoading.value = true

    try {
        const response = await fetch(`/api/v1/tools/${props.tool.slug}/reviews?sort=${reviewSort.value}&page=${page}`, {
            headers: { Accept: 'application/json' },
        })
        const data = await response.json()
        if (!response.ok) throw new Error(data.message || t('Reviews could not be loaded.'))

        reviews.value = append ? [...reviews.value, ...data.data.data] : data.data.data
        reviewsPage.value = data.data.current_page
        reviewsLastPage.value = data.data.last_page
        reviewStats.value = data.meta || reviewStats.value
    } catch (err) {
        toast.error(err instanceof Error ? err.message : t('Reviews could not be loaded.'))
    } finally {
        reviewsLoading.value = false
    }
}

const changeReviewSort = () => {
    fetchReviews(1, false)
}

const loadMoreReviews = () => {
    if (reviewsPage.value >= reviewsLastPage.value) return
    fetchReviews(reviewsPage.value + 1, true)
}

const voteReview = async (review: any, isHelpful: boolean) => {
    if (!props.authUser) {
        toast.warning(t('Please sign in to vote on reviews.'))
        return
    }

    try {
        const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
        const response = await fetch(`/api/v1/tools/reviews/${review.id}/vote`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.pop() || '') : '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ is_helpful: isHelpful }),
        })

        const data = await response.json()
        if (!response.ok) throw new Error(data.message || t('Vote could not be recorded.'))
        review.helpful_count = data.data?.helpful_count ?? review.helpful_count
        toast.success(data.message || t('Vote recorded.'))
    } catch (err) {
        toast.error(err instanceof Error ? err.message : t('Vote could not be recorded.'))
    }
}

const submitReview = async () => {
    reviewSubmitting.value = true
    reviewMessage.value = ''

    try {
        const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
        const response = await fetch(`/api/v1/tools/${props.tool.slug}/reviews`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.pop() || '') : '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ rating: reviewRating.value, comment: reviewComment.value }),
        })

        const data = await response.json()
        if (!response.ok) throw new Error(data.message || t('Review could not be submitted.'))
        reviewMessage.value = data.message || t('Review submitted.')
        reviewComment.value = ''
        toast.success(reviewMessage.value)
    } catch (err) {
        reviewMessage.value = err instanceof Error ? err.message : t('Review could not be submitted.')
        toast.error(reviewMessage.value)
    } finally {
        reviewSubmitting.value = false
    }
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex items-center gap-2 mb-6 text-sm">
            <Link :href="routeTo('ai.tools.index')" class="text-gray-500 hover:text-primary-400 transition-colors">AI Tools</Link>
            <i class="ti-chevron-right text-gray-600 text-xs"></i>
            <Link v-if="tool.category" :href="routeTo('ai.tools.category', tool.category.slug)" class="text-gray-500 hover:text-primary-400 transition-colors">
                {{ tool.category.name }}
            </Link>
            <i v-if="tool.category" class="ti-chevron-right text-gray-600 text-xs"></i>
            <span class="text-gray-300">{{ tool.name }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="lg:col-span-4 space-y-5">
                <div class="bg-white/[0.03] border border-white/5 rounded-2xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                        <i :class="[tool.icon || 'ti-wand', 'text-8xl']" :style="{ color: tool.color || '#10b981' }"></i>
                    </div>

                    <div class="mb-6 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center border shrink-0" :style="{ background: (tool.color || '#10b981') + '15', borderColor: (tool.color || '#10b981') + '30' }">
                            <i :class="[tool.icon || 'ti-wand', 'text-xl']" :style="{ color: tool.color || '#10b981' }"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-lg font-bold text-white">{{ tool.name }}</h1>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <span v-if="tool.category" class="text-xs text-gray-400 inline-flex items-center gap-1">
                                    <i v-if="tool.category.icon" :class="tool.category.icon"></i>
                                    {{ tool.category.name }}
                                </span>
                                <span v-if="tool.requires_pro" class="px-1.5 py-0.5 bg-accent-500/10 text-accent-400 text-[10px] font-bold uppercase rounded border border-accent-500/20">PRO</span>
                                <span v-else class="px-1.5 py-0.5 bg-primary-500/10 text-primary-300 text-[10px] font-bold uppercase rounded border border-primary-500/20">Free</span>
                            </div>
                        </div>
                        <FavoriteButton
                            model-type="ai_templates"
                            :model-id="tool.id"
                            :is-favorited="Boolean(tool.is_favorited)"
                            :count="tool.favorites_count"
                            show-count
                            size="sm"
                        />
                    </div>

                    <p class="text-sm text-gray-400 mb-6 leading-relaxed">{{ tool.description }}</p>

                    <DynamicForm
                        v-model="formValues"
                        :fields="fields"
                        :languages="languages"
                        :models="models"
                        :disabled="isStreaming"
                        @submit="runGenerate"
                    >
                        <div v-if="showCreditCosts && estimatedCredits" class="rounded-xl border border-white/5 bg-white/[0.02] px-4 py-3 text-xs text-gray-400">
                            Estimated cost: <span class="text-gray-200">~{{ estimatedCredits.estimated_credits }}</span> credits
                            <span v-if="estimatedCredits.estimated_tokens"> · Based on ~{{ estimatedCredits.estimated_tokens }} output tokens</span>
                            <span v-if="authUser"> · Balance: <span class="text-gray-200">{{ authUser.credits }}</span></span>
                        </div>

                        <div v-if="error" class="px-4 py-3 bg-danger-500/10 border border-danger-500/20 rounded-xl text-sm text-danger-400 flex items-start gap-2">
                            <i class="ti-alert-triangle mt-0.5"></i>
                            <div>{{ error }}</div>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            class="w-full py-3 mt-2 bg-gradient-to-r from-primary-600 to-accent-600 text-white rounded-xl font-semibold text-sm shadow-lg shadow-primary-500/25 hover:shadow-primary-500/35 hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:translate-y-0 flex items-center justify-center gap-2"
                        >
                            <svg v-if="isStreaming" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            <i v-else class="ti-wand"></i>
                            {{ isStreaming ? 'Generating...' : 'Generate Content' }}
                        </button>
                    </DynamicForm>
                </div>
            </div>

            <div class="lg:col-span-8">
                <OutputPanel
                    :output="output"
                    :output-type="tool.output_type || 'markdown'"
                    :loading="isStreaming"
                    :usage="usage"
                    :saved-document="savedDocument"
                    :show-credit-costs="showCreditCosts"
                    :can-save="Boolean(authUser)"
                    :slug="tool.slug"
                    :default-title="`${tool.name} Output`"
                    @document-saved="handleDocumentSaved"
                />

                <div v-if="output" class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="px-3 py-2 text-xs font-medium text-gray-300 bg-white/5 hover:bg-white/10 rounded-lg border border-white/5" @click="regenerate">
                        <i class="ti-refresh"></i> Regenerate
                    </button>
                    <Link v-if="authUser && savedDocument?.id" :href="routeTo('documents.edit', savedDocument.id)" class="px-3 py-2 text-xs font-medium text-gray-300 bg-white/5 hover:bg-white/10 rounded-lg border border-white/5">
                        <i class="ti-edit"></i> Edit in Editor
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="contentTabsVisible" class="border-t border-white/5 pt-10">
            <div class="flex gap-4 border-b border-white/5 mb-8 overflow-x-auto pb-px scrollbar-hide">
                <button v-if="hasAbout" @click="activeTab = 'about'" :class="[activeTab === 'about' ? 'border-primary-500 text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-700']" class="px-1 py-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap">{{ t('About') }}</button>
                <button v-if="hasHowItWorks" @click="activeTab = 'how'" :class="[activeTab === 'how' ? 'border-primary-500 text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-700']" class="px-1 py-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap">{{ t('How It Works') }}</button>
                <button v-if="hasUsageExamples" @click="activeTab = 'examples'" :class="[activeTab === 'examples' ? 'border-primary-500 text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-700']" class="px-1 py-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap">{{ t('Examples') }}</button>
                <button v-if="hasFaqs" @click="activeTab = 'faqs'" :class="[activeTab === 'faqs' ? 'border-primary-500 text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-700']" class="px-1 py-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap">{{ t('FAQs') }}</button>
                <button v-if="tool.show_reviews" @click="activeTab = 'reviews'" :class="[activeTab === 'reviews' ? 'border-primary-500 text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-700']" class="px-1 py-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap">{{ t('Reviews') }}</button>
                <button v-if="tool.show_related_tools && relatedTools.length" @click="activeTab = 'related'" :class="[activeTab === 'related' ? 'border-primary-500 text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-300 hover:border-gray-700']" class="px-1 py-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap">{{ t('Related') }}</button>
            </div>

            <div>
                <section v-if="activeTab === 'about' && hasAbout" id="about" class="prose prose-invert prose-sm max-w-none text-gray-300" v-html="tool.about_content"></section>

                <div v-if="activeTab === 'how' && hasHowItWorks" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div v-for="(step, index) in howItWorks" :key="index" class="bg-white/[0.02] border border-white/5 rounded-2xl p-6 relative">
                        <div class="absolute -top-4 -left-4 w-8 h-8 rounded-full bg-primary-500/20 text-primary-400 flex items-center justify-center font-bold text-sm border border-primary-500/30">
                            {{ step.step || index + 1 }}
                        </div>
                        <i :class="[step.icon || 'ti-check', 'text-2xl mb-4 block']" :style="{ color: tool.color || '#10b981' }"></i>
                        <h4 class="text-white font-semibold mb-2">{{ step.title }}</h4>
                        <p class="text-gray-500 text-sm leading-relaxed">{{ step.description }}</p>
                    </div>
                </div>

                <div v-if="activeTab === 'examples' && hasUsageExamples" class="space-y-6">
                    <div v-for="(example, index) in usageExamples" :key="index" class="bg-white/[0.02] border border-white/5 rounded-2xl overflow-hidden">
                        <div class="bg-white/5 px-6 py-4 flex items-center justify-between border-b border-white/5">
                            <h4 class="font-medium text-white">{{ example.title }}</h4>
                            <button @click="applyExample(example)" class="text-xs font-medium bg-primary-500/20 text-primary-400 hover:bg-primary-500/30 px-3 py-1.5 rounded-lg transition-colors border border-primary-500/30">
                                {{ t('Try this example') }}
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-white/5">
                            <div class="p-6">
                                <div class="text-xs text-gray-500 font-medium mb-3 uppercase tracking-wider">{{ t('Input Data') }}</div>
                                <div class="space-y-2">
                                    <div v-for="(value, key) in example.input" :key="key" class="flex gap-2">
                                        <span class="text-gray-400 text-sm font-medium min-w-[100px]">{{ key }}:</span>
                                        <span class="text-gray-300 text-sm break-words">{{ value }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6 bg-white/[0.01]">
                                <div class="text-xs text-gray-500 font-medium mb-3 uppercase tracking-wider">{{ t('Generated Output') }}</div>
                                <div class="text-sm text-gray-300 leading-relaxed whitespace-pre-wrap">{{ truncatedOutput(example.output, Number(index)) }}</div>
                                <button v-if="exampleOutput(example.output).length > 200" type="button" class="mt-3 text-xs font-medium text-primary-400 hover:text-primary-300" @click="expandedExamples[Number(index)] = !expandedExamples[Number(index)]">
                                    {{ expandedExamples[Number(index)] ? t('Show less') : t('See full output') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'faqs' && hasFaqs" class="space-y-4 max-w-4xl">
                    <details v-for="(faq, index) in faqItems" :key="index" class="group bg-white/[0.02] border border-white/5 rounded-2xl overflow-hidden">
                        <summary class="px-6 py-4 text-white font-medium cursor-pointer list-none flex items-center justify-between group-open:bg-white/[0.03]">
                            {{ faq.question }}
                            <i class="ti-chevron-down text-gray-500 group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <div class="px-6 py-4 text-gray-400 text-sm leading-relaxed border-t border-white/5 bg-white/[0.01]" v-html="faq.answer"></div>
                    </details>
                </div>

                <div v-if="activeTab === 'reviews'" class="max-w-4xl space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ t('User Reviews') }}</h3>
                            <p class="text-gray-500 text-sm">{{ tool.avg_rating || 0 }}/5 {{ t('from') }} {{ tool.review_count || 0 }} {{ t('reviews') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="reviewSort" class="px-3 py-2 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white" @change="changeReviewSort">
                                <option value="helpful" class="bg-surface-900">{{ t('Most Helpful') }}</option>
                                <option value="recent" class="bg-surface-900">{{ t('Most Recent') }}</option>
                                <option value="highest" class="bg-surface-900">{{ t('Highest Rated') }}</option>
                                <option value="lowest" class="bg-surface-900">{{ t('Lowest Rated') }}</option>
                            </select>
                        </div>
                        <div v-if="authUser && canReview" class="flex items-center gap-2">
                            <select v-model="reviewRating" class="px-3 py-2 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white">
                                <option v-for="rating in 5" :key="rating" :value="rating" class="bg-surface-900">{{ rating }} {{ t('stars') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-2xl border border-white/5 bg-white/[0.02] p-5">
                        <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="grid grid-cols-[52px_1fr_42px] items-center gap-3 text-xs text-gray-400">
                            <span>{{ rating }} {{ t('star') }}</span>
                            <div class="h-2 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-warning-400" :style="{ width: `${reviewStats.distribution?.[rating]?.percent || 0}%` }"></div>
                            </div>
                            <span class="text-end">{{ reviewStats.distribution?.[rating]?.percent || 0 }}%</span>
                        </div>
                    </div>

                    <form v-if="authUser && canReview" class="space-y-3" @submit.prevent="submitReview">
                        <textarea v-model="reviewComment" rows="3" maxlength="2000" class="w-full px-4 py-3 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white placeholder-gray-600" :placeholder="t('Share your experience with this tool')"></textarea>
                        <div class="flex items-center gap-3">
                            <button type="submit" :disabled="reviewSubmitting" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-sm font-semibold disabled:opacity-50">
                                {{ reviewSubmitting ? t('Submitting...') : t('Write Review') }}
                            </button>
                            <span v-if="reviewMessage" class="text-xs text-gray-400">{{ reviewMessage }}</span>
                        </div>
                    </form>
                    <div v-else-if="authUser" class="rounded-xl border border-white/5 bg-white/[0.02] px-4 py-3 text-sm text-gray-400">
                        {{ t('Generate with this tool once to unlock review writing.') }}
                    </div>

                    <div v-if="reviews && reviews.length" class="space-y-4">
                        <div v-for="review in reviews" :key="review.id" class="bg-white/[0.02] border border-white/5 rounded-2xl p-6">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 font-bold overflow-hidden">
                                        <img v-if="review.user?.avatar" :src="'/storage/' + review.user.avatar" class="w-full h-full object-cover" />
                                        <span v-else>{{ review.user?.name?.charAt(0) || 'U' }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-200">{{ review.user?.name || 'Anonymous' }}</div>
                                        <div class="flex items-center gap-1 text-warning-400 text-xs">
                                            <i v-for="star in 5" :key="star" :class="star <= review.rating ? 'ti-star text-warning-400' : 'ti-star text-gray-600'"></i>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="text-xs text-gray-500 hover:text-primary-400" @click="voteReview(review, true)">
                                    {{ review.helpful_count || 0 }} {{ t('helpful') }}
                                </button>
                            </div>
                            <p class="text-gray-400 text-sm whitespace-pre-wrap">{{ review.comment }}</p>
                            <p v-if="review.admin_reply" class="mt-3 rounded-xl bg-primary-500/10 border border-primary-500/20 px-4 py-3 text-sm text-primary-100">{{ review.admin_reply }}</p>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-white/[0.02] border border-white/5 rounded-2xl">
                        <i class="ti-star text-4xl text-gray-600 mb-3 block"></i>
                        <p class="text-gray-400">{{ t('No reviews yet for this tool.') }}</p>
                    </div>
                    <div v-if="reviewsPage < reviewsLastPage" class="text-center">
                        <button type="button" :disabled="reviewsLoading" class="rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2 text-sm font-semibold text-gray-200 hover:border-primary-500/40 disabled:opacity-50" @click="loadMoreReviews">
                            {{ reviewsLoading ? t('Loading...') : t('Load more reviews') }}
                        </button>
                    </div>
                </div>

                <div v-if="activeTab === 'related'" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Link v-for="tool in relatedTools" :key="tool.slug" :href="routeTo('ai.tools.show', tool.slug)" class="block bg-white/[0.02] border border-white/5 hover:border-primary-500/30 rounded-2xl p-5 transition-colors">
                        <i :class="[tool.icon || 'ti-wand', 'text-2xl text-primary-400 mb-3 block']"></i>
                        <h4 class="text-white font-semibold mb-2">{{ tool.name }}</h4>
                        <p class="text-sm text-gray-500 line-clamp-3">{{ tool.description }}</p>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
