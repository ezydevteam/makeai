<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    currencies: Array<any>
}>();

const showModal = ref(false);
const editingCurrency = ref<any>(null);

const form = useForm({
    code: '',
    symbol: '',
    name: '',
    exchange_rate: 1,
    decimal_places: 2,
    is_active: true
});

const openAddModal = () => {
    editingCurrency.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (curr: any) => {
    editingCurrency.value = curr;
    form.code = curr.code;
    form.symbol = curr.symbol;
    form.name = curr.name;
    form.exchange_rate = curr.exchange_rate;
    form.decimal_places = curr.decimal_places;
    form.is_active = curr.is_active;
    showModal.value = true;
};

const submit = () => {
    if (editingCurrency.value) {
        form.post(route('admin.currencies.update', editingCurrency.value.id), {
            onSuccess: () => showModal.value = false
        });
    } else {
        form.post(route('admin.currencies.store'), {
            onSuccess: () => showModal.value = false
        });
    }
};

const setDefault = (id: number) => {
    useForm({}).post(route('admin.currencies.default', id));
};

const syncRates = () => {
    useForm({}).post(route('admin.currencies.sync'));
};

const deleteCurrency = (id: number) => {
    if (confirm('Delete this currency? This might affect pricing calculations.')) {
        useForm({}).delete(route('admin.currencies.delete', id));
    }
};
</script>

<template>
    <Head title="Currencies — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Currencies</h1>
                <p class="text-sm text-gray-500 mt-1">Manage global pricing currencies and exchange rates.</p>
            </div>
            <div class="flex gap-3">
                <button @click="syncRates" class="px-5 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-bold hover:bg-gray-800 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    SYNC RATES
                </button>
                <button @click="openAddModal" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20">
                    ADD CURRENCY
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="curr in currencies" :key="curr.id" :class="[curr.is_default ? 'border-primary-500 ring-1 ring-primary-500' : 'border-gray-200']" class="bg-white border rounded-2xl p-6 shadow-sm flex flex-col relative overflow-hidden group">
                <div v-if="curr.is_default" class="absolute top-0 right-0 px-3 py-1 bg-primary-500 text-white text-[10px] font-black rounded-bl-xl">DEFAULT</div>
                
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-14 h-14 bg-gray-50 rounded-2xl flex items-center justify-center text-2xl font-bold text-gray-900 border border-gray-100 group-hover:bg-primary-50 group-hover:border-primary-100 transition-colors">
                        {{ curr.symbol }}
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ curr.name }}</h3>
                        <p class="text-xs text-gray-400 font-mono tracking-widest">{{ curr.code }}</p>
                    </div>
                </div>

                <div class="space-y-3 mb-6">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-400">Exchange Rate</span>
                        <span class="font-bold text-gray-900">1 {{ currencies.find(c => c.is_default)?.code || 'USD' }} = {{ curr.exchange_rate }} {{ curr.code }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-400">Decimals</span>
                        <span class="font-medium text-gray-700">{{ curr.decimal_places }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-400">Status</span>
                        <span :class="curr.is_active ? 'text-success-600' : 'text-danger-600'" class="font-bold uppercase text-[10px]">{{ curr.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>

                <div class="mt-auto pt-6 border-t border-gray-100 flex items-center gap-3">
                    <button @click="openEditModal(curr)" class="flex-1 py-2 text-xs font-bold text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Edit</button>
                    <button v-if="!curr.is_default" @click="setDefault(curr.id)" class="flex-1 py-2 text-xs font-bold text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors">Set Default</button>
                    <button v-if="!curr.is_default" @click="deleteCurrency(curr.id)" class="p-2 text-danger-400 hover:text-danger-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">{{ editingCurrency ? 'Edit' : 'Add' }} Currency</h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ISO Code</label>
                            <input v-model="form.code" type="text" placeholder="USD" :disabled="editingCurrency" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none disabled:opacity-50" required />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Symbol</label>
                            <input v-model="form.symbol" type="text" placeholder="$" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
                        <input v-model="form.name" type="text" placeholder="US Dollar" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Exchange Rate (vs Default)</label>
                            <input v-model="form.exchange_rate" type="number" step="0.000001" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Decimals</label>
                            <input v-model="form.decimal_places" type="number" min="0" max="5" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                        </div>
                    </div>
                    <div v-if="editingCurrency" class="pt-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 group-hover:text-gray-900">Active</span>
                        </label>
                    </div>
                    <div class="pt-4">
                        <button type="submit" :disabled="form.processing" class="w-full py-3 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            {{ form.processing ? 'Processing...' : (editingCurrency ? 'Update Currency' : 'Add Currency') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
