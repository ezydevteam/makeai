<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlagIcon from '@/Components/FlagIcon.vue';
import { useTranslate } from '@/Composables/useTranslate';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    languages: Array<any>
}>();

const showAddModal = ref(false);
const editingLang = ref<any>(null);
const { t } = useTranslate();

const dateFormatOptions = [
    { value: 'MMM D, YYYY', label: 'Jan 31, 2026', help: 'Month name, day, year' },
    { value: 'D MMM YYYY', label: '31 Jan 2026', help: 'Day, month name, year' },
    { value: 'MM/DD/YYYY', label: '01/31/2026', help: 'US numeric date' },
    { value: 'DD/MM/YYYY', label: '31/01/2026', help: 'International numeric date' },
    { value: 'YYYY-MM-DD', label: '2026-01-31', help: 'ISO date' },
];

const timeFormatOptions = [
    { value: 'h:mm A', label: '9:30 PM', help: '12-hour clock' },
    { value: 'HH:mm', label: '21:30', help: '24-hour clock' },
    { value: 'HH:mm:ss', label: '21:30:45', help: '24-hour clock with seconds' },
];

const numberSystemOptions = [
    { value: 'latn', label: 'English digits', sample: '123,456.78' },
    { value: 'arab', label: 'Arabic-Indic digits', sample: '١٢٣٬٤٥٦٫٧٨' },
    { value: 'arabext', label: 'Eastern Arabic digits', sample: '۱۲۳٬۴۵۶٫۷۸' },
    { value: 'beng', label: 'Bengali digits', sample: '১২৩,৪৫৬.৭৮' },
    { value: 'deva', label: 'Devanagari digits', sample: '१२३,४५६.७८' },
];

const currencyPositionOptions = [
    { value: 'before', label: 'Before amount', sample: '$99.00' },
    { value: 'before_with_space', label: 'Before amount with space', sample: '$ 99.00' },
    { value: 'after', label: 'After amount', sample: '99.00$' },
    { value: 'after_with_space', label: 'After amount with space', sample: '99.00 $' },
];

const currencyPositionValues = currencyPositionOptions.map((option) => option.value);

const normalizeCurrencyPosition = (value: unknown) => {
    const position = String(value ?? 'before');

    return currencyPositionValues.includes(position) ? position : 'before';
};

const setCurrencyPosition = (event: Event) => {
    form.currency_position = normalizeCurrencyPosition((event.target as HTMLSelectElement).value);
};

void [
    t('Jan 31, 2026'),
    t('31 Jan 2026'),
    t('01/31/2026'),
    t('31/01/2026'),
    t('2026-01-31'),
    t('Month name, day, year'),
    t('Day, month name, year'),
    t('US numeric date'),
    t('International numeric date'),
    t('ISO date'),
    t('9:30 PM'),
    t('21:30'),
    t('21:30:45'),
    t('12-hour clock'),
    t('24-hour clock'),
    t('24-hour clock with seconds'),
    t('English digits'),
    t('Arabic-Indic digits'),
    t('Eastern Arabic digits'),
    t('Bengali digits'),
    t('Devanagari digits'),
    t('Before amount'),
    t('Before amount with space'),
    t('After amount'),
    t('After amount with space'),
];

const form = useForm({
    name: '',
    code: '',
    flag_file: null as File | null,
    is_rtl: false,
    is_active: true,
    date_format: 'MMM D, YYYY',
    time_format: 'h:mm A',
    decimal_separator: '.',
    thousands_separator: ',',
    number_system: 'latn',
    currency_position: 'before',
});

const openAddModal = () => {
    editingLang.value = null;
    form.reset();
    showAddModal.value = true;
};

const openEditModal = (lang: any) => {
    editingLang.value = lang;
    form.name = lang.name;
    form.code = lang.code;
    form.flag_file = null;
    form.is_rtl = lang.is_rtl;
    form.is_active = lang.is_active;
    form.date_format = lang.date_format;
    form.time_format = lang.time_format;
    form.decimal_separator = lang.decimal_separator;
    form.thousands_separator = lang.thousands_separator;
    form.number_system = lang.number_system;
    form.currency_position = normalizeCurrencyPosition(lang.currency_position);
    showAddModal.value = true;
};

const submit = () => {
    form.currency_position = normalizeCurrencyPosition(form.currency_position);

    if (editingLang.value) {
        form.post(route('admin.languages.update', editingLang.value.id), {
            forceFormData: true,
            preserveState: false,
            onSuccess: () => showAddModal.value = false
        });
    } else {
        form.post(route('admin.languages.store'), {
            forceFormData: true,
            preserveState: false,
            onSuccess: () => showAddModal.value = false
        });
    }
};

const setDefault = (id: number) => {
    useForm({}).post(route('admin.languages.default', id));
};

const deleteLang = (id: number) => {
    if (confirm(t('Delete this language and all its translations?'))) {
        useForm({}).delete(route('admin.languages.delete', id));
    }
};

const setFlagFile = (event: Event) => {
    form.flag_file = (event.target as HTMLInputElement).files?.[0] ?? null;
};
</script>

<template>
    <Head :title="t('Languages')" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('Languages') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ t('Manage platform languages and RTL settings.') }}</p>
            </div>
            <button @click="openAddModal" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20">
                {{ t('Add language') }}
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="lang in languages" :key="lang.id" :class="[lang.is_default ? 'border-primary-500 ring-1 ring-primary-500' : 'border-gray-200']" class="bg-white border rounded-2xl p-6 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <FlagIcon :flag="lang.flag" :language-code="lang.code" :language-name="lang.name" size="lg" />
                    <div class="flex gap-2">
                        <span v-if="lang.is_default" class="px-2 py-0.5 bg-primary-50 text-primary-600 text-[10px] font-bold rounded-full border border-primary-100">{{ t('Default') }}</span>
                        <span v-if="lang.is_rtl" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded-full">{{ t('RTL') }}</span>
                        <span v-if="!lang.is_active" class="px-2 py-0.5 bg-danger-50 text-danger-600 text-[10px] font-bold rounded-full">{{ t('Inactive') }}</span>
                    </div>
                </div>

                <h3 class="text-lg font-bold text-gray-900">{{ lang.name }}</h3>
                <p class="text-sm text-gray-400 font-mono mb-6">{{ lang.code }}</p>

                <div class="mt-auto pt-6 border-t border-gray-100 flex items-center justify-between">
                    <Link :href="route('admin.translations.index', lang.id)" class="text-sm font-bold text-primary-600 hover:text-primary-700">
                        {{ t('Translations') }}
                    </Link>
                    <div class="flex items-center gap-3">
                        <button v-if="!lang.is_default" @click="setDefault(lang.id)" class="text-xs font-bold text-gray-400 hover:text-gray-900 transition-colors">{{ t('Set default') }}</button>
                        <button @click="openEditModal(lang)" class="text-xs font-bold text-gray-400 hover:text-primary-600 transition-colors">{{ t('Edit') }}</button>
                        <button v-if="!lang.is_default" @click="deleteLang(lang.id)" class="text-xs font-bold text-gray-400 hover:text-danger-600 transition-colors">{{ t('Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">{{ editingLang ? t('Edit language') : t('Add language') }}</h3>
                    <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Name') }}</label>
                            <input v-model="form.name" type="text" :placeholder="t('e.g. French')" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('ISO code') }}</label>
                            <input v-model="form.code" type="text" :placeholder="t('fr')" :disabled="editingLang" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none disabled:opacity-50" required />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Flag image') }}</label>
                        <div class="flex items-center gap-3">
                            <FlagIcon v-if="editingLang" :flag="editingLang.flag" :language-code="editingLang.code" :language-name="editingLang.name" size="lg" />
                            <input type="file" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" @change="setFlagFile" />
                        </div>
                        <p class="mt-1 text-xs text-gray-400">{{ t('Upload SVG, PNG, JPG, or WebP up to 512 KB.') }}</p>
                        <p v-if="form.errors.flag_file" class="mt-1 text-xs text-danger-600">{{ form.errors.flag_file }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Date format') }}</label>
                            <select v-model="form.date_format" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required>
                                <option v-for="option in dateFormatOptions" :key="option.value" :value="option.value">
                                    {{ t(option.label) }} - {{ t(option.help) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Time format') }}</label>
                            <select v-model="form.time_format" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required>
                                <option v-for="option in timeFormatOptions" :key="option.value" :value="option.value">
                                    {{ t(option.label) }} - {{ t(option.help) }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Decimal') }}</label>
                            <input v-model="form.decimal_separator" type="text" maxlength="1" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Thousands') }}</label>
                            <input v-model="form.thousands_separator" type="text" maxlength="1" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Number system') }}</label>
                            <select v-model="form.number_system" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required>
                                <option v-for="option in numberSystemOptions" :key="option.value" :value="option.value">
                                    {{ t(option.label) }} ({{ option.sample }})
                                </option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Currency position') }}</label>
                        <select :value="form.currency_position" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required @change="setCurrencyPosition">
                            <option v-for="option in currencyPositionOptions" :key="option.value" :value="option.value">
                                {{ t(option.label) }} ({{ option.sample }})
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.is_rtl" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ t('RTL layout') }}</span>
                        </label>
                        <label v-if="editingLang" class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ t('Active') }}</span>
                        </label>
                    </div>
                    <div class="pt-4">
                        <button type="submit" :disabled="form.processing" class="w-full py-3 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            {{ form.processing ? t('Processing...') : (editingLang ? t('Update language') : t('Create language')) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
