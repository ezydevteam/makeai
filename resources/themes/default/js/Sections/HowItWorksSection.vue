<script setup lang="ts">
import { loadGsapNearViewport } from '../composables/useGsapScrollAnimation'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/pagination'

import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useTheme } from '@/Composables/useTheme'

const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle, sectionVisibilityClass, sectionAnchorId } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []
const howItWorksSteps = (): Record<string, string | number | boolean>[] => asItems(props.section.config.items).slice(0, Number(asString(props.section.config.max_items, '6')))

const howItWorksStepCardClass = (style: string): string => {
    if (isDark.value) {
        const darkBase = 'border bg-surface-900/40 border-surface-800/60 transition-all duration-300 hover:-translate-y-1 hover:bg-surface-900/80 hover:border-primary-500/30 hover:shadow-2xl'
        return style === 'simple'
            ? `rounded-[1.5rem] p-6 ${darkBase}`
            : `rounded-[1.75rem] p-6 ${darkBase}`
    }
    return style === 'simple'
        ? 'rounded-[1.5rem] border border-transparent bg-white/80 p-6 shadow-[0_10px_28px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-[0_18px_45px_rgba(31,117,254,0.08)]'
        : 'rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)]'
}

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})

const howItWorksStepIndexClass = (style: string): string => ({
    simple: 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300',
    bordered: 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/20',
}[style] ?? 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/20')

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  const gsapLoaded = await loadGsapNearViewport(sectionRef)
  if (! gsapLoaded) return
  const { gsap, ScrollTrigger } = gsapLoaded

  gsapCtx = gsap.context(() => {
    // Section heading
    const heading = sectionRef.value!.querySelector('h2, h3')
    if (heading) {
      gsap.from(heading, {
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

    // Steps — alternating left/right
    const steps = sectionRef.value!.querySelectorAll<HTMLElement>('.step-item')
    steps.forEach((step, i) => {
      gsap.from(step, {
        opacity: 0,
        x: i % 2 === 0 ? -60 : 60,
        duration: 0.7,
        ease: 'power2.out',
        immediateRender: false,
        scrollTrigger: {
          trigger: step,
          start: 'top 82%',
          once: true,
        },
      })
    })

    // Connector line draw (only if element exists)
    const line = sectionRef.value!.querySelector<HTMLElement>('.steps-connector-line')
    if (line) {
      gsap.from(line, {
        scaleY: 0,
        transformOrigin: 'top center',
        ease: 'none',
        immediateRender: false,
        scrollTrigger: {
          trigger: sectionRef.value!.querySelector('.steps-wrapper') ?? sectionRef.value,
          start: 'top 70%',
          end: 'bottom 30%',
          scrub: 1,
        },
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
        :id="sectionAnchorId(section.config.section_anchor)" ref="sectionRef" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="overflow-hidden transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-12">
                    <!-- Top Position Icon -->
                    <div v-if="(section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-route') && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-route'"></i>
                    </div>
                    <div class="w-full">
                        <span v-if="asString(section.config.badge_text)" :class="badgeClass(asString(section.config.section_bg, 'default'), asString(section.config.title_color, 'dark'))">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ t(asString(section.config.badge_text)) }}
                        </span>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="(section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-route') && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-route'"></i>
                            </div>
                            <h2 :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ t(asString(section.config.heading ?? section.config.title, t('How It Works'))) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subheading ?? section.config.subtitle)" :class="['font-medium mt-4 max-w-2xl', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ t(asString(section.config.subheading ?? section.config.subtitle)) }}</p>
                    </div>
                </div>
                <div v-if="howItWorksSteps().length > 0">
                    <!-- Desktop Grid -->
                    <div class="hidden md:grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3 steps-wrapper">
                        <article v-for="(item, index) in howItWorksSteps()" :key="`${item.title}_${index}`" :class="[howItWorksStepCardClass(asString(section.config.step_card_style, 'bordered')), 'flex flex-col h-full step-item']">
                            <div class="mb-5 flex items-center justify-between gap-3">
                                <span :class="['inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black', howItWorksStepIndexClass(asString(section.config.step_card_style, 'bordered'))]">{{ String(index + 1).padStart(2, '0') }}</span>
                                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-primary-700 dark:text-primary-300">{{ t('Step :count', { count: String(index + 1).padStart(2, '0') }) }}</p>
                                <span v-if="item.icon" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:!bg-primary-500/20 dark:!text-primary-400 dark:border dark:border-primary-500/30"><i :class="String(item.icon)"></i></span>
                            </div>
                            <h3 class="text-xl font-black !text-gray-900 dark:!text-white">{{ t(item.title || item.label || item.name) }}</h3>
                            <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t(item.description || item.text || item.number) }}</p>
                        </article>
                    </div>

                    <!-- Mobile Swiper -->
                    <div class="block md:hidden">
                        <Swiper
                            :modules="[Pagination]"
                            :pagination="{ el: '.how-it-works-pagination', clickable: true }"
                            :slides-per-view="1.15"
                            :space-between="16"
                            :breakpoints="{
                                480: { slidesPerView: 1.25, spaceBetween: 20 },
                                640: { slidesPerView: 1.5, spaceBetween: 24 }
                            }"
                            class="how-it-works-swiper !overflow-visible"
                        >
                            <SwiperSlide v-for="(item, index) in howItWorksSteps()" :key="`swiper_${item.title}_${index}`" class="!h-auto flex flex-col">
                                <article :class="[howItWorksStepCardClass(asString(section.config.step_card_style, 'bordered')), 'h-full flex-1 flex flex-col']">
                                    <div class="mb-5 flex items-center justify-between gap-3">
                                        <span :class="['inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black', howItWorksStepIndexClass(asString(section.config.step_card_style, 'bordered'))]">{{ String(index + 1).padStart(2, '0') }}</span>
                                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-primary-700 dark:text-primary-300">{{ t('Step :count', { count: String(index + 1).padStart(2, '0') }) }}</p>
                                        <span v-if="item.icon" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:!bg-primary-500/20 dark:!text-primary-400 dark:border dark:border-primary-500/30"><i :class="String(item.icon)"></i></span>
                                    </div>
                                    <h3 class="text-xl font-black !text-gray-900 dark:!text-white">{{ t(item.title || item.label || item.name) }}</h3>
                                    <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t(item.description || item.text || item.number) }}</p>
                                </article>
                            </SwiperSlide>
                        </Swiper>
                        <div class="how-it-works-pagination flex justify-center gap-2 mt-6"></div>
                    </div>
                </div>
                <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No steps have been added to this section yet.') }}</div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.how-it-works-pagination :deep(.swiper-pagination-bullet) {
    background: var(--color-primary-500) !important;
    opacity: 0.3;
    width: 8px;
    height: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}
.how-it-works-pagination :deep(.swiper-pagination-bullet-active) {
    background: var(--color-primary-500) !important;
    opacity: 1;
    width: 24px;
    border-radius: 9999px;
}
</style>
