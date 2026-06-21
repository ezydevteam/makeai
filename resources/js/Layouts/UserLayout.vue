<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useFlashToasts } from '@/Composables/useToastr'
import AppHeader from '@/Components/AppHeader.vue'
import AppFooter from '@/Components/AppFooter.vue'
import AnnouncementManager from '@/Components/AnnouncementManager.vue'
import NewsletterPopup from '@/Components/NewsletterPopup.vue'
import AdSection from '@/Components/AdSection.vue'

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

const close = () => { profileOpen.value = false }
onMounted(() => document.addEventListener('click', close))
onUnmounted(() => document.removeEventListener('click', close))

useFlashToasts()
</script>

<template>
    <div class="frontend-theme min-h-screen bg-gray-50 dark:bg-surface-950 flex flex-col transition-colors duration-300">
        <!-- ═══ Impersonation Banner ═══ -->
        <div v-if="user?.isImpersonating" class="bg-accent-600 text-white px-4 py-2 flex items-center justify-center gap-4 text-sm font-bold z-[60] sticky top-0">
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
        
        <!-- Newsletter Popup -->
        <NewsletterPopup />

        <!-- Global Header Component -->
        <AppHeader v-if="showHeader" />
        <AdSection v-if="showHeader" zone="header_banner" class="mx-auto mt-4 w-full max-w-7xl px-6" />

        <!-- Content -->
        <main class="flex-1 flex flex-col">
            <AdSection zone="content_top" class="mx-auto mt-4 w-full max-w-7xl px-6" />
            <slot />
            <AdSection zone="content_bottom" class="mx-auto mb-4 w-full max-w-7xl px-6" />
        </main>

        <!-- Global Footer Component -->
        <AdSection v-if="showFooter" zone="footer_banner" class="mx-auto mb-4 w-full max-w-7xl px-6" />
        <AppFooter v-if="showFooter" />
    </div>
</template>
