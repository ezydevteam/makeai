<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Tooltip from '@/Components/UI/Tooltip.vue';
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
const deleteKeyId = ref<number | null>(null);
const { t } = useTranslate();
const { formatDateTime } = useDateFormat();
const keyForm = useForm({
    api_key: '',
    label: ''
});

const savingModels = reactive<Record<number, boolean>>({});
const savedModels = reactive<Record<number, boolean>>({});

const openKeyModal = () => {
    keyForm.reset();
    keyForm.clearErrors();
    showKeyModal.value = true;
};

const closeKeyModal = () => {
    showKeyModal.value = false;
    keyForm.reset();
    keyForm.clearErrors();
};

const submitKey = () => {
    keyForm.post(route('admin.ai.key.store', { provider: props.provider.slug }), {
        onSuccess: () => {
            closeKeyModal();
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

const deleteKey = () => {
    if (deleteKeyId.value === null) {
        return;
    }

    useForm({}).delete(route('admin.ai.key.delete', { key: deleteKeyId.value }), {
        onFinish: () => {
            deleteKeyId.value = null;
        },
    });
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
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div>
                    <h1 class="font-heading text-2xl font-bold text-gray-900 dark:text-white">{{ provider.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure API keys, model pricing, token limits, and model availability for this provider.') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 self-start">
                <Link
                    :href="route('admin.ai.index')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-500 transition-colors hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:text-primary-300"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>
                <button
                    type="button"
                    @click="openKeyModal"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-600"
                >
                    <i class="ti ti-key text-base"></i>
                    <span>{{ t('Add Key') }}</span>
                </button>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('API Keys') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Keys are rotated across active requests for this provider.') }}</p>
                </div>
                <div class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                    {{ t(':count key(s)', { count: keys.length }) }}
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-surface-700 dark:bg-surface-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-surface-700 dark:bg-surface-800/70">
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Label') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Key') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Usage') }}</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Last Used') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                        <tr v-for="key in keys" :key="key.id" class="transition-colors hover:bg-primary-50/40 dark:hover:bg-surface-800/60">
                            <td class="px-5 py-3.5 font-medium text-gray-900 dark:text-white">{{ key.label || t('Unnamed Key') }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-400 dark:text-gray-500">••••{{ (key.api_key || '').slice(-4) }}</td>
                            <td class="px-5 py-3.5">
                                <span
                                    v-if="key.is_active"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-600 dark:bg-green-900/20 dark:text-green-300"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500" />
                                    {{ t('Active') }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500 dark:bg-surface-800 dark:text-gray-300"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400" />
                                    {{ t('Inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-500 dark:text-gray-400">{{ key.usage_count.toLocaleString() }}</td>
                            <td class="px-5 py-3.5 text-xs text-gray-400 dark:text-gray-500">
                                {{ key.last_used_at ? formatDateTime(key.last_used_at) : t('Never') }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Tooltip :content="t('Delete key')" placement="top">
                                    <button
                                        type="button"
                                        @click="deleteKeyId = key.id"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:text-gray-500 dark:hover:bg-red-900/10 dark:hover:text-red-400"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </Tooltip>
                            </td>
                        </tr>
                        <tr v-if="!keys.length" class="dark:bg-surface-900">
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <i class="ti ti-key-off text-3xl text-gray-300 dark:text-gray-600"></i>
                                    <span class="mt-2 text-sm text-gray-400 dark:text-gray-500">{{ t('No API keys configured for this provider.') }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="mb-4">
                    <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Model Settings & Costs') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Adjust pricing, credits, output limits, and availability for each model.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4">
                <div
                    v-for="model in models"
                    :key="model.id"
                    class="rounded-2xl border border-gray-200 bg-gray-50/80 p-5 transition-colors hover:border-primary-300 hover:bg-white dark:border-surface-700 dark:bg-surface-800/70 dark:hover:border-primary-900/40 dark:hover:bg-surface-800"
                >
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ model.name }}</h4>
                            <span
                                class="rounded px-1.5 py-px text-[10px] font-medium uppercase"
                                :class="typeBadge(model.type)"
                            >
                                {{ model.type }}
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-xs text-gray-400 dark:text-gray-500">{{ model.slug }}</p>
                        </div>

                        <div class="flex items-center gap-3 xl:ml-4">
                            <button
                                type="button"
                                @click="model.is_active = !model.is_active"
                                :class="model.is_active ? 'bg-green-600 dark:bg-green-500' : 'bg-gray-300 dark:bg-surface-700'"
                                class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                            >
                                <span
                                    class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                    :class="model.is_active ? 'left-0.5 translate-x-[22px]' : 'left-0.5'"
                                />
                            </button>
                            <button
                                @click="updateModel(model)"
                                :disabled="savingModels[model.id]"
                                class="min-w-[78px] rounded-lg px-3.5 py-2 text-xs font-semibold transition-all"
                                :class="savedModels[model.id]
                                    ? 'border border-green-200 bg-green-50 text-green-600 dark:border-green-900/30 dark:bg-green-900/10 dark:text-green-300'
                                    : 'bg-primary text-white hover:bg-primary-600 disabled:opacity-50'"
                            >
                                <span v-if="savingModels[model.id]">{{ t('Saving') }}</span>
                                <span v-else-if="savedModels[model.id]">{{ t('Saved') }}</span>
                                <span v-else>{{ t('Save') }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase text-gray-400 dark:text-gray-500">{{ t('Input $ /1K') }}</label>
                            <input
                                type="number"
                                step="0.000001"
                                v-model="model.cost_input_1k"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 outline-none transition-colors focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase text-gray-400 dark:text-gray-500">{{ t('Output $ /1K') }}</label>
                            <input
                                type="number"
                                step="0.000001"
                                v-model="model.cost_output_1k"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 outline-none transition-colors focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase text-gray-400 dark:text-gray-500">{{ t('Credits /1K') }}</label>
                            <input
                                type="number"
                                v-model="model.credits_per_1k"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 outline-none transition-colors focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase text-gray-400 dark:text-gray-500">{{ t('Max Tokens') }}</label>
                            <input
                                type="number"
                                v-model="model.max_tokens"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 outline-none transition-colors focus:border-primary-500 focus:ring-1 focus:ring-primary-500/15 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="!models.length" class="rounded-xl border border-gray-200 bg-white p-10 text-center dark:border-surface-700 dark:bg-surface-900">
                    <i class="ti ti-brain text-4xl text-gray-300 dark:text-gray-600"></i>
                    <p class="mt-3 text-sm text-gray-400 dark:text-gray-500">{{ t('No models configured. Run the seeder or add models manually.') }}</p>
                </div>
            </div>
        </div>
        </div>

        <Teleport to="body">
            <div v-if="showKeyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="closeKeyModal" />
                <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-surface-900" style="animation: modal-in 0.18s ease">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-surface-700">
                        <h3 class="font-heading text-base font-semibold text-gray-900 dark:text-white">{{ t('Add API Key') }} - {{ provider.name }}</h3>
                        <button
                            @click="closeKeyModal"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                    <form @submit.prevent="submitKey" class="p-5 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('API Key') }}</label>
                            <input
                                type="text"
                                v-model="keyForm.api_key"
                                placeholder="sk-..."
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-950 dark:text-white dark:placeholder:text-gray-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Label') }} <span class="font-normal text-gray-400">({{ t('optional') }})</span></label>
                            <input
                                type="text"
                                v-model="keyForm.label"
                                :placeholder="t('e.g. Main Account, Backup')"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-950 dark:text-white dark:placeholder:text-gray-500"
                            />
                        </div>
                        <div class="flex gap-3 border-t border-gray-100 pt-4 dark:border-surface-700">
                            <button
                                type="button"
                                @click="closeKeyModal"
                                class="flex-1 rounded-lg border border-gray-200 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800"
                            >
                                {{ t('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="keyForm.processing"
                                class="flex-1 rounded-lg bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-600 disabled:opacity-50"
                            >
                                {{ keyForm.processing ? t('Adding...') : t('Add Key') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <ActionConfirmModal
            :open="deleteKeyId !== null"
            :title="t('Delete API Key')"
            :message="t('This API key will be removed from this provider.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            @confirm="deleteKey"
            @cancel="deleteKeyId = null"
        />
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
