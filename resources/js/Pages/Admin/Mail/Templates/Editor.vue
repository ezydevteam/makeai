<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    template: {
        id: number,
        slug: string,
        name: string,
        subject: string,
        content: string,
        is_active: boolean,
        category: string
    }
}>();

const form = useForm({
    name: props.template.name,
    subject: props.template.subject,
    content: props.template.content,
    is_active: props.template.is_active,
});

const submit = () => {
    form.post(route('admin.mail.templates.update', props.template.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit Template: ${template.name} — Admin`" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-2xl font-bold text-gray-900">Edit Template</h1>
                    <span class="px-2 py-1 bg-primary-50 text-primary-700 text-[10px] font-black uppercase tracking-widest rounded-lg">
                        {{ template.category }}
                    </span>
                </div>
                <p class="text-sm text-gray-500">Slug: <code class="bg-gray-100 px-1 rounded text-primary-600">{{ template.slug }}</code></p>
            </div>
            <button @click="submit" :disabled="form.processing" class="bg-primary-600 hover:bg-primary-500 text-white px-8 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20">
                Save Changes
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <form @submit.prevent="submit" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Display Name</label>
                            <input v-model="form.name" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Status</label>
                            <select v-model="form.is_active" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                                <option :value="true">Active</option>
                                <option :value="false">Disabled</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Email Subject</label>
                        <input v-model="form.subject" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all font-medium">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Email Content</label>
                        <RichEditor v-model="form.content" />
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <!-- Helper Card -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs border-b border-gray-50 pb-4">Variable Guide</h3>
                    <div class="space-y-4">
                        <div v-for="(desc, tag) in {
                            '{site_name}': 'Name of your platform',
                            '{site_url}': 'Full URL of your site',
                            '{user_name}': 'Full name of recipient',
                            '{otp_code}': 'Verification code (if auth)',
                            '{plan_name}': 'Subscription plan name',
                            '{credits}': 'Credit amount (if top-up)'
                        }" :key="tag" class="flex flex-col">
                            <span class="text-xs font-mono text-primary-600 font-black">{{ tag }}</span>
                            <span class="text-[10px] text-gray-500">{{ desc }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
