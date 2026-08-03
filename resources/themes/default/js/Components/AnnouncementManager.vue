<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { mediaUrl } from '@/lib/media'

interface Announcement {
    id: number
    type: 'topbar' | 'popup' | 'bottom_popup' | 'notification'
    title: string | null
    content: string | null
    bg_color: string | null
    text_color: string | null
    cta_text: string | null
    cta_url: string | null
    image: string | null
    target_audience: string
    trigger_type: string | null
    trigger_value: string | null
    show_frequency: 'always' | 'session' | 'once'
}

interface HeaderCoupon {
    code: string
    type: 'percent' | 'fixed'
    value: number
    discount_label: string
    expires_at: string | null
    pricing_url: string
}

const page = usePage()
const { t } = useTranslate()
const { formatDate } = useDateFormat()
const now = ref(Date.now())
let nowTimer: number | null = null
const allAnnouncements = computed(() => (page.props.announcements as Announcement[]) || [])
const headerCoupon = computed(() => page.props.headerCoupon as HeaderCoupon | null)

// Tapping the code copies it. Feedback is inline on the chip rather than a toast: this
// component has no toast dependency, and the banner is already the thing being looked at.
const couponCopied = ref(false)
let couponCopiedTimer: number | null = null

const copyCouponCode = async () => {
    const code = headerCoupon.value?.code
    if (!code) return

    try {
        await navigator.clipboard.writeText(code)
    } catch {
        // clipboard API needs a secure context; fall back to a throwaway selection.
        const scratch = document.createElement('textarea')
        scratch.value = code
        scratch.setAttribute('readonly', '')
        scratch.style.position = 'fixed'
        scratch.style.opacity = '0'
        document.body.appendChild(scratch)
        scratch.select()
        try { document.execCommand('copy') } catch { /* nothing else to try */ }
        document.body.removeChild(scratch)
    }

    couponCopied.value = true
    if (couponCopiedTimer !== null) window.clearTimeout(couponCopiedTimer)
    couponCopiedTimer = window.setTimeout(() => { couponCopied.value = false }, 2000)
}
/* Dismissal is keyed to the coupon CODE, not a generic flag, so publishing a different
   coupon shows the banner again to someone who dismissed the previous one. Persisted in
   localStorage to match how the announcement topbar remembers its own dismissal. */
const couponDismissed = ref(false)

const couponStorageKey = computed(() => `header_coupon_${headerCoupon.value?.code ?? ''}_dismissed`)

const dismissCoupon = () => {
    couponDismissed.value = true
    try {
        localStorage.setItem(couponStorageKey.value, 'true')
    } catch { /* private mode — dismissal just won't persist */ }
}

const headerCouponVisible = computed(() => {
    if (!headerCoupon.value) return false
    if (couponDismissed.value) return false
    if (!headerCoupon.value.expires_at) return true

    return new Date(headerCoupon.value.expires_at).getTime() > now.value
})
const headerCouponEndingDate = computed(() => {
    if (!headerCoupon.value?.expires_at) return null

    return formatDate(headerCoupon.value.expires_at)
})

const activeTopbars = ref<Announcement[]>([])
const activePopups = ref<Announcement[]>([])
const showPopup = ref<Announcement | null>(null)
const popupVisible = ref(false)
const showBottomPopup = ref<Announcement | null>(null)
const bottomPopupVisible = ref(false)
const bottomPopupOffset = ref(16)

// Only pin a colour inline when the admin actually chose one — an unset value falls back
// to the theme-aware `bg-white dark:bg-surface-900` classes so the surface follows dark
// mode. A chosen colour is honoured verbatim in both light and dark.
const surfaceStyle = (a: Announcement | null) => {
    const style: Record<string, string> = {}
    if (a?.bg_color) style.backgroundColor = a.bg_color
    if (a?.text_color) style.color = a.text_color
    return style
}

// The popup CTA inverts the surface colours; same rule, with contrasting theme defaults.
const popupCtaStyle = computed(() => {
    const style: Record<string, string> = {}
    if (showPopup.value?.text_color) style.backgroundColor = showPopup.value.text_color
    if (showPopup.value?.bg_color) style.color = showPopup.value.bg_color
    return style
})

const popupSurfaceStyle = computed(() => surfaceStyle(showPopup.value))
const bottomPopupSurfaceStyle = computed(() => surfaceStyle(showBottomPopup.value))

// The corner card is anchored bottom-right, the same spot as the AI assistant bubble
// (.ai-trigger-fab), the floating scroll-to-top button (.back-to-top-btn) and, on mobile,
// the bottom navigation bar (.header-section-overlay). Those are owned by other
// components/addons and appear conditionally, so instead of threading their settings
// through props we measure whatever is actually on screen and lift the card above it.
// Runs on show, on scroll (the scroll-top button only appears after scrolling) and on
// resize. The bottom-half filter below keeps the top header (which shares the overlay
// class) from ever counting.
const FLOATING_NEIGHBOUR_SELECTORS = ['.ai-trigger-fab', '.back-to-top-btn', '.header-section-overlay']

const recomputeBottomPopupOffset = () => {
    if (typeof window === 'undefined' || !showBottomPopup.value) return

    const gap = 16
    let offset = gap

    for (const selector of FLOATING_NEIGHBOUR_SELECTORS) {
        document.querySelectorAll<HTMLElement>(selector).forEach((el) => {
            const rect = el.getBoundingClientRect()
            if (rect.width === 0 || rect.height === 0) return

            // Only neighbours sharing the bottom-right corner can collide with the card.
            const onRightSide = rect.right > window.innerWidth / 2
            const nearBottom = rect.top > window.innerHeight / 2
            if (!onRightSide || !nearBottom) return

            const clearance = window.innerHeight - rect.top + gap
            if (clearance > offset) offset = clearance
        })
    }

    bottomPopupOffset.value = offset
}

let offsetRafId: number | null = null
const scheduleOffsetRecompute = () => {
    if (typeof window === 'undefined') return
    if (offsetRafId !== null) return
    offsetRafId = window.requestAnimationFrame(() => {
        offsetRafId = null
        recomputeBottomPopupOffset()
    })
}

const getStorageKey = (a: Announcement) => `announcement_${a.id}_dismissed`

const shouldShow = (a: Announcement) => {
    if (a.show_frequency === 'always') return true

    if (a.show_frequency === 'once') {
        return localStorage.getItem(getStorageKey(a)) !== 'true'
    }

    if (a.show_frequency === 'session') {
        return sessionStorage.getItem(getStorageKey(a)) !== 'true'
    }

    return true
}

const dismiss = (a: Announcement) => {
    if (a.show_frequency === 'once') {
        localStorage.setItem(getStorageKey(a), 'true')
    } else if (a.show_frequency === 'session') {
        sessionStorage.setItem(getStorageKey(a), 'true')
    }

    if (a.type === 'topbar') {
        activeTopbars.value = activeTopbars.value.filter(item => item.id !== a.id)
    } else if (a.type === 'popup') {
        popupVisible.value = false
        setTimeout(() => {
            showPopup.value = null
        }, 300)
    } else if (a.type === 'bottom_popup') {
        bottomPopupVisible.value = false
        setTimeout(() => {
            showBottomPopup.value = null
        }, 300)
    }
}

const triggerPopup = (a: Announcement) => {
    showPopup.value = a
    popupVisible.value = true
}

const triggerBottomPopup = (a: Announcement) => {
    showBottomPopup.value = a
    bottomPopupVisible.value = true
    nextTick(() => recomputeBottomPopupOffset())
}

// Popup and bottom popup share the same trigger vocabulary (immediate, delay, scroll %,
// exit intent). Centralised here so both surfaces stay in sync.
const scheduleByTrigger = (a: Announcement, show: (a: Announcement) => void) => {
    if (a.trigger_type === 'delay') {
        const delay = a.trigger_value ? parseInt(a.trigger_value) * 1000 : 0
        setTimeout(() => show(a), delay)
    } else if (a.trigger_type === 'scroll') {
        const threshold = a.trigger_value ? parseInt(a.trigger_value) : 50
        const onScroll = () => {
            const scrolled = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
            if (scrolled >= threshold) {
                show(a)
                window.removeEventListener('scroll', onScroll)
            }
        }
        window.addEventListener('scroll', onScroll)
    } else if (a.trigger_type === 'exit') {
        const onMouseOut = (e: MouseEvent) => {
            if (e.clientY <= 0) {
                show(a)
                document.removeEventListener('mouseout', onMouseOut)
            }
        }
        document.addEventListener('mouseout', onMouseOut)
    } else {
        show(a)
    }
}

// Publish the total height of the pinned top banners (coupon + topbar) as a CSS
// variable so the sticky header (and the dashboard's fixed sidebar) can offset
// below them instead of overlapping. 0 when nothing is active.
const recomputeTopBannerHeight = () => {
    if (typeof document === 'undefined') return
    // Measure the whole pinned stack (impersonation banner + coupon + topbar),
    // falling back to just this component's container when no wrapper is present.
    const el = document.getElementById('top-sticky-stack') ?? document.getElementById('top-announcement-container')
    const height = el ? Math.round(el.getBoundingClientRect().height) : 0
    document.documentElement.style.setProperty('--top-banners-height', `${height}px`)
}

const notifyChange = () => {
    nextTick(() => {
        recomputeTopBannerHeight()
        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('announcement:change'))
        }
    })
}

watch([activeTopbars, headerCouponVisible], () => {
    notifyChange()
}, { deep: true })

onMounted(() => {
    nowTimer = window.setInterval(() => {
        now.value = Date.now()
    }, 60000)

    // Restore a previous dismissal of this specific coupon.
    if (headerCoupon.value) {
        try {
            couponDismissed.value = localStorage.getItem(couponStorageKey.value) === 'true'
        } catch { /* storage unavailable — treat as not dismissed */ }
    }

    // Process Topbars
    activeTopbars.value = allAnnouncements.value
        .filter(a => a.type === 'topbar' && shouldShow(a))

    // Process Popups
    const popups = allAnnouncements.value
        .filter(a => a.type === 'popup' && shouldShow(a))

    if (popups.length > 0) {
        // Just take the first active popup
        scheduleByTrigger(popups[0], triggerPopup)
    }

    // Process Bottom Popups (corner card)
    const bottomPopups = allAnnouncements.value
        .filter(a => a.type === 'bottom_popup' && shouldShow(a))

    if (bottomPopups.length > 0) {
        scheduleByTrigger(bottomPopups[0], triggerBottomPopup)
    }

    window.addEventListener('scroll', scheduleOffsetRecompute, { passive: true })
    window.addEventListener('resize', scheduleOffsetRecompute)
    window.addEventListener('resize', recomputeTopBannerHeight)
    notifyChange()
})

onUnmounted(() => {
    if (nowTimer) {
        window.clearInterval(nowTimer)
    }

    if (couponCopiedTimer !== null) {
        window.clearTimeout(couponCopiedTimer)
    }

    window.removeEventListener('scroll', scheduleOffsetRecompute)
    window.removeEventListener('resize', scheduleOffsetRecompute)
    window.removeEventListener('resize', recomputeTopBannerHeight)
    if (typeof document !== 'undefined') {
        document.documentElement.style.setProperty('--top-banners-height', '0px')
    }
    if (offsetRafId !== null) {
        window.cancelAnimationFrame(offsetRafId)
    }
})
</script>

<template>
    <div id="top-announcement-container" class="relative z-50 flex flex-col w-full">
        <div v-if="headerCouponVisible && headerCoupon" class="relative z-50 bg-gradient-to-br from-primary-500 to-primary-600 px-4 py-2.5 pe-11 text-white shadow-sm">
            <!-- Absolutely positioned so it pins to the bar's edge and does not join the
                 centred flex row (where it would shift the message off-centre and wrap onto
                 its own line on mobile). pe-11 on the bar reserves its space. -->
            <button
                type="button"
                class="absolute end-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-white/80 transition hover:bg-white/15 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                :title="t('Dismiss')"
                :aria-label="t('Dismiss')"
                @click="dismissCoupon"
            >
                <i class="ti ti-x text-base" aria-hidden="true"></i>
            </button>
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-4 gap-y-1 text-center text-sm font-semibold">
                <span>{{ t(':discount off with coupon', { discount: headerCoupon.discount_label }) }}</span>
                <!-- The copy affordance stays out of the way until hover/focus, so the chip
                     reads as a code first. It is forced visible right after a copy so the
                     tick still confirms the action on touch, where there is no hover. -->
                <button
                    type="button"
                    class="group inline-flex cursor-pointer items-center rounded-md bg-white/15 px-2 py-0.5 font-mono text-sm tracking-wide transition hover:bg-white/25 active:scale-[0.98]"
                    :title="couponCopied ? t('Copied!') : t('Copy code')"
                    :aria-label="t('Copy code')"
                    @click="copyCouponCode"
                >
                    <span>{{ headerCoupon.code }}</span>
                    <!-- Collapsed to zero width (not just transparent) so the hidden icon
                         leaves no gap beside the code; it expands on hover/focus, and stays
                         open while the copied tick shows, which is the only feedback on
                         touch where there is no hover. -->
                    <span
                        class="inline-flex max-w-0 overflow-hidden opacity-0 transition-all duration-200 group-hover:ml-1.5 group-hover:max-w-4 group-hover:opacity-100 group-focus-visible:ml-1.5 group-focus-visible:max-w-4 group-focus-visible:opacity-100"
                        :class="{ '!ml-1.5 !max-w-4 !opacity-100': couponCopied }"
                        aria-hidden="true"
                    >
                        <i class="ti text-xs" :class="couponCopied ? 'ti-check' : 'ti-copy'"></i>
                    </span>
                </button>
                <span aria-live="polite" class="sr-only">{{ couponCopied ? t('Copied!') : '' }}</span>
                <span v-if="headerCouponEndingDate" class="text-xs font-medium text-white/85">
                    {{ t('Ends :date', { date: headerCouponEndingDate }) }}
                </span>
                <a :href="headerCoupon.pricing_url" class="group inline-flex items-center gap-1.5 rounded-full !bg-white px-3 py-1 text-xs font-bold text-primary-700 transition hover:bg-primary-50">
                    <span>{{ t('Choose plan') }}</span>
                    <i class="ti ti-arrow-right text-sm transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <!-- Topbar Banners -->
        <div v-if="activeTopbars.length > 0" class="flex flex-col z-50 relative">
            <div
                v-for="banner in activeTopbars"
                :key="banner.id"
                class="relative px-4 py-3 sm:px-6 lg:px-8 text-center flex items-center justify-center gap-4 transition-all"
                :style="{ backgroundColor: banner.bg_color || '#4f46e5', color: banner.text_color || '#ffffff' }"
            >
                <div class="flex-1 flex flex-wrap items-center justify-center gap-x-6 gap-y-1 text-sm font-medium">
                    <span v-if="banner.title" class="font-bold">{{ banner.title }}:</span>
                    <span v-html="banner.content"></span>
                    <a v-if="banner.cta_text && banner.cta_url" :href="banner.cta_url" class="flex-none rounded-full px-3.5 py-1 text-sm font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" :style="{ backgroundColor: banner.text_color || '#ffffff', color: banner.bg_color || '#4f46e5' }">
                        {{ banner.cta_text }} <span aria-hidden="true">→</span>
                    </a>
                </div>
                <button @click="dismiss(banner)" type="button" class="flex-none p-1 focus-visible:outline-offset-[-4px] opacity-80 hover:opacity-100 transition-opacity">
                    <span class="sr-only">{{ t('Dismiss') }}</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Popup Modal -->
    <Teleport to="body">
        <transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showPopup && popupVisible" class="relative z-[100]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="dismiss(showPopup)"></div>

                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100 translate-y-0 sm:scale-100" leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                            <div v-if="popupVisible" class="relative transform rounded-2xl text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg bg-white text-gray-900 dark:bg-surface-900 dark:text-white" :style="popupSurfaceStyle">
                                <button @click="dismiss(showPopup)" type="button" class="absolute -right-2.5 -top-2.5 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-900/5 transition hover:text-gray-900 dark:bg-surface-800 dark:text-gray-400 dark:ring-white/10 dark:hover:text-white">
                                    <span class="sr-only">{{ t('Close') }}</span>
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                                </button>

                                <div v-if="showPopup.image" class="w-full h-48 bg-cover bg-center rounded-t-2xl" :style="{ backgroundImage: `url(${mediaUrl(showPopup.image)})` }"></div>

                                <div class="px-6 py-8 sm:p-10 flex flex-col items-center text-center">
                                    <h3 v-if="showPopup.title" class="text-md font-semibold mb-4 leading-tight">{{ showPopup.title }}</h3>
                                    <div class="text-sm opacity-90 mb-8" v-html="showPopup.content"></div>

                                    <a v-if="showPopup.cta_text && showPopup.cta_url" :href="showPopup.cta_url" class="w-full sm:w-auto inline-flex justify-center items-center rounded-full px-8 py-3 text-sm font-semibold shadow-lg hover:opacity-90 transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 bg-gray-900 text-white dark:bg-white dark:text-gray-900" :style="popupCtaStyle">
                                        {{ showPopup.cta_text }}
                                    </a>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    <!-- Bottom Popup (corner card) -->
    <Teleport to="body">
        <transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0 translate-y-6 sm:translate-x-6 sm:translate-y-0"
            enter-to-class="opacity-100 translate-y-0 sm:translate-x-0"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0 sm:translate-x-0"
            leave-to-class="opacity-0 translate-y-6 sm:translate-x-6 sm:translate-y-0"
        >
            <div
                v-if="showBottomPopup && bottomPopupVisible"
                class="frontend-theme-vars fixed right-4 z-[95] w-[calc(100vw-2rem)] max-w-[20rem]"
                :style="{ bottom: `${bottomPopupOffset}px` }"
                role="dialog"
                aria-modal="false"
                aria-label="Announcement"
            >
                <div class="relative rounded-2xl shadow-2xl ring-1 ring-black/5 dark:ring-white/10 bg-white text-gray-900 dark:bg-surface-900 dark:text-white" :style="bottomPopupSurfaceStyle">
                    <button
                        @click="dismiss(showBottomPopup)"
                        type="button"
                        class="absolute -right-2.5 -top-2.5 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-900/5 transition hover:text-gray-900 dark:bg-surface-800 dark:text-gray-400 dark:ring-white/10 dark:hover:text-white"
                    >
                        <span class="sr-only">{{ t('Close') }}</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                    </button>

                    <div
                        class="relative flex h-44 items-center justify-center overflow-hidden rounded-t-2xl bg-cover bg-center px-6 text-center"
                        :style="showBottomPopup.image
                            ? { backgroundImage: `url(${mediaUrl(showBottomPopup.image)})` }
                            : { background: `linear-gradient(135deg, ${showBottomPopup.bg_color || '#4f46e5'}, ${showBottomPopup.text_color || '#8b5cf6'})` }"
                    >
                        <h3 v-if="showBottomPopup.title && !showBottomPopup.image" class="text-xl font-black leading-tight text-white drop-shadow">
                            {{ showBottomPopup.title }}
                        </h3>
                    </div>

                    <div class="p-4">
                        <h6 v-if="showBottomPopup.title && showBottomPopup.image" class="text-sm font-bold">
                            {{ showBottomPopup.title }}
                        </h6>
                        <div
                            v-if="showBottomPopup.content"
                            class="mt-1 text-xs leading-relaxed opacity-80"
                            v-html="showBottomPopup.content"
                        ></div>

                        <a
                            v-if="showBottomPopup.cta_text && showBottomPopup.cta_url"
                            :href="showBottomPopup.cta_url"
                            class="mt-4 block border-t border-current/15 pt-3 text-center text-sm font-semibold underline underline-offset-4 transition hover:opacity-70"
                        >
                            {{ showBottomPopup.cta_text }}
                        </a>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
