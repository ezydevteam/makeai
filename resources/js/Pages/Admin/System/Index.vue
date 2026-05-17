<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    stats: {
        php_version: string,
        laravel_version: string,
        server_software: string,
        database_version: string,
        disk_free: string,
        memory_usage: string
    },
    status: {
        is_maintenance: boolean,
        queue_running: boolean,
        scheduler_running: boolean
    },
    logs: Array<string>
}>();

const cacheForm = useForm({ type: 'all' });
const clearCache = (type: string) => {
    cacheForm.type = type;
    cacheForm.post(route('admin.system.cache.clear'), { preserveScroll: true });
};

const maintenanceForm = useForm({});
const toggleMaintenance = () => {
    if (confirm('Toggle maintenance mode? This affects all users.')) {
        maintenanceForm.post(route('admin.system.maintenance.toggle'), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="System Tools — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">System Tools</h1>
                <p class="text-sm text-gray-500 mt-1">Monitor health, manage cache, and control platform availability.</p>
            </div>
            <button @click="toggleMaintenance" :class="status.is_maintenance ? 'bg-success-600 hover:bg-success-500' : 'bg-danger-600 hover:bg-danger-500'" class="px-6 py-3 text-white rounded-2xl font-bold transition-all shadow-lg flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                {{ status.is_maintenance ? 'GO LIVE' : 'ENTER MAINTENANCE' }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Health Stats -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Environment Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="(val, key) in stats" :key="key" class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ key.replace('_', ' ') }}</div>
                            <div class="text-sm font-bold text-gray-900">{{ val }}</div>
                        </div>
                        <div class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center text-gray-300">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3V3m3 11.25a3 3 0 01-3 3h13.5m-13.5 0a3 3 0 00-3 3V21m3.75-18h13.5m-13.5 0a3 3 0 013 3v8.25m0-8.25a3 3 0 003 3H12" /></svg>
                        </div>
                    </div>
                </div>

                <!-- Cache Actions -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest mb-6">Cache Management</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <button v-for="type in ['Application', 'View', 'Route', 'Config']" :key="type" @click="clearCache(type.toLowerCase())" :disabled="cacheForm.processing" class="flex flex-col items-center gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100 hover:bg-white hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-gray-400 group-hover:text-primary-600 transition-colors shadow-sm">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-600">{{ type }}</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Logs -->
                <div class="bg-gray-900 p-8 rounded-3xl shadow-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-black text-white uppercase tracking-widest text-sm">Real-time Logs (Last 50)</h3>
                        <span class="text-[10px] font-mono text-gray-500">storage/logs/laravel.log</span>
                    </div>
                    <div class="h-[400px] overflow-y-auto font-mono text-xs text-gray-400 space-y-1 custom-scrollbar">
                        <div v-for="(log, i) in logs" :key="i" class="py-1 border-b border-white/5 last:border-none">
                            <span :class="log.includes('ERROR') ? 'text-danger-400' : (log.includes('INFO') ? 'text-primary-400' : 'text-gray-500')">
                                {{ log }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Side Status -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs border-b border-gray-50 pb-4">Service Status</h3>
                    
                    <!-- Queue -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div :class="status.queue_running ? 'bg-success-100 text-success-600' : 'bg-danger-100 text-danger-600'" class="w-8 h-8 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            </div>
                            <span class="text-sm font-bold text-gray-700">Queue Worker</span>
                        </div>
                        <span :class="status.queue_running ? 'text-success-600' : 'text-danger-600'" class="text-[10px] font-black uppercase tracking-widest">
                            {{ status.queue_running ? 'ACTIVE' : 'OFFLINE' }}
                        </span>
                    </div>

                    <!-- Scheduler -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div :class="status.scheduler_running ? 'bg-success-100 text-success-600' : 'bg-danger-100 text-danger-600'" class="w-8 h-8 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <span class="text-sm font-bold text-gray-700">Scheduler (Cron)</span>
                        </div>
                        <span :class="status.scheduler_running ? 'text-success-600' : 'text-danger-600'" class="text-[10px] font-black uppercase tracking-widest">
                            {{ status.scheduler_running ? 'ACTIVE' : 'OFFLINE' }}
                        </span>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="bg-primary-600 p-8 rounded-3xl shadow-xl shadow-primary-600/20 text-white relative overflow-hidden">
                    <svg class="absolute -right-10 -bottom-10 w-40 h-40 text-white/10" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    <h4 class="font-black text-lg mb-2">Platform Health</h4>
                    <p class="text-xs text-white/80 leading-relaxed">Regularly clear your application cache and monitor the logs to ensure peak performance for your users.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>
