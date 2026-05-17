<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

declare const route: (name: string, params?: Record<string, string | number>) => string

interface FaqCategory { id: number; name: string; slug: string; sort_order: number; faqs: Faq[] }
interface Faq { id: number; question: string; answer: string; category_id: number | null; is_active: boolean; sort_order: number }

const props = defineProps<{ categories: FaqCategory[]; uncategorized: Faq[] }>()

// ── FAQ form ────────────────────────────────────────────────────────────────
const showFaqForm = ref(false)
const editingFaqId = ref<number | null>(null)
const faqForm = useForm({ question: '', answer: '', category_id: null as number | null, is_active: true, sort_order: 0 })

const openCreateFaq = (categoryId?: number) => {
    faqForm.reset()
    faqForm.question = ''
    faqForm.answer = ''
    faqForm.category_id = categoryId ?? null
    faqForm.is_active = true
    faqForm.sort_order = 0
    editingFaqId.value = null
    showFaqForm.value = true
}

const openEditFaq = (faq: Faq) => {
    faqForm.question = faq.question
    faqForm.answer = faq.answer
    faqForm.category_id = faq.category_id
    faqForm.is_active = faq.is_active
    faqForm.sort_order = faq.sort_order
    editingFaqId.value = faq.id
    showFaqForm.value = true
}

const submitFaq = () => {
    if (editingFaqId.value) {
        faqForm.post(route('admin.faqs.update', { faq: editingFaqId.value }), { onSuccess: () => { showFaqForm.value = false } })
    } else {
        faqForm.post(route('admin.faqs.store'), { onSuccess: () => { showFaqForm.value = false } })
    }
}

const removeFaq = (id: number) => {
    if (!confirm('Delete this FAQ?')) return
    router.delete(route('admin.faqs.delete', { faq: id }), { preserveScroll: true })
}

const toggleFaqActive = (id: number) => router.post(route('admin.faqs.active', { faq: id }), {}, { preserveScroll: true })

// ── Category form ────────────────────────────────────────────────────────────
const showCatForm = ref(false)
const editingCatId = ref<number | null>(null)
const catForm = useForm({ name: '', sort_order: 0 })

const openCreateCat = () => { catForm.reset(); catForm.name = ''; catForm.sort_order = 0; editingCatId.value = null; showCatForm.value = true }
const openEditCat = (cat: FaqCategory) => { catForm.name = cat.name; catForm.sort_order = cat.sort_order; editingCatId.value = cat.id; showCatForm.value = true }

const submitCat = () => {
    if (editingCatId.value) {
        catForm.post(route('admin.faq-categories.update', { category: editingCatId.value }), { onSuccess: () => { showCatForm.value = false } })
    } else {
        catForm.post(route('admin.faq-categories.store'), { onSuccess: () => { showCatForm.value = false } })
    }
}

const removeCat = (id: number) => {
    if (!confirm('Delete category? FAQs inside will become uncategorized.')) return
    router.delete(route('admin.faq-categories.delete', { category: id }), { preserveScroll: true })
}

// ── Import ───────────────────────────────────────────────────────────────────
const importInput = ref<HTMLInputElement | null>(null)
const importCategoryId = ref<number | null>(null)

const handleImport = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    const fd = new FormData()
    fd.append('csv', file)
    if (importCategoryId.value) fd.append('category_id', String(importCategoryId.value))
    router.post(route('admin.faqs.import'), fd, { preserveScroll: true })
}

const totalFaqs = props.categories.reduce((s, c) => s + c.faqs.length, 0) + props.uncategorized.length
</script>

<template>
    <Head title="FAQs — Admin" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto px-6 py-8">

            <!-- Header -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">FAQs</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Manage frequently asked questions displayed on your homepage FAQ section and /faq page.
                        <a :href="route('admin.homepage.index')" class="text-primary-600 hover:underline ml-1">← Homepage Builder</a>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input ref="importInput" type="file" accept=".csv,.txt" class="hidden" @change="handleImport">
                    <button @click="importInput?.click()" type="button" class="px-4 py-2.5 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-bold hover:bg-gray-50 transition-all shadow-sm">Import CSV</button>
                    <button @click="openCreateCat" type="button" class="px-4 py-2.5 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-bold hover:bg-gray-50 transition-all shadow-sm">+ Category</button>
                    <button @click="openCreateFaq()" type="button" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20">+ Add FAQ</button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4 mb-8">
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-gray-900 dark:text-white">{{ totalFaqs }}</div>
                    <div class="text-xs text-gray-500 mt-1">Total FAQs</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-primary-600">{{ categories.length }}</div>
                    <div class="text-xs text-gray-500 mt-1">Categories</div>
                </div>
                <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5">
                    <div class="text-2xl font-black text-gray-400">{{ uncategorized.length }}</div>
                    <div class="text-xs text-gray-500 mt-1">Uncategorized</div>
                </div>
            </div>

            <!-- Empty -->
            <div v-if="totalFaqs === 0 && categories.length === 0" class="bg-white dark:bg-surface-900 rounded-2xl border-2 border-dashed border-gray-200 dark:border-surface-700 p-16 text-center">
                <div class="text-5xl mb-4">❓</div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No FAQs yet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Add FAQs to display in your homepage accordion or the /faq page.</p>
                <button @click="openCreateFaq()" type="button" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all">Add first FAQ</button>
            </div>

            <div v-else class="space-y-6">
                <!-- Categories -->
                <div v-for="cat in categories" :key="cat.id" class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-surface-800 bg-gray-50 dark:bg-surface-800/50">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ cat.name }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">{{ cat.faqs.length }} FAQ{{ cat.faqs.length !== 1 ? 's' : '' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="openCreateFaq(cat.id)" type="button" class="px-3 py-1.5 text-xs font-bold text-primary-600 bg-primary-50 dark:bg-primary-900/20 rounded-lg hover:bg-primary-100 transition-colors">+ FAQ</button>
                            <button @click="openEditCat(cat)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-gray-500 hover:text-primary-600 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click="removeCat(cat.id)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-danger-500 hover:bg-danger-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    <div v-if="cat.faqs.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">No FAQs in this category yet.</div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div v-for="faq in cat.faqs" :key="faq.id" class="px-6 py-4 flex items-start gap-4" :class="!faq.is_active ? 'opacity-50' : ''">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ faq.question }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ faq.answer }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button @click="toggleFaqActive(faq.id)" type="button" :class="faq.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-5 w-9 rounded-full transition-colors">
                                    <span :class="faq.is_active ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                                </button>
                                <button @click="openEditFaq(faq)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-gray-500 hover:text-primary-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button @click="removeFaq(faq.id)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-danger-500 hover:bg-danger-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Uncategorized -->
                <div v-if="uncategorized.length > 0" class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-surface-800 bg-gray-50 dark:bg-surface-800/50">
                        <h3 class="font-bold text-gray-500 dark:text-gray-400">Uncategorized</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div v-for="faq in uncategorized" :key="faq.id" class="px-6 py-4 flex items-start gap-4" :class="!faq.is_active ? 'opacity-50' : ''">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ faq.question }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ faq.answer }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button @click="toggleFaqActive(faq.id)" type="button" :class="faq.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-5 w-9 rounded-full transition-colors">
                                    <span :class="faq.is_active ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                                </button>
                                <button @click="openEditFaq(faq)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-gray-500 hover:text-primary-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button @click="removeFaq(faq.id)" type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 text-danger-500 hover:bg-danger-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Modal -->
        <div v-if="showFaqForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingFaqId ? 'Edit FAQ' : 'Add FAQ' }}</h3>
                    <button @click="showFaqForm = false" type="button" class="text-gray-400 hover:text-gray-700 text-sm">Close</button>
                </div>
                <div class="p-6 overflow-y-auto space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Question *</label>
                        <input v-model="faqForm.question" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <p v-if="faqForm.errors.question" class="text-xs text-danger-500 mt-1">{{ faqForm.errors.question }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Answer *</label>
                        <textarea v-model="faqForm.answer" rows="5" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                        <p v-if="faqForm.errors.answer" class="text-xs text-danger-500 mt-1">{{ faqForm.errors.answer }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Category</label>
                            <select v-model="faqForm.category_id" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option :value="null">— Uncategorized —</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Sort Order</label>
                            <input v-model.number="faqForm.sort_order" type="number" min="0" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="faqForm.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Active</span>
                    </label>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="showFaqForm = false" type="button" class="px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">Cancel</button>
                    <button @click="submitFaq" :disabled="faqForm.processing" type="button" class="px-6 py-2.5 bg-primary-600 text-white text-sm font-bold rounded-xl hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20 disabled:opacity-50">
                        {{ faqForm.processing ? 'Saving…' : editingFaqId ? 'Save Changes' : 'Add FAQ' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Category Modal -->
        <div v-if="showCatForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingCatId ? 'Edit Category' : 'Add Category' }}</h3>
                    <button @click="showCatForm = false" type="button" class="text-gray-400 hover:text-gray-700 text-sm">Close</button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Name *</label>
                        <input v-model="catForm.name" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <p v-if="catForm.errors.name" class="text-xs text-danger-500 mt-1">{{ catForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Sort Order</label>
                        <input v-model.number="catForm.sort_order" type="number" min="0" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                    </div>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="showCatForm = false" type="button" class="px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 rounded-xl transition-colors">Cancel</button>
                    <button @click="submitCat" :disabled="catForm.processing" type="button" class="px-6 py-2.5 bg-primary-600 text-white text-sm font-bold rounded-xl hover:bg-primary-500 transition-all disabled:opacity-50">
                        {{ catForm.processing ? 'Saving…' : editingCatId ? 'Save' : 'Create' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
