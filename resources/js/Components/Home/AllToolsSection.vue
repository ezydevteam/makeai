<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import AllToolsSectionInner from '@/Components/AllToolsSection.vue'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface ToolItem { slug: string; name: string; description: string; icon: string | null; color: string | null; category: string | null; usage_count: number; avg_rating: number | null; is_featured: boolean }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
defineProps<{ section: HomepageSection; allTools?: ToolItem[]; allToolCategories?: string[] }>()
const { t } = useTranslate()
</script>

<template>
    <section class="bg-[var(--color-bg)] py-24">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-12 text-center">
                <h2 v-if="section.config.title" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ section.config.title }}</h2>
                <p v-if="section.config.subtitle" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ section.config.subtitle }}</p>
            </div>
            <AllToolsSectionInner :all-tools="allTools || []" :all-tool-categories="allToolCategories || []" />
        </div>
    </section>
</template>
