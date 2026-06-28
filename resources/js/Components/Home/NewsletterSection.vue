<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '@/Composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
const { isDark } = useTheme()
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const isGradientBg = (style: string): boolean => ['gradient-1', 'gradient-2', 'gradient-3', 'gradient-4'].includes(style)

const newsletterBgClass = (style: string): string => ({
    'default': '',
    'transparent': 'bg-transparent border border-gray-200 dark:border-surface-800',
    'white': 'bg-white border border-gray-100 dark:bg-surface-900 dark:border-surface-800',
    'light': 'bg-gray-50 border border-gray-100 dark:bg-surface-850 dark:border-surface-800',
    'primary_light': 'bg-primary-50/50 border border-primary-100 dark:bg-primary-950/10 dark:border-primary-950/20',
    'gradient-1': 'bg-gradient-to-r from-primary-600 to-violet-600 text-white border-0',
    'gradient-2': 'bg-gradient-to-r from-secondary-600 to-primary-600 text-white border-0',
    'gradient-3': 'bg-gradient-to-br from-primary-700 via-sky-600 to-violet-700 text-white border-0',
    'gradient-4': 'bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 text-white border-0',
}[style] ?? '')

const heroButtonClass = (style: string): string => ({
    primary: 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    primary_filled: 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    dark: 'bg-gray-900 !text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800',
    purple: 'bg-violet-600 !text-white shadow-2xl shadow-violet-600/20 hover:bg-violet-700',
    gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 !text-white shadow-2xl shadow-primary-600/20 hover:opacity-95',
    red: 'bg-red-600 !text-white shadow-2xl shadow-red-600/20 hover:bg-red-700',
    danger: 'bg-red-600 !text-white shadow-2xl shadow-red-600/20 hover:bg-red-700',
    green: 'bg-success-600 !text-white shadow-2xl shadow-success-600/20 hover:bg-success-700',
    success: 'bg-emerald-600 !text-white shadow-2xl shadow-emerald-600/20 hover:bg-emerald-700',
    warning: 'bg-amber-50 !text-white shadow-2xl shadow-amber-50/20 hover:bg-amber-600',
    gradient_sunset: 'bg-gradient-to-r from-orange-500 via-pink-500 to-purple-600 !text-white shadow-2xl hover:opacity-95',
    gradient_ocean: 'bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-600 !text-white shadow-2xl hover:opacity-95',
    gradient_royal: 'bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 !text-white shadow-2xl hover:opacity-95',
    outline: 'border-2 border-gray-200 bg-transparent text-gray-900 hover:bg-gray-50 dark:border-surface-700 dark:text-white dark:hover:bg-surface-850',
    ghost: 'bg-gray-100/50 backdrop-blur-sm border border-gray-200 text-gray-900 hover:bg-gray-150 dark:bg-white/10 dark:border-white/20 dark:text-white dark:hover:bg-white/20',
}[style] ?? 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')

const resolveButtonStyle = (btnStyle: string, bgStyle: string): string => {
    if (btnStyle === 'outline' && isGradientBg(bgStyle)) {
        return 'border-2 border-white/30 bg-transparent !text-white hover:bg-white/10'
    }
    return heroButtonClass(btnStyle)
}
const backgroundStyle = computed(() => {
    const cardBg = asString(props.section.config.card_bg_style)
    return !cardBg || cardBg === 'default'
        ? asString(props.section.config.background_style, 'white')
        : cardBg
})

const effectiveCardWrapperClass = computed(() => {
    const style = backgroundStyle.value
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style, 'white')
})

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Subtle scale-up entrance for the newsletter banner
    const banner = sectionRef.value!.querySelector('.mx-auto > div > div')
    if (banner) {
      gsap.from(banner, {
        opacity: 0,
        scale: 0.95,
        y: 40,
        duration: 0.8,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        },
      })
    }
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section ref="sectionRef" :class="[isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto max-w-4xl">
                <!-- Wrapper Card with selected Background Style -->
                <div :class="effectiveCardWrapperClass">
                    <!-- Title/Subtitle inside Card -->
                    <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-10">
                        <!-- Top Position Icon -->
                        <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                            sectionIconClass(asString(section.config.icon_style, 'primary')),
                            'mb-5 h-14 w-14 text-2xl'
                        ]">
                            <i :class="asString(section.config.icon)"></i>
                        </div>
                        
                        <div class="w-full">
                            <span v-if="asString(section.config.badge_text)" :class="badgeClass(backgroundStyle, asString(section.config.title_color, 'dark'))">
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
                                <h2 :class="[
                                    'font-black',
                                    titleSizeClass(asString(section.config.title_size, 'md')),
                                    isGradientBg(backgroundStyle) && asString(section.config.title_color, 'dark') === 'dark'
                                        ? 'text-white'
                                        : titleColorClass(asString(section.config.title_color, 'dark'))
                                ]">
                                    {{ asString(section.config.heading ?? section.config.title, t('Stay in the Loop')) }}
                                </h2>
                            </div>
                            
                            <!-- Subheading Style & Color -->
                            <p v-if="asString(section.config.subheading ?? section.config.subtitle)" :class="[
                                'font-medium max-w-2xl mt-4',
                                isGradientBg(backgroundStyle) && asString(section.config.title_color, 'dark') === 'dark'
                                    ? 'text-white/80'
                                    : subtitleColorClass(asString(section.config.title_color, 'dark'), backgroundStyle),
                                asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : ''
                            ]">
                                {{ asString(section.config.subheading ?? section.config.subtitle) }}
                            </p>
                        </div>
                    </div>

                    <!-- Subscription Form Layouts -->
                    <div class="mx-auto w-full">
                        <!-- Inline Pill Style -->
                        <form v-if="asString(section.config.newsletter_style, 'inline') === 'inline_pill'"
                            :method="'post'"
                            :action="asString(section.config.button_link, '/newsletter/subscribe')"
                            :class="[
                                isGradientBg(backgroundStyle)
                                    ? 'bg-white/10 border-white/20 focus-within:border-white/40 focus-within:ring-2 focus-within:ring-white/10 text-white'
                                    : 'bg-gray-50 border-gray-200 focus-within:border-primary-400 focus-within:ring-2 focus-within:ring-primary-400/20 dark:bg-surface-800 dark:border-surface-700 text-gray-900',
                                'mx-auto w-full md:w-[70%] flex items-center border rounded-full p-1 pl-6 gap-0 shadow-sm transition-all duration-300'
                            ]"
                        >
                            <input name="email" type="email" required
                                :placeholder="asString(section.config.placeholder_text, t('Enter your email address'))"
                                :class="[
                                    isGradientBg(backgroundStyle)
                                        ? 'text-white placeholder-white/50'
                                        : 'text-gray-900 dark:text-white placeholder-gray-400',
                                    'w-full !bg-transparent border-none focus:outline-none focus:!ring-0 focus:!bg-transparent focus:!shadow-none active:!bg-transparent active:!shadow-none py-3.5 pl-0 pr-4 outline-none text-sm !shadow-none inline-pill-input'
                                ]"
                            >
                            <button type="submit"
                                :class="[
                                    resolveButtonStyle(asString(section.config.button_style, 'primary_filled'), backgroundStyle),
                                    'rounded-full px-8 py-3.5 inline-flex items-center justify-center gap-3 font-black transition-colors text-sm shrink-0'
                                ]"
                            >
                                <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'text-lg']"></i>
                                <span class="whitespace-nowrap">{{ asString(section.config.button_text, t('Subscribe')) }}</span>
                             </button>
                        </form>

                        <!-- Stacked Style -->
                        <form v-else-if="asString(section.config.newsletter_style, 'inline') === 'stacked'"
                            :method="'post'"
                            :action="asString(section.config.button_link, '/newsletter/subscribe')"
                            class="mx-auto w-full max-w-md flex flex-col gap-4"
                        >
                            <input name="email" type="email" required
                                :placeholder="asString(section.config.placeholder_text, t('Enter your email address'))"
                                :class="[
                                    isGradientBg(backgroundStyle)
                                        ? 'bg-white/10 border-white/20 text-white placeholder-white/50 focus:!border-white focus:!bg-white/15 focus:!ring-0 focus:!shadow-none active:!bg-white/15'
                                        : 'bg-gray-50 border-gray-200 text-gray-900 focus:border-primary-400 focus:!ring-primary-400 focus:!bg-gray-50 dark:bg-surface-800 dark:border-surface-700 dark:text-white dark:focus:!bg-surface-800 placeholder-gray-400',
                                    'w-full rounded-2xl border px-5 py-4 text-sm outline-none transition'
                                ]"
                            >
                            <button type="submit"
                                :class="[
                                    resolveButtonStyle(asString(section.config.button_style, 'primary_filled'), backgroundStyle),
                                    'rounded-2xl w-full px-8 py-4 inline-flex items-center justify-center gap-3 font-black transition-colors text-sm'
                                ]"
                            >
                                <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'text-lg']"></i>
                                <span>{{ asString(section.config.button_text, t('Subscribe')) }}</span>
                            </button>
                        </form>

                        <!-- Standard Inline Style -->
                        <form v-else
                            :method="'post'"
                            :action="asString(section.config.button_link, '/newsletter/subscribe')"
                            class="mx-auto w-full md:w-[70%] flex flex-col gap-3 sm:flex-row sm:items-center"
                        >
                            <input name="email" type="email" required
                                :placeholder="asString(section.config.placeholder_text, t('Enter your email address'))"
                                :class="[
                                    isGradientBg(backgroundStyle)
                                        ? 'bg-white/10 border-white/20 text-white placeholder-white/50 focus:!border-white focus:!bg-white/15 focus:!ring-0 focus:!shadow-none active:!bg-white/15'
                                        : 'bg-gray-50 border-gray-200 text-gray-900 focus:border-primary-400 focus:!ring-primary-400 focus:!bg-gray-50 dark:bg-surface-800 dark:border-surface-700 dark:text-white dark:focus:!bg-surface-800 placeholder-gray-400',
                                    'w-full rounded-2xl border px-5 py-4 text-sm outline-none transition'
                                ]"
                            >
                            <button type="submit"
                                :class="[
                                    resolveButtonStyle(asString(section.config.button_style, 'primary_filled'), backgroundStyle),
                                    'rounded-2xl px-8 py-4 inline-flex items-center justify-center gap-3 font-black transition-colors text-sm shrink-0'
                                ]"
                            >
                                <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'text-lg']"></i>
                                <span class="whitespace-nowrap">{{ asString(section.config.button_text, t('Subscribe')) }}</span>
                            </button>
                        </form>
                    </div>

                    <p v-if="asString(section.config.privacy_text)"
                        :class="[
                            isGradientBg(backgroundStyle)
                                ? 'text-white/70'
                                : 'text-gray-500 dark:text-gray-400',
                            'mt-6 text-center text-sm'
                        ]"
                    >
                        {{ asString(section.config.privacy_text, t('No spam. Unsubscribe anytime.')) }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Force input background transparency, especially for autocomplete and autofill */
.inline-pill-input {
    background-color: transparent !important;
}
.inline-pill-input:focus,
.inline-pill-input:active {
    background-color: transparent !important;
    box-shadow: none !important;
}
.inline-pill-input:-webkit-autofill,
.inline-pill-input:-webkit-autofill:hover,
.inline-pill-input:-webkit-autofill:focus,
.inline-pill-input:-webkit-autofill:active {
    -webkit-background-clip: text;
    -webkit-text-fill-color: inherit !important;
    transition: background-color 5000s ease-in-out 0s;
    box-shadow: inset 0 0 20px 20px transparent !important;
}
</style>
