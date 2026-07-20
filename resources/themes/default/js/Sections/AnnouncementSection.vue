<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
import { computed } from 'vue'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle, sectionVisibilityClass, sectionAnchorId } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface Announcement { id: number; type: 'topbar' | 'popup' | 'bottom_popup' | 'notification'; title: string | null; content: string | null; bg_color: string | null; text_color: string | null; cta_text: string | null; cta_url: string | null; image: string | null; target_audience: string }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; announcements: Announcement[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const getAnnouncementSlice = (): Announcement[] => {
    const max = parseInt(String(props.section.config.max_items ?? 3), 10)
    const selectedType = asString(props.section.config.announcement_type, 'topbar')
    const filtered = selectedType === 'all' ? props.announcements : props.announcements.filter((a) => a.type === selectedType)
    return filtered.slice(0, max)
}

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
    <section
        :id="sectionAnchorId(section.config.section_anchor)" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '64')" class="transition-colors duration-300">
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
            <div v-if="getAnnouncementSlice().length > 0" class="space-y-4">
                <article v-for="announcement in getAnnouncementSlice()" :key="announcement.id" class="overflow-hidden rounded-[2rem] border border-transparent p-6 shadow-sm" :style="{ backgroundColor: announcement.bg_color || '#111827', color: announcement.text_color || '#ffffff' }">
                    <div class="mb-2 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">{{ t(announcement.type) }}</div>
                    <h3 v-if="announcement.title" class="text-xl font-black">{{ announcement.title }}</h3>
                    <div v-if="announcement.content" class="mt-2 text-sm leading-relaxed opacity-90" v-html="announcement.content"></div>
                    <a v-if="announcement.cta_text && announcement.cta_url" :href="announcement.cta_url" class="mt-5 inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-gray-900 transition hover:bg-gray-100">{{ announcement.cta_text }}</a>
                </article>
            </div>
            <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No announcements available yet.') }}</div>
            </div>
        </div>
    </section>
</template>
