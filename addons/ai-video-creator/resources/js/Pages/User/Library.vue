<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed } from 'vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

const props = defineProps<{
    folders: { id: number; name: string; color: string; projects_count: number }[]
    projects: { data: any[] }
    recent_renders: any[]
}>()

const activeFolder = ref<number | null>(new URLSearchParams(window.location.search).get('folder_id') ? Number(new URLSearchParams(window.location.search).get('folder_id')) : null)
const expandedProject = ref<string | null>(null)

const typeIcon = (type: string) =>
    type === 'text_to_video' ? 'ti ti-video' : type === 'image_to_video' ? 'ti ti-photo' :
    type === 'avatar_video' ? 'ti ti-user' : 'ti ti-slideshow'

const statusClass = (s: string) =>
    s === 'completed' ? 'bg-emerald-100 text-emerald-800' : s === 'processing' ? 'bg-blue-100 text-blue-800 text-xs animate-pulse' :
    s === 'queued' ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-800'

function toggleProject(ulid: string) {
    expandedProject.value = expandedProject.value === ulid ? null : ulid
}
</script>

<template>
    <Head :title="t('Video Library')" />

    <div class="space-y-6 p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold">{{ t('Video Library') }}</h1>
            <Link :href="route('addon.video.user.create')" class="btn btn-sm btn-emerald w-full justify-center sm:w-auto">
                + {{ t('New Video') }}
            </Link>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row">
            <aside class="w-full shrink-0 space-y-1 lg:w-56">
                <button @click="activeFolder = null"
                        class="block w-full rounded px-3 py-2 text-left text-sm font-medium lg:py-1.5"
                        :class="!activeFolder ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-gray-50'">
                    📁 {{ t('All Videos') }}
                </button>
                <button v-for="folder in folders" :key="folder.id"
                        @click="activeFolder = folder.id"
                        class="flex w-full items-center gap-2 rounded px-3 py-2 text-sm lg:py-1.5"
                        :class="activeFolder === folder.id ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-gray-50'">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: folder.color }"></span>
                    <span class="flex-1 truncate">{{ folder.name }}</span>
                    <span class="text-xs text-gray-400">{{ folder.projects_count }}</span>
                </button>
            </aside>

            <div class="flex-1 space-y-4">
                <div v-if="projects.data.length === 0" class="card p-8 text-center text-gray-400">
                    {{ t('No projects yet. Create your first video to get started.') }}
                </div>

                <div v-for="project in projects.data" :key="project.ulid" class="card p-4 space-y-3">
                    <div class="flex cursor-pointer flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" @click="toggleProject(project.ulid)">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: project.color || '#6366f1' }"></span>
                            <span class="truncate font-medium">{{ project.name }}</span>
                            <span class="shrink-0 text-xs text-gray-400">{{ project.renders_count }} {{ t('renders') }}</span>
                        </div>
                        <i class="ti ti-chevron-down self-end text-sm transition-transform sm:self-auto"
                           :class="{ 'rotate-180': expandedProject === project.ulid }"></i>
                    </div>

                    <div v-if="expandedProject === project.ulid" class="flex gap-3 overflow-x-auto pb-2">
                        <div v-for="render in project.renders" :key="render.ulid"
                             class="shrink-0 w-40 border rounded-lg overflow-hidden">
                            <div class="aspect-video bg-gray-200 flex items-center justify-center text-2xl text-gray-400">
                                <img v-if="render.thumbnail_url" :src="render.thumbnail_url" class="w-full h-full object-cover" />
                                <i v-else :class="typeIcon(render.type)"></i>
                            </div>
                            <div class="p-2 space-y-1">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium" :class="statusClass(render.status)">
                                    {{ render.status_label }}
                                </span>
                                <Link :href="route('addon.video.user.renders.show', render.ulid)"
                                      class="block text-xs text-emerald-600 hover:underline mt-1">
                                    ▶ {{ t('View') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
