<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'

const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
const { isDark } = useTheme()
const { t } = useTranslate()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]

interface HomepageSection { 
    id: string; 
    type: string; 
    enabled: boolean; 
    core: boolean; 
    config: Record<string, SectionConfigValue> 
}

const props = defineProps<{ section: HomepageSection }>()

const asString = (v: SectionConfigValue | undefined, fallback = ''): string => 
    typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => 
    Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []

const resolveMediaUrl = (path?: string | null): string => { 
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path
    return `/storage/${path}` 
}

const items = computed(() => asItems(props.section.config.items))

// Columns and Auto change configuration
const columnsCount = computed(() => asString(props.section.config.carousel_layout, '3'))
const isSingleCol = computed(() => columnsCount.value === '1')

const autoplayEnabled = computed(() => asString(props.section.config.carousel_autoplay, '1') === '1')
const autoplayTime = computed(() => Number(asString(props.section.config.carousel_time, '5')) * 1000)

const maxIndex = computed(() => Math.max(0, items.value.length - Number(columnsCount.value)))

// Carousel sliding state
const currentIndex = ref(0)
let autoplayInterval: ReturnType<typeof setInterval> | null = null

function nextSlideList() {
    if (maxIndex.value <= 0) return
    if (currentIndex.value >= maxIndex.value) {
        currentIndex.value = 0
    } else {
        currentIndex.value++
    }
}

function prevSlideList() {
    if (maxIndex.value <= 0) return
    if (currentIndex.value <= 0) {
        currentIndex.value = maxIndex.value
    } else {
        currentIndex.value--
    }
}

function startAutoplay() {
    stopAutoplay()
    if (!autoplayEnabled.value || maxIndex.value <= 0 || isOpen.value) return
    autoplayInterval = setInterval(() => {
        nextSlideList()
    }, autoplayTime.value)
}

function stopAutoplay() {
    if (autoplayInterval) {
        clearInterval(autoplayInterval)
        autoplayInterval = null
    }
}

// Lightbox state
const isOpen = ref(false)
const activeIndex = ref(0)
const activeItem = computed(() => items.value[activeIndex.value] || null)

function openLightbox(index: number) {
    activeIndex.value = index
    isOpen.value = true
    document.body.style.overflow = 'hidden'
    stopAutoplay()
}

function closeLightbox() {
    isOpen.value = false
    document.body.style.overflow = ''
    startAutoplay()
}

function nextSlide() {
    if (items.value.length === 0) return
    activeIndex.value = (activeIndex.value + 1) % items.value.length
}

function prevSlide() {
    if (items.value.length === 0) return
    activeIndex.value = (activeIndex.value - 1 + items.value.length) % items.value.length
}

function handleKeyDown(e: KeyboardEvent) {
    if (!isOpen.value) return
    if (e.key === 'Escape') closeLightbox()
    if (e.key === 'ArrowRight') nextSlide()
    if (e.key === 'ArrowLeft') prevSlide()
}

watch([autoplayEnabled, autoplayTime, maxIndex], () => {
    startAutoplay()
}, { immediate: true })

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown)
    startAutoplay()
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown)
    document.body.style.overflow = ''
    stopAutoplay()
})

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})
</script>

<template>
    <section :class="[isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div :class="effectiveCardWrapperClass">
                <!-- Header Section -->
                <div v-if="asString(section.config.title) || asString(section.config.subtitle) || asString(section.config.icon) || asString(section.config.badge_text)" 
                     :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-16">
                    <!-- Top Position Icon -->
                    <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="asString(section.config.icon)"></i>
                    </div>
                    <div class="w-full">
                        <span v-if="asString(section.config.badge_text)" :class="badgeClass(asString(section.config.section_bg, 'default'), asString(section.config.title_color, 'dark'))">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ asString(section.config.badge_text) }}
                        </span>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="asString(section.config.icon)"></i>
                            </div>
                            <h2 v-if="asString(section.config.title)" :class="['font-black tracking-tight', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.title) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subtitle)" :class="['font-medium max-w-2xl text-base/relaxed', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subtitle) }}</p>
                    </div>
                </div>

                <!-- Custom Premium Carousel Slider Container -->
                <div 
                    v-if="items.length > 0"
                    class="relative w-full group/carousel"
                    @mouseenter="stopAutoplay"
                    @mouseleave="startAutoplay"
                >
                    <!-- Carousel Viewport -->
                    <div class="overflow-hidden rounded-[2.25rem]">
                        <div 
                            class="flex transition-transform duration-500 ease-out"
                            :style="{ transform: `translateX(-${currentIndex * (100 / Number(columnsCount))}%` }"
                        >
                            <!-- Slide Card -->
                            <div 
                                v-for="(item, index) in items" 
                                :key="index"
                                class="px-3 shrink-0 select-none cursor-pointer"
                                :style="{ width: `${100 / Number(columnsCount)}%` }"
                                @click="openLightbox(index)"
                            >
                                <div :class="[isDark ? 'bg-surface-900/40 border border-surface-800/60' : cardBgClass('white')]" class="group relative overflow-hidden rounded-[2.25rem] p-2 shadow-xs transition-all duration-500 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-primary-500/5 hover:border-primary-500/30 dark:hover:bg-surface-900/80">
                                    <!-- Image Wrapper -->
                                    <div 
                                        class="relative overflow-hidden rounded-[1.85rem] w-full"
                                        :class="isSingleCol ? 'h-[450px] md:h-[500px]' : 'aspect-video'"
                                    >
                                        <img 
                                            v-if="item.image_url" 
                                            :src="resolveMediaUrl(String(item.image_url))" 
                                            :alt="String(item.title || '')" 
                                            loading="lazy" 
                                            class="w-full transition-transform duration-700 ease-out group-hover:scale-105"
                                            :class="isSingleCol ? 'h-full object-cover' : 'h-full object-cover'"
                                        />
                                        <div v-else class="flex h-full w-full items-center justify-center bg-gray-100 text-gray-400 dark:bg-surface-700 dark:text-gray-500" :class="isSingleCol ? 'h-full' : ''">
                                            <i class="ti ti-photo text-4xl"></i>
                                        </div>
                                        
                                        <!-- Text Caption Overlay inside the image -->
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/45 to-transparent p-6 pt-16 flex flex-col justify-end transition-transform duration-500 group-hover:translate-y-1">
                                            <h3 v-if="item.title" class="text-base md:text-lg font-black text-white leading-tight mb-1 drop-shadow-md">
                                                {{ item.title }}
                                            </h3>
                                            <p v-if="item.description" class="text-xs text-gray-200/95 line-clamp-2 leading-relaxed max-w-[95%]">
                                                {{ item.description }}
                                            </p>
                                        </div>

                                        <!-- Zoom Hover Overlay Indicator -->
                                        <div class="absolute top-4 right-4 flex h-8 w-8 scale-75 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-md opacity-0 transition-all duration-300 group-hover:scale-100 group-hover:opacity-100">
                                            <i class="ti ti-zoom-in text-sm"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Slider Navigation arrows (overlapping edges, visible on hover) -->
                    <button 
                        v-if="maxIndex > 0"
                        type="button" 
                        @click="prevSlideList"
                        class="absolute -left-5 top-1/2 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white text-gray-800 shadow-lg border border-gray-100 hover:bg-primary-500 hover:text-white transition-all opacity-0 group-hover/carousel:opacity-100 hover:scale-105 z-10 dark:bg-surface-800 dark:border-surface-700 dark:text-white"
                        aria-label="Previous slide"
                    >
                        <i class="ti ti-chevron-left text-lg"></i>
                    </button>
                    <button 
                        v-if="maxIndex > 0"
                        type="button" 
                        @click="nextSlideList"
                        class="absolute -right-5 top-1/2 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white text-gray-800 shadow-lg border border-gray-100 hover:bg-primary-500 hover:text-white transition-all opacity-0 group-hover/carousel:opacity-100 hover:scale-105 z-10 dark:bg-surface-800 dark:border-surface-700 dark:text-white"
                        aria-label="Next slide"
                    >
                        <i class="ti ti-chevron-right text-lg"></i>
                    </button>
                </div>
                
                <!-- Empty state fallback -->
                <div v-else class="text-center py-12 rounded-[2.25rem] border border-dashed border-gray-200 dark:border-surface-800">
                    <i class="ti ti-photo-sensor text-4xl text-gray-400 dark:text-gray-600 mb-3"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('No carousel items added yet.') }}</p>
                </div>
            </div>
        </div>

        <!-- Premium Glassmorphic Lightbox Slide modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-250 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div 
                    v-if="isOpen && activeItem" 
                    class="fixed inset-0 z-9999 flex items-center justify-center bg-black/90 p-4 backdrop-blur-xl md:p-10"
                    @click.self="closeLightbox"
                >
                    <!-- Close Button -->
                    <button 
                        type="button" 
                        @click="closeLightbox"
                        class="absolute top-4 right-4 z-50 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white/80 transition hover:bg-white/20 hover:text-white"
                        aria-label="Close"
                    >
                        <i class="ti ti-x text-2xl"></i>
                    </button>

                    <!-- Responsive Slideshow container -->
                    <div class="relative flex flex-col items-center justify-between w-full h-full max-h-[85vh] max-w-5xl">
                        
                        <!-- Media viewport -->
                        <div class="relative flex-1 w-full flex items-center justify-center min-h-0 px-4">
                            <img 
                                v-if="activeItem.image_url" 
                                :src="resolveMediaUrl(String(activeItem.image_url))" 
                                :alt="String(activeItem.title || '')" 
                                class="max-h-[55vh] sm:max-h-[65vh] md:max-h-[75vh] max-w-full object-contain rounded-2xl select-none shadow-2xl"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center text-gray-500">
                                <i class="ti ti-photo text-6xl"></i>
                            </div>

                            <!-- Left/Right Slide Cycle controls -->
                            <button 
                                v-if="items.length > 1"
                                type="button" 
                                @click="prevSlide"
                                class="absolute left-0 top-1/2 -translate-y-1/2 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white/80 transition hover:bg-white/20 hover:text-white backdrop-blur-md"
                                aria-label="Previous"
                            >
                                <i class="ti ti-chevron-left text-xl"></i>
                            </button>
                            <button 
                                v-if="items.length > 1"
                                type="button" 
                                @click="nextSlide"
                                class="absolute right-0 top-1/2 -translate-y-1/2 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white/80 transition hover:bg-white/20 hover:text-white backdrop-blur-md"
                                aria-label="Next"
                            >
                                <i class="ti ti-chevron-right text-xl"></i>
                            </button>
                        </div>

                        <!-- Slideshow Info panel at the bottom -->
                        <div v-if="activeItem.title || activeItem.description || activeItem.link_url" class="w-full bg-black/45 backdrop-blur-md border border-white/10 rounded-2xl p-6 text-white mt-6 shrink-0 shadow-lg">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1.5 text-left flex-1 min-w-0">
                                    <span class="inline-block text-xs font-bold uppercase tracking-wider text-primary-400">
                                        {{ t('Item') }} {{ activeIndex + 1 }} / {{ items.length }}
                                    </span>
                                    <h3 v-if="activeItem.title" class="text-xl md:text-2xl font-black text-white leading-tight">
                                        {{ activeItem.title }}
                                    </h3>
                                    <p v-if="activeItem.description" class="text-sm text-gray-300 leading-relaxed max-w-3xl">
                                        {{ activeItem.description }}
                                    </p>
                                </div>

                                <!-- Action / CTA Button -->
                                <div class="shrink-0 flex items-center">
                                    <a 
                                        v-if="activeItem.link_url" 
                                        :href="String(activeItem.link_url)" 
                                        class="inline-flex items-center gap-2 rounded-xl bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-primary-500/20 transition hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-xl dark:shadow-none"
                                    >
                                        {{ t('Learn more') }}
                                        <i class="ti ti-arrow-right text-sm"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>
