<script setup lang="ts">
import { computed } from 'vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import type { SelectOption } from '@/Components/UI/AppSelect.vue'
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

const icons = computed<[string, string][]>(() => [
    // ── General UI ────────────────────────────────────
    ['ti ti-home', t('Home')],
    ['ti ti-layout-dashboard', t('Dashboard')],
    ['ti ti-sparkles', t('AI Tools')],
    ['ti ti-news', t('Blog')],
    ['ti ti-file-text', t('Pages')],
    ['ti ti-book', t('Docs')],
    ['ti ti-help-circle', t('Help')],
    ['ti ti-message-circle', t('Contact')],
    ['ti ti-users', t('Users')],
    ['ti ti-users-group', t('Team')],
    ['ti ti-user', t('Account')],
    ['ti ti-user-circle', t('Profile')],
    ['ti ti-user-cog', t('User Settings')],
    ['ti ti-login', t('Login')],
    ['ti ti-user-plus', t('Register')],
    ['ti ti-user-check', t('Verified User')],
    ['ti ti-user-shield', t('Admin User')],
    ['ti ti-credit-card', t('Pricing')],
    ['ti ti-receipt', t('Billing')],
    ['ti ti-crown', t('Premium')],
    ['ti ti-badge', t('Badge')],
    ['ti ti-tags', t('Tags')],
    ['ti ti-gift', t('Offer')],
    ['ti ti-star', t('Featured')],
    ['ti ti-stars', t('Highlights')],
    ['ti ti-rocket', t('Launch')],
    ['ti ti-bell', t('Notification')],
    ['ti ti-mail', t('Mail')],
    ['ti ti-mood-smile', t('Smile')],
    ['ti ti-folder', t('Folder')],
    ['ti ti-files', t('Files')],
    ['ti ti-file', t('File')],
    ['ti ti-file-code', t('Code File')],
    ['ti ti-file-zip', t('Archive File')],
    ['ti ti-file-spreadsheet', t('Spreadsheet')],
    ['ti ti-archive', t('Archive')],

    // ── Arrows & Navigation ───────────────────────────
    ['ti ti-arrow-left', t('Arrow Left')],
    ['ti ti-arrow-right', t('Arrow Right')],
    ['ti ti-arrow-up', t('Arrow Up')],
    ['ti ti-arrow-down', t('Arrow Down')],
    ['ti ti-arrow-up-right', t('Arrow Up Right')],
    ['ti ti-arrow-up-left', t('Arrow Up Left')],
    ['ti ti-arrow-down-right', t('Arrow Down Right')],
    ['ti ti-arrow-down-left', t('Arrow Down Left')],
    ['ti ti-arrow-back-up', t('Arrow Back Up')],
    ['ti ti-arrow-forward-up', t('Arrow Forward Up')],
    ['ti ti-arrow-big-up', t('Arrow Big Up')],
    ['ti ti-arrow-big-down', t('Arrow Big Down')],
    ['ti ti-arrow-big-left', t('Arrow Big Left')],
    ['ti ti-arrow-big-right', t('Arrow Big Right')],
    ['ti ti-arrow-narrow-left', t('Arrow Narrow Left')],
    ['ti ti-arrow-narrow-right', t('Arrow Narrow Right')],
    ['ti ti-arrow-narrow-up', t('Arrow Narrow Up')],
    ['ti ti-arrow-narrow-down', t('Arrow Narrow Down')],
    ['ti ti-chevron-left', t('Chevron Left')],
    ['ti ti-chevron-right', t('Chevron Right')],
    ['ti ti-chevron-up', t('Chevron Up')],
    ['ti ti-chevron-down', t('Chevron Down')],
    ['ti ti-chevrons-left', t('Double Chevron Left')],
    ['ti ti-chevrons-right', t('Double Chevron Right')],
    ['ti ti-arrow-loop-left', t('Arrow Loop Left')],
    ['ti ti-arrow-loop-right', t('Arrow Loop Right')],

    // ── Navigation ────────────────────────────────────
    ['ti ti-search', t('Search')],
    ['ti ti-globe', t('Website')],
    ['ti ti-shield', t('Security')],
    ['ti ti-lock', t('Protected')],
    ['ti ti-settings', t('Settings')],
    ['ti ti-adjustments-horizontal', t('Preferences')],
    ['ti ti-palette', t('Appearance')],
    ['ti ti-menu-2', t('Menu')],
    ['ti ti-sitemap', t('Structure')],
    ['ti ti-link', t('Link')],
    ['ti ti-external-link', t('External Link')],
    ['ti ti-shopping-cart', t('Checkout')],
    ['ti ti-shopping-bag', t('Store')],
    ['ti ti-chart-bar', t('Analytics')],
    ['ti ti-chart-pie', t('Reports')],
    ['ti ti-chart-donut', t('Insights')],
    ['ti ti-chart-line', t('Chart')],
    ['ti ti-trending-up', t('Trending Up')],
    ['ti ti-trending-down', t('Trending Down')],
    ['ti ti-growth', t('Growth')],

    // ── AI & Tech ─────────────────────────────────────
    ['ti ti-brain', t('AI / Brain')],
    ['ti ti-robot', t('Robot')],
    ['ti ti-cpu', t('Processor')],
    ['ti ti-wand', t('Magic / AI')],
    ['ti ti-bolt', t('Fast / Credits')],
    ['ti ti-message-plus', t('New Chat')],
    ['ti ti-messages', t('Chat Messages')],
    ['ti ti-message-chatbot', t('Chatbot')],
    ['ti ti-ripple', t('Generate')],
    ['ti ti-markdown', t('Markdown')],
    ['ti ti-script', t('Script')],
    ['ti ti-code', t('Code')],
    ['ti ti-terminal-2', t('Terminal')],
    ['ti ti-database', t('Database')],
    ['ti ti-cloud', t('Cloud')],
    ['ti ti-server', t('Server')],
    ['ti ti-api', t('API')],
    ['ti ti-plug-connected', t('Connected')],
    ['ti ti-bulb', t('Idea')],
    ['ti ti-atom', t('Science / ML')],
    ['ti ti-flask', t('Experiment')],
    ['ti ti-chart-treemap', t('Heatmap')],

    // ── AI Generation ─────────────────────────────────
    ['ti ti-photo', t('AI Image')],
    ['ti ti-text-recognition', t('Text Recognition')],
    ['ti ti-message-2', t('Chat')],
    ['ti ti-volume', t('Voice')],
    ['ti ti-language', t('Language')],
    ['ti ti-abc', t('Text')],
    ['ti ti-keyboard', t('Input')],
    ['ti ti-text-scan-2', t('OCR')],

    // ── Content & Writing ─────────────────────────────
    ['ti ti-writing', t('Writing')],
    ['ti ti-pencil', t('Edit')],
    ['ti ti-article', t('Article')],
    ['ti ti-news', t('News')],
    ['ti ti-eraser', t('Eraser')],
    ['ti ti-paint', t('Paint')],
    ['ti ti-brush', t('Brush')],
    ['ti ti-droplet', t('Color Drop')],
    ['ti ti-photo', t('Image')],
    ['ti ti-camera', t('Camera')],
    ['ti ti-video', t('Video')],
    ['ti ti-movie', t('Movie')],
    ['ti ti-music', t('Music')],
    ['ti ti-microphone', t('Microphone')],
    ['ti ti-headphones', t('Headphones')],
    ['ti ti-volume', t('Volume')],
    ['ti ti-file-text', t('Document')],
    ['ti ti-notes', t('Notes')],
    ['ti ti-forms', t('Form')],
    ['ti ti-template', t('Template')],
    ['ti ti-clipboard-text', t('Clipboard')],
    ['ti ti-clipboard-check', t('Checklist')],

    // ── Devices ───────────────────────────────────────
    ['ti ti-device-mobile', t('Mobile')],
    ['ti ti-device-desktop', t('Desktop')],
    ['ti ti-device-laptop', t('Laptop')],
    ['ti ti-device-tablet', t('Tablet')],
    ['ti ti-device-watch', t('Watch')],
    ['ti ti-device-speaker', t('Speaker')],

    // ── Files & Folders ───────────────────────────────
    ['ti ti-apps', t('Apps')],
    ['ti ti-package', t('Package')],
    ['ti ti-box', t('Box')],
    ['ti ti-tool', t('Tool')],
    ['ti ti-folder', t('Folder')],
    ['ti ti-files', t('Files')],
    ['ti ti-file', t('File')],
    ['ti ti-file-code', t('Code File')],
    ['ti ti-file-zip', t('Archive')],
    ['ti ti-file-spreadsheet', t('Spreadsheet')],
    ['ti ti-archive', t('Archive')],

    // ── Payments & Currency ───────────────────────────
    ['ti ti-currency-dollar', t('Dollar')],
    ['ti ti-currency-euro', t('Euro')],
    ['ti ti-currency-bitcoin', t('Bitcoin')],
    ['ti ti-wallet', t('Wallet')],
    ['ti ti-cash', t('Cash')],
    ['ti ti-coins', t('Coins')],
    ['ti ti-discount-2', t('Discount')],
    ['ti ti-ticket', t('Coupon')],
    ['ti ti-percentage', t('Percentage')],
    ['ti ti-building-bank', t('Bank')],
    ['ti ti-briefcase', t('Business')],
    ['ti ti-building', t('Building')],

    // ── Time & Location ───────────────────────────────
    ['ti ti-calendar', t('Calendar')],
    ['ti ti-clock', t('Clock')],
    ['ti ti-alarm', t('Alarm')],
    ['ti ti-calendar-event', t('Event')],
    ['ti ti-calendar-time', t('Schedule')],
    ['ti ti-hourglass', t('Timer')],
    ['ti ti-history', t('History')],
    ['ti ti-map-pin', t('Location')],
    ['ti ti-world', t('World')],
    ['ti ti-flag', t('Flag')],

    // ── Social & Feedback ─────────────────────────────
    ['ti ti-heart', t('Favorite')],
    ['ti ti-thumb-up', t('Like')],
    ['ti ti-thumb-down', t('Dislike')],
    ['ti ti-mood-smile', t('Happy')],
    ['ti ti-mood-sad', t('Sad')],
    ['ti ti-mood-happy', t('Smile')],

    // ── Actions ───────────────────────────────────────
    ['ti ti-check', t('Check')],
    ['ti ti-x', t('Close')],
    ['ti ti-plus', t('Add')],
    ['ti ti-minus', t('Remove')],
    ['ti ti-trash', t('Delete')],
    ['ti ti-download', t('Download')],
    ['ti ti-upload', t('Upload')],
    ['ti ti-refresh', t('Refresh')],
    ['ti ti-send', t('Send')],
    ['ti ti-share', t('Share')],
    ['ti ti-copy', t('Copy')],
    ['ti ti-eye', t('View')],
    ['ti ti-eye-off', t('Hidden')],
    ['ti ti-adjustments', t('Adjust')],
    ['ti ti-filter', t('Filter')],
    ['ti ti-sort-ascending', t('Sort')],

    // ── Security & Auth ───────────────────────────────
    ['ti ti-key', t('API Key')],
    ['ti ti-fingerprint', t('Identity')],
    ['ti ti-certificate', t('Certificate')],
    ['ti ti-shield-lock', t('Secure')],
    ['ti ti-lock-open', t('Unlocked')],
    ['ti ti-shield-check', t('Verified')],
    ['ti ti-shield-off', t('Unprotected')],

    // ── Status ────────────────────────────────────────
    ['ti ti-help', t('Help')],
    ['ti ti-info-circle', t('Info')],
    ['ti ti-alert-triangle', t('Warning')],
    ['ti ti-alert-circle', t('Alert')],
    ['ti ti-ban', t('Forbidden')],
    ['ti ti-circle-check', t('Approved')],
    ['ti ti-circle-x', t('Rejected')],
    ['ti ti-circle-plus', t('Add Circle')],
    ['ti ti-circle-minus', t('Remove Circle')],

    // ── Layout ────────────────────────────────────────
    ['ti ti-adjustments-horizontal', t('Sliders')],
    ['ti ti-layout-list', t('List View')],
    ['ti ti-layout-grid', t('Grid View')],
    ['ti ti-layout-columns', t('Columns')],
    ['ti ti-layout-sidebar-left-collapse', t('Sidebar')],
    ['ti ti-table', t('Table')],
    ['ti ti-puzzle', t('Component')],
    ['ti ti-stack', t('Stack')],
    ['ti ti-layers-subtract', t('Layers')],
    ['ti ti-3d-cube-sphere', t('3D')],

    // ── Theme ─────────────────────────────────────────
    ['ti ti-sun', t('Light Mode')],
    ['ti ti-moon', t('Dark Mode')],
    ['ti ti-flame', t('Hot')],
    ['ti ti-snowflake', t('Cold')],
    ['ti ti-umbrella', t('Protection')],
    ['ti ti-lifebuoy', t('Support')],

    // ── Frameworks ────────────────────────────────────
    ['ti ti-brand-vue', t('Vue')],
    ['ti ti-brand-react', t('React')],
    ['ti ti-brand-laravel', t('Laravel')],
    ['ti ti-brand-tailwind', t('Tailwind')],

    // ── Dev Tools ─────────────────────────────────────
    ['ti ti-brand-github', t('GitHub')],
    ['ti ti-brand-git', t('Git')],
    ['ti ti-brand-docker', t('Docker')],
    ['ti ti-brand-nodejs', t('Node.js')],
    ['ti ti-brand-python', t('Python')],
    ['ti ti-brand-javascript', t('JavaScript')],
    ['ti ti-brand-php', t('PHP')],
    ['ti ti-brand-typescript', t('TypeScript')],
    ['ti ti-brand-html5', t('HTML5')],
    ['ti ti-brand-css3', t('CSS3')],
    ['ti ti-brand-svelte', t('Svelte')],
    ['ti ti-brand-figma', t('Figma')],

    // ── Services ──────────────────────────────────────
    ['ti ti-brand-stripe', t('Stripe')],
    ['ti ti-brand-paypal', t('PayPal')],
    ['ti ti-brand-google', t('Google')],
    ['ti ti-brand-google-drive', t('Google Drive')],
    ['ti ti-brand-vercel', t('Vercel')],
    ['ti ti-brand-sentry', t('Sentry')],
    ['ti ti-server', t('Server')],
    ['ti ti-cloud-computing', t('Cloud Compute')],
    ['ti ti-database', t('Database')],
    ['ti ti-stack-2', t('Platform')],
    ['ti ti-globe', t('Global')],
    ['ti ti-world-www', t('Web')],
    ['ti ti-network', t('Network')],

    // ── Social Platforms ──────────────────────────────
    ['ti ti-brand-facebook', t('Facebook')],
    ['ti ti-brand-twitter', t('Twitter')],
    ['ti ti-brand-instagram', t('Instagram')],
    ['ti ti-brand-youtube', t('YouTube')],
    ['ti ti-brand-linkedin', t('LinkedIn')],
    ['ti ti-brand-discord', t('Discord')],
    ['ti ti-brand-slack', t('Slack')],
    ['ti ti-brand-telegram', t('Telegram')],
    ['ti ti-brand-whatsapp', t('WhatsApp')],
    ['ti ti-brand-twilio', t('Twilio')],

    // ── Productivity ──────────────────────────────────
    ['ti ti-brand-notion', t('Notion')],
    ['ti ti-brand-trello', t('Trello')],
    ['ti ti-brand-wordpress', t('WordPress')],
    ['ti ti-brand-spotify', t('Spotify')],
    ['ti ti-brand-apple', t('Apple')],
    ['ti ti-brand-windows', t('Windows')],
    ['ti ti-brand-android', t('Android')],
    ['ti ti-brand-chrome', t('Chrome')],
    ['ti ti-brand-firefox', t('Firefox')],
    ['ti ti-brand-safari', t('Safari')],
    ['ti ti-brand-edge', t('Edge')],

    // ── Category icons (used by seeders) ─────────────
    ['ti ti-speakerphone', t('Speakerphone')],
    ['ti ti-school', t('School')],
    ['ti ti-device-gamepad', t('Gamepad')],
    ['ti ti-chart-arrows', t('Chart Arrows')],
    ['ti ti-headset', t('Headset')],
    ['ti ti-scale', t('Scale')],
    ['ti ti-checklist', t('Checklist')],
])

const options = computed<SelectOption[]>(() => {
    const seen = new Set<string>()
    const list: SelectOption[] = [{ value: '', label: t('No icon') }]
    for (const [className, label] of icons.value) {
        if (seen.has(className)) continue
        seen.add(className)
        list.push({ value: className, label, icon: className })
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
