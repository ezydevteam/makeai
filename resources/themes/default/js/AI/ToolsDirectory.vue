<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import type { Swiper as SwiperClass } from 'swiper'
import { Navigation, Pagination, A11y } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import AppSelect from '@/Components/UI/AppSelect.vue'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import AdSection from '@themes/default/js/Components/AdSection.vue'
import AppPagination from '@/Components/UI/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

interface Category {
    id: number
    name: string
    slug: string
    icon: string
    color: string
    access_level: string
    active_tools_count?: number
}

interface Tool {
    id: number
    name: string
    slug: string
    description: string
    category_id: number | null
    category?: Category
    icon: string
    color: string
    is_featured: boolean
    access_level: string
    tags?: string[]
    views_count?: number
}

const props = defineProps<{
    tools: Tool[]
    categories: Category[]
    featured: Tool[]
    recentlyUsed: Tool[]
    initialCategory?: number | string
    // Only title_page is read here; the rest of the seo payload is consumed by
    // app.blade.php's server-rendered head.
    seo?: { title_page?: string }
}>()

const { t } = useTranslate()
const activeCategory = ref<number | string>(props.initialCategory || 'all')
const search = ref('')
const activeTag = ref<string | null>(null)
const viewMode = ref<'grid' | 'list'>((localStorage.getItem('tools_view_mode') as 'grid' | 'list') || 'grid')
const setViewMode = (mode: 'grid' | 'list') => {
    viewMode.value = mode
    localStorage.setItem('tools_view_mode', mode)
}
const featuredModules = [Navigation, Pagination, A11y]
const featuredSwiper = ref<SwiperClass | null>(null)
const featuredAtStart = ref(true)
const featuredAtEnd = ref(false)

const recentSwiper = ref<SwiperClass | null>(null)
const recentAtStart = ref(true)
const recentAtEnd = ref(false)

function slideRecent(direction: 'prev' | 'next') {
    const swiper = recentSwiper.value
    if (!swiper) {
        return
    }

    if (direction === 'prev') {
        swiper.slidePrev()
        return
    }

    swiper.slideNext()
}

function onRecentSwiper(swiper: SwiperClass) {
    recentSwiper.value = swiper
    recentAtStart.value = swiper.isBeginning
    recentAtEnd.value = swiper.isEnd
}

function updateRecentBounds(swiper: SwiperClass) {
    recentAtStart.value = swiper.isBeginning
    recentAtEnd.value = swiper.isEnd
}
const categoryOptions = computed(() => [
    { value: 'all', label: t('All categories') },
    ...props.categories.map(category => ({
        value: category.id,
        label: category.name,
        icon: category.icon || undefined,
    })),
])

const allTags = computed(() => {
    const tagSet = new Set<string>()
    props.tools.forEach(tool => {
        if (Array.isArray(tool.tags)) {
            tool.tags.forEach(tag => tagSet.add(tag))
        }
    })
    return Array.from(tagSet).sort()
})

const tagOptions = computed(() => [
    { value: '', label: t('All tags') },
    ...allTags.value.map(tag => ({ value: tag, label: tag })),
])

function slideFeatured(direction: 'prev' | 'next') {
    const swiper = featuredSwiper.value
    if (!swiper) {
        return
    }

    if (direction === 'prev') {
        swiper.slidePrev()
        return
    }

    swiper.slideNext()
}

function formatViews(views: number): string {
    if (views >= 1000000) {
        return (views / 1000000).toFixed(1) + 'M'
    }
    if (views >= 1000) {
        return (views / 1000).toFixed(1) + 'K'
    }
    return views.toString()
}

function isProTool(tool: Tool): boolean {
    const level = tool.access_level || 'inherit'
    if (level === 'premium' || level.startsWith('plan:')) return true
    if (level === 'inherit' && tool.category?.access_level) {
        const catLevel = tool.category.access_level
        return catLevel === 'premium' || catLevel.startsWith('plan:')
    }
    return false
}

function onFeaturedSwiper(swiper: SwiperClass) {
    featuredSwiper.value = swiper
    featuredAtStart.value = swiper.isBeginning
    featuredAtEnd.value = swiper.isEnd
}

function updateFeaturedBounds(swiper: SwiperClass) {
    featuredAtStart.value = swiper.isBeginning
    featuredAtEnd.value = swiper.isEnd
}

const filtered = computed(() => {
    let list = props.tools
    if (activeCategory.value !== 'all') {
        list = list.filter(t => t.category_id == activeCategory.value || t.category?.slug === activeCategory.value)
    }
    if (activeTag.value) {
        list = list.filter(t => t.tags?.includes(activeTag.value!))
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        list = list.filter(t => t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q))
    }
    return list
})

const page = usePage()
const settings = computed(() => (page.props as any).appearanceToolPageSettings ?? {})

const itemsPerPage = ref(16)
const currentPage = ref(1)
const isPageLoading = ref(false)

function loadMore() {
    if (isPageLoading.value) return
    isPageLoading.value = true
    setTimeout(() => {
        currentPage.value++
        isPageLoading.value = false
    }, 600)
}

const paginatedTools = computed(() => {
    if (settings.value.archive_pagination === 'none') {
        return filtered.value
    }
    if (settings.value.archive_pagination === 'load_more') {
        return filtered.value.slice(0, currentPage.value * itemsPerPage.value)
    }
    // 'numbered'
    const start = (currentPage.value - 1) * itemsPerPage.value
    return filtered.value.slice(start, start + itemsPerPage.value)
})

const totalPages = computed(() => Math.ceil(filtered.value.length / itemsPerPage.value))

const paginationLinks = computed(() => {
    const basePath = window.location.pathname
    const searchParams = new URLSearchParams(window.location.search)

    const getLinkUrl = (pageNum: number) => {
        const params = new URLSearchParams(searchParams)
        params.set('page', String(pageNum))
        return `${basePath}?${params.toString()}`
    }

    const links = []

    // Previous Page
    links.push({
        url: currentPage.value > 1 ? getLinkUrl(currentPage.value - 1) : null,
        label: '&laquo; Previous',
        active: false,
    })

    // Page Numbers
    for (let i = 1; i <= totalPages.value; i++) {
        links.push({
            url: getLinkUrl(i),
            label: String(i),
            active: currentPage.value === i,
        })
    }

    // Next Page
    links.push({
        url: currentPage.value < totalPages.value ? getLinkUrl(currentPage.value + 1) : null,
        label: 'Next &raquo;',
        active: false,
    })

    return links
})

watch(() => page.url, (newUrl) => {
    try {
        const url = new URL(newUrl, window.location.origin)
        const pageParam = url.searchParams.get('page')
        currentPage.value = pageParam ? parseInt(pageParam, 10) || 1 : 1
    } catch (e) {
        currentPage.value = 1
    }
}, { immediate: true })

const isSearching = ref(false)
let searchTimeout: ReturnType<typeof setTimeout> | null = null

watch([search, activeCategory, activeTag], () => {
    currentPage.value = 1
    isSearching.value = true
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
    searchTimeout = setTimeout(() => {
        isSearching.value = false
    }, 250)
})
</script>

<template>
    <!-- Site-free base; the global callback in app.ts appends the site name with the
         admin's separator. Hardcoding t('AI Tools') here made the tab disagree with the
         server <title>, which is built from seo.title_page. -->
    <Head :title="seo?.title_page || t('AI Tools')" />

    <div class="relative overflow-hidden w-full">
        <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 w-full">
            <!-- Modern Header Layout -->
            <section v-if="settings.archive_layout === 'modern'" class="mb-8 overflow-hidden rounded-[1.6rem] bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-8 text-white shadow-xl dark:from-blue-700 dark:via-indigo-850 dark:to-purple-900">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <!-- Breadcrumbs -->
                        <div v-if="settings.archive_show_breadcrumbs !== false" class="mb-3 flex flex-wrap items-center gap-2 text-sm text-white/70">
                            <Link :href="route('home')" class="inline-flex items-center gap-1.5 transition-colors">
                                <i class="ti ti-home text-base text-white/70 hover:text-white"></i>
                            </Link>
                            <i class="ti ti-chevron-right text-xs text-white/50"></i>
                            <span class="font-medium text-white">{{ t('AI Tools') }}</span>
                        </div>
                        <h1 class="font-heading text-4xl font-black tracking-tight modern-header-gradient-text sm:text-5xl pb-1">
                            {{ t('Find the right tool for every job') }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-base leading-7 text-white/80">
                            {{ t('Browse featured tools, filter by category, and open the exact workflow you need.') }}
                        </p>
                    </div>

                    <!-- Stats Cards -->
                    <div v-if="settings.archive_show_stats !== false" class="grid grid-cols-3 gap-3 sm:min-w-[320px]">
                        <div class="relative overflow-hidden rounded-2xl bg-white/10 px-4 py-3 shadow-sm border border-white/10 backdrop-blur-sm">
                            <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 text-white">
                                    <i class="ti ti-layout-grid text-[16px]"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-widest text-white/60">{{ t('Categories') }}</div>
                                    <div class="mt-0.5 text-xl font-bold text-white">{{ categories.length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="relative overflow-hidden rounded-2xl bg-white/10 px-4 py-3 shadow-sm border border-white/10 backdrop-blur-sm">
                            <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 text-white">
                                    <i class="ti ti-tools text-[16px]"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-widest text-white/60">{{ t('Tools') }}</div>
                                    <div class="mt-0.5 text-xl font-bold text-white">{{ tools.length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="relative overflow-hidden rounded-2xl bg-white/10 px-4 py-3 shadow-sm border border-white/10 backdrop-blur-sm">
                            <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 text-white">
                                    <i class="ti ti-stars text-[16px]"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-widest text-white/60">{{ t('Featured') }}</div>
                                    <div class="mt-0.5 text-xl font-bold text-white">{{ featured.length }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Minimal Header Layout -->
            <section v-else-if="settings.archive_layout === 'minimal'" class="mb-10 text-center flex flex-col items-center justify-center py-6">
                <div class="max-w-3xl">
                    <!-- Breadcrumbs -->
                    <div v-if="settings.archive_show_breadcrumbs !== false" class="mb-3 flex flex-wrap items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <Link :href="route('home')" class="inline-flex items-center gap-1.5 transition-colors">
                            <i class="ti ti-home text-base dark:text-gray-400 dark:hover:text-white"></i>
                        </Link>
                        <i class="ti ti-chevron-right text-xs text-gray-300 dark:text-gray-600"></i>
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ t('AI Tools') }}</span>
                    </div>
                    <h1 class="font-heading text-4xl font-black tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                        {{ t('Find the right tool for every job') }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-base leading-7 text-gray-600 dark:text-gray-400">
                        {{ t('Browse featured tools, filter by category, and open the exact workflow you need.') }}
                    </p>
                </div>

                <!-- Stats Cards -->
                <div v-if="settings.archive_show_stats !== false" class="mt-6 flex flex-wrap justify-center gap-3">
                    <div class="tool-stat-card tool-stat-card-categories relative overflow-hidden rounded-2xl px-5 py-2.5 shadow-sm border border-success-200 bg-gradient-to-r from-white to-green-50/20 dark:border-success-500/10 dark:from-white/5 dark:to-green-500/5">
                        <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-success-500/10 text-success-600 ring-1 ring-success-500/10 dark:bg-success-500/15 dark:text-success-300">
                                <i class="ti ti-layout-grid text-[16px]"></i>
                            </div>
                            <div class="text-left">
                                <div class="text-[10px] uppercase tracking-widest text-gray-500">{{ t('Categories') }}</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ categories.length }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="tool-stat-card tool-stat-card-tools relative overflow-hidden rounded-2xl px-5 py-2.5 shadow-sm border border-primary-200 bg-gradient-to-r from-white to-blue-50/20 dark:border-primary-500/10 dark:from-white/5 dark:to-blue-500/5">
                        <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 ring-1 ring-primary-500/10 dark:bg-primary-500/15 dark:text-primary-300">
                                <i class="ti ti-tools text-[16px]"></i>
                            </div>
                            <div class="text-left">
                                <div class="text-[10px] uppercase tracking-widest text-gray-500">{{ t('Tools') }}</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ tools.length }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="tool-stat-card tool-stat-card-featured relative overflow-hidden rounded-2xl px-5 py-2.5 shadow-sm border border-violet-200 bg-gradient-to-r from-white to-purple-50/20 dark:border-violet-500/10 dark:from-white/5 dark:to-purple-500/5">
                        <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                                <i class="ti ti-stars text-[16px]"></i>
                            </div>
                            <div class="text-left">
                                <div class="text-[10px] uppercase tracking-widest text-gray-500">{{ t('Featured') }}</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ featured.length }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Default Header Layout -->
            <section v-else class="card mb-8 overflow-hidden">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <!-- Breadcrumbs -->
                        <div v-if="settings.archive_show_breadcrumbs !== false" class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <Link :href="route('home')" class="inline-flex items-center gap-1.5 transition-colors">
                                <i class="ti ti-home text-base dark:text-gray-400 dark:hover:text-white"></i>
                            </Link>
                            <i class="ti ti-chevron-right text-xs text-gray-300 dark:text-gray-600"></i>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ t('AI Tools') }}</span>
                        </div>
                        <h1 class="font-heading text-4xl font-black tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                            {{ t('Find the right tool for every job') }}
                        </h1>
                        <p class="mt-2 max-w-2xl text-base leading-7 text-gray-600 dark:text-gray-400">
                            {{ t('Browse featured tools, filter by category, and open the exact workflow you need.') }}
                        </p>
                    </div>

                    <!-- Stats Cards -->
                    <div v-if="settings.archive_show_stats !== false" class="grid grid-cols-3 gap-3 sm:min-w-[320px]">
                        <div class="tool-stat-card tool-stat-card-categories relative overflow-hidden rounded-2xl px-4 py-3 shadow-sm">
                            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-success-500/10 blur-2xl dark:bg-success-400/20"></div>
                            <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-success-500/10 text-success-600 ring-1 ring-success-500/10 dark:bg-success-500/15 dark:text-success-300 dark:ring-success-400/15">
                                    <i class="ti ti-layout-grid text-[16px]"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-widest text-gray-500">{{ t('Categories') }}</div>
                                    <div class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white">{{ categories.length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="tool-stat-card tool-stat-card-tools relative overflow-hidden rounded-2xl px-4 py-3 shadow-sm">
                            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-primary-500/10 blur-2xl dark:bg-primary-400/20"></div>
                            <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 ring-1 ring-primary-500/10 dark:bg-primary-500/15 dark:text-primary-300 dark:ring-primary-400/15">
                                    <i class="ti ti-tools text-[16px]"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-widest text-gray-500">{{ t('Tools') }}</div>
                                    <div class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white">{{ tools.length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="tool-stat-card tool-stat-card-featured relative overflow-hidden rounded-2xl px-4 py-3 shadow-sm">
                            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-violet-500/10 blur-2xl dark:bg-violet-400/20"></div>
                            <div class="relative flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                                    <i class="ti ti-stars text-[16px]"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-widest text-gray-500">{{ t('Featured') }}</div>
                                    <div class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white">{{ featured.length }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="settings.archive_show_featured !== false && featured.length > 0 && activeCategory === 'all' && !search" class="mb-8">
                <div class="mb-4 flex items-center gap-2">
                    <i class="ti ti-carambola text-warning-500"></i>
                    <h2 class="font-heading text-xl font-black text-gray-900 dark:text-white">
                        <span :class="settings.archive_layout === 'modern' ? 'modern-gradient-text' : ''">
                            {{ t('Featured Tools') }}
                        </span>
                    </h2>
                </div>
                <div class="featured-tools-swiper relative overflow-visible">
                    <button
                        v-if="featured.length > 1 && !featuredAtStart"
                        type="button"
                        class="featured-tools-nav featured-tools-nav-prev"
                        :aria-label="t('Previous featured tool')"
                        @click="slideFeatured('prev')"
                    >
                        <i class="ti ti-chevron-left"></i>
                    </button>
                    <button
                        v-if="featured.length > 1 && !featuredAtEnd"
                        type="button"
                        class="featured-tools-nav featured-tools-nav-next"
                        :aria-label="t('Next featured tool')"
                        @click="slideFeatured('next')"
                    >
                        <i class="ti ti-chevron-right"></i>
                    </button>
                    <div class="overflow-hidden py-4 -my-4">
                        <Swiper
                            @swiper="onFeaturedSwiper"
                            @slideChange="updateFeaturedBounds"
                            @reachBeginning="updateFeaturedBounds"
                            @reachEnd="updateFeaturedBounds"
                            :modules="featuredModules"
                            :slides-per-view="1.15"
                            :space-between="16"
                            :breakpoints="{
                                640: { slidesPerView: 1.8 },
                                768: { slidesPerView: 2.2 },
                                1024: { slidesPerView: 3 },
                                1280: { slidesPerView: 4 }
                            }"
                        >
                            <SwiperSlide v-for="item in featured" :key="'feat-'+item.id">
                                <Link
                                    :href="route('ai.tools.show', item.slug)"
                                    class="group relative flex h-full min-h-[250px] flex-col overflow-hidden rounded-[1.4rem] border border-gray-200 bg-gradient-to-br from-white via-white to-gray-50 p-5 shadow-sm transition-all hover:-translate-y-1 hover:!border-primary-200 hover:shadow-lg dark:border-white/5 dark:from-white/[0.05] dark:via-white/[0.03] dark:to-white/[0.015] dark:hover:!border-primary-500/30"
                                >
                                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.08),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.08),transparent_35%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.12),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.08),transparent_35%)]"></div>
                                    <div class="absolute -right-10 -top-10 h-28 w-28 rounded-full bg-primary-500/10 blur-3xl"></div>
                                    <div v-if="isProTool(item)" class="badge badge-pro absolute right-4 top-4">{{ t('PRO') }}</div>
                                    <div class="relative z-10 flex h-full flex-col">
                                        <div class="mb-5 flex items-start justify-between gap-3">
                                            <div class="flex h-14 w-14 items-center justify-center rounded-[1.15rem] border border-white/60 bg-white/80 shadow-[0_18px_40px_rgba(15,23,42,0.06)] backdrop-blur dark:border-white/10 dark:bg-white/10" :style="{ boxShadow: `0 12px 30px ${ (item.color || '#1F75FE') }20` }">
                                                <i :class="[item.icon || 'ti ti-wand', 'text-[24px]']" :style="{ color: item.color || '#1F75FE' }"></i>
                                            </div>
                                        </div>

                                        <div class="flex-1">
                                            <h3 :class="settings.archive_layout === 'modern' ? 'modern-gradient-text text-[15px] font-extrabold tracking-tight transition-all group-hover:opacity-90' : 'text-[15px] font-bold tracking-tight text-gray-900 transition-colors group-hover:text-primary-600 dark:text-white'">{{ item.name }}</h3>
                                            <p class="mt-2 line-clamp-3 text-sm leading text-gray-500 dark:text-gray-300">{{ item.description }}</p>
                                        </div>

                                        <div class="mt-5 flex items-center justify-between gap-3 text-xs text-gray-400">
                                            <span v-if="item.category" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white/90 px-2.5 py-1 text-[10px] font-medium text-gray-500 shadow-sm dark:border-white/10 dark:bg-white/10 dark:text-gray-300">
                                                <i v-if="item.category.icon" :class="item.category.icon" class="text-[10px]" :style="{ color: item.category.color }"></i>
                                                {{ item.category.name }}
                                            </span>
                                            <span v-if="settings.archive_show_open_button !== false" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary-600 opacity-90 transition-opacity group-hover:opacity-100 dark:text-primary-300">
                                                {{ t('Open tool') }}
                                                <i class="ti ti-arrow-right text-base"></i>
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                </div>
            </section>

            <section v-if="settings.archive_show_recently_used !== false && recentlyUsed.length > 0 && activeCategory === 'all' && !search && !activeTag" class="mb-8">
                <div class="mb-4 flex items-center gap-2">
                    <i class="ti ti-clock text-primary-500"></i>
                    <h2 class="font-heading text-xl font-black text-gray-900 dark:text-white">
                        <span :class="settings.archive_layout === 'modern' ? 'modern-gradient-text' : ''">
                            {{ t('Recently Used') }}
                        </span>
                    </h2>
                </div>
                <div class="recent-tools-swiper relative overflow-visible">
                    <button
                        v-if="recentlyUsed.length > 1 && !recentAtStart"
                        type="button"
                        class="recent-tools-nav recent-tools-nav-prev"
                        :aria-label="t('Previous tool')"
                        @click="slideRecent('prev')"
                    >
                        <i class="ti ti-chevron-left"></i>
                    </button>
                    <button
                        v-if="recentlyUsed.length > 1 && !recentAtEnd"
                        type="button"
                        class="recent-tools-nav recent-tools-nav-next"
                        :aria-label="t('Next tool')"
                        @click="slideRecent('next')"
                    >
                        <i class="ti ti-chevron-right"></i>
                    </button>
                    <div class="overflow-hidden py-4 -my-4">
                        <Swiper
                            @swiper="onRecentSwiper"
                            @slideChange="updateRecentBounds"
                            @reachBeginning="updateRecentBounds"
                            @reachEnd="updateRecentBounds"
                            :modules="featuredModules"
                            :slides-per-view="1.15"
                            :space-between="16"
                            :breakpoints="{
                                640: { slidesPerView: 1.8 },
                                768: { slidesPerView: 2.2 },
                                1024: { slidesPerView: 3 },
                                1280: { slidesPerView: 4 }
                            }"
                        >
                            <SwiperSlide v-for="item in recentlyUsed" :key="'recent-'+item.id">
                                <Link
                                    :href="route('ai.tools.show', item.slug)"
                                    class="group card relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:-translate-y-1 hover:!border-primary-200 hover:shadow-lg dark:border-white/5 dark:bg-white/[0.03] flex flex-col h-full justify-between"
                                >
                                    <div>
                                        <div class="mb-3 flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border shrink-0" :style="{ background: (item.color || '#64748b') + '14', borderColor: (item.color || '#64748b') + '28' }">
                                                <i :class="[item.icon || 'ti ti-wand', 'text-[18px]']" :style="{ color: item.color || '#64748b' }"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h3 :class="settings.archive_layout === 'modern' ? 'modern-gradient-text text-sm font-extrabold transition-all group-hover:opacity-90' : 'text-sm font-bold text-gray-900 transition-colors group-hover:text-primary-600 dark:text-white'">{{ item.name }}</h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ item.category?.name }}</p>
                                            </div>
                                            <i class="ti ti-arrow-right text-primary-400 opacity-0 transition-opacity group-hover:opacity-100 shrink-0"></i>
                                        </div>
                                        <p class="line-clamp-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                                    </div>
                                </Link>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                </div>
            </section>

            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="font-heading text-xl font-black text-gray-900 dark:text-white flex items-center">
                        <i class="ti ti-folder-search mr-2 text-primary-500"></i>
                        <span :class="settings.archive_layout === 'modern' ? 'modern-gradient-text' : ''">
                            {{ t('Explore Tools') }}
                        </span>
                    </h2>
                </div>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center lg:items-end lg:ml-auto w-full lg:w-auto">
                    <div class="relative w-full sm:w-64">
                        <i v-if="isSearching" class="ti ti-loader animate-spin absolute left-3 top-1/2 -translate-y-1/2 text-base text-primary-500"></i>
                        <i v-else class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-450 pointer-events-none"></i>
                        <input
                            v-model="search"
                            type="text"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2.5 pl-10 pr-9 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-900/50 dark:text-white dark:focus:border-primary-500 dark:focus:bg-surface-900"
                            :placeholder="t('Search tools...')"
                        />
                        <button
                            v-if="search"
                            type="button"
                            @click="search = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            :aria-label="t('Clear search')"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="flex-1 sm:w-48">
                            <AppSelect
                                v-model="activeCategory"
                                :options="categoryOptions"
                                :placeholder="t('All categories')"
                                live-search
                            />
                        </div>

                        <!-- Grid/List View Toggle Group -->
                        <div v-if="settings.archive_show_grid_list !== false" class="flex items-center rounded-xl bg-gray-100 p-0.5 dark:bg-surface-800 shrink-0">
                            <button
                                type="button"
                                @click="setViewMode('grid')"
                                :class="viewMode === 'grid' ? 'bg-white text-gray-900 shadow-sm dark:bg-surface-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg transition-all"
                                :title="t('Grid View')"
                            >
                                <i class="ti ti-layout-grid text-lg"></i>
                            </button>
                            <button
                                type="button"
                                @click="setViewMode('list')"
                                :class="viewMode === 'list' ? 'bg-white text-gray-900 shadow-sm dark:bg-surface-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg transition-all"
                                :title="t('List View')"
                            >
                                <i class="ti ti-layout-list text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="allTags.length > 0" class="w-full sm:w-48">
                        <AppSelect
                            v-model="activeTag"
                            :options="tagOptions"
                            :placeholder="t('All tags')"
                        />
                    </div>
                </div>
            </div>

            <!-- Loading Skeleton Grid/List -->
            <div v-if="isSearching" class="transition-all duration-200">
                <div v-if="viewMode === 'grid'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div v-for="n in 8" :key="'skeleton-grid-'+n" class="card relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-5 dark:border-white/5 dark:bg-white/[0.03]">
                        <div class="mb-4 h-12 w-12 shimmer rounded-2xl"></div>
                        <div class="h-4 w-2/3 shimmer rounded"></div>
                        <div class="mt-3 space-y-2">
                            <div class="h-3 w-full shimmer rounded"></div>
                            <div class="h-3 w-5/6 shimmer rounded"></div>
                        </div>
                        <div class="mt-5 flex justify-between">
                            <div class="h-4 w-1/3 shimmer rounded"></div>
                            <div class="h-4 w-1/4 shimmer rounded"></div>
                        </div>
                    </div>
                </div>
                <div v-else class="flex flex-col gap-3">
                    <div v-for="n in 5" :key="'skeleton-list-'+n" class="relative flex flex-col gap-4 rounded-2xl border border-gray-100 bg-white p-4 dark:border-white/5 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3.5 w-full">
                            <div class="h-12 w-12 rounded-xl shimmer shrink-0"></div>
                            <div class="flex-1 space-y-2 min-w-0">
                                <div class="h-4 w-1/4 shimmer rounded"></div>
                                <div class="h-3 w-1/2 shimmer rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actual Tools Grid/List -->
            <template v-else>
                <div v-if="filtered.length && viewMode === 'grid'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <template v-for="(item, index) in paginatedTools" :key="'grid-'+item.id">
                        <AdSection v-if="index > 0 && index % 8 === 0" zone="between_ai_tools" bare class="col-span-full mx-auto w-full max-w-[728px]" />
                        <Link
                            :href="route('ai.tools.show', item.slug)"
                            class="group card relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:-translate-y-1 hover:!border-primary-200 hover:shadow-lg dark:border-white/5 dark:bg-white/[0.03] dark:hover:!border-primary-500/30"
                        >
                            <div v-if="isProTool(item)" class="badge badge-pro absolute right-4 top-4">{{ t('PRO') }}</div>

                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl border" :style="{ background: (item.color || '#64748b') + '14', borderColor: (item.color || '#64748b') + '28' }">
                                <i :class="[item.icon || 'ti ti-wand', 'text-[22px]']" :style="{ color: item.color || '#64748b' }"></i>
                            </div>

                            <h3 :class="settings.archive_layout === 'modern' ? 'modern-gradient-text pr-10 text-sm font-extrabold transition-all group-hover:opacity-90' : 'pr-10 text-sm font-bold text-gray-900 transition-colors group-hover:text-primary-600 dark:text-white'">{{ item.name }}</h3>
                            <p class="mt-2 line-clamp-2 text-xs leading text-gray-500 dark:text-gray-400">{{ item.description }}</p>

                            <div class="mt-4 flex items-center justify-between text-xs text-gray-400">
                                <div v-if="activeCategory === 'all' && item.category">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-500 dark:bg-white/[0.05] dark:text-gray-400">
                                        <i v-if="item.category.icon" :class="item.category.icon" class="text-[10px]" :style="{ color: item.category.color }"></i>
                                        {{ item.category.name }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="item.views_count" class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                        <i class="ti ti-eye text-[10px]"></i>
                                        {{ formatViews(item.views_count) }}
                                    </span>
                                    <span v-if="settings.archive_show_open_button !== false" class="text-primary-400 inline-flex items-center gap-1">
                                        <i class="ti ti-sparkles text-sm"></i>
                                        {{ t('Open tool') }}
                                        <i class="ti ti-arrow-right text-base opacity-0 transition-opacity group-hover:opacity-100"></i>
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </template>
                </div>

                <div v-else-if="filtered.length && viewMode === 'list'" class="flex flex-col gap-3">
                    <template v-for="(item, index) in paginatedTools" :key="'list-'+item.id">
                        <AdSection v-if="index > 0 && index % 8 === 0" zone="between_ai_tools" bare class="mx-auto w-full max-w-[728px]" />
                        <Link
                            :href="route('ai.tools.show', item.slug)"
                            class="group relative flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 transition-all hover:!border-primary-200 hover:shadow-md dark:border-white/5 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between dark:hover:!border-primary-500/30"
                        >
                            <div class="flex items-center gap-3.5 min-w-0">
                                <!-- Tool Icon -->
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border" :style="{ background: (item.color || '#64748b') + '14', borderColor: (item.color || '#64748b') + '28' }">
                                    <i :class="[item.icon || 'ti ti-wand', 'text-[22px]']" :style="{ color: item.color || '#64748b' }"></i>
                                </div>

                                <!-- Name / Description -->
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 :class="settings.archive_layout === 'modern' ? 'modern-gradient-text text-sm font-extrabold transition-all group-hover:opacity-90 truncate' : 'text-sm font-bold text-gray-900 transition-colors group-hover:text-primary-600 dark:text-white truncate'">{{ item.name }}</h3>
                                        <div v-if="isProTool(item)" class="badge badge-pro !static !py-0.5 !px-1.5 !text-[9px]">{{ t('PRO') }}</div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-1 max-w-xl">{{ item.description }}</p>
                                </div>
                            </div>

                            <!-- Right Stats / Category / Open button -->
                            <div class="flex items-center justify-between sm:justify-end gap-4 border-t border-gray-50 pt-3 sm:border-0 sm:pt-0 shrink-0">
                                <div class="flex items-center gap-3 text-xs text-gray-400">
                                    <span v-if="activeCategory === 'all' && item.category" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-500 dark:bg-white/[0.05] dark:text-gray-400">
                                        <i v-if="item.category.icon" :class="item.category.icon" class="text-[10px]" :style="{ color: item.category.color }"></i>
                                        {{ item.category.name }}
                                    </span>
                                    <span v-if="item.views_count" class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                        <i class="ti ti-eye text-[10px]"></i>
                                        {{ formatViews(item.views_count) }}
                                    </span>
                                </div>
                                <span v-if="settings.archive_show_open_button !== false" :class="settings.archive_layout === 'modern' ? 'inline-flex items-center gap-1 rounded-xl bg-gradient-to-r from-primary-500 to-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:from-primary-600 hover:to-violet-700 transition' : 'text-primary-400 inline-flex items-center gap-1 text-xs font-semibold'">
                                    {{ t('Open tool') }}
                                    <i class="ti ti-arrow-right text-base transition-transform group-hover:translate-x-1"></i>
                                </span>
                            </div>
                        </Link>
                    </template>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-2xl mt-8 w-full border border-gray-200 bg-white p-12 text-center dark:border-white/5 dark:bg-white/[0.03]">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                        <i class="ti ti-search text-2xl"></i>
                    </div>
                    <h3 class="font-heading text-xl font-bold text-gray-900 dark:text-white">{{ t('No tools found') }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t("We couldn't find any tools matching your search criteria.") }}</p>
                    <button @click="search = ''; activeCategory = 'all'; activeTag = null" :class="settings.archive_layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white shadow-md' : 'btn-primary'" class="mt-5 rounded-full px-5 py-2.5 text-sm font-semibold transition">
                        {{ t('Clear Filters') }}
                    </button>
                </div>

                <!-- Pagination Controls -->
                <div v-if="filtered.length" class="relative z-10" :class="{ 'modern-pagination-container': settings.archive_layout === 'modern' }">
                    <!-- Load More Button -->
                    <div v-if="settings.archive_pagination === 'load_more' && currentPage < totalPages" class="mt-8 flex justify-center">
                        <button
                            type="button"
                            @click="loadMore"
                            :disabled="isPageLoading"
                            :class="[
                                settings.archive_layout === 'modern' ? 'bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 shadow-md shadow-blue-500/10' : 'bg-primary-500 hover:bg-primary-600',
                                isPageLoading ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                            class="inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold text-white shadow-sm transition"
                        >
                            <i :class="isPageLoading ? 'ti ti-loader animate-spin' : 'ti ti-refresh mr-1'"></i>
                            {{ isPageLoading ? t('Loading...') : t('Load More') }}
                        </button>
                    </div>

                    <!-- Numbered Pagination -->
                    <AppPagination
                        v-if="settings.archive_pagination === 'numbered' && totalPages > 1"
                        :links="paginationLinks"
                        :current-page="currentPage"
                        :last-page="totalPages"
                        :total="filtered.length"
                        :from="(currentPage - 1) * itemsPerPage + 1"
                        :to="Math.min(currentPage * itemsPerPage, filtered.length)"
                        class="mt-8"
                    />
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.shimmer {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: shimmer-animation 1.5s infinite linear;
}

.dark .shimmer {
    background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
    background-size: 200% 100%;
}

@keyframes shimmer-animation {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}

.featured-tools-swiper {
    padding-bottom: 0;
}

.featured-tools-swiper :deep(.swiper) {
    overflow: visible;
}

.featured-tools-swiper :deep(.swiper-wrapper) {
    align-items: stretch;
}

.featured-tools-swiper :deep(.swiper-slide) {
    height: auto;
}

.featured-tools-nav {
    position: absolute;
    top: 50%;
    z-index: 10;
    display: none;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border: 1px solid rgb(229 231 235);
    border-radius: 9999px;
    background: rgb(255 255 255);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
    color: rgb(31 41 55);
    transform: translateY(-50%);
}

@media (min-width: 640px) {
    .featured-tools-nav {
        display: inline-flex;
    }
}

.featured-tools-nav:hover {
    background: rgb(249 250 251);
}

.featured-tools-nav:disabled,
.featured-tools-nav.is-hidden {
    opacity: 0;
    pointer-events: none;
}

.featured-tools-nav-prev {
    left: 0;
    transform: translate(-50%, -50%);
}

.featured-tools-nav-next {
    right: 0;
    transform: translate(50%, -50%);
}

.tool-stat-card {
    border: 1px solid transparent;
    background-position: center;
    background-size: cover;
}

.tool-stat-card-categories {
    border-color: rgb(187 247 208);
    background-image: linear-gradient(135deg, rgb(255 255 255), rgb(240 253 244) 55%, rgb(255 255 255));
}

.tool-stat-card-tools {
    border-color: rgb(191 219 254);
    background-image: linear-gradient(135deg, rgb(255 255 255), rgb(239 246 255) 55%, rgb(255 255 255));
}

.tool-stat-card-featured {
    border-color: rgb(221 214 254);
    background-image: linear-gradient(135deg, rgb(255 255 255), rgb(245 243 255) 55%, rgb(255 255 255));
}

.dark .tool-stat-card-categories {
    border-color: rgb(34 197 94 / 0.2);
    background-image: linear-gradient(135deg, rgb(255 255 255 / 0.06), rgb(34 197 94 / 0.12) 55%, rgb(255 255 255 / 0.03));
}

.dark .tool-stat-card-tools {
    border-color: rgb(59 130 246 / 0.2);
    background-image: linear-gradient(135deg, rgb(255 255 255 / 0.06), rgb(59 130 246 / 0.12) 55%, rgb(255 255 255 / 0.03));
}

.dark .tool-stat-card-featured {
    border-color: rgb(168 85 247 / 0.2);
    background-image: linear-gradient(135deg, rgb(255 255 255 / 0.06), rgb(168 85 247 / 0.12) 55%, rgb(255 255 255 / 0.03));
}

.modern-pagination-container :deep(.bg-primary-500) {
    background-image: linear-gradient(to right, #3b82f6, #8b5cf6) !important;
    border-color: transparent !important;
}

.modern-gradient-text {
    background-image: linear-gradient(to right, #3b82f6, #8b5cf6) !important;
    -webkit-background-clip: text !important;
    background-clip: text !important;
    color: transparent !important;
    display: inline-block;
}

.dark .modern-gradient-text {
    background-image: linear-gradient(to right, #60a5fa, #a78bfa) !important;
}

.modern-header-gradient-text {
    background-image: linear-gradient(to right, #ffffff, #e0e7ff) !important;
    -webkit-background-clip: text !important;
    background-clip: text !important;
    color: transparent !important;
    display: inline-block;
}

.recent-tools-swiper :deep(.swiper-slide) {
    height: auto;
}

.recent-tools-nav {
    position: absolute;
    top: 50%;
    z-index: 10;
    display: none;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border: 1px solid rgb(229 231 235);
    border-radius: 9999px;
    background: rgb(255 255 255);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
    color: rgb(31 41 55);
    transform: translateY(-50%);
}

@media (min-width: 640px) {
    .recent-tools-nav {
        display: inline-flex;
    }
}

.recent-tools-nav:hover {
    background: rgb(249 250 251);
}

.recent-tools-nav:disabled,
.recent-tools-nav.is-hidden {
    opacity: 0;
    pointer-events: none;
}

.recent-tools-nav-prev {
    left: 0;
    transform: translate(-50%, -50%);
}

.recent-tools-nav-next {
    right: 0;
    transform: translate(50%, -50%);
}
</style>
