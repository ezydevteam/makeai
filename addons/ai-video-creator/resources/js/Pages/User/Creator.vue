<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

type Project = {
    id: number
    ulid: string
    name: string
}

type CreditCosts = {
    text_video: number
    text_video_long: number
    image_video: number
    avatar_video: number
    slideshow: number
}

const props = defineProps<{
    projects: Project[]
    credit_costs: CreditCosts
    max_video_duration: number
    max_storage_mb_per_user: number
}>()

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

const selectedType = ref('')
const generating = ref(false)

const types = computed(() => [
    {
        value: 'text_to_video',
        label: t('Text to Video'),
        icon: 'ti ti-video',
        desc: t('Generate a video from a text prompt.'),
        credits: `${props.credit_costs.text_video}–${props.credit_costs.text_video_long}`,
    },
    {
        value: 'image_to_video',
        label: t('Image to Video'),
        icon: 'ti ti-photo',
        desc: t('Animate any image into a video.'),
        credits: props.credit_costs.image_video,
    },
    {
        value: 'avatar_video',
        label: t('AI Avatar'),
        icon: 'ti ti-user',
        desc: t('Create a talking head from a script.'),
        credits: `${props.credit_costs.avatar_video} / 30s`,
    },
    {
        value: 'slideshow',
        label: t('Slideshow'),
        icon: 'ti ti-slideshow',
        desc: t('Images and voiceover into MP4.'),
        credits: `${props.credit_costs.slideshow} / min`,
    },
])

const aspectRatios = computed(() => [
    { value: '16:9', label: t('Landscape') },
    { value: '9:16', label: t('Portrait') },
    { value: '1:1', label: t('Square') },
])

const durationOptions = computed(() => [
    { value: 5, label: `5s` },
    { value: 10, label: `10s` },
])

const slideDurationOptions = computed(() => [
    { value: 2, label: `2s` },
    { value: 3, label: `3s` },
    { value: 5, label: `5s` },
])

const musicVolumeOptions = computed(() => [
    { value: 0, label: `0%` },
    { value: 0.3, label: `30%` },
    { value: 0.5, label: `50%` },
])

const projectOptions = computed(() => [
    { value: null, label: t('No project') },
    ...props.projects.map((project) => ({ value: project.id, label: project.name })),
])

const selectedTypeMeta = computed(() => types.value.find((item) => item.value === selectedType.value) ?? null)

const typeRequirements = computed(() => ({
    prompt: selectedType.value === 'text_to_video' || selectedType.value === 'image_to_video',
    script: selectedType.value === 'avatar_video' || selectedType.value === 'slideshow',
    image: selectedType.value === 'image_to_video',
    slides: selectedType.value === 'slideshow',
}))

const estimatedCredits = computed(() => {
    if (selectedType.value === 'text_to_video') return form.duration <= 5 ? props.credit_costs.text_video : props.credit_costs.text_video_long
    if (selectedType.value === 'image_to_video') return props.credit_costs.image_video
    if (selectedType.value === 'avatar_video') return Math.ceil(form.duration / 30) * props.credit_costs.avatar_video
    if (selectedType.value === 'slideshow') return Math.ceil((form.slides.length * form.slide_duration) / 60) * props.credit_costs.slideshow
    return 0
})

const canGenerate = computed(() => {
    if (!selectedType.value) return false
    if (typeRequirements.value.prompt && !form.prompt) return false
    if (typeRequirements.value.script && !form.script) return false
    if (typeRequirements.value.image && !form.image) return false
    if (typeRequirements.value.slides && form.slides.length < 2) return false
    return true
})

function selectType(type: string) {
    selectedType.value = type
    form.type = type
}

async function generate() {
    if (!canGenerate.value || generating.value) {
        return
    }

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
    form.slides.forEach((file, index) => fd.append(`slides[${index}]`, file))

    try {
        const resp = await fetch(route('addon.video.user.store'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: fd,
        })

        if (resp.ok) {
            window.location.href = route('addon.video.user.library')
        }
    } catch {
        // Keep the page quiet and let the user retry from the same form.
    } finally {
        generating.value = false
    }
}
</script>

<template>
    <Head :title="t('Video Creator')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Create Video') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Choose a format, fill the needed fields, and keep the workflow in one clean dashboard screen.') }}
                </p>
            </div>

            <Link
                :href="route('addon.video.user.library')"
                class="inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
            >
                <i class="ti ti-arrow-left text-sm"></i>
                {{ t('Back') }}
            </Link>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6 space-y-6">
                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Choose Type') }}</h2>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ t('One selection only') }}</span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <button
                            v-for="typeItem in types"
                            :key="typeItem.value"
                            type="button"
                            @click="selectType(typeItem.value)"
                            class="rounded-2xl border p-4 text-left transition"
                            :class="selectedType === typeItem.value
                                ? 'border-primary-300 bg-primary-50 text-gray-900 shadow-sm dark:border-primary-700/50 dark:bg-primary-500/10 dark:text-white'
                                : 'border-gray-200 bg-gray-50/60 text-gray-700 hover:border-primary-200 hover:bg-primary-50/60 dark:border-surface-800 dark:bg-surface-800/70 dark:text-gray-300 dark:hover:border-primary-800/50 dark:hover:bg-primary-500/10'"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
                                    <i :class="typeItem.icon" class="text-xl"></i>
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ typeItem.label }}</span>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ typeItem.desc }}</p>
                                </div>
                            </div>
                        </button>
                    </div>
                </section>

                <section class="space-y-4 border-t border-gray-100 pt-6 dark:border-surface-800">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Details') }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Only the fields for the selected type will appear.') }}</p>
                    </div>

                    <div v-if="typeRequirements.prompt" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Prompt') }}</label>
                        <textarea
                            v-model="form.prompt"
                            rows="4"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            maxlength="2000"
                            :placeholder="t('Describe your video...')"
                        />
                    </div>

                    <div v-if="typeRequirements.script" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Script') }}</label>
                        <textarea
                            v-model="form.script"
                            rows="5"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            maxlength="5000"
                            :placeholder="t('Write the narration or dialogue...')"
                        />
                    </div>

                    <div v-if="typeRequirements.image" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Source Image') }}</label>
                        <input
                            type="file"
                            accept="image/*"
                            class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 file:mr-4 file:rounded-full file:border-0 file:bg-primary-50 file:px-4 file:py-1.5 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:file:bg-primary-500/15 dark:file:text-primary-300 dark:hover:file:bg-primary-500/20"
                            @change="(e: Event) => { const files = (e.target as HTMLInputElement).files; if (files) form.image = files[0] }"
                        />
                    </div>

                    <div v-if="typeRequirements.slides" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slides') }}</label>
                        <input
                            type="file"
                            multiple
                            accept="image/*"
                            class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 file:mr-4 file:rounded-full file:border-0 file:bg-primary-50 file:px-4 file:py-1.5 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:file:bg-primary-500/15 dark:file:text-primary-300 dark:hover:file:bg-primary-500/20"
                            @change="(e: Event) => { const files = (e.target as HTMLInputElement).files; if (files) form.slides = Array.from(files) }"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Add at least 2 images.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <AppSelect
                                v-model="form.duration"
                                :label="t('Duration')"
                                :options="durationOptions"
                            />
                        </div>

                        <div class="space-y-2">
                            <AppSelect
                                v-model="form.aspect_ratio"
                                :label="t('Aspect Ratio')"
                                :options="aspectRatios"
                            />
                        </div>
                    </div>

                    <div v-if="typeRequirements.slides" class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <AppSelect
                                v-model="form.slide_duration"
                                :label="t('Slide duration')"
                                :options="slideDurationOptions"
                            />
                        </div>

                        <div class="space-y-2">
                            <AppSelect
                                v-model="form.music_volume"
                                :label="t('Music Volume')"
                                :options="musicVolumeOptions"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title (optional)') }}</label>
                            <input
                                v-model="form.title"
                                maxlength="100"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div class="space-y-2">
                            <AppSelect
                                v-model="form.project_id"
                                :label="t('Project')"
                                :options="projectOptions"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex flex-col gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Your video will be queued after you submit this form.') }}
                    </p>
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold !text-white shadow-sm transition hover:bg-primary-600 disabled:opacity-60 sm:w-auto"
                        :disabled="generating || !canGenerate"
                        @click="generate"
                    >
                        <i v-if="generating" class="ti ti-loader-2 animate-spin text-sm"></i>
                        <i v-else class="ti ti-video-plus text-sm"></i>
                        {{ generating ? t('Generating...') : t('Generate Video') }}
                    </button>
                </div>
            </div>

            <aside class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6 h-fit space-y-4">
                <div class="space-y-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Summary') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('A compact view of the current setup.') }}</p>
                </div>

                <div class="space-y-3 rounded-2xl bg-gray-100 border border-gray-200 p-4 dark:!bg-surface-800/60">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">{{ t('Type') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ selectedTypeMeta?.label ?? t('Not selected') }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">{{ t('Estimated credits') }}</span>
                        <span class="font-medium text-primary-600 dark:text-primary-300">{{ estimatedCredits }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">{{ t('Duration') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ form.duration }}s</span>
                    </div>
                </div>

                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex items-center justify-between gap-3">
                        <span>{{ t('Max video duration') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ props.max_video_duration }}s</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span>{{ t('Storage limit') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ props.max_storage_mb_per_user }} MB</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span>{{ t('Projects') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ projects.length }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
