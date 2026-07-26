<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useIntersectionObserver } from '@vueuse/core'
import { mediaUrl } from '@/lib/media'

// Must mirror the keys in config/ads.php.
type AdZone = 'header_banner' | 'footer_banner' | 'sidebar_top' | 'sidebar_bottom' | 'between_posts' | 'blog_after_content' | 'between_ai_tools' | 'tool_page_top' | 'tool_page_bottom' | 'chat_banner' | 'dashboard_top' | 'custom_zone_1' | 'custom_zone_2'

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

const props = withDefaults(defineProps<{
    zone: AdZone
    /**
     * Drop the card chrome (border + panel + shadow). Use where the ad sits inside
     * content that already provides its own surface, so the slot does not read as a
     * second nested card.
     */
    bare?: boolean
}>(), {
    bare: false,
})

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
    <!-- Image creatives carry their own artwork and background, so the card chrome
         (border + white panel + shadow) only framed them with an unwanted letterbox.
         Keep it for custom_html and adsense, which have no backdrop of their own. -->
    <div
        v-if="hasRenderableAd && ad"
        ref="adRef"
        class="ad-container overflow-hidden rounded-lg"
        :class="(ad.type === 'image_link' || bare)
            ? ''
            : 'border border-gray-200 bg-white shadow-sm transition hover:border-primary-200 dark:border-gray-800 dark:bg-gray-900'"
    >
        <a v-if="ad.type === 'image_link' && ad.image_url && ad.click_url" :href="ad.click_url" :target="ad.link_target" rel="noopener noreferrer" class="block">
            <img :src="mediaUrl(ad.image_url)" :alt="ad.title" class="h-auto w-full object-cover" loading="lazy" />
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
