<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useIntersectionObserver } from '@vueuse/core'

type AdZone = 'header_banner' | 'sidebar_top' | 'sidebar_bottom' | 'content_top' | 'content_bottom' | 'content-injection' | 'between_posts' | 'between_ai_tools' | 'tool_page_top' | 'tool_page_bottom' | 'template_page' | 'chat_banner' | 'dashboard_top' | 'footer_banner' | 'custom_zone_1' | 'custom_zone_2'

interface AdPayload {
    id: number
    title: string
    type: 'adsense' | 'custom_html' | 'image_link'
    zone: AdZone
    custom_html: string | null
    adsense_client: string | null
    adsense_slot: string | null
    adsense_format: string | null
    image_url: string | null
    link_url: string | null
    link_target: '_blank' | '_self'
    click_url: string | null
}

const props = defineProps<{
    zone: AdZone
}>()

const ad = ref<AdPayload | null>(null)
const adRef = ref<HTMLElement | null>(null)
const hasTrackedView = ref(false)
const hasRenderableAd = computed(() => {
    if (!ad.value) return false

    if (ad.value.type === 'image_link') {
        return Boolean(ad.value.image_url && ad.value.click_url)
    }

    if (ad.value.type === 'custom_html') {
        return Boolean(ad.value.custom_html && String(ad.value.custom_html).trim())
    }

    if (ad.value.type === 'adsense') {
        return Boolean(ad.value.adsense_client && ad.value.adsense_slot)
    }

    return false
})

const fetchAd = async () => {
    const response = await fetch(route('ads.active', { zone: props.zone }), {
        headers: { Accept: 'application/json' },
    })

    if (!response.ok) return

    ad.value = await response.json()

    if (ad.value?.type === 'adsense') {
        await nextTick()
        ;(window as any).adsbygoogle = (window as any).adsbygoogle || []
        ;(window as any).adsbygoogle.push({})
    }
}

const trackView = async () => {
    if (!ad.value || hasTrackedView.value) return

    const adId = Number(ad.value.id)

    if (!Number.isFinite(adId) || adId <= 0) return

    hasTrackedView.value = true
    await fetch(`/api/ads/${encodeURIComponent(String(adId))}/view`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
    })
}

useIntersectionObserver(adRef, ([entry]) => {
    if (entry?.isIntersecting) {
        void trackView()
    }
})

onMounted(() => {
    void fetchAd()
})
</script>

<template>
    <div v-if="hasRenderableAd && ad" ref="adRef" class="ad-container overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-200 dark:border-gray-800 dark:bg-gray-900">
        <a v-if="ad.type === 'image_link' && ad.image_url && ad.click_url" :href="ad.click_url" :target="ad.link_target" rel="noopener noreferrer" class="block">
            <img :src="ad.image_url" :alt="ad.title" class="h-auto w-full object-cover" loading="lazy" />
        </a>

        <div v-else-if="ad.type === 'custom_html'" class="flex min-h-16 items-center justify-center" v-html="ad.custom_html"></div>

        <ins
            v-else-if="ad.type === 'adsense'"
            class="adsbygoogle block"
            data-full-width-responsive="true"
            :data-ad-client="ad.adsense_client"
            :data-ad-slot="ad.adsense_slot"
            :data-ad-format="ad.adsense_format || 'auto'"
        ></ins>
    </div>
</template>
