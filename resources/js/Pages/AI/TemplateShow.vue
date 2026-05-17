<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'

defineOptions({ layout: UserLayout })

interface Field {
    key: string
    label: string
    type: 'text' | 'textarea' | 'select'
    options?: string[]
    required?: boolean
}

interface Template {
    id: number
    name: string
    slug: string
    description: string
    category: string
    icon: string
    color: string
    is_premium: boolean
    fields: Field[]
}

const props = defineProps<{ template: Template }>()

const inputs = ref<Record<string, string>>({})
const output = ref('')
const loading = ref(false)
const error = ref('')
const copied = ref(false)

// Initialize inputs
if (props.template.fields) {
    props.template.fields.forEach((f: Field) => {
        inputs.value[f.key] = ''
    })
}

const canSubmit = computed(() => {
    if (loading.value) return false
    if (!props.template.fields?.length) return true
    return props.template.fields.filter((f: Field) => f.required).every((f: Field) => inputs.value[f.key]?.trim())
})

const generate = async () => {
    loading.value = true
    error.value = ''
    output.value = ''

    try {
        const res = await fetch(`/api/v1/ai/template/${props.template.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ inputs: inputs.value }),
        })

        const data = await res.json()

        if (!res.ok || !data.success) {
            error.value = data.message || 'Generation failed'
            return
        }

        output.value = data.data.content
    } catch (e: any) {
        error.value = e.message || 'Network error'
    } finally {
        loading.value = false
    }
}

const copyOutput = async () => {
    await navigator.clipboard.writeText(output.value)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
}

const downloadOutput = () => {
    const blob = new Blob([output.value], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${props.template.slug}-output.txt`
    a.click()
    URL.revokeObjectURL(url)
}

function getCookie(name: string): string {
    const v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)')
    return v ? decodeURIComponent(v.pop()!) : ''
}
</script>

<template>
    <Head :title="template.name" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm">
            <Link :href="route('ai.tools.index')" class="text-gray-500 hover:text-primary-400 transition-colors">Templates</Link>
            <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            <span class="text-gray-300">{{ template.name }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- ═══ Left: Input Panel ═══ -->
            <div class="space-y-5">
                <div class="bg-white/[0.03] border border-white/5 rounded-2xl p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center border" :style="{ background: template.color + '12', borderColor: template.color + '20' }">
                            <svg class="w-5 h-5" :style="{ color: template.color }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-white">{{ template.name }}</h1>
                            <p class="text-xs text-gray-500">{{ template.description }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="generate" class="space-y-4">
                        <div v-for="field in template.fields" :key="field.key">
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                                {{ field.label }}
                                <span v-if="field.required" class="text-danger-500">*</span>
                            </label>

                            <!-- Text input -->
                            <input
                                v-if="field.type === 'text'"
                                v-model="inputs[field.key]"
                                type="text"
                                :required="field.required"
                                class="w-full px-4 py-2.5 bg-white/[0.04] border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all"
                            />

                            <!-- Textarea -->
                            <textarea
                                v-else-if="field.type === 'textarea'"
                                v-model="inputs[field.key]"
                                :required="field.required"
                                rows="4"
                                class="w-full px-4 py-2.5 bg-white/[0.04] border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all resize-none"
                            />

                            <!-- Select -->
                            <select
                                v-else-if="field.type === 'select'"
                                v-model="inputs[field.key]"
                                :required="field.required"
                                class="w-full px-4 py-2.5 bg-white/[0.04] border border-white/10 rounded-xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-all"
                            >
                                <option value="" disabled class="bg-surface-900">Select...</option>
                                <option v-for="opt in field.options" :key="opt" :value="opt" class="bg-surface-900">{{ opt }}</option>
                            </select>
                        </div>

                        <!-- Error -->
                        <div v-if="error" class="px-4 py-3 bg-danger-500/10 border border-danger-500/20 rounded-xl text-sm text-danger-500">
                            {{ error }}
                        </div>

                        <!-- Generate button -->
                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            class="w-full py-3 bg-gradient-to-r from-primary-600 to-accent-600 text-white rounded-xl font-semibold text-sm shadow-lg shadow-primary-500/25 hover:shadow-primary-500/35 hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:translate-y-0 flex items-center justify-center gap-2"
                        >
                            <svg v-if="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                            {{ loading ? 'Generating...' : 'Generate Content' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- ═══ Right: Output Panel ═══ -->
            <div>
                <div class="bg-white/[0.03] border border-white/5 rounded-2xl p-6 min-h-[400px] flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-300">Output</h3>
                        <div v-if="output" class="flex items-center gap-2">
                            <button @click="copyOutput" class="px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-all flex items-center gap-1.5">
                                <svg v-if="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                <svg v-else class="w-3.5 h-3.5 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </button>
                            <button @click="downloadOutput" class="px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-all flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Download
                            </button>
                        </div>
                    </div>

                    <!-- Output content -->
                    <div class="flex-1">
                        <div v-if="output" class="prose prose-invert prose-sm max-w-none text-gray-300 leading-relaxed whitespace-pre-wrap">{{ output }}</div>

                        <div v-else-if="loading" class="flex items-center justify-center h-full py-16">
                            <div class="text-center">
                                <div class="relative w-16 h-16 mx-auto mb-4">
                                    <div class="absolute inset-0 border-2 border-primary-500/20 rounded-full"></div>
                                    <div class="absolute inset-0 border-2 border-transparent border-t-primary-500 rounded-full animate-spin"></div>
                                    <svg class="absolute inset-0 m-auto w-6 h-6 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                </div>
                                <p class="text-gray-500 text-sm">AI is generating your content...</p>
                            </div>
                        </div>

                        <div v-else class="flex items-center justify-center h-full py-16">
                            <div class="text-center">
                                <svg class="w-16 h-16 mx-auto mb-3 text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                <p class="text-gray-600 text-sm">Your generated content will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
