<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import type { Swiper as SwiperClass } from 'swiper'
import { Navigation, Pagination, A11y } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import AppSelect from '@/Components/AppSelect.vue'
import UserLayout from '@/Layouts/UserLayout.vue'
import AdSection from '@/Components/AdSection.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

interface Category {
    id: number
    name: string
    slug: string
    icon: string
    color: string
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
    requires_pro: boolean
    requires_login: boolean
}

const props = defineProps<{
    tools: Tool[]
    categories: Category[]
    featured: Tool[]
    initialCategory?: number | string
}>()

const { t } = useTranslate()
const activeCategory = ref<number | string>(props.initialCategory || 'all')
const search = ref('')
const featuredModules = [Navigation, Pagination, A11y]
const featuredSwiper = ref<SwiperClass | null>(null)
const featuredAtStart = ref(true)
const featuredAtEnd = ref(false)
const categoryOptions = computed(() => [
    { value: 'all', label: t('All categories') },
    ...props.categories.map(category => ({
        value: category.id,
        label: category.name,
        icon: category.icon || undefined,
    })),
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
        list = list.filter(t => t.category_id === activeCategory.value)
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        list = list.filter(t => t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q))
    }
    return list
})
</script>

<template>
    <Head :title="t('AI Tools')" />

    <div class="relative overflow-hidden">
        <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <section class="card mb-8 overflow-hidden">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <Link :href="route('home')" class="inline-flex items-center gap-1.5 transition-colors hover:text-primary-600 dark:hover:text-primary-300">
                                <i class="ti ti-home text-base"></i>
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

                    <div class="grid grid-cols-3 gap-3 sm:min-w-[320px]">
                        <div class="tool-stat-card tool-stat-card-categories relative overflow-hidden rounded-2xl px-4 py-3 shadow-sm">
                            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-success-500/10 blur-2xl dark:bg-success-400/20"></div>
                            <div class="relative flex items-center gap-2">
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
                            <div class="relative flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 ring-1 ring-primary-500/10 dark:bg-primary-500/15 dark:text-primary-300 dark:ring-primary-400/15">
                                    <i class="ti ti-tools text-[16px]"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-widest text-gray-500">{{ t('Tools') }}</div>
                                    <div class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white">{{ filtered.length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="tool-stat-card tool-stat-card-featured relative overflow-hidden rounded-2xl px-4 py-3 shadow-sm">
                            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-violet-500/10 blur-2xl dark:bg-violet-400/20"></div>
                            <div class="relative flex items-center gap-2">
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

            <section v-if="featured.length > 0 && activeCategory === 'all' && !search" class="mb-8">
                <div class="mb-4 flex items-center gap-2">
                    <i class="ti ti-carambola text-warning-500"></i>
                    <h2 class="font-heading text-xl font-black text-gray-900 dark:text-white">{{ t('Featured Tools') }}</h2>
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
                    <div class="overflow-hidden">
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
                                    class="group relative flex h-full min-h-[250px] flex-col overflow-hidden rounded-[1.4rem] border border-gray-200 bg-gradient-to-br from-white via-white to-gray-50 p-5 shadow-sm transition-all hover:-translate-y-1 hover:border-primary-200 hover:shadow-lg dark:border-white/5 dark:from-white/[0.05] dark:via-white/[0.03] dark:to-white/[0.015]"
                                >
                                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.08),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.08),transparent_35%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.12),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.08),transparent_35%)]"></div>
                                    <div class="absolute -right-10 -top-10 h-28 w-28 rounded-full bg-primary-500/10 blur-3xl"></div>
                                    <div v-if="item.requires_pro" class="badge badge-pro absolute right-4 top-4">{{ t('PRO') }}</div>
                                    <div class="relative z-10 flex h-full flex-col">
                                        <div class="mb-5 flex items-start justify-between gap-3">
                                            <div class="flex h-14 w-14 items-center justify-center rounded-[1.15rem] border border-white/60 bg-white/80 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/10" :style="{ boxShadow: `0 12px 30px ${ (item.color || '#1F75FE') }20` }">
                                                <i :class="[item.icon || 'ti ti-wand', 'text-[24px]']" :style="{ color: item.color || '#1F75FE' }"></i>
                                            </div>
                                        </div>

                                        <div class="flex-1">
                                            <h3 class="text-[15px] font-bold tracking-tight text-gray-900 transition-colors group-hover:text-primary-600 dark:text-white">{{ item.name }}</h3>
                                            <p class="mt-2 line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ item.description }}</p>
                                        </div>

                                        <div class="mt-5 flex items-center justify-between gap-3 text-xs text-gray-400">
                                            <span v-if="item.category" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white/90 px-2.5 py-1 text-[10px] font-medium text-gray-500 shadow-sm dark:border-white/10 dark:bg-white/10 dark:text-gray-300">
                                                <i v-if="item.category.icon" :class="item.category.icon" class="text-[10px]" :style="{ color: item.category.color }"></i>
                                                {{ item.category.name }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-primary-600 opacity-90 transition-opacity group-hover:opacity-100 dark:text-primary-300">
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

            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="font-heading text-xl font-black text-gray-900 dark:text-white">
                        <i class="ti ti-folder-search mr-2 text-primary-500"></i>
                        {{ t('Explore Tools') }}
                    </h2>
                </div>

                <div class="grid lg:w-fit grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_200px] lg:items-end lg:ml-auto">
                    <div class="relative">
                        <input
                            v-model="search"
                            type="text"
                            class="ai-tools-search"
                            :placeholder="t('Search tools...')"
                        />
                        <i class="ti ti-search absolute right-0 top-1/2 -translate-y-1/2 text-[16px] text-gray-400"></i>
                    </div>

                    <AppSelect
                        v-model="activeCategory"
                        :options="categoryOptions"
                        :placeholder="t('All categories')"
                        live-search
                    />
                </div>
            </div>

            <div v-if="filtered.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <template v-for="(item, index) in filtered" :key="item.id">
                    <AdSection v-if="index > 0 && index % 4 === 0" zone="between_ai_tools" class="col-span-full" />
                    <Link
                    :href="route('ai.tools.show', item.slug)"
                    class="group card relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:-translate-y-1 hover:border-primary-200 hover:shadow-lg dark:border-white/5 dark:bg-white/[0.03]"
                >
                    <div v-if="item.requires_pro" class="badge badge-pro absolute right-4 top-4">{{ t('PRO') }}</div>

                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl border" :style="{ background: (item.color || '#64748b') + '14', borderColor: (item.color || '#64748b') + '28' }">
                        <i :class="[item.icon || 'ti ti-wand', 'text-[22px]']" :style="{ color: item.color || '#64748b' }"></i>
                    </div>

                    <h3 class="pr-10 text-sm font-bold text-gray-900 transition-colors group-hover:text-primary-600 dark:text-white">{{ item.name }}</h3>
                    <p class="mt-2 line-clamp-2 text-xs leading-6 text-gray-500 dark:text-gray-400">{{ item.description }}</p>

                    <div class="mt-4 flex items-center justify-between text-xs text-gray-400">
                        <div v-if="activeCategory === 'all' && item.category">
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-500 dark:bg-white/[0.05] dark:text-gray-400">
                                <i v-if="item.category.icon" :class="item.category.icon" class="text-[10px]" :style="{ color: item.category.color }"></i>
                                {{ item.category.name }}
                            </span>
                        </div>
                        <span class=" text-primary-400 inline-flex items-center gap-1">
                            <i class="ti ti-sparkles text-sm"></i>
                            {{ t('Open tool') }}
                            <i class="ti ti-arrow-right text-base opacity-0 transition-opacity group-hover:opacity-100"></i>
                        </span>
                    </div>
                </Link>
                </template>
            </div>

            <div v-else class="card mx-auto mt-8 max-w-2xl border border-gray-200 bg-white p-8 text-center dark:border-white/5 dark:bg-white/[0.03]">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                    <i class="ti ti-search text-2xl"></i>
                </div>
                <h3 class="font-heading text-xl font-bold text-gray-900 dark:text-white">{{ t('No tools found') }}</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t("We couldn't find any tools matching your search criteria.") }}</p>
                <button @click="search = ''; activeCategory = 'all'" class="btn-primary mt-5 rounded-xl px-4 py-2 text-sm font-semibold">
                    {{ t('Clear Filters') }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ai-tools-search {
    width: 100%;
    border: 0;
    border-bottom: 1px solid rgb(229 231 235);
    background: transparent;
    padding: 0.5rem 0 0.5rem 0;
    padding-inline-end: 2rem;
    font-size: 0.875rem;
    color: rgb(17 24 39);
    outline: none;
    box-shadow: none;
    transition: border-color 0.15s ease;
}

.ai-tools-search::placeholder {
    color: rgb(156 163 175);
}

.ai-tools-search:focus,
.ai-tools-search:focus-visible {
    border-bottom-color: rgb(16 185 129);
    outline: none;
    box-shadow: none;
    --tw-ring-shadow: 0 0 #0000;
    --tw-ring-offset-shadow: 0 0 #0000;
    --tw-ring-offset-width: 0px;
    --tw-ring-color: transparent;
}

.dark .ai-tools-search {
    border-bottom-color: rgb(255 255 255 / 0.1);
    color: rgb(255 255 255);
}

.dark .ai-tools-search::placeholder {
    color: rgb(107 114 128);
}

.dark .ai-tools-search:focus,
.dark .ai-tools-search:focus-visible {
    border-bottom-color: rgb(16 185 129);
    outline: none;
    box-shadow: none;
    --tw-ring-shadow: 0 0 #0000;
    --tw-ring-offset-shadow: 0 0 #0000;
    --tw-ring-offset-width: 0px;
    --tw-ring-color: transparent;
}

.featured-tools-swiper {
    padding-bottom: 0;
}

.featured-tools-nav {
    position: absolute;
    top: 50%;
    z-index: 10;
    display: inline-flex;
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
</style>
