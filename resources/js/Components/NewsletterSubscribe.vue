<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    layout?: 'horizontal' | 'vertical' | 'auto',
    showCard?: boolean
}>();

const layoutClass = props.layout || 'auto';
const withCard = props.showCard !== false;

const form = useForm({
    email: '',
    name: ''
});

const submit = () => {
    form.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => form.reset()
    });
};
</script>

<template>
    <div v-if="withCard" class="bg-primary-600 rounded-3xl p-8 md:p-12 text-white relative overflow-hidden shadow-2xl shadow-primary-500/30">
        <!-- Abstract Decoration -->
        <div class="absolute -top-12 -right-12 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-accent-500/20 rounded-full blur-2xl"></div>

        <div class="relative z-10 max-w-2xl">
            <h2 class="text-3xl font-black mb-4">Stay ahead with AI updates.</h2>
            <p class="text-primary-100 mb-8 text-lg">Join 10,000+ creators getting weekly AI prompts, news, and platform updates directly in their inbox.</p>

            <form @submit.prevent="submit" :class="[
                'gap-4',
                layoutClass === 'vertical' ? 'flex flex-col' : '',
                layoutClass === 'horizontal' ? 'flex flex-row' : '',
                layoutClass === 'auto' ? 'flex flex-col md:flex-row' : ''
            ]">
                <div class="flex-1">
                    <input v-model="form.email" type="email" placeholder="your@email.com" class="w-full bg-white/10 border border-white/20 rounded-2xl px-5 py-4 text-white placeholder-white/50 focus:bg-white focus:text-gray-900 focus:outline-none transition-all" required />
                </div>
                <button type="submit" :disabled="form.processing" :class="[
                    'px-8 py-4 bg-white text-primary-600 rounded-2xl font-black hover:bg-primary-50 transition-all shadow-xl disabled:opacity-50',
                    (layoutClass === 'horizontal' || layoutClass === 'auto') ? 'whitespace-nowrap' : ''
                ]">
                    {{ form.processing ? 'Joining...' : 'SUBSCRIBE' }}
                </button>
            </form>
            <p class="mt-4 text-xs text-primary-200">No spam, ever. Unsubscribe with one click anytime.</p>
        </div>
    </div>
    
    <div v-else class="w-full">
        <form @submit.prevent="submit" :class="[
            'gap-3',
            layoutClass === 'vertical' ? 'flex flex-col' : '',
            layoutClass === 'horizontal' ? 'flex flex-row items-stretch' : '',
            layoutClass === 'auto' ? 'flex flex-col md:flex-row md:items-stretch' : ''
        ]">
            <div class="flex-1">
                <input v-model="form.email" type="email" placeholder="your@email.com" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-3 text-sm focus:border-primary-500 focus:outline-none transition-all dark:text-white" required />
            </div>
            <button type="submit" :disabled="form.processing" :class="[
                'px-6 py-3 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-all disabled:opacity-50',
                (layoutClass === 'horizontal' || layoutClass === 'auto') ? 'whitespace-nowrap' : ''
            ]">
                {{ form.processing ? 'Joining...' : 'SUBSCRIBE' }}
            </button>
        </form>
    </div>
</template>
