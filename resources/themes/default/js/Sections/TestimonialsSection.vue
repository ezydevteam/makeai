<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Pagination, Autoplay } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { useTheme } from '@/Composables/useTheme'

const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface Testimonial { id: number; name: string; role: string | null; company: string | null; avatar: string | null; content: string; rating: number; is_featured: boolean; source: string }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; testimonials: Testimonial[] }>()
const { t } = useTranslate()

const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const resolveMediaUrl = (path?: string | null): string => { if (!path) return ''; if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path; return `/storage/${path}` }
const stars = (n: number): boolean[] => Array.from({ length: 5 }, (_, i) => i < n)
const getTestimonialsSlice = (): Testimonial[] => {
    const max = parseInt(String(props.section.config.max_items ?? 6), 10)
    const source = asString(props.section.config.source, 'all')
    const list = source === 'featured' ? props.testimonials.filter(t => t.is_featured) : props.testimonials
    return list.slice(0, max)
}
const testimonialsCardClass = (style: string): string => {
    if (isDark.value) {
        const darkBase = 'bg-surface-900/40 border-surface-800/60 hover:bg-surface-900/80 hover:border-primary-500/30 hover:shadow-2xl transition-all duration-300'
        return {
            simple: `relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] border p-7 ${darkBase} transform translate-z-0`,
            bordered: `relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] border p-8 ${darkBase} transform translate-z-0`,
            spotlight: `relative flex h-full flex-col gap-5 items-center text-center overflow-hidden rounded-[1.5rem] border border-primary-500/30 bg-surface-900/60 p-8 hover:bg-surface-900/90 transform translate-z-0`
        }[style] ?? `relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] border p-8 ${darkBase} transform translate-z-0`
    }
    return {
        simple: 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white/90 p-7 transition-all duration-300 hover:border-primary-200 dark:bg-surface-900/80 transform translate-z-0',
        bordered: 'bordered-gradient-card relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] p-8 transition-all duration-300 hover:shadow-[0_28px_70px_rgba(31,117,254,0.12)] transform translate-z-0',
        spotlight: 'relative flex h-full flex-col gap-5 items-center text-center overflow-hidden rounded-[1.5rem] border border-primary-100 bg-gradient-to-br from-primary-50 via-white to-sky-50 p-8 transition-all duration-300 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(16,185,129,0.16)] dark:border-primary-900/40 dark:from-primary-950/30 dark:via-surface-900 dark:to-surface-900 transform translate-z-0',
    }[style] ?? 'bordered-gradient-card relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] p-8 transition-all duration-300 hover:shadow-[0_28px_70px_rgba(31,117,254,0.12)] transform translate-z-0'
}

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})

const sliderColumns = computed(() => parseInt(asString(props.section.config.slider_columns, '3'), 10))
const isCentered = computed(() => sliderColumns.value === 1 || asString(props.section.config.card_style, 'bordered') === 'spotlight')
const hideControls = computed(() => asString(props.section.config.hide_controls, '0') === '1')
const autoplayEnabled = computed(() => asString(props.section.config.autoplay_enabled, '0') === '1')

const swiperModules = computed(() => {
    const modules = [Pagination]
    if (!hideControls.value) modules.push(Navigation)
    if (autoplayEnabled.value) modules.push(Autoplay)
    return modules
})

const autoplayConfig = computed(() => {
    if (!autoplayEnabled.value) return false
    return {
        delay: 4000,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
    }
})

const swiperBreakpoints = computed(() => {
    const cols = sliderColumns.value
    return {
        320: { slidesPerView: 1, spaceBetween: 16 },
        640: { slidesPerView: Math.min(2, cols), spaceBetween: 20 },
        1024: { slidesPerView: cols, spaceBetween: 24 }
    }
})

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Section Header Entrance
    const header = sectionRef.value!.querySelector('.mb-16')
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

    // Testimonial Cards — Staggered Entrance
    const cards = sectionRef.value!.querySelectorAll('.testimonial-card')
    if (cards.length) {
      gsap.from(cards, {
        opacity: 0,
        y: 45,
        duration: 0.6,
        stagger: 0.1,
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
    <section ref="sectionRef" :class="[isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-16">
                    <!-- Top Position Icon -->
                    <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="asString(section.config.icon)"></i>
                    </div>
                    <div class="w-full">
                        <div v-if="asString(section.config.badge_text)" :class="[badgeClass(asString(section.config.section_bg, 'default'), asString(section.config.title_color, 'dark'))]">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ asString(section.config.badge_text) }}
                        </div>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="asString(section.config.icon)"></i>
                            </div>
                            <h2 :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.heading ?? section.config.title, t('What Our Users Say')) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subheading ?? section.config.subtitle)" :class="['font-medium mt-4 max-w-2xl', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
                    </div>
                </div>

                <div v-if="getTestimonialsSlice().length > 0" class="testimonials-slider-container relative">
                    <Swiper
                        :modules="swiperModules"
                        :slides-per-view="1"
                        :space-between="24"
                        :pagination="{
                            el: '.testimonials-pagination',
                            clickable: true,
                        }"
                        :navigation="{
                            nextEl: '.testimonials-button-next',
                            prevEl: '.testimonials-button-prev',
                        }"
                        :autoplay="autoplayConfig"
                        :breakpoints="swiperBreakpoints"
                        class="testimonials-swiper overflow-hidden"
                    >
                        <SwiperSlide v-for="testimonial in getTestimonialsSlice()" :key="testimonial.id" class="!h-auto flex flex-col">
                            <div :class="[sliderColumns === 1 ? 'relative flex h-full flex-col gap-6 p-4 text-center items-center justify-center bg-transparent border-0 shadow-none' : testimonialsCardClass(asString(section.config.card_style, 'bordered')), 'h-full flex-1 testimonial-card']">
                                <div v-if="sliderColumns !== 1 && asString(section.config.card_style, 'spotlight') === 'spotlight'" class="absolute -right-12 -top-12 h-28 w-28 rounded-full bg-primary-500/10 blur-3xl"></div>
                                <div class="relative z-10 flex items-center gap-0.5" :class="[isCentered ? 'justify-center' : '']">
                                    <svg v-for="(filled, i) in stars(testimonial.rating)" :key="i" class="h-4 w-4" :class="filled ? 'text-yellow-400' : 'text-gray-200 dark:text-surface-700'" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                </div>
                                <p class="relative z-10 flex-1 leading-relaxed !text-gray-700 dark:!text-gray-300" :class="[sliderColumns === 1 ? 'max-w-3xl text-lg md:text-xl font-medium' : 'text-sm']">&ldquo;{{ testimonial.content }}&rdquo;</p>
                                <div class="relative z-10 flex border-t border-gray-100 pt-4 dark:border-surface-700 w-full" :class="[isCentered ? 'flex-col items-center justify-center border-t-0 gap-2' : 'items-center gap-3']">
                                    <div v-if="testimonial.avatar" class="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-surface-700">
                                        <img :src="resolveMediaUrl(testimonial.avatar)" :alt="testimonial.name" class="h-full w-full object-cover">
                                    </div>
                                    <div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-black text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">{{ testimonial.name.charAt(0) }}</div>
                                    <div class="min-w-0" :class="[isCentered ? 'text-center' : '']">
                                        <p class="truncate text-base font-black !text-gray-900 dark:!text-white">{{ testimonial.name }}</p>
                                        <p class="truncate text-xs !text-gray-500 dark:!text-gray-400">{{ [testimonial.role, testimonial.company].filter(Boolean).join(' · ') }}</p>
                                    </div>
                                </div>
                            </div>
                        </SwiperSlide>
                    </Swiper>

                    <!-- Navigation buttons -->
                    <div v-if="!hideControls" class="hidden md:block">
                        <button class="testimonials-button-prev absolute -left-4 top-1/2 z-20 -translate-y-1/2 flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-md hover:bg-gray-50 hover:text-primary-600 active:scale-95 transition-all dark:!border-surface-800/60 dark:!bg-surface-900/60 dark:!text-gray-300 dark:hover:!bg-surface-800/80 dark:hover:!border-primary-500/30">
                            <i class="ti ti-chevron-left text-lg"></i>
                        </button>
                        <button class="testimonials-button-next absolute -right-4 top-1/2 z-20 -translate-y-1/2 flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-md hover:bg-gray-50 hover:text-primary-600 active:scale-95 transition-all dark:!border-surface-800/60 dark:!bg-surface-900/60 dark:!text-gray-300 dark:hover:!bg-surface-800/80 dark:hover:!border-primary-500/30">
                            <i class="ti ti-chevron-right text-lg"></i>
                        </button>
                    </div>

                    <!-- Pagination -->
                    <div :class="['testimonials-pagination mt-8 flex justify-center gap-1.5', !autoplayEnabled ? 'md:hidden' : '']"></div>
                </div>
                <div v-else class="py-16 text-center text-gray-400 dark:text-gray-600">
                    <svg class="mx-auto mb-3 h-12 w-12 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    <p class="font-medium">{{ t('No testimonials yet. Add some from the admin panel.') }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.testimonials-pagination :deep(.swiper-pagination-bullet) {
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background-color: rgb(156, 163, 175);
    opacity: 0.5;
    transition: all 0.3s ease;
    cursor: pointer;
}
.dark .testimonials-pagination :deep(.swiper-pagination-bullet) {
    background-color: rgb(75, 85, 99);
}
.testimonials-pagination :deep(.swiper-pagination-bullet-active) {
    width: 24px;
    background-color: var(--color-primary-500);
    opacity: 1;
}
.testimonials-button-prev.swiper-button-disabled,
.testimonials-button-next.swiper-button-disabled {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}
.bordered-gradient-card {
    border: 1px solid transparent;
    background-image: linear-gradient(white, white), linear-gradient(to right, var(--color-primary-500), var(--color-accent-500));
    background-origin: border-box;
    background-clip: padding-box, border-box;
}
:global(.dark) .bordered-gradient-card {
    background-image: linear-gradient(var(--color-surface-900), var(--color-surface-900)), linear-gradient(to right, var(--color-primary-500), var(--color-accent-500));
}
</style>
