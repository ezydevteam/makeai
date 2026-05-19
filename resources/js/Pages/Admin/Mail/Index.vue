<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    settings: Record<string, any>
    configuredSecrets: Record<string, boolean>
}>();

const form = useForm({
    mail_driver: props.settings.mail_driver || 'smtp',
    mail_host: props.settings.mail_host || '',
    mail_port: props.settings.mail_port || 587,
    mail_username: props.settings.mail_username || '',
    mail_password: '',
    mail_encryption: props.settings.mail_encryption || 'tls',
    mail_from_address: props.settings.mail_from_address || '',
    mail_from_name: props.settings.mail_from_name || '',
    mailgun_domain: props.settings.mailgun_domain || '',
    mailgun_secret: '',
    mailgun_endpoint: props.settings.mailgun_endpoint || 'api.mailgun.net',
    ses_key: props.settings.ses_key || '',
    ses_secret: '',
    ses_region: props.settings.ses_region || 'us-east-1',
    postmark_token: '',
    sendgrid_api_key: '',
});

const submit = () => {
    form.post(route('admin.mail.update'), { preserveScroll: true });
};

const testForm = useForm({ email: '' });
const sendTest = () => {
    testForm.post(route('admin.mail.test'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Mail Settings — Admin" />
    <div class="max-w-5xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Mail System</h1>
            <p class="text-sm text-gray-500 mt-1">Configure your email delivery service and sender details.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                <form @submit.prevent="submit" class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-8">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Mail Driver</label>
                            <select v-model="form.mail_driver" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all font-bold">
                                <option value="smtp">SMTP</option>
                                <option value="mailgun">Mailgun</option>
                                <option value="ses">Amazon SES</option>
                                <option value="postmark">Postmark</option>
                                <option value="sendgrid">SendGrid</option>
                                <option value="log">Log (For Testing)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">From Address</label>
                            <input v-model="form.mail_from_address" type="email" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" placeholder="hello@makeai.test">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">From Name</label>
                            <input v-model="form.mail_from_name" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" placeholder="MakeAI Team">
                        </div>
                    </div>

                    <!-- SMTP Fields -->
                    <div v-if="form.mail_driver === 'smtp'" class="pt-6 border-t border-gray-50 space-y-6">
                        <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">SMTP Credentials</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Host</label>
                                <input v-model="form.mail_host" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Port</label>
                                <input v-model="form.mail_port" type="number" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Encryption</label>
                                <select v-model="form.mail_encryption" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="null">None</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Username</label>
                                <input v-model="form.mail_username" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Password</label>
                                <input v-model="form.mail_password" type="password" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" :placeholder="configuredSecrets.mail_password ? 'Stored securely - leave blank to keep' : ''">
                            </div>
                        </div>
                    </div>

                    <!-- Mailgun Fields -->
                    <div v-if="form.mail_driver === 'mailgun'" class="pt-6 border-t border-gray-50 space-y-6">
                        <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">Mailgun API</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Domain</label>
                                <input v-model="form.mailgun_domain" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Endpoint</label>
                                <select v-model="form.mailgun_endpoint" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                                    <option value="api.mailgun.net">US (api.mailgun.net)</option>
                                    <option value="api.eu.mailgun.net">EU (api.eu.mailgun.net)</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Secret Key</label>
                                <input v-model="form.mailgun_secret" type="password" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" :placeholder="configuredSecrets.mailgun_secret ? 'Stored securely - leave blank to keep' : ''">
                            </div>
                        </div>
                    </div>

                    <!-- SES Fields -->
                    <div v-if="form.mail_driver === 'ses'" class="pt-6 border-t border-gray-50 space-y-6">
                        <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">Amazon SES</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Access Key ID</label>
                                <input v-model="form.ses_key" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Region</label>
                                <input v-model="form.ses_region" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" placeholder="us-east-1">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Secret Access Key</label>
                                <input v-model="form.ses_secret" type="password" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" :placeholder="configuredSecrets.ses_secret ? 'Stored securely - leave blank to keep' : ''">
                            </div>
                        </div>
                    </div>

                    <!-- Postmark Fields -->
                    <div v-if="form.mail_driver === 'postmark'" class="pt-6 border-t border-gray-50 space-y-6">
                        <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">Postmark API</h3>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Server Token</label>
                            <input v-model="form.postmark_token" type="password" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" :placeholder="configuredSecrets.postmark_token ? 'Stored securely - leave blank to keep' : ''">
                        </div>
                    </div>

                    <!-- SendGrid Fields -->
                    <div v-if="form.mail_driver === 'sendgrid'" class="pt-6 border-t border-gray-50 space-y-6">
                        <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">SendGrid API</h3>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">API Key</label>
                            <input v-model="form.sendgrid_api_key" type="password" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" :placeholder="configuredSecrets.sendgrid_api_key ? 'Stored securely - leave blank to keep' : ''">
                        </div>
                    </div>

                    <div class="pt-8">
                        <button type="submit" :disabled="form.processing" class="bg-primary-600 hover:bg-primary-500 text-white px-8 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20">
                            Save Configuration
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <!-- Test Mail Card -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs border-b border-gray-50 pb-4">Connectivity Test</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">Save your settings first, then send a test email to verify delivery.</p>
                    
                    <form @submit.prevent="sendTest" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Recipient Email</label>
                            <input v-model="testForm.email" type="email" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all" placeholder="test@example.com">
                        </div>
                        <button type="submit" :disabled="testForm.processing" class="w-full bg-gray-900 hover:bg-gray-800 text-white px-6 py-3 rounded-2xl font-bold transition-all">
                            Send Test Mail
                        </button>
                    </form>
                </div>

                <!-- Info Card -->
                <div class="bg-primary-600 p-8 rounded-3xl shadow-xl shadow-primary-600/20 text-white relative overflow-hidden">
                    <svg class="absolute -right-10 -bottom-10 w-40 h-40 text-white/10" fill="currentColor" viewBox="0 0 24 24"><path d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                    <h4 class="font-black text-lg mb-2">Email Delivery</h4>
                    <p class="text-xs text-white/80 leading-relaxed">Using a professional SMTP provider like Mailgun or Postmark is highly recommended for high deliverability rates.</p>
                </div>
            </div>
        </div>
    </div>
</template>
