<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

const props = defineProps<{
  searches_today: number
  searches_7d: number
  answer_rate: number
  published_count: number
  unanswered: string[]
  top_queries: { query: string; count: number }[]
  top_articles: {
    id: number
    title: string
    views: number
    helpful_count: number
    not_helpful_count: number
  }[]
  embed_summary: Record<string, number>
}>()
</script>

<template>
  <AdminLayout :title="t('KB Analytics')">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="card text-center">
        <p class="text-3xl font-bold text-emerald-500">{{ props.searches_today }}</p>
        <p class="text-sm text-gray-500">{{ t('Searches Today') }}</p>
      </div>
      <div class="card text-center">
        <p class="text-3xl font-bold">{{ props.searches_7d }}</p>
        <p class="text-sm text-gray-500">{{ t('Searches (7d)') }}</p>
      </div>
      <div class="card text-center">
        <p class="text-3xl font-bold text-emerald-500">{{ props.answer_rate }}%</p>
        <p class="text-sm text-gray-500">{{ t('Answer Rate') }}</p>
      </div>
      <div class="card text-center">
        <p class="text-3xl font-bold">{{ props.published_count }}</p>
        <p class="text-sm text-gray-500">{{ t('Published Articles') }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="card mb-6">
          <h3 class="font-semibold mb-3">{{ t('Unanswered Queries') }}</h3>
          <ul class="space-y-1">
            <li v-for="(q, i) in props.unanswered" :key="i" class="text-sm text-gray-600 dark:text-gray-300 py-1 border-b border-gray-100 dark:border-gray-700 last:border-0">
              {{ q }}
            </li>
          </ul>
          <p v-if="!props.unanswered.length" class="text-sm text-gray-400">{{ t('No unanswered queries') }}</p>
        </div>

        <div class="card">
          <h3 class="font-semibold mb-3">{{ t('Top Search Queries') }}</h3>
          <table class="w-full">
            <thead>
              <tr><th>{{ t('Query') }}</th><th class="text-right">{{ t('Count') }}</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in props.top_queries" :key="r.query">
                <td class="text-sm">{{ r.query }}</td>
                <td class="text-sm text-right font-medium">{{ r.count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <div class="card mb-6">
          <h3 class="font-semibold mb-3">{{ t('Top Articles') }}</h3>
          <table class="w-full">
            <thead>
              <tr><th>{{ t('Title') }}</th><th class="text-right">{{ t('Views') }}</th><th class="text-right">{{ t('Helpful') }}</th></tr>
            </thead>
            <tbody>
              <tr v-for="a in props.top_articles" :key="a.id">
                <td class="text-sm">{{ a.title }}</td>
                <td class="text-sm text-right">{{ a.views }}</td>
                <td class="text-sm text-right">
                  <span v-if="(a.helpful_count + a.not_helpful_count) > 0">
                    {{ Math.round(a.helpful_count / (a.helpful_count + a.not_helpful_count) * 100) }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h3 class="font-semibold mb-3">{{ t('Embed Status') }}</h3>
          <div class="flex gap-4 flex-wrap">
            <div v-for="(count, status) in props.embed_summary" :key="status" class="text-center">
              <p class="text-xl font-bold">{{ count }}</p>
              <p class="text-xs text-gray-500">{{ status }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
