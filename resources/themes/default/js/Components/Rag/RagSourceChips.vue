<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
    sources: Array<{
        doc: string
        chunk?: number
        score?: number
        snippet?: string
        start?: number
        doc_label?: string
    }>
}>()

const { t } = useTranslate()

interface GroupedSource {
    doc: string
    isYoutube: boolean
    docLabel?: string
    items: Array<{
        chunk?: number
        score?: number
        snippet?: string
        start?: number
    }>
}

const groupedSources = computed<GroupedSource[]>(() => {
    const groups: Record<string, GroupedSource> = {}

    props.sources.forEach(s => {
        const key = s.doc
        const isYoutube = s.start !== undefined

        if (!groups[key]) {
            groups[key] = {
                doc: s.doc,
                isYoutube,
                docLabel: s.doc_label,
                items: []
            }
        }
        groups[key].items.push({
            chunk: s.chunk,
            score: s.score,
            snippet: s.snippet,
            start: s.start
        })
    })

    return Object.values(groups)
})

function youtubeUrl(doc: string, start?: number): string | null {
    return start !== undefined && doc ? `https://youtube.com/watch?v=${doc}&t=${Math.floor(start)}` : null
}

function formatTime(seconds?: number): string {
    if (seconds === undefined) return ''
    const m = Math.floor(seconds / 60)
    const s = Math.floor(seconds % 60)
    return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<template>
    <div class="flex flex-col gap-2 mt-3 text-[11px] font-medium text-gray-500 overflow-visible">
        <div 
            v-for="(group, gIdx) in groupedSources" 
            :key="gIdx" 
            class="flex flex-wrap items-center gap-1.5 overflow-visible"
        >
            <!-- Document Source Badge -->
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-150 dark:bg-surface-800 border border-surface-200/50 dark:border-surface-700/50 text-gray-700 dark:text-gray-300 shadow-sm">
                <template v-if="group.isYoutube">
                    <i class="ti ti-brand-youtube text-red-500 text-xs"></i>
                    <span>{{ group.docLabel || 'YouTube' }}</span>
                </template>
                <template v-else-if="group.docLabel">
                    <span class="font-mono text-[10px] px-1 rounded bg-surface-200 dark:bg-surface-700 text-gray-500">{{ group.docLabel }}</span>
                    <span class="truncate max-w-[120px]">{{ group.doc }}</span>
                </template>
                <template v-else>
                    <i class="ti ti-file-text text-gray-400 text-xs animate-pulse"></i>
                    <span class="truncate max-w-[150px]" :title="group.doc">{{ group.doc }}</span>
                </template>
            </span>

            <!-- Match Citations (Timestamps or Scores) -->
            <div class="flex flex-wrap gap-1 overflow-visible">
                <template v-for="(item, iIdx) in group.items" :key="iIdx">
                    <!-- YouTube Timestamps -->
                    <a
                        v-if="group.isYoutube && youtubeUrl(group.doc, item.start)"
                        :href="youtubeUrl(group.doc, item.start)!"
                        target="_blank"
                        class="inline-flex items-center px-2 py-0.5 rounded bg-red-500/10 text-red-500 hover:bg-red-500/20 no-underline transition-colors font-semibold"
                    >
                        {{ formatTime(item.start) }}
                    </a>

                    <!-- Text Similarity Citations with hover snippet tooltips -->
                    <div
                        v-else-if="item.score !== undefined"
                        class="relative group/tooltip inline-flex items-center px-2 py-0.5 rounded bg-primary-500/10 text-primary-500 border border-primary-500/20 hover:bg-primary-500/20 transition-all cursor-help font-semibold text-[10px]"
                    >
                        <span>[{{ iIdx + 1 }}]</span>

                        <!-- Tooltip -->
                        <div
                            v-if="item.snippet"
                            class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2.5 w-72 p-3.5 bg-gray-900 text-gray-100 dark:bg-slate-800 dark:text-slate-100 text-[11px] font-normal leading-relaxed rounded-xl shadow-xl border border-gray-800 dark:border-slate-700 opacity-0 pointer-events-none group-hover/tooltip:opacity-100 group-hover/tooltip:pointer-events-auto transition-all duration-200 z-50 normal-case scale-95 group-hover/tooltip:scale-100 origin-bottom"
                        >
                            <div class="font-semibold text-primary-400 mb-1.5 flex items-center gap-1">
                                <i class="ti ti-quote text-xs"></i> {{ t('Source Reference') }} #{{ iIdx + 1 }}
                            </div>
                            <div class="line-clamp-4 select-text text-gray-300 dark:text-slate-300">{{ item.snippet }}</div>
                            <!-- Arrow -->
                            <div class="absolute top-full left-1/2 -translate-x-1/2 w-2 h-2 rotate-45 bg-gray-900 dark:bg-slate-800 -translate-y-1/2 border-r border-b border-gray-800 dark:border-slate-700"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
