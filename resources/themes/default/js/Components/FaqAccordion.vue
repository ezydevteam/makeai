<script setup lang="ts">
import { ref, onMounted } from 'vue';

const props = defineProps<{
    content: string
}>();

const items = ref<{ question: string; answer: string; isOpen: boolean }[]>([]);

onMounted(() => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(props.content, 'text/html');
    const h3s = doc.querySelectorAll('h3');

    h3s.forEach((h3) => {
        let answer = '';
        let next = h3.nextElementSibling;
        while (next && next.tagName !== 'H3') {
            answer += next.outerHTML;
            next = next.nextElementSibling;
        }
        items.value.push({
            question: h3.textContent || '',
            answer: answer,
            isOpen: false
        });
    });

    // If no H3s found, just show the content
    if (items.value.length === 0) {
        items.value.push({
            question: 'General Information',
            answer: props.content,
            isOpen: true
        });
    }
});

const toggle = (index: number) => {
    items.value[index].isOpen = !items.value[index].isOpen;
};
</script>

<template>
    <div class="space-y-4">
        <div v-for="(item, index) in items" :key="index" class="border border-gray-100 rounded-xl overflow-hidden transition-all" :class="{'border-primary-500 shadow-xl shadow-primary-600/5': item.isOpen}">
            <button
                @click="toggle(index)"
                class="w-full flex items-center justify-between p-4 text-left bg-white hover:bg-gray-50 transition-colors"
            >
                <span class="text-base font-semibold text-gray-900 leading-tight">{{ item.question }}</span>
                <span class="flex-shrink-0 ml-4">
                    <svg
                        class="w-4 h-4 text-gray-400 transition-transform duration-300"
                        :class="{'rotate-180 text-primary-600': item.isOpen}"
                        fill="none" viewBox="0 0 20 20" stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 9l-7 7-7-7" />
                    </svg>
                </span>
            </button>
            <div
                v-show="item.isOpen"
                class="px-4 pb-4 bg-white"
            >
                <div v-html="item.answer" class="prose prose-indigo max-w-none text-gray-600 leading-relaxed"></div>
            </div>
        </div>
    </div>
</template>
