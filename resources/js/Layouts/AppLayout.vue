<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useFlashToasts } from '@/Composables/useToastr'
import AppHeader from '@/Components/AppHeader.vue'
import AppFooter from '@/Components/AppFooter.vue'
import AnnouncementManager from '@/Components/AnnouncementManager.vue'
import NewsletterPopup from '@/Components/NewsletterPopup.vue'
import AdSection from '@/Components/AdSection.vue'

const page = usePage()
const hideHeader = computed(() => (page.props.hide_header as boolean) ?? false)
const hideFooter = computed(() => (page.props.hide_footer as boolean) ?? false)

useFlashToasts()
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-surface-950 text-gray-900 dark:text-gray-100 flex flex-col font-sans transition-colors duration-300">
        <template v-if="!hideHeader">
            <AnnouncementManager />
            <NewsletterPopup />
            <AppHeader />
            <AdSection zone="header_banner" class="mx-auto mt-4 w-full max-w-7xl px-6" />
        </template>

        <main class="flex-1">
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
    </div>
</template>
