<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppSelect from '@/Components/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

interface Author {
    id: number
    name: string
}

interface ShareNetworkOption {
    key: string
    label: string
}

interface BooleanSettingItem {
    key:
        | 'default_allow_comments'
        | 'show_reading_time'
        | 'blog_sidebar'
        | 'social_share_show_counts'
        | 'comments_enabled'
        | 'comments_auto_approve_users'
        | 'comments_allow_guests'
        | 'comments_require_approval'
        | 'comments_notify_admin'
    title: string
    description: string
}

interface BlogSettingsForm {
    posts_per_page: number | string
    related_posts_count: number | string
    related_posts_algorithm: string
    auto_excerpt_length: number | string
    default_author: number | null | string
    rss_posts_count: number | string
    words_per_minute: number | string
    blog_sidebar_position: string
    default_allow_comments: boolean
    show_reading_time: boolean
    blog_sidebar: boolean
    social_share_networks: string[]
    social_share_blog_style: string
    social_share_show_counts: boolean
    comments_enabled: boolean
    comments_auto_approve_users: boolean
    comments_allow_guests: boolean
    comments_require_approval: boolean
    comments_notify_admin: boolean
    comments_poll_seconds: number | string
    comments_akismet_key: string
    comments_akismet_configured: boolean
    [key: string]: string | number | boolean | string[] | null | undefined
}

const props = defineProps<{
    settings: BlogSettingsForm
    authors: Author[]
    shareNetworks: ShareNetworkOption[]
}>()

const { t } = useTranslate()

const form = useForm<BlogSettingsForm>({ ...props.settings })

const relatedAlgorithmOptions = computed(() => [
    { value: 'tags_first', label: t('Tags first') },
    { value: 'category_first', label: t('Category first') },
    { value: 'recent', label: t('Recent') },
])

const authorOptions = computed(() => [
    { value: '', label: t('Current admin') },
    ...props.authors.map((author) => ({
        value: String(author.id),
        label: author.name,
    })),
])

const sidebarPositionOptions = computed(() => [
    { value: 'right', label: t('Right') },
    { value: 'left', label: t('Left') },
])

const shareStyleOptions = computed(() => [
    { value: 'icon', label: t('Icon only') },
    { value: 'icon-label', label: t('Icon and label') },
    { value: 'icon-count', label: t('Icon and count') },
])

const booleanSettings = computed<BooleanSettingItem[]>(() => [
    {
        key: 'default_allow_comments',
        title: t('Default allow comments'),
        description: t('New posts start with comments enabled unless an editor changes it.'),
    },
    {
        key: 'show_reading_time',
        title: t('Show reading time'),
        description: t('Display the estimated reading time on blog post pages.'),
    },
    {
        key: 'blog_sidebar',
        title: t('Show blog sidebar'),
        description: t('Keep sidebar widgets visible on the public blog layout.'),
    },
    {
        key: 'social_share_show_counts',
        title: t('Show share counts when available'),
        description: t('Display network share totals on post pages when a provider supports it.'),
    },
    {
        key: 'comments_enabled',
        title: t('Enable comments globally'),
        description: t('Allow comment features across blog posts and public sections.'),
    },
    {
        key: 'comments_auto_approve_users',
        title: t('Auto-approve logged-in users'),
        description: t('Trusted signed-in users bypass the moderation queue.'),
    },
    {
        key: 'comments_allow_guests',
        title: t('Allow guest comments'),
        description: t('Let visitors comment without creating an account first.'),
    },
    {
        key: 'comments_require_approval',
        title: t('Require approval for all'),
        description: t('Send every new comment into moderation before publishing.'),
    },
    {
        key: 'comments_notify_admin',
        title: t('Notify admin on new comment'),
        description: t('Send an admin alert whenever a new comment is submitted.'),
    },
])

const submit = () => form
    .transform(({ comments_akismet_configured, ...payload }) => payload)
    .post(route('admin.blog.settings.update'), { preserveScroll: true })
</script>

<template>
    <Head :title="t('Blog Settings')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Settings') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure listing behavior, defaults, sidebar layout, RSS output, and social sharing from one place.') }}</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <Link :href="route('admin.blog.posts.index')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="ti ti-article text-base"></i>
                        {{ t('Posts') }}
                    </Link>
                    <Link :href="route('admin.blog.categories.index')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="ti ti-category text-base"></i>
                        {{ t('Categories') }}
                    </Link>
                    <button @click="submit" :disabled="form.processing" type="button" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary disabled:cursor-not-allowed disabled:opacity-60">
                        <i class="ti ti-device-floppy text-base"></i>
                        {{ form.processing ? t('Saving...') : t('Save Settings') }}
                    </button>
                </div>
            </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)]">
            <div class="space-y-6">
                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Publishing Defaults') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control how blog listings, excerpts, related posts, and author defaults behave.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Posts per page') }}</label>
                                <input v-model="form.posts_per_page" type="number" min="1" max="48" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Related posts count') }}</label>
                                <input v-model="form.related_posts_count" type="number" min="1" max="12" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <AppSelect v-model="form.related_posts_algorithm" :options="relatedAlgorithmOptions" :label="t('Related posts algorithm')" :placeholder="t('Select algorithm')" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto excerpt length') }}</label>
                                <input v-model="form.auto_excerpt_length" type="number" min="80" max="500" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <AppSelect v-model="form.default_author" :options="authorOptions" :label="t('Default author')" :placeholder="t('Current admin')" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Words per minute') }}</label>
                                <input v-model="form.words_per_minute" type="number" min="100" max="500" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('RSS and Sidebar') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Set the feed size and choose how the blog sidebar appears on public pages.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('RSS posts count') }}</label>
                                <input v-model="form.rss_posts_count" type="number" min="1" max="100" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <AppSelect v-model="form.blog_sidebar_position" :options="sidebarPositionOptions" :label="t('Sidebar position')" :placeholder="t('Select position')" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Social Share Buttons') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose which networks appear on blog posts and how those share buttons are displayed.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <label v-for="network in shareNetworks" :key="network.key" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <input v-model="form.social_share_networks" :value="network.key" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                <span>{{ t(network.label) }}</span>
                            </label>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <AppSelect v-model="form.social_share_blog_style" :options="shareStyleOptions" :label="t('Display style')" :placeholder="t('Select display style')" />
                            </div>
                        </div>

                        <p v-if="form.errors.social_share_networks" class="mt-3 text-sm text-danger-600">{{ form.errors.social_share_networks }}</p>
                    </section>
            </div>

            <aside class="space-y-6">
                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Visibility and Defaults') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Enable or disable the default blog behaviors editors see most often.') }}</p>
                        </div>

                        <div class="space-y-3">
                            <div v-for="setting in booleanSettings.filter((setting) => ['default_allow_comments', 'show_reading_time', 'blog_sidebar', 'social_share_show_counts'].includes(setting.key))" :key="setting.key" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="Boolean(form[setting.key])"
                                        class="app-switch"
                                        @click="form[setting.key] = !form[setting.key]"
                                    >
                                        <span class="app-switch__thumb"></span>
                                    </button>

                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ setting.title }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ setting.description }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Comment Controls') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage how blog comments are submitted, moderated, and protected from spam.') }}</p>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="setting in booleanSettings.filter((setting) => ['comments_enabled', 'comments_auto_approve_users', 'comments_allow_guests', 'comments_require_approval', 'comments_notify_admin'].includes(setting.key))"
                                :key="`comment-${setting.key}`"
                                class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                            >
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="Boolean(form[setting.key])"
                                        class="app-switch"
                                        @click="form[setting.key] = !form[setting.key]"
                                    >
                                        <span class="app-switch__thumb"></span>
                                    </button>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ setting.title }}</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ setting.description }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Polling interval seconds') }}</label>
                                <input v-model="form.comments_poll_seconds" type="number" min="10" max="300" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Akismet API key') }}</label>
                                <input
                                    v-model="form.comments_akismet_key"
                                    type="password"
                                    :placeholder="form.comments_akismet_configured ? t('Configured - leave blank to keep') : t('Optional spam filter key')"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ form.comments_akismet_configured ? t('A key is already configured. Leave this blank to keep the current value.') : t('Optional spam filter key for automated screening.') }}
                                </p>
                            </div>
                        </div>
                    </section>
            </aside>
        </div>
    </div>
</template>
