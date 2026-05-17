<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppHeader from '@/Components/AppHeader.vue';
import AppFooter from '@/Components/AppFooter.vue';
import AnnouncementManager from '@/Components/AnnouncementManager.vue';
import NewsletterPopup from '@/Components/NewsletterPopup.vue';
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-surface-950 text-gray-900 dark:text-gray-100 flex flex-col font-sans transition-colors duration-300">
        <!-- Announcements -->
        <AnnouncementManager />
        
        <!-- Newsletter Popup -->
        <NewsletterPopup />
        
        <!-- Global Header Component -->
        <AppHeader />

        <!-- Main Content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Global Footer Component -->
        <AppFooter />
    </div>
</template>
