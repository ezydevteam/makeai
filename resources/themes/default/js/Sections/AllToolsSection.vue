<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
import AllToolsSectionInner from '@themes/default/js/Components/AllToolsSection.vue'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface ToolItem { slug: string; name: string; description: string; icon: string | null; color: string | null; category: string | null; usage_count: number; avg_rating: number | null; is_featured: boolean }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
defineProps<{ section: HomepageSection; allTools?: ToolItem[]; allToolCategories?: string[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Section Header Entrance
    const header = sectionRef.value!.querySelector('.mb-12')
    if (header) {
      gsap.from(header, {
        opacity: 0,
        y: 30,
        duration: 0.7,
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        },
      })
    }

    // Inner content entrance (the search/tabs/grid wrapper)
    const content = sectionRef.value!.querySelector('.relative')
    if (content) {
      gsap.from(content, {
        opacity: 0,
        y: 40,
        duration: 0.8,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 80%',
          once: true,
        },
      })
    }
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section ref="sectionRef" :class="[sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div :class="cardWrapperClass(asString(section.config.card_bg_style, 'default'))">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-12">
                    <!-- Top Position Icon -->
                    <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="asString(section.config.icon)"></i>
                    </div>
                    <div class="w-full">
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="asString(section.config.icon)"></i>
                            </div>
                            <h2 v-if="asString(section.config.title)" :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.title) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subtitle)" :class="['font-medium max-w-2xl mt-4', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subtitle) }}</p>
                    </div>
                </div>
                <AllToolsSectionInner :tools="allTools || []" :categories="allToolCategories || []" />
            </div>
        </div>
    </section>
</template>
