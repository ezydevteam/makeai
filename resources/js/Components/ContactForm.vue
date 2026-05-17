<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const isSubmitted = ref(false);

const submit = () => {
    form.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            isSubmitted.value = true;
            setTimeout(() => isSubmitted.value = false, 5000);
        },
    });
};
</script>

<template>
    <div class="bg-gray-50 p-8 md:p-12 rounded-3xl border border-gray-100 shadow-xl shadow-gray-200/50">
        <form v-if="!isSubmitted" @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-black text-gray-900 uppercase tracking-widest mb-2">Full Name</label>
                    <input 
                        v-model="form.name" 
                        type="text" 
                        id="name" 
                        required 
                        class="w-full bg-white border-2 border-gray-100 rounded-2xl px-6 py-4 text-sm focus:border-primary-500 focus:ring-0 transition-all outline-none"
                        :class="{'border-red-500': form.errors.name}"
                        placeholder="John Doe"
                    >
                    <p v-if="form.errors.name" class="mt-2 text-xs font-bold text-red-500 uppercase tracking-wider">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="email" class="block text-sm font-black text-gray-900 uppercase tracking-widest mb-2">Email Address</label>
                    <input 
                        v-model="form.email" 
                        type="email" 
                        id="email" 
                        required 
                        class="w-full bg-white border-2 border-gray-100 rounded-2xl px-6 py-4 text-sm focus:border-primary-500 focus:ring-0 transition-all outline-none"
                        :class="{'border-red-500': form.errors.email}"
                        placeholder="john@example.com"
                    >
                    <p v-if="form.errors.email" class="mt-2 text-xs font-bold text-red-500 uppercase tracking-wider">{{ form.errors.email }}</p>
                </div>
            </div>

            <div>
                <label for="subject" class="block text-sm font-black text-gray-900 uppercase tracking-widest mb-2">Subject</label>
                <input 
                    v-model="form.subject" 
                    type="text" 
                    id="subject" 
                    class="w-full bg-white border-2 border-gray-100 rounded-2xl px-6 py-4 text-sm focus:border-primary-500 focus:ring-0 transition-all outline-none"
                    :class="{'border-red-500': form.errors.subject}"
                    placeholder="How can we help you?"
                >
                <p v-if="form.errors.subject" class="mt-2 text-xs font-bold text-red-500 uppercase tracking-wider">{{ form.errors.subject }}</p>
            </div>

            <div>
                <label for="message" class="block text-sm font-black text-gray-900 uppercase tracking-widest mb-2">Message</label>
                <textarea 
                    v-model="form.message" 
                    id="message" 
                    rows="6" 
                    required 
                    class="w-full bg-white border-2 border-gray-100 rounded-2xl px-6 py-4 text-sm focus:border-primary-500 focus:ring-0 transition-all outline-none resize-none"
                    :class="{'border-red-500': form.errors.message}"
                    placeholder="Tell us more about your inquiry..."
                ></textarea>
                <p v-if="form.errors.message" class="mt-2 text-xs font-bold text-red-500 uppercase tracking-wider">{{ form.errors.message }}</p>
            </div>

            <button 
                type="submit" 
                :disabled="form.processing"
                class="w-full bg-primary-600 text-white py-5 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-primary-500 transition-all shadow-xl shadow-primary-600/20 disabled:opacity-50"
            >
                {{ form.processing ? 'Sending...' : 'Send Message' }}
            </button>
        </form>

        <div v-else class="text-center py-12">
            <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-2">Message Sent!</h3>
            <p class="text-gray-500 font-medium">Thank you for reaching out. We'll get back to you shortly.</p>
            <button @click="isSubmitted = false" class="mt-8 text-sm font-black text-primary-600 uppercase tracking-widest hover:text-primary-500">Send another message</button>
        </div>
    </div>
</template>
