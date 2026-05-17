<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref } from 'vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    provider: { slug: string, name: string },
    keys: Array<any>,
    models: Array<any>
}>();

const showKeyModal = ref(false);
const keyForm = useForm({
    api_key: '',
    label: ''
});

const submitKey = () => {
    keyForm.post(route('admin.ai.key.store', { provider: props.provider.slug }), {
        onSuccess: () => {
            showKeyModal.value = false;
            keyForm.reset();
        }
    });
};

const updateModel = (model: any) => {
    useForm({
        is_active: model.is_active,
        cost_input_1k: model.cost_input_1k,
        cost_output_1k: model.cost_output_1k,
        credits_per_1k: model.credits_per_1k,
        max_tokens: model.max_tokens,
    }).post(route('admin.ai.model.update', { model: model.id }));
};

const deleteKey = (id: number) => {
    if (confirm('Delete this API key?')) {
        useForm({}).delete(route('admin.ai.key.delete', { key: id }));
    }
};
</script>

<template>
    <Head :title="`${provider.name} — AI Management`" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center gap-3 mb-8">
            <Link :href="route('admin.ai.index')" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-500 hover:text-gray-900 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ provider.name }} Management</h1>
                <p class="text-sm text-gray-500">Configure API keys and model-specific parameters.</p>
            </div>
        </div>

        <!-- API Keys Section -->
        <div class="mb-10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-gray-900">API Keys (Load Balanced)</h3>
                <button @click="showKeyModal = true" class="px-4 py-2 bg-primary-600 text-white text-xs font-bold rounded-lg hover:bg-primary-500 transition-colors">
                    ADD NEW KEY
                </button>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-3 font-semibold text-gray-700">Label</th>
                            <th class="px-6 py-3 font-semibold text-gray-700">Key</th>
                            <th class="px-6 py-3 font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-3 font-semibold text-gray-700">Usage</th>
                            <th class="px-6 py-3 font-semibold text-gray-700">Last Used</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="key in keys" :key="key.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ key.label || 'Unnamed Key' }}</td>
                            <td class="px-6 py-4 text-gray-500">••••••••{{ key.api_key.slice(-4) }}</td>
                            <td class="px-6 py-4">
                                <span v-if="key.is_active" class="px-2 py-0.5 bg-success-50 text-success-600 text-[10px] font-bold rounded-full">ACTIVE</span>
                                <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-bold rounded-full">INACTIVE</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ key.usage_count }} reqs</td>
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ key.last_used_at ? new Date(key.last_used_at).toLocaleString() : 'Never' }}</td>
                            <td class="px-6 py-4 text-right">
                                <button @click="deleteKey(key.id)" class="text-danger-500 hover:text-danger-600 font-medium">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!keys.length">
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500 italic">No API keys configured for this provider.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Models Section -->
        <div>
            <h3 class="font-bold text-gray-900 mb-4">Model Settings & Costs</h3>
            <div class="grid grid-cols-1 gap-4">
                <div v-for="model in models" :key="model.id" class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex flex-wrap items-center gap-6">
                    <div class="flex-1 min-w-[200px]">
                        <h4 class="font-bold text-gray-900">{{ model.name }}</h4>
                        <p class="text-xs text-gray-500 font-mono">{{ model.slug }}</p>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Input $ (1K)</label>
                            <input type="number" step="0.000001" v-model="model.cost_input_1k" class="w-24 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-900 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Output $ (1K)</label>
                            <input type="number" step="0.000001" v-model="model.cost_output_1k" class="w-24 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-900 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Credits (1K)</label>
                            <input type="number" v-model="model.credits_per_1k" class="w-16 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-900 focus:outline-none" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3 ml-auto">
                        <button @click="model.is_active = !model.is_active" :class="model.is_active ? 'bg-success-500' : 'bg-gray-200'" class="relative w-10 h-5 rounded-full transition-colors">
                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform" :class="model.is_active ? 'translate-x-5' : 'translate-x-0'"></span>
                        </button>
                        <button @click="updateModel(model)" class="px-3 py-1.5 bg-gray-900 text-white text-xs font-bold rounded-lg hover:bg-gray-800 transition-colors">
                            SAVE
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Key Modal -->
        <div v-if="showKeyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">Add API Key</h3>
                    <button @click="showKeyModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitKey" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <input type="password" v-model="keyForm.api_key" placeholder="sk-..." class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Label (Optional)</label>
                        <input type="text" v-model="keyForm.label" placeholder="Main Account / Backup" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                    </div>
                    <div class="pt-2">
                        <button type="submit" :disabled="keyForm.processing" class="w-full py-2.5 bg-primary-600 text-white rounded-lg font-bold hover:bg-primary-500 transition-colors disabled:opacity-50">
                            {{ keyForm.processing ? 'Adding...' : 'ADD API KEY' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
