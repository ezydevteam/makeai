<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';

const page = usePage();
const settings = computed<Record<string, any>>(() => (page.props.newsletterSettings as Record<string, any>) || {});

const isVisible = ref(false);
const form = useForm({
    email: '',
    name: ''
});

const isEnabled = computed(() => settings.value.newsletter_enable_popup === '1' || settings.value.newsletter_enable_popup === true);
const hideForLoggedIn = computed(() => settings.value.newsletter_popup_hide_for_logged_in === '1' || settings.value.newsletter_popup_hide_for_logged_in === true);
const showOnMobile = computed(() => settings.value.newsletter_popup_show_mobile === '1' || settings.value.newsletter_popup_show_mobile === true);

const trigger = computed(() => settings.value.newsletter_popup_trigger || 'time_delay');
const triggerValue = computed(() => settings.value.newsletter_popup_trigger_value || '5');

let scrollListener: any = null;

onMounted(() => {
    if (!isEnabled.value) return;
    
    // Check if user is logged in and hideForLoggedIn is true
    if (hideForLoggedIn.value && page.props.auth?.user) return;
    
    // Check if dismissed
    if (localStorage.getItem('newsletter_popup_dismissed')) {
        const dismissedAt = new Date(localStorage.getItem('newsletter_popup_dismissed')!);
        const duration = parseInt(settings.value.newsletter_popup_cookie_duration || '30');
        const now = new Date();
        const diffTime = Math.abs(now.getTime() - dismissedAt.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
        
        if (diffDays <= duration) {
            return;
        } else {
            localStorage.removeItem('newsletter_popup_dismissed');
        }
    }

    // Check if first visit only
    if (trigger.value === 'first_visit') {
        if (sessionStorage.getItem('newsletter_popup_shown')) return;
        setTimeout(() => showPopup(), parseInt(triggerValue.value) * 1000 || 2000);
    } else if (trigger.value === 'time_delay') {
        setTimeout(() => showPopup(), parseInt(triggerValue.value) * 1000 || 5000);
    } else if (trigger.value === 'scroll_depth') {
        scrollListener = () => {
            const h = document.documentElement;
            const b = document.body;
            const st = 'scrollTop';
            const sh = 'scrollHeight';
            const percent = (h[st] || b[st]) / ((h[sh] || b[sh]) - h.clientHeight) * 100;
            
            if (percent >= parseInt(triggerValue.value)) {
                showPopup();
                window.removeEventListener('scroll', scrollListener);
            }
        };
        window.addEventListener('scroll', scrollListener);
    } else if (trigger.value === 'exit_intent') {
        document.addEventListener('mouseleave', (e) => {
            if (e.clientY < 0 && !isVisible.value && !sessionStorage.getItem('newsletter_popup_shown')) {
                showPopup();
            }
        });
    } else if (trigger.value === 'page_views') {
        let views = parseInt(localStorage.getItem('page_views') || '0') + 1;
        localStorage.setItem('page_views', views.toString());
        if (views >= parseInt(triggerValue.value)) {
            setTimeout(() => showPopup(), 1000);
        }
    }
});

onBeforeUnmount(() => {
    if (scrollListener) {
        window.removeEventListener('scroll', scrollListener);
    }
});

const showPopup = () => {
    if (!showOnMobile.value && window.innerWidth < 768) return;
    
    isVisible.value = true;
    sessionStorage.setItem('newsletter_popup_shown', 'true');
};

const closePopup = () => {
    isVisible.value = false;
    localStorage.setItem('newsletter_popup_dismissed', new Date().toISOString());
};

const submit = () => {
    form.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => {
            setTimeout(() => {
                closePopup();
            }, 3000);
        }
    });
};
</script>

<template>
    <Teleport to="body">
        <div v-if="isVisible" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm animate-in fade-in duration-300">
            <div class="relative w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300" :style="{ backgroundColor: settings.newsletter_popup_bg_color || '#ffffff' }">
                
                <button @click="closePopup" class="absolute top-4 right-4 z-10 p-2 text-gray-400 hover:text-gray-900 bg-white/50 hover:bg-white/80 rounded-full transition-all backdrop-blur-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <div class="p-8 md:p-10 text-center relative z-0">
                    <div class="w-16 h-16 mx-auto bg-primary-100 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    
                    <h2 class="text-2xl font-black text-gray-900 mb-3">{{ settings.newsletter_popup_title || 'Subscribe to our Newsletter' }}</h2>
                    <p class="text-gray-600 mb-8">{{ settings.newsletter_popup_description || 'Get the latest updates delivered directly to your inbox.' }}</p>

                    <form v-if="!form.recentlySuccessful" @submit.prevent="submit" class="space-y-4">
                        <div>
                            <input v-model="form.name" type="text" placeholder="Your Name (Optional)" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all" />
                        </div>
                        <div>
                            <input v-model="form.email" type="email" :placeholder="settings.newsletter_popup_placeholder || 'Enter your email address'" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all" required />
                        </div>
                        <button type="submit" :disabled="form.processing" class="w-full py-3.5 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/25 disabled:opacity-50">
                            <span v-if="form.processing">Subscribing...</span>
                            <span v-else>{{ settings.newsletter_popup_submit_text || 'Subscribe' }}</span>
                        </button>
                    </form>

                    <div v-else class="py-6 px-4 bg-success-50 rounded-xl border border-success-100 animate-in fade-in zoom-in-95 duration-300">
                        <svg class="w-12 h-12 text-success-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="font-bold text-success-800">{{ settings.newsletter_popup_success_message || 'Thanks for subscribing!' }}</p>
                    </div>

                    <p class="text-xs text-gray-400 mt-6">We respect your privacy. Unsubscribe at any time.</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
