<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import KbLayout from './KbLayout.vue'

defineOptions({ layout: KbLayout })

const { t } = useTranslate()
const page = usePage()
const kbSettings = computed(() => (page.props as any).kbSettings || {})
const kbSlug = computed(() => kbSettings.value.public_slug || 'help')

interface Article {
  ulid: string
  title: string
  slug: string
  body: string
  excerpt: string | null
  views: number
  helpful_count: number
  not_helpful_count: number
  helpful_percent: number | null
  published_at: string
  meta_title: string | null
  meta_desc: string | null
  category?: { id: number; name: string; slug: string } | null
}

interface RelatedArticle {
  ulid: string
  title: string
  slug: string
  excerpt: string | null
}

const props = defineProps<{
  article: Article
  related: RelatedArticle[]
  userVote: number | null
  meta: { title: string; description: string }
}>()

const voted = ref<number | null>(props.userVote)
const helpfulCount = ref(props.article.helpful_count)
const notHelpfulCount = ref(props.article.not_helpful_count)
const voting = ref(false)

async function vote(val: number) {
  if (voting.value || voted.value !== null) return
  voting.value = true
  try {
    const res = await fetch(`/${kbSlug.value}/vote/${props.article.ulid}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content },
      body: JSON.stringify({ vote: val }),
    })
    const data = await res.json()
    voted.value = data.your_vote
    helpfulCount.value = data.helpful_count
    notHelpfulCount.value = data.not_helpful_count
  } catch {} finally {
    voting.value = false
  }
}

const percent = computed(() => {
  const total = helpfulCount.value + notHelpfulCount.value
  return total > 0 ? Math.round((helpfulCount.value / total) * 100) : 0
})
</script>

<template>
  <div>
    <nav class="flex gap-2 text-sm text-gray-500 mb-6">
      <a :href="`/${kbSlug}`" class="hover:text-emerald-600">{{ t('Help Center') }}</a>
      <span>/</span>
      <a v-if="props.article.category" :href="`/${kbSlug}?category=${props.article.category.slug}`" class="hover:text-emerald-600">
        {{ props.article.category.name }}
      </a>
      <span>/</span>
      <span class="text-gray-800 dark:text-gray-200">{{ props.article.title }}</span>
    </nav>

    <article class="max-w-3xl mx-auto">
      <h1 class="text-3xl font-bold mb-2">{{ props.article.title }}</h1>
      <div class="flex items-center gap-3 text-sm text-gray-500 mb-8">
        <span>{{ new Date(props.article.published_at).toLocaleDateString() }}</span>
        <span v-if="props.article.category" class="badge badge-sm">{{ props.article.category.name }}</span>
        <span>{{ props.article.views }} {{ t('views') }}</span>
      </div>

      <div v-html="props.article.body" class="prose prose-emerald max-w-none dark:prose-invert mb-10" />

      <div v-if="voted === null" class="card p-5 mb-8 text-center">
        <p class="mb-3 font-medium">{{ t('Was this article helpful?') }}</p>
        <div class="flex justify-center gap-3">
          <button @click="vote(1)" :disabled="voting" class="btn btn-ghost flex items-center gap-1">
            👍 {{ t('Yes') }} ({{ helpfulCount }})
          </button>
          <button @click="vote(-1)" :disabled="voting" class="btn btn-ghost flex items-center gap-1">
            👎 {{ t('No') }} ({{ notHelpfulCount }})
          </button>
        </div>
      </div>
      <div v-else class="card p-5 mb-8 text-center">
        <p class="text-emerald-600 font-medium">{{ t('Thanks for your feedback!') }}</p>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-3">
          <div class="bg-emerald-500 h-2 rounded-full" :style="{ width: percent + '%' }" />
        </div>
        <p class="text-xs text-gray-400 mt-1">{{ percent }}% {{ t('found this helpful') }}</p>
      </div>
    </article>

    <aside v-if="props.related.length" class="max-w-3xl mx-auto mt-8">
      <h3 class="font-semibold mb-3">{{ t('Related Articles') }}</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <a
          v-for="r in props.related"
          :key="r.ulid"
          :href="`/${kbSlug}/article/${r.slug}`"
          class="card p-4 hover:shadow-md transition"
        >
          <p class="font-medium text-sm">{{ r.title }}</p>
          <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ r.excerpt }}</p>
        </a>
      </div>
    </aside>
  </div>
</template>
