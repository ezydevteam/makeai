<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTranslate } from '@/Composables/useTranslate';

defineOptions({ layout: AdminLayout });

interface Language {
    id: number;
    name: string;
}

interface TranslationItem {
    id: number;
    key: string;
    value: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTranslations {
    data: TranslationItem[];
    from: number;
    to: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    language: Language;
    translations: PaginatedTranslations;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters.search || '');
const { t } = useTranslate();
const rows = ref<TranslationItem[]>([]);
const originalValues = ref<Record<number, string>>({});
const savingIds = ref<number[]>([]);
const savingAll = ref(false);
const syncedSearch = ref(props.filters.search || '');
const allowNavigation = ref(false);
let removeNavigationGuard: (() => void) | undefined;

const debounce = (fn: Function, delay: number) => {
    let timeoutId: ReturnType<typeof setTimeout>;
    return (...args: any[]) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

const hasUnsavedChanges = computed(() => rows.value.some((translation) => isDirty(translation)));
const dirtyRows = computed(() => rows.value.filter((translation) => isDirty(translation)));
const dirtyCount = computed(() => dirtyRows.value.length);

const resetRows = () => {
    rows.value = props.translations.data.map((translation) => ({ ...translation }));
    originalValues.value = Object.fromEntries(rows.value.map((translation) => [translation.id, translation.value ?? '']));
};

const isDirty = (translation: TranslationItem) => (translation.value ?? '') !== (originalValues.value[translation.id] ?? '');

const isSaving = (id: number) => savingIds.value.includes(id);

const rememberSaving = (id: number) => {
    savingIds.value = [...savingIds.value, id];
};

const forgetSaving = (id: number) => {
    savingIds.value = savingIds.value.filter((savingId) => savingId !== id);
};

const confirmDiscardUnsaved = () => !hasUnsavedChanges.value || confirm(t('You have unsaved translation changes. Continue without saving?'));

watch(search, debounce((val: string) => {
    if (val === syncedSearch.value) {
        return;
    }

    if (!confirmDiscardUnsaved()) {
        search.value = syncedSearch.value;
        return;
    }

    syncedSearch.value = val;
    allowNavigation.value = true;
    router.get(route('admin.translations.index', props.language.id), { search: val }, {
        preserveState: true,
        replace: true,
        onFinish: () => {
            allowNavigation.value = false;
        },
    });
}, 300));

watch(() => props.translations.data, resetRows, { immediate: true });

const saveTranslation = (translation: TranslationItem) => {
    if (!isDirty(translation) || isSaving(translation.id)) {
        return;
    }

    rememberSaving(translation.id);
    allowNavigation.value = true;
    router.post(route('admin.translations.update', translation.id), { value: translation.value }, {
        preserveScroll: true,
        only: ['translations', 'flash', 'errors'],
        onSuccess: () => {
            originalValues.value = {
                ...originalValues.value,
                [translation.id]: translation.value ?? '',
            };
        },
        onFinish: () => {
            allowNavigation.value = false;
            forgetSaving(translation.id);
        },
    });
};

const saveAllChanges = () => {
    if (!dirtyRows.value.length || savingAll.value) {
        return;
    }

    const changedTranslations = dirtyRows.value.map((translation) => ({
        id: translation.id,
        value: translation.value,
    }));

    savingAll.value = true;
    allowNavigation.value = true;
    router.post(route('admin.translations.bulk_update', props.language.id), { translations: changedTranslations }, {
        preserveScroll: true,
        only: ['translations', 'flash', 'errors'],
        onSuccess: () => {
            originalValues.value = {
                ...originalValues.value,
                ...Object.fromEntries(changedTranslations.map((translation) => [translation.id, translation.value ?? ''])),
            };
        },
        onFinish: () => {
            allowNavigation.value = false;
            savingAll.value = false;
        },
    });
};

const aiTranslate = (id: number) => {
    if (!confirmDiscardUnsaved()) {
        return;
    }

    allowNavigation.value = true;
    router.post(route('admin.translations.ai', id), {}, {
        preserveScroll: true,
        onFinish: () => {
            allowNavigation.value = false;
        },
    });
};

const aiTranslateAll = () => {
    if (!confirmDiscardUnsaved()) {
        return;
    }

    if (confirm(t('AI will attempt to translate missing strings. Continue?'))) {
        allowNavigation.value = true;
        router.post(route('admin.translations.ai_all', props.language.id), {}, {
            onFinish: () => {
                allowNavigation.value = false;
            },
        });
    }
};

const warnBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!hasUnsavedChanges.value) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
};

onMounted(() => {
    window.addEventListener('beforeunload', warnBeforeUnload);
    removeNavigationGuard = router.on('before', (event) => {
        if (allowNavigation.value || !hasUnsavedChanges.value) {
            return;
        }

        if (!confirm(t('You have unsaved translation changes. Continue without saving?'))) {
            event.preventDefault();
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', warnBeforeUnload);
    removeNavigationGuard?.();
});
</script>

<template>
    <Head :title="t('Translations — :language', { language: language.name })" />
    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <Link :href="route('admin.languages.index')" class="shrink-0 rounded-lg bg-gray-100 p-2 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </Link>
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-bold text-gray-900">{{ t(':language Translations', { language: language.name }) }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Edit phrases and use AI to fill missing translations.') }}</p>
                </div>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-primary-500/20 transition-all hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="dirtyCount === 0 || savingAll"
                    @click="saveAllChanges"
                >
                    <svg v-if="savingAll" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                    <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    {{ dirtyCount > 0 ? t('Save :count changes', { count: dirtyCount }) : t('All changes saved') }}
                </button>
                <button @click="aiTranslateAll" type="button" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-accent-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-accent-500/20 transition-all hover:bg-accent-500">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                    {{ t('AI translate missing') }}
                </button>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="relative mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <span class="pointer-events-none absolute inset-y-0 left-7 flex items-center text-gray-400 rtl:left-auto rtl:right-7">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </span>
            <input v-model="search" type="text" :placeholder="t('Search by key or translation...')" class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm text-gray-900 transition-all focus:border-primary-500 focus:outline-none rtl:pl-4 rtl:pr-10" />
        </div>

        <!-- Translations Grid -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="divide-y divide-gray-100">
                <div v-for="translation in rows" :key="translation.id" class="group grid grid-cols-1 gap-4 p-4 transition-colors hover:bg-gray-50/50 lg:grid-cols-[minmax(0,1fr)_minmax(320px,1.35fr)]">
                    <div class="min-w-0 space-y-1">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ t('Original / Key') }}</label>
                        <p class="break-words rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs leading-5 text-gray-700">{{ translation.key }}</p>
                    </div>
                    <div class="min-w-0 space-y-2">
                        <label class="flex items-center justify-between gap-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                            <span>{{ t('Translation') }}</span>
                            <button @click="aiTranslate(translation.id)" type="button" class="inline-flex shrink-0 items-center gap-1 text-accent-600 opacity-100 transition-opacity hover:text-accent-700 lg:opacity-0 lg:group-hover:opacity-100">
                                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                {{ t('AI auto-fill') }}
                            </button>
                        </label>
                        <div class="min-w-0">
                            <textarea
                                v-model="translation.value"
                                rows="2"
                                class="block w-full resize-y rounded-lg border bg-gray-50 px-3 py-2 text-sm leading-5 text-gray-900 transition-all focus:border-primary-500 focus:outline-none"
                                :class="isDirty(translation) ? 'border-primary-300 ring-2 ring-primary-100' : 'border-gray-200'"
                            ></textarea>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-xs font-medium" :class="isDirty(translation) ? 'text-amber-600' : 'text-gray-400'">
                                {{ isDirty(translation) ? t('Unsaved changes') : t('Saved') }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!isDirty(translation) || isSaving(translation.id) || savingAll"
                                @click="saveTranslation(translation)"
                            >
                                <svg v-if="isSaving(translation.id)" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                                <svg v-else class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                {{ t('Save') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="!rows.length" class="px-6 py-12 text-center text-sm text-gray-400">
                    {{ t('No translations found.') }}
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="translations.links.length > 3" class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500">{{ t('Showing :from to :to of :total phrases', { from: translations.from, to: translations.to, total: translations.total }) }}</p>
                <div class="flex flex-wrap items-center gap-1">
                    <Link v-for="(link, i) in translations.links" :key="i" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50', !link.url ? 'opacity-50 cursor-not-allowed' : '']" class="px-3 py-1.5 text-xs font-bold border rounded-lg transition-all" />
                </div>
            </div>
        </div>
    </div>
</template>
