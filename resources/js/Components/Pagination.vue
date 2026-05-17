<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: Array<{
        url: string | null,
        label: string,
        active: boolean
    }>
}>();
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-2">
        <template v-for="(link, key) in links" :key="key">
            <div v-if="link.url === null" 
                 class="px-4 py-2 text-xs font-black text-gray-300 uppercase tracking-widest bg-gray-50 rounded-xl cursor-not-allowed"
                 v-html="link.label" />
            
            <Link v-else 
                  :href="link.url" 
                  class="px-4 py-2 text-xs font-black uppercase tracking-widest rounded-xl transition-all border border-transparent"
                  :class="{ 'bg-primary-600 text-white shadow-lg shadow-primary-600/20': link.active, 'bg-white text-gray-500 hover:bg-gray-50 border-gray-100': !link.active }"
                  v-html="link.label" />
        </template>
    </div>
</template>
