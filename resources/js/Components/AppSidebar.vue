<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

interface SidebarBlock {
    id: string | number
    type: string
    config: Record<string, any>
}

interface SidebarConfig {
    blocks: SidebarBlock[]
    position: string
    sticky: boolean
}

interface SidebarToolCategory {
    slug: string
    name: string
    count?: number
}

interface SidebarTool {
    slug: string
    name: string
    description?: string
    icon?: string
    color: string
}

interface SidebarData {
    toolCategories: SidebarToolCategory[]
    recentTools: SidebarTool[]
}

// Access the global sidebar config injected by HandleInertiaRequests
const page = usePage()
const sidebarConfig = computed<SidebarConfig>(() => (page.props.sidebarConfig as SidebarConfig) || { blocks: [], position: 'right', sticky: true });
const sidebarData = computed<SidebarData>(() => (page.props.sidebarData as SidebarData) || { toolCategories: [], recentTools: [] });

// Tags are still hardcoded as we don't have a Tag model yet
const mockTags = ['AI Writer', 'Image Gen', 'Code Assistant', 'Marketing', 'SEO', 'Business', 'Social Media'];
</script>

<template>
    <aside :class="['w-full shrink-0 space-y-6', { 'lg:sticky lg:top-24': sidebarConfig.sticky }]">
        <div v-for="block in sidebarConfig.blocks" :key="block.id" class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-5 overflow-hidden">
            <!-- Block Header -->
            <h3 v-if="block.config.title" class="text-base font-bold text-gray-900 dark:text-white mb-4 relative pb-3 after:absolute after:bottom-0 after:left-0 after:w-10 after:h-1 after:bg-primary-500 after:rounded-full">
                {{ block.config.title }}
            </h3>

            <!-- Search Box -->
            <div v-if="block.type === 'search_box'">
                <form @submit.prevent class="relative">
                    <input type="text" :placeholder="block.config.placeholder || 'Search...'" class="w-full bg-gray-50 dark:bg-surface-800 border-none rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 dark:text-white transition-all">
                    <svg class="w-4 h-4 absolute left-3.5 top-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </form>
            </div>

            <!-- Categories List -->
            <div v-else-if="block.type === 'categories_list'">
                <ul class="space-y-2">
                    <li v-for="category in sidebarData.toolCategories" :key="category.slug">
                        <Link :href="route('ai.tools.index', { category: category.slug })" class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group">
                            <span class="flex items-center gap-2">
                                <svg class="w-3 h-3 opacity-0 -ml-5 group-hover:opacity-100 group-hover:ml-0 transition-all duration-300 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                {{ category.name }}
                            </span>
                            <span v-if="block.config.show_count" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-surface-800 text-gray-500 font-bold">{{ category.count }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Recent Tools/Posts -->
            <div v-else-if="block.type === 'recent_posts'" class="space-y-4">
                <div v-for="(tool, index) in sidebarData.recentTools.slice(0, block.config.count || 3)" :key="index" class="flex gap-3 items-start group">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 border" :style="{ background: tool.color + '12', borderColor: tool.color + '20' }">
                        <svg class="w-5 h-5" :style="{ color: tool.color }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path v-if="tool.icon === 'document'" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            <path v-else d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white leading-tight group-hover:text-primary-600 transition-colors truncate">
                            <Link :href="route('ai.tools.show', tool.slug)">{{ tool.name }}</Link>
                        </h4>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ tool.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Tag Cloud -->
            <div v-else-if="block.type === 'tag_cloud'" class="flex flex-wrap gap-2">
                <Link v-for="tag in mockTags" :key="tag" :href="`/tag/${tag.toLowerCase().replace(/ /g, '-')}`" class="text-xs px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-surface-800 text-gray-600 dark:text-gray-400 hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20 dark:hover:text-primary-400 transition-colors border border-gray-100 dark:border-surface-700 hover:border-primary-200 dark:hover:border-primary-800">
                    {{ tag }}
                </Link>
            </div>

            <!-- Newsletter -->
            <div v-else-if="block.type === 'newsletter'">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ block.config.description }}</p>
                <form @submit.prevent class="space-y-3">
                    <input type="email" placeholder="Your email address" class="w-full bg-gray-50 dark:bg-surface-800 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 dark:text-white transition-all" required>
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-500 text-white font-bold py-3 px-4 rounded-xl text-sm transition-all shadow-lg shadow-primary-600/20">
                        Subscribe Now
                    </button>
                </form>
            </div>

            <!-- Social Follow -->
            <div v-else-if="block.type === 'social_follow'" class="flex gap-2">
                <!-- Example hardcoded socials for now. In real app, comes from settings -->
                <a href="#" class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-surface-800 flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#1DA1F2] transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg></a>
                <a href="#" class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-surface-800 flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#1877F2] transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                <a href="#" class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-surface-800 flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#E4405F] transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
            </div>

            <!-- Ad Zone -->
            <div v-else-if="block.type === 'ad_zone'" class="w-full h-48 bg-gray-100 dark:bg-surface-800 rounded-xl flex items-center justify-center border border-gray-200 dark:border-surface-700">
                <span class="text-xs text-gray-400 font-bold uppercase tracking-widest">Advertisement</span>
            </div>

            <!-- Custom HTML -->
            <div v-else-if="block.type === 'custom_html'" v-html="block.config.content"></div>
        </div>
    </aside>
</template>
