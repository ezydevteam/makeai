<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichEditor from '@/Components/UI/RichEditor.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface ArticleCategory {
    id: number
    name: string
}

interface Article {
    id: number
    ulid: string
    title: string
    slug: string
    excerpt: string | null
    body: string
    status: 'draft' | 'published'
    sort_order: number
    embed_status: 'pending' | 'processing' | 'done' | 'failed'
    embed_error: string | null
    meta_title: string | null
    meta_desc: string | null
    category?: ArticleCategory | null
}

const { t } = useTranslate()

const props = defineProps<{
    article: Article | null
    categories: ArticleCategory[]
}>()

const isEdit = computed(() => props.article !== null)

const form = useForm({
    title: props.article?.title ?? '',
    slug: props.article?.slug ?? '',
    body: props.article?.body ?? '',
    excerpt: props.article?.excerpt ?? '',
    kb_category_id: props.article?.category?.id?.toString() ?? '',
    status: props.article?.status ?? 'draft',
    sort_order: props.article?.sort_order ?? 0,
    meta_title: props.article?.meta_title ?? '',
    meta_desc: props.article?.meta_desc ?? '',
})

const categoryOptions = computed(() => [
    { value: '', label: t('Uncategorized') },
    ...props.categories.map((category) => ({
        value: String(category.id),
        label: category.name,
    })),
])

const pageTitle = computed(() => isEdit.value ? t('Edit Article') : t('New Article'))
const pageDescription = computed(() => isEdit.value
    ? t('Update the article content, publish state, and search metadata.')
    : t('Create a new knowledge base article for the public help center.'))

function autoSlug() {
    if (isEdit.value) {
        return
    }

    form.slug = form.title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '')
}

function save(status: 'draft' | 'published') {
    form.status = status

    if (isEdit.value && props.article) {
        form.put(route('addon.kb.admin.articles.update', { article: props.article.ulid }), {
            preserveScroll: true,
        })
        return
    }

    form.post(route('addon.kb.admin.articles.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ pageTitle }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Knowledge Base') }}
                    </span>
                </div>
                <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    {{ pageDescription }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('addon.kb.admin.articles.index')"
                    class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>
                <button
                    type="button"
                    class="grow inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                    :disabled="form.processing"
                    @click="save('draft')"
                >
                    <i class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Draft') }}
                </button>
                <button
                    type="button"
                    class="grow inline-flex items-center gap-2 btn-primary-admin disabled:opacity-60"
                    :disabled="form.processing"
                    @click="save('published')"
                >
                    <i class="ti ti-send text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Publish') }}
                </button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Article Content') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Write the public help content and keep the URL slug stable.') }}
                        </p>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Title') }}
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                :placeholder="t('e.g. How to reset your password')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-surface-900"
                                @input="autoSlug"
                            />
                            <p v-if="form.errors.title" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.title }}</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Slug') }}
                            </label>
                            <input
                                v-model="form.slug"
                                type="text"
                                :placeholder="t('how-to-reset-your-password')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-surface-900"
                            />
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ t('Public URL') }}</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">/help/{{ form.slug || t('article-slug') }}</span>
                            </div>
                            <p v-if="form.errors.slug" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.slug }}</p>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Body') }}
                                </label>
                            </div>
                            <RichEditor v-model="form.body" />
                            <p v-if="form.errors.body" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.body }}</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Excerpt') }}
                            </label>
                            <textarea
                                v-model="form.excerpt"
                                rows="4"
                                maxlength="500"
                                :placeholder="t('Optional short summary shown in article listings')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-surface-900"
                            />
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ t('Short summary for cards and previews') }}</span>
                                <span>{{ form.excerpt.length }}/500</span>
                            </div>
                            <p v-if="form.errors.excerpt" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.excerpt }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Attributes') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Assign a category and sort order for article lists.') }}
                    </p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Category') }}
                            </label>
                            <AppSelect
                                v-model="form.kb_category_id"
                                :options="categoryOptions"
                                :placeholder="t('Uncategorized')"
                            />
                            <p v-if="form.errors.kb_category_id" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.kb_category_id }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Sort Order') }}
                            </label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                :placeholder="t('0')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-surface-900"
                            />
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Lower numbers appear first.') }}
                            </p>
                            <p v-if="form.errors.sort_order" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.sort_order }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('SEO') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Tune the search engine snippet for this article.') }}
                    </p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Meta Title') }}
                                </label>
                                <span class="text-xs text-gray-400">{{ form.meta_title.length }}/160</span>
                            </div>
                            <input
                                v-model="form.meta_title"
                                type="text"
                                maxlength="160"
                                :placeholder="t('e.g. How to Reset Your Password')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-surface-900"
                            />
                            <p v-if="form.errors.meta_title" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.meta_title }}</p>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Meta Description') }}
                                </label>
                                <span class="text-xs text-gray-400">{{ form.meta_desc.length }}/320</span>
                            </div>
                            <textarea
                                v-model="form.meta_desc"
                                rows="3"
                                maxlength="320"
                                :placeholder="t('Brief description shown in search results')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-surface-900"
                            />
                            <p v-if="form.errors.meta_desc" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.meta_desc }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="isEdit && props.article" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Embedding') }}</h2>
                    <div class="mt-4 space-y-2">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ t('Status') }}:
                            <span class="font-semibold text-gray-900 dark:text-white">{{ props.article.embed_status }}</span>
                        </p>
                        <p v-if="props.article.embed_error" class="text-sm text-red-600 dark:text-red-400">
                            {{ props.article.embed_error }}
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
