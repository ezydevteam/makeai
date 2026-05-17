<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    providerStats: Record<string, {
        name: string,
        key_count: number,
        model_count: number
    }>,
    globalSettings: {
        default_provider: string,
        max_tokens: number,
        show_tool_credit_costs: boolean
    }
}>();

const form = useForm({
    default_provider: props.globalSettings.default_provider,
    max_tokens: props.globalSettings.max_tokens,
    show_tool_credit_costs: props.globalSettings.show_tool_credit_costs,
});

const saveSettings = () => {
    form.post(route('admin.ai.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="AI Management — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">AI Management</h1>
            <p class="text-sm text-gray-500 mt-1">Manage API keys, model costs, and global AI settings.</p>
        </div>

        <!-- Providers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div v-for="(stat, slug) in providerStats" :key="slug" class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:border-primary-500/50 transition-all group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center text-gray-900 font-bold group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                        {{ stat.name.charAt(0) }}
                    </div>
                    <div class="flex gap-2">
                        <span :class="stat.key_count > 0 ? 'bg-success-50 text-success-600' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase">
                            {{ stat.key_count }} KEYS
                        </span>
                    </div>
                </div>
                
                <h3 class="text-lg font-bold text-gray-900 mb-1">{{ stat.name }}</h3>
                <p class="text-sm text-gray-500 mb-5">{{ stat.model_count }} active models configured.</p>
                
                <Link :href="route('admin.ai.provider', { slug })" class="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700">
                    Manage Provider
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" /></svg>
                </Link>
            </div>
        </div>

        <!-- Global Settings Card -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-900">Global AI Settings</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Provider</label>
                        <select v-model="form.default_provider" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:border-primary-500 focus:outline-none">
                            <option v-for="(stat, slug) in providerStats" :key="slug" :value="slug">{{ stat.name }}</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">The provider used when no specific model is requested.</p>
                        <p v-if="form.errors.default_provider" class="text-xs text-red-600 mt-1">{{ form.errors.default_provider }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Global Max Tokens</label>
                        <input type="number" v-model="form.max_tokens" min="1" max="128000" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:border-primary-500 focus:outline-none" />
                        <p class="text-xs text-gray-500 mt-2">Hard limit for output tokens per request across all models.</p>
                        <p v-if="form.errors.max_tokens" class="text-xs text-red-600 mt-1">{{ form.errors.max_tokens }}</p>
                    </div>
                    <div class="md:col-span-2 border border-gray-100 rounded-xl p-4 bg-gray-50/50">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                v-model="form.show_tool_credit_costs"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Show credit costs on tool pages</span>
                                <span class="block text-xs text-gray-500 mt-1">Controls estimated cost, user balance, and actual used credits after generation.</span>
                            </span>
                        </label>
                        <p v-if="form.errors.show_tool_credit_costs" class="text-xs text-red-600 mt-1">{{ form.errors.show_tool_credit_costs }}</p>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end">
                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="saveSettings"
                        class="px-5 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors text-sm font-medium disabled:opacity-60"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Global Settings' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
