<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { useTranslate } from '@/Composables/useTranslate';
import SocialFollow from '@/Components/SocialFollow.vue';
import LiveSearch from '@/Components/LiveSearch.vue';
import AdSection from '@/Components/AdSection.vue';

interface SidebarBlock {
    id: string | number
    type: string
    config: Record<string, any>
}

interface SidebarConfig {
    blocks: SidebarBlock[]
    position: string
    sticky: boolean
    show_on_pages: string[]
}

const page = usePage()
const { t } = useTranslate()
const sidebarConfig = computed<SidebarConfig>(() => (page.props.sidebarConfig as SidebarConfig) || { blocks: [], position: 'right', sticky: true, show_on_pages: [] });
const sidebarData = computed(() => (page.props.sidebarData as Record<string, any>) || {});

const currentRoute = computed<string>(() => {
    const cur = route().current();
    return typeof cur === 'string' ? cur : '';
});
const isVisible = computed(() => {
    const pages = sidebarConfig.value.show_on_pages ?? [];
    if (pages.length === 0) return true;
    const parts = currentRoute.value.split('.');
    return pages.some((p) => currentRoute.value === p || (parts.length > 1 && currentRoute.value.startsWith(p.replace('*', ''))));
});
</script>

<template>
    <aside v-if="isVisible && sidebarConfig.blocks.length" :class="['w-full shrink-0 space-y-6', { 'lg:sticky lg:top-24': sidebarConfig.sticky }]">
        <div v-for="block in sidebarConfig.blocks" :key="block.id" class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-5 overflow-hidden">
            <h3 v-if="block.config.title" class="text-base font-bold text-gray-900 dark:text-white mb-4 relative pb-3 after:absolute after:bottom-0 after:left-0 after:w-10 after:h-1 after:bg-primary-500 after:rounded-full">
                {{ block.config.title }}
            </h3>

            <!-- Search Box -->
            <LiveSearch v-if="block.type === 'search_box'" context="public" compact />

            <!-- Categories List -->
            <div v-else-if="block.type === 'categories_list'">
                <ul class="space-y-2">
                    <li v-for="category in (sidebarData.toolCategories || [])" :key="category.slug">
                        <Link :href="route('ai.tools.index', { category: category.slug })" class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group">
                            <span class="flex items-center gap-2">
                                <svg class="w-3 h-3 opacity-0 -ml-5 group-hover:opacity-100 group-hover:ml-0 transition-all duration-300 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                {{ category.name }}
                            </span>
                            <span v-if="block.config.show_count && category.tools_count" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-surface-800 text-gray-500 font-bold">{{ category.tools_count }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Recent Blog Posts -->
            <div v-else-if="block.type === 'recent_posts'" class="space-y-4">
                <div v-for="(post, index) in (sidebarData.recentPosts || []).slice(0, block.config.count || 3)" :key="index" class="flex gap-3 items-start group">
                    <div v-if="post.image" class="w-12 h-12 rounded-lg overflow-hidden shrink-0">
                        <img :src="post.image" :alt="post.title" class="w-full h-full object-cover">
                    </div>
                    <div v-else class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-surface-800 flex items-center justify-center shrink-0 text-primary-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <Link :href="route('blog.show', post.slug)" class="text-sm font-bold text-gray-900 dark:text-white leading-tight group-hover:text-primary-600 transition-colors line-clamp-2">{{ post.title }}</Link>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ post.published_at }}</p>
                    </div>
                </div>
                <div v-if="!sidebarData.recentPosts?.length" class="text-sm text-gray-400 italic">{{ t('No posts yet.') }}</div>
            </div>

            <!-- Popular Tools -->
            <div v-else-if="block.type === 'popular_tools'">
                <ul class="space-y-2">
                    <li v-for="tool in (sidebarData.popularTools || []).slice(0, block.config.count || 5)" :key="tool.slug">
                        <Link :href="route('ai.tools.show', tool.slug)" class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group">
                            <span class="flex items-center gap-2">
                                <i v-if="tool.icon" :class="tool.icon" :style="{ color: tool.color }" class="text-base"></i>
                                <span class="truncate">{{ tool.name }}</span>
                            </span>
                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-surface-800 text-gray-500 font-bold shrink-0 ml-2">{{ tool.usage_count || 0 }}</span>
                        </Link>
                    </li>
                </ul>
                <div v-if="!sidebarData.popularTools?.length" class="text-sm text-gray-400 italic">{{ t('No data yet.') }}</div>
            </div>

            <!-- Recently Added Tools -->
            <div v-else-if="block.type === 'recently_added'" class="space-y-4">
                <div v-for="(tool, index) in (sidebarData.recentTools || []).slice(0, block.config.count || 3)" :key="index" class="flex gap-3 items-start group">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 border" :style="{ background: (tool.color || '#6366f1') + '12', borderColor: (tool.color || '#6366f1') + '20' }">
                        <i v-if="tool.icon" :class="tool.icon" :style="{ color: tool.color }" class="text-lg"></i>
                        <svg v-else class="w-5 h-5" :style="{ color: tool.color }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <Link :href="route('ai.tools.show', tool.slug)" class="text-sm font-bold text-gray-900 dark:text-white leading-tight group-hover:text-primary-600 transition-colors line-clamp-1">{{ tool.name }}</Link>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ tool.description || t('Recently added tool.') }}</p>
                    </div>
                </div>
                <div v-if="!sidebarData.recentTools?.length" class="text-sm text-gray-400 italic">{{ t('No tools yet.') }}</div>
            </div>

            <!-- Tag Cloud -->
            <div v-else-if="block.type === 'tag_cloud'" class="flex flex-wrap gap-2">
                <Link v-for="tag in (sidebarData.tags || [])" :key="tag.slug ?? tag.name" :href="tag.url ?? `/blog/tag/${(tag.slug || tag.name || '').toLowerCase().replace(/\s+/g, '-')}`" class="text-xs px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-surface-800 text-gray-600 dark:text-gray-400 hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-900/20 dark:hover:text-primary-400 transition-colors border border-gray-100 dark:border-surface-700 hover:border-primary-200 dark:hover:border-primary-800">
                    {{ tag.name }}
                </Link>
                <span v-if="!sidebarData.tags?.length" class="text-sm text-gray-400 italic">{{ t('No tags yet.') }}</span>
            </div>

            <!-- Newsletter -->
            <div v-else-if="block.type === 'newsletter'">
                <p v-if="block.config.description" class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ block.config.description }}</p>
                <form method="post" action="/newsletter/subscribe" class="space-y-3">
                    <input type="email" name="email" :placeholder="t('Your email address')" class="w-full bg-gray-50 dark:bg-surface-800 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 dark:text-white transition-all" required>
                    <button type="submit" class="w-full btn-primary font-bold py-3 px-4 rounded-xl text-sm transition-all shadow-lg shadow-primary-600/20">
                        {{ t('Subscribe Now') }}
                    </button>
                </form>
            </div>

            <!-- Social Follow -->
            <SocialFollow v-else-if="block.type === 'social_follow'" :display-mode="block.config.display_mode || 'icons'" />

            <!-- Ad Zone -->
            <AdSection v-else-if="block.type === 'ad_zone'" :zone="block.config.zone_id || 'sidebar_top'" class="w-full" />

            <!-- Custom HTML -->
            <div v-else-if="block.type === 'custom_html'" v-html="block.config.content"></div>
        </div>
    </aside>
</template>
