<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useTranslate } from '@/Composables/useTranslate';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    filters: {
        search?: string,
        status?: string
    },
    logs: {
        data: Array<{
            id: number,
            template_slug: string,
            recipient_email: string,
            subject: string,
            status: string,
            error_message: string,
            sent_at: string
        }>,
        links: Array<any>
    }
}>();

const filterForm = useForm({
    search: props.filters.search || '',
    status: props.filters.status || '',
});
const { t } = useTranslate();
const { formatDateTime } = useDateFormat();

const applyFilters = () => {
    router.get(route('admin.mail.logs.index'), {
        search: filterForm.search,
        status: filterForm.status,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head :title="$t('Mail Logs — Admin')" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ t('Mail Delivery Logs') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ t('Monitor all outgoing communications and troubleshoot delivery issues.') }}</p>
        </div>

        <form @submit.prevent="applyFilters" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 mb-6 grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-4">
            <input v-model="filterForm.search" type="search" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" :placeholder="$t('Search recipient, subject, or template')">
            <select v-model="filterForm.status" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                <option value="">{{ t('All statuses') }}</option>
                <option value="sent">{{ t('Sent') }}</option>
                <option value="failed">{{ t('Failed') }}</option>
                <option value="bounced">{{ t('Bounced') }}</option>
            </select>
            <button type="submit" class="bg-primary-600 hover:bg-primary-500 text-white px-6 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20">
                {{ t('Filter') }}
            </button>
        </form>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-6">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Recipient') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Subject') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Template') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ t('Status') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">{{ t('Sent At') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900 text-xs">{{ log.recipient_email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs text-gray-600 truncate max-w-xs">{{ log.subject }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[10px] font-mono text-gray-400">{{ log.template_slug || t('Manual') }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <span :class="{
                                    'bg-success-100 text-success-600': log.status === 'sent',
                                    'bg-danger-100 text-danger-600': log.status === 'failed',
                                    'bg-amber-100 text-amber-600': log.status === 'bounced'
                                }" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest rounded-lg">
                                    {{ log.status }}
                                </span>
                                <div v-if="log.error_message" class="text-[8px] text-danger-500 font-mono truncate max-w-[100px]" :title="log.error_message">
                                    {{ log.error_message }}
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right text-[10px] text-gray-400 font-mono">
                            {{ formatDateTime(log.sent_at) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link v-if="log.template_slug" :href="route('admin.mail.logs.resend', log.id)" method="post" as="button" preserve-scroll class="px-3 py-1.5 bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-primary-600 transition-all shadow-md shadow-gray-900/10">
                                {{ t('Resend') }}
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-400">
                            {{ t('No mail logs found.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="logs.links" />
    </div>
</template>
