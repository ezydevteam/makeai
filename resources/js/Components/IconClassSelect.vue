<script setup lang="ts">
import { computed } from 'vue'
import AppSelect from '@/Components/AppSelect.vue'
import type { SelectOption } from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = withDefaults(defineProps<{
    modelValue?: string
    label?: string
    error?: string
    placeholder?: string
}>(), {
    modelValue: '',
    label: '',
    error: '',
    placeholder: '',
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const { t } = useTranslate()

const icons: [string, string][] = [
    // ── General UI ────────────────────────────────────
    ['ti ti-home', 'Home'],
    ['ti ti-layout-dashboard', 'Dashboard'],
    ['ti ti-sparkles', 'AI Tools'],
    ['ti ti-news', 'Blog'],
    ['ti ti-file-text', 'Pages'],
    ['ti ti-book', 'Docs'],
    ['ti ti-help-circle', 'Help'],
    ['ti ti-message-circle', 'Contact'],
    ['ti ti-users', 'Users'],
    ['ti ti-user', 'Account'],
    ['ti ti-login', 'Login'],
    ['ti ti-user-plus', 'Register'],
    ['ti ti-credit-card', 'Pricing'],
    ['ti ti-receipt', 'Billing'],
    ['ti ti-tags', 'Tags'],
    ['ti ti-gift', 'Offer'],
    ['ti ti-star', 'Featured'],
    ['ti ti-rocket', 'Launch'],
    ['ti ti-bell', 'Notification'],
    ['ti ti-mail', 'Mail'],
    ['ti ti-mood-smile', 'Smile'],
    ['ti ti-folder', 'Folder'],
    ['ti ti-files', 'Files'],
    ['ti ti-file', 'File'],
    ['ti ti-file-code', 'Code File'],
    ['ti ti-file-zip', 'Archive File'],
    ['ti ti-file-spreadsheet', 'Spreadsheet'],
    ['ti ti-archive', 'Archive'],

    // ── Navigation ────────────────────────────────────
    ['ti ti-search', 'Search'],
    ['ti ti-globe', 'Website'],
    ['ti ti-shield', 'Security'],
    ['ti ti-lock', 'Protected'],
    ['ti ti-settings', 'Settings'],
    ['ti ti-palette', 'Appearance'],
    ['ti ti-menu-2', 'Menu'],
    ['ti ti-link', 'Link'],
    ['ti ti-external-link', 'External Link'],
    ['ti ti-shopping-cart', 'Checkout'],
    ['ti ti-chart-bar', 'Analytics'],
    ['ti ti-chart-line', 'Chart'],
    ['ti ti-trending-up', 'Trending Up'],
    ['ti ti-trending-down', 'Trending Down'],
    ['ti ti-growth', 'Growth'],

    // ── AI & Tech ─────────────────────────────────────
    ['ti ti-brain', 'AI / Brain'],
    ['ti ti-robot', 'Robot'],
    ['ti ti-cpu', 'Processor'],
    ['ti ti-wand', 'Magic / AI'],
    ['ti ti-bolt', 'Fast / Credits'],
    ['ti ti-message-plus', 'New Chat'],
    ['ti ti-messages', 'Chat Messages'],
    ['ti ti-ripple', 'Generate'],
    ['ti ti-markdown', 'Markdown'],
    ['ti ti-script', 'Script'],
    ['ti ti-code', 'Code'],
    ['ti ti-terminal-2', 'Terminal'],
    ['ti ti-database', 'Database'],
    ['ti ti-cloud', 'Cloud'],
    ['ti ti-server', 'Server'],
    ['ti ti-api', 'API'],
    ['ti ti-plug-connected', 'Connected'],
    ['ti ti-bulb', 'Idea'],
    ['ti ti-atom', 'Science / ML'],
    ['ti ti-flask', 'Experiment'],
    ['ti ti-chart-treemap', 'Heatmap'],

    // ── AI Generation ─────────────────────────────────
    ['ti ti-photo', 'AI Image'],
    ['ti ti-text-recognition', 'Text Recognition'],
    ['ti ti-message-2', 'Chat'],
    ['ti ti-volume', 'Voice'],
    ['ti ti-language', 'Language'],
    ['ti ti-abc', 'Text'],
    ['ti ti-keyboard', 'Input'],

    // ── Content & Writing ─────────────────────────────
    ['ti ti-writing', 'Writing'],
    ['ti ti-pencil', 'Edit'],
    ['ti ti-eraser', 'Eraser'],
    ['ti ti-paint', 'Paint'],
    ['ti ti-brush', 'Brush'],
    ['ti ti-droplet', 'Color Drop'],
    ['ti ti-photo', 'Image'],
    ['ti ti-camera', 'Camera'],
    ['ti ti-video', 'Video'],
    ['ti ti-movie', 'Movie'],
    ['ti ti-music', 'Music'],
    ['ti ti-microphone', 'Microphone'],
    ['ti ti-headphones', 'Headphones'],
    ['ti ti-volume', 'Volume'],
    ['ti ti-file-text', 'Document'],
    ['ti ti-forms', 'Form'],
    ['ti ti-template', 'Template'],
    ['ti ti-clipboard-text', 'Clipboard'],
    ['ti ti-clipboard-check', 'Checklist'],

    // ── Devices ───────────────────────────────────────
    ['ti ti-device-mobile', 'Mobile'],
    ['ti ti-device-desktop', 'Desktop'],
    ['ti ti-device-laptop', 'Laptop'],
    ['ti ti-device-tablet', 'Tablet'],
    ['ti ti-device-watch', 'Watch'],
    ['ti ti-device-speaker', 'Speaker'],

    // ── Files & Folders ───────────────────────────────
    ['ti ti-apps', 'Apps'],
    ['ti ti-package', 'Package'],
    ['ti ti-box', 'Box'],
    ['ti ti-tool', 'Tool'],
    ['ti ti-folder', 'Folder'],
    ['ti ti-files', 'Files'],
    ['ti ti-file', 'File'],
    ['ti ti-file-code', 'Code File'],
    ['ti ti-file-zip', 'Archive'],
    ['ti ti-file-spreadsheet', 'Spreadsheet'],
    ['ti ti-archive', 'Archive'],

    // ── Payments & Currency ───────────────────────────
    ['ti ti-currency-dollar', 'Dollar'],
    ['ti ti-currency-euro', 'Euro'],
    ['ti ti-currency-bitcoin', 'Bitcoin'],
    ['ti ti-wallet', 'Wallet'],
    ['ti ti-cash', 'Cash'],
    ['ti ti-building-bank', 'Bank'],
    ['ti ti-briefcase', 'Business'],
    ['ti ti-building', 'Building'],

    // ── Time & Location ───────────────────────────────
    ['ti ti-calendar', 'Calendar'],
    ['ti ti-clock', 'Clock'],
    ['ti ti-alarm', 'Alarm'],
    ['ti ti-hourglass', 'Timer'],
    ['ti ti-history', 'History'],
    ['ti ti-map-pin', 'Location'],
    ['ti ti-world', 'World'],
    ['ti ti-flag', 'Flag'],

    // ── Social & Feedback ─────────────────────────────
    ['ti ti-heart', 'Favorite'],
    ['ti ti-thumb-up', 'Like'],
    ['ti ti-thumb-down', 'Dislike'],
    ['ti ti-mood-smile', 'Happy'],
    ['ti ti-mood-sad', 'Sad'],
    ['ti ti-mood-happy', 'Smile'],

    // ── Actions ───────────────────────────────────────
    ['ti ti-check', 'Check'],
    ['ti ti-x', 'Close'],
    ['ti ti-plus', 'Add'],
    ['ti ti-minus', 'Remove'],
    ['ti ti-trash', 'Delete'],
    ['ti ti-download', 'Download'],
    ['ti ti-upload', 'Upload'],
    ['ti ti-share', 'Share'],
    ['ti ti-copy', 'Copy'],
    ['ti ti-eye', 'View'],
    ['ti ti-eye-off', 'Hidden'],
    ['ti ti-adjustments', 'Adjust'],
    ['ti ti-filter', 'Filter'],
    ['ti ti-sort-ascending', 'Sort'],

    // ── Security & Auth ───────────────────────────────
    ['ti ti-key', 'API Key'],
    ['ti ti-fingerprint', 'Identity'],
    ['ti ti-shield-lock', 'Secure'],
    ['ti ti-lock-open', 'Unlocked'],
    ['ti ti-shield-check', 'Verified'],
    ['ti ti-shield-off', 'Unprotected'],

    // ── Status ────────────────────────────────────────
    ['ti ti-help', 'Help'],
    ['ti ti-info-circle', 'Info'],
    ['ti ti-alert-triangle', 'Warning'],
    ['ti ti-alert-circle', 'Alert'],
    ['ti ti-ban', 'Forbidden'],
    ['ti ti-circle-check', 'Approved'],
    ['ti ti-circle-x', 'Rejected'],
    ['ti ti-circle-plus', 'Add Circle'],
    ['ti ti-circle-minus', 'Remove Circle'],

    // ── Layout ────────────────────────────────────────
    ['ti ti-sliders', 'Sliders'],
    ['ti ti-layout-list', 'List View'],
    ['ti ti-layout-grid', 'Grid View'],
    ['ti ti-layout-columns', 'Columns'],
    ['ti ti-table', 'Table'],
    ['ti ti-puzzle', 'Component'],
    ['ti ti-stack', 'Stack'],
    ['ti ti-layers-subtract', 'Layers'],
    ['ti ti-3d-cube-sphere', '3D'],

    // ── Theme ─────────────────────────────────────────
    ['ti ti-sun', 'Light Mode'],
    ['ti ti-moon', 'Dark Mode'],
    ['ti ti-flame', 'Hot'],
    ['ti ti-snowflake', 'Cold'],
    ['ti ti-umbrella', 'Protection'],
    ['ti ti-lifebuoy', 'Support'],

    // ── Frameworks ────────────────────────────────────
    ['ti ti-brand-vue', 'Vue'],
    ['ti ti-brand-react', 'React'],
    ['ti ti-brand-laravel', 'Laravel'],
    ['ti ti-brand-tailwind', 'Tailwind'],

    // ── Dev Tools ─────────────────────────────────────
    ['ti ti-brand-github', 'GitHub'],
    ['ti ti-brand-git', 'Git'],
    ['ti ti-brand-docker', 'Docker'],
    ['ti ti-brand-nodejs', 'Node.js'],
    ['ti ti-brand-python', 'Python'],
    ['ti ti-brand-javascript', 'JavaScript'],
    ['ti ti-brand-php', 'PHP'],
    ['ti ti-brand-typescript', 'TypeScript'],
    ['ti ti-brand-html5', 'HTML5'],
    ['ti ti-brand-css3', 'CSS3'],
    ['ti ti-brand-svelte', 'Svelte'],
    ['ti ti-brand-figma', 'Figma'],

    // ── Services ──────────────────────────────────────
    ['ti ti-brand-stripe', 'Stripe'],
    ['ti ti-brand-paypal', 'PayPal'],
    ['ti ti-brand-google', 'Google'],
    ['ti ti-brand-google-drive', 'Google Drive'],
    ['ti ti-brand-vercel', 'Vercel'],
    ['ti ti-brand-sentry', 'Sentry'],
    ['ti ti-server', 'Server'],
    ['ti ti-cloud-computing', 'Cloud Compute'],
    ['ti ti-database', 'Database'],
    ['ti ti-stack-2', 'Platform'],
    ['ti ti-globe', 'Global'],
    ['ti ti-world-www', 'Web'],
    ['ti ti-network', 'Network'],

    // ── Social Platforms ──────────────────────────────
    ['ti ti-brand-facebook', 'Facebook'],
    ['ti ti-brand-twitter', 'Twitter'],
    ['ti ti-brand-instagram', 'Instagram'],
    ['ti ti-brand-youtube', 'YouTube'],
    ['ti ti-brand-linkedin', 'LinkedIn'],
    ['ti ti-brand-discord', 'Discord'],
    ['ti ti-brand-slack', 'Slack'],
    ['ti ti-brand-telegram', 'Telegram'],
    ['ti ti-brand-whatsapp', 'WhatsApp'],
    ['ti ti-brand-twilio', 'Twilio'],

    // ── Productivity ──────────────────────────────────
    ['ti ti-brand-notion', 'Notion'],
    ['ti ti-brand-trello', 'Trello'],
    ['ti ti-brand-wordpress', 'WordPress'],
    ['ti ti-brand-spotify', 'Spotify'],
    ['ti ti-brand-apple', 'Apple'],
    ['ti ti-brand-windows', 'Windows'],
    ['ti ti-brand-android', 'Android'],
    ['ti ti-brand-chrome', 'Chrome'],
    ['ti ti-brand-firefox', 'Firefox'],
    ['ti ti-brand-safari', 'Safari'],
    ['ti ti-brand-edge', 'Edge'],

    // ── Category icons (used by seeders) ─────────────
    ['ti ti-speakerphone', 'Speakerphone'],
    ['ti ti-school', 'School'],
    ['ti ti-device-gamepad', 'Gamepad'],
    ['ti ti-chart-arrows', 'Chart Arrows'],
    ['ti ti-headset', 'Headset'],
    ['ti ti-scale', 'Scale'],
    ['ti ti-checklist', 'Checklist'],
]

const options = computed<SelectOption[]>(() => {
    const seen = new Set<string>()
    const list: SelectOption[] = [{ value: '', label: t('No icon') }]
    for (const [className, label] of icons) {
        if (seen.has(className)) continue
        seen.add(className)
        list.push({ value: className, label: t(label), icon: className })
    }
    return list
})

const selected = computed({
    get: () => props.modelValue ?? '',
    set: (val) => emit('update:modelValue', String(val ?? '')),
})
</script>

<template>
    <AppSelect
        v-model="selected"
        :options="options"
        :label="label"
        :placeholder="placeholder || t('Search icons...')"
        :error="error"
        live-search
        :size="9"
    />
</template>
