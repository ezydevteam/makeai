<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout });

const { t } = useTranslate()

defineProps<{
    templates: Array<{
        id: number,
        slug: string,
        name: string,
        subject: string,
        category: string,
        is_active: boolean,
        is_system: boolean,
        requires_pro: boolean
    }>
}>();
</script>

<template>
    <Head :title="t('Mail Templates — Admin')" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('Mail Templates') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ t('Manage system notifications and custom email communications.') }}</p>
            </div>
            <Link :href="route('admin.mail.templates.create')" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-500 text-white px-5 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20">
                {{ t('New Template') }}
            </Link>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Template Name') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Category') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Subject') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ t('Status') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="template in templates" :key="template.id" class="hover:bg-gray-50/50 transition-colors group">
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900">{{ template.name }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="text-[10px] font-mono text-gray-400">{{ template.slug }}</div>
                                <span :class="template.is_system ? 'bg-blue-50 text-blue-600' : 'bg-violet-50 text-violet-600'" class="px-2 py-0.5 text-[9px] font-black uppercase tracking-widest rounded-lg">
                                    {{ template.is_system ? t('System') : t('Custom') }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-lg">
                                {{ template.category }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs text-gray-600 max-w-xs truncate">{{ template.subject }}</div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span :class="template.is_active ? 'bg-success-100 text-success-600' : 'bg-gray-100 text-gray-400'" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest rounded-lg">
                                {{ template.is_active ? t('Active') : t('Disabled') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="route('admin.mail.templates.edit', template.id)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-primary-600 transition-all shadow-md shadow-gray-900/10">
                                {{ t('Edit Template') }}
                            </Link>
                            <Link v-if="!template.is_system" :href="route('admin.mail.templates.delete', template.id)" method="delete" as="button" preserve-scroll class="ml-2 inline-flex items-center gap-2 px-3 py-1.5 bg-danger-500 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-danger-600 transition-all shadow-md shadow-danger-500/10">
                                {{ t('Delete') }}
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="templates.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">
                            {{ t('No mail templates found.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
