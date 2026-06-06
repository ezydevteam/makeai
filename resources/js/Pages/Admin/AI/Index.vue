<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTranslate } from '@/Composables/useTranslate'

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
