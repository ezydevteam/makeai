<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
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

interface BlogSettingsForm {
    posts_per_page: number | string
    related_posts_count: number | string
    related_posts_algorithm: string
    auto_excerpt_length: number | string
    default_author: number | null | string
    rss_posts_count: number | string
    words_per_minute: number | string
    blog_sidebar_position: string
    show_reading_time_archive: boolean
    show_view_count_archive: boolean
    sidebar_post_position: string
    post_layout_width: string
    post_layout_centered: boolean
    show_reading_time_post: boolean
    show_view_count_post: boolean
    show_published_date_post: boolean
    show_related_posts_post: boolean
    show_comments_post: boolean
    show_post_author_post: boolean
    show_tags_post: boolean
    post_social_share_position: string
    social_share_networks: string[]
    social_share_blog_style: string
    social_share_show_counts: boolean
    comments_enabled: boolean
    comments_auto_approve_users: boolean
    comments_allow_guests: boolean
    comments_require_approval: boolean
    comments_notify_admin: boolean
    comments_poll_seconds: number | string
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

const shareStyleOptions = computed(() => [
    { value: 'icon', label: t('Icon only') },
    { value: 'icon-label', label: t('Icon and label') },
    { value: 'icon-count', label: t('Icon and count') },
])

const sidebarPositionOptions = computed(() => [
    { value: 'none', label: t('No sidebar') },
    { value: 'right', label: t('Right') },
    { value: 'left', label: t('Left') },
])

const layoutWidthOptions = computed(() => [
    { value: 'default', label: t('Default') },
    { value: 'boxed', label: t('Boxed') },
])

const socialSharePositionOptions = computed(() => [
    { value: 'hide', label: t('Hide share') },
    { value: 'top', label: t('Top only') },
    { value: 'bottom', label: t('Bottom only') },
    { value: 'both', label: t('Both') },
])

const submit = () => {
    if (form.comments_require_approval) {
        form.comments_auto_approve_users = false
    }
    form.post(route('admin.blog.settings.update'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Blog Settings')" />

    <div class="mx-auto max-w-5xl w-full space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure listing behavior, defaults, RSS output, and social sharing from one place.') }}</p>
            </div>

            <button @click="submit" :disabled="form.processing" type="button" class="shrink-0 inline-flex items-center justify-center gap-2 btn-primary-admin disabled:opacity-60">
                <i class="ti ti-device-floppy text-base"></i>
                {{ form.processing ? t('Saving...') : t('Save Settings') }}
            </button>
        </div>

        <div class="space-y-6">
            <!-- 1. General Settings Card -->
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('General Settings') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure general defaults, RSS output, and reading speed.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <AppSelect v-model="form.default_author" :options="authorOptions" :label="t('Default author')" :placeholder="t('Current admin')" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Words per minute (reading time)') }}</label>
                        <input v-model="form.words_per_minute" type="number" min="100" max="500" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('RSS posts count') }}</label>
                        <input v-model="form.rss_posts_count" type="number" min="1" max="100" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>
            </section>

            <!-- 2. Blog Archive & Category Settings Card -->
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Blog Archive & Category') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure how blog archive, search, and category listing pages display.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Posts per page') }}</label>
                        <input v-model="form.posts_per_page" type="number" min="1" max="48" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <AppSelect v-model="form.blog_sidebar_position" :options="sidebarPositionOptions" :label="t('Sidebar position')" :placeholder="t('Select position')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-5 border-t border-gray-100 dark:border-gray-800 pt-5">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show reading time') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display estimated reading time on blog archive pages.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_reading_time_archive)"
                                @update:model-value="val => form.show_reading_time_archive = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show view count') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display views count on blog archive pages.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_view_count_archive)"
                                @update:model-value="val => form.show_view_count_archive = val"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- 3. Blog Post Settings Card -->
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Blog Post Settings') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Customize layout, related items, and excerpt defaults for single post pages.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <AppSelect v-model="form.post_layout_width" :options="layoutWidthOptions" :label="t('Layout width')" :placeholder="t('Select layout width')" />
                    </div>
                    <div>
                        <AppSelect v-model="form.sidebar_post_position" :options="sidebarPositionOptions" :label="t('Sidebar position')" :placeholder="t('Select position')" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto excerpt length') }}</label>
                        <input v-model="form.auto_excerpt_length" type="number" min="80" max="500" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <AppSelect v-model="form.post_social_share_position" :options="socialSharePositionOptions" :label="t('Social share')" :placeholder="t('Select social share position')" />
                    </div>
                    <div v-if="form.show_related_posts_post">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Related posts count') }}</label>
                        <input v-model="form.related_posts_count" type="number" min="1" max="12" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div v-if="form.show_related_posts_post">
                        <AppSelect v-model="form.related_posts_algorithm" :options="relatedAlgorithmOptions" :label="t('Related posts algorithm')" :placeholder="t('Select algorithm')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-5 border-t border-gray-100 dark:border-gray-800 pt-5">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Center align layout') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Center the title, meta details, and article content on single post pages.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.post_layout_centered)"
                                @update:model-value="val => form.post_layout_centered = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show reading time') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display estimated reading time on single blog posts.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_reading_time_post)"
                                @update:model-value="val => form.show_reading_time_post = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 whitespace-normal">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show view count') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display views count on single blog posts.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_view_count_post)"
                                @update:model-value="val => form.show_view_count_post = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 whitespace-normal">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show published date') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display publication date on single blog posts.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_published_date_post)"
                                @update:model-value="val => form.show_published_date_post = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 whitespace-normal">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show post author') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display the post author details on single blog posts.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_post_author_post)"
                                @update:model-value="val => form.show_post_author_post = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 whitespace-normal">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show tags') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display the tags list on single blog posts.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_tags_post)"
                                @update:model-value="val => form.show_tags_post = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 whitespace-normal">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show related posts') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display the related posts section on single blog posts.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_related_posts_post)"
                                @update:model-value="val => form.show_related_posts_post = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 whitespace-normal">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show comments section') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display the comment section on single blog posts.') }}</p>
                            </div>

                            <AppSwitch
                                :model-value="Boolean(form.show_comments_post)"
                                @update:model-value="val => form.show_comments_post = val"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4. Social Share Buttons Card -->
            <section v-if="form.post_social_share_position !== 'hide'" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
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

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Show share counts when available') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display network share totals on post pages when a provider supports it.') }}</p>
                            </div>
                            <AppSwitch
                                :model-value="Boolean(form.social_share_show_counts)"
                                @update:model-value="val => form.social_share_show_counts = val"
                            />
                        </div>
                    </div>
                </div>

                <p v-if="form.errors.social_share_networks" class="mt-3 text-sm text-danger-600">{{ form.errors.social_share_networks }}</p>
            </section>

            <!-- 5. Comment Settings Card -->
            <section v-if="form.show_comments_post" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Comment Settings') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage how blog comments are enabled, submitted, and moderated.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable comments globally') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Allow comment features across blog posts and public sections.') }}</p>
                            </div>
                            <AppSwitch
                                :model-value="Boolean(form.comments_enabled)"
                                @update:model-value="val => form.comments_enabled = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Require approval for all') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Send every new comment into moderation before publishing.') }}</p>
                            </div>
                            <AppSwitch
                                :model-value="Boolean(form.comments_require_approval)"
                                @update:model-value="val => form.comments_require_approval = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Auto-approve logged-in users') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Trusted signed-in users bypass the moderation queue.') }}</p>
                            </div>
                            <AppSwitch
                                :model-value="form.comments_require_approval ? false : Boolean(form.comments_auto_approve_users)"
                                :disabled="Boolean(form.comments_require_approval)"
                                @update:model-value="val => form.comments_auto_approve_users = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Allow guest comments') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Let visitors comment without creating an account first.') }}</p>
                            </div>
                            <AppSwitch
                                :model-value="Boolean(form.comments_allow_guests)"
                                @update:model-value="val => form.comments_allow_guests = val"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Notify admin on new comment') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Send an admin alert whenever a new comment is submitted.') }}</p>
                            </div>
                            <AppSwitch
                                :model-value="Boolean(form.comments_notify_admin)"
                                @update:model-value="val => form.comments_notify_admin = val"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Polling interval seconds') }}</label>
                        <input v-model="form.comments_poll_seconds" type="number" min="10" max="300" class="max-w-md w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
