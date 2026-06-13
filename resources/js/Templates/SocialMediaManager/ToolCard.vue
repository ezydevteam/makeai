<template>
  <Link
    :href="'/ai-tools/' + tool.slug"
    class="group rounded-2xl border border-gray-100 dark:border-surface-700 p-6 transition-all hover:shadow-lg relative overflow-hidden"
    :style="{ background: surfaceColor }"
  >
    <!-- Platform badge (top-right) -->
    <span
      v-if="platform"
      class="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold text-white shadow-sm"
      :style="{ background: platform.color_hex }"
    >
      <i :class="platform.icon" class="text-xs"></i>
      {{ platform.label }}
    </span>

    <div class="flex items-start gap-3 mb-3">
      <span
        v-if="tool.icon"
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white"
        :style="{ background: tool.color ?? primaryColor }"
      >
        <i :class="tool.icon" class="text-lg"></i>
      </span>
      <div class="min-w-0">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1 truncate">{{ tool.name }}</h3>
      </div>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 line-clamp-2">{{ tool.description }}</p>

    <div class="flex items-center justify-between mt-auto pt-3 border-t border-gray-50 dark:border-surface-600">
      <span class="text-sm font-semibold" :style="{ color: primaryColor }">
        Generate →
      </span>
      <span v-if="tool.avg_rating" class="flex items-center gap-1 text-xs text-amber-500">
        <i class="ti ti-star-filled text-xs"></i>
        {{ tool.avg_rating }}
      </span>
    </div>
  </Link>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

interface PlatformDef {
  slug: string
  label: string
  icon: string
  color_hex: string
}

interface ToolItem {
  slug: string
  name: string
  description: string
  icon: string | null
  color: string | null
  avg_rating?: number
}

defineProps<{
  tool: ToolItem
  platform: PlatformDef | null
  primaryColor: string
  surfaceColor: string
}>()
</script>
