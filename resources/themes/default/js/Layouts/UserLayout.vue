<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useFlashToasts } from '@/Composables/useToastr'
import AppHeader from '@themes/default/js/Components/AppHeader.vue'
import AppFooter from '@themes/default/js/Components/AppFooter.vue'
import AiAssistantLoader from '../../../../../addons/ai-assistant/resources/js/Components/AiAssistantLoader.vue'
import AnnouncementManager from '@themes/default/js/Components/AnnouncementManager.vue'
import NewsletterPopup from '@themes/default/js/Components/NewsletterPopup.vue'
import AdSection from '@themes/default/js/Components/AdSection.vue'

const { t } = useTranslate()
const page = usePage()
const profileOpen = ref(false)
const user = computed(() => page.props.auth?.user as any)

const showHeader = computed(() => {
    const tool = page.props.tool as any
    return tool?.show_header !== false
})

const showFooter = computed(() => {
    const tool = page.props.tool as any
    return tool?.show_footer !== false
})
const frontendHeaderSettings = computed(() => (page.props as any).frontendHeaderSettings ?? {})
const mobileBottomHeaderHeight = computed(() => {
    const mobileBottom = frontendHeaderSettings.value?.mobile_bottom ?? {}

    if (mobileBottom.enabled !== true) {
        return 0
    }

    return mobileBottom.hide_menu_labels === true ? 48 : 60
})
const mobileBottomInsetStyle = computed(() => ({
    paddingBottom: `${mobileBottomHeaderHeight.value}px`,
}))

const close = () => { profileOpen.value = false }
onMounted(() => document.addEventListener('click', close))
onUnmounted(() => document.removeEventListener('click', close))

useFlashToasts()
</script>

<template>
    <div class="frontend-theme min-h-screen bg-gray-50 dark:bg-surface-950 flex flex-col transition-colors duration-300">
        <!-- ═══ Pinned top stack: impersonation banner + announcements/coupon ═══
             One sticky container so the bars stack (instead of all pinning to
             top:0 and overlapping), and so its measured height offsets the header. -->
        <div id="top-sticky-stack" class="sticky top-0 z-[60] flex flex-col w-full">
            <!-- Impersonation Banner -->
            <div v-if="user?.isImpersonating" class="bg-accent-600 text-white px-4 py-2 flex items-center justify-center gap-4 text-sm font-bold">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                    <span>{{ t('Viewing account as :name', { name: user.name }) }}</span>
                </div>
                <button @click="router.post(route('admin.users.stop_impersonating'))" class="px-3 py-1 bg-white text-accent-600 rounded-lg hover:bg-white/90 transition-all text-xs">
                    {{ t('Stop impersonating') }}
                </button>
            </div>

            <!-- Announcements -->
            <AnnouncementManager />
        </div>

        <!-- Newsletter Popup -->
        <NewsletterPopup />

        <!-- Global Header Component. The banner sits ABOVE the header so it is the first
             thing on the page. -->
        <AdSection v-if="showHeader" zone="header_banner" class="mx-auto mb-4 w-full max-w-7xl px-6" />
        <AppHeader v-if="showHeader" />

        <!-- Content -->
        <main class="flex-1 flex flex-col md:pb-0" :style="mobileBottomInsetStyle">
            <slot />
        </main>

        <!-- Global Footer Component -->
        <AdSection v-if="showFooter" zone="footer_banner" class="mx-auto mb-4 w-full max-w-7xl px-6" />
        <AppFooter v-if="showFooter" />

        <!-- The assistant was only mounted in AppLayout, so it never appeared anywhere in
             the signed-in dashboard area (which uses this layout, and which
             UserDashboardLayout wraps). Mounting it here covers both. -->
        <AiAssistantLoader />
    </div>
</template>
