<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed, watch, onUnmounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

const props = defineProps<{
    post: any
    accounts: { id: number; platform: string; platform_label: string; platform_username: string | null }[]
    approval_required: boolean
    max_media_mb: number
    carousel_max_slides: number
    first_comment_enabled: boolean
}>()

const isEdit = computed(() => !!props.post)

const form = useForm({
    caption: props.post?.caption ?? '',
    platforms: props.post?.platforms ?? [] as string[],
    post_type: props.post?.post_type ?? 'single',
    title: props.post?.title ?? '',
    hashtags: props.post?.hashtags ?? '',
    first_comment: props.post?.first_comment ?? '',
    scheduled_at: props.post?.scheduled_at ?? '',
    ss_campaign_id: props.post?.ss_campaign_id ?? null,
    platform_overrides: props.post?.platform_overrides ?? {} as Record<string, any>,
    media: [] as File[],
})

const platformOptions = [
    { slug: 'instagram', label: 'Instagram', icon: 'ti ti-brand-instagram' },
    { slug: 'facebook', label: 'Facebook', icon: 'ti ti-brand-facebook' },
    { slug: 'twitter', label: 'X / Twitter', icon: 'ti ti-brand-x' },
    { slug: 'linkedin', label: 'LinkedIn', icon: 'ti ti-brand-linkedin' },
]

const availablePlatforms = computed(() =>
    platformOptions.filter(po => props.accounts.some(a => a.platform === po.slug))
)

function togglePlatform(slug: string) {
    const i = form.platforms.indexOf(slug)
    if (i >= 0) form.platforms.splice(i, 1)
    else form.platforms.push(slug)
}

const tabTypes = computed(() => {
    const tabs = [{ value: 'single', label: t('Single') }]
    if (form.platforms.includes('twitter')) tabs.push({ value: 'thread', label: t('Thread') })
    if (form.platforms.some(p => ['instagram', 'facebook'].includes(p))) {
        tabs.push({ value: 'carousel', label: t('Carousel') }, { value: 'story', label: t('Story') }, { value: 'reel', label: t('Reel') })
    }
    return tabs
})

// AI Caption streaming
const aiTopic = ref('')
const aiTone = ref('professional')
const aiContext = ref('')
const aiStreaming = ref(false)
const aiCaption = ref('')
const aiAbort = ref<AbortController | null>(null)

async function generateCaption() {
    aiStreaming.value = true
    aiCaption.value = ''
    aiAbort.value = new AbortController()
    try {
        const resp = await fetch(route('addon.social.user.caption.generate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ topic: aiTopic.value, platform: form.platforms[0] || 'twitter', tone: aiTone.value, context: aiContext.value }),
            signal: aiAbort.value.signal,
        })
        const reader = resp.body?.getReader()
        if (!reader) return
        const decoder = new TextDecoder()
        while (true) {
            const { done, value } = await reader.read()
            if (done) break
            aiCaption.value += decoder.decode(value, { stream: true })
        }
    } catch (e: any) {
        if (e.name !== 'AbortError') console.error(e)
    } finally {
        aiStreaming.value = false
    }
}

function stopGenerating() {
    aiAbort.value?.abort()
    aiStreaming.value = false
}

function useAiCaption() {
    form.caption = aiCaption.value
    aiCaption.value = ''
    aiTopic.value = ''
}

// Best Time
const bestTimeResult = ref<any>(null)

async function suggestBestTime() {
    try {
        const { data } = await axios.post(route('addon.social.user.best-time'), {
            platform: form.platforms[0] || 'twitter',
            content_type: form.post_type,
        })
        bestTimeResult.value = data
    } catch {}
}

function applyBestTime() {
    if (bestTimeResult.value) {
        form.scheduled_at = bestTimeResult.value.suggested_time
        bestTimeResult.value = null
    }
}

// Post type tabs
const showCarousel = computed(() => form.post_type === 'carousel')
const carouselSlides = ref<{ caption: string }[]>([{ caption: '' }])

function addSlide() {
    if (carouselSlides.value.length < props.carousel_max_slides) {
        carouselSlides.value.push({ caption: '' })
    }
}
function removeSlide(index: number) {
    carouselSlides.value.splice(index, 1)
}

// Character limit
const charLimits: Record<string, number> = { twitter: 280, instagram: 2200, facebook: 5000, linkedin: 3000 }
const charCount = computed(() => form.caption.length)
const maxChars = computed(() => {
    const limits = form.platforms.map(p => charLimits[p] ?? 5000)
    return Math.min(...limits)
})
const overLimit = computed(() => charCount.value > maxChars.value)

function submit() {
    if (isEdit.value) {
        form.post(route('addon.social.user.posts.index') + '/' + props.post.ulid, {
            _method: 'PUT',
            preserveScroll: true,
        })
    } else {
        form.post(route('addon.social.user.posts.index'), {
            preserveScroll: true,
        })
    }
}

onUnmounted(() => { aiAbort.value?.abort() })
</script>

<template>
    <Head :title="isEdit ? t('Edit Post') : t('Composer')" />

    <div class="p-6 max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ isEdit ? t('Edit Post') : t('New Post') }}</h1>
            <div class="flex gap-2">
                <button @click="form.status = 'draft'" class="btn btn-ghost btn-sm"
                        @click.prevent="form.scheduled_at = ''; submit()">{{ t('Save Draft') }}</button>
                <button @click="submit" :disabled="form.processing || !form.caption.trim() || form.platforms.length === 0"
                        class="btn btn-sm btn-emerald">{{ t('Schedule Post') }}</button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- LEFT: Composer -->
            <div class="space-y-4">
                <!-- Platform Selector -->
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('Platforms') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="po in availablePlatforms" :key="po.slug"
                                @click="togglePlatform(po.slug)"
                                class="btn btn-sm" :class="form.platforms.includes(po.slug) ? 'btn-emerald' : 'btn-ghost'">
                            <i :class="po.icon" class="mr-1"></i> {{ po.label }}
                        </button>
                    </div>
                </div>

                <!-- Post Type -->
                <div v-if="form.platforms.length">
                    <label class="block text-sm font-medium mb-1">{{ t('Post Type') }}</label>
                    <div class="flex flex-wrap gap-1">
                        <button v-for="tab in tabTypes" :key="tab.value"
                                @click="form.post_type = tab.value"
                                class="btn btn-xs" :class="form.post_type === tab.value ? 'btn-emerald' : 'btn-ghost'">{{ tab.label }}</button>
                    </div>
                </div>

                <!-- Caption -->
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('Caption') }}</label>
                    <textarea v-model="form.caption" rows="5" class="input w-full" maxlength="5000"
                              :placeholder="t('Write your post...')"></textarea>
                    <p class="text-xs mt-1" :class="overLimit ? 'text-red-500' : 'text-gray-400'">
                        {{ charCount }} / {{ maxChars }}
                        <span v-if="overLimit">{{ t('Over limit!') }}</span>
                    </p>
                </div>

                <!-- AI Caption Generator -->
                <details class="card p-3">
                    <summary class="font-medium text-sm cursor-pointer select-none">🤖 {{ t('AI Generate Caption') }}</summary>
                    <div class="mt-3 space-y-2">
                        <input v-model="aiTopic" class="input w-full" :placeholder="t('What to post about?')" />
                        <select v-model="aiTone" class="input w-full">
                            <option value="professional">{{ t('Professional') }}</option>
                            <option value="casual">{{ t('Casual') }}</option>
                            <option value="funny">{{ t('Funny') }}</option>
                            <option value="inspirational">{{ t('Inspirational') }}</option>
                            <option value="educational">{{ t('Educational') }}</option>
                        </select>
                        <textarea v-model="aiContext" rows="2" class="input w-full" :placeholder="t('Extra context (optional)')"></textarea>
                        <div class="flex gap-2">
                            <button @click="generateCaption" :disabled="aiStreaming || !aiTopic.trim()" class="btn btn-sm btn-emerald">
                                {{ aiStreaming ? t('Generating...') : t('Generate') }}
                            </button>
                            <button v-if="aiStreaming" @click="stopGenerating" class="btn btn-sm btn-ghost text-red-500">{{ t('Stop') }}</button>
                        </div>
                        <div v-if="aiCaption" class="mt-2 p-2 bg-gray-50 rounded text-sm whitespace-pre-wrap relative">
                            {{ aiCaption }}
                            <button @click="useAiCaption" class="btn btn-xs btn-emerald mt-2">{{ t('Use This') }}</button>
                        </div>
                    </div>
                </details>

                <!-- Hashtags -->
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('Hashtags') }}</label>
                    <input v-model="form.hashtags" class="input w-full" :placeholder="'#hashtag1 #hashtag2'" />
                </div>

                <!-- Media Upload -->
                <div v-if="showCarousel">
                    <label class="block text-sm font-medium mb-1">{{ t('Carousel Slides') }} ({{ carouselSlides.length }}/{{ carousel_max_slides }})</label>
                    <div class="space-y-2">
                        <div v-for="(slide, idx) in carouselSlides" :key="idx" class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-6">#{{ idx + 1 }}</span>
                            <input v-model="slide.caption" class="input flex-1" :placeholder="t('Slide caption')" />
                            <button @click="removeSlide(idx)" class="btn btn-ghost btn-xs text-red-500" :disabled="carouselSlides.length <= 1">&times;</button>
                        </div>
                        <button @click="addSlide" class="btn btn-xs btn-ghost" :disabled="carouselSlides.length >= carousel_max_slides">+ {{ t('Add Slide') }}</button>
                    </div>
                </div>
                <div v-else>
                    <label class="block text-sm font-medium mb-1">{{ t('Media') }}</label>
                    <input type="file" multiple accept="image/*,video/*" @change="(e: Event) => {
                        const files = (e.target as HTMLInputElement).files
                        if (files) form.media = Array.from(files)
                    }" class="text-sm" />
                    <p class="text-xs text-gray-400 mt-1">{{ t('Max') }} {{ max_media_mb }}MB</p>
                </div>

                <!-- First Comment -->
                <div v-if="form.platforms.includes('instagram') && first_comment_enabled">
                    <label class="block text-sm font-medium mb-1">{{ t('First Comment (Instagram)') }}</label>
                    <input v-model="form.first_comment" class="input w-full" :placeholder="t('First comment with more hashtags')" />
                </div>

                <!-- Schedule -->
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('Schedule') }}</label>
                    <div class="flex gap-2 items-center">
                        <input type="datetime-local" v-model="form.scheduled_at" class="input" />
                        <button @click="suggestBestTime" class="btn btn-xs btn-ghost">💡 {{ t('Best Time') }}</button>
                    </div>
                    <div v-if="bestTimeResult" class="mt-2 p-2 bg-amber-50 rounded text-sm">
                        <p class="font-medium">{{ new Date(bestTimeResult.suggested_time).toLocaleString() }}</p>
                        <p class="text-gray-500">{{ bestTimeResult.reasoning }}</p>
                        <button @click="applyBestTime" class="btn btn-xs btn-emerald mt-1">{{ t('Use This') }}</button>
                    </div>
                </div>

                <p v-if="approval_required" class="text-sm text-amber-600 bg-amber-50 p-2 rounded">
                    ⚠️ {{ t('Approval is required before this post can be published.') }}
                </p>
            </div>

            <!-- RIGHT: Preview -->
            <div class="card p-4 space-y-4 sticky top-6">
                <h3 class="font-medium">{{ t('Preview') }}</h3>

                <!-- Instagram preview -->
                <div v-if="form.platforms.includes('instagram')" class="border rounded-lg p-3 max-w-[320px] space-y-2">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="w-6 h-6 rounded-full bg-gray-300"></div>
                        <span class="font-medium">{{ accounts.find(a => a.platform === 'instagram')?.platform_username || 'yourbrand' }}</span>
                    </div>
                    <div class="aspect-square bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">
                        {{ t('Image Placeholder') }}
                    </div>
                    <div class="text-xs whitespace-pre-wrap line-clamp-3">{{ form.caption || t('Your caption will appear here...') }}</div>
                </div>

                <!-- Twitter preview -->
                <div v-if="form.platforms.includes('twitter')" class="border rounded-lg p-3 max-w-[320px] space-y-1">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="w-5 h-5 rounded-full bg-gray-300"></div>
                        <span class="font-medium">@{{ accounts.find(a => a.platform === 'twitter')?.platform_username || 'handle' }}</span>
                    </div>
                    <p class="text-sm whitespace-pre-wrap">{{ form.caption.slice(0, 280) || t('Your tweet...') }}</p>
                </div>

                <!-- LinkedIn preview -->
                <div v-if="form.platforms.includes('linkedin')" class="border rounded-lg p-3 max-w-[320px] space-y-1">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="w-6 h-6 rounded-full bg-gray-300"></div>
                        <span class="font-medium">{{ accounts.find(a => a.platform === 'linkedin')?.platform_username || 'Your Name' }}</span>
                    </div>
                    <p class="text-xs whitespace-pre-wrap">{{ form.caption || t('Your LinkedIn post...') }}</p>
                </div>

                <!-- Facebook preview -->
                <div v-if="form.platforms.includes('facebook')" class="border rounded-lg p-3 max-w-[320px] space-y-1">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="w-5 h-5 rounded-full bg-gray-300"></div>
                        <span class="font-medium">{{ accounts.find(a => a.platform === 'facebook')?.platform_username || 'Your Page' }}</span>
                    </div>
                    <p class="text-xs whitespace-pre-wrap">{{ form.caption || t('Your Facebook post...') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
