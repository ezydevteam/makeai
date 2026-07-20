<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { useTranslate } from '@/Composables/useTranslate';
import SocialFollow from '@themes/default/js/Components/SocialFollow.vue';
import LiveSearch from '@/Components/Utility/LiveSearch.vue';
import AdSection from '@themes/default/js/Components/AdSection.vue';

interface SidebarBlock {
    id: string | number
    type: string
    config: Record<string, any>
}

type SidebarArea = 'main' | 'blog' | 'page'

interface SidebarConfig {
    areas?: Record<SidebarArea, SidebarBlock[]>
    // Legacy flat shape kept for backward compatibility with older saved configs.
    blocks?: SidebarBlock[]
}

const props = defineProps<{
    // Override the widget area; when omitted it is derived from the current route.
    area?: SidebarArea
}>();

const page = usePage()
const { t } = useTranslate()
const sidebarConfig = computed<SidebarConfig>(() => (page.props.sidebarConfig as SidebarConfig) || { areas: { main: [], blog: [], page: [] } });
const sidebarData = computed(() => (page.props.sidebarData as Record<string, any>) || {});

const currentRoute = computed<string>(() => {
    const cur = route().current();
    return typeof cur === 'string' ? cur : '';
});

const deriveArea = (r: string): SidebarArea => {
    if (r.startsWith('ai.tools')) return 'main';
    if (r.startsWith('blog')) return 'blog';
    return 'page';
};

const activeArea = computed<SidebarArea>(() => props.area ?? deriveArea(currentRoute.value));

const localeCode = computed<string>(() => (page.props.locale as { code?: string })?.code ?? 'en');

// Day-first "17 Jul 2026" — built manually so the order stays day/month/year
// regardless of the viewer's locale, while the month name follows the locale.
const formatPostDate = (value?: string): string => {
    if (!value) return '';
    // published_at is a date-only string (YYYY-MM-DD); parse the parts as a local
    // date so a UTC parse can't shift the day back in negative timezones.
    const [y, m, d] = (value.split('T')[0] ?? '').split('-').map(Number);
    const date = (y && m && d) ? new Date(y, m - 1, d) : new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    const month = new Intl.DateTimeFormat(localeCode.value, { month: 'short' }).format(date);
    return `${date.getDate()} ${month} ${date.getFullYear()}`;
};

const blocks = computed<SidebarBlock[]>(() => {
    const cfg = sidebarConfig.value;
    if (cfg.areas) return cfg.areas[activeArea.value] ?? [];
    // Legacy configs only ever rendered on CMS pages, so the flat block list
    // maps to the 'page' area only — never leak it into main/blog areas.
    return activeArea.value === 'page' ? (cfg.blocks ?? []) : [];
});
</script>

<template>
    <aside v-if="blocks.length" class="w-full shrink-0 space-y-6 lg:sticky lg:top-24">
        <div v-for="block in blocks" :key="block.id" class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-100 dark:border-surface-800 p-5" :class="block.type === 'search_box' ? 'overflow-visible' : 'overflow-hidden'">
            <h3 v-if="block.config.title" class="text-base font-bold text-gray-900 dark:text-white mb-4 relative pb-3 after:absolute after:bottom-0 after:left-0 after:w-10 after:h-1 after:bg-primary-500 after:rounded-full">
                {{ block.config.title }}
            </h3>

            <!-- Search Box -->
            <LiveSearch v-if="block.type === 'search_box'" context="public" compact />

            <!-- Categories List -->
            <div v-else-if="block.type === 'categories_list'">
                <ul class="space-y-2">
                    <li v-for="category in (sidebarData.toolCategories || [])" :key="category.slug">
                        <Link :href="route('ai.tools.index', { category: category.slug })" class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 hover:!text-primary-600 dark:hover:!text-primary-400 transition-colors group">
                            <span class="flex items-center gap-2">
                                <svg class="w-3 h-3 opacity-0 -ml-5 group-hover:opacity-100 group-hover:ml-0 transition-all duration-300 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                {{ category.name }}
                            </span>
                            <span v-if="block.config.show_count && category.tools_count" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-surface-800 text-gray-500 font-bold">{{ category.tools_count }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Blog Categories -->
            <div v-else-if="block.type === 'blog_categories'">
                <ul class="space-y-2">
                    <li v-for="category in (sidebarData.blogCategories || [])" :key="category.slug">
                        <Link :href="route('blog.category', category.slug)" class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 hover:!text-primary-600 dark:hover:!text-primary-400 transition-colors group">
                            <span class="flex items-center gap-2">
                                <i v-if="category.icon" :class="category.icon" :style="{ color: category.color || undefined }" class="text-base"></i>
                                <svg v-else class="w-3 h-3 opacity-0 -ml-5 group-hover:opacity-100 group-hover:ml-0 transition-all duration-300 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                {{ category.name }}
                            </span>
                            <span v-if="block.config.show_count && category.posts_count" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-surface-800 text-gray-500 font-bold">{{ category.posts_count }}</span>
                        </Link>
                    </li>
                </ul>
                <div v-if="!sidebarData.blogCategories?.length" class="text-sm text-gray-400 italic">{{ t('No categories yet.') }}</div>
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
                        <Link :href="route('blog.show', post.slug)" class="text-sm font-bold text-gray-900 dark:text-white leading-tight group-hover:!text-primary-600 dark:group-hover:!text-primary-400 transition-colors line-clamp-2">{{ post.title }}</Link>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ formatPostDate(post.published_at) }}</p>
                    </div>
                </div>
                <div v-if="!sidebarData.recentPosts?.length" class="text-sm text-gray-400 italic">{{ t('No posts yet.') }}</div>
            </div>

            <!-- Popular Tools -->
            <div v-else-if="block.type === 'popular_tools'">
                <ul class="space-y-2">
                    <li v-for="tool in (sidebarData.popularTools || []).slice(0, block.config.count || 5)" :key="tool.slug">
                        <Link :href="route('ai.tools.show', tool.slug)" class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 hover:!text-primary-600 dark:hover:!text-primary-400 transition-colors group">
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
                        <Link :href="route('ai.tools.show', tool.slug)" class="text-sm font-bold text-gray-900 dark:text-white leading-tight group-hover:!text-primary-600 dark:group-hover:!text-primary-400 transition-colors line-clamp-1">{{ tool.name }}</Link>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ tool.description || t('Recently added tool.') }}</p>
                    </div>
                </div>
                <div v-if="!sidebarData.recentTools?.length" class="text-sm text-gray-400 italic">{{ t('No tools yet.') }}</div>
            </div>

            <!-- Tag Cloud -->
            <div v-else-if="block.type === 'tag_cloud'" class="flex flex-wrap gap-2">
                <Link v-for="tag in (sidebarData.tags || [])" :key="tag.slug ?? tag.name" :href="tag.url ?? `/blog/tag/${(tag.slug || tag.name || '').toLowerCase().replace(/\s+/g, '-')}`" class="text-xs px-3 py-1.5 rounded-full bg-gray-50 dark:bg-surface-800 text-gray-600 dark:text-gray-400 hover:!bg-primary-50 hover:!text-primary-600 dark:hover:!bg-primary-900/20 dark:hover:!text-primary-400 transition-colors border border-gray-100 dark:border-surface-700 hover:!border-primary-200 dark:hover:!border-primary-900/30">
                    {{ tag.name }}
                </Link>
                <span v-if="!sidebarData.tags?.length" class="text-sm text-gray-400 italic">{{ t('No tags yet.') }}</span>
            </div>

            <!-- Newsletter -->
            <div v-else-if="block.type === 'newsletter'">
                <p v-if="block.config.description" class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ block.config.description }}</p>
                <form method="post" action="/newsletter/subscribe" class="space-y-3">
                    <input type="email" name="email" :placeholder="t('Your email address...')" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 !rounded-full px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:text-white transition-all" required>
                    <button type="submit" class="w-full btn-primary shadow-lg shadow-primary-600/20">
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
