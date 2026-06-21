<script setup lang="ts">
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
defineProps<{ section: HomepageSection }>()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
</script>

<template>
    <section class="bg-[var(--color-bg)]" v-html="asString(section.config.content)"></section>
</template>
