<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'
import AppPagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

interface Category {
    id: number
    name: string
    slug: string
    description: string
    icon: string
    color: string
    access_level: string
}

interface Template {
    id: number
    name: string
    slug: string
    description: string
    icon: string
    color: string
    is_featured: boolean
    access_level: string
    views_count?: number
}

const props = defineProps<{
    category: Category
    tools: Template[]
}>()

const { t } = useTranslate()

const page = usePage()
const settings = computed(() => (page.props as any).appearanceToolPageSettings ?? {})

const itemsPerPage = ref(16)
const currentPage = ref(1)
const isPageLoading = ref(false)
const search = ref('')

const filteredTools = computed(() => {
    let list = props.tools
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        list = list.filter(t => t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q))
    }
    return list
})

watch(search, () => {
    currentPage.value = 1
})

function loadMore() {
    if (isPageLoading.value) return
    isPageLoading.value = true
    setTimeout(() => {
        currentPage.value++
        isPageLoading.value = false
    }, 600)
}

const paginatedTools = computed(() => {
    if (settings.value.category_pagination === 'none') {
        return filteredTools.value
    }
    if (settings.value.category_pagination === 'load_more') {
        return filteredTools.value.slice(0, currentPage.value * itemsPerPage.value)
    }
    // 'numbered'
    const start = (currentPage.value - 1) * itemsPerPage.value
    return filteredTools.value.slice(start, start + itemsPerPage.value)
})

const totalPages = computed(() => Math.ceil(filteredTools.value.length / itemsPerPage.value))

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
            active: i === currentPage.value,
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

// Soft paginate on page load query parameter "?page=x"
watch(
    () => page.url,
    () => {
        const params = new URLSearchParams(window.location.search)
        const p = parseInt(params.get('page') || '1')
        if (p > 0 && p <= totalPages.value) {
            currentPage.value = p
        }
    },
    { immediate: true }
)

function formatViews(views: number): string {
    if (views >= 1000000) {
        return (views / 1000000).toFixed(1) + 'M'
    }
    if (views >= 1000) {
        return (views / 1000).toFixed(1) + 'K'
    }
    return views.toString()
}

const isProTool = (tool: Template) => {
    const level = tool.access_level || 'inherit'
    if (level === 'premium' || level.startsWith('plan:')) return true
    if (level === 'inherit' && props.category.access_level) {
        const catLevel = props.category.access_level
        return catLevel === 'premium' || catLevel.startsWith('plan:')
    }
    return false
}
</script>

<template>
    <Head :title="t(':name AI Tools', { name: category.name })" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6">
        <!-- Header -->
        <div :class="settings.category_enable_gradient ? 'bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 text-white shadow-xl dark:from-blue-700 dark:via-indigo-850 dark:to-purple-900 border-0 p-8 sm:p-10 mb-10' : 'bg-white/[0.02] border border-white/5 p-8 mb-6'" class="rounded-2xl relative overflow-hidden">
            <!-- Breadcrumbs positioned above the title -->
            <div v-if="settings.category_show_breadcrumbs !== false" class="flex flex-wrap items-center gap-2 mb-6 text-sm relative z-10">
                <Link :href="route('home')" class="inline-flex items-center gap-1.5" :class="settings.category_enable_gradient ? 'category-gradient-breadcrumb-link' : 'text-gray-500 hover:text-primary-400 dark:text-gray-400 dark:hover:text-white'">
                    <i class="ti ti-home text-base"></i>
                </Link>
                <i class="ti ti-chevron-right text-xs" :class="settings.category_enable_gradient ? 'category-gradient-breadcrumb-divider' : 'text-gray-400 dark:text-gray-600'"></i>
                <Link :href="route('ai.tools.index')" :class="settings.category_enable_gradient ? 'category-gradient-breadcrumb-link' : 'text-gray-500 hover:text-primary-400 transition-colors'">{{ t('AI Tools') }}</Link>
                <i class="ti ti-chevron-right text-xs" :class="settings.category_enable_gradient ? 'category-gradient-breadcrumb-divider' : 'text-gray-400 dark:text-gray-600'"></i>
                <span :class="settings.category_enable_gradient ? 'category-gradient-breadcrumb-active font-medium' : 'text-gray-300 font-medium'">{{ category.name }}</span>
            </div>

            <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                <i :class="[category.icon || 'ti ti-apps', 'text-9xl']" :style="{ color: settings.category_enable_gradient ? '#ffffff' : category.color }"></i>
            </div>

            <!-- Title & Search box row -->
            <div class="relative z-10 flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                <div class="flex items-start gap-5 min-w-0 flex-1">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center border shrink-0 bg-white/10 border-white/15 shadow-sm" :style="!settings.category_enable_gradient ? { background: (category.color || '#3b82f6') + '15', borderColor: (category.color || '#3b82f6') + '30' } : {}">
                        <i :class="[category.icon || 'ti ti-apps', 'text-3xl']" :style="settings.category_enable_gradient ? { color: '#ffffff' } : { color: category.color || '#3b82f6' }"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 :class="settings.category_enable_gradient ? 'modern-header-gradient-text font-black' : 'text-white'" class="text-3xl font-bold mb-2">{{ t(':name Tools', { name: category.name }) }}</h1>
                        <p :class="settings.category_enable_gradient ? 'text-white/80' : 'text-gray-400'" class="max-w-2xl leading-relaxed">{{ category.description || t('Explore our collection of AI-powered tools for :name.', { name: category.name.toLowerCase() }) }}</p>
                        <div :class="settings.category_enable_gradient ? 'bg-white/15 border-white/20 text-white shadow-sm' : 'bg-gray-200/30 border-gray-200/30 text-gray-500 dark:bg-surface-800 dark:border-gray-800 dark:text-gray-300'" class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm">
                            <i :class="settings.category_enable_gradient ? 'text-white' : 'text-primary-400'" class="ti ti-layers"></i>
                            {{ t(':count tools available', { count: tools.length }) }}
                        </div>
                    </div>
                </div>

                <!-- Search Filter Inline -->
                <div v-if="tools.length" class="shrink-0 w-full md:w-72 mt-2 md:mt-0">
                    <div class="relative">
                        <input
                            v-model="search"
                            type="text"
                            :class="[
                                settings.category_enable_gradient
                                ? 'category-gradient-search-input'
                                : 'bg-white/[0.03] border-white/5 text-white placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500'
                            ]"
                            class="w-full rounded-xl border py-2.5 pl-10 pr-9 text-sm transition-all focus:outline-none focus:ring-1"
                            :placeholder="t('Search tools...')"
                        />
                        <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-base pointer-events-none z-10" :class="settings.category_enable_gradient ? 'category-gradient-search-icon' : 'text-gray-400'"></i>
                        <button
                            v-if="search"
                            type="button"
                            @click="search = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 z-10"
                            :class="settings.category_enable_gradient ? 'category-gradient-search-close' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'"
                            :aria-label="t('Clear search')"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tool Grid -->
        <div v-if="filteredTools.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="tool in paginatedTools"
                :key="tool.id"
                :href="route('ai.tools.show', tool.slug)"
                class="group card relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:-translate-y-1 hover:!border-primary-200 hover:shadow-lg dark:border-white/5 dark:hover:!border-primary-500/40 dark:bg-white/[0.03] flex flex-col justify-between"
            >
                <div v-if="isProTool(tool)" class="badge badge-pro absolute right-4 top-4 transition-opacity group-hover:opacity-0">{{ t('PRO') }}</div>
                <div v-else-if="tool.access_level === 'login'" class="absolute top-4 right-4 px-2 py-0.5 bg-sky-500/15 text-sky-400 text-[10px] font-bold uppercase rounded-full border border-sky-500/20 transition-opacity group-hover:opacity-0">{{ t('LOGIN') }}</div>

                <!-- Hover Arrow in Top Right Corner -->
                <i class="ti ti-arrow-up-right absolute right-5 top-5 text-primary-400 text-lg opacity-0 -translate-x-1.5 translate-y-1.5 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 group-hover:translate-y-0"></i>

                <div>
                    <!-- Icon Container -->
                    <div
                        class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl border transition-transform group-hover:scale-110"
                        :style="{ background: (tool.color || category.color || '#64748b') + '14', borderColor: (tool.color || category.color || '#64748b') + '28' }"
                    >
                        <i :class="[tool.icon || 'ti ti-wand', 'text-[22px]']" :style="{ color: tool.color || category.color || '#64748b' }"></i>
                    </div>

                    <h3 :class="settings.category_enable_gradient ? 'modern-gradient-text pr-10 text-sm font-extrabold transition-all group-hover:opacity-90' : 'pr-10 text-sm font-bold text-gray-900 transition-colors group-hover:text-primary-600 dark:text-white'">{{ tool.name }}</h3>
                    <p class="mt-2 line-clamp-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ tool.description }}</p>
                </div>

                <div v-if="tool.views_count" class="mt-4 flex items-center justify-end text-xs text-gray-400">
                    <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                        <i class="ti ti-eye text-[10px]"></i>
                        {{ formatViews(tool.views_count) }}
                    </span>
                </div>
            </Link>
        </div>

        <!-- Pagination Controls -->
        <div v-if="filteredTools.length" class="relative z-10" :class="{ 'modern-pagination-container': settings.category_enable_gradient }">
            <!-- Load More Button -->
            <div v-if="settings.category_pagination === 'load_more' && currentPage < totalPages" class="mt-8 flex justify-center">
                <button
                    type="button"
                    @click="loadMore"
                    :disabled="isPageLoading"
                    :class="[
                        settings.category_enable_gradient ? 'bg-gradient-to-r from-primary-500 to-violet-600 hover:from-primary-600 hover:to-violet-700 shadow-md shadow-primary-500/10' : 'bg-primary-500 hover:bg-primary-600',
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
                v-if="settings.category_pagination === 'numbered' && totalPages > 1"
                :links="paginationLinks"
                :current-page="currentPage"
                :last-page="totalPages"
                :total="filteredTools.length"
                :from="(currentPage - 1) * itemsPerPage + 1"
                :to="Math.min(currentPage * itemsPerPage, filteredTools.length)"
                class="mt-8"
            />
        </div>

        <!-- Search Empty State -->
        <div v-else-if="tools.length && !filteredTools.length" class="text-center py-20 bg-white/[0.02] border border-white/5 rounded-2xl">
            <div class="w-16 h-16 rounded-full bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-4">
                <i class="ti ti-search text-2xl text-gray-500"></i>
            </div>
            <h3 class="text-white font-medium mb-1">{{ t('No tools match your search') }}</h3>
            <p class="text-gray-500 text-sm mb-6">{{ t('Try checking for typos or searching a different term.') }}</p>
            <button type="button" @click="search = ''" class="px-6 py-2.5 bg-white/5 hover:bg-white/10 text-white rounded-xl text-sm font-medium transition-colors border border-white/5">
                {{ t('Clear search') }}
            </button>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-20 bg-white/[0.02] border border-white/5 rounded-2xl">
            <div class="w-16 h-16 rounded-full bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-4">
                <i :class="[category.icon || 'ti ti-apps', 'text-2xl text-gray-500']"></i>
            </div>
            <h3 class="text-white font-medium mb-1">{{ t('No tools yet') }}</h3>
            <p class="text-gray-500 text-sm mb-6">{{ t("We're still building tools for this category. Check back soon!") }}</p>
            <Link :href="route('ai.tools.index')" :class="settings.category_enable_gradient ? 'bg-gradient-to-r from-primary-500 to-violet-600 hover:from-primary-600 hover:to-violet-700 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-white border border-white/5'" class="px-6 py-2.5 rounded-xl text-sm font-medium transition-colors">
                {{ t('Explore All Tools') }}
            </Link>
        </div>
    </div>
</template>

<style scoped>
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

.category-gradient-breadcrumb-link,
.category-gradient-breadcrumb-link * {
    color: rgba(255, 255, 255, 0.75) !important;
    transition: color 0.2s ease-in-out;
}

.category-gradient-breadcrumb-link:hover,
.category-gradient-breadcrumb-link:hover * {
    color: rgba(255, 255, 255, 1) !important;
}

.category-gradient-breadcrumb-divider {
    color: rgba(255, 255, 255, 0.5) !important;
}

.category-gradient-breadcrumb-active {
    color: rgba(255, 255, 255, 1) !important;
}

.category-gradient-search-input {
    background-color: rgba(255, 255, 255, 0.12) !important;
    border-color: rgba(255, 255, 255, 0.22) !important;
    color: #ffffff !important;
    backdrop-filter: blur(12px) !important;
    -webkit-backdrop-filter: blur(12px) !important;
    box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.05), 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
}

.category-gradient-search-input::placeholder {
    color: rgba(255, 255, 255, 0.6) !important;
}

.category-gradient-search-input:focus {
    background-color: rgba(255, 255, 255, 0.18) !important;
    border-color: rgba(255, 255, 255, 0.35) !important;
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.12) !important;
}

.category-gradient-search-icon {
    color: rgba(255, 255, 255, 0.75) !important;
}

.category-gradient-search-close,
.category-gradient-search-close i {
    color: rgba(255, 255, 255, 0.7) !important;
    transition: color 0.15s ease-in-out;
}

.category-gradient-search-close:hover,
.category-gradient-search-close:hover i {
    color: #ffffff !important;
}
</style>
