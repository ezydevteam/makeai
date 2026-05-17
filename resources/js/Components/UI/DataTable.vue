<script setup lang="ts">
import { computed } from 'vue'

export interface Column {
    key: string
    label: string
    sortable?: boolean
    class?: string
}

const props = defineProps<{
    columns: Column[]
    data: Record<string, any>[]
    emptyMessage?: string
    loading?: boolean
}>()
</script>

<template>
    <div class="bg-white/[0.03] border border-white/5 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-white/5">
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :class="col.class"
                            class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-5 py-3.5"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody v-if="!loading && data.length > 0">
                    <tr
                        v-for="(row, idx) in data"
                        :key="idx"
                        class="border-b border-white/[0.03] hover:bg-white/[0.02] transition-colors"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="col.class"
                            class="px-5 py-3.5 text-sm text-gray-300"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ row[col.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else-if="loading">
                    <tr>
                        <td :colspan="columns.length" class="px-5 py-12 text-center">
                            <div class="flex items-center justify-center gap-2 text-gray-500">
                                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Loading...
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr>
                        <td :colspan="columns.length" class="px-5 py-12 text-center text-gray-500 text-sm">
                            {{ emptyMessage ?? 'No data available' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
