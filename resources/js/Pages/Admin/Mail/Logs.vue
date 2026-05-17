<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineOptions({ layout: AdminLayout });

defineProps<{
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
</script>

<template>
    <Head title="Mail Logs — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Mail Delivery Logs</h1>
            <p class="text-sm text-gray-500 mt-1">Monitor all outgoing communications and troubleshoot delivery issues.</p>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-6">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipient</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Subject</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Template</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Sent At</th>
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
                            <span class="text-[10px] font-mono text-gray-400">{{ log.template_slug || 'Manual' }}</span>
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
                            {{ new Date(log.sent_at).toLocaleString() }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="logs.links" />
    </div>
</template>
