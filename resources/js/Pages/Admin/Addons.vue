<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface AddonConfig {
    name: string; slug: string; version: string; description?: string
    requires_license: number; is_active: boolean; license_ok: boolean
    settings?: Array<{ key: string; label: string; type: string; default: any }>
}

const props = defineProps<{ addons: AddonConfig[] }>()
const activate = (slug: string) => router.post(route('admin.addons.activate', { slug }))
const deactivate = (slug: string) => router.post(route('admin.addons.deactivate', { slug }))
</script>

<template>
    <Head title="Addons — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Addon Manager</h1>
        <p class="text-sm text-gray-500 mb-8">Install, activate, and configure platform addons.</p>

        <div class="space-y-4">
            <div v-for="addon in addons" :key="addon.slug" :class="[addon.is_active ? 'border-primary-500/30 bg-primary-50/30' : 'border-gray-200 bg-white']" class="border rounded-xl p-5 flex items-center gap-5 shadow-sm">
                <div :class="[addon.is_active ? 'bg-primary-100 text-primary-600' : 'bg-gray-100 text-gray-500']" class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.959.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z" /></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <h3 class="text-gray-900 font-semibold">{{ addon.name }}</h3>
                        <span class="text-xs text-gray-600">v{{ addon.version }}</span>
                        <span v-if="addon.is_active" class="px-2 py-0.5 bg-success-500/15 text-success-500 text-[10px] font-bold rounded-full">ACTIVE</span>
                        <span v-if="!addon.license_ok" class="px-2 py-0.5 bg-danger-500/15 text-danger-500 text-[10px] font-bold rounded-full">🔒 EXTENDED</span>
                    </div>
                    <p class="text-sm text-gray-500 truncate">{{ addon.description || 'No description' }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <Link v-if="addon.is_active && addon.settings?.length" :href="route('admin.addons.settings', { slug: addon.slug })" class="px-3 py-2 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 text-sm">Settings</Link>
                    <button v-if="addon.is_active" @click="deactivate(addon.slug)" class="px-4 py-2 bg-danger-500/10 text-danger-500 rounded-lg hover:bg-danger-500/20 text-sm font-medium">Deactivate</button>
                    <button v-else-if="addon.license_ok" @click="activate(addon.slug)" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 text-sm font-medium">Activate</button>
                    <span v-else class="px-4 py-2 bg-white/5 text-gray-600 rounded-lg text-sm">Locked</span>
                </div>
            </div>
        </div>

        <div v-if="!addons.length" class="text-center py-16 bg-white border border-gray-200 rounded-2xl shadow-sm">
            <p class="text-gray-500 text-sm mb-2">No addons installed yet</p>
            <p class="text-gray-600 text-xs">Upload addons to <code class="text-gray-400">addons/</code> directory</p>
        </div>
    </div>
</template>
