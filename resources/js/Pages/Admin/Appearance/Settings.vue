<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    admin_settings: Record<string, string>
    theme_settings: Record<string, string>
}>()

const GOOGLE_FONTS = [
    'Inter', 'Poppins', 'DM Sans', 'Nunito', 'Plus Jakarta Sans',
    'Noto Sans Bengali', 'Hind Siliguri', 'Roboto', 'Open Sans',
    'Lato', 'Montserrat', 'Raleway', 'Ubuntu', 'Merriweather',
    'Playfair Display', 'Source Sans 3', 'Fira Sans', 'IBM Plex Sans',
]

const get = (obj: Record<string, string>, key: string, fallback: string) => obj[key] || fallback

const adminForm = useForm({
    scope: 'admin',
    settings: {
        primary_color: get(props.admin_settings, 'primary_color', '#6366f1'),
        sidebar_bg: get(props.admin_settings, 'sidebar_bg', '#ffffff'),
        sidebar_text_color: get(props.admin_settings, 'sidebar_text_color', '#ffffff'),
        navbar_bg: get(props.admin_settings, 'navbar_bg', '#ffffff'),
        navbar_text_color: get(props.admin_settings, 'navbar_text_color', '#111827'),
        accent_color: get(props.admin_settings, 'accent_color', '#a855f7'),
        font_family: get(props.admin_settings, 'font_family', 'Inter'),
        base_font_size: get(props.admin_settings, 'base_font_size', '14px'),
        heading_weight: get(props.admin_settings, 'heading_weight', '600'),
    },
})

const themeForm = useForm({
    scope: 'theme_default',
    settings: {
        primary_color: get(props.theme_settings, 'primary_color', '#6366f1'),
        secondary_color: get(props.theme_settings, 'secondary_color', '#6366f1'),
        accent_color: get(props.theme_settings, 'accent_color', '#a855f7'),
        bg_color: get(props.theme_settings, 'bg_color', '#f9fafb'),
        surface_color: get(props.theme_settings, 'surface_color', '#ffffff'),
        text_primary_color: get(props.theme_settings, 'text_primary_color', '#111827'),
        text_secondary_color: get(props.theme_settings, 'text_secondary_color', '#6b7280'),
        link_color: get(props.theme_settings, 'link_color', '#6366f1'),
        button_color: get(props.theme_settings, 'button_color', '#6366f1'),
        button_hover_color: get(props.theme_settings, 'button_hover_color', '#4338ca'),
        header_background: get(props.theme_settings, 'header_background', '#ffffff'),
        footer_background: get(props.theme_settings, 'footer_background', '#f9fafb'),
        font_body: get(props.theme_settings, 'font_body', 'Inter'),
        heading_font: get(props.theme_settings, 'heading_font', 'Inter'),
        base_font_size: get(props.theme_settings, 'base_font_size', '16px'),
        heading_weight: get(props.theme_settings, 'heading_weight', '700'),
        line_height: get(props.theme_settings, 'line_height', '1.5'),
        letter_spacing: get(props.theme_settings, 'letter_spacing', 'normal'),
        border_radius: get(props.theme_settings, 'border_radius', '12px'),
        container_width: get(props.theme_settings, 'container_width', '1280px'),
        bg_gradient: get(props.theme_settings, 'bg_gradient', ''),
        bg_gradient_direction: get(props.theme_settings, 'bg_gradient_direction', 'to bottom'),
        bg_image: get(props.theme_settings, 'bg_image', ''),
        bg_size: get(props.theme_settings, 'bg_size', 'cover'),
        bg_repeat: get(props.theme_settings, 'bg_repeat', 'no-repeat'),
        bg_attachment: get(props.theme_settings, 'bg_attachment', 'scroll'),
        bg_position: get(props.theme_settings, 'bg_position', 'center'),
    },
})

const updateAdmin = () => adminForm.post(route('admin.appearance.update'))
const updateTheme = () => themeForm.post(route('admin.appearance.update'))
const { t } = useTranslate()

const themeColorOpen = ref(true)
const themeTypoOpen = ref(true)
const themeLayoutOpen = ref(true)
const themeBgOpen = ref(false)

const a = adminForm.settings
const ts = themeForm.settings
</script>

<template>
    <Head :title="t('Theme Settings — Admin')" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ t('Appearance Settings') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ t('Customize the visual identity of your admin panel and frontend.') }}</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-10">
            <!-- ═══ Admin Panel Settings ═══ -->
            <div class="space-y-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-gray-900 text-white rounded-xl flex items-center justify-center shadow-lg shadow-gray-900/10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <h3 class="font-black text-gray-900 uppercase tracking-widest">{{ t('Admin Panel Appearance') }}</h3>
                </div>

                <form @submit.prevent="updateAdmin" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <!-- Colors -->
                    <fieldset class="space-y-4">
                        <legend class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">{{ t('Colors') }}</legend>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Primary') }}</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="a.primary_color" type="color" class="w-9 h-9 rounded-lg cursor-pointer border-none p-0 shrink-0" />
                                    <input v-model="a.primary_color" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Accent') }}</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="a.accent_color" type="color" class="w-9 h-9 rounded-lg cursor-pointer border-none p-0 shrink-0" />
                                    <input v-model="a.accent_color" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Sidebar BG') }}</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="a.sidebar_bg" type="color" class="w-9 h-9 rounded-lg cursor-pointer border-none p-0 shrink-0" />
                                    <input v-model="a.sidebar_bg" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Sidebar Text') }}</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="a.sidebar_text_color" type="color" class="w-9 h-9 rounded-lg cursor-pointer border-none p-0 shrink-0" />
                                    <input v-model="a.sidebar_text_color" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Navbar BG') }}</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="a.navbar_bg" type="color" class="w-9 h-9 rounded-lg cursor-pointer border-none p-0 shrink-0" />
                                    <input v-model="a.navbar_bg" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Navbar Text') }}</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="a.navbar_text_color" type="color" class="w-9 h-9 rounded-lg cursor-pointer border-none p-0 shrink-0" />
                                    <input v-model="a.navbar_text_color" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none" />
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Typography -->
                    <fieldset class="space-y-4">
                        <legend class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">{{ t('Typography') }}</legend>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Font Family') }}</label>
                            <select v-model="a.font_family" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                <option v-for="f in GOOGLE_FONTS" :key="f" :value="f">{{ f }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Base Font Size') }}</label>
                                <select v-model="a.base_font_size" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="12px">12px</option>
                                    <option value="13px">13px</option>
                                    <option value="14px">14px</option>
                                    <option value="15px">15px</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Heading Weight') }}</label>
                                <select v-model="a.heading_weight" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="400">400</option>
                                    <option value="500">500</option>
                                    <option value="600">600</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <button type="submit" :disabled="adminForm.processing" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold hover:bg-gray-800 transition-all shadow-xl shadow-gray-900/10">
                        {{ adminForm.processing ? t('Saving...') : t('SAVE ADMIN UI') }}
                    </button>
                </form>
            </div>

            <!-- ═══ Frontend Theme Settings ═══ -->
            <div class="space-y-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 btn-primary rounded-xl flex items-center justify-center shadow-lg shadow-primary-600/10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h14a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <h3 class="font-black text-gray-900 uppercase tracking-widest">{{ t('Frontend Theme Settings') }}</h3>
                </div>

                <form @submit.prevent="updateTheme" class="bg-white rounded-3xl border border-gray-100 shadow-sm divide-y divide-gray-100">
                    <!-- Colors -->
                    <div class="p-8">
                        <button type="button" @click="themeColorOpen = !themeColorOpen" class="flex items-center justify-between w-full text-left mb-5">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ t('Colors') }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': themeColorOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div v-show="themeColorOpen" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Primary') }}</label>
                                <input v-model="ts.primary_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.primary_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Secondary') }}</label>
                                <input v-model="ts.secondary_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.secondary_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Accent') }}</label>
                                <input v-model="ts.accent_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.accent_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Page BG') }}</label>
                                <input v-model="ts.bg_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.bg_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Surface') }}</label>
                                <input v-model="ts.surface_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.surface_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Text Primary') }}</label>
                                <input v-model="ts.text_primary_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.text_primary_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Text Muted') }}</label>
                                <input v-model="ts.text_secondary_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.text_secondary_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Link') }}</label>
                                <input v-model="ts.link_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.link_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Button') }}</label>
                                <input v-model="ts.button_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.button_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Btn Hover') }}</label>
                                <input v-model="ts.button_hover_color" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.button_hover_color" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Header BG') }}</label>
                                <input v-model="ts.header_background" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.header_background" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Footer BG') }}</label>
                                <input v-model="ts.footer_background" type="color" class="w-full h-10 rounded-lg cursor-pointer border-none p-0 mb-1" />
                                <input v-model="ts.footer_background" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:outline-none text-center" />
                            </div>
                        </div>
                    </div>

                    <!-- Typography -->
                    <div class="p-8">
                        <button type="button" @click="themeTypoOpen = !themeTypoOpen" class="flex items-center justify-between w-full text-left mb-5">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ t('Typography') }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': themeTypoOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div v-show="themeTypoOpen" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Body Font') }}</label>
                                <select v-model="ts.font_body" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option v-for="f in GOOGLE_FONTS" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Heading Font') }}</label>
                                <select v-model="ts.heading_font" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option v-for="f in GOOGLE_FONTS" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Base Font Size') }}</label>
                                <select v-model="ts.base_font_size" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="14px">14px</option>
                                    <option value="15px">15px</option>
                                    <option value="16px">16px</option>
                                    <option value="18px">18px</option>
                                    <option value="20px">20px</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Heading Weight') }}</label>
                                <select v-model="ts.heading_weight" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="400">400</option>
                                    <option value="500">500</option>
                                    <option value="600">600</option>
                                    <option value="700">700</option>
                                    <option value="800">800</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Line Height') }}</label>
                                <select v-model="ts.line_height" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="1.25">Tight (1.25)</option>
                                    <option value="1.375">Snug (1.375)</option>
                                    <option value="1.5">Normal (1.5)</option>
                                    <option value="1.625">Relaxed (1.625)</option>
                                    <option value="1.75">Loose (1.75)</option>
                                    <option value="2">Very Loose (2)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Letter Spacing') }}</label>
                                <select v-model="ts.letter_spacing" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="tighter">{{ t('Tighter') }}</option>
                                    <option value="tight">{{ t('Tight') }}</option>
                                    <option value="normal">{{ t('Normal') }}</option>
                                    <option value="wide">{{ t('Wide') }}</option>
                                    <option value="wider">{{ t('Wider') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Layout -->
                    <div class="p-8">
                        <button type="button" @click="themeLayoutOpen = !themeLayoutOpen" class="flex items-center justify-between w-full text-left mb-5">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ t('Layout') }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': themeLayoutOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div v-show="themeLayoutOpen" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Border Radius') }}</label>
                                <select v-model="ts.border_radius" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="0px">{{ t('Sharp (0px)') }}</option>
                                    <option value="8px">{{ t('Soft (8px)') }}</option>
                                    <option value="12px">{{ t('Rounded (12px)') }}</option>
                                    <option value="16px">{{ t('Subtle (16px)') }}</option>
                                    <option value="20px">{{ t('Modern (20px)') }}</option>
                                    <option value="999px">{{ t('Pill (999px)') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Container Width') }}</label>
                                <select v-model="ts.container_width" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                    <option value="full">{{ t('Full (100%)') }}</option>
                                    <option value="1080px">{{ t('Boxed (1080px)') }}</option>
                                    <option value="1280px">{{ t('XL (1280px)') }}</option>
                                    <option value="1536px">{{ t('2XL (1536px)') }}</option>
                                    <option value="custom">{{ t('Custom') }}</option>
                                </select>
                                <input v-if="ts.container_width === 'custom'" v-model="ts.container_width" placeholder="e.g. 1440px" class="mt-2 w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none" />
                            </div>
                        </div>
                    </div>

                    <!-- Background -->
                    <div class="p-8">
                        <button type="button" @click="themeBgOpen = !themeBgOpen" class="flex items-center justify-between w-full text-left mb-5">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ t('Page Background') }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': themeBgOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div v-show="themeBgOpen" class="space-y-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Gradient 2nd Color') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="ts.bg_gradient" type="color" class="w-9 h-9 rounded-lg cursor-pointer border-none p-0 shrink-0" />
                                        <input v-model="ts.bg_gradient" type="text" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none" placeholder="#e5e7eb" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Direction') }}</label>
                                    <select v-model="ts.bg_gradient_direction" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                        <option value="to bottom">{{ t('↓ Top to Bottom') }}</option>
                                        <option value="to top">{{ t('↑ Bottom to Top') }}</option>
                                        <option value="to right">{{ t('→ Left to Right') }}</option>
                                        <option value="to left">{{ t('← Right to Left') }}</option>
                                        <option value="to bottom right">{{ t('↘ Diagonal') }}</option>
                                        <option value="to bottom left">{{ t('↙ Diagonal') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Background Image URL') }}</label>
                                <input v-model="ts.bg_image" type="url" placeholder="https://..." class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none" />
                            </div>

                            <div v-if="ts.bg_image" class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Size') }}</label>
                                    <select v-model="ts.bg_size" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                        <option value="cover">{{ t('Cover') }}</option>
                                        <option value="contain">{{ t('Contain') }}</option>
                                        <option value="auto">{{ t('Auto') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Repeat') }}</label>
                                    <select v-model="ts.bg_repeat" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                        <option value="no-repeat">{{ t('No Repeat') }}</option>
                                        <option value="repeat">{{ t('Repeat') }}</option>
                                        <option value="repeat-x">{{ t('Repeat X') }}</option>
                                        <option value="repeat-y">{{ t('Repeat Y') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Attachment') }}</label>
                                    <select v-model="ts.bg_attachment" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                        <option value="scroll">{{ t('Scroll') }}</option>
                                        <option value="fixed">{{ t('Fixed') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1.5">{{ t('Position') }}</label>
                                    <select v-model="ts.bg_position" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none">
                                        <option value="center">{{ t('Center') }}</option>
                                        <option value="top">{{ t('Top') }}</option>
                                        <option value="bottom">{{ t('Bottom') }}</option>
                                        <option value="left">{{ t('Left') }}</option>
                                        <option value="right">{{ t('Right') }}</option>
                                        <option value="top left">{{ t('Top Left') }}</option>
                                        <option value="top right">{{ t('Top Right') }}</option>
                                        <option value="bottom left">{{ t('Bottom Left') }}</option>
                                        <option value="bottom right">{{ t('Bottom Right') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="p-8">
                        <button type="submit" :disabled="themeForm.processing" class="w-full py-4 btn-primary rounded-2xl font-bold transition-all shadow-xl shadow-primary-600/10">
                            {{ themeForm.processing ? t('Saving...') : t('SAVE THEME UI') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
