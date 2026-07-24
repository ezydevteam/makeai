<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'

type Selectable = { key: string; label: string; description: string; type: string; preview: Record<string, string>; image?: string | null }
type DemoBar = { selectable: Selectable[]; active: string }

const page = usePage()
const { t } = useTranslate()

const demoBar = computed(() => (page.props as any).demoBar as DemoBar | null)
const selectable = computed(() => demoBar.value?.selectable ?? [])
const activeKey = computed(() => demoBar.value?.active ?? '')
const activeLabel = computed(() => selectable.value.find((o) => o.key === activeKey.value)?.label ?? t('Default'))

const branding = computed(() => (page.props as any).branding ?? {})
const siteName = computed(() => String(branding.value?.site_name || (page.props as any).appName || 'MakeAI'))
const logo = computed(() => (branding.value?.site_logo_light ? mediaUrl(branding.value.site_logo_light) : ''))

// Reference-style labels: a pill over the media, and a dotted sub-label under the title.
const badgeLabel = (o: Selectable) => (o.type === 'addon' ? t('Premium Addon') : t('Theme Preset'))
const subLabel = (o: Selectable) => (o.type === 'addon' ? t('Premium Extension') : t('Theme Preset'))

const modalOpen = ref(false)

// Full page load so the (cached) theme CSS re-fetches with the new preset applied.
function selectDemo(key: string) {
    window.location.assign(`/__demo/select?demo=${encodeURIComponent(key)}`)
}
</script>

<template>
    <div v-if="demoBar">
        <!-- Floating brush launcher, pinned to the right edge -->
        <button
            type="button"
            class="fixed right-0 top-1/2 z-[110] flex -translate-y-1/2 items-center gap-2 rounded-l-xl bg-gray-900 py-3 pl-3 pr-3.5 text-white shadow-xl ring-1 ring-white/10 transition-all hover:pr-5 dark:bg-gray-800"
            :title="t('Demo styles')"
            @click="modalOpen = true"
        >
            <i class="ti ti-brush text-xl" />
            <span class="hidden text-xs font-semibold sm:inline">{{ t('Styles') }}</span>
        </button>

        <!-- Selector modal -->
        <Teleport to="body">
            <div v-if="modalOpen" class="fixed inset-0 z-[130] flex items-center justify-center p-4" @click.self="modalOpen = false">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="modalOpen = false" />
                <div class="frontend-theme-vars relative z-10 max-h-[88vh] w-full max-w-4xl overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 shadow-2xl dark:border-white/10 dark:bg-gray-900">
                    <!-- Inset the scroll area so the scrollbar clears the rounded corners -->
                    <div class="my-3 max-h-[calc(88vh-1.5rem)] overflow-y-auto">
                    <!-- Top bar: brand + close -->
                    <div class="flex items-center justify-between px-6 pt-5 sm:px-8">
                        <div class="flex items-center gap-2">
                            <img v-if="logo" :src="logo" :alt="siteName" class="h-7 w-auto" />
                            <template v-else>
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-500 text-white">
                                    <i class="ti ti-sparkles text-lg" />
                                </span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">{{ siteName }}</span>
                            </template>
                        </div>
                        <button type="button" class="rounded-full w-10 h-10 flex items-center justify-center text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10" @click="modalOpen = false">
                            <i class="ti ti-x text-xl" />
                        </button>
                    </div>

                    <!-- Heading -->
                    <div class="px-6 pb-6 pt-3 sm:px-8">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">{{ t('Customize Appearance') }}</h2>
                        <p class="mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Customize the visual appearance of :site with a single click and complement the design principles of your brand identity.', { site: siteName }) }}
                        </p>
                    </div>

                    <!-- Cards -->
                    <div class="grid grid-cols-1 gap-6 px-6 pb-8 sm:grid-cols-2 sm:px-8">
                        <!-- Reset to the site's real configuration -->
                        <button type="button" class="group text-center" @click="selectDemo('default')">
                            <div
                                class="relative rounded-2xl transition"
                                :class="activeKey === '' ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-gray-50 dark:ring-offset-gray-900' : ''"
                            >
                                <div class="flex aspect-[16/10] items-center justify-center overflow-hidden rounded-2xl border-[6px] border-white bg-white text-gray-300 shadow-lg transition group-hover:shadow-xl dark:border-gray-800 dark:bg-gray-800 dark:text-gray-600">
                                    <i class="ti ti-home-cog text-5xl" />
                                </div>
                                <span class="absolute bottom-3 right-3 rounded-lg border border-gray-100 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-md dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">
                                    {{ t('Default') }}
                                </span>
                            </div>
                            <div class="mt-4">
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Site Default') }}</div>
                                <div class="mt-1 flex items-center justify-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-gray-400" />
                                    {{ t('Current configuration') }}
                                </div>
                            </div>
                        </button>

                        <button
                            v-for="option in selectable"
                            :key="option.key"
                            type="button"
                            class="group text-center"
                            @click="selectDemo(option.key)"
                        >
                            <div
                                class="relative rounded-2xl transition"
                                :class="activeKey === option.key ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-gray-50 dark:ring-offset-gray-900' : ''"
                            >
                                <!-- White-framed floating screenshot; tinted icon placeholder when none dropped in -->
                                <div class="aspect-[16/10] overflow-hidden rounded-2xl border-[6px] border-white bg-white shadow-lg transition group-hover:shadow-xl dark:border-gray-800 dark:bg-gray-800">
                                    <img
                                        v-if="option.image"
                                        :src="option.image"
                                        :alt="option.label"
                                        loading="lazy"
                                        class="h-full w-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-5xl"
                                        :style="option.preview?.primary ? { backgroundColor: option.preview.primary + '1f', color: option.preview.primary } : {}"
                                        :class="!option.preview?.primary ? 'text-gray-300 dark:text-gray-600' : ''"
                                    >
                                        <i :class="option.type === 'addon' ? 'ti ti-puzzle' : 'ti ti-palette'" />
                                    </span>
                                </div>

                                <!-- White pill badge overlapping the screenshot -->
                                <span class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 rounded-lg border border-gray-100 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-md dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">
                                    <i v-if="option.type === 'addon'" class="ti ti-crown text-amber-500" />
                                    {{ badgeLabel(option) }}
                                </span>

                                <!-- Active check -->
                                <span v-if="activeKey === option.key" class="absolute left-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary-500 text-white shadow">
                                    <i class="ti ti-check text-sm" />
                                </span>
                            </div>

                            <div class="mt-4">
                                <div class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ option.label }}</div>
                                <div class="mt-1 flex items-center justify-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="option.type === 'addon' ? 'bg-primary-500' : 'bg-gray-400'" />
                                    {{ subLabel(option) }}
                                </div>
                            </div>
                        </button>
                    </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
