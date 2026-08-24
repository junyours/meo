<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total_users: 18,
            admin_count: 12,
            staff_count: 6,
        }),
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || { name: 'Admin' });

const searchQuery = ref('');
const reminders = ref([]);
const loadingReminders = ref(false);

// Dynamic Greeting based on time of day
const greetingMessage = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
});

const formattedCurrentDate = computed(() => {
    return new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const fetchReminders = async () => {
    loadingReminders.value = true;
    try {
        const response = await axios.get('/reminders');
        reminders.value = response.data || [];
    } catch (err) {
        console.error('Failed to load dashboard reminders:', err);
    } finally {
        loadingReminders.value = false;
    }
};

onMounted(() => {
    fetchReminders();
});

const filteredUsers = computed(() => {
    return (props.users || []).filter(u =>
        !searchQuery.value ||
        u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        u.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Analytics & Summary Calculations
const activeReminders = computed(() => reminders.value.filter(r => !r.isDone));
const completedReminders = computed(() => reminders.value.filter(r => r.isDone));
const upcomingReminders = computed(() => {
    return [...activeReminders.value]
        .sort((a, b) => new Date(a.startsAt || a.starts_at) - new Date(b.startsAt || b.starts_at))
        .slice(0, 5);
});

const reminderCategoryBreakdown = computed(() => {
    const counts = {};
    reminders.value.forEach(r => {
        const cat = r.category || 'Uncategorized';
        counts[cat] = (counts[cat] || 0) + 1;
    });
    const total = reminders.value.length || 1;
    return Object.keys(counts).map(cat => ({
        name: cat,
        count: counts[cat],
        percentage: Math.round((counts[cat] / total) * 100)
    }));
});

const completionRate = computed(() => {
    if (!reminders.value.length) return 0;
    return Math.round((completedReminders.value.length / reminders.value.length) * 100);
});

const formatDate = (dateStr) => {
    if (!dateStr) return 'TBD';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const emit = defineEmits(['create-user', 'view-reports', 'open-settings']);
</script>

<template>
    <div class="w-full font-sans antialiased text-slate-800 space-y-6">
        <!-- Minimal User Greeting & System Monitoring Banner -->
        <div class="bg-white border border-slate-200/80 p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-slate-400 text-[11px] font-semibold uppercase tracking-wider">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>System Live Monitoring</span>
                        <span>•</span>
                        <span>{{ formattedCurrentDate }}</span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                        {{ greetingMessage }}, {{ currentUser.name }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-xl leading-relaxed">
                        Here is your operational snapshot. You have <span class="font-medium text-slate-900 underline decoration-red-300 decoration-1">{{ activeReminders.length }} active reminders</span> scheduled across {{ stats.total_users || 0 }} registered accounts.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <button
                        @click="fetchReminders"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-red-700 hover:bg-red-800 text-white text-xs font-medium rounded-lg transition-all shadow-sm active:scale-95"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-red-200" :class="{ 'animate-spin': loadingReminders }">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Refresh Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Stats Cards (Minimalist Metric Grid) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Users -->
            <div class="bg-white border border-slate-200/80 p-5 shadow-sm hover:border-slate-300 transition-all">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Total Users</p>
                    <div class="p-2 bg-slate-50 text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ stats.total_users || 0 }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Registered system accounts</p>
                </div>
            </div>

            <!-- Admins / Staff -->
            <div class="bg-white border border-slate-200/80 p-5 shadow-sm hover:border-slate-300 transition-all">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Admins / Staff</p>
                    <div class="p-2 bg-red-50 text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-slate-900 tracking-tight">
                        {{ stats.admin_count || 0 }} <span class="text-sm font-normal text-slate-400">/ {{ stats.staff_count || 0 }}</span>
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Administrative vs operational staff</p>
                </div>
            </div>

            <!-- Active Reminders -->
            <div class="bg-white border border-slate-200/80 p-5 shadow-sm hover:border-slate-300 transition-all">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Active Reminders</p>
                    <div class="p-2 bg-amber-50 text-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m2 8H6a2 2 0 01-2-2V6a2 2 0 012-2h6" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ activeReminders.length }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Pending schedules requiring action</p>
                </div>
            </div>

            <!-- Task Completion Rate -->
            <div class="bg-white border border-slate-200/80 p-5 shadow-sm hover:border-slate-300 transition-all">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Completion Rate</p>
                    <div class="p-2 bg-emerald-50 text-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ completionRate }}%</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Resolved vs total recorded reminders</p>
                </div>
            </div>
        </div>

        <!-- Reminders & Analytics Main Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Upcoming Reminders -->
            <div class="lg:col-span-2 bg-white border border-slate-200/80 p-5 shadow-sm">
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Upcoming Reminders</h3>
                        <p class="text-[11px] text-slate-400">Prioritized operational schedules and meetings</p>
                    </div>
                    <span class="text-[11px] font-medium px-2.5 py-0.5 bg-red-50 text-red-700 border border-red-100">
                        {{ activeReminders.length }} Pending
                    </span>
                </div>

                <div v-if="loadingReminders" class="py-10 text-center text-xs text-slate-400">
                    Loading schedules...
                </div>
                <div v-else-if="upcomingReminders.length === 0" class="py-10 text-center text-xs text-slate-400">
                    No active reminders found.
                </div>
                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="item in upcomingReminders"
                        :key="item.id"
                        class="py-3 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 group"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded bg-red-50 text-red-700 border border-red-100">
                                    {{ item.category || 'General' }}
                                </span>
                                <h4 class="text-xs font-semibold text-slate-800 group-hover:text-red-700 transition-colors">
                                    {{ item.title }}
                                </h4>
                            </div>
                            <p v-if="item.description" class="text-[11px] text-slate-500 line-clamp-1">
                                {{ item.description }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="inline-flex items-center gap-1 text-[11px] text-slate-400 font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-3.5 h-3.5 text-slate-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                                </svg>
                                {{ formatDate(item.startsAt || item.starts_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics & Operational Insights Sidebar -->
            <div class="space-y-5">
                <!-- Category Analytics -->
                <div class="bg-white border border-slate-200/80 p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900 mb-0.5">Reminder Breakdown</h3>
                    <p class="text-[11px] text-slate-400 mb-4">Distribution by task category</p>

                    <div v-if="reminderCategoryBreakdown.length === 0" class="py-6 text-center text-xs text-slate-400">
                        No categorization data.
                    </div>
                    <div v-else class="space-y-3.5">
                        <div v-for="cat in reminderCategoryBreakdown" :key="cat.name" class="space-y-1">
                            <div class="flex justify-between text-[11px] font-medium text-slate-700">
                                <span>{{ cat.name }}</span>
                                <span class="text-slate-400">{{ cat.count }} ({{ cat.percentage }}%)</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div
                                    class="bg-red-700 h-1.5 rounded-full transition-all duration-300"
                                    :style="{ width: cat.percentage + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Minimal Insights Box -->
                <div class="bg-slate-900 p-5 text-slate-100 shadow-sm border border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-semibold tracking-wider uppercase text-slate-400">System Insights</h4>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                    </div>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        Total <span class="font-semibold text-white">{{ reminders.length }}</span> reminders recorded. System operations and task monitoring are running as scheduled.
                    </p>
                </div>
            </div>
        </div>

        <!-- Management Shortcuts (Minimalist Cards) -->
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Management Shortcuts</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Provision User -->
                <div
                    @click="emit('create-user')"
                    class="bg-white border border-slate-200/80 p-4 cursor-pointer hover:border-red-500/40 hover:shadow-sm transition-all group"
                >
                    <div class="flex items-center justify-between">
                        <div class="p-2 bg-slate-50 text-slate-700 group-hover:bg-red-700 group-hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.37-1.765Z" />
                            </svg>
                        </div>
                        <span class="text-slate-300 group-hover:text-red-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <h4 class="font-semibold text-slate-900 group-hover:text-red-700 transition-colors text-xs">Provision New User</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Add operational staff or admin access</p>
                    </div>
                </div>

                <!-- System Analytics -->
                <div
                    @click="emit('view-reports')"
                    class="bg-white border border-slate-200/80 p-4 cursor-pointer hover:border-red-500/40 hover:shadow-sm transition-all group"
                >
                    <div class="flex items-center justify-between">
                        <div class="p-2 bg-slate-50 text-slate-700 group-hover:bg-red-700 group-hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v5.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 18.375v-5.25ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125v-9.75ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v14.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                        </div>
                        <span class="text-slate-300 group-hover:text-red-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <h4 class="font-semibold text-slate-900 group-hover:text-red-700 transition-colors text-xs">System Analytics</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Review operational reports & status</p>
                    </div>
                </div>

                <!-- Settings -->
                <div
                    @click="emit('open-settings')"
                    class="bg-white border border-slate-200/80 p-4 cursor-pointer hover:border-red-500/40 hover:shadow-sm transition-all group"
                >
                    <div class="flex items-center justify-between">
                        <div class="p-2 bg-slate-50 text-slate-700 group-hover:bg-red-700 group-hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.754c-.29.218-.44.573-.4 1.23.013.201.02.404.02.608 0 .204-.007.407-.02.608-.04.657.11 1.012.4 1.23l1.003.754a1.125 1.125 0 0 1 .26 1.43l-1.297 2.247a1.125 1.125 0 0 1-1.37.491l-1.216-.456c-.356-.133-.751-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.754c.29-.218.44-.573.4-1.23a7.115 7.115 0 0 1-.02-.608c0-.204.007-.407.02-.608.04-.657-.11-1.012-.4-1.23l-1.004-.754a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </div>
                        <span class="text-slate-300 group-hover:text-red-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <h4 class="font-semibold text-slate-900 group-hover:text-red-700 transition-colors text-xs">MEO Portal Settings</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Configure portal preferences</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>