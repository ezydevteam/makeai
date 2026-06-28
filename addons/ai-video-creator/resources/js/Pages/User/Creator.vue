<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed } from 'vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

const props = defineProps<{
    projects: { id: number; ulid: string; name: string }[]
    credit_costs: Record<string, number>
    max_video_duration: number
}>()

const tab = ref<'type' | 'configure' | 'review'>('type')
const selectedType = ref('')
const form = useForm({
    type: '',
    prompt: '',
    script: '',
    duration: 5,
    aspect_ratio: '16:9' as string,
    project_id: null as number | null,
    title: '',
    image: null as File | null,
    slides: [] as File[],
    slide_duration: 3,
    voice_id: null as string | null,
    music_volume: 0.3,
})

const types = computed(() => [
    { value: 'text_to_video', label: t('Text to Video'), icon: 'ti ti-video', desc: t('Generate a video from a text prompt'), credits: `${props.credit_costs.text_video}–${props.credit_costs.text_video_long}` },
    { value: 'image_to_video', label: t('Image to Video'), icon: 'ti ti-photo', desc: t('Animate any image into a video'), credits: props.credit_costs.image_video },
    { value: 'avatar_video', label: t('AI Avatar'), icon: 'ti ti-user', desc: t('Create a talking head from script'), credits: `${props.credit_costs.avatar_video} / 30s` },
    { value: 'slideshow', label: t('Slideshow'), icon: 'ti ti-slideshow', desc: t('Images + voiceover into MP4'), credits: `${props.credit_costs.slideshow} / min` },
])

const estimatedCredits = computed(() => {
    if (selectedType.value === 'text_to_video') return form.duration <= 5 ? props.credit_costs.text_video : props.credit_costs.text_video_long
    if (selectedType.value === 'image_to_video') return props.credit_costs.image_video
    if (selectedType.value === 'avatar_video') return Math.ceil(form.duration / 30) * props.credit_costs.avatar_video
    if (selectedType.value === 'slideshow') return Math.ceil((form.slides.length * form.slide_duration) / 60) * props.credit_costs.slideshow
    return 0
})

function selectType(type: string) {
    selectedType.value = type
    form.type = type
    tab.value = 'configure'
}

const generating = ref(false)

async function generate() {
    generating.value = true

    const fd = new FormData()
    fd.append('type', form.type)
    if (form.prompt) fd.append('prompt', form.prompt)
    if (form.script) fd.append('script', form.script)
    fd.append('duration', String(form.duration))
    fd.append('aspect_ratio', form.aspect_ratio)
    if (form.project_id) fd.append('project_id', String(form.project_id))
    if (form.title) fd.append('title', form.title)
    fd.append('slide_duration', String(form.slide_duration))
    if (form.voice_id) fd.append('voice_id', form.voice_id)
    fd.append('music_volume', String(form.music_volume))
    if (form.image) fd.append('image', form.image)
    form.slides.forEach((f, i) => fd.append(`slides[${i}]`, f))

    try {
        const resp = await fetch(route('addon.video.user.store'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' },
            body: fd,
        })
        if (resp.ok) {
            window.location.href = route('addon.video.user.library')
        }
    } catch {}
}
</script>

<template>
    <Head :title="t('Video Creator')" />

    <div class="mx-auto max-w-3xl space-y-6 p-4 sm:p-6">
        <h1 class="text-xl font-semibold">{{ t('Create Video') }}</h1>

        <!-- Tab 1: Choose Type -->
        <div v-if="tab === 'type'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <button v-for="typeItem in types" :key="typeItem.value" @click="selectType(typeItem.value)"
                    class="card p-5 text-left hover:border-emerald-300 transition cursor-pointer border-2"
                    :class="selectedType === typeItem.value ? 'border-emerald-500 bg-emerald-50' : 'border-transparent'">
                <i :class="[typeItem.icon, 'text-2xl text-emerald-600 mb-2 block']"></i>
                <div class="font-semibold">{{ typeItem.label }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ typeItem.desc }}</div>
                <div class="text-xs text-emerald-600 mt-2 font-medium">{{ typeItem.credits }} {{ t('credits') }}</div>
            </button>
        </div>

        <!-- Tab 2: Configure -->
        <div v-if="tab === 'configure'" class="space-y-4">
            <div v-if="selectedType === 'text_to_video' || selectedType === 'image_to_video'">
                <label class="block text-sm font-medium mb-1">{{ t('Prompt') }}</label>
                <textarea v-model="form.prompt" rows="3" class="input w-full" maxlength="2000"
                          :placeholder="t('Describe your video...')"></textarea>
            </div>
            <div v-if="selectedType === 'avatar_video' || selectedType === 'slideshow'">
                <label class="block text-sm font-medium mb-1">{{ t('Script') }}</label>
                <textarea v-model="form.script" rows="4" class="input w-full" maxlength="5000"
                          :placeholder="t('Script for voiceover...')"></textarea>
            </div>
            <div v-if="selectedType === 'image_to_video'">
                <label class="block text-sm font-medium mb-1">{{ t('Source Image') }}</label>
                <input type="file" accept="image/*" @change="(e: Event) => { const f = (e.target as HTMLInputElement).files; if (f) form.image = f[0] }" />
            </div>
            <div v-if="selectedType === 'slideshow'">
                <label class="block text-sm font-medium mb-1">{{ t('Slides (2-20 images)') }}</label>
                <input type="file" multiple accept="image/*" @change="(e: Event) => { const f = (e.target as HTMLInputElement).files; if (f) form.slides = Array.from(f) }" />
                <div class="mt-2 flex flex-col gap-4 sm:flex-row">
                    <div>
                        <label class="text-xs">{{ t('Slide duration') }}</label>
                        <select v-model="form.slide_duration" class="input">
                            <option :value="2">2s</option>
                            <option :value="3">3s</option>
                            <option :value="5">5s</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-4 sm:flex-row">
                <div class="sm:flex-1">
                    <label class="block text-xs mb-1">{{ t('Duration') }}</label>
                    <select v-model="form.duration" class="input w-full">
                        <option :value="5">5s</option>
                        <option :value="10">10s</option>
                    </select>
                </div>
                <div class="sm:flex-1">
                    <label class="block text-xs mb-1">{{ t('Aspect Ratio') }}</label>
                    <select v-model="form.aspect_ratio" class="input w-full">
                        <option value="16:9">16:9 🖥️</option>
                        <option value="9:16">9:16 📱</option>
                        <option value="1:1">1:1 ⬛</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ t('Title (optional)') }}</label>
                <input v-model="form.title" class="input w-full" maxlength="100" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ t('Project') }}</label>
                <select v-model="form.project_id" class="input w-full">
                    <option :value="null">{{ t('No project') }}</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button @click="tab = 'type'" class="btn btn-ghost btn-sm w-full justify-center sm:w-auto">{{ t('← Back') }}</button>
                <button @click="tab = 'review'" class="btn btn-sm btn-emerald"
                        :class="'w-full justify-center sm:w-auto'"
                        :disabled="!form.prompt && !form.script">{{ t('Review →') }}</button>
            </div>
        </div>

        <!-- Tab 3: Review -->
        <div v-if="tab === 'review'" class="card p-4 space-y-3">
            <div class="flex items-center justify-between gap-4 text-sm">
                <span class="text-gray-500">{{ t('Type') }}</span>
                <span class="font-medium text-right break-all">{{ selectedType }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 text-sm">
                <span class="text-gray-500">{{ t('Duration') }}</span>
                <span class="font-medium">{{ form.duration }}s</span>
            </div>
            <div class="flex items-center justify-between gap-4 text-sm">
                <span class="text-gray-500">{{ t('Credits') }}</span>
                <span class="font-medium text-emerald-600">{{ estimatedCredits }}</span>
            </div>
            <hr />
            <div class="flex flex-col gap-2 sm:flex-row">
                <button @click="tab = 'configure'" class="btn btn-ghost btn-sm w-full justify-center sm:w-auto">{{ t('← Back') }}</button>
                <button @click="generate" :disabled="generating" class="btn btn-sm btn-emerald w-full justify-center sm:w-auto">
                    {{ generating ? t('Generating...') : t('🎬 Generate Video') }}
                </button>
            </div>
        </div>
    </div>
</template>
