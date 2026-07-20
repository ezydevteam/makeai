<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

interface DialCountry {
    /** ISO 3166-1 alpha-2 code, e.g. "US". */
    iso: string
    name: string
    /** International dial code without the leading "+", e.g. "1", "44", "880". */
    dial: string
}

// Reference data only — dial codes are stable. Flag glyphs are derived from the ISO
// code (regional-indicator code points) so we don't ship image assets.
const COUNTRIES: DialCountry[] = [
    { iso: 'AF', name: 'Afghanistan', dial: '93' },
    { iso: 'AL', name: 'Albania', dial: '355' },
    { iso: 'DZ', name: 'Algeria', dial: '213' },
    { iso: 'AR', name: 'Argentina', dial: '54' },
    { iso: 'AM', name: 'Armenia', dial: '374' },
    { iso: 'AU', name: 'Australia', dial: '61' },
    { iso: 'AT', name: 'Austria', dial: '43' },
    { iso: 'AZ', name: 'Azerbaijan', dial: '994' },
    { iso: 'BH', name: 'Bahrain', dial: '973' },
    { iso: 'BD', name: 'Bangladesh', dial: '880' },
    { iso: 'BY', name: 'Belarus', dial: '375' },
    { iso: 'BE', name: 'Belgium', dial: '32' },
    { iso: 'BO', name: 'Bolivia', dial: '591' },
    { iso: 'BA', name: 'Bosnia and Herzegovina', dial: '387' },
    { iso: 'BR', name: 'Brazil', dial: '55' },
    { iso: 'BG', name: 'Bulgaria', dial: '359' },
    { iso: 'KH', name: 'Cambodia', dial: '855' },
    { iso: 'CM', name: 'Cameroon', dial: '237' },
    { iso: 'CA', name: 'Canada', dial: '1' },
    { iso: 'CL', name: 'Chile', dial: '56' },
    { iso: 'CN', name: 'China', dial: '86' },
    { iso: 'CO', name: 'Colombia', dial: '57' },
    { iso: 'CR', name: 'Costa Rica', dial: '506' },
    { iso: 'HR', name: 'Croatia', dial: '385' },
    { iso: 'CY', name: 'Cyprus', dial: '357' },
    { iso: 'CZ', name: 'Czechia', dial: '420' },
    { iso: 'DK', name: 'Denmark', dial: '45' },
    { iso: 'DO', name: 'Dominican Republic', dial: '1' },
    { iso: 'EC', name: 'Ecuador', dial: '593' },
    { iso: 'EG', name: 'Egypt', dial: '20' },
    { iso: 'SV', name: 'El Salvador', dial: '503' },
    { iso: 'EE', name: 'Estonia', dial: '372' },
    { iso: 'ET', name: 'Ethiopia', dial: '251' },
    { iso: 'FI', name: 'Finland', dial: '358' },
    { iso: 'FR', name: 'France', dial: '33' },
    { iso: 'GE', name: 'Georgia', dial: '995' },
    { iso: 'DE', name: 'Germany', dial: '49' },
    { iso: 'GH', name: 'Ghana', dial: '233' },
    { iso: 'GR', name: 'Greece', dial: '30' },
    { iso: 'GT', name: 'Guatemala', dial: '502' },
    { iso: 'HN', name: 'Honduras', dial: '504' },
    { iso: 'HK', name: 'Hong Kong', dial: '852' },
    { iso: 'HU', name: 'Hungary', dial: '36' },
    { iso: 'IS', name: 'Iceland', dial: '354' },
    { iso: 'IN', name: 'India', dial: '91' },
    { iso: 'ID', name: 'Indonesia', dial: '62' },
    { iso: 'IQ', name: 'Iraq', dial: '964' },
    { iso: 'IE', name: 'Ireland', dial: '353' },
    { iso: 'IL', name: 'Israel', dial: '972' },
    { iso: 'IT', name: 'Italy', dial: '39' },
    { iso: 'JM', name: 'Jamaica', dial: '1' },
    { iso: 'JP', name: 'Japan', dial: '81' },
    { iso: 'JO', name: 'Jordan', dial: '962' },
    { iso: 'KZ', name: 'Kazakhstan', dial: '7' },
    { iso: 'KE', name: 'Kenya', dial: '254' },
    { iso: 'KW', name: 'Kuwait', dial: '965' },
    { iso: 'LV', name: 'Latvia', dial: '371' },
    { iso: 'LB', name: 'Lebanon', dial: '961' },
    { iso: 'LT', name: 'Lithuania', dial: '370' },
    { iso: 'LU', name: 'Luxembourg', dial: '352' },
    { iso: 'MO', name: 'Macau', dial: '853' },
    { iso: 'MY', name: 'Malaysia', dial: '60' },
    { iso: 'MT', name: 'Malta', dial: '356' },
    { iso: 'MX', name: 'Mexico', dial: '52' },
    { iso: 'MD', name: 'Moldova', dial: '373' },
    { iso: 'MA', name: 'Morocco', dial: '212' },
    { iso: 'NP', name: 'Nepal', dial: '977' },
    { iso: 'NL', name: 'Netherlands', dial: '31' },
    { iso: 'NZ', name: 'New Zealand', dial: '64' },
    { iso: 'NI', name: 'Nicaragua', dial: '505' },
    { iso: 'NG', name: 'Nigeria', dial: '234' },
    { iso: 'NO', name: 'Norway', dial: '47' },
    { iso: 'OM', name: 'Oman', dial: '968' },
    { iso: 'PK', name: 'Pakistan', dial: '92' },
    { iso: 'PA', name: 'Panama', dial: '507' },
    { iso: 'PY', name: 'Paraguay', dial: '595' },
    { iso: 'PE', name: 'Peru', dial: '51' },
    { iso: 'PH', name: 'Philippines', dial: '63' },
    { iso: 'PL', name: 'Poland', dial: '48' },
    { iso: 'PT', name: 'Portugal', dial: '351' },
    { iso: 'QA', name: 'Qatar', dial: '974' },
    { iso: 'RO', name: 'Romania', dial: '40' },
    { iso: 'RU', name: 'Russia', dial: '7' },
    { iso: 'SA', name: 'Saudi Arabia', dial: '966' },
    { iso: 'RS', name: 'Serbia', dial: '381' },
    { iso: 'SG', name: 'Singapore', dial: '65' },
    { iso: 'SK', name: 'Slovakia', dial: '421' },
    { iso: 'SI', name: 'Slovenia', dial: '386' },
    { iso: 'ZA', name: 'South Africa', dial: '27' },
    { iso: 'KR', name: 'South Korea', dial: '82' },
    { iso: 'ES', name: 'Spain', dial: '34' },
    { iso: 'LK', name: 'Sri Lanka', dial: '94' },
    { iso: 'SE', name: 'Sweden', dial: '46' },
    { iso: 'CH', name: 'Switzerland', dial: '41' },
    { iso: 'TW', name: 'Taiwan', dial: '886' },
    { iso: 'TZ', name: 'Tanzania', dial: '255' },
    { iso: 'TH', name: 'Thailand', dial: '66' },
    { iso: 'TN', name: 'Tunisia', dial: '216' },
    { iso: 'TR', name: 'Turkey', dial: '90' },
    { iso: 'UA', name: 'Ukraine', dial: '380' },
    { iso: 'AE', name: 'United Arab Emirates', dial: '971' },
    { iso: 'GB', name: 'United Kingdom', dial: '44' },
    { iso: 'US', name: 'United States', dial: '1' },
    { iso: 'UY', name: 'Uruguay', dial: '598' },
    { iso: 'UZ', name: 'Uzbekistan', dial: '998' },
    { iso: 'VE', name: 'Venezuela', dial: '58' },
    { iso: 'VN', name: 'Vietnam', dial: '84' },
    { iso: 'YE', name: 'Yemen', dial: '967' },
    { iso: 'ZM', name: 'Zambia', dial: '260' },
    { iso: 'ZW', name: 'Zimbabwe', dial: '263' },
]

const props = withDefaults(defineProps<{
    /** National phone number (no dial code). */
    modelValue?: string | null
    /** Selected country as ISO alpha-2. */
    country?: string | null
    /** ISO alpha-2 used when no country is selected yet. */
    defaultCountry?: string
    label?: string
    placeholder?: string
    error?: string
    required?: boolean
    disabled?: boolean
    id?: string
    name?: string
}>(), {
    modelValue: '',
    country: null,
    defaultCountry: 'US',
    label: '',
    placeholder: '',
    error: '',
    required: false,
    disabled: false,
    id: undefined,
    name: undefined,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'update:country': [value: string]
}>()

const { t } = useTranslate()

const isOpen = ref(false)
const searchQuery = ref('')
const wrapperRef = ref<HTMLElement | null>(null)
const searchRef = ref<HTMLInputElement | null>(null)

/** ISO alpha-2 → flag emoji via regional-indicator code points. */
function isoToFlag(iso: string): string {
    if (!/^[a-z]{2}$/i.test(iso)) return '🌐'
    return iso.toUpperCase().replace(/./g, (c) => String.fromCodePoint(127397 + c.charCodeAt(0)))
}

const selected = computed<DialCountry>(() => {
    const wanted = (props.country || props.defaultCountry || 'US').toUpperCase()
    return COUNTRIES.find((c) => c.iso === wanted) ?? COUNTRIES.find((c) => c.iso === 'US')!
})

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return COUNTRIES
    return COUNTRIES.filter(
        (c) => c.name.toLowerCase().includes(q) || c.dial.includes(q) || c.iso.toLowerCase().includes(q),
    )
})

function toggle() {
    if (props.disabled) return
    isOpen.value = !isOpen.value
    if (isOpen.value) {
        searchQuery.value = ''
        nextTick(() => searchRef.value?.focus())
    }
}

function pick(country: DialCountry) {
    emit('update:country', country.iso)
    isOpen.value = false
}

function onNumberInput(e: Event) {
    // Keep digits and common separators; strip letters and stray symbols.
    const raw = (e.target as HTMLInputElement).value
    const cleaned = raw.replace(/[^\d\s().+-]/g, '')
    emit('update:modelValue', cleaned)
}

function clickOutside(e: MouseEvent) {
    if (!wrapperRef.value?.contains(e.target as Node)) {
        isOpen.value = false
    }
}

onMounted(() => document.addEventListener('click', clickOutside))
onBeforeUnmount(() => document.removeEventListener('click', clickOutside))
</script>

<template>
    <div ref="wrapperRef" class="relative min-w-0 w-full">
        <label v-if="label" :for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <div
            class="flex items-stretch rounded-xl border border-gray-200 bg-white transition-colors focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/10 dark:border-gray-700 dark:bg-gray-800"
            :class="{ 'opacity-60 pointer-events-none': disabled }"
        >
            <!-- Dial code selector -->
            <button
                type="button"
                :disabled="disabled"
                class="flex shrink-0 items-center gap-1.5 rounded-l-xl border-r border-gray-200 px-3 text-sm text-gray-900 outline-none hover:bg-gray-50 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700/60"
                :aria-expanded="isOpen"
                @click.stop="toggle"
            >
                <span class="text-base leading-none">{{ isoToFlag(selected.iso) }}</span>
                <span class="font-medium">+{{ selected.dial }}</span>
                <svg class="h-3 w-3 text-gray-400 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <!-- National number -->
            <input
                :id="id"
                :name="name"
                :value="modelValue ?? ''"
                type="tel"
                inputmode="tel"
                autocomplete="tel-national"
                :placeholder="placeholder || t('Phone number')"
                :disabled="disabled"
                class="w-full min-w-0 flex-1 rounded-r-xl bg-transparent px-4 py-2.5 text-sm text-gray-900 outline-none placeholder:text-gray-400 dark:text-white"
                @input="onNumberInput"
            />
        </div>

        <!-- Country dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute z-40 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900"
                @click.stop
            >
                <div class="border-b border-gray-100 p-2 dark:border-surface-700">
                    <input
                        ref="searchRef"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('Search country or code...')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm outline-none focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                </div>

                <div class="max-h-64 overflow-y-auto">
                    <button
                        v-for="c in filtered"
                        :key="c.iso"
                        type="button"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors hover:bg-primary-50 dark:hover:bg-primary-900/20"
                        :class="c.iso === selected.iso
                            ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                            : 'text-gray-700 dark:text-gray-200'"
                        @click="pick(c)"
                    >
                        <span class="text-base leading-none">{{ isoToFlag(c.iso) }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ c.name }}</span>
                        <span class="shrink-0 text-gray-400 dark:text-gray-500">+{{ c.dial }}</span>
                    </button>

                    <div v-if="filtered.length === 0" class="px-3 py-4 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ t('No results found') }}
                    </div>
                </div>
            </div>
        </Transition>

        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
    </div>
</template>
