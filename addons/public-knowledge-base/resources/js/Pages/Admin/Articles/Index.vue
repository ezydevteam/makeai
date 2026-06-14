<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppSelect from '@/Components/AppSelect.vue'

const { t } = useTranslate()

interface Article {
  id: number
  ulid: string
  title: string
  slug: string
  status: string
  embed_status: string
  embed_error: string | null
  views: number
  helpful_count: number
  not_helpful_count: number
  helpful_percent: number | null
  created_at: string
  updated_at: string
  category?: { id: number; name: string } | null
  creator?: { name: string } | null
}

const props = defineProps<{
  articles: { data: Article[] }
  categories: { id: number; name: string }[]
  filters: Record<string, string>
}>()

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const categoryFilter = ref(props.filters.category_id || '')
const embedFilter = ref(props.filters.embed_status || '')

let debounce: ReturnType<typeof setTimeout>

watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => applyFilters(), 400)
})

watch([statusFilter, categoryFilter, embedFilter], applyFilters)

function applyFilters() {
  router.visit('/admin/kb/articles', {
    data: {
      search: search.value,
      status: statusFilter.value,
      category_id: categoryFilter.value,
      embed_status: embedFilter.value,
    },
    preserveState: true,
    replace: true,
  })
}

function reEmbed(article: Article) {
  router.post(`/admin/kb/articles/${article.ulid}/re-embed`, undefined, {
    preserveScroll: true,
  })
}

function destroy(article: Article) {
  if (!confirm(`${t('Delete')} "${article.title}"?`)) return
  router.delete(`/admin/kb/articles/${article.ulid}`)
}

const embedBadge = (status: string) => {
  return {
    pending: 'badge badge-warning',
    processing: 'badge badge-info',
    done: 'badge badge-success',
    failed: 'badge badge-error',
  }[status] || 'badge'
}
</script>

<template>
  <AdminLayout :title="t('KB Articles')">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">{{ t('Knowledge Base Articles') }}</h1>
      <a href="/admin/kb/articles/create" class="btn btn-primary">
        + {{ t('New Article') }}
      </a>
    </div>

    <div class="flex gap-3 mb-4 flex-wrap">
      <input v-model="search" type="text" :placeholder="t('Search articles...')" class="input" style="max-width: 240px" />
      <AppSelect v-model="statusFilter" :options="[
        { value: '', label: t('All Status') },
        { value: 'draft', label: t('Draft') },
        { value: 'published', label: t('Published') },
      ]" />
      <AppSelect v-model="categoryFilter" :options="[
        { value: '', label: t('All Categories') },
        ...props.categories.map(c => ({ value: String(c.id), label: c.name })),
      ]" />
      <AppSelect v-model="embedFilter" :options="[
        { value: '', label: t('All Embed') },
        { value: 'pending', label: t('Pending') },
        { value: 'processing', label: t('Processing') },
        { value: 'done', label: t('Done') },
        { value: 'failed', label: t('Failed') },
      ]" />
    </div>

    <div class="card">
      <table class="w-full table">
        <thead>
          <tr>
            <th>{{ t('Title') }}</th>
            <th>{{ t('Category') }}</th>
            <th>{{ t('Status') }}</th>
            <th>{{ t('Embed') }}</th>
            <th>{{ t('Views') }}</th>
            <th>{{ t('Votes') }}</th>
            <th>{{ t('Updated') }}</th>
            <th>{{ t('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="article in props.articles.data" :key="article.id">
            <td class="font-medium">{{ article.title }}</td>
            <td>{{ article.category?.name || '-' }}</td>
            <td>
              <span :class="article.status === 'published' ? 'badge badge-success' : 'badge'">
                {{ article.status === 'published' ? t('Published') : t('Draft') }}
              </span>
            </td>
            <td>
              <span :class="embedBadge(article.embed_status)" :title="article.embed_error || undefined">
                {{ article.embed_status }}
              </span>
            </td>
            <td>{{ article.views }}</td>
            <td>
              <span v-if="article.helpful_percent !== null" class="text-sm">
                {{ article.helpful_percent }}% {{ t('helpful') }}
              </span>
              <span v-else>-</span>
            </td>
            <td class="text-sm text-gray-500">{{ new Date(article.updated_at).toLocaleDateString() }}</td>
            <td class="flex gap-2">
              <a :href="`/admin/kb/articles/${article.ulid}/edit`" class="btn btn-sm btn-ghost">{{ t('Edit') }}</a>
              <button
                v-if="article.embed_status === 'failed' || article.embed_status === 'done'"
                @click="reEmbed(article)"
                class="btn btn-sm btn-ghost"
              >{{ t('Re-Embed') }}</button>
              <button @click="destroy(article)" class="btn btn-sm btn-ghost text-red-500">{{ t('Delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
