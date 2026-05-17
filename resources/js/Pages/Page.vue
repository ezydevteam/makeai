<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/AppLayout.vue';
import ContactForm from '@/Components/ContactForm.vue';
import FaqAccordion from '@/Components/FaqAccordion.vue';
import AppSidebar from '@/Components/AppSidebar.vue';

const props = defineProps<{
    page: any
}>();
</script>

<template>
    <Head>
        <title>{{ page.meta_title || page.title }}</title>
        <meta name="description" :content="page.meta_description">
        <meta name="keywords" :content="page.meta_keywords">
        <meta property="og:title" :content="page.meta_title || page.title">
        <meta property="og:description" :content="page.meta_description">
        <meta v-if="page.og_image" property="og:image" :content="'/storage/' + page.og_image">
    </Head>

    <Layout>
        <div :class="[
            page.container_width === 'full' ? 'max-w-full' : 
            page.container_width === 'wide' ? 'max-w-7xl' : 
            page.container_width === 'narrow' ? 'max-w-3xl' : 'max-w-5xl'
        ]" class="mx-auto px-6 py-12 md:py-20 bg-white">
            
            <div class="flex flex-col lg:flex-row gap-12">
                <!-- Main Content -->
                <div :class="[page.show_sidebar ? 'lg:w-2/3' : 'w-full']" :style="{ order: page.sidebar_position === 'left' ? 2 : 1 }">
                    <article class="prose prose-lg prose-indigo max-w-none">
                        <header v-if="page.show_title" class="mb-12">
                            <h1 class="text-4xl md:text-5xl font-black text-gray-900 leading-tight mb-4">
                                {{ page.title }}
                            </h1>
                            <div v-if="page.show_breadcrumbs" class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest">
                                <Link href="/" class="hover:text-primary-600 transition-colors">Home</Link>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                <span class="text-gray-900">{{ page.title }}</span>
                            </div>
                        </header>

                        <div v-if="page.show_featured_image && page.featured_image" class="mb-12 rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50">
                            <img :src="'/storage/' + page.featured_image" :alt="page.title" class="w-full h-auto object-cover">
                        </div>

                        <div v-if="page.slug === 'contact'" class="not-prose">
                            <div v-html="page.content" class="cms-content text-gray-700 leading-relaxed mb-12"></div>
                            <ContactForm />
                        </div>
                        <div v-else-if="page.slug === 'faq'" class="not-prose">
                            <FaqAccordion :content="page.content" />
                        </div>
                        <div v-else v-html="page.content" class="cms-content text-gray-700 leading-relaxed space-y-6"></div>
                    </article>
                </div>

                <!-- Sidebar -->
                <div v-if="page.show_sidebar" class="lg:w-1/3" :style="{ order: page.sidebar_position === 'left' ? 1 : 2 }">
                    <AppSidebar />
                </div>
            </div>
        </div>
    </Layout>
</template>

<style>
@reference "../../css/app.css";

/* Base Styles for CMS Content */
.cms-content h2 { @apply text-3xl font-black text-gray-900 mt-12 mb-6; }
.cms-content h3 { @apply text-2xl font-bold text-gray-900 mt-8 mb-4; }
.cms-content p { @apply text-lg text-gray-700 leading-relaxed mb-6; }
.cms-content ul { @apply list-disc list-inside space-y-3 mb-6 ml-4; }
.cms-content ol { @apply list-decimal list-inside space-y-3 mb-6 ml-4; }
.cms-content a { @apply text-primary-600 font-bold border-b-2 border-primary-100 hover:border-primary-600 transition-all; }
.cms-content img { @apply rounded-3xl shadow-lg my-12; }
.cms-content blockquote { @apply border-l-4 border-primary-500 pl-6 italic text-gray-500 my-12; }
</style>
