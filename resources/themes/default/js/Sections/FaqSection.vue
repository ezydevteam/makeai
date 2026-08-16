<script setup lang="ts">
import { loadGsapNearViewport } from '../composables/useGsapScrollAnimation'
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'

const {
    sectionBgClass,
    titleColorClass,
    subtitleColorClass,
    titleAlignClass,
    titleSizeClass,
    cardBgClass,
    cardWrapperClass,
    badgeClass,
    sectionIconClass,
    sectionHeaderClass,
    sectionPaddingStyle,
    sectionVisibilityClass,
    sectionAnchorId
} = useSectionStyle()

const { isDark } = useTheme()
const { t } = useTranslate()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]

interface FaqCategory {
    id: number
    name: string
    sort_order: number
}

interface Faq {
    id: number
    question: string
    answer: string
    category_id: number | null
    sort_order: number
    category?: FaqCategory
}

interface HomepageSection {
    id: string
    type: string
    enabled: boolean
    core: boolean
    config: Record<string, SectionConfigValue>
}

const props = defineProps<{ section: HomepageSection; faqs: Faq[] }>()

const asString = (v: SectionConfigValue | undefined, fallback = ''): string =>
    typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const openFaqId = ref<number | null>(null)
const selectedCategoryId = ref<number | 'all'>('all')

// Admin can restrict the section to specific FAQ categories; empty = show all.
const allowedCategoryIds = computed<number[]>(() => {
    const raw = props.section.config.faq_categories
    if (!Array.isArray(raw)) return []
    return raw.map((value) => Number(value)).filter((value) => Number.isFinite(value))
})

const scopedFaqs = computed<Faq[]>(() => {
    if (allowedCategoryIds.value.length === 0) return props.faqs
    const allow = new Set(allowedCategoryIds.value)
    return props.faqs.filter((faq) => {
        const categoryId = faq.category?.id ?? faq.category_id
        return categoryId != null && allow.has(Number(categoryId))
    })
})

// Category selector layout: 'hidden' | 'top' | 'sidebar'. Falls back to the legacy
// boolean toggle for sections saved before the style select existed.
const categoryTabsStyle = computed<'hidden' | 'top' | 'sidebar'>(() => {
    const raw = asString(props.section.config.category_tabs_style)
    if (raw === 'hidden' || raw === 'top' || raw === 'sidebar') return raw
    const legacy = props.section.config.show_category_tabs
    const legacyOff = legacy === false || legacy === 0 || legacy === '0' || legacy === 'false'
    return legacyOff ? 'hidden' : 'sidebar'
})

const showCategoryTabs = computed<boolean>(() => categoryTabsStyle.value !== 'hidden')

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})

// Build dynamic categories list with "All" option
const categories = computed(() => {
    const list: { id: number | 'all'; name: string }[] = [{ id: 'all', name: t('All') }]
    const seen = new Set<number>()
    scopedFaqs.value.forEach(faq => {
        if (faq.category && !seen.has(faq.category.id)) {
            seen.add(faq.category.id)
            list.push({ id: faq.category.id, name: faq.category.name })
        } else if (faq.category_id && !seen.has(faq.category_id)) {
            seen.add(faq.category_id)
            list.push({ id: faq.category_id, name: `Category ${faq.category_id}` })
        }
    })
    return list
})

// Get item count per category
const getCategoryCount = (categoryId: number | 'all') => {
    if (categoryId === 'all') return scopedFaqs.value.length
    return scopedFaqs.value.filter(faq =>
        faq.category_id === categoryId ||
        (faq.category && faq.category.id === categoryId)
    ).length
}

// Show the category selector only when enabled by the admin and there is more than one
// category to choose between. `categories` leads with the synthetic "All" entry, so the
// comparison is against the real ones — restricting the section to a single category
// leaves nothing to filter, and an "All / <only category>" pair is just noise.
const realCategoryCount = computed(() => categories.value.length - 1)
const showTabs = computed(() => showCategoryTabs.value && realCategoryCount.value > 1)
const showSidebarTabs = computed(() => showTabs.value && categoryTabsStyle.value === 'sidebar')
const showTopTabs = computed(() => showTabs.value && categoryTabsStyle.value === 'top')

// Sidebar tabs → wide dual-column; top tabs → single column with room for the tab bar;
// tabs hidden by the admin → single column at max-w-4xl; single category → compact max-w-3xl.
const containerMaxWidth = computed(() => {
    if (showSidebarTabs.value) return 'max-w-6xl'
    // `!` is required — app.ts injects `main .mx-auto { max-width: var(--page-width) !important }`.
    if (showTopTabs.value) return '!max-w-5xl'
    return showCategoryTabs.value ? 'max-w-3xl' : '!max-w-4xl'
})

const tabsAlignClass = computed(() =>
    titleAlignClass(asString(props.section.config.title_align, 'center')) === 'text-center'
        ? 'justify-center'
        : 'justify-start'
)

// Live filter FAQs (by category only, search removed)
const filteredFaqs = computed(() => {
    let items = scopedFaqs.value

    // Category filter
    if (selectedCategoryId.value !== 'all') {
        items = items.filter(faq =>
            faq.category_id === selectedCategoryId.value ||
            (faq.category && faq.category.id === selectedCategoryId.value)
        )
    }

    const max = parseInt(String(props.section.config.max_items ?? 12), 10)
    return items.slice(0, max)
})

// An accordion left open in the previous category made the list swap jump by its
// expanded height — collapse it so only the list content changes.
watch(selectedCategoryId, () => {
    openFaqId.value = null
})

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  const gsapLoaded = await loadGsapNearViewport(sectionRef)
  if (! gsapLoaded) return
  const { gsap, ScrollTrigger } = gsapLoaded

  gsapCtx = gsap.context(() => {
    // Section Header Entrance
    const header = sectionRef.value!.querySelector('.section-header')
    if (header) {
      gsap.from(header, {
        opacity: 0,
        y: 30,
        duration: 0.7,
        ease: 'power2.out',
        immediateRender: false,
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        },
      })
    }

    // FAQ Items — Staggered Entrance
    const cards = sectionRef.value!.querySelectorAll('.faq-item')
    if (cards.length) {
      gsap.from(cards, {
        opacity: 0,
        y: 35,
        duration: 0.5,
        stagger: 0.08,
        ease: 'power2.out',
        immediateRender: false,
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        }
      })
    }
  }, sectionRef.value!)

  setTimeout(() => {
    ScrollTrigger.refresh()
  }, 100)
})

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section
        :id="sectionAnchorId(section.config.section_anchor)"
        ref="sectionRef"
        :class="[
            sectionVisibilityClass(asString(section.config.visibility, 'all')),
            isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))
        ]"
        :style="sectionPaddingStyle(section.config.vertical_padding, '96')"
        class="transition-colors duration-300 overflow-hidden"
    >
        <div :class="[containerMaxWidth]" class="mx-auto px-6">
            <div :class="effectiveCardWrapperClass">

                <!-- Section Header -->
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-16 section-header">
                    <!-- Top Position Icon -->
                    <div v-if="(section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle') && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle'"></i>
                    </div>

                    <div class="w-full">
                        <span v-if="asString(section.config.badge_text)" :class="badgeClass(asString(section.config.section_bg, 'default'), asString(section.config.title_color, 'dark'))">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ t(asString(section.config.badge_text)) }}
                        </span>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="(section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle') && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle'"></i>
                            </div>
                            <h2 :class="['font-bold', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">
                                {{ t(asString(section.config.heading ?? section.config.title, t('Frequently Asked Questions'))) }}
                            </h2>
                        </div>
                        <p v-if="asString(section.config.subheading ?? section.config.subtitle)" :class="['font-medium mt-4 max-w-2xl', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">
                            {{ t(asString(section.config.subheading ?? section.config.subtitle)) }}
                        </p>
                    </div>
                </div>

                <!-- Top Tabs: pill row above the questions -->
                <div v-if="showTopTabs" :class="[tabsAlignClass]" class="mb-10 flex section-header">
                    <div :class="[tabsAlignClass]" class="max-w-full flex flex-wrap items-center gap-2 overflow-x-auto scrollbar-none">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="selectedCategoryId = cat.id"
                            type="button"
                            :class="[
                                selectedCategoryId === cat.id
                                    ? 'bg-primary-600 text-white border-primary-600 shadow-sm shadow-primary-500/25 dark:bg-primary-500 dark:border-primary-500 dark:text-white'
                                    : 'bg-gray-50 hover:bg-gray-100 text-gray-700 hover:text-gray-900 border-gray-100 hover:border-gray-200 dark:bg-surface-900/40 dark:hover:bg-surface-900/70 dark:text-gray-300 dark:hover:text-white dark:border-surface-800'
                            ]"
                            class="shrink-0 flex items-center gap-2 rounded-full border px-4 py-2.5 text-sm font-semibold transition-all duration-200 cursor-pointer"
                        >
                            <span>{{ cat.name }}</span>
                            <span
                                :class="[
                                    selectedCategoryId === cat.id
                                        ? 'bg-black/20 text-white'
                                        : 'bg-gray-200/80 text-gray-500 dark:bg-surface-800 dark:text-gray-400'
                                ]"
                                class="rounded-full px-1.5 py-0.5 text-[10px] font-bold transition-all duration-200"
                            >
                                {{ getCategoryCount(cat.id) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Dual Column Layout or Single Column Container -->
                <div :class="[showSidebarTabs ? 'lg:grid lg:grid-cols-12 lg:gap-12 lg:items-start' : '']">

                    <!-- Sticky Sidebar/Header Column: Category selector -->
                    <div v-if="showSidebarTabs" class="lg:col-span-4 mb-10 lg:mb-0 lg:sticky lg:top-24 space-y-6 section-header">


                        <!-- Desktop Category List (Vertical Tabs) -->
                        <div class="hidden lg:block space-y-2 pt-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 px-1 mb-3">
                                {{ t('Categories') }}
                            </h3>
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                @click="selectedCategoryId = cat.id"
                                type="button"
                                :class="[
                                    selectedCategoryId === cat.id
                                        ? 'bg-primary-50 text-primary border-primary-100 shadow-sm shadow-primary-500/15 dark:!bg-primary-900/30 dark:text-white dark:border-primary-900/30'
                                        : 'bg-gray-50/50 hover:bg-gray-100 text-gray-700 hover:text-gray-900 border-gray-100/60 hover:border-gray-200/80 dark:bg-surface-900/20 dark:hover:bg-surface-900/80 dark:text-gray-300 dark:hover:text-white dark:border-surface-800/40 dark:hover:border-surface-800/80'
                                ]"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold rounded-xl border text-left transition-all duration-200 cursor-pointer"
                            >
                                <span>{{ cat.name }}</span>
                                <span
                                    :class="[
                                        selectedCategoryId === cat.id
                                            ? 'bg-primary-400 text-white'
                                            : 'bg-gray-200/60 text-gray-600 dark:bg-surface-850 dark:text-gray-400'
                                    ]"
                                    class="text-xs px-2 py-0.5 rounded-full font-bold transition-all duration-200"
                                >
                                    {{ getCategoryCount(cat.id) }}
                                </span>
                            </button>
                        </div>

                        <!-- Mobile/Tablet Category List (Horizontal Scrolling Tabs) -->
                        <div class="lg:hidden w-full overflow-hidden">
                            <div class="flex overflow-x-auto gap-2 pb-3 scrollbar-none -mx-4 px-4">
                                <button
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    @click="selectedCategoryId = cat.id"
                                    type="button"
                                    :class="[
                                        selectedCategoryId === cat.id
                                            ? 'bg-primary-600 text-white shadow-sm shadow-primary-500/15 dark:bg-primary-500 dark:text-white'
                                            : 'bg-gray-50 hover:bg-gray-100 text-gray-700 dark:bg-surface-900/40 dark:hover:bg-surface-900/60 dark:text-gray-300'
                                    ]"
                                    class="shrink-0 flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-full border border-gray-100 dark:border-surface-800 transition-all cursor-pointer"
                                >
                                    <span>{{ cat.name }}</span>
                                    <span
                                        :class="[
                                            selectedCategoryId === cat.id
                                                ? 'bg-black/20 text-white'
                                                : 'bg-gray-200/80 text-gray-500 dark:bg-surface-800 dark:text-gray-400'
                                        ]"
                                        class="text-[10px] px-1.5 py-0.2 rounded-full font-bold"
                                    >
                                        {{ getCategoryCount(cat.id) }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- FAQs Column -->
                    <div :class="[showSidebarTabs ? 'lg:col-span-8' : '']">
                        <div v-if="filteredFaqs.length > 0" class="relative">
                            <!-- Keyed per category with out-in so the outgoing list never overlaps
                                 the incoming one (that overlap read as a flicker on tab switch). -->
                            <Transition name="faq-list" mode="out-in">
                            <div :key="String(selectedCategoryId)" class="space-y-4">
                                <div
                                    v-for="faq in filteredFaqs"
                                    :key="faq.id"
                                    :class="[
                                        openFaqId === faq.id
                                            ? 'bg-gradient-to-r from-primary-50/40 to-transparent dark:from-primary-950/10 dark:to-transparent border-primary-500/30 dark:border-primary-500/30 shadow-md shadow-primary-500/5 border-l-4 border-l-primary-500'
                                            : 'bg-white/60 hover:bg-white border-gray-100 hover:border-primary-500/20 dark:bg-surface-900/40 dark:hover:bg-surface-900/60 dark:border-surface-800/80 dark:hover:border-surface-800'
                                    ]"
                                    class="overflow-hidden rounded-2xl border backdrop-blur-md transition-all duration-300 hover:-translate-y-0.5 faq-item group"
                                >
                                    <button
                                        @click="openFaqId = openFaqId === faq.id ? null : faq.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-4 p-4 text-left cursor-pointer"
                                    >
                                        <div class="flex flex-col gap-1.5">
                                            <span
                                                :class="[
                                                    openFaqId === faq.id
                                                        ? 'text-primary-600 dark:text-primary-400'
                                                        : 'text-gray-900 dark:text-white'
                                                ]"
                                                class="text-sm font-semibold md:text-base leading-snug transition-colors duration-200"
                                            >
                                                {{ t(faq.question) }}
                                            </span>
                                            <!-- Category tag badge (only in 'All' category view to distinguish different groups) -->
                                            <span
                                                v-if="selectedCategoryId === 'all' && faq.category && showCategoryTabs"
                                                class="text-[10px] font-bold text-gray-400 dark:text-gray-500 bg-gray-100/60 dark:bg-surface-800/60 px-2 py-0.5 rounded-full uppercase tracking-wider w-fit"
                                            >
                                                {{ faq.category.name }}
                                            </span>
                                        </div>

                                        <!-- Morphing +/- Icon -->
                                        <div
                                            :class="[
                                                openFaqId === faq.id
                                                    ? 'bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400 rotate-180'
                                                    : 'bg-gray-100/60 text-gray-500 group-hover:bg-gray-100 dark:bg-surface-800/60 dark:text-gray-400 dark:group-hover:bg-surface-800'
                                            ]"
                                            class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-all duration-300"
                                        >
                                            <span class="absolute h-[2px] w-3.5 bg-current rounded-full transition-transform duration-300"></span>
                                            <span
                                                class="absolute h-3.5 w-[2px] bg-current rounded-full transition-transform duration-300"
                                                :class="openFaqId === faq.id ? 'rotate-90 opacity-0' : ''"
                                            ></span>
                                        </div>
                                    </button>

                                    <!-- Smooth Height Collapsing content wrapper using CSS Grid -->
                                    <div
                                        class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                                        :class="openFaqId === faq.id ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                                    >
                                        <div class="overflow-hidden">
                                            <div class="px-4 pb-4 text-sm !text-gray-600 dark:!text-gray-400" v-html="faq.answer"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </Transition>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="py-20 text-center rounded-3xl border border-dashed border-gray-200 dark:border-surface-800 bg-gray-50/20 dark:bg-surface-900/10 backdrop-blur-sm">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-50 text-gray-400 dark:bg-surface-900 dark:text-gray-500 mb-4 border border-gray-100 dark:border-surface-800">
                                <i class="ti ti-help-off text-2xl"></i>
                            </div>
                            <p class="text-base font-bold text-gray-900 dark:text-white mb-1">
                                {{ t('No FAQs available') }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto px-4">
                                {{ t('No FAQs yet. Add some from the admin panel.') }}
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</template>

<style scoped>
/* Smooth list filtering animation — the outgoing list finishes before the
   incoming one mounts (mode="out-in"), so nothing overlaps mid-switch. */
.faq-list-enter-active {
  transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.faq-list-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.faq-list-enter-from {
  opacity: 0;
  transform: translateY(12px);
}
.faq-list-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* Hide scrollbar for Chrome, Safari and Opera */
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
