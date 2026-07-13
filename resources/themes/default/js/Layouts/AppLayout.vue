<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'
import AppHeader from '@themes/default/js/Components/AppHeader.vue'
import AppFooter from '@themes/default/js/Components/AppFooter.vue'
import AnnouncementManager from '@themes/default/js/Components/AnnouncementManager.vue'
import NewsletterPopup from '@themes/default/js/Components/NewsletterPopup.vue'
import AdSection from '@themes/default/js/Components/AdSection.vue'
import CookieConsentBanner from '@/Components/Utility/CookieConsentBanner.vue'
import AiAssistantLoader from '../../../../../addons/ai-assistant/resources/js/Components/AiAssistantLoader.vue'

const page = usePage()
const { t } = useTranslate()
const hideHeader = computed(() => (page.props.hide_header as boolean) ?? false)
const hideFooter = computed(() => (page.props.hide_footer as boolean) ?? false)
const isDemo = computed(() => (page.props as any).app?.demo ?? false)
const demoDismissed = ref(false)
const demoBannerColor = computed(() => (page.props as any).app?.demo_banner_color ?? 'indigo')
const envatoUrl = computed(() => (page.props as any).app?.envato_url ?? 'https://codecanyon.net')
const showCookieBanner = ref(false)

const gdprConfig = computed(() => (page.props as any).gdpr ?? { enabled: false })
const gdprEnabled = computed(() => gdprConfig.value.enabled === true)
const frontendHeaderSettings = computed(() => (page.props as any).frontendHeaderSettings ?? {})
const mobileBottomHeaderHeight = computed(() => {
    const mobileBottom = frontendHeaderSettings.value?.mobile_bottom ?? {}

    if (mobileBottom.enabled !== true) {
        return 0
    }

    return mobileBottom.hide_menu_labels === true ? 48 : 60
})
const gdprShowBanner = computed(() => {
    if (!gdprConfig.value.enabled) return false
    if (gdprConfig.value.eu_only && !gdprConfig.value.is_eu) return false
    return true
})

onMounted(() => {
    demoDismissed.value = sessionStorage.getItem('demo_banner_dismissed') === '1'
    if (gdprShowBanner.value) {
        const consent = localStorage.getItem('cookie_consent')
        if (!consent) {
            showCookieBanner.value = true
        }
    }
})

function onCookieAccept(consent: any) {
    showCookieBanner.value = false
    if (consent.analytics && typeof window !== 'undefined' && (window as any).gtag) {
        (window as any).gtag('consent', 'update', { analytics_storage: 'granted' })
    }
}

function dismissDemoBanner() {
    demoDismissed.value = true
    sessionStorage.setItem('demo_banner_dismissed', '1')
}

const colorClasses: Record<string, string> = {
    indigo: 'bg-indigo-600',
    amber: 'bg-amber-600',
    emerald: 'bg-emerald-600',
    rose: 'bg-rose-600',
    sky: 'bg-sky-600',
}

const bannerBg = computed(() => colorClasses[demoBannerColor.value] ?? colorClasses.indigo)
const mobileBottomInsetStyle = computed(() => ({
    paddingBottom: `${mobileBottomHeaderHeight.value}px`,
}))

useFlashToasts()
</script>

<template>
    <div class="frontend-theme min-h-screen bg-gray-50 dark:bg-surface-950 text-gray-900 dark:text-gray-100 flex flex-col font-sans transition-colors duration-300">
        <!-- Demo Banner (user-facing) -->
        <div v-if="isDemo && !demoDismissed" :class="bannerBg" class="px-4 py-2.5 text-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white via-transparent to-transparent" />
            <div class="relative z-10 flex items-center justify-center gap-4 flex-wrap">
                <p class="text-sm font-semibold text-white">
                    <span class="w-2 h-2 rounded-full bg-white inline-block mr-2 animate-pulse" />
                    {{ t('You are viewing a demo. Some actions are disabled.') }}
                </p>
                <a :href="envatoUrl" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-white/20 px-3 py-1 text-sm font-bold text-white hover:bg-white/30 transition-colors">
                    {{ t('Buy Now') }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </a>
                <button @click="dismissDemoBanner" class="ml-2 p-1 rounded text-white/70 hover:text-white hover:bg-white/10 transition-colors" :title="t('Dismiss')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <template v-if="!hideHeader">
            <AnnouncementManager />
            <NewsletterPopup />
            <AppHeader />
            <AdSection zone="header_banner" class="mx-auto mt-4 w-full max-w-7xl px-6" />
        </template>

        <main class="flex-1 md:pb-0" :style="mobileBottomInsetStyle">
            <template v-if="!hideHeader">
                <AdSection zone="content_top" class="mx-auto mt-4 w-full max-w-7xl px-6" />
            </template>
            <slot />
            <template v-if="!hideHeader">
                <AdSection zone="content_bottom" class="mx-auto mb-4 w-full max-w-7xl px-6" />
            </template>
        </main>

        <template v-if="!hideFooter">
            <AdSection zone="footer_banner" class="mx-auto mb-4 w-full max-w-7xl px-6" />
            <AppFooter />
        </template>

        <!-- GDPR Cookie Consent Banner -->
        <CookieConsentBanner v-if="gdprEnabled && showCookieBanner" :config="gdprConfig" @accept="onCookieAccept" @accept-all="showCookieBanner = false" @accept-necessary="showCookieBanner = false" />
        <AiAssistantLoader />
    </div>
</template>
