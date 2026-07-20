<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/UI/AppSelect.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

type Settings = {
    enabled: boolean
    show_to: 'all' | 'logged_in' | 'pro'
    text_video_provider: 'kling' | 'runway' | 'pika' | 'minimax'
    image_video_provider: 'kling' | 'runway' | 'pika'
    avatar_provider: 'heygen' | 'did'
    tts_provider: 'elevenlabs' | 'openai'
    subtitle_provider: 'whisper'
    kling_api_key: string
    kling_api_secret: string
    runway_api_key: string
    pika_api_key: string
    minimax_api_key: string
    heygen_api_key: string
    did_api_key: string
    elevenlabs_api_key: string
    slideshow_api_key: string
    max_video_duration: number
    max_storage_mb_per_user: number
    credits_text_video: number
    credits_text_video_long: number
    credits_image_video: number
    credits_avatar_video: number
    credits_slideshow: number
    credits_subtitles: number
    slideshow_provider: 'local' | 'api'
    slideshow_api_key: string
    ffmpeg_path: string
    poll_interval_seconds: number
    max_poll_attempts: number
    auto_delete_days: number
}

type ApiKeyStatus = {
    kling_api_key: string
    kling_api_secret: string
    runway_api_key: string
    pika_api_key: string
    minimax_api_key: string
    heygen_api_key: string
    did_api_key: string
    elevenlabs_api_key: string
}

const props = defineProps<{
    settings: Settings
    ffmpeg_found: boolean
    apiKeyStatus?: Partial<ApiKeyStatus>
}>()

const form = useForm<Settings>({
    ...props.settings,
    kling_api_key: '',
    kling_api_secret: '',
    runway_api_key: '',
    pika_api_key: '',
    minimax_api_key: '',
    heygen_api_key: '',
    did_api_key: '',
    elevenlabs_api_key: '',
    slideshow_api_key: '',
})

const save = () => form.put(route('addon.video.admin.settings'), { preserveScroll: true })

const providerOptions = {
    text_video_provider: [
        { value: 'kling', label: t('Kling AI') },
        { value: 'runway', label: t('Runway ML') },
        { value: 'pika', label: t('Pika Labs') },
        { value: 'minimax', label: t('Minimax') },
    ],
    image_video_provider: [
        { value: 'kling', label: t('Kling AI') },
        { value: 'runway', label: t('Runway ML') },
        { value: 'pika', label: t('Pika Labs') },
    ],
    avatar_provider: [
        { value: 'heygen', label: t('HeyGen') },
        { value: 'did', label: t('D-ID') },
    ],
    tts_provider: [
        { value: 'openai', label: t('OpenAI TTS') },
        { value: 'elevenlabs', label: t('ElevenLabs') },
    ],
    subtitle_provider: [
        { value: 'whisper', label: t('Whisper') },
    ],
    slideshow_provider: [
        { value: 'local', label: t('Local (FFMPEG)') },
        { value: 'api', label: t('API (Cloud)') },
    ],
} as const

const apiKeyStatus = computed<Partial<ApiKeyStatus>>(() => props.apiKeyStatus ?? {})
const isProAvailable = computed(() => Boolean(usePage().props.isProAvailable))

const accessOptions = computed(() => {
    const options = [
        { value: 'all', label: t('Everyone') },
        { value: 'logged_in', label: t('Logged In') },
    ]

    if (isProAvailable.value) {
        options.push({ value: 'pro', label: t('Pro Users') })
    }

    return options
})
</script>

<template>
    <Head :title="t('Video Creator Settings')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Video Creator Settings') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Control provider access, API keys, limits, and polling behavior from one unified settings page.') }}
                </p>
            </div>
            <button type="button" :disabled="form.processing" class="rounded-lg btn-primary-admin disabled:opacity-60" @click="save">
                {{ form.processing ? t('Saving...') : t('Save Changes') }}
            </button>
        </div>

        <form class="space-y-5" @submit.prevent="save">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('General') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Enable the addon and define who can access it.') }}
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Video Creator') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Hide the public tools while keeping the settings saved.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.enabled"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.enabled = !form.enabled"
                        >
                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Access for') }}</span>
                        <AppSelect v-model="form.show_to" :options="accessOptions" />
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Providers & API Keys') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Choose providers first, then add secrets only if you want to replace the current encrypted values.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ t('Text-to-Video') }}</label>
                        <AppSelect v-model="form.text_video_provider" :options="providerOptions.text_video_provider" />
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ t('Image-to-Video') }}</label>
                        <AppSelect v-model="form.image_video_provider" :options="providerOptions.image_video_provider" />
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ t('Avatar Video') }}</label>
                        <AppSelect v-model="form.avatar_provider" :options="providerOptions.avatar_provider" />
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ t('TTS (Voiceover)') }}</label>
                        <AppSelect v-model="form.tts_provider" :options="providerOptions.tts_provider" />
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60 md:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ t('Subtitle Provider') }}</label>
                        <AppSelect v-model="form.subtitle_provider" :options="providerOptions.subtitle_provider" />
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60 md:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ t('Slideshow Rendering') }}</label>
                        <AppSelect v-model="form.slideshow_provider" :options="providerOptions.slideshow_provider" />
                    </div>
                </div>

                <div class="mt-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('API Keys') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Leave a field blank to keep the current encrypted key.') }}
                    </p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Kling AI') }}</span>
                            <input v-model="form.kling_api_key" type="text" autocomplete="off" :placeholder="apiKeyStatus.kling_api_key || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Kling Secret') }}</span>
                            <input v-model="form.kling_api_secret" type="text" autocomplete="off" :placeholder="apiKeyStatus.kling_api_secret || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Runway ML') }}</span>
                            <input v-model="form.runway_api_key" type="text" autocomplete="off" :placeholder="apiKeyStatus.runway_api_key || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Pika Labs') }}</span>
                            <input v-model="form.pika_api_key" type="text" autocomplete="off" :placeholder="apiKeyStatus.pika_api_key || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Minimax') }}</span>
                            <input v-model="form.minimax_api_key" type="text" autocomplete="off" :placeholder="apiKeyStatus.minimax_api_key || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('HeyGen') }}</span>
                            <input v-model="form.heygen_api_key" type="text" autocomplete="off" :placeholder="apiKeyStatus.heygen_api_key || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('D-ID') }}</span>
                            <input v-model="form.did_api_key" type="text" autocomplete="off" :placeholder="apiKeyStatus.did_api_key || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('ElevenLabs') }}</span>
                            <input v-model="form.elevenlabs_api_key" type="text" autocomplete="off" :placeholder="apiKeyStatus.elevenlabs_api_key || t('Enter new key to replace the saved value')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                        <label v-if="form.slideshow_provider === 'api'" class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slideshow API Key') }}</span>
                            <input v-model="form.slideshow_api_key" type="text" autocomplete="off" :placeholder="t('Enter your cloud API key')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </label>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Credits') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Set the credit cost for each video workflow.') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Text-to-Video (5s)') }}</span>
                        <input v-model.number="form.credits_text_video" type="number" min="0" :placeholder="t('50')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Text-to-Video (10s)') }}</span>
                        <input v-model.number="form.credits_text_video_long" type="number" min="0" :placeholder="t('100')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Image-to-Video') }}</span>
                        <input v-model.number="form.credits_image_video" type="number" min="0" :placeholder="t('40')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Avatar (per 30s)') }}</span>
                        <input v-model.number="form.credits_avatar_video" type="number" min="0" :placeholder="t('80')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slideshow (per min)') }}</span>
                        <input v-model.number="form.credits_slideshow" type="number" min="0" :placeholder="t('30')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Subtitles') }}</span>
                        <input v-model.number="form.credits_subtitles" type="number" min="0" :placeholder="t('10')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Limits & Technical') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Keep render length, storage, poll timing, and ffmpeg settings under control.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Duration (s)') }}</span>
                        <input v-model.number="form.max_video_duration" type="number" min="5" :placeholder="t('30')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Storage / User (MB)') }}</span>
                        <input v-model.number="form.max_storage_mb_per_user" type="number" min="10" :placeholder="t('500')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-delete (days)') }}</span>
                        <input v-model.number="form.auto_delete_days" type="number" min="0" :placeholder="t('30')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Poll Interval (s)') }}</span>
                        <input v-model.number="form.poll_interval_seconds" type="number" min="5" :placeholder="t('30')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Poll Attempts') }}</span>
                        <input v-model.number="form.max_poll_attempts" type="number" min="5" :placeholder="t('20')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </label>
                    <label class="block md:col-span-2 xl:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('ffmpeg Path') }}</span>
                        <input v-model="form.ffmpeg_path" type="text" :placeholder="t('/usr/bin/ffmpeg')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <span class="mt-1 block text-xs" :class="props.ffmpeg_found ? 'text-emerald-600' : 'text-red-500'">
                            {{ props.ffmpeg_found ? t('ffmpeg found') : t('ffmpeg not found') }}
                        </span>
                    </label>
                </div>
            </section>
        </form>
    </div>
</template>
