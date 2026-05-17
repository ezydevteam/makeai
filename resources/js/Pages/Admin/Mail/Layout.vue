<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    layout: string
}>();

const form = useForm({
    layout: props.layout,
});

const submit = () => {
    form.post(route('admin.mail.layout.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Email Layout — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Email Layout Editor</h1>
            <p class="text-sm text-gray-500 mt-1">Design the global HTML/CSS wrapper for all system communications.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <form @submit.prevent="submit" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-8">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Master Layout</label>
                        <RichEditor v-model="form.layout" />
                        <p class="text-[10px] text-gray-400 mt-4 italic">CRITICAL: You MUST include the <code class="text-primary-600 font-black">{content}</code> placeholder where the template body should be injected.</p>
                    </div>

                    <div class="pt-4 flex items-center justify-between">
                        <button type="submit" :disabled="form.processing" class="bg-primary-600 hover:bg-primary-500 text-white px-8 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20">
                            Save Global Layout
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <!-- Branding Card -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-4">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs border-b border-gray-50 pb-4">Branding Tips</h3>
                    <ul class="space-y-3">
                        <li class="flex gap-3 text-xs text-gray-500">
                            <span class="text-primary-600 font-black">01</span>
                            Include your logo at the top using an absolute URL.
                        </li>
                        <li class="flex gap-3 text-xs text-gray-500">
                            <span class="text-primary-600 font-black">02</span>
                            Add social links and unsubscribe info in the footer.
                        </li>
                        <li class="flex gap-3 text-xs text-gray-500">
                            <span class="text-primary-600 font-black">03</span>
                            Test responsiveness on multiple mobile devices.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
