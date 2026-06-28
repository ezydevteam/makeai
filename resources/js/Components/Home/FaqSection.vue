<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '@/Composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface Faq { id: number; question: string; answer: string; category_id: number | null; sort_order: number }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; faqs: Faq[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const openFaqId = ref<number | null>(null)
const getFaqsSlice = (): Faq[] => {
    const max = parseInt(String(props.section.config.max_items ?? 10), 10)
    return props.faqs.slice(0, max)
}

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
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
    <section ref="sectionRef" :class="[isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div class="mx-auto max-w-3xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-16">
                    <!-- Top Position Icon -->
                    <div v-if="(section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle') && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle'"></i>
                    </div>
                    <div class="w-full">
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="(section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle') && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="section.config.icon !== undefined ? asString(section.config.icon) : 'ti ti-help-circle'"></i>
                            </div>
                            <h2 :class="['font-bold', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.heading ?? section.config.title, t('Frequently Asked Questions')) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subheading ?? section.config.subtitle)" :class="['font-medium mt-4 max-w-2xl', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
                    </div>
                </div>
                <div v-if="getFaqsSlice().length > 0" class="space-y-3">
                    <div v-for="faq in getFaqsSlice()" :key="faq.id" :class="[openFaqId === faq.id ? 'dark:!bg-surface-900/80 dark:!border-primary-500/30 shadow-md shadow-primary-500/5' : '']" class="overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 transition-all dark:!border-surface-700/60 dark:!bg-surface-900/40 hover:border-primary-500/20 faq-item">
                        <button @click="openFaqId = openFaqId === faq.id ? null : faq.id" type="button" class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                            <span class="text-sm font-bold !text-gray-900 dark:!text-white md:text-base">{{ faq.question }}</span>
                            <svg :class="openFaqId === faq.id ? 'rotate-180 text-primary-500' : ''" class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div v-show="openFaqId === faq.id" class="px-6 pb-5">
                            <div class="text-sm leading-relaxed !text-gray-600 dark:!text-gray-400" v-html="faq.answer"></div>
                        </div>
                    </div>
                </div>
                <div v-else class="py-16 text-center text-gray-400 dark:text-gray-600">
                    <svg class="mx-auto mb-3 h-12 w-12 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="font-medium">{{ t('No FAQs yet. Add some from the admin panel.') }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
