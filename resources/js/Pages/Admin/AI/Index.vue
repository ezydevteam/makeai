<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTranslate } from '@/Composables/useTranslate'
import { computed, watch } from 'vue';

defineOptions({ layout: AdminLayout });

interface ProviderModel {
    slug: string;
    name: string;
}

const props = defineProps<{
    providerStats: Record<string, {
        name: string,
        key_count: number,
        model_count: number
    }>,
    providerModels: Record<string, ProviderModel[]>,
    globalSettings: {
        default_provider: string,
        default_model: string,
        fallback_provider: string,
        fallback_model: string,
        max_tokens: number,
        show_tool_credit_costs: boolean
    }
}>();

const form = useForm({
    default_provider: props.globalSettings.default_provider,
    default_model: props.globalSettings.default_model,
    fallback_provider: props.globalSettings.fallback_provider || '',
    fallback_model: props.globalSettings.fallback_model || '',
    max_tokens: props.globalSettings.max_tokens,
    show_tool_credit_costs: props.globalSettings.show_tool_credit_costs,
});

// Models available for the currently selected provider
const availableModels = computed<ProviderModel[]>(() => {
    return props.providerModels[form.default_provider] ?? [];
});

// Models available for the fallback provider
const fallbackModels = computed<ProviderModel[]>(() => {
    if (!form.fallback_provider) return [];
    return props.providerModels[form.fallback_provider] ?? [];
});

// When provider changes, auto-select the first available model
watch(() => form.default_provider, (newProvider) => {
    const models = props.providerModels[newProvider] ?? [];
    if (models.length > 0 && !models.some(m => m.slug === form.default_model)) {
        form.default_model = models[0].slug;
    }
});

// When fallback provider changes, auto-select the first available model
watch(() => form.fallback_provider, (newProvider) => {
    if (!newProvider) {
        form.fallback_model = '';
        return;
    }
    const models = props.providerModels[newProvider] ?? [];
    if (models.length > 0 && !models.some(m => m.slug === form.fallback_model)) {
        form.fallback_model = models[0].slug;
    }
});

const saveSettings = () => {
    form.post(route('admin.ai.settings.update'), { preserveScroll: true });
};

const { t } = useTranslate()

const providerIcons: Record<string, string> = {
    openai: 'O', anthropic: 'A', google: 'G', xai: 'X',
    deepseek: 'D', openrouter: 'R', groq: 'Q', mistral: 'M',
    ollama: 'L', bedrock: 'B', cohere: 'C', eleven: 'E',
    jina: 'J', voyageai: 'V', perplexity: 'P', together: 'T', replicate: 'R2',
};

const providerColors: Record<string, string> = {
    openai: 'bg-emerald-50 text-emerald-600', anthropic: 'bg-amber-50 text-amber-600',
    google: 'bg-blue-50 text-blue-600', xai: 'bg-gray-100 text-gray-700',
    deepseek: 'bg-indigo-50 text-indigo-600', openrouter: 'bg-violet-50 text-violet-600',
    groq: 'bg-orange-50 text-orange-600', mistral: 'bg-cyan-50 text-cyan-600',
    ollama: 'bg-teal-50 text-teal-600', bedrock: 'bg-rose-50 text-rose-600',
    cohere: 'bg-sky-50 text-sky-600', eleven: 'bg-pink-50 text-pink-600',
    jina: 'bg-fuchsia-50 text-fuchsia-600', voyageai: 'bg-purple-50 text-purple-600',
    perplexity: 'bg-yellow-50 text-yellow-700',
    together: 'bg-green-50 text-green-600', replicate: 'bg-red-50 text-red-600',
};
</script>

<template>
    <Head :title="t('AI Management — Admin')" />

    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ t('AI Management') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ t('Manage API keys, model costs, and global AI settings.') }}</p>
        </div>

        <!-- Provider Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
            <Link
                v-for="(stat, slug) in providerStats"
                :key="slug"
                :href="route('admin.ai.provider', { slug })"
                class="stat-card group cursor-pointer"
            >
                <div class="flex items-center justify-between mb-3">
                    <div
                        class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold"
                        :class="providerColors[slug] ?? 'bg-gray-100 text-gray-600'"
                    >
                        {{ providerIcons[slug] ?? slug.charAt(0).toUpperCase() }}
                    </div>
                    <span
                        v-if="stat.key_count > 0"
                        class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-green-50 text-green-600"
                    >
                        {{ stat.key_count }} {{ stat.key_count === 1 ? t('KEY') : t('KEYS') }}
                    </span>
                </div>

                <h3 class="text-sm font-semibold text-gray-900 mb-0.5">{{ stat.name }}</h3>
                <p class="text-xs text-gray-500">
                    {{ stat.model_count === 0 ? t('No models') : t(':count model(s)', { count: stat.model_count }) }}
                </p>
            </Link>
        </div>

        <!-- Global Settings -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">{{ t('Global AI Settings') }}</h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Default Provider -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Default Provider') }}</label>
                        <select
                            v-model="form.default_provider"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20width=%2216%22%20height=%2216%22%20viewBox=%220%200%2024%2024%22%3E%3Cpath%20fill=%22%239ca3af%22%20d=%22M7%2010l5%205%205-5z%22/%3E%3C/svg%3E')] bg-[length:16px] bg-[right_0.75rem_center] bg-no-repeat pr-10"
                        >
                            <option v-for="(stat, slug) in providerStats" :key="slug" :value="slug">
                                {{ stat.name }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Used when no specific model is requested.') }}</p>
                        <p v-if="form.errors.default_provider" class="text-xs text-red-500 mt-1">{{ form.errors.default_provider }}</p>
                    </div>

                    <!-- Default Model -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Default Model') }}</label>
                        <select
                            v-model="form.default_model"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20width=%2216%22%20height=%2216%22%20viewBox=%220%200%2024%2024%22%3E%3Cpath%20fill=%22%239ca3af%22%20d=%22M7%2010l5%205%205-5z%22/%3E%3C/svg%3E')] bg-[length:16px] bg-[right_0.75rem_center] bg-no-repeat pr-10"
                            :disabled="availableModels.length === 0"
                        >
                            <option v-if="availableModels.length === 0" value="" disabled>
                                {{ t('No models configured for this provider') }}
                            </option>
                            <option v-for="model in availableModels" :key="model.slug" :value="model.slug">
                                {{ model.name }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('The model used for tools set to Inherit/Default.') }}</p>
                        <p v-if="form.errors.default_model" class="text-xs text-red-500 mt-1">{{ form.errors.default_model }}</p>
                    </div>

                    <!-- Max Tokens -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Global Max Tokens') }}</label>
                        <input
                            type="number"
                            v-model="form.max_tokens"
                            min="1"
                            max="128000"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none"
                        />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Hard limit for output tokens per request.') }}</p>
                        <p v-if="form.errors.max_tokens" class="text-xs text-red-500 mt-1">{{ form.errors.max_tokens }}</p>
                    </div>
                </div>

                <!-- Fallback Provider/Model -->
                <div class="p-4 bg-amber-50/50 rounded-xl border border-amber-100">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span class="text-sm font-semibold text-gray-800">{{ t('Fallback Provider & Model') }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">{{ t('Automatically retries with this provider if the primary fails (rate limit, quota exceeded, server error).') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Fallback Provider -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('Fallback Provider') }}</label>
                            <select
                                v-model="form.fallback_provider"
                                class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20width=%2216%22%20height=%2216%22%20viewBox=%220%200%2024%2024%22%3E%3Cpath%20fill=%22%239ca3af%22%20d=%22M7%2010l5%205%205-5z%22/%3E%3C/svg%3E')] bg-[length:16px] bg-[right_0.75rem_center] bg-no-repeat pr-10"
                            >
                                <option value="">{{ t('— None (no fallback) —') }}</option>
                                <option v-for="(stat, slug) in providerStats" :key="slug" :value="slug">
                                    {{ stat.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.fallback_provider" class="text-xs text-red-500 mt-1">{{ form.errors.fallback_provider }}</p>
                        </div>

                        <!-- Fallback Model -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('Fallback Model') }}</label>
                            <select
                                v-model="form.fallback_model"
                                :disabled="!form.fallback_provider || fallbackModels.length === 0"
                                class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20width=%2216%22%20height=%2216%22%20viewBox=%220%200%2024%2024%22%3E%3Cpath%20fill=%22%239ca3af%22%20d=%22M7%2010l5%205%205-5z%22/%3E%3C/svg%3E')] bg-[length:16px] bg-[right_0.75rem_center] bg-no-repeat pr-10 disabled:bg-gray-50 disabled:text-gray-400"
                            >
                                <option v-if="!form.fallback_provider" value="" disabled>{{ t('Select a provider first') }}</option>
                                <option v-else-if="fallbackModels.length === 0" value="" disabled>{{ t('No models configured') }}</option>
                                <option v-for="model in fallbackModels" :key="model.slug" :value="model.slug">
                                    {{ model.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.fallback_model" class="text-xs text-red-500 mt-1">{{ form.errors.fallback_model }}</p>
                        </div>
                    </div>
                </div>

                <!-- Show credits toggle -->
                <div class="flex items-start gap-3 p-4 bg-gray-50/50 rounded-xl border border-gray-100">
                    <button
                        type="button"
                        @click="form.show_tool_credit_costs = !form.show_tool_credit_costs"
                        :class="form.show_tool_credit_costs ? 'bg-[#1F75FE]' : 'bg-gray-300'"
                        class="relative w-11 h-6 rounded-full transition-colors shrink-0 mt-0.5 cursor-pointer"
                    >
                        <span
                            class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                            :class="form.show_tool_credit_costs ? 'translate-x-[22px] left-0.5' : 'left-0.5'"
                        />
                    </button>
                    <div>
                        <span class="block text-sm font-medium text-gray-800">{{ t('Show credit costs on tool pages') }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            {{ t('Displays estimated cost, user balance, and actual used credits after generation.') }}
                        </span>
                    </div>
                    <p v-if="form.errors.show_tool_credit_costs" class="text-xs text-red-500 mt-1">{{ form.errors.show_tool_credit_costs }}</p>
                </div>

                <!-- Save -->
                <div class="flex justify-end pt-2">
                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="saveSettings"
                        class="px-5 py-2.5 bg-[#111] text-white text-sm font-medium rounded-lg hover:bg-[#1a1a1a] transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? t('Saving...') : t('Save Settings') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.06), 0 1px 2px rgb(0 0 0 / 0.04);
    transition: all 0.18s ease;
    text-decoration: none;
}
.stat-card:hover {
    border-color: #1F75FE;
    box-shadow: 0 4px 12px rgb(0 0 0 / 0.08), 0 0 20px rgb(31 117 254 / 0.12);
    transform: translateY(-1px);
}
</style>
