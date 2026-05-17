<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Setting { key: string; label: string; type: string; default: any; value: any; options?: string[] }
interface AddonConfig { name: string; slug: string; version: string }

const props = defineProps<{ addon: AddonConfig; settings: Setting[] }>()
const form = useForm(Object.fromEntries(props.settings.map(s => [s.key, s.value ?? s.default])))
const save = () => form.post(route('admin.addons.settings.save', { slug: props.addon.slug }), { preserveScroll: true })
</script>

<template>
    <Head :title="`${addon.name} Settings — Admin`" />
    <div class="max-w-3xl mx-auto px-6 py-8">
        <div class="flex items-center gap-3 mb-8">
            <Link :href="route('admin.addons')" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-500 hover:text-gray-900">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ addon.name }} Settings</h1>
                <p class="text-sm text-gray-500">v{{ addon.version }}</p>
            </div>
        </div>

        <form @submit.prevent="save" class="space-y-5">
            <div v-for="setting in settings" :key="setting.key" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ setting.label }}</label>
                <div v-if="setting.type === 'boolean'" class="flex items-center gap-3">
                    <button type="button" @click="form[setting.key] = !form[setting.key]" class="relative w-12 h-6 rounded-full transition-colors" :class="form[setting.key] ? 'bg-primary-600' : 'bg-white/10'">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform" :class="form[setting.key] ? 'translate-x-6' : 'translate-x-0'"></span>
                    </button>
                    <span class="text-sm text-gray-400">{{ form[setting.key] ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <select v-else-if="setting.type === 'select'" v-model="form[setting.key]" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-primary-500/50 focus:outline-none">
                    <option v-for="opt in setting.options" :key="opt" :value="opt" class="bg-surface-900">{{ opt }}</option>
                </select>
                <input v-else-if="setting.type === 'integer'" type="number" v-model="form[setting.key]" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-primary-500/50 focus:outline-none" />
                <input v-else type="text" v-model="form[setting.key]" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-primary-500/50 focus:outline-none" />
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <Link :href="route('admin.addons')" class="px-5 py-2.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-500 text-sm font-medium disabled:opacity-50">
                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                </button>
            </div>
        </form>
    </div>
</template>
