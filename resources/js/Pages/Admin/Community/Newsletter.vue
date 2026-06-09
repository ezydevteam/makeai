<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue';
import Pagination from '@/Components/Pagination.vue';
import RichEditor from '@/Components/RichEditor.vue';
import { useTranslate } from '@/Composables/useTranslate';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    subscribers: any,
    campaigns: any,
    stats: any,
    settings: any,
    configuredSecrets: Record<string, boolean>
}>();

const activeTab = ref('subscribers');
const { t } = useTranslate();
const { formatDate } = useDateFormat();

const showCampaignModal = ref(false);
const editingCampaignId = ref<number | null>(null);
const sendTargetId = ref<number | null>(null);
const deleteTargetId = ref<number | null>(null);
const testTargetId = ref<number | null>(null);
const deleteSubscriberId = ref<number | null>(null);
const subscriberSearch = ref(props.subscribers?.search ?? '')
const filterSubscribers = () => {
    window.location.href = route('admin.newsletter.index') + '?search=' + encodeURIComponent(subscriberSearch.value)
}
const campaignForm = useForm({
    subject: '',
    audience: 'subscribers',
    content: ''
});

const audienceOptions = [
    { value: 'subscribers', label: t('Newsletter subscribers'), countKey: 'active' },
    { value: 'users_all', label: t('All opted-in users'), countKey: 'users_all' },
    { value: 'users_active', label: t('Active users'), countKey: 'users_active' },
    { value: 'users_inactive', label: t('Inactive users'), countKey: 'users_inactive' },
    { value: 'users_pro', label: t('Pro users'), countKey: 'users_pro' },
    { value: 'users_free', label: t('Free users'), countKey: 'users_free' },
];

const audienceLabel = (audience: string) => audienceOptions.find((option) => option.value === audience)?.label || t('Newsletter subscribers');

const submitCampaign = () => {
    campaignForm.post(route('admin.newsletter.campaign.store'), {
        onSuccess: () => {
            showCampaignModal.value = false;
            editingCampaignId.value = null;
            campaignForm.reset();
        }
    });
};

const editCampaign = (camp: any) => {
    editingCampaignId.value = camp.id;
    campaignForm.subject = camp.subject;
    campaignForm.audience = camp.audience;
    campaignForm.content = camp.content;
    showCampaignModal.value = true;
};

const updateCampaign = () => {
    if (editingCampaignId.value === null) return;
    campaignForm.post(route('admin.newsletter.campaign.update', editingCampaignId.value), {
        onSuccess: () => {
            showCampaignModal.value = false;
            editingCampaignId.value = null;
            campaignForm.reset();
        }
    });
};

const sendCampaign = (id: number) => {
    sendTargetId.value = id;
};

const confirmSendCampaign = () => {
    if (sendTargetId.value === null) {
        return;
    }

    useForm({}).post(route('admin.newsletter.campaign.send', sendTargetId.value), {
        onFinish: () => {
            sendTargetId.value = null;
        },
    });
};

const deleteSubscriber = (id: number) => {
    deleteSubscriberId.value = id;
};

const confirmDeleteSubscriber = () => {
    if (deleteSubscriberId.value === null) {
        return;
    }

    useForm({}).delete(route('admin.newsletter.subscriber.delete', deleteSubscriberId.value), {
        onFinish: () => {
            deleteSubscriberId.value = null;
        },
    });
};

const deleteCampaign = (id: number) => {
    deleteTargetId.value = id;
};

const confirmDeleteCampaign = () => {
    if (deleteTargetId.value === null) return;
    useForm({}).delete(route('admin.newsletter.campaign.delete', deleteTargetId.value), {
        onFinish: () => { deleteTargetId.value = null; },
    });
};

const testCampaign = (id: number) => {
    useForm({}).post(route('admin.newsletter.campaign.test', id));
};

const retryCampaign = (id: number) => {
    useForm({}).post(route('admin.newsletter.campaign.retry', id));
};

const settingsForm = useForm({
    newsletter_driver: props.settings.newsletter_driver || 'internal',
    mailchimp_api_key: '',
    mailchimp_server_prefix: props.settings.mailchimp_server_prefix || '',
    mailchimp_list_id: props.settings.mailchimp_list_id || '',
    mailchimp_double_optin: props.settings.mailchimp_double_optin ?? false,
    mailchimp_tags: props.settings.mailchimp_tags || '',
    newsletter_double_optin: props.settings.newsletter_double_optin ?? false,
    
    newsletter_enable_popup: props.settings.newsletter_enable_popup ?? false,
    newsletter_popup_trigger: props.settings.newsletter_popup_trigger || 'time_delay',
    newsletter_popup_trigger_value: props.settings.newsletter_popup_trigger_value || '5',
    newsletter_popup_title: props.settings.newsletter_popup_title || t('Subscribe to our Newsletter'),
    newsletter_popup_description: props.settings.newsletter_popup_description || t('Get the latest updates delivered directly to your inbox.'),
    newsletter_popup_placeholder: props.settings.newsletter_popup_placeholder || t('Enter your email address'),
    newsletter_popup_submit_text: props.settings.newsletter_popup_submit_text || t('Subscribe'),
    newsletter_popup_success_message: props.settings.newsletter_popup_success_message || t('Thanks for subscribing!'),
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
    <Head :title="t('Newsletter - Admin')" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('Newsletter System') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ t('Manage subscribers, campaigns, and Mailchimp integration.') }}</p>
            </div>
            <button v-if="activeTab === 'campaigns'" @click="showCampaignModal = true" class="px-5 py-2.5 btn-primary rounded-xl text-sm font-bold transition-all shadow-lg shadow-primary-500/20">
                {{ t('CREATE CAMPAIGN') }}
            </button>
            <button v-if="activeTab === 'settings' || activeTab === 'popup'" @click="saveSettings" :disabled="settingsForm.processing" class="px-5 py-2.5 btn-primary rounded-xl text-sm font-bold transition-all shadow-lg shadow-primary-500/20 disabled:opacity-50">
                {{ t('SAVE SETTINGS') }}
            </button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase mb-2">{{ t('Total Subscribers') }}</p>
                <p class="text-3xl font-black text-gray-900">{{ stats.total }}</p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase mb-2">{{ t('Active') }}</p>
                <p class="text-3xl font-black text-success-600">{{ stats.active }}</p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <p class="text-xs font-bold text-gray-400 uppercase mb-2">{{ t('Unsubscribed') }}</p>
                <p class="text-3xl font-black text-danger-600">{{ stats.unsubscribed }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex space-x-6 border-b border-gray-200 mb-6">
            <button @click="activeTab = 'subscribers'" :class="[activeTab === 'subscribers' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                {{ t('Subscribers') }}
            </button>
            <button @click="activeTab = 'campaigns'" :class="[activeTab === 'campaigns' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                {{ t('Campaigns') }}
            </button>
            <button @click="activeTab = 'settings'" :class="[activeTab === 'settings' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                {{ t('Integrations (Mailchimp)') }}
            </button>
            <button @click="activeTab = 'popup'" :class="[activeTab === 'popup' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm']">
                {{ t('Popup Settings') }}
            </button>
        </div>

        <div v-if="activeTab === 'subscribers'">
            <div class="mb-4">
                <input v-model="subscriberSearch" @keyup.enter="filterSubscribers" type="text" :placeholder="t('Search subscribers...')" class="w-full max-w-sm bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50">
                                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Email') }}</th>
                                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('Date') }}</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="sub in subscribers.data" :key="sub.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900">{{ sub.email }}</div>
                                    <div class="text-[10px] text-gray-400">{{ sub.name || t('Anonymous') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="{
                                        'bg-success-50 text-success-600 border-success-100': sub.status === 'subscribed',
                                        'bg-warning-50 text-warning-600 border-warning-100': sub.status === 'pending',
                                        'bg-danger-50 text-danger-600 border-danger-100': sub.status === 'unsubscribed' || sub.status === 'bounced'
                                    }" class="px-2 py-0.5 text-[10px] font-bold rounded-full border">
                                        {{ t(sub.status).toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    {{ formatDate(sub.created_at) }}
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
            <Pagination class="mt-6" :links="subscribers.links" />
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
                                    {{ t(camp.status).toUpperCase() }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ t('Created: :date', { date: formatDate(camp.created_at) }) }}
                                <span v-if="camp.sent_at">{{ t(' • Sent: :date', { date: formatDate(camp.sent_at) }) }}</span>
                                {{ t(' • :audience • :recipients recipients • :sent sent • :failed failed • :opened opened', { audience: audienceLabel(camp.audience), recipients: camp.recipient_count, sent: camp.sent_count || 0, failed: camp.failed_count || 0, opened: camp.opened_count || 0 }) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="camp.status === 'draft'" @click="editCampaign(camp)" class="p-2 text-gray-400 hover:text-gray-600 transition-colors" :title="t('Edit campaign')">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button v-if="camp.status !== 'sending'" @click="testCampaign(camp.id)" class="p-2 text-gray-400 hover:text-blue-600 transition-colors" :title="t('Send test')">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            </button>
                            <button v-if="camp.status === 'draft'" @click="sendCampaign(camp.id)" class="px-4 py-2 btn-primary rounded-lg text-xs font-bold transition-all">
                                {{ t('QUEUE SEND') }}
                            </button>
                            <span v-else-if="camp.status === 'sending'" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold">
                                {{ t('SENDING IN QUEUE') }}
                            </span>
                            <button v-if="camp.status !== 'sending'" @click="deleteCampaign(camp.id)" class="p-2 text-gray-400 hover:text-danger-600 transition-colors" :title="t('Delete campaign')">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                            <button v-if="camp.status === 'sent' && (camp.failed_count || 0) > 0" @click="retryCampaign(camp.id)" class="px-3 py-1.5 bg-amber-50 text-amber-700 rounded-lg text-xs font-bold hover:bg-amber-100 transition-colors" :title="t('Retry failed recipients')">
                                {{ t('RETRY FAILED') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="campaigns.data.length === 0" class="p-10 bg-white rounded-2xl border border-gray-100 shadow-sm text-center text-sm text-gray-400">
                    {{ t('No campaigns found.') }}
                </div>
            </div>
            <Pagination class="mt-6" :links="campaigns.links" />
        </div>

        <div v-if="activeTab === 'settings'">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl">
                <form @submit.prevent="saveSettings" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Newsletter Driver') }}</label>
                        <p class="text-xs text-gray-500 mb-3">{{ t('Choose how to handle new newsletter subscribers.') }}</p>
                        <select v-model="settingsForm.newsletter_driver" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            <option value="internal">{{ t('Internal Only (Local DB)') }}</option>
                            <option value="mailchimp">{{ t('Mailchimp Only') }}</option>
                            <option value="both">{{ t('Both (Local DB + Mailchimp)') }}</option>
                        </select>
                    </div>

                    <div v-if="settingsForm.newsletter_driver !== 'internal'" class="space-y-6 p-5 bg-gray-50 rounded-xl border border-gray-100">
                        <div>
                            <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Mailchimp API Key') }}</label>
                            <input v-model="settingsForm.mailchimp_api_key" type="password" :placeholder="configuredSecrets.mailchimp_api_key ? t('Stored securely - leave blank to keep') : t('e.g. 1234567890abcdef-us21')" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Server Prefix') }}</label>
                                <input v-model="settingsForm.mailchimp_server_prefix" type="text" :placeholder="t('e.g. us21')" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Audience / List ID') }}</label>
                                <input v-model="settingsForm.mailchimp_list_id" type="text" :placeholder="t('e.g. 1a2b3c4d5e')" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Default Tags') }}</label>
                            <p class="text-xs text-gray-500 mb-2">{{ t('Comma-separated tags to apply to new subscribers.') }}</p>
                            <input v-model="settingsForm.mailchimp_tags" type="text" :placeholder="t('website_signup, ai_user')" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input v-model="settingsForm.newsletter_double_optin" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <div>
                                <span class="block text-sm font-bold text-gray-900">{{ t('Double Opt-In') }}</span>
                                <span class="block text-xs text-gray-500">{{ t('Require email confirmation before adding subscribers.') }}</span>
                            </div>
                        </label>

                        <label v-if="settingsForm.newsletter_driver !== 'internal'" class="flex items-center gap-3 cursor-pointer">
                            <input v-model="settingsForm.mailchimp_double_optin" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <div>
                                <span class="block text-sm font-bold text-gray-900">{{ t('Require Double Opt-in') }}</span>
                                <span class="block text-xs text-gray-500">{{ t('Send a confirmation email to new subscribers.') }}</span>
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
                            <span class="block text-sm font-bold text-gray-900">{{ t('Enable Newsletter Popup') }}</span>
                            <span class="block text-xs text-gray-500">{{ t('Show a popup to encourage visitors to subscribe.') }}</span>
                        </div>
                    </label>

                    <template v-if="settingsForm.newsletter_enable_popup">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Trigger Type') }}</label>
                                <select v-model="settingsForm.newsletter_popup_trigger" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                    <option value="time_delay">{{ t('Time Delay (Seconds)') }}</option>
                                    <option value="scroll_depth">{{ t('Scroll Depth (%)') }}</option>
                                    <option value="exit_intent">{{ t('Exit Intent') }}</option>
                                    <option value="page_views">{{ t('After N Page Views') }}</option>
                                    <option value="first_visit">{{ t('First Visit Only') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Trigger Value') }}</label>
                                <input v-model="settingsForm.newsletter_popup_trigger_value" type="text" :placeholder="t('e.g. 5')" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Title') }}</label>
                                <input v-model="settingsForm.newsletter_popup_title" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Success Message') }}</label>
                                <input v-model="settingsForm.newsletter_popup_success_message" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Description') }}</label>
                            <textarea v-model="settingsForm.newsletter_popup_description" rows="2" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Input Placeholder') }}</label>
                                <input v-model="settingsForm.newsletter_popup_placeholder" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Submit Button Text') }}</label>
                                <input v-model="settingsForm.newsletter_popup_submit_text" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Cookie Duration (Days)') }}</label>
                                <input v-model="settingsForm.newsletter_popup_cookie_duration" type="number" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-900 mb-1">{{ t('Background Color') }}</label>
                                <input v-model="settingsForm.newsletter_popup_bg_color" type="color" class="w-full h-10 bg-gray-50 border border-gray-200 rounded-lg px-1 py-1 focus:border-primary-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="space-y-3 pt-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="settingsForm.newsletter_popup_show_mobile" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm font-bold text-gray-900">{{ t('Show on Mobile Devices') }}</span>
                            </label>
                            
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="settingsForm.newsletter_popup_hide_for_logged_in" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm font-bold text-gray-900">{{ t('Hide for logged-in users') }}</span>
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
                    <h3 class="font-bold text-gray-900">{{ editingCampaignId ? t('Edit Campaign') : t('Create Campaign') }}</h3>
                    <button @click="showCampaignModal = false; editingCampaignId = null; campaignForm.reset()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="editingCampaignId ? updateCampaign() : submitCampaign()" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Subject') }}</label>
                        <input v-model="campaignForm.subject" type="text" :placeholder="t('Weekly AI Updates')" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Audience') }}</label>
                        <select v-model="campaignForm.audience" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            <option v-for="option in audienceOptions" :key="option.value" :value="option.value" :disabled="option.value === 'users_pro' && !$page.props.isProAvailable">
                                {{ option.label }} ({{ stats[option.countKey] ?? 0 }})
                            </option>
                        </select>
                        <p class="text-[10px] text-gray-400 mt-2">{{ t('User audiences only include active, non-banned users with email marketing enabled.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('Content') }}</label>
                        <RichEditor v-model="campaignForm.content" variant="minimal" />
                        <p class="text-[10px] text-gray-400 mt-2">{{ t('Available variables: {user_name}, {user_email}, {unsubscribe_url}, {site_name}, {site_url}') }}</p>
                    </div>
                    <div class="pt-4">
                        <button type="submit" :disabled="campaignForm.processing" class="w-full py-3 btn-primary rounded-xl font-bold transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            {{ editingCampaignId ? t('UPDATE CAMPAIGN') : t('SAVE AS DRAFT') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <ActionConfirmModal
            :open="sendTargetId !== null"
            :title="t('Send campaign?')"
            :message="t('This campaign will be queued for delivery to the selected audience.')"
            :confirm-label="t('Queue Send')"
            variant="primary"
            @cancel="sendTargetId = null"
            @confirm="confirmSendCampaign"
        />

        <ActionConfirmModal
            :open="deleteTargetId !== null"
            :title="t('Delete campaign?')"
            :message="t('This campaign and all its recipient records will be permanently deleted.')"
            :confirm-label="t('Delete')"
            @cancel="deleteTargetId = null"
            @confirm="confirmDeleteCampaign"
        />

        <ActionConfirmModal
            :open="deleteSubscriberId !== null"
            :title="t('Remove subscriber?')"
            :message="t('This subscriber will be removed from the newsletter list.')"
            :confirm-label="t('Remove')"
            @cancel="deleteSubscriberId = null"
            @confirm="confirmDeleteSubscriber"
        />
    </div>
</template>
