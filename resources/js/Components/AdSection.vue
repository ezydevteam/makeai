<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useIntersectionObserver } from '@vueuse/core';

const props = defineProps<{
    placement: 'top' | 'bottom' | 'sidebar' | 'feed' | 'blog_side'
}>();

const ad = ref<any>(null);
const adRef = ref(null);
const hasTrackedView = ref(false);

const fetchAd = async () => {
    try {
        const response = await axios.get(route('ads.active', props.placement));
        ad.value = response.data;
    } catch (e) {
        console.error('Failed to load ad:', e);
    }
};

const trackView = async () => {
    if (!ad.value || hasTrackedView.value) return;
    try {
        await axios.post(route('ads.trackView', ad.value.id));
        hasTrackedView.value = true;
    } catch (e) {
        // Silent fail for analytics
    }
};

const trackClick = async (e: Event) => {
    if (!ad.value) return;
    try {
        await axios.post(route('ads.trackClick', ad.value.id));
    } catch (e) {
        // Silent fail
    }
};

useIntersectionObserver(adRef, ([{ isIntersecting }]) => {
    if (isIntersecting) {
        trackView();
    }
});

onMounted(fetchAd);
</script>

<template>
    <div v-if="ad" ref="adRef" class="ad-container overflow-hidden rounded-2xl transition-all hover:opacity-95">
        <!-- Image Ad -->
        <a v-if="ad.type === 'image'" :href="ad.link_url" target="_blank" @click="trackClick" class="block w-full h-full">
            <img :src="ad.image_url" :alt="ad.name" class="w-full h-auto object-cover rounded-2xl">
        </a>

        <!-- Script Ad -->
        <div v-else v-html="ad.content" class="w-full h-full flex justify-center items-center"></div>
    </div>
</template>

<style scoped>
.ad-container {
    min-height: 50px;
    background: transparent;
}
</style>
