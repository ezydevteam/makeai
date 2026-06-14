<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import RichEditor from '@/Components/RichEditor.vue'
import AppSelect from '@/Components/AppSelect.vue'

const { t } = useTranslate()

interface Article {
  id: number
  ulid: string
  title: string
  slug: string
  excerpt: string | null
  body: string
  status: string
  sort_order: number
  embed_status: string
  embed_error: string | null
  meta_title: string | null
  meta_desc: string | null
  category?: { id: number; name: string } | null
}

const props = defineProps<{
  article: Article | null
  categories: { id: number; name: string }[]
}>()

const isEdit = computed(() => !!props.article)

const form = ref({
  title: props.article?.title || '',
  slug: props.article?.slug || '',
  body: props.article?.body || '',
  excerpt: props.article?.excerpt || '',
  kb_category_id: props.article?.category?.id || '',
  status: props.article?.status || 'draft',
  sort_order: props.article?.sort_order || 0,
  meta_title: props.article?.meta_title || '',
  meta_desc: props.article?.meta_desc || '',
})

const processing = ref(false)

function autoSlug() {
  if (!isEdit.value) {
    form.value.slug = form.value.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  }
}

function save(status: string) {
  processing.value = true
  const data = { ...form.value, status }

  if (isEdit.value) {
    router.put(`/admin/kb/articles/${props.article!.ulid}`, data, {
      onFinish: () => { processing.value = false },
    })
  } else {
    router.post('/admin/kb/articles', data, {
      onFinish: () => { processing.value = false },
    })
  }
}
</script>

<template>
  <AdminLayout :title="isEdit ? t('Edit Article') : t('New Article')">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <div class="lg:col-span-3 space-y-4">
        <div>
          <input
            v-model="form.title"
            @input="autoSlug"
            type="text"
            :placeholder="t('Article Title')"
            class="input w-full text-xl font-bold"
          />
        </div>
        <div>
          <input v-model="form.slug" type="text" :placeholder="t('slug')" class="input w-full text-sm" />
          <p class="text-xs text-gray-400 mt-1">/help/article/{{ form.slug }}</p>
        </div>
        <div>
          <RichEditor v-model="form.body" />
        </div>
        <div>
          <label class="label">{{ t('Excerpt') }}</label>
          <textarea v-model="form.excerpt" class="input w-full" rows="3" maxlength="500" :placeholder="t('Optional short summary')" />
        </div>
      </div>

      <div class="space-y-4">
        <div class="card">
          <h3 class="font-semibold mb-3">{{ t('Publish') }}</h3>
          <div class="space-y-3">
            <label class="flex items-center gap-2">
              <input v-model="form.status" type="radio" value="draft" />
              {{ t('Draft') }}
            </label>
            <label class="flex items-center gap-2">
              <input v-model="form.status" type="radio" value="published" />
              {{ t('Published') }}
            </label>
            <p v-if="form.status === 'published'" class="text-xs text-emerald-600">
              {{ t('Article will be indexed for AI search automatically.') }}
            </p>
          </div>
        </div>

        <div class="card">
          <h3 class="font-semibold mb-3">{{ t('Category') }}</h3>
          <AppSelect
            v-model="form.kb_category_id"
            :options="[
              { value: '', label: t('None') },
              ...props.categories.map(c => ({ value: String(c.id), label: c.name })),
            ]"
          />
        </div>

        <div>
          <label class="label">{{ t('Sort Order') }}</label>
          <input v-model.number="form.sort_order" type="number" class="input w-full" min="0" />
        </div>

        <div class="card">
          <h3 class="font-semibold mb-3">{{ t('SEO') }}</h3>
          <div class="space-y-2">
            <div>
              <label class="label">{{ t('Meta Title') }}</label>
              <input v-model="form.meta_title" class="input w-full" maxlength="160" />
              <span class="text-xs text-gray-400">{{ form.meta_title.length }}/160</span>
            </div>
            <div>
              <label class="label">{{ t('Meta Description') }}</label>
              <textarea v-model="form.meta_desc" class="input w-full" rows="2" maxlength="320" />
              <span class="text-xs text-gray-400">{{ form.meta_desc.length }}/320</span>
            </div>
          </div>
        </div>

        <div v-if="isEdit && props.article" class="card">
          <p class="text-sm mb-1">{{ t('Embed Status') }}: <strong>{{ props.article.embed_status }}</strong></p>
          <p v-if="props.article.embed_error" class="text-xs text-red-500 mb-2">{{ props.article.embed_error }}</p>
        </div>

        <div class="flex gap-3">
          <button @click="save('draft')" :disabled="processing" class="btn btn-ghost flex-1">
            {{ t('Save Draft') }}
          </button>
          <button @click="save('published')" :disabled="processing" class="btn btn-primary flex-1">
            {{ processing ? t('Saving...') : t('Publish') }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
