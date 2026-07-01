<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface ThemeConfig {
    name: string; slug: string; version: string; author: string
    description: string; requires_license: number; thumbnail?: string
    is_active: boolean; license_ok: boolean
    settings?: Array<{ key: string; label: string; type: string; default: any }>
}

const props = defineProps<{ themes: ThemeConfig[]; activeTheme: string }>()
const { t } = useTranslate()

const activating = ref<string | null>(null)

const canOpenSettings = (theme: ThemeConfig): boolean => theme.slug === 'default' || Boolean(theme.settings?.length)

const activate = (slug: string) => {
    activating.value = slug
    router.post(route('admin.themes.activate', { slug }), {}, {
        onFinish: () => {
            activating.value = null
        }
    })
}
</script>

<template>
    <Head :title="$t('Themes — Admin')" />

    <div class="max-w-6xl mx-auto px-6 py-6">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Theme Manager</h1>
                <p class="text-sm text-gray-500 mt-1">Activate and configure themes for your platform.</p>
            </div>
        </div>

        <!-- Theme Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div v-for="theme in themes" :key="theme.slug" :class="[theme.is_active ? 'border-primary-500 ring-1 ring-primary-500/20' : 'border-gray-200']" class="relative bg-white border rounded-2xl overflow-hidden shadow-sm">
                <!-- Thumbnail -->
                <div class="h-40 bg-gradient-to-br from-primary-500/10 to-accent-500/10 flex items-center justify-center">
                    <div v-if="theme.is_active" class="absolute top-3 right-3 px-2.5 py-0.5 bg-success-500/15 text-success-500 text-xs font-bold rounded-full">ACTIVE</div>
                    <div v-if="!theme.license_ok" class="absolute top-3 left-3 px-2.5 py-0.5 bg-danger-500/15 text-danger-500 text-xs font-bold rounded-full flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                        EXTENDED
                    </div>
                    <svg class="w-12 h-12 text-primary-400/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" /></svg>
                </div>

                <!-- Info -->
                <div class="p-5">
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="text-gray-900 font-semibold">{{ theme.name }}</h3>
                        <span class="text-xs text-gray-600">v{{ theme.version }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-1">by {{ theme.author }}</p>
                    <p class="text-sm text-gray-400 mb-4">{{ theme.description }}</p>

                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!theme.is_active && theme.license_ok" 
                            @click="activate(theme.slug)" 
                            :disabled="activating === theme.slug"
                            :aria-label="t('Activate :name theme', { name: theme.name })"
                            class="px-4 py-2 btn-primary text-sm font-medium rounded-lg transition-colors flex-1 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="activating === theme.slug" class="inline-flex items-center gap-2">
                                <i class="ti ti-loader-2 animate-spin"></i>
                                {{ t('Activating...') }}
                            </span>
                            <span v-else>{{ t('Activate') }}</span>
                        </button>
                        <span v-else-if="theme.is_active" class="px-4 py-2 bg-gray-50 text-gray-500 text-sm font-medium rounded-lg flex-1 text-center">{{ t('Currently Active') }}</span>
                        <span v-else class="px-4 py-2 bg-gray-50 text-gray-400 text-sm rounded-lg flex-1 text-center">{{ t('Requires Extended License') }}</span>

                        <Link 
                            v-if="canOpenSettings(theme)" 
                            :href="route('admin.themes.settings', { slug: theme.slug })" 
                            :aria-label="t('Settings for :name theme', { name: theme.name })"
                            class="px-3 py-2 bg-gray-50 text-gray-500 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors" 
                            :title="t('Settings')"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!themes.length" class="text-center py-16">
            <svg class="w-12 h-12 text-gray-700 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" /></svg>
            <p class="text-gray-500 text-sm">No themes found in <code class="text-gray-400">resources/themes/</code></p>
        </div>
    </div>
</template>
