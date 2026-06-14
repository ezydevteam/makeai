<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{ settings: Record<string, any>; ffmpeg_found: boolean }>()

const form = useForm({ ...props.settings })

function save() {
    form.put(route('addon.video.admin.settings'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Video Creator Settings')" />

    <div class="p-6 max-w-2xl space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('Video Creator Settings') }}</h1>
            <button @click="save" :disabled="form.processing" class="btn btn-sm btn-emerald">{{ t('Save') }}</button>
        </div>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('General') }}</h3>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.enabled" class="toggle" />
                <span class="text-sm">{{ t('Enable Video Creator') }}</span>
            </label>
            <div>
                <label class="block text-sm mb-1">{{ t('Access for') }}</label>
                <select v-model="form.show_to" class="input">
                    <option value="all">{{ t('Everyone') }}</option>
                    <option value="logged_in">{{ t('Logged In') }}</option>
                    <option value="pro">{{ t('Pro Users') }}</option>
                </select>
            </div>
        </section>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('Providers') }}</h3>
            <div><label class="block text-xs mb-1">{{ t('Text-to-Video') }}</label><AppSelect v-model="form.text_video_provider" :options="[{value:'kling',label:'Kling AI'},{value:'runway',label:'Runway ML'},{value:'pika',label:'Pika Labs'},{value:'minimax',label:'Minimax'}]" /></div>
            <div><label class="block text-xs mb-1">{{ t('Image-to-Video') }}</label><AppSelect v-model="form.image_video_provider" :options="[{value:'kling',label:'Kling AI'},{value:'runway',label:'Runway ML'},{value:'pika',label:'Pika Labs'}]" /></div>
            <div><label class="block text-xs mb-1">{{ t('Avatar Video') }}</label><AppSelect v-model="form.avatar_provider" :options="[{value:'heygen',label:'HeyGen'},{value:'did',label:'D-ID'}]" /></div>
            <div><label class="block text-xs mb-1">{{ t('TTS (Voiceover)') }}</label><AppSelect v-model="form.tts_provider" :options="[{value:'openai',label:'OpenAI TTS'},{value:'elevenlabs',label:'ElevenLabs'}]" /></div>
            <details class="mt-2">
                <summary class="text-xs text-gray-500 cursor-pointer">{{ t('API Keys') }}</summary>
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <input v-model="form.kling_api_key" class="input text-sm" placeholder="Kling Access Key" />
                    <input v-model="form.runway_api_key" class="input text-sm" placeholder="Runway API Key" />
                    <input v-model="form.pika_api_key" class="input text-sm" placeholder="Pika API Key" />
                    <input v-model="form.minimax_api_key" class="input text-sm" placeholder="Minimax API Key" />
                    <input v-model="form.heygen_api_key" class="input text-sm" placeholder="HeyGen API Key" />
                    <input v-model="form.did_api_key" class="input text-sm" placeholder="D-ID API Key" />
                    <input v-model="form.elevenlabs_api_key" class="input text-sm" placeholder="ElevenLabs API Key" />
                </div>
            </details>
        </section>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('Credits') }}</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs">{{ t('Text-to-Video (5s)') }}</label><input type="number" v-model.number="form.credits_text_video" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Text-to-Video (10s)') }}</label><input type="number" v-model.number="form.credits_text_video_long" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Image-to-Video') }}</label><input type="number" v-model.number="form.credits_image_video" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Avatar (per 30s)') }}</label><input type="number" v-model.number="form.credits_avatar_video" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Slideshow (per min)') }}</label><input type="number" v-model.number="form.credits_slideshow" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Subtitles') }}</label><input type="number" v-model.number="form.credits_subtitles" class="input w-full" /></div>
            </div>
        </section>

        <section class="card p-4 space-y-3">
            <h3 class="font-medium">{{ t('Limits & Technical') }}</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs">{{ t('Max Duration (s)') }}</label><input type="number" v-model.number="form.max_video_duration" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Storage / User (MB)') }}</label><input type="number" v-model.number="form.max_storage_mb_per_user" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Auto-delete (days)') }}</label><input type="number" v-model.number="form.auto_delete_days" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Poll Interval (s)') }}</label><input type="number" v-model.number="form.poll_interval_seconds" class="input w-full" /></div>
                <div><label class="text-xs">{{ t('Max Poll Attempts') }}</label><input type="number" v-model.number="form.max_poll_attempts" class="input w-full" /></div>
                <div>
                    <label class="text-xs">{{ t('ffmpeg Path') }}</label>
                    <input v-model="form.ffmpeg_path" class="input w-full" />
                    <span class="text-xs" :class="ffmpeg_found ? 'text-emerald-600' : 'text-red-500'">
                        {{ ffmpeg_found ? '✓ ffmpeg found' : '⚠ ffmpeg not found' }}
                    </span>
                </div>
            </div>
        </section>
    </div>
</template>
