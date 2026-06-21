<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

interface Category {
    id: number
    name: string
    slug: string
    description: string
    icon: string
    color: string
    access_level: string
}

interface Template {
    id: number
    name: string
    slug: string
    description: string
    icon: string
    color: string
    is_featured: boolean
    access_level: string
}

const props = defineProps<{
    category: Category
    tools: Template[]
}>()

const { t } = useTranslate()

const isProTool = (tool: Template) => {
    const level = tool.access_level || 'inherit'
    if (level === 'premium' || level.startsWith('plan:')) return true
    if (level === 'inherit' && category.access_level) {
        const catLevel = category.access_level
        return catLevel === 'premium' || catLevel.startsWith('plan:')
    }
    return false
}
</script>

<template>
    <Head :title="t(':name AI Tools', { name: category.name })" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm">
            <Link :href="route('ai.tools.index')" class="text-gray-500 hover:text-primary-400 transition-colors">{{ t('AI Tools') }}</Link>
            <i class="ti ti-chevron-right text-gray-600 text-xs"></i>
            <span class="text-gray-300">{{ category.name }}</span>
        </div>

        <!-- Header -->
        <div class="mb-10 bg-white/[0.02] border border-white/5 rounded-2xl p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                <i :class="[category.icon || 'ti ti-apps', 'text-9xl']" :style="{ color: category.color }"></i>
            </div>

            <div class="relative z-10 flex items-start gap-5">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center border shrink-0" :style="{ background: (category.color || '#3b82f6') + '15', borderColor: (category.color || '#3b82f6') + '30' }">
                    <i :class="[category.icon || 'ti ti-apps', 'text-3xl']" :style="{ color: category.color || '#3b82f6' }"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">{{ t(':name Tools', { name: category.name }) }}</h1>
                    <p class="text-gray-400 max-w-2xl leading-relaxed">{{ category.description || t('Explore our collection of AI-powered tools for :name.', { name: category.name.toLowerCase() }) }}</p>
                    <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-sm text-gray-300">
                        <i class="ti ti-layers text-primary-400"></i>
                        {{ t(':count tools available', { count: tools.length }) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Tool Grid -->
        <div v-if="tools.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="tool in tools"
                :key="tool.id"
                :href="route('ai.tools.show', tool.slug)"
                class="group relative bg-white/[0.03] border border-white/5 rounded-2xl p-5 hover:border-white/15 hover:bg-white/[0.05] transition-all duration-300 cursor-pointer shadow-sm hover:shadow-md"
            >
                <!-- Badges -->
                <div v-if="isProTool(tool)" class="absolute top-3 right-3 px-2 py-0.5 bg-gradient-to-r from-primary-500/20 to-accent-500/20 text-accent-400 text-[10px] font-bold uppercase rounded-full border border-accent-500/20 shadow-sm shadow-accent-500/10">PRO</div>
                <div v-else-if="tool.access_level === 'login'" class="absolute top-3 right-3 px-2 py-0.5 bg-sky-500/15 text-sky-400 text-[10px] font-bold uppercase rounded-full border border-sky-500/20">LOGIN</div>

                <!-- Icon -->
                <div
                    class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform border"
                    :style="{ background: (tool.color || category.color || '#64748b') + '15', borderColor: (tool.color || category.color || '#64748b') + '30' }"
                >
                    <i :class="[tool.icon || 'ti ti-wand', 'text-xl']" :style="{ color: tool.color || category.color || '#64748b' }"></i>
                </div>

                <h3 class="text-white font-semibold text-sm mb-1.5 group-hover:text-primary-400 transition-colors pr-8">{{ tool.name }}</h3>
                <p class="text-gray-500 text-xs leading-relaxed line-clamp-2">{{ tool.description }}</p>

                <!-- Arrow -->
                <div class="absolute bottom-5 right-5 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300">
                    <i class="ti ti-arrow-right text-primary-400 text-lg"></i>
                </div>
            </Link>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-20 bg-white/[0.02] border border-white/5 rounded-2xl">
            <div class="w-16 h-16 rounded-full bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-4">
                <i :class="[category.icon || 'ti ti-apps', 'text-2xl text-gray-500']"></i>
            </div>
            <h3 class="text-white font-medium mb-1">{{ t('No tools yet') }}</h3>
            <p class="text-gray-500 text-sm mb-6">{{ t("We're still building tools for this category. Check back soon!") }}</p>
            <Link :href="route('ai.tools.index')" class="px-6 py-2.5 bg-white/5 hover:bg-white/10 text-white rounded-xl text-sm font-medium transition-colors border border-white/5">
                {{ t('Explore All Tools') }}
            </Link>
        </div>
    </div>
</template>
