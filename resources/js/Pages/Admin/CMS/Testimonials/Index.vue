<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

declare const route: (name: string, params?: Record<string, string | number>) => string

interface Testimonial {
    id: number
    name: string
    role: string | null
    company: string | null
    avatar: string | null
    content: string
    rating: number
    is_active: boolean
    is_featured: boolean
    sort_order: number
    source: 'manual' | 'google' | 'trustpilot' | 'import'
}

const props = defineProps<{ testimonials: Testimonial[] }>()
const toast = useToastr()
const { t } = useTranslate()

const showForm = ref(false)
const editingId = ref<number | null>(null)
const importInput = ref<HTMLInputElement | null>(null)
const avatarPreview = ref<string | null>(null)
const showAiForm = ref(false)
const aiGenerating = ref(false)
const aiForm = ref({
    company_type: 'AI SaaS platform',
    tone: 'professional and authentic',
    prompt: '',
    count: 5,
})

const toneOptions = [
    { value: 'professional and authentic', label: 'Professional' },
    { value: 'friendly and conversational', label: 'Friendly' },
    { value: 'confident and results-focused', label: 'Results-focused' },
    { value: 'warm and trustworthy', label: 'Warm' },
    { value: 'premium and polished', label: 'Premium' },
    { value: 'casual startup voice', label: 'Casual startup' },
]

interface TestimonialForm {
    name: string
    role: string
    company: string
    avatar: string
    avatar_file: File | null
    content: string
    rating: number
    is_active: boolean
    is_featured: boolean
    sort_order: number
    source: Testimonial['source']
}

const blank = (): TestimonialForm => ({
    name: '',
    role: '',
    company: '',
    avatar: '',
    avatar_file: null,
    content: '',
    rating: 5,
    is_active: true,
    is_featured: false,
    sort_order: props.testimonials.length,
    source: 'manual',
})

const form = useForm(blank())

const openCreate = () => {
    form.reset()
    Object.assign(form, blank())
    avatarPreview.value = null
    editingId.value = null
    showForm.value = true
}

const openEdit = (t: Testimonial) => {
    form.name = t.name
    form.role = t.role ?? ''
    form.company = t.company ?? ''
    form.avatar = t.avatar ?? ''
    form.avatar_file = null
    form.content = t.content
    form.rating = t.rating
    form.is_active = t.is_active
    form.is_featured = t.is_featured
    form.sort_order = t.sort_order
    form.source = t.source
    avatarPreview.value = resolveAvatar(t.avatar)
    editingId.value = t.id
    showForm.value = true
}

const submit = () => {
    if (editingId.value) {
        form.post(route('admin.testimonials.update', { testimonial: editingId.value }), {
            forceFormData: true,
            onSuccess: () => { showForm.value = false },
        })
    } else {
        form.post(route('admin.testimonials.store'), {
            forceFormData: true,
            onSuccess: () => { showForm.value = false },
        })
    }
}

const remove = (id: number) => {
    if (!confirm(t('Delete this testimonial?'))) return
    router.delete(route('admin.testimonials.delete', { testimonial: id }), { preserveScroll: true })
}

const toggleFeatured = (id: number) => {
    router.post(route('admin.testimonials.featured', { testimonial: id }), {}, { preserveScroll: true })
}

const toggleActive = (id: number) => {
    router.post(route('admin.testimonials.active', { testimonial: id }), {}, { preserveScroll: true })
}

const importFile = () => importInput.value?.click()

const handleImport = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    const fd = new FormData()
    fd.append('csv', file)
    router.post(route('admin.testimonials.import'), fd, { preserveScroll: true })
}

const handleAvatarChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    form.avatar_file = file
    avatarPreview.value = file ? URL.createObjectURL(file) : resolveAvatar(form.avatar)
}

const resolveAvatar = (avatar: string | null): string => {
    if (!avatar) return ''
    if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')) return avatar

    return `/storage/${avatar}`
}

const generateTestimonials = async () => {
    if (aiGenerating.value) return
    aiGenerating.value = true

    try {
        const response = await fetch(route('admin.testimonials.generate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
            },
            body: JSON.stringify(aiForm.value),
        })
        const payload = await response.json()
        if (!response.ok || !payload.success) throw new Error(payload.message || t('AI generation failed.'))

        toast.success(payload.message || t('Testimonials generated.'))
        showAiForm.value = false
        router.reload({ only: ['testimonials'] })
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('AI generation failed.'))
    } finally {
        aiGenerating.value = false
    }
}

const stars = (n: number) => Array.from({ length: 5 }, (_, i) => i < n)

const sourceColor: Record<string, string> = {
    manual: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    google: 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    trustpilot: 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    import: 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-400',
}
</script>

<template>
    <Head title="Testimonials — Admin" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto px-6 py-8">

            <!-- Header -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Testimonials</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Manage customer reviews shown on the homepage and marketing pages.
                        <a :href="route('admin.homepage.index')" class="text-primary-600 hover:underline ml-1">← Homepage Builder</a>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input ref="importInput" type="file" accept=".csv,.txt" class="hidden" @change="handleImport">
                    <button @click="importFile" type="button" class="px-4 py-2.5 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-bold hover:bg-gray-50 dark:hover:bg-surface-800 transition-all shadow-sm">
                        Import CSV
                    </button>
                    <button @click="showAiForm = true" type="button" class="px-4 py-2.5 bg-violet-50 dark:bg-violet-900/20 border border-violet-100 dark:border-violet-900/40 text-violet-700 dark:text-violet-300 rounded-xl text-sm font-bold hover:bg-violet-100 transition-all shadow-sm">
                        ✨ AI Generate
                    </button>
                    <button @click="openCreate" type="button" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20">
                        + Add Testimonial
                    </button>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-gray-900 dark:text-white">{{ testimonials.length }}</div>
                    <div class="text-xs text-gray-500 mt-1">Total</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-success-600">{{ testimonials.filter(t => t.is_active).length }}</div>
                    <div class="text-xs text-gray-500 mt-1">Active</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-yellow-500">{{ testimonials.filter(t => t.is_featured).length }}</div>
                    <div class="text-xs text-gray-500 mt-1">Featured</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-primary-600">
                        {{ testimonials.length ? (testimonials.reduce((s,t) => s + t.rating, 0) / testimonials.length).toFixed(1) : '—' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Avg Rating</div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="testimonials.length === 0" class="bg-white dark:bg-surface-900 rounded-2xl border-2 border-dashed border-gray-200 dark:border-surface-700 p-16 text-center">
                <div class="text-5xl mb-4">💬</div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No testimonials yet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Add testimonials to display on your homepage and boost trust with visitors.</p>
                <button @click="openCreate" type="button" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all">Add first testimonial</button>
            </div>

            <!-- Testimonial cards -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div
                    v-for="t in testimonials"
                    :key="t.id"
                    class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5 flex flex-col gap-4 hover:shadow-md transition-shadow"
                    :class="!t.is_active ? 'opacity-60' : ''"
                >
                    <!-- Stars -->
                    <div class="flex items-center gap-0.5">
                        <svg v-for="(filled, i) in stars(t.rating)" :key="i" class="w-4 h-4" :class="filled ? 'text-yellow-400' : 'text-gray-200 dark:text-surface-700'" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>

                    <!-- Content -->
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed line-clamp-4">"{{ t.content }}"</p>

                    <!-- Author -->
                    <div class="flex items-center gap-3 mt-auto">
                        <div v-if="t.avatar" class="w-9 h-9 rounded-full bg-gray-100 dark:bg-surface-800 overflow-hidden shrink-0">
                            <img :src="resolveAvatar(t.avatar)" :alt="t.name" class="w-full h-full object-cover">
                        </div>
                        <div v-else class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 font-black text-sm shrink-0">
                            {{ t.name.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ t.name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ [t.role, t.company].filter(Boolean).join(' · ') || '—' }}</div>
                        </div>
                        <span :class="sourceColor[t.source]" class="ml-auto text-[10px] uppercase font-bold px-2 py-0.5 rounded-md shrink-0">{{ t.source }}</span>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-surface-800">
                        <button @click="toggleFeatured(t.id)" type="button" :title="t.is_featured ? 'Remove from featured' : 'Mark featured'" :class="t.is_featured ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'" class="transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </button>
                        <button @click="toggleActive(t.id)" type="button" :class="t.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-5 w-9 rounded-full transition-colors ml-1">
                            <span :class="t.is_active ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                        </button>
                        <div class="flex-1"></div>
                        <button @click="openEdit(t)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-gray-500 hover:text-primary-600 hover:border-primary-300 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button @click="remove(t.id)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-danger-500 hover:bg-danger-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingId ? 'Edit Testimonial' : 'Add Testimonial' }}</h3>
                    <button @click="showForm = false" type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-sm">Close</button>
                </div>
                <div class="p-6 overflow-y-auto space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Name *</label>
                            <input v-model="form.name" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <p v-if="form.errors.name" class="text-xs text-danger-500 mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Role</label>
                            <input v-model="form.role" type="text" placeholder="CEO, Developer…" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Company</label>
                            <input v-model="form.company" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Avatar</label>
                            <div class="flex items-center gap-3">
                                <div class="h-11 w-11 shrink-0 overflow-hidden rounded-full bg-primary-50 text-primary-700 flex items-center justify-center font-black">
                                    <img v-if="avatarPreview" :src="avatarPreview" class="h-full w-full object-cover">
                                    <span v-else>{{ form.name ? form.name.charAt(0) : 'U' }}</span>
                                </div>
                                <label class="cursor-pointer rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-xs font-bold text-gray-700 transition-colors hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300">
                                    <input type="file" accept="image/*" class="hidden" @change="handleAvatarChange">
                                    Upload photo
                                </label>
                            </div>
                            <p v-if="form.errors.avatar_file" class="text-xs text-danger-500 mt-1">{{ form.errors.avatar_file }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Review *</label>
                        <textarea v-model="form.content" rows="4" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                        <p v-if="form.errors.content" class="text-xs text-danger-500 mt-1">{{ form.errors.content }}</p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Rating</label>
                            <select v-model.number="form.rating" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option v-for="n in [5,4,3,2,1]" :key="n" :value="n">{{ n }} ★</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Source</label>
                            <select v-model="form.source" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option value="manual">Manual</option>
                                <option value="google">Google</option>
                                <option value="trustpilot">Trustpilot</option>
                                <option value="import">Import</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Sort</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                        <div class="flex flex-col gap-3 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Featured</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="showForm = false" type="button" class="px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">Cancel</button>
                    <button @click="submit" :disabled="form.processing" type="button" class="px-6 py-2.5 bg-primary-600 text-white text-sm font-bold rounded-xl hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20 disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : editingId ? 'Save Changes' : 'Add Testimonial' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- AI Generate Modal -->
        <div v-if="showAiForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">✨ AI Generate Testimonials</h3>
                    <button @click="showAiForm = false" type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-sm">Close</button>
                </div>
                <div class="p-6 space-y-4">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Company type
                        <input v-model="aiForm.company_type" type="text" class="mt-2 w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </label>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Tone
                        <select v-model="aiForm.tone" class="mt-2 w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option v-for="option in toneOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Prompt <span class="text-gray-400 normal-case">(optional)</span>
                        <textarea v-model="aiForm.prompt" rows="3" class="mt-2 w-full resize-none bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Mention target audience, product benefits, wording to avoid..."></textarea>
                    </label>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Count
                        <input v-model.number="aiForm.count" type="number" min="1" max="10" class="mt-2 w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </label>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="showAiForm = false" type="button" class="px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">Cancel</button>
                    <button @click="generateTestimonials" :disabled="aiGenerating" type="button" class="px-6 py-2.5 bg-primary-600 text-white text-sm font-bold rounded-xl hover:bg-primary-500 transition-all disabled:opacity-50">
                        {{ aiGenerating ? 'Generating...' : 'Generate' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
