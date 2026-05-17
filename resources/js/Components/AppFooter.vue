<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const page = usePage();
const footerConfig = computed(() => page.props.footerConfig as any);
const globalMenus = computed(() => page.props.globalMenus as Array<any> || []);

const currentYear = new Date().getFullYear();

const parsedCopyright = computed(() => {
    let text = footerConfig.value?.bottom_bar?.copyright_text || '';
    return text.replace('{year}', currentYear.toString());
});

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const getMenu = (slug: string) => {
    return globalMenus.value.find((m: any) => m.slug === slug);
};

const layoutClass = computed(() => {
    const layout = footerConfig.value?.layout || 4;
    if (layout === 1) return 'grid-cols-1';
    if (layout === 2) return 'grid-cols-1 md:grid-cols-2';
    if (layout === 3) return 'grid-cols-1 md:grid-cols-3';
    return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4';
});
</script>

<template>
    <footer v-if="footerConfig" class="bg-white dark:bg-surface-900 border-t border-gray-100 dark:border-surface-800 mt-auto">
        <!-- Main Footer -->
        <div class="max-w-7xl mx-auto px-6 py-16">
            <div class="grid gap-12" :class="layoutClass">
                
                <!-- Columns Loop -->
                <div v-for="(colBlocks, index) in footerConfig.columns" :key="index" class="space-y-8">
                    
                    <!-- Blocks inside Column -->
                    <div v-for="block in colBlocks" :key="block.id">
                        
                        <!-- ABOUT TEXT -->
                        <div v-if="block.type === 'about_text'" class="space-y-6">
                            <Link href="/" class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-gradient-to-br from-primary-600 to-accent-600 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                    </svg>
                                </div>
                                <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white">Make<span class="text-primary-600">AI</span></span>
                            </Link>
                            <p class="text-gray-500 dark:text-gray-400 text-sm leading-relaxed" v-html="block.config.description"></p>
                        </div>

                        <!-- MENU LIST -->
                        <div v-else-if="block.type === 'menu_list'">
                            <h4 class="font-black text-xs uppercase tracking-widest text-gray-900 dark:text-white mb-6" v-if="block.config.title">{{ block.config.title }}</h4>
                            <ul class="space-y-4">
                                <template v-if="getMenu(block.config.menu_slug)">
                                    <li v-for="item in getMenu(block.config.menu_slug).items" :key="item.id">
                                        <a :href="item.url" :target="item.target" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors font-medium">
                                            {{ item.title }}
                                        </a>
                                    </li>
                                </template>
                                <li v-else class="text-sm text-gray-400 italic">Menu not found</li>
                            </ul>
                        </div>

                        <!-- CONTACT INFO -->
                        <div v-else-if="block.type === 'contact_info'">
                            <h4 class="font-black text-xs uppercase tracking-widest text-gray-900 dark:text-white mb-6" v-if="block.config.title">{{ block.config.title }}</h4>
                            <ul class="space-y-4">
                                <li v-if="block.config.address" class="flex items-start gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="w-5 h-5 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                    <span>{{ block.config.address }}</span>
                                </li>
                                <li v-if="block.config.phone" class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="w-5 h-5 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.896-1.596-5.539-4.062-7.09-7.09l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
                                    <a :href="`tel:${block.config.phone}`" class="hover:text-primary-600 transition-colors">{{ block.config.phone }}</a>
                                </li>
                                <li v-if="block.config.email" class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="w-5 h-5 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                                    <a :href="`mailto:${block.config.email}`" class="hover:text-primary-600 transition-colors">{{ block.config.email }}</a>
                                </li>
                            </ul>
                        </div>

                        <!-- NEWSLETTER -->
                        <div v-else-if="block.type === 'newsletter'" class="bg-gray-50 dark:bg-surface-800 p-6 rounded-2xl border border-gray-100 dark:border-surface-700">
                            <h4 class="font-black text-xs uppercase tracking-widest text-gray-900 dark:text-white mb-2" v-if="block.config.title">{{ block.config.title }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4" v-if="block.config.description">{{ block.config.description }}</p>
                            <form @submit.prevent class="relative">
                                <input type="email" placeholder="Enter your email" class="w-full bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 pr-12 dark:text-white transition-all shadow-sm">
                                <button type="submit" class="absolute right-1 top-1 bottom-1 px-3 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                </button>
                            </form>
                        </div>

                        <!-- SOCIAL ICONS -->
                        <div v-else-if="block.type === 'social_icons'">
                            <h4 class="font-black text-xs uppercase tracking-widest text-gray-900 dark:text-white mb-6" v-if="block.config.title">{{ block.config.title }}</h4>
                            <div class="flex flex-wrap gap-3">
                                <!-- Example social icon (we will expand this properly later, using generic fallback for now) -->
                                <a href="#" class="w-10 h-10 rounded-full bg-gray-50 hover:bg-primary-50 dark:bg-surface-800 dark:hover:bg-primary-900/20 text-gray-400 hover:text-primary-600 flex items-center justify-center transition-colors">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                                </a>
                                <a href="#" class="w-10 h-10 rounded-full bg-gray-50 hover:bg-primary-50 dark:bg-surface-800 dark:hover:bg-primary-900/20 text-gray-400 hover:text-primary-600 flex items-center justify-center transition-colors">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                </a>
                            </div>
                        </div>

                        <!-- CUSTOM HTML -->
                        <div v-else-if="block.type === 'custom_html'">
                            <h4 class="font-black text-xs uppercase tracking-widest text-gray-900 dark:text-white mb-6" v-if="block.config.title">{{ block.config.title }}</h4>
                            <div class="text-gray-500 dark:text-gray-400 text-sm prose dark:prose-invert" v-html="block.config.content"></div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Sub Footer (Bottom Bar) -->
            <div class="pt-8 border-t border-gray-100 dark:border-surface-800 flex flex-col md:flex-row items-center justify-between gap-6" v-if="footerConfig.bottom_bar">
                <!-- Copyright -->
                <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">
                    {{ parsedCopyright }}
                </p>

                <div class="flex items-center gap-6">
                    <!-- Bottom Menu -->
                    <ul class="flex items-center gap-6" v-if="footerConfig.bottom_bar.menu_slug && getMenu(footerConfig.bottom_bar.menu_slug)">
                        <li v-for="item in getMenu(footerConfig.bottom_bar.menu_slug).items" :key="item.id">
                            <a :href="item.url" :target="item.target" class="text-xs text-gray-400 dark:text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors font-medium">
                                {{ item.title }}
                            </a>
                        </li>
                    </ul>

                    <!-- Payment Icons -->
                    <div class="flex items-center gap-2" v-if="footerConfig.bottom_bar.show_payment_icons && footerConfig.bottom_bar.payment_icons?.length">
                        <!-- Placeholder SVGs for payment methods -->
                        <div v-for="icon in footerConfig.bottom_bar.payment_icons" :key="icon" class="h-6 px-2 bg-gray-50 dark:bg-surface-800 border border-gray-100 dark:border-surface-700 rounded flex items-center justify-center text-[10px] font-black text-gray-400 uppercase">
                            {{ icon }}
                        </div>
                    </div>

                    <!-- Back to top -->
                    <button v-if="footerConfig.bottom_bar.show_back_to_top" @click="scrollToTop" class="w-8 h-8 rounded-lg bg-gray-50 hover:bg-primary-50 dark:bg-surface-800 dark:hover:bg-primary-900/20 text-gray-400 hover:text-primary-600 flex items-center justify-center transition-all shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </footer>
</template>
