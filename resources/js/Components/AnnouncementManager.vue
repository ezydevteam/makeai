<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

interface Announcement {
    id: number
    type: 'topbar' | 'popup' | 'notification'
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
const headerCouponVisible = computed(() => {
    if (!headerCoupon.value) return false
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
    }
}

const triggerPopup = (a: Announcement) => {
    showPopup.value = a
    popupVisible.value = true
}

onMounted(() => {
    nowTimer = window.setInterval(() => {
        now.value = Date.now()
    }, 60000)

    // Process Topbars
    activeTopbars.value = allAnnouncements.value
        .filter(a => a.type === 'topbar' && shouldShow(a))

    // Process Popups
    const popups = allAnnouncements.value
        .filter(a => a.type === 'popup' && shouldShow(a))
    
    if (popups.length > 0) {
        // Just take the first active popup
        const p = popups[0]
        
        if (!p.trigger_type || p.trigger_type === 'delay') {
            const delay = p.trigger_value ? parseInt(p.trigger_value) * 1000 : 0
            setTimeout(() => triggerPopup(p), delay)
        } else if (p.trigger_type === 'scroll') {
            const threshold = p.trigger_value ? parseInt(p.trigger_value) : 50
            const onScroll = () => {
                const scrolled = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
                if (scrolled >= threshold) {
                    triggerPopup(p)
                    window.removeEventListener('scroll', onScroll)
                }
            }
            window.addEventListener('scroll', onScroll)
        } else if (p.trigger_type === 'exit') {
            const onMouseOut = (e: MouseEvent) => {
                if (e.clientY <= 0) {
                    triggerPopup(p)
                    document.removeEventListener('mouseout', onMouseOut)
                }
            }
            document.addEventListener('mouseout', onMouseOut)
        } else {
            triggerPopup(p)
        }
    }
})

onUnmounted(() => {
    if (nowTimer) {
        window.clearInterval(nowTimer)
    }
})
</script>

<template>
    <div v-if="headerCouponVisible && headerCoupon" class="relative z-50 bg-primary-600 px-4 py-2 text-white shadow-sm">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-4 gap-y-1 text-center text-sm font-semibold">
            <span>{{ t(':discount off with coupon', { discount: headerCoupon.discount_label }) }}</span>
            <code class="rounded-md bg-white/15 px-2 py-0.5 font-mono text-xs tracking-wide">{{ headerCoupon.code }}</code>
            <span v-if="headerCouponEndingDate" class="text-xs font-medium text-white/85">
                {{ t('Ends :date', { date: headerCouponEndingDate }) }}
            </span>
            <a :href="headerCoupon.pricing_url" class="rounded-full bg-white px-3 py-1 text-xs font-bold text-primary-700 transition hover:bg-primary-50">
                {{ t('Choose plan') }}
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
            <div class="flex-1 flex items-center justify-center gap-x-6 text-sm font-medium">
                <span v-if="banner.title" class="font-bold hidden md:inline">{{ banner.title }}:</span>
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

    <!-- Popup Modal -->
    <Teleport to="body">
        <transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showPopup && popupVisible" class="relative z-[100]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="dismiss(showPopup)"></div>

                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100 translate-y-0 sm:scale-100" leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                            <div v-if="popupVisible" class="relative transform overflow-hidden rounded-2xl text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg" :style="{ backgroundColor: showPopup.bg_color || '#ffffff', color: showPopup.text_color || '#111827' }">
                                <button @click="dismiss(showPopup)" type="button" class="absolute right-4 top-4 rounded-full p-2 hover:bg-black/10 transition-colors z-10" :style="{ color: showPopup.text_color || '#111827' }">
                                    <span class="sr-only">{{ t('Close') }}</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                                </button>
                                
                                <div v-if="showPopup.image" class="w-full h-48 bg-cover bg-center" :style="{ backgroundImage: `url(${showPopup.image})` }"></div>

                                <div class="px-6 py-8 sm:p-10 flex flex-col items-center text-center">
                                    <h3 v-if="showPopup.title" class="text-2xl font-black mb-4 leading-tight">{{ showPopup.title }}</h3>
                                    <div class="text-base opacity-90 mb-8" v-html="showPopup.content"></div>

                                    <a v-if="showPopup.cta_text && showPopup.cta_url" :href="showPopup.cta_url" class="w-full sm:w-auto inline-flex justify-center items-center rounded-xl px-8 py-3.5 text-base font-bold shadow-lg hover:opacity-90 transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" :style="{ backgroundColor: showPopup.text_color || '#111827', color: showPopup.bg_color || '#ffffff' }">
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
</template>
