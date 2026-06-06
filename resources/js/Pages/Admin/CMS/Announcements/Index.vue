<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'

declare const route: (name: string, params?: Record<string, string | number>) => string

interface Announcement {
    id: number
    type: 'topbar' | 'popup' | 'notification'
    title: string | null
    content: string | null
    bg_color: string | null
    text_color: string | null
    cta_text: string | null
    cta_url: string | null
    image: string | null
    target_audience: 'all' | 'guests' | 'auth' | 'free' | 'pro'
    trigger_type: string | null
    trigger_value: string | null
    show_frequency: 'always' | 'session' | 'once'
    is_active: boolean
    starts_at: string | null
    ends_at: string | null
}

const props = defineProps<{ announcements: Announcement[] }>()
const { t } = useTranslate()
const { formatDate } = useDateFormat()

const showForm = ref(false)
const editingId = ref<number | null>(null)
const deleteTargetId = ref<number | null>(null)

const blank = (): Omit<Announcement, 'id'> => ({
    type: 'topbar',
    title: '',
    content: '',
    bg_color: '#4f46e5',
    text_color: '#ffffff',
    cta_text: '',
    cta_url: '',
    image: '',
    target_audience: 'all',
    trigger_type: '',
    trigger_value: '',
    show_frequency: 'session',
    is_active: true,
    starts_at: '',
    ends_at: '',
})

const form = useForm(blank())

const openCreate = () => {
    form.reset()
    Object.assign(form, blank())
    editingId.value = null
    showForm.value = true
}

const openEdit = (a: Announcement) => {
    form.type = a.type
    form.title = a.title ?? ''
    form.content = a.content ?? ''
    form.bg_color = a.bg_color ?? '#4f46e5'
    form.text_color = a.text_color ?? '#ffffff'
    form.cta_text = a.cta_text ?? ''
    form.cta_url = a.cta_url ?? ''
    form.image = a.image ?? ''
    form.target_audience = a.target_audience
    form.trigger_type = a.trigger_type ?? ''
    form.trigger_value = a.trigger_value ?? ''
    form.show_frequency = a.show_frequency
    form.is_active = a.is_active
    // format dates for input[type="datetime-local"] if they exist
    form.starts_at = a.starts_at ? new Date(a.starts_at).toISOString().slice(0, 16) : ''
    form.ends_at = a.ends_at ? new Date(a.ends_at).toISOString().slice(0, 16) : ''

    editingId.value = a.id
    showForm.value = true
}

const submit = () => {
    if (editingId.value) {
        form.post(route('admin.announcements.update', { announcement: editingId.value }), {
            onSuccess: () => { showForm.value = false },
        })
    } else {
        form.post(route('admin.announcements.store'), {
            onSuccess: () => { showForm.value = false },
        })
    }
}

const remove = (id: number) => {
    deleteTargetId.value = id
}

const confirmDelete = () => {
    if (deleteTargetId.value === null) {
        return
    }

    router.delete(route('admin.announcements.delete', { announcement: deleteTargetId.value }), {
        preserveScroll: true,
        onFinish: () => {
            deleteTargetId.value = null
        },
    })
}

const toggleActive = (id: number) => {
    router.post(route('admin.announcements.active', { announcement: id }), {}, { preserveScroll: true })
}

const typeLabel: Record<string, string> = {
    topbar: t('Top Bar'),
    popup: t('Popup'),
    notification: t('Notification'),
}

const typeColor: Record<string, string> = {
    topbar: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    popup: 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
    notification: 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
}
</script>

<template>
    <Head :title="t('Announcements - Admin')" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto px-6 py-8">

            <!-- Header -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Announcements') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Manage sitewide banners, popups, and in-app notifications.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="openCreate" type="button" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20">
                        {{ t('+ Add Announcement') }}
                    </button>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-gray-900 dark:text-white">{{ announcements.length }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ t('Total') }}</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-success-600">{{ announcements.filter(a => a.is_active).length }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ t('Active') }}</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-blue-600">{{ announcements.filter(a => a.type === 'topbar').length }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ t('Top Bars') }}</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-purple-600">{{ announcements.filter(a => a.type === 'popup').length }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ t('Popups') }}</div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="announcements.length === 0" class="bg-white dark:bg-surface-900 rounded-2xl border-2 border-dashed border-gray-200 dark:border-surface-700 p-16 text-center">
                <div class="text-5xl mb-4">📢</div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">{{ t('No announcements yet') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ t('Create promotional banners or important notices for your users.') }}</p>
                <button @click="openCreate" type="button" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all">{{ t('Add first announcement') }}</button>
            </div>

            <!-- Announcements list -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div
                    v-for="a in announcements"
                    :key="a.id"
                    class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5 flex flex-col gap-4 hover:shadow-md transition-shadow"
                    :class="!a.is_active ? 'opacity-60' : ''"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <span :class="typeColor[a.type]" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-md shrink-0">{{ typeLabel[a.type] }}</span>
                        <button @click="toggleActive(a.id)" type="button" :class="a.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-5 w-9 rounded-full transition-colors ml-1">
                            <span :class="a.is_active ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="flex-1">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1 line-clamp-1">{{ a.title || t('Untitled') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2" v-html="a.content"></p>
                    </div>

                    <!-- Details -->
                    <div class="text-xs text-gray-400 flex flex-col gap-1 bg-gray-50 dark:bg-surface-800 p-3 rounded-xl">
                        <div class="flex justify-between"><span>{{ t('Audience:') }}</span> <span class="text-gray-700 dark:text-gray-300 capitalize">{{ t(a.target_audience) }}</span></div>
                        <div class="flex justify-between"><span>{{ t('Frequency:') }}</span> <span class="text-gray-700 dark:text-gray-300 capitalize">{{ t(a.show_frequency) }}</span></div>
                        <div v-if="a.starts_at" class="flex justify-between"><span>{{ t('Starts:') }}</span> <span class="text-gray-700 dark:text-gray-300">{{ formatDate(a.starts_at) }}</span></div>
                        <div v-if="a.ends_at" class="flex justify-between"><span>{{ t('Ends:') }}</span> <span class="text-gray-700 dark:text-gray-300">{{ formatDate(a.ends_at) }}</span></div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-surface-800">
                        <div class="flex-1"></div>
                        <button @click="openEdit(a)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-gray-500 hover:text-primary-600 hover:border-primary-300 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button @click="remove(a.id)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-danger-500 hover:bg-danger-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingId ? t('Edit Announcement') : t('Add Announcement') }}</h3>
                    <button @click="showForm = false" type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-sm">{{ t('Close') }}</button>
                </div>
                <div class="p-6 overflow-y-auto space-y-5">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Type') }} *</label>
                            <select v-model="form.type" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option value="topbar">{{ t('Top Bar (Banner)') }}</option>
                                <option value="popup">{{ t('Popup Modal') }}</option>
                                <option value="notification">{{ t('In-App Notification') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Target Audience') }}</label>
                            <select v-model="form.target_audience" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option value="all">{{ t('Everyone') }}</option>
                                <option value="guests">{{ t('Guests Only') }}</option>
                                <option value="auth">{{ t('Logged In Users') }}</option>
                                <option value="free">{{ t('Free Users') }}</option>
                                <option value="pro">{{ t('Pro Users') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Title') }}</label>
                        <input v-model="form.title" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Content (Supports HTML)') }}</label>
                        <textarea v-model="form.content" rows="3" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Background Color') }}</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.bg_color" type="color" class="w-8 h-8 rounded cursor-pointer border-0 p-0">
                                <input v-model="form.bg_color" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-2 py-1.5 text-xs text-gray-900 dark:text-white uppercase font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Text Color') }}</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.text_color" type="color" class="w-8 h-8 rounded cursor-pointer border-0 p-0">
                                <input v-model="form.text_color" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-2 py-1.5 text-xs text-gray-900 dark:text-white uppercase font-mono">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('CTA Text') }}</label>
                            <input v-model="form.cta_text" type="text" :placeholder="t('e.g. Learn More')" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('CTA URL') }}</label>
                            <input v-model="form.cta_url" type="text" :placeholder="t('https://')" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div v-if="form.type === 'popup'" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-purple-50 dark:bg-purple-900/10 p-4 rounded-xl border border-purple-100 dark:border-purple-900/30">
                        <div class="md:col-span-2">
                            <h4 class="text-sm font-bold text-purple-800 dark:text-purple-300 mb-3">{{ t('Popup Specific Settings') }}</h4>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Trigger Type') }}</label>
                            <select v-model="form.trigger_type" class="w-full bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option value="">{{ t('On Load') }}</option>
                                <option value="delay">{{ t('Delay') }}</option>
                                <option value="scroll">{{ t('Scroll %') }}</option>
                                <option value="exit">{{ t('Exit Intent') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Trigger Value') }}</label>
                            <input v-model="form.trigger_value" type="text" :placeholder="t('e.g. 5 (seconds) or 50 (%)')" class="w-full bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Image URL (Optional banner)') }}</label>
                            <input v-model="form.image" type="text" :placeholder="t('https://')" class="w-full bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Show Frequency') }}</label>
                            <select v-model="form.show_frequency" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option value="always">{{ t('Always Show') }}</option>
                                <option value="session">{{ t('Once per session') }}</option>
                                <option value="once">{{ t('Once per user') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Start Date (Optional)') }}</label>
                            <input v-model="form.starts_at" type="datetime-local" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('End Date (Optional)') }}</label>
                            <input v-model="form.ends_at" type="datetime-local" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div class="flex items-center gap-2 cursor-pointer mt-2">
                        <input v-model="form.is_active" type="checkbox" id="isActive" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        <label for="isActive" class="text-sm font-bold text-gray-700 dark:text-gray-300 cursor-pointer">{{ t('Active') }}</label>
                    </div>
                </div>
                
                <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="showForm = false" type="button" class="px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">{{ t('Cancel') }}</button>
                    <button @click="submit" :disabled="form.processing" type="button" class="px-6 py-2.5 bg-primary-600 text-white text-sm font-bold rounded-xl hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20 disabled:opacity-50">
                        {{ form.processing ? t('Saving...') : editingId ? t('Save Changes') : t('Add Announcement') }}
                    </button>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="deleteTargetId !== null"
            :title="t('Delete announcement?')"
            :message="t('This announcement will be deleted permanently.')"
            :confirm-label="t('Delete')"
            @cancel="deleteTargetId = null"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
