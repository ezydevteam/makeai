<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

interface SyncStatus {
    last_synced_at: string | null
    voice_count: number
}

interface VoiceoverSettings {
    enabled: boolean
    default_provider: string
    default_voice_id: string
    speed_default: string
    stability_default: string
    similarity_boost_default: string
    podcast_enabled: boolean
    podcast_base_url: string
    credits_per_1k_chars: number
    credits_stt: number
    max_script_chars: number
    max_file_size_mb: number
    background_music_max_size_mb: number
    auto_transcribe: boolean
    ffmpeg_path: string
    auto_delete_days: number
    elevenlabs_api_key: string
    openai_api_key: string
    murf_api_key: string
    playht_api_key: string
    playht_user_id: string
    show_to: string
}

type ProviderId = 'elevenlabs' | 'openai' | 'murf' | 'playht'

interface ProviderMeta {
    label: string
    apiKeyField: keyof Pick<
        VoiceoverSettings,
        'elevenlabs_api_key' | 'openai_api_key' | 'murf_api_key' | 'playht_api_key'
    >
    apiKeyPlaceholder: string
    extraField?: {
        field: keyof Pick<VoiceoverSettings, 'playht_user_id'>
        label: string
        placeholder: string
    }
}

const { t } = useTranslate()
const toast = useToastr()
const page = usePage()

const props = defineProps<{
    voiceSyncStatus: Record<string, SyncStatus>
    systemStatus: {
        ffmpeg: boolean
        ffprobe: boolean
    }
    accessLevels: Array<{ value: string; label: string; description?: string | null }>
}>()

const form = useForm<VoiceoverSettings>({
    enabled: true,
    default_provider: 'openai',
    default_voice_id: 'alloy',
    speed_default: '1.0',
    stability_default: '0.5',
    similarity_boost_default: '0.75',
    podcast_enabled: true,
    podcast_base_url: '',
    credits_per_1k_chars: 5,
    credits_stt: 10,
    max_script_chars: 5000,
    max_file_size_mb: 25,
    background_music_max_size_mb: 10,
    auto_transcribe: false,
    ffmpeg_path: '/usr/bin/ffmpeg',
    auto_delete_days: 0,
    elevenlabs_api_key: '',
    openai_api_key: '',
    murf_api_key: '',
    playht_api_key: '',
    playht_user_id: '',
    show_to: 'login',
})

const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const providerMeta: Record<ProviderId, ProviderMeta> = {
    elevenlabs: {
        label: t('ElevenLabs'),
        apiKeyField: 'elevenlabs_api_key',
        apiKeyPlaceholder: t('Enter ElevenLabs API key'),
    },
    openai: {
        label: t('OpenAI'),
        apiKeyField: 'openai_api_key',
        apiKeyPlaceholder: t('Enter OpenAI API key'),
    },
    murf: {
        label: t('Murf'),
        apiKeyField: 'murf_api_key',
        apiKeyPlaceholder: t('Enter Murf API key'),
    },
    playht: {
        label: t('PlayHT'),
        apiKeyField: 'playht_api_key',
        apiKeyPlaceholder: t('Enter PlayHT API key'),
        extraField: {
            field: 'playht_user_id',
            label: t('PlayHT User ID'),
            placeholder: t('Enter PlayHT user ID'),
        },
    },
}

const selectedProvider = computed<ProviderId>(() => {
    const provider = form.default_provider as ProviderId

    return provider in providerMeta ? provider : 'openai'
})

const selectedProviderMeta = computed(() => providerMeta[selectedProvider.value])
const selectedProviderStatus = computed(() => props.voiceSyncStatus[selectedProvider.value] ?? null)

// Same access levels as the core AI tools (server-provided: guest / login /
// premium / plan:*). Premium/plan options are already filtered server-side when
// pro isn't available.
const accessOptions = computed<SelectOption[]>(() =>
    (props.accessLevels ?? []).map((l) => ({ value: l.value, label: l.label }))
)

const providerOptions: SelectOption[] = [
    { value: 'elevenlabs', label: t('ElevenLabs') },
    { value: 'openai', label: t('OpenAI') },
    { value: 'murf', label: t('Murf') },
    { value: 'playht', label: t('PlayHT') },
]

const speedOptions: SelectOption[] = [
    { value: '0.8', label: '0.8' },
    { value: '1.0', label: '1.0' },
    { value: '1.2', label: '1.2' },
    { value: '1.5', label: '1.5' },
]

const save = () => {
    form.put(route('addon.vo.admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('Settings saved.')),
    })
}

const syncVoices = () => {
    form.post(route('addon.vo.admin.sync-voices'), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('Voice sync dispatched.')),
    })
}
</script>

<template>
    <Head :title="t('Voiceover Settings')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Voiceover Settings') }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Control voice generation, podcast feeds, fallback API keys, and transcription limits from one unified settings page.') }}
                </p>
            </div>

            <button
                type="button"
                :disabled="form.processing"
                class="rounded-lg btn-primary-admin disabled:opacity-60"
                @click="save"
            >
                {{ form.processing ? t('Saving...') : t('Save Changes') }}
            </button>
        </div>

        <form class="space-y-5" @submit.prevent="save">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('General') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Enable the addon and set who can access it.') }}
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Voiceover Studio') }}</span>
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
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>

                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Podcast RSS feeds') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Expose podcast feed features for generated shows.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.podcast_enabled"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.podcast_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.podcast_enabled = !form.podcast_enabled"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.podcast_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>

                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-transcribe generated audio') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Send generated audio through speech-to-text automatically.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.auto_transcribe"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.auto_transcribe ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.auto_transcribe = !form.auto_transcribe"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.auto_transcribe ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Access for') }}</span>
                        <AppSelect
                            v-model="form.show_to"
                            :options="accessOptions"
                            :placeholder="t('Select access')"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ isProAvailable ? t('Pro access is available on this install.') : t('Pro access is hidden for this license.') }}
                        </p>
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Podcast Base URL') }}</span>
                        <input
                            v-model="form.podcast_base_url"
                            type="text"
                            autocomplete="off"
                            :placeholder="t('Leave empty to use site URL')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('AI Provider Settings') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Choose a default TTS provider and fine-tune the behavior for generated voices.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default TTS Provider') }}</span>
                        <AppSelect
                            v-model="form.default_provider"
                            :options="providerOptions"
                            :placeholder="t('Select a provider')"
                        />
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Voice ID') }}</span>
                        <input
                            v-model="form.default_voice_id"
                            type="text"
                            autocomplete="off"
                            :placeholder="t('alloy')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Speed (OpenAI)') }}</span>
                        <AppSelect
                            v-model="form.speed_default"
                            :options="speedOptions"
                            :placeholder="t('1.0')"
                        />
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Stability (ElevenLabs)') }}</span>
                        <input
                            v-model="form.stability_default"
                            type="number"
                            step="0.05"
                            min="0"
                            max="1"
                            autocomplete="off"
                            :placeholder="t('0.5')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </div>

                    <div class="block md:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Similarity Boost (ElevenLabs)') }}</span>
                        <input
                            v-model="form.similarity_boost_default"
                            type="number"
                            step="0.05"
                            min="0"
                            max="1"
                            autocomplete="off"
                            :placeholder="t('0.75')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Credits & Limits') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Set credit usage and limits for generated audio and transcription jobs.') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per 1,000 chars TTS') }}</span>
                        <input v-model.number="form.credits_per_1k_chars" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per STT transcription') }}</span>
                        <input v-model.number="form.credits_stt" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max script characters') }}</span>
                        <input v-model.number="form.max_script_chars" type="number" min="100" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max audio file size for STT (MB)') }}</span>
                        <input v-model.number="form.max_file_size_mb" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max background music size (MB)') }}</span>
                        <input v-model.number="form.background_music_max_size_mb" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Storage & Cleanup') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Configure local tools, podcast URL fallback, and auto cleanup behavior.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('ffmpeg Binary Path') }}</span>
                        <input
                            v-model="form.ffmpeg_path"
                            type="text"
                            autocomplete="off"
                            :placeholder="t('/usr/bin/ffmpeg')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                        <span class="mt-1 block text-xs" :class="props.systemStatus.ffmpeg ? 'text-emerald-600' : 'text-red-500'">
                            {{ props.systemStatus.ffmpeg ? t('ffmpeg binary found and executable') : t('ffmpeg not found or not executable') }}
                        </span>
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-delete audio after N days (0 = never)') }}</span>
                        <input
                            v-model.number="form.auto_delete_days"
                            type="number"
                            min="0"
                            autocomplete="off"
                            :placeholder="t('0')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Provider API Keys') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Configure the fallback key for the currently selected TTS provider.') }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ selectedProviderMeta.label }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('This key is used when the main AI provider credential is unavailable.') }}
                            </p>
                        </div>

                        <span class="rounded-full bg-primary-100 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                            {{ t('Selected provider') }}
                        </span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t(':provider API Key', { provider: selectedProviderMeta.label }) }}
                            </span>
                            <input
                                v-model="form[selectedProviderMeta.apiKeyField]"
                                type="password"
                                autocomplete="off"
                                :placeholder="selectedProviderMeta.apiKeyPlaceholder"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                        </div>

                        <div
                            v-if="selectedProviderMeta.extraField"
                            class="block"
                        >
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ selectedProviderMeta.extraField.label }}
                            </span>
                            <input
                                v-model="form[selectedProviderMeta.extraField.field]"
                                type="text"
                                autocomplete="off"
                                :placeholder="selectedProviderMeta.extraField.placeholder"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Voice Library') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('View the sync status for the selected provider voice library.') }}
                    </p>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ selectedProviderMeta.label }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ selectedProviderStatus?.voice_count ?? 0 }} {{ t('voices') }}
                                <template v-if="selectedProviderStatus?.last_synced_at">
                                    Â· {{ t('Last synced: :time', { time: selectedProviderStatus.last_synced_at }) }}
                                </template>
                                <template v-else>
                                    Â· {{ t('Never synced') }}
                                </template>
                            </p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Syncing refreshes the selected provider library in the background.') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                            @click="syncVoices"
                        >
                            <i class="ti ti-refresh text-base"></i>
                            {{ t('Sync :provider Voices', { provider: selectedProviderMeta.label }) }}
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </div>
</template>
