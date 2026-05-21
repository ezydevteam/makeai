<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppHeader from '@/Components/AppHeader.vue';
import AppFooter from '@/Components/AppFooter.vue';
import AnnouncementManager from '@/Components/AnnouncementManager.vue';
import NewsletterPopup from '@/Components/NewsletterPopup.vue';
import AdSection from '@/Components/AdSection.vue';
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-surface-950 text-gray-900 dark:text-gray-100 flex flex-col font-sans transition-colors duration-300">
        <!-- Announcements -->
        <AnnouncementManager />
        
        <!-- Newsletter Popup -->
        <NewsletterPopup />
        
        <!-- Global Header Component -->
        <AppHeader />
        <AdSection zone="header_banner" class="mx-auto mt-4 w-full max-w-7xl px-6" />

        <!-- Main Content -->
        <main class="flex-1">
            <AdSection zone="content_top" class="mx-auto mt-4 w-full max-w-7xl px-6" />
            <slot />
            <AdSection zone="content_bottom" class="mx-auto mb-4 w-full max-w-7xl px-6" />
        </main>

        <!-- Global Footer Component -->
        <AdSection zone="footer_banner" class="mx-auto mb-4 w-full max-w-7xl px-6" />
        <AppFooter />
    </div>
</template>
