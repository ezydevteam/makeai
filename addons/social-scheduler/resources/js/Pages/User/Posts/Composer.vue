<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useRateLimit } from '@/Composables/useRateLimit'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed, onUnmounted } from 'vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()
const {
    isLimited: isBestTimeLimited,
    formattedCountdown: bestTimeCountdown,
    parseHeaders: parseBestTimeHeaders,
} = useRateLimit()

type SocialPost = {
    caption?: string
    platforms?: string[]
    post_type?: string
    title?: string
    hashtags?: string
    first_comment?: string
    scheduled_at?: string
    ss_campaign_id?: number | null
    platform_overrides?: Record<string, unknown>
    ulid?: string
}

type Account = {
    id: number
    platform: string
    platform_label: string
    platform_username: string | null
}

const props = defineProps<{
    post: SocialPost | null
    accounts: Account[]
    approval_required: boolean
    max_media_mb: number
    carousel_max_slides: number
    first_comment_enabled: boolean
}>()

const isEdit = computed(() => !!props.post)

function formatDateTimeLocal(value?: string | null) {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')

    return `${year}-${month}-${day}T${hours}:${minutes}`
}

const form = useForm({
    caption: props.post?.caption ?? '',
    platforms: props.post?.platforms ?? [] as string[],
    post_type: props.post?.post_type ?? 'single',
    title: props.post?.title ?? '',
    hashtags: props.post?.hashtags ?? '',
    first_comment: props.post?.first_comment ?? '',
    scheduled_at: formatDateTimeLocal(props.post?.scheduled_at),
    ss_campaign_id: props.post?.ss_campaign_id ?? null,
    platform_overrides: props.post?.platform_overrides ?? {} as Record<string, any>,
    media: [] as File[],
})

const platformOptions = computed(() => [
    { slug: 'instagram', label: t('Instagram'), icon: 'ti ti-brand-instagram' },
    { slug: 'facebook', label: t('Facebook'), icon: 'ti ti-brand-facebook' },
    { slug: 'twitter', label: t('X / Twitter'), icon: 'ti ti-brand-x' },
    { slug: 'linkedin', label: t('LinkedIn'), icon: 'ti ti-brand-linkedin' },
])

const availablePlatforms = computed(() =>
    platformOptions.value.filter(po => props.accounts.some(a => a.platform === po.slug))
)

const platformSelectOptions = computed<SelectOption[]>(() =>
    availablePlatforms.value.map((platform) => ({
        value: platform.slug,
        label: platform.label,
        icon: platform.icon,
    })),
)

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
const bestTimeResult = ref<{ suggested_time: string; reasoning: string; alternatives: string[] } | null>(null)
const bestTimeLoading = ref(false)
const bestTimeError = ref('')

const toneOptions = computed<SelectOption[]>(() => [
    { value: 'professional', label: t('Professional') },
    { value: 'casual', label: t('Casual') },
    { value: 'funny', label: t('Funny') },
    { value: 'inspirational', label: t('Inspirational') },
    { value: 'educational', label: t('Educational') },
])

async function readResponseMessage(response: Response) {
    const contentType = response.headers.get('content-type') || ''

    if (contentType.includes('application/json')) {
        const payload = await response.json() as { message?: string }
        return payload.message?.trim() || null
    }

    const text = (await response.text()).trim()

    if (!text) {
        return null
    }

    return text.length > 220 ? `${text.slice(0, 217)}...` : text
}

async function suggestBestTime() {
    if (bestTimeLoading.value || isBestTimeLimited.value) {
        return
    }

    bestTimeLoading.value = true
    bestTimeError.value = ''

    try {
        const response = await fetch(route('addon.social.user.best-time'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({
                platform: form.platforms[0] || 'twitter',
                content_type: form.post_type,
            }),
        })

        parseBestTimeHeaders(response.headers)

        if (response.status === 429) {
            bestTimeError.value = t('Best time suggestions are temporarily rate limited. Please try again in :time.', {
                time: bestTimeCountdown.value || '1:00',
            })
            bestTimeResult.value = null
            return
        }

        if (!response.ok) {
            bestTimeError.value = (await readResponseMessage(response))
                || t('Unable to fetch a best-time suggestion right now.')
            bestTimeResult.value = null
            return
        }

        const data = await response.json() as { suggested_time: string; reasoning: string; alternatives: string[] }
        bestTimeResult.value = data
    } catch {
        bestTimeError.value = t('Unable to fetch a best-time suggestion right now.')
    } finally {
        bestTimeLoading.value = false
    }
}

function applyBestTime() {
    if (bestTimeResult.value) {
        form.scheduled_at = formatDateTimeLocal(bestTimeResult.value.suggested_time)
        bestTimeResult.value = null
        bestTimeError.value = ''
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
    if (form.platforms.length === 0) {
        return 5000
    }

    const limits = form.platforms.map(p => charLimits[p] ?? 5000)
    return Math.min(...limits)
})
const overLimit = computed(() => charCount.value > maxChars.value)
const hasPreview = computed(() =>
    ['instagram', 'twitter', 'linkedin', 'facebook'].some((platform) => form.platforms.includes(platform)),
)

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

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ isEdit ? t('Edit Post') : t('New Post') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Create, schedule, and preview your social content in one place.') }}</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    @click.prevent="form.scheduled_at = ''; submit()"
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-surface-600 dark:hover:bg-surface-800"
                >
                    {{ t('Save Draft') }}
                </button>
                <button
                    @click="submit"
                    :disabled="form.processing || !form.caption.trim() || form.platforms.length === 0"
                    type="button"
                    class="inline-flex items-center justify-center rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ t('Schedule Post') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <!-- LEFT: Composer -->
            <div class="space-y-4">
                <!-- Platform Selector -->
                <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <AppSelect
                        v-model="form.platforms"
                        :options="platformSelectOptions"
                        :label="t('Platforms')"
                        :placeholder="t('Select platforms')"
                        :search-placeholder="t('Search platforms...')"
                        multiple
                        compact-multiple
                        live-search
                        :size="6"
                    />
                </div>

                <!-- Post Type -->
                <div v-if="form.platforms.length" class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Post Type') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabTypes"
                            :key="tab.value"
                            @click="form.post_type = tab.value"
                            type="button"
                            class="inline-flex items-center rounded-full px-3.5 py-2 text-xs font-semibold transition"
                            :class="form.post_type === tab.value
                                ? 'bg-primary-500 text-white shadow-sm'
                                : 'border border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-surface-600 dark:hover:bg-surface-800'"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <!-- Caption -->
                <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Caption') }}</label>
                    <textarea
                        v-model="form.caption"
                        rows="6"
                        maxlength="5000"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:bg-surface-900"
                        :placeholder="t('Write your post...')"
                    ></textarea>
                    <p class="mt-2 text-xs" :class="overLimit ? 'text-red-500' : 'text-gray-400'">
                        {{ charCount }} / {{ maxChars }}
                        <span v-if="overLimit">{{ t('Over limit!') }}</span>
                    </p>
                </div>

                <!-- AI Caption Generator -->
                <details class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <summary class="cursor-pointer select-none text-sm font-semibold text-gray-800 dark:text-gray-100">🤖 {{ t('AI Generate Caption') }}</summary>
                    <div class="mt-4 space-y-3">
                        <input
                            v-model="aiTopic"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:bg-surface-900"
                            :placeholder="t('What to post about?')"
                        />
                        <AppSelect
                            v-model="aiTone"
                            :options="toneOptions"
                            :label="t('Tone')"
                        />
                        <textarea
                            v-model="aiContext"
                            rows="2"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:bg-surface-900"
                            :placeholder="t('Extra context (optional)')"
                        ></textarea>
                        <div class="flex gap-2">
                            <button
                                @click="generateCaption"
                                :disabled="aiStreaming || !aiTopic.trim()"
                                type="button"
                                class="inline-flex items-center justify-center rounded-full bg-primary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ aiStreaming ? t('Generating...') : t('Generate') }}
                            </button>
                            <button
                                v-if="aiStreaming"
                                @click="stopGenerating"
                                type="button"
                                class="inline-flex items-center justify-center rounded-full border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-900/60 dark:bg-surface-900 dark:text-red-400 dark:hover:bg-red-950/30"
                            >
                                {{ t('Stop') }}
                            </button>
                        </div>
                        <div v-if="aiCaption" class="mt-2 rounded-2xl border border-gray-200 bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100">
                            {{ aiCaption }}
                            <button
                                @click="useAiCaption"
                                type="button"
                                class="mt-3 inline-flex items-center justify-center rounded-full bg-primary-500 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-600"
                            >
                                {{ t('Use This') }}
                            </button>
                        </div>
                    </div>
                </details>

                <!-- Hashtags -->
                <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hashtags') }}</label>
                    <input
                        v-model="form.hashtags"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:bg-surface-900"
                        :placeholder="t('#hashtag1 #hashtag2')"
                    />
                </div>

                <!-- Media Upload -->
                <div v-if="showCarousel" class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Carousel Slides') }} ({{ carouselSlides.length }}/{{ carousel_max_slides }})</label>
                    <div class="space-y-2">
                        <div v-for="(slide, idx) in carouselSlides" :key="idx" class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-6">#{{ idx + 1 }}</span>
                            <input
                                v-model="slide.caption"
                                class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:bg-surface-900"
                                :placeholder="t('Slide caption')"
                            />
                            <button
                                @click="removeSlide(idx)"
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-red-200 bg-white text-red-500 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900/60 dark:bg-surface-900 dark:text-red-400 dark:hover:bg-red-950/30"
                                :disabled="carouselSlides.length <= 1"
                            >&times;</button>
                        </div>
                        <button
                            @click="addSlide"
                            type="button"
                            class="inline-flex items-center justify-center rounded-full border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-surface-600 dark:hover:bg-surface-800"
                            :disabled="carouselSlides.length >= carousel_max_slides"
                        >+ {{ t('Add Slide') }}</button>
                    </div>
                </div>
                <div v-else class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Media') }}</label>
                    <input type="file" multiple accept="image/*,video/*" @change="(e: Event) => {
                        const files = (e.target as HTMLInputElement).files
                        if (files) form.media = Array.from(files)
                    }" class="block w-full rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-600 transition file:me-3 file:rounded-full file:border-0 file:bg-primary-500 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:border-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" />
                    <p class="text-xs text-gray-400 mt-1">{{ t('Max. upload') }} {{ max_media_mb }}MB</p>
                </div>

                <!-- First Comment -->
                <div v-if="form.platforms.includes('instagram') && first_comment_enabled" class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('First Comment (Instagram)') }}</label>
                    <input
                        v-model="form.first_comment"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:bg-surface-900"
                        :placeholder="t('First comment with more hashtags')"
                    />
                </div>

                <p v-if="approval_required" class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300">
                    ⚠️ {{ t('Approval is required before this post can be published.') }}
                </p>
            </div>

            <div class="space-y-6">
                <!-- RIGHT: Schedule -->
                <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Schedule') }}</label>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input
                            type="datetime-local"
                            v-model="form.scheduled_at"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-100 dark:focus:bg-surface-900"
                        />
                        <button
                            @click="suggestBestTime"
                            type="button"
                            :disabled="bestTimeLoading || isBestTimeLimited"
                            :title="t('AI suggest best time')"
                            class="inline-flex items-center justify-center rounded-xl btn-primary px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                        >{{ bestTimeLoading ? t('Checking...') : t('Find') }}</button>
                    </div>
                    <p v-if="bestTimeError" class="mt-2 text-xs text-amber-600 dark:text-amber-300">{{ bestTimeError }}</p>
                    <div v-if="bestTimeResult" class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900/60 dark:bg-amber-950/20">
                        <p class="font-medium">{{ new Date(bestTimeResult.suggested_time).toLocaleString() }}</p>
                        <p class="text-gray-500">{{ bestTimeResult.reasoning }}</p>
                        <button
                            @click="applyBestTime"
                            type="button"
                            class="mt-2 inline-flex items-center justify-center rounded-full bg-primary-500 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-600"
                        >{{ t('Use This') }}</button>
                    </div>
                </div>

                <!-- RIGHT: Preview -->
                <div class="h-fit rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Preview') }}</h3>

                    <div
                        v-if="!hasPreview"
                        class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50/80 p-5 text-center dark:border-surface-700 dark:bg-surface-800/60"
                    >
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Preview will appear here') }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Select at least one platform to see how your post will look.') }}</p>
                    </div>

                    <!-- Instagram preview -->
                    <div v-if="form.platforms.includes('instagram')" class="mt-4 max-w-[360px] space-y-2 rounded-2xl border border-gray-200 p-3 dark:border-surface-700">
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-6 h-6 rounded-full bg-gray-300"></div>
                            <span class="font-medium">{{ accounts.find(a => a.platform === 'instagram')?.platform_username || t('yourbrand') }}</span>
                        </div>
                        <div class="aspect-square bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">
                            {{ t('Image Placeholder') }}
                        </div>
                        <div class="text-xs whitespace-pre-wrap line-clamp-3">{{ form.caption || t('Your caption will appear here...') }}</div>
                    </div>

                    <!-- Twitter preview -->
                    <div v-if="form.platforms.includes('twitter')" class="mt-4 max-w-[360px] space-y-1 rounded-2xl border border-gray-200 p-3 dark:border-surface-700">
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-5 h-5 rounded-full bg-gray-300"></div>
                            <span class="font-medium">@{{ accounts.find(a => a.platform === 'twitter')?.platform_username || t('handle') }}</span>
                        </div>
                        <p class="text-sm whitespace-pre-wrap">{{ form.caption.slice(0, 280) || t('Your tweet...') }}</p>
                    </div>

                    <!-- LinkedIn preview -->
                    <div v-if="form.platforms.includes('linkedin')" class="mt-4 max-w-[360px] space-y-1 rounded-2xl border border-gray-200 p-3 dark:border-surface-700">
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-6 h-6 rounded-full bg-gray-300"></div>
                            <span class="font-medium">{{ accounts.find(a => a.platform === 'linkedin')?.platform_username || t('Your Name') }}</span>
                        </div>
                        <p class="text-xs whitespace-pre-wrap">{{ form.caption || t('Your LinkedIn post...') }}</p>
                    </div>

                    <!-- Facebook preview -->
                    <div v-if="form.platforms.includes('facebook')" class="mt-4 max-w-[360px] space-y-1 rounded-2xl border border-gray-200 p-3 dark:border-surface-700">
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-5 h-5 rounded-full bg-gray-300"></div>
                            <span class="font-medium">{{ accounts.find(a => a.platform === 'facebook')?.platform_username || t('Your Page') }}</span>
                        </div>
                        <p class="text-xs whitespace-pre-wrap">{{ form.caption || t('Your Facebook post...') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
