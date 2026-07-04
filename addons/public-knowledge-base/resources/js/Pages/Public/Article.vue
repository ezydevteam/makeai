<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import DOMPurify from 'dompurify'
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

// Admin-authored HTML — sanitized as defense-in-depth. Video embeds (iframe) are
// preserved; scripts, event handlers and javascript: URLs are stripped.
const safeBody = computed(() => DOMPurify.sanitize(props.article.body ?? '', {
    USE_PROFILES: { html: true },
    ADD_TAGS: ['iframe'],
    ADD_ATTR: ['allow', 'allowfullscreen', 'frameborder', 'scrolling', 'target'],
    FORBID_TAGS: ['style', 'form', 'input'],
    FORBID_ATTR: ['style'],
}))
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
  <div class="max-w-4xl mx-auto space-y-8">
    <!-- Breadcrumbs Navigation -->
    <nav class="flex items-center flex-wrap gap-2 text-xs tracking-wider text-gray-400 dark:text-gray-500">
      <a :href="`/${kbSlug}`" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">{{ t('Help Center') }}</a>
      <i class="ti ti-chevron-right text-[10px]"></i>
      <a v-if="props.article.category" :href="`/${kbSlug}?category=${props.article.category.slug}`" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
        {{ props.article.category.name }}
      </a>
      <i v-if="props.article.category" class="ti ti-chevron-right text-[10px]"></i>
      <span class="text-gray-600 dark:text-gray-300 truncate max-w-xs">{{ props.article.title }}</span>
    </nav>

    <!-- Main Article Body -->
    <article class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-850 rounded-3xl p-6 sm:p-10 shadow-sm">
      <header class="mb-8 pb-6 border-b border-gray-100 dark:border-surface-850">
        <!-- Category Badge -->
        <span v-if="props.article.category" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-primary-50 dark:bg-primary-950/30 text-primary-700 dark:text-primary-300 border border-primary-200/20 mb-4">
          {{ props.article.category.name }}
        </span>

        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white leading-tight mb-4">
          {{ props.article.title }}
        </h1>

        <!-- Metadata Row -->
        <div class="flex flex-wrap items-center gap-y-2 gap-x-4 text-xs font-medium text-gray-450 dark:text-gray-500">
          <span class="flex items-center gap-1">
            <i class="ti ti-calendar"></i>
            {{ new Date(props.article.published_at).toLocaleDateString(undefined, { dateStyle: 'medium' }) }}
          </span>
          <span class="hidden sm:inline">&bull;</span>
          <span class="flex items-center gap-1">
            <i class="ti ti-eye"></i>
            {{ props.article.views }} {{ t('views') }}
          </span>
        </div>
      </header>

      <!-- Article Content HTML -->
      <div v-html="safeBody" class="prose prose-gray dark:prose-invert max-w-none prose-headings:font-bold prose-a:text-primary-600 dark:prose-a:text-primary-400 hover:prose-a:underline prose-img:rounded-2xl prose-img:border prose-img:border-gray-200 dark:prose-img:border-surface-800 leading-relaxed text-gray-700 dark:text-gray-300" />

      <!-- Feedback / Helpfulness Widget -->
      <div class="mt-12 pt-8 border-t border-gray-100 dark:border-surface-850">
        <div v-if="voted === null" class="bg-gray-50/50 dark:bg-surface-850/50 border border-gray-200 dark:border-surface-800 rounded-2xl p-6 text-center max-w-md mx-auto">
          <p class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-4">{{ t('Was this article helpful?') }}</p>
          <div class="flex justify-center gap-3">
            <button
              @click="vote(1)"
              :disabled="voting"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 hover:bg-primary-50 dark:hover:bg-primary-950/20 hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-500/30 text-sm font-semibold transition-all cursor-pointer"
            >
              <i class="ti ti-thumb-up"></i>
              <span>{{ t('Yes') }}</span>
              <span class="text-xs text-gray-400">({{ helpfulCount }})</span>
            </button>
            <button
              @click="vote(-1)"
              :disabled="voting"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 hover:bg-red-50 dark:hover:bg-red-950/20 hover:text-red-650 dark:hover:text-red-400 hover:border-red-300 dark:hover:border-red-500/30 text-sm font-semibold transition-all cursor-pointer"
            >
              <i class="ti ti-thumb-down"></i>
              <span>{{ t('No') }}</span>
              <span class="text-xs text-gray-400">({{ notHelpfulCount }})</span>
            </button>
          </div>
        </div>

        <div v-else class="bg-primary-50/10 dark:bg-primary-950/5 border border-primary-500/20 rounded-2xl p-6 text-center max-w-md mx-auto">
          <p class="text-sm font-bold text-primary-700 dark:text-primary-400 flex items-center justify-center gap-2">
            <i class="ti ti-circle-check-filled"></i>
            <span>{{ t('Thanks for your feedback!') }}</span>
          </p>
          <!-- Percentage Bar -->
          <div class="w-full bg-gray-200/60 dark:bg-surface-800 rounded-full h-2 mt-4 overflow-hidden">
            <div class="bg-primary-600 dark:bg-primary-500 h-2 rounded-full transition-all duration-500" :style="{ width: percent + '%' }" />
          </div>
          <p class="text-[11px] text-gray-450 dark:text-gray-500 mt-2 font-medium">{{ percent }}% {{ t('of readers found this helpful') }}</p>
        </div>
      </div>
    </article>

    <!-- Related Articles Section -->
    <aside v-if="props.related.length" class="space-y-4">
      <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Related Articles') }}</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a
          v-for="r in props.related"
          :key="r.ulid"
          :href="`/${kbSlug}/article/${r.slug}`"
          class="group block p-5 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-850 hover:border-primary-300 dark:hover:border-primary-500/40 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300"
        >
          <p class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-1 mb-1">{{ r.title }}</p>
          <p class="text-xs text-gray-550 dark:text-gray-400 line-clamp-2 leading-relaxed">{{ r.excerpt }}</p>
        </a>
      </div>
    </aside>
  </div>
</template>
