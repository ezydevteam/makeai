<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'

defineOptions({ layout: UserLayout })

interface Category {
    id: number
    name: string
    slug: string
    icon: string
    color: string
    active_tools_count?: number
}

interface Tool {
    id: number
    name: string
    slug: string
    description: string
    category_id: number | null
    category?: Category
    icon: string
    color: string
    is_featured: boolean
    requires_pro: boolean
}

const props = defineProps<{
    tools: Tool[]
    categories: Category[]
    featured: Tool[]
    initialCategory?: number | string
}>()

const activeCategory = ref<number | string>(props.initialCategory || 'all')
const search = ref('')

const filtered = computed(() => {
    let list = props.tools
    if (activeCategory.value !== 'all') {
        list = list.filter(t => t.category_id === activeCategory.value)
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        list = list.filter(t => t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q))
    }
    return list
})
</script>

<template>
    <Head title="AI Tools" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white mb-2">AI Tools</h1>
            <p class="text-gray-400 text-sm">Choose a tool and let AI assist you in seconds.</p>
        </div>

        <div v-if="featured.length > 0 && activeCategory === 'all' && !search" class="mb-10">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-warning-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                <h2 class="text-lg font-semibold text-white">Featured Tools</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="t in featured"
                    :key="'feat-'+t.id"
                    :href="route('ai.tools.show', t.slug)"
                    class="group relative bg-white/[0.03] border border-warning-500/20 rounded-2xl p-5 hover:border-warning-500/40 hover:bg-white/[0.05] transition-all duration-300"
                >
                    <div class="absolute top-0 right-0 p-3 opacity-20 group-hover:opacity-100 transition-opacity">
                        <svg class="w-5 h-5 text-warning-400" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    </div>

                    <div v-if="t.requires_pro" class="absolute top-3 right-3 px-2 py-0.5 bg-gradient-to-r from-primary-500/20 to-accent-500/20 text-accent-400 text-[10px] font-bold uppercase rounded-full border border-accent-500/20">PRO</div>

                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform border" :style="{ background: (t.color || '#3b82f6') + '15', borderColor: (t.color || '#3b82f6') + '30' }">
                        <i :class="[t.icon || 'ti-wand', 'text-xl']" :style="{ color: t.color || '#3b82f6' }"></i>
                    </div>

                    <h3 class="text-white font-semibold text-sm mb-1.5 group-hover:text-primary-400 transition-colors pr-8">{{ t.name }}</h3>
                    <p class="text-gray-500 text-xs leading-relaxed line-clamp-2">{{ t.description }}</p>
                </Link>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <div class="relative flex-1 max-w-md">
                <i class="ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500"></i>
                <input v-model="search" type="text" placeholder="Search tools..." class="w-full pl-10 pr-4 py-2.5 bg-white/[0.03] border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500/40 transition-all" />
            </div>

            <div class="flex gap-2 overflow-x-auto pb-2 md:pb-0 flex-1">
                <button
                    @click="activeCategory = 'all'"
                    :class="[activeCategory === 'all' ? 'bg-primary-500/15 text-primary-400 border-primary-500/30' : 'bg-white/[0.03] text-gray-400 border-white/5 hover:border-white/10 hover:text-white']"
                    class="px-4 py-2 rounded-xl text-sm font-medium border transition-all whitespace-nowrap flex items-center gap-2"
                >
                    <i class="ti-apps"></i> All
                </button>
                <button
                    v-for="cat in categories"
                    :key="'cat-'+cat.id"
                    @click="activeCategory = cat.id"
                    :class="[activeCategory === cat.id ? 'bg-primary-500/15 text-primary-400 border-primary-500/30' : 'bg-white/[0.03] text-gray-400 border-white/5 hover:border-white/10 hover:text-white']"
                    class="px-4 py-2 rounded-xl text-sm font-medium border transition-all whitespace-nowrap flex items-center gap-2"
                >
                    <i v-if="cat.icon" :class="cat.icon"></i>
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <div v-if="filtered.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="t in filtered"
                :key="t.id"
                :href="route('ai.tools.show', t.slug)"
                class="group relative bg-white/[0.03] border border-white/5 rounded-2xl p-5 hover:border-white/15 hover:bg-white/[0.05] transition-all duration-300"
            >
                <div v-if="t.requires_pro" class="absolute top-3 right-3 px-2 py-0.5 bg-gradient-to-r from-primary-500/20 to-accent-500/20 text-accent-400 text-[10px] font-bold uppercase rounded-full border border-accent-500/20">PRO</div>

                <div v-if="activeCategory === 'all' && t.category" class="mb-3">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-white/5 text-gray-400">
                        <i v-if="t.category.icon" :class="[t.category.icon, 'text-[10px]']" :style="{ color: t.category.color }"></i>
                        {{ t.category.name }}
                    </span>
                </div>

                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform border" :style="{ background: (t.color || '#64748b') + '15', borderColor: (t.color || '#64748b') + '30' }">
                    <i :class="[t.icon || 'ti-wand', 'text-xl']" :style="{ color: t.color || '#64748b' }"></i>
                </div>

                <h3 class="text-white font-semibold text-sm mb-1.5 group-hover:text-primary-400 transition-colors pr-8">{{ t.name }}</h3>
                <p class="text-gray-500 text-xs leading-relaxed line-clamp-2">{{ t.description }}</p>

                <div class="absolute bottom-5 right-5 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300">
                    <i class="ti-arrow-right text-primary-400 text-lg"></i>
                </div>
            </Link>
        </div>

        <div v-else class="text-center py-20 bg-white/[0.02] border border-white/5 rounded-2xl">
            <div class="w-16 h-16 rounded-full bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-4">
                <i class="ti-search text-2xl text-gray-500"></i>
            </div>
            <h3 class="text-white font-medium mb-1">No tools found</h3>
            <p class="text-gray-500 text-sm">We couldn't find any tools matching your search criteria.</p>
            <button @click="search = ''; activeCategory = 'all'" class="mt-4 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-sm transition-colors border border-white/5">
                Clear Filters
            </button>
        </div>
    </div>
</template>
