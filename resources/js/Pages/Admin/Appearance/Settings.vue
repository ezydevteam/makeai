<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    admin_settings: any,
    theme_settings: any
}>();

const adminForm = useForm({
    scope: 'admin',
    settings: {
        primary_color: props.admin_settings.primary_color || '#10b981',
        sidebar_bg: props.admin_settings.sidebar_bg || '#ffffff',
        font_family: props.admin_settings.font_family || 'Inter',
    }
});

const themeForm = useForm({
    scope: 'theme_default',
    settings: {
        primary_color: props.theme_settings.primary_color || '#10b981',
        secondary_color: props.theme_settings.secondary_color || '#000000',
        bg_color: props.theme_settings.bg_color || '#f9fafb',
        border_radius: props.theme_settings.border_radius || '12px',
        font_body: props.theme_settings.font_body || 'Inter',
    }
});

const updateAdmin = () => adminForm.post(route('admin.appearance.update'));
const updateTheme = () => themeForm.post(route('admin.appearance.update'));
const { t } = useTranslate()
</script>

<template>
    <Head :title="t('Theme Settings — Admin')" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ t('Appearance Settings') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ t('Customize the visual identity of your admin panel and frontend.') }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <!-- Admin Panel Settings -->
            <div class="space-y-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-gray-900 text-white rounded-xl flex items-center justify-center shadow-lg shadow-gray-900/10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <h3 class="font-black text-gray-900 uppercase tracking-widest">{{ t('Admin Panel Appearance') }}</h3>
                </div>
                <form @submit.prevent="updateAdmin" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-3 tracking-widest">{{ t('Brand Color') }}</label>
                        <div class="flex items-center gap-4">
                            <input v-model="adminForm.settings.primary_color" type="color" class="w-12 h-12 rounded-xl cursor-pointer border-none p-0" />
                            <input v-model="adminForm.settings.primary_color" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-mono focus:outline-none" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-3 tracking-widest">{{ t('Sidebar Background') }}</label>
                        <select v-model="adminForm.settings.sidebar_bg" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none">
                            <option value="#ffffff">{{ t('Clean White') }}</option>
                            <option value="#f9fafb">{{ t('Light Gray') }}</option>
                            <option value="#111827">{{ t('Modern Dark') }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="adminForm.processing" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold hover:bg-gray-800 transition-all shadow-xl shadow-gray-900/10">{{ t('SAVE ADMIN UI') }}</button>
                </form>
            </div>

            <!-- Frontend Theme Settings -->
            <div class="space-y-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-primary-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-primary-600/10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h14a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <h3 class="font-black text-gray-900 uppercase tracking-widest">{{ t('Frontend Theme Settings') }}</h3>
                </div>
                <form @submit.prevent="updateTheme" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-3 tracking-widest">{{ t('Primary Color') }}</label>
                            <input v-model="themeForm.settings.primary_color" type="color" class="w-full h-12 rounded-xl cursor-pointer border-none p-0 mb-2" />
                            <input v-model="themeForm.settings.primary_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 text-[10px] font-mono focus:outline-none text-center" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-3 tracking-widest">{{ t('Accent Color') }}</label>
                            <input v-model="themeForm.settings.secondary_color" type="color" class="w-full h-12 rounded-xl cursor-pointer border-none p-0 mb-2" />
                            <input v-model="themeForm.settings.secondary_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 text-[10px] font-mono focus:outline-none text-center" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-3 tracking-widest">{{ t('Border Radius') }}</label>
                        <select v-model="themeForm.settings.border_radius" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none">
                            <option value="0px">{{ t('Sharp (0px)') }}</option>
                            <option value="8px">{{ t('Soft (8px)') }}</option>
                            <option value="12px">{{ t('Rounded (12px)') }}</option>
                            <option value="20px">{{ t('Modern (20px)') }}</option>
                            <option value="999px">{{ t('Pill (999px)') }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="themeForm.processing" class="w-full py-4 bg-primary-600 text-white rounded-2xl font-bold hover:bg-primary-500 transition-all shadow-xl shadow-primary-600/10">{{ t('SAVE THEME UI') }}</button>
                </form>
            </div>
        </div>
    </div>
</template>
