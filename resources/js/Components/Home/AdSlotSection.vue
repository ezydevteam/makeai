<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '@/Composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
import { computed } from 'vue'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
const { isDark } = useTheme()

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})
import AdSection from '@/Components/AdSection.vue'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
type AdZone = 'header_banner' | 'sidebar_top' | 'sidebar_bottom' | 'content_top' | 'content_bottom' | 'content-injection' | 'between_posts' | 'between_ai_tools' | 'tool_page_top' | 'tool_page_bottom' | 'template_page' | 'chat_banner' | 'dashboard_top' | 'footer_banner' | 'custom_zone_1' | 'custom_zone_2'
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
</script>

<template>
    <section :class="[isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '64')" class="transition-colors duration-300">
        <div class="mx-auto max-w-5xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div v-if="asString(section.config.title) || asString(section.config.subtitle) || asString(section.config.icon) || asString(section.config.badge_text)" 
                     :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-8">
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
                            <h2 v-if="asString(section.config.title)" :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.title) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subtitle)" :class="['font-medium max-w-2xl mt-4', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subtitle) }}</p>
                    </div>
                </div>
                <AdSection :zone="asString(section.config.zone, 'content_top') as AdZone" />
            </div>
        </div>
    </section>
</template>
