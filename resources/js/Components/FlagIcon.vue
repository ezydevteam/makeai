<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
    flag?: string | null
    languageCode?: string | null
    languageName?: string | null
    size?: 'sm' | 'md' | 'lg'
}>(), {
    flag: null,
    languageCode: null,
    languageName: null,
    size: 'md',
})

const imageFailed = ref(false)

const rawFlag = computed(() => props.flag?.trim() ?? '')

const isImageSource = (value: string) => {
    return /^(https?:)?\/\//i.test(value)
        || value.startsWith('/')
        || value.startsWith('storage/')
        || value.startsWith('images/')
        || /\.(svg|png|jpe?g|webp|gif)$/i.test(value)
}

const imageSrc = computed(() => {
    if (!rawFlag.value || !isImageSource(rawFlag.value)) {
        return null
    }

    return rawFlag.value.startsWith('/') || /^(https?:)?\/\//i.test(rawFlag.value)
        ? rawFlag.value
        : `/${rawFlag.value}`
})

const fallbackLabel = computed(() => {
    if (rawFlag.value && !/^[a-z]{2}$/i.test(rawFlag.value)) {
        return rawFlag.value
    }

    return '🌐'
})

const sizeClass = computed(() => ({
    sm: 'h-4 w-5',
    md: 'h-5 w-6',
    lg: 'h-8 w-10',
}[props.size]))

watch(imageSrc, () => {
    imageFailed.value = false
})
</script>

<template>
    <img
        v-if="imageSrc && !imageFailed"
        :src="imageSrc"
        :alt="languageName ? `${languageName} flag` : ''"
        :class="sizeClass"
        class="shrink-0 rounded-sm object-cover shadow-sm ring-1 ring-black/5"
        loading="lazy"
        decoding="async"
        @error="imageFailed = true"
    />
    <span v-else class="shrink-0 leading-none" :class="{ 'text-3xl': size === 'lg' }">
        {{ fallbackLabel }}
    </span>
</template>
