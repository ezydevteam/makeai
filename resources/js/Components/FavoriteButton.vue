<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    modelType: string,
    modelId: number,
    isFavorited: boolean,
    count?: number,
    size?: 'sm' | 'md' | 'lg'
}>();

const form = useForm({
    favoriteable_type: props.modelType,
    favoriteable_id: props.modelId
});

const toggle = () => {
    form.post(route('favorites.toggle'), {
        preserveScroll: true
    });
};

const iconSize = computed(() => {
    if (props.size === 'sm') return 'w-4 h-4';
    if (props.size === 'lg') return 'w-6 h-6';
    return 'w-5 h-5';
});
</script>

<template>
    <button @click.stop="toggle" :class="[isFavorited ? 'text-danger-500 bg-danger-50 border-danger-100' : 'text-gray-400 bg-gray-50 border-gray-100 hover:text-gray-600 hover:bg-gray-100']" class="p-2 rounded-xl border transition-all flex items-center gap-1.5 group">
        <svg :class="iconSize" viewBox="0 0 24 24" stroke="currentColor" :stroke-width="isFavorited ? '0' : '2'" :fill="isFavorited ? 'currentColor' : 'none'">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
        </svg>
        <span v-if="count !== undefined" class="text-xs font-bold">{{ count }}</span>
    </button>
</template>
