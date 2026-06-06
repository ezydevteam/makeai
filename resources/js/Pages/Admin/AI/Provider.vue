<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, reactive } from 'vue';
import { useTranslate } from '@/Composables/useTranslate';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    provider: { slug: string, name: string },
    keys: Array<any>,
    models: Array<any>
}>();

const showKeyModal = ref(false);
const { t } = useTranslate();
const { formatDateTime } = useDateFormat();
const keyForm = useForm({
    api_key: '',
    label: ''
});

const savingModels = reactive<Record<number, boolean>>({});
const savedModels = reactive<Record<number, boolean>>({});

const submitKey = () => {
    keyForm.post(route('admin.ai.key.store', { provider: props.provider.slug }), {
        onSuccess: () => {
            showKeyModal.value = false;
            keyForm.reset();
        }
    });
};

const updateModel = (model: any) => {
    savingModels[model.id] = true;

    useForm({
        is_active: model.is_active,
        cost_input_1k: model.cost_input_1k,
        cost_output_1k: model.cost_output_1k,
        credits_per_1k: model.credits_per_1k,
        max_tokens: model.max_tokens,
    }).post(route('admin.ai.model.update', { model: model.id }), {
        onSuccess: () => {
            savingModels[model.id] = false;
            savedModels[model.id] = true;
            setTimeout(() => { savedModels[model.id] = false }, 2000);
        },
        onError: () => {
            savingModels[model.id] = false;
        },
    });
};

const deleteKey = (id: number) => {
    if (confirm(t('Delete this API key?'))) {
        useForm({}).delete(route('admin.ai.key.delete', { key: id }));
    }
};

const typeBadge = (type: string) => {
    const map: Record<string, string> = {
        chat: 'bg-blue-50 text-blue-600',
        embedding: 'bg-violet-50 text-violet-600',
        reranking: 'bg-amber-50 text-amber-600',
        audio: 'bg-pink-50 text-pink-600',
    };
    return map[type] ?? 'bg-gray-100 text-gray-500';
};
</script>

<template>
    <Head :title="t(':provider — AI Management', { provider: provider.name })" />

    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
            <Link
                :href="route('admin.ai.index')"
                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:text-gray-900 hover:border-gray-300 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ provider.name }}</h1>
                <p class="text-sm text-gray-500">{{ t('Configure API keys and model-specific parameters.') }}</p>
            </div>
        </div>

        <!-- API Keys Section -->
        <div class="mb-10">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">{{ t('API Keys') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ t('Load-balanced across all active keys.') }}</p>
                </div>
                <button
                    @click="showKeyModal = true"
                    class="px-4 py-2 bg-[#1F75FE] text-white text-sm font-medium rounded-lg hover:bg-[#1a65e0] active:bg-[#1554c0] transition-colors"
                >
                    {{ t('Add Key') }}
                </button>
            </div>

            <!-- Keys Table -->
            <div class="rounded-xl border border-gray-200 overflow-hidden bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('Label') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('Key') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('Status') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('Usage') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('Last Used') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="key in keys" :key="key.id" class="hover:bg-blue-50/30 transition-colors">
                            <td class="px-5 py-3.5 font-medium text-gray-900">{{ key.label || t('Unnamed Key') }}</td>
                            <td class="px-5 py-3.5 text-gray-400 font-mono text-xs">••••{{ key.api_key.slice(-4) }}</td>
                            <td class="px-5 py-3.5">
                                <span
                                    v-if="key.is_active"
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-green-50 text-green-600 text-[11px] font-semibold rounded-full"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500" />
                                    {{ t('Active') }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-gray-100 text-gray-500 text-[11px] font-semibold rounded-full"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400" />
                                    {{ t('Inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ key.usage_count.toLocaleString() }}</td>
                            <td class="px-5 py-3.5 text-gray-400 text-xs">
                                {{ key.last_used_at ? formatDateTime(key.last_used_at) : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    @click="deleteKey(key.id)"
                                    class="text-xs font-medium text-gray-400 hover:text-red-500 transition-colors"
                                >
                                    {{ t('Delete') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!keys.length">
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                    </svg>
                                    <span class="text-sm text-gray-400 mt-2">{{ t('No API keys configured for this provider.') }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Models Section -->
        <div>
            <div class="mb-4">
                <h3 class="text-base font-semibold text-gray-900">{{ t('Model Settings & Costs') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ t('Per-model pricing, limits, and activation.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <div
                    v-for="model in models"
                    :key="model.id"
                    class="bg-white border border-gray-200 rounded-xl px-5 py-4 shadow-sm flex flex-wrap items-center gap-4 transition-colors hover:border-gray-300"
                >
                    <!-- Model info -->
                    <div class="flex-1 min-w-[180px]">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-semibold text-gray-900">{{ model.name }}</h4>
                            <span
                                class="px-1.5 py-px text-[10px] font-medium rounded uppercase"
                                :class="typeBadge(model.type)"
                            >
                                {{ model.type }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 font-mono mt-0.5">{{ model.slug }}</p>
                    </div>

                    <!-- Inputs -->
                    <div class="flex items-center gap-3">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-1">Input $ /1K</label>
                            <input
                                type="number"
                                step="0.000001"
                                v-model="model.cost_input_1k"
                                class="w-24 px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 outline-none transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-1">Output $ /1K</label>
                            <input
                                type="number"
                                step="0.000001"
                                v-model="model.cost_output_1k"
                                class="w-24 px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 outline-none transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-1">Credits /1K</label>
                            <input
                                type="number"
                                v-model="model.credits_per_1k"
                                class="w-16 px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 outline-none transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-1">Max Tokens</label>
                            <input
                                type="number"
                                v-model="model.max_tokens"
                                class="w-20 px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 outline-none transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Toggle + Save -->
                    <div class="flex items-center gap-3 ml-auto">
                        <button
                            type="button"
                            @click="model.is_active = !model.is_active"
                            :class="model.is_active ? 'bg-[#1F75FE]' : 'bg-gray-300'"
                            class="relative w-11 h-6 rounded-full transition-colors shrink-0 cursor-pointer"
                        >
                            <span
                                class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                                :class="model.is_active ? 'translate-x-[22px] left-0.5' : 'left-0.5'"
                            />
                        </button>
                        <button
                            @click="updateModel(model)"
                            :disabled="savingModels[model.id]"
                            class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-all min-w-[66px]"
                            :class="savedModels[model.id]
                                ? 'bg-green-50 text-green-600 border border-green-200'
                                : 'bg-[#111] text-white hover:bg-[#1a1a1a] disabled:opacity-50'"
                        >
                            <span v-if="savingModels[model.id]">{{ t('Saving') }}</span>
                            <span v-else-if="savedModels[model.id]">{{ t('Saved') }}</span>
                            <span v-else>{{ t('Save') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="!models.length" class="bg-white border border-gray-200 rounded-xl p-10 text-center shadow-sm">
                    <svg class="w-10 h-10 text-gray-300 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                    </svg>
                    <p class="text-sm text-gray-400 mt-3">{{ t('No models configured. Run the seeder or add models manually.') }}</p>
                </div>
            </div>
        </div>

        <!-- Add Key Modal -->
        <Teleport to="body">
            <div v-if="showKeyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="showKeyModal = false" />
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden" style="animation: modal-in 0.18s ease">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">{{ t('Add API Key') }} — {{ provider.name }}</h3>
                        <button
                            @click="showKeyModal = false"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitKey" class="p-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('API Key') }}</label>
                            <input
                                type="password"
                                v-model="keyForm.api_key"
                                placeholder="sk-..."
                                class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none transition-colors placeholder:text-gray-400"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Label') }} <span class="text-gray-400 font-normal">({{ t('optional') }})</span></label>
                            <input
                                type="text"
                                v-model="keyForm.label"
                                :placeholder="t('e.g. Main Account, Backup')"
                                class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none transition-colors placeholder:text-gray-400"
                            />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button
                                type="button"
                                @click="showKeyModal = false"
                                class="flex-1 py-2.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                {{ t('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="keyForm.processing"
                                class="flex-1 py-2.5 bg-[#1F75FE] text-white text-sm font-medium rounded-lg hover:bg-[#1a65e0] transition-colors disabled:opacity-50"
                            >
                                {{ keyForm.processing ? t('Adding...') : t('Add Key') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@keyframes modal-in {
    from {
        opacity: 0;
        transform: scale(0.96) translateY(8px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>
