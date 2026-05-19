<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import Layout from '@/Layouts/AppLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

declare const route: (name: string, params?: unknown) => string

const props = defineProps<{
    page: {
        title: string
        slug: string
        meta_title?: string | null
        meta_description?: string | null
    }
}>()

const form = useForm({
    password: '',
})
const { t } = useTranslate()

const submit = () => {
    form.post(route('page.password', props.page.slug), {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head>
        <title>{{ page.meta_title || page.title }}</title>
        <meta name="description" :content="page.meta_description || ''">
        <meta name="robots" content="noindex,nofollow">
    </Head>

    <Layout>
        <main class="min-h-[70vh] bg-emerald-50 px-6 py-20">
            <section class="mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <Link href="/" class="mb-6 inline-flex text-sm font-medium text-primary-600 hover:text-primary-500">{{ t('Back home') }}</Link>
                <h1 class="text-2xl font-bold text-gray-900">{{ page.title }}</h1>
                <p class="mt-2 text-sm text-gray-500">{{ t('This page is password protected.') }}</p>

                <form @submit.prevent="submit" class="mt-6 space-y-4">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('Password') }}
                        <input v-model="form.password" type="password" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-primary-400 focus:ring-primary-400" autocomplete="current-password">
                    </label>
                    <p v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</p>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">
                        {{ form.processing ? t('Checking...') : t('Unlock Page') }}
                    </button>
                </form>
            </section>
        </main>
    </Layout>
</template>
