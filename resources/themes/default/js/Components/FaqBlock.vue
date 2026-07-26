<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

interface FaqCategory {
    id: number
    name: string
    sort_order?: number
}

interface Faq {
    id: number
    question: string
    answer: string
    category_id: number | null
    category?: FaqCategory | null
}

const props = withDefaults(defineProps<{
    faqs: Faq[]
    tabsStyle?: 'hidden' | 'top' | 'sidebar'
    heading?: string | null
}>(), {
    tabsStyle: 'top',
    heading: null,
})

const openFaqId = ref<number | null>(null)
const selectedCategoryId = ref<number | 'all'>('all')

const categories = computed(() => {
    const list: { id: number | 'all'; name: string }[] = [{ id: 'all', name: t('All') }]
    const seen = new Set<number>()
    props.faqs.forEach((faq) => {
        const id = faq.category?.id ?? faq.category_id
        if (id == null || seen.has(id)) return
        seen.add(id)
        list.push({ id, name: faq.category?.name ?? `Category ${id}` })
    })
    return list
})

const getCategoryCount = (categoryId: number | 'all') => {
    if (categoryId === 'all') return props.faqs.length
    return props.faqs.filter((faq) => (faq.category?.id ?? faq.category_id) === categoryId).length
}

// `categories` leads with the synthetic "All" entry, so compare against the real ones.
// A selector is only worth showing when there are at least two to choose between —
// scoping the shortcode to one category (`[faqs category="2"]`) leaves nothing to filter,
// so the tabs turn themselves off no matter what `tabs` asked for.
const realCategoryCount = computed(() => categories.value.length - 1)
const showTabs = computed(() => props.tabsStyle !== 'hidden' && realCategoryCount.value > 1)
const showSidebarTabs = computed(() => showTabs.value && props.tabsStyle === 'sidebar')
const showTopTabs = computed(() => showTabs.value && props.tabsStyle === 'top')

const filteredFaqs = computed(() => {
    if (selectedCategoryId.value === 'all') return props.faqs
    return props.faqs.filter((faq) => (faq.category?.id ?? faq.category_id) === selectedCategoryId.value)
})

// Collapse any open answer on switch so the list swap doesn't jump by its height.
watch(selectedCategoryId, () => {
    openFaqId.value = null
})
</script>

<template>
    <div v-if="faqs.length" class="not-prose my-10">
        <h2 v-if="heading" class="mb-8 text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
            {{ heading }}
        </h2>

        <!-- Top Tabs: pill row above the questions -->
        <div v-if="showTopTabs" class="mb-8 flex flex-wrap items-center gap-2">
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
                            ? 'bg-white/25 text-white'
                            : 'bg-gray-200/80 text-gray-500 dark:bg-surface-800 dark:text-gray-400'
                    ]"
                    class="rounded-full px-1.5 py-0.5 text-[10px] font-bold transition-all duration-200"
                >
                    {{ getCategoryCount(cat.id) }}
                </span>
            </button>
        </div>

        <div :class="[showSidebarTabs ? 'lg:grid lg:grid-cols-12 lg:gap-10 lg:items-start' : '']">

            <!-- Sidebar Tabs -->
            <div v-if="showSidebarTabs" class="lg:col-span-4 mb-8 lg:mb-0 lg:sticky lg:top-24">
                <!-- Desktop vertical list -->
                <div class="hidden lg:block space-y-2">
                    <h3 class="mb-3 px-1 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
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
                        class="w-full flex items-center justify-between rounded-xl border px-4 py-3 text-left text-sm font-semibold transition-all duration-200 cursor-pointer"
                    >
                        <span>{{ cat.name }}</span>
                        <span
                            :class="[
                                selectedCategoryId === cat.id
                                    ? 'bg-primary-400 text-white'
                                    : 'bg-gray-200/60 text-gray-600 dark:bg-surface-850 dark:text-gray-400'
                            ]"
                            class="rounded-full px-2 py-0.5 text-xs font-bold transition-all duration-200"
                        >
                            {{ getCategoryCount(cat.id) }}
                        </span>
                    </button>
                </div>

                <!-- Mobile horizontal pills -->
                <div class="lg:hidden w-full overflow-hidden">
                    <div class="flex gap-2 overflow-x-auto pb-3 scrollbar-none">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="selectedCategoryId = cat.id"
                            type="button"
                            :class="[
                                selectedCategoryId === cat.id
                                    ? 'bg-primary-600 text-white shadow-sm shadow-primary-500/15 dark:bg-primary-500'
                                    : 'bg-gray-50 hover:bg-gray-100 text-gray-700 dark:bg-surface-900/40 dark:hover:bg-surface-900/60 dark:text-gray-300'
                            ]"
                            class="shrink-0 flex items-center gap-2 rounded-full border border-gray-100 px-4 py-2.5 text-xs font-bold transition-all cursor-pointer dark:border-surface-800"
                        >
                            <span>{{ cat.name }}</span>
                            <span
                                :class="[
                                    selectedCategoryId === cat.id
                                        ? 'bg-white/25 text-white'
                                        : 'bg-gray-200/80 text-gray-500 dark:bg-surface-800 dark:text-gray-400'
                                ]"
                                class="rounded-full px-1.5 text-[10px] font-bold"
                            >
                                {{ getCategoryCount(cat.id) }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Questions -->
            <div :class="[showSidebarTabs ? 'lg:col-span-8' : '']">
                <Transition name="faq-block" mode="out-in">
                    <div :key="String(selectedCategoryId)" class="space-y-4">
                        <div
                            v-for="faq in filteredFaqs"
                            :key="faq.id"
                            :class="[
                                openFaqId === faq.id
                                    ? 'bg-gradient-to-r from-primary-50/40 to-transparent dark:from-primary-950/10 dark:to-transparent border-primary-500/30 dark:border-primary-500/30 shadow-md shadow-primary-500/5 border-l-4 border-l-primary-500'
                                    : 'bg-white/60 hover:bg-white border-gray-100 hover:border-primary-500/20 dark:bg-surface-900/40 dark:hover:bg-surface-900/60 dark:border-surface-800/80 dark:hover:border-surface-800'
                            ]"
                            class="group overflow-hidden rounded-2xl border backdrop-blur-md transition-all duration-300 hover:-translate-y-0.5"
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
                                        {{ faq.question }}
                                    </span>
                                    <span
                                        v-if="selectedCategoryId === 'all' && faq.category && showTabs"
                                        class="w-fit rounded-full bg-gray-100/60 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:bg-surface-800/60 dark:text-gray-500"
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
                                    <span class="absolute h-[2px] w-3.5 rounded-full bg-current transition-transform duration-300"></span>
                                    <span
                                        class="absolute h-3.5 w-[2px] rounded-full bg-current transition-transform duration-300"
                                        :class="openFaqId === faq.id ? 'rotate-90 opacity-0' : ''"
                                    ></span>
                                </div>
                            </button>

                            <div
                                class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                                :class="openFaqId === faq.id ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="overflow-hidden">
                                    <div class="cms-content px-4 pb-4 text-sm !text-gray-600 dark:!text-gray-400" v-html="faq.answer"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </div>
</template>

<style scoped>
.faq-block-enter-active {
    transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.faq-block-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.faq-block-enter-from {
    opacity: 0;
    transform: translateY(12px);
}
.faq-block-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
