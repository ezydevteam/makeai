<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import Layout from '@themes/default/js/Layouts/AppLayout.vue'
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
const showPassword = ref(false)

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
        <div class="w-full min-h-[75vh] flex items-center justify-center pt-8 md:pt-12 pb-16 px-4 sm:px-6">
            <div class="w-full max-w-md">
                <!-- Card -->
                <div class="bg-white dark:bg-surface-900 border border-gray-200/40 dark:border-surface-800 rounded-3xl shadow-xl shadow-gray-100/50 dark:shadow-none p-8 md:p-10 text-center relative overflow-hidden transition-all duration-300">

                    <!-- Decorative Background Gradients -->
                    <div class="absolute -top-24 -left-24 w-48 h-48 rounded-full bg-primary-500/5 blur-3xl pointer-events-none"></div>
                    <div class="absolute -bottom-24 -right-24 w-48 h-48 rounded-full bg-accent-500/5 blur-3xl pointer-events-none"></div>

                    <!-- Header Lock Icon -->
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center text-primary-600 dark:text-primary-400 mb-6 shadow-sm border border-primary-100/30 dark:border-primary-500/10">
                        <i class="ti ti-shield-lock text-3xl"></i>
                    </div>

                    <!-- Heading & Text -->
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight mb-2">
                        {{ t('Content Protected') }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-6 max-w-sm mx-auto font-medium">
                        {{ t('The page ":title" is password protected. Enter the password below to unlock it.', { title: page.title }) }}
                    </p>

                    <!-- Error Alert -->
                    <div v-if="form.errors.password || $page.props.errors?.password" class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-left text-sm text-red-800 dark:border-red-950/30 dark:bg-red-500/10 dark:text-red-400">
                        <i class="ti ti-alert-circle text-lg shrink-0 mt-0.5"></i>
                        <div>
                            <p class="font-bold">{{ t('Access Denied') }}</p>
                            <p class="mt-0.5 text-xs opacity-90">
                                {{ form.errors.password || $page.props.errors?.password }}
                            </p>
                        </div>
                    </div>

                    <!-- Password Form -->
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-4 flex items-center text-gray-400 dark:text-gray-500 pointer-events-none">
                                <i class="ti ti-lock text-lg"></i>
                            </span>
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                class="w-full pl-11 pr-11 py-3.5 rounded-2xl border border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:border-primary-500 dark:focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 focus:outline-none transition-all"
                                :placeholder="t('Enter page password')"
                                autocomplete="current-password"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-4 flex items-center text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? t('Hide password') : t('Show password')"
                            >
                                <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-lg"></i>
                            </button>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3.5 rounded-2xl btn-primary font-bold shadow-lg shadow-primary-500/10 transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed hover:-translate-y-0.5 active:translate-y-0"
                        >
                            <span v-if="form.processing" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ form.processing ? t('Unlocking...') : t('Unlock Content') }}</span>
                        </button>
                    </form>

                    <!-- Footer Link -->
                    <div class="mt-8 border-t border-gray-200/50 dark:border-surface-800/85 pt-6">
                        <Link href="/" class="inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400 transition-colors">
                            <i class="ti ti-arrow-left text-base"></i>
                            {{ t('Back to home') }}
                        </Link>
                    </div>

                </div>
            </div>
        </div>
    </Layout>
</template>
