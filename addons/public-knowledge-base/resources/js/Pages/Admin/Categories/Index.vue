<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppSelect from '@/Components/AppSelect.vue'

const { t } = useTranslate()

interface Category {
  id: number
  name: string
  slug: string
  icon: string
  description: string | null
  sort_order: number
  is_active: boolean
  articles_count: number
  meta_title: string | null
  meta_desc: string | null
}

const props = defineProps<{
  categories: {
    data: Category[]
  }
}>()

const slideOver = ref(false)
const editing = ref<Category | null>(null)
const form = ref({
  name: '',
  slug: '',
  icon: '',
  description: '',
  is_active: true,
  sort_order: 0,
  meta_title: '',
  meta_desc: '',
})
const processing = ref(false)

function openCreate() {
  editing.value = null
  form.value = { name: '', slug: '', icon: '', description: '', is_active: true, sort_order: 0, meta_title: '', meta_desc: '' }
  slideOver.value = true
}

function openEdit(cat: Category) {
  editing.value = cat
  form.value = {
    name: cat.name,
    slug: cat.slug,
    icon: cat.icon || '',
    description: cat.description || '',
    is_active: cat.is_active,
    sort_order: cat.sort_order,
    meta_title: cat.meta_title || '',
    meta_desc: cat.meta_desc || '',
  }
  slideOver.value = true
}

function autoSlug() {
  if (!editing.value) {
    form.value.slug = form.value.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  }
}

function save() {
  processing.value = true
  if (editing.value) {
    router.put(`/admin/kb/categories/${editing.value.id}`, form.value, {
      onFinish: () => { processing.value = false; slideOver.value = false },
    })
  } else {
    router.post('/admin/kb/categories', form.value, {
      onFinish: () => { processing.value = false; slideOver.value = false },
    })
  }
}

function destroy(cat: Category) {
  if (!confirm(`${t('Delete')} "${cat.name}"?`)) return
  router.delete(`/admin/kb/categories/${cat.id}`)
}
</script>

<template>
  <AdminLayout :title="t('Knowledge Base Categories')">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">{{ t('Knowledge Base Categories') }}</h1>
      <button @click="openCreate" class="btn btn-primary">
        + {{ t('New Category') }}
      </button>
    </div>

    <div class="card">
      <table class="w-full table">
        <thead>
          <tr>
            <th>{{ t('Icon') }}</th>
            <th>{{ t('Name') }}</th>
            <th>{{ t('Articles') }}</th>
            <th>{{ t('Status') }}</th>
            <th>{{ t('Sort') }}</th>
            <th>{{ t('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cat in props.categories.data" :key="cat.id">
            <td><i v-if="cat.icon" :class="cat.icon" class="text-xl" /></td>
            <td class="font-medium">{{ cat.name }}</td>
            <td>{{ cat.articles_count }}</td>
            <td>
              <span :class="cat.is_active ? 'badge badge-success' : 'badge'">
                {{ cat.is_active ? t('Active') : t('Inactive') }}
              </span>
            </td>
            <td>{{ cat.sort_order }}</td>
            <td class="flex gap-2">
              <button @click="openEdit(cat)" class="btn btn-sm btn-ghost">{{ t('Edit') }}</button>
              <button @click="destroy(cat)" class="btn btn-sm btn-ghost text-red-500">{{ t('Delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="slideOver" class="fixed inset-0 z-50 flex justify-end">
      <div class="fixed inset-0 bg-black/50" @click="slideOver = false" />
      <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 p-6 overflow-y-auto shadow-xl">
        <h2 class="text-xl font-bold mb-4">
          {{ editing ? t('Edit Category') : t('New Category') }}
        </h2>
        <div class="space-y-4">
          <div>
            <label class="label">{{ t('Name') }}</label>
            <input v-model="form.name" @input="autoSlug" class="input w-full" />
          </div>
          <div>
            <label class="label">{{ t('Slug') }}</label>
            <input v-model="form.slug" class="input w-full" />
          </div>
          <div>
            <label class="label">{{ t('Icon') }} (Tabler)</label>
            <input v-model="form.icon" class="input w-full" placeholder="ti ti-rocket" />
          </div>
          <div>
            <label class="label">{{ t('Description') }}</label>
            <textarea v-model="form.description" class="input w-full" rows="3" />
          </div>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="form.is_active" />
              {{ t('Active') }}
            </label>
            <label class="flex items-center gap-2">
              {{ t('Sort Order') }}
              <input type="number" v-model.number="form.sort_order" class="input w-20" min="0" />
            </label>
          </div>
          <div>
            <label class="label">{{ t('Meta Title') }}</label>
            <input v-model="form.meta_title" class="input w-full" maxlength="160" />
          </div>
          <div>
            <label class="label">{{ t('Meta Description') }}</label>
            <textarea v-model="form.meta_desc" class="input w-full" rows="2" maxlength="320" />
          </div>
        </div>
        <div class="flex gap-3 mt-6 pt-4 border-t">
          <button @click="save" :disabled="processing" class="btn btn-primary flex-1">
            {{ processing ? t('Saving...') : t('Save') }}
          </button>
          <button @click="slideOver = false" class="btn btn-ghost">{{ t('Cancel') }}</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
