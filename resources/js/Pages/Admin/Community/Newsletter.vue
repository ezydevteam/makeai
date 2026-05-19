<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import RichEditor from '@/Components/RichEditor.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    subscribers: any,
    campaigns: any,
    stats: any,
    settings: any,
    configuredSecrets: Record<string, boolean>
}>();

const activeTab = ref('subscribers');

const showCampaignModal = ref(false);
const campaignForm = useForm({
    subject: '',
    audience: 'subscribers',
    content: ''
});

const audienceOptions = [
    { value: 'subscribers', label: 'Newsletter subscribers', countKey: 'active' },
    { value: 'users_all', label: 'All opted-in users', countKey: 'users_all' },
    { value: 'users_active', label: 'Active users', countKey: 'users_active' },
    { value: 'users_inactive', label: 'Inactive users', countKey: 'users_inactive' },
    { value: 'users_pro', label: 'Pro users', countKey: 'users_pro' },
    { value: 'users_free', label: 'Free users', countKey: 'users_free' },
];

const audienceLabel = (audience: string) => audienceOptions.find((option) => option.value === audience)?.label || 'Newsletter subscribers';

const submitCampaign = () => {
    campaignForm.post(route('admin.newsletter.campaign.store'), {
        onSuccess: () => {
            showCampaignModal.value = false;
            campaignForm.reset();
        }
    });
};

const sendCampaign = (id: number) => {
    if (confirm('Send this campaign to all active subscribers?')) {
        useForm({}).post(route('admin.newsletter.campaign.send', id));
    }
};

const deleteSubscriber = (id: number) => {
    if (confirm('Remove this subscriber?')) {
        useForm({}).delete(route('admin.newsletter.subscriber.delete', id));
    }
};

const settingsForm = useForm({
    newsletter_driver: props.settings.newsletter_driver || 'internal',
    mailchimp_api_key: '',
    mailchimp_server_prefix: props.settings.mailchimp_server_prefix || '',
    mailchimp_list_id: props.settings.mailchimp_list_id || '',
    mailchimp_double_optin: props.settings.mailchimp_double_optin ?? false,
    mailchimp_tags: props.settings.mailchimp_tags || '',
    
    newsletter_enable_popup: props.settings.newsletter_enable_popup ?? false,
    newsletter_popup_trigger: props.settings.newsletter_popup_trigger || 'time_delay',
    newsletter_popup_trigger_value: props.settings.newsletter_popup_trigger_value || '5',
    newsletter_popup_title: props.settings.newsletter_popup_title || 'Subscribe to our Newsletter',
    newsletter_popup_description: props.settings.newsletter_popup_description || 'Get the latest updates delivered directly to your inbox.',
    newsletter_popup_placeholder: props.settings.newsletter_popup_placeholder || 'Enter your email address',
    newsletter_popup_submit_text: props.settings.newsletter_popup_submit_text || 'Subscribe',
    newsletter_popup_success_message: props.settings.newsletter_popup_success_message || 'Thanks for subscribing!',
    newsletter_popup_bg_color: props.settings.newsletter_popup_bg_color || '#ffffff',
    newsletter_popup_show_mobile: props.settings.newsletter_popup_show_mobile ?? true,
    newsletter_popup_cookie_duration: props.settings.newsletter_popup_cookie_duration || 30,
    newsletter_popup_hide_for_logged_in: props.settings.newsletter_popup_hide_for_logged_in ?? true,
});

const saveSettings = () => {
    settingsForm.post(route('admin.newsletter.settings.save'));
};
</script>

<template>
    <Head title="Newsletter — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Newsletter System</h1>
                <p class="text-sm text-gray-500 mt-1">Manage subscribers, campaigns, and Mailchimp integration.</p>
            </div>
            <button v-if="activeTab === 'campaigns'" @click="showCampaignModal = true" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20">
                CREATE CAMPAIGN
            </button>
            <button v-if="activeTab === 'settings' || activeTab === 'popup'" @click="saveSettings" :disabled="settingsForm.processing" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20 disabled:opacity-50">
                SAVE SETTINGS
            </button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase mb-2">Total Subscribers</p>
                <p class="text-3xl font-black text-gray-900">{{ stats.total }}</p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase mb-2">Active</p>
                <p class="text-3xl font-black text-success-600">{{ stats.active }}</p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase mb-2">Unsubscribed</p>
                <p class="text-3xl font-black text-danger-600">{{ stats.unsubscribed }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex space-x-6 border-b border-gray-200 mb-6">
            <button @click="activeTab = 'subscribers'" :class="[activeTab === 'subscribers' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                Subscribers
            </button>
            <button @click="activeTab = 'campaigns'" :class="[activeTab === 'campaigns' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                Campaigns
            </button>
            <button @click="activeTab = 'settings'" :class="[activeTab === 'settings' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                Integrations (Mailchimp)
            </button>
            <button @click="activeTab = 'popup'" :class="[activeTab === 'popup' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                Popup Settings
            </button>
        </div>

        <div v-if="activeTab === 'subscribers'">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">Email</th>
                                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">Date</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="sub in subscribers.data" :key="sub.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900">{{ sub.email }}</div>
                                    <div class="text-[10px] text-gray-400">{{ sub.name || 'Anonymous' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="sub.status === 'subscribed' ? 'bg-success-50 text-success-600 border-success-100' : 'bg-danger-50 text-danger-600 border-danger-100'" class="px-2 py-0.5 text-[10px] font-bold rounded-full border">
                                        {{ sub.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    {{ new Date(sub.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="deleteSubscriber(sub.id)" class="text-gray-400 hover:text-danger-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="activeTab === 'campaigns'">
            <div class="space-y-4">
                <div v-for="camp in campaigns.data" :key="camp.id" class="p-6 bg-white rounded-2xl border border-gray-100 shadow-sm group">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3 mb-2">
                                <h4 class="text-base font-bold text-gray-900">{{ camp.subject }}</h4>
                                <span :class="{
                                    'bg-success-50 text-success-600': camp.status === 'sent',
                                    'bg-blue-50 text-blue-600': camp.status === 'sending',
                                    'bg-warning-50 text-warning-600': camp.status === 'draft'
                                }" class="px-2 py-0.5 text-[10px] font-bold rounded-md">
                                    {{ camp.status.toUpperCase() }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">Created: {{ new Date(camp.created_at).toLocaleDateString() }} <span v-if="camp.sent_at">• Sent: {{ new Date(camp.sent_at).toLocaleDateString() }}</span> • {{ audienceLabel(camp.audience) }} • {{ camp.recipient_count }} recipients • {{ camp.sent_count || 0 }} sent • {{ camp.failed_count || 0 }} failed</p>
                        </div>
                        <button v-if="camp.status === 'draft'" @click="sendCampaign(camp.id)" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-xs font-bold hover:bg-primary-500 transition-all">
                            QUEUE SEND
                        </button>
                        <span v-else-if="camp.status === 'sending'" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold">
                            SENDING IN QUEUE
                        </span>
                    </div>
                </div>
                <div v-if="campaigns.data.length === 0" class="p-10 bg-white rounded-2xl border border-gray-100 shadow-sm text-center text-sm text-gray-400">
                    No campaigns found.
                </div>
            </div>
            <Pagination class="mt-6" :links="campaigns.links" />
        </div>

        <div v-if="activeTab === 'settings'">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl">
                <form @submit.prevent="saveSettings" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-1">Newsletter Driver</label>
                        <p class="text-xs text-gray-500 mb-3">Choose how to handle new newsletter subscribers.</p>
                        <select v-model="settingsForm.newsletter_driver" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            <option value="internal">Internal Only (Local DB)</option>
                            <option value="mailchimp">Mailchimp Only</option>
                            <option value="both">Both (Local DB + Mailchimp)</option>
                        </select>
                    </div>

                    <div v-if="settingsForm.newsletter_driver !== 'internal'" class="space-y-6 p-5 bg-gray-50 rounded-xl border border-gray-100">
                        <div>
                            <label class="block text-sm font-bold text-gray-900 mb-1">Mailchimp API Key</label>
                            <input v-model="settingsForm.mailchimp_api_key" type="password" :placeholder="configuredSecrets.mailchimp_api_key ? 'Stored securely - leave blank to keep' : 'e.g. 1234567890abcdef-us21'" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Server Prefix</label>
                                <input v-model="settingsForm.mailchimp_server_prefix" type="text" placeholder="e.g. us21" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Audience / List ID</label>
                                <input v-model="settingsForm.mailchimp_list_id" type="text" placeholder="e.g. 1a2b3c4d5e" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-900 mb-1">Default Tags</label>
                            <p class="text-xs text-gray-500 mb-2">Comma-separated tags to apply to new subscribers.</p>
                            <input v-model="settingsForm.mailchimp_tags" type="text" placeholder="website_signup, ai_user" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input v-model="settingsForm.mailchimp_double_optin" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <div>
                                <span class="block text-sm font-bold text-gray-900">Require Double Opt-in</span>
                                <span class="block text-xs text-gray-500">Send a confirmation email to new subscribers.</span>
                            </div>
                        </label>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="activeTab === 'popup'">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl">
                <form @submit.prevent="saveSettings" class="space-y-6">
                    <label class="flex items-center gap-3 cursor-pointer pb-6 border-b border-gray-100">
                        <input v-model="settingsForm.newsletter_enable_popup" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <div>
                            <span class="block text-sm font-bold text-gray-900">Enable Newsletter Popup</span>
                            <span class="block text-xs text-gray-500">Show a popup to encourage visitors to subscribe.</span>
                        </div>
                    </label>

                    <template v-if="settingsForm.newsletter_enable_popup">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Trigger Type</label>
                                <select v-model="settingsForm.newsletter_popup_trigger" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                    <option value="time_delay">Time Delay (Seconds)</option>
                                    <option value="scroll_depth">Scroll Depth (%)</option>
                                    <option value="exit_intent">Exit Intent</option>
                                    <option value="page_views">After N Page Views</option>
                                    <option value="first_visit">First Visit Only</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Trigger Value</label>
                                <input v-model="settingsForm.newsletter_popup_trigger_value" type="text" placeholder="e.g. 5" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Title</label>
                                <input v-model="settingsForm.newsletter_popup_title" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Success Message</label>
                                <input v-model="settingsForm.newsletter_popup_success_message" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-900 mb-1">Description</label>
                            <textarea v-model="settingsForm.newsletter_popup_description" rows="2" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Input Placeholder</label>
                                <input v-model="settingsForm.newsletter_popup_placeholder" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Submit Button Text</label>
                                <input v-model="settingsForm.newsletter_popup_submit_text" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Cookie Duration (Days)</label>
                                <input v-model="settingsForm.newsletter_popup_cookie_duration" type="number" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">Background Color</label>
                                <input v-model="settingsForm.newsletter_popup_bg_color" type="color" class="w-full h-10 bg-gray-50 border border-gray-200 rounded-lg px-1 py-1 focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="space-y-3 pt-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="settingsForm.newsletter_popup_show_mobile" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm font-bold text-gray-900">Show on Mobile Devices</span>
                            </label>
                            
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="settingsForm.newsletter_popup_hide_for_logged_in" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm font-bold text-gray-900">Hide for logged-in users</span>
                            </label>
                        </div>
                    </template>
                </form>
            </div>
        </div>

        <!-- Campaign Modal -->
        <div v-if="showCampaignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">Create Campaign</h3>
                    <button @click="showCampaignModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitCampaign" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Subject</label>
                        <input v-model="campaignForm.subject" type="text" placeholder="Weekly AI Updates" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Audience</label>
                        <select v-model="campaignForm.audience" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            <option v-for="option in audienceOptions" :key="option.value" :value="option.value" :disabled="option.value === 'users_pro' && !$page.props.isProAvailable">
                                {{ option.label }} ({{ stats[option.countKey] ?? 0 }})
                            </option>
                        </select>
                        <p class="text-[10px] text-gray-400 mt-2">User audiences only include active, non-banned users with email marketing enabled.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Content</label>
                        <RichEditor v-model="campaignForm.content" variant="minimal" />
                        <p class="text-[10px] text-gray-400 mt-2">Available variables: {user_name}, {user_email}, {unsubscribe_url}, {site_name}, {site_url}</p>
                    </div>
                    <div class="pt-4">
                        <button type="submit" :disabled="campaignForm.processing" class="w-full py-3 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            SAVE AS DRAFT
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
