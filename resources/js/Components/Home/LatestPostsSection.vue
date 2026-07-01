<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '@/Composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'

const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface BlogPostPreview { title: string; slug: string; published_at: string | null; image: string | null; is_featured: boolean; excerpt?: string | null; category?: string | null }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; recentPosts: BlogPostPreview[] }>()
const { t } = useTranslate()
const page = usePage()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asBool = (v: any, fallback = true): boolean => {
    if (v === undefined || v === null || v === '') return fallback
    if (typeof v === 'boolean') return v
    return !['0', 'false', 'no', 'off'].includes(String(v).trim().toLowerCase())
}
const localeCode = computed(() => String(page.props.locale?.code || 'en'))

const resolveMediaUrl = (path?: string | null): string => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path

    return `/storage/${path}`
}

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
    warning: 'bg-amber-500 !text-white shadow-2xl shadow-amber-500/20 hover:bg-amber-600',
    gradient_sunset: 'bg-gradient-to-r from-orange-500 via-pink-500 to-purple-600 !text-white shadow-2xl hover:opacity-95',
    gradient_ocean: 'bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-600 !text-white shadow-2xl hover:opacity-95',
    gradient_royal: 'bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 !text-white shadow-2xl hover:opacity-95',
    outline: 'border-2 border-gray-200 bg-transparent text-gray-900 hover:bg-gray-50 dark:border-surface-700 dark:text-white dark:hover:bg-surface-850',
    white: 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25',
    light: 'bg-white/10 !text-white shadow-xl hover:bg-white/20',
    ghost: 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25',
}[style] ?? 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')

const latestPostsPageButtonText = () => asString(props.section.config.button_text, t('Visit Blog'))
const latestPostsPageButtonLink = () => asString(props.section.config.button_link, '/blog')
const latestPostsPageButtonStyle = () => asString(props.section.config.button_style, 'outline')
const latestPostsSectionCardClass = (style: string): string => {
    if (isDark.value) {
        return 'overflow-hidden rounded-[2rem] border border-surface-800/60 bg-surface-900/40 shadow-sm transition hover:-translate-y-1 hover:border-primary-500/30 hover:bg-surface-900/80 hover:shadow-2xl'
    }
    return {
        simple: 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl',
        bordered: 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl',
    }[style] ?? 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl'
}

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})

const latestPostsItems = (): BlogPostPreview[] => {
    const max = parseInt(String(props.section.config.max_items ?? 3), 10)
    return (props.recentPosts || []).slice(0, max)
}

const formatDate = (date: string | null): string => {
    if (!date) return ''

    return new Intl.DateTimeFormat(localeCode.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date))
}

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null
let headerAnimated = false
let isMounted = false
let isInitializing = false
let pendingInit = false

const initAnimations = async () => {
  if (!isMounted) return
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  if (isInitializing) {
    pendingInit = true
    return
  }
  isInitializing = true

  if (gsapCtx) {
    gsapCtx.revert()
  }

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Section Header Entrance
    if (!headerAnimated) {
      const header = sectionRef.value!.querySelector('.mb-12')
      if (header) {
        gsap.from(header, {
          opacity: 0,
          y: 30,
          duration: 0.7,
          immediateRender: false,
          scrollTrigger: {
            trigger: sectionRef.value,
            start: 'top 85%',
            once: true,
          },
          onComplete: () => {
            headerAnimated = true
          }
        })
      }
    }

    // Post Cards — Staggered Entrance
    const cards = sectionRef.value!.querySelectorAll('.post-card')
    if (cards.length) {
      gsap.from(cards, {
        opacity: 0,
        y: 50,
        duration: 0.6,
        stagger: 0.12,
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

  isInitializing = false
  if (pendingInit) {
    pendingInit = false
    initAnimations()
  }
}

onMounted(() => {
  isMounted = true
  initAnimations()
})

watch(() => props.recentPosts, async () => {
  await nextTick()
  initAnimations()
}, { deep: true })

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section ref="sectionRef" :class="[isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-12">
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
                            <h2 :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.title, t('Latest from the Blog')) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subtitle)" :class="['font-medium max-w-2xl mt-4', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subtitle) }}</p>
                    </div>
                </div>
                <div v-if="latestPostsItems().length" class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="post in latestPostsItems()" :key="post.slug" :href="route('blog.show', post.slug)" :class="[latestPostsSectionCardClass(asString(section.config.card_style, 'bordered')), 'group flex h-full flex-col post-card']">
                        <div class="aspect-[16/9] overflow-hidden bg-gray-100 dark:bg-surface-800">
                            <img v-if="post.image" :src="resolveMediaUrl(post.image)" :alt="post.title" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-100 to-secondary-100 text-primary-300 dark:from-primary-900/30 dark:to-surface-800">
                                <i class="ti ti-article text-4xl"></i>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col p-6 text-left">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <span v-if="post.category" class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-700/10 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/20">
                                    {{ post.category }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    <i class="ti ti-clock mr-1"></i>
                                    {{ formatDate(post.published_at) }}
                                </span>
                            </div>
                            <h3 class="text-xl font-black !text-gray-900 dark:!text-white">{{ post.title }}</h3>
                            <p v-if="asBool(section.config.show_description, true) && post.excerpt" class="mt-3 text-sm text-gray-500 line-clamp-3 dark:text-gray-400">
                                {{ post.excerpt }}
                            </p>
                            <div v-if="asBool(section.config.show_read_more_btn, true)" class="mt-auto pt-4 flex items-center gap-1.5 text-sm font-black text-primary-600 dark:text-primary-400 group-hover:text-primary-700 dark:group-hover:text-primary-300 transition-colors">
                                <span>{{ t('Read More') }}</span>
                                <i class="ti ti-arrow-right text-xs leading-none"></i>
                            </div>
                        </div>
                    </Link>
                </div>
                <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No blog posts available yet.') }}</div>
                <div :class="['mt-10 flex', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']" v-if="asBool(section.config.show_button, true) && latestPostsPageButtonText()">
                    <Link :href="latestPostsPageButtonLink()" :class="heroButtonClass(latestPostsPageButtonStyle())" class="inline-flex items-center justify-center gap-3 rounded-full px-8 py-4 font-black transition-colors">
                        <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'text-lg leading-none']"></i>
                        {{ latestPostsPageButtonText() }}
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
