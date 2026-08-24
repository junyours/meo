<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
            total_users: 0,
            superadmin_count: 0,
            admin_count: 0,
            staff_count: 0,
        }),
    },
    projects: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['tab-change', 'create-user', 'view-reports', 'open-settings']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || { name: 'Super Administrator' });

// ================= CLOCK & GREETING =================
const currentTimeStr = ref('');
const currentDateStr = ref('');
let clockInterval = null;

const updateClock = () => {
    const now = new Date();
    currentTimeStr.value = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
    currentDateStr.value = now.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const greetingMessage = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
});

// ================= REMINDERS MONITORING =================
const reminders = ref([]);
const loadingReminders = ref(false);
const reminderAudienceFilter = ref('all'); // 'all' | 'everyone' | 'personal'
const reminderSearchQuery = ref('');

const fetchReminders = async () => {
    loadingReminders.value = true;
    try {
        const response = await axios.get('/reminders');
        if (Array.isArray(response.data)) {
            reminders.value = response.data;
        }
    } catch (err) {
        console.error('Failed to load superadmin dashboard reminders:', err);
    } finally {
        loadingReminders.value = false;
    }
};

const activeReminders = computed(() => {
    return reminders.value
        .filter(r => r.startsAt && !r.isDone)
        .sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt));
});

const filteredReminders = computed(() => {
    let list = activeReminders.value;
    if (reminderAudienceFilter.value !== 'all') {
        list = list.filter(r => (r.audience || 'personal') === reminderAudienceFilter.value);
    }
    if (reminderSearchQuery.value.trim()) {
        const q = reminderSearchQuery.value.trim().toLowerCase();
        list = list.filter(r => 
            (r.title && r.title.toLowerCase().includes(q)) ||
            (r.category && r.category.toLowerCase().includes(q)) ||
            (r.description && r.description.toLowerCase().includes(q))
        );
    }
    return list;
});

const pinnedReminder = computed(() => {
    return filteredReminders.value.length > 0 ? filteredReminders.value[0] : null;
});

const remainingReminders = computed(() => {
    return filteredReminders.value.length > 1 ? filteredReminders.value.slice(1) : [];
});

const officeWideRemindersCount = computed(() => {
    return activeReminders.value.filter(r => r.audience === 'everyone').length;
});

const personalRemindersCount = computed(() => {
    return activeReminders.value.filter(r => r.audience !== 'everyone').length;
});

const todayStr = computed(() => new Date().toDateString());

const isToday = (dateStr) => {
    if (!dateStr) return false;
    return new Date(dateStr).toDateString() === todayStr.value;
};

const isTomorrow = (dateStr) => {
    if (!dateStr) return false;
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return new Date(dateStr).toDateString() === tomorrow.toDateString();
};

const getRelativeScheduleLabel = (dateStr) => {
    if (!dateStr) return '';
    if (isToday(dateStr)) return 'Today';
    if (isTomorrow(dateStr)) return 'Tomorrow';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const formatReminderDateTime = (dateStr) => {
    if (!dateStr) return 'TBD';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// ================= DIRECTIVES & STAFF ASSIGNMENTS =================
const directives = ref([]);
const loadingDirectives = ref(false);

const fetchDirectives = async () => {
    loadingDirectives.value = true;
    try {
        const res = await axios.get('/staff-assignments');
        if (res.data?.assignments) {
            directives.value = res.data.assignments;
        }
    } catch (e) {
        console.error('Failed to load staff directives for superadmin dashboard:', e);
    } finally {
        loadingDirectives.value = false;
    }
};

const activeDirectives = computed(() => {
    return directives.value.filter(d => d.status !== 'completed');
});

const urgentDirectives = computed(() => {
    return activeDirectives.value.filter(d => d.priority === 'urgent' || d.priority === 'high');
});

// ================= PROJECT ANALYTICS & MONITORING =================
const projectStats = computed(() => {
    const all = props.projects || [];
    const total = all.length;
    
    // Status mapping: 0: Ongoing, 1: Completed, 2: Delayed, 3: Not Started, 4: Suspended
    const ongoing = all.filter(p => p.status === 0 || p.status === 'Ongoing' || (typeof p.status === 'string' && p.status.toLowerCase() === 'ongoing')).length;
    const completed = all.filter(p => p.status === 1 || p.status === 'Completed' || (typeof p.status === 'string' && p.status.toLowerCase() === 'completed')).length;
    const delayed = all.filter(p => p.status === 2 || p.status === 'Delayed' || (typeof p.status === 'string' && p.status.toLowerCase() === 'delayed')).length;
    const notStarted = all.filter(p => p.status === 3 || p.status === 'Not Started' || (typeof p.status === 'string' && p.status.toLowerCase() === 'not started')).length;
    const suspended = all.filter(p => p.status === 4 || p.status === 'Suspended' || (typeof p.status === 'string' && p.status.toLowerCase() === 'suspended')).length;

    // Calculate average accomplishment
    const accomplishments = all.map(p => Number(p.accomplishment_rate || p.accomplishment || 0)).filter(n => !isNaN(n));
    const avgAccomplishment = accomplishments.length > 0 
        ? Math.round(accomplishments.reduce((a, b) => a + b, 0) / accomplishments.length) 
        : 0;

    return {
        total,
        ongoing,
        completed,
        delayed,
        notStarted,
        suspended,
        avgAccomplishment,
    };
});

const criticalProjects = computed(() => {
    const all = props.projects || [];
    return all
        .filter(p => p.status === 2 || p.status === 'Delayed' || (typeof p.status === 'string' && p.status.toLowerCase() === 'delayed'))
        .slice(0, 4);
});

const recentProjects = computed(() => {
    const all = props.projects || [];
    return all.slice(0, 5);
});

// ================= USER METRICS =================
const userMetrics = computed(() => {
    const allUsers = props.users || [];
    const total = props.stats?.total_users || allUsers.length || 0;
    const superadmins = props.stats?.superadmin_count || allUsers.filter(u => u.role === 'superadmin').length || 0;
    const admins = props.stats?.admin_count || allUsers.filter(u => u.role === 'admin').length || 0;
    const staff = props.stats?.staff_count || allUsers.filter(u => u.role === 'staff' || !u.role).length || 0;
    const verified = allUsers.filter(u => u.email_verified_at).length;
    const verifiedPercent = total > 0 ? Math.round((verified / total) * 100) : 100;

    return {
        total,
        superadmins,
        admins,
        staff,
        verified,
        verifiedPercent,
    };
});

// Refresh All Data
const isRefreshingAll = ref(false);
const refreshAllData = async () => {
    isRefreshingAll.value = true;
    await Promise.allSettled([
        fetchReminders(),
        fetchDirectives(),
    ]);
    isRefreshingAll.value = false;
};

const getInitials = (name) => {
    if (!name) return 'SA';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const navigateToTab = (tabName) => {
    emit('tab-change', tabName);
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
    fetchReminders();
    fetchDirectives();
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
});
</script>

<template>
    <div class="w-full font-sans antialiased text-gray-800 space-y-6">

        <!-- 1. LIVE SYSTEM MONITORING & EXECUTIVE GREETING BANNER -->
        <div class="bg-white border border-gray-200 p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                
                <!-- Left: Greeting & Live Indicator -->
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span class="text-emerald-700 font-bold">All Municipal Systems Operational</span>
                        <span class="text-gray-300">•</span>
                        <span>{{ currentDateStr }}</span>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <!-- Profile Photo / Avatar -->
                        <div class="w-11 h-11 shrink-0 rounded-xl overflow-hidden bg-gray-900 text-white font-bold flex items-center justify-center text-sm shadow-sm border border-gray-200">
                            <img 
                                v-if="currentUser.profile_photo_url" 
                                :src="currentUser.profile_photo_url" 
                                :alt="currentUser.name" 
                                class="w-full h-full object-cover" 
                            />
                            <span v-else>{{ getInitials(currentUser.name) }}</span>
                        </div>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">
                                {{ greetingMessage }}, {{ currentUser.name }}
                            </h2>
                            <p class="text-xs text-gray-500">
                                Super Administrator Executive Command & Municipal Live Monitoring Center
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right: Live Clock & Quick Refresh -->
                <div class="flex items-center gap-3 shrink-0 self-start lg:self-center">
                    <div class="hidden sm:flex flex-col items-end px-3.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Philippine Standard Time</span>
                        <span class="text-sm font-mono font-bold text-gray-900">{{ currentTimeStr }}</span>
                    </div>

                    <button
                        @click="refreshAllData"
                        :disabled="isRefreshingAll"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-xs font-semibold rounded-lg transition-all shadow-xs disabled:opacity-50"
                        title="Reload Real-Time Municipal Monitoring Feeds"
                    >
                        <svg 
                            xmlns="http://www.w3.org/2000/svg" 
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke-width="2" 
                            stroke="currentColor" 
                            class="w-3.5 h-3.5" 
                            :class="{ 'animate-spin': isRefreshingAll }"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Refresh Feeds</span>
                    </button>
                </div>

            </div>
        </div>

        <!-- 2. HIGH-LEVEL KPI METRIC GRID (ALL-WHITE BLOCK CARDS) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- KPI 1: System Accounts & Verification -->
            <div 
                @click="navigateToTab('users')"
                class="bg-white border border-gray-200 p-5 shadow-xs hover:border-gray-300 hover:shadow-sm transition-all cursor-pointer group"
            >
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 group-hover:text-red-600 transition-colors">System Users</p>
                    <div class="p-2 bg-gray-50 text-gray-600 rounded-lg group-hover:bg-red-50 group-hover:text-red-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{{ userMetrics.total }}</div>
                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                        <span class="text-emerald-700 font-semibold">{{ userMetrics.verified }} Verified</span>
                        <span>•</span>
                        <span>{{ userMetrics.staff }} Staff</span>
                        <span>•</span>
                        <span>{{ userMetrics.admins }} Admins</span>
                    </div>
                </div>
            </div>

            <!-- KPI 2: Infrastructure Projects -->
            <div 
                @click="navigateToTab('projects')"
                class="bg-white border border-gray-200 p-5 shadow-xs hover:border-gray-300 hover:shadow-sm transition-all cursor-pointer group"
            >
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 group-hover:text-blue-600 transition-colors">Municipal Projects</p>
                    <div class="p-2 bg-gray-50 text-gray-600 rounded-lg group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{{ projectStats.total }}</div>
                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                        <span class="text-blue-700 font-semibold">{{ projectStats.ongoing }} Ongoing</span>
                        <span>•</span>
                        <span class="text-emerald-700 font-semibold">{{ projectStats.completed }} Done</span>
                        <span v-if="projectStats.delayed > 0" class="text-rose-700 font-bold">• {{ projectStats.delayed }} Delayed</span>
                    </div>
                </div>
            </div>

            <!-- KPI 3: Operational Directives & Workloads -->
            <div 
                @click="navigateToTab('staff')"
                class="bg-white border border-gray-200 p-5 shadow-xs hover:border-gray-300 hover:shadow-sm transition-all cursor-pointer group"
            >
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 group-hover:text-amber-600 transition-colors">Staff Directives</p>
                    <div class="p-2 bg-gray-50 text-gray-600 rounded-lg group-hover:bg-amber-50 group-hover:text-amber-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{{ activeDirectives.length }}</div>
                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                        <span v-if="urgentDirectives.length > 0" class="text-rose-700 font-bold">{{ urgentDirectives.length }} Urgent</span>
                        <span v-else class="text-emerald-700 font-semibold">All In Order</span>
                        <span>•</span>
                        <span>{{ directives.length }} Total Dispatched</span>
                    </div>
                </div>
            </div>

            <!-- KPI 4: Global Reminders & Schedules -->
            <div 
                @click="navigateToTab('reminders')"
                class="bg-white border border-gray-200 p-5 shadow-xs hover:border-gray-300 hover:shadow-sm transition-all cursor-pointer group"
            >
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 group-hover:text-purple-600 transition-colors">Office Schedules</p>
                    <div class="p-2 bg-gray-50 text-gray-600 rounded-lg group-hover:bg-purple-50 group-hover:text-purple-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.253M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{{ activeReminders.length }}</div>
                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                        <span class="text-purple-700 font-semibold">{{ officeWideRemindersCount }} Office-Wide</span>
                        <span>•</span>
                        <span>{{ personalRemindersCount }} Personal</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- 3. REMINDERS & SCHEDULES LIVE MONITORING (SPOTLIGHT + SCROLLABLE CONTAINER) -->
        <div class="bg-white border border-gray-200 p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 bg-red-50 text-red-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Municipal Reminders & Office Directives Monitor</h3>
                        <p class="text-xs text-gray-500">Real-time schedule monitoring across all engineering personnel and office-wide broadcasts</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Audience filter buttons -->
                    <div class="inline-flex p-0.5 bg-gray-100 rounded-lg border border-gray-200 text-xs font-semibold">
                        <button
                            @click="reminderAudienceFilter = 'all'"
                            class="px-2.5 py-1 rounded-md transition-colors"
                            :class="reminderAudienceFilter === 'all' ? 'bg-white text-gray-900 shadow-2xs' : 'text-gray-500 hover:text-gray-900'"
                        >
                            All ({{ activeReminders.length }})
                        </button>
                        <button
                            @click="reminderAudienceFilter = 'everyone'"
                            class="px-2.5 py-1 rounded-md transition-colors"
                            :class="reminderAudienceFilter === 'everyone' ? 'bg-white text-gray-900 shadow-2xs' : 'text-gray-500 hover:text-gray-900'"
                        >
                            Office-Wide ({{ officeWideRemindersCount }})
                        </button>
                        <button
                            @click="reminderAudienceFilter = 'personal'"
                            class="px-2.5 py-1 rounded-md transition-colors"
                            :class="reminderAudienceFilter === 'personal' ? 'bg-white text-gray-900 shadow-2xs' : 'text-gray-500 hover:text-gray-900'"
                        >
                            Personal ({{ personalRemindersCount }})
                        </button>
                    </div>

                    <button 
                        @click="navigateToTab('reminders')"
                        class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition"
                    >
                        Manage & Dispatch &rarr;
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loadingReminders" class="py-10 text-center text-xs text-gray-400">
                <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-red-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Synchronizing municipal reminders...</span>
            </div>

            <!-- Reminders Content -->
            <div v-else class="mt-4 space-y-4">
                <!-- PINNED MOST UPCOMING SPOTLIGHT CARD -->
                <div v-if="pinnedReminder" class="p-4 rounded-xl border border-red-200 bg-red-50/40">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Animated Attention Grabber Badge -->
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white shadow-2xs">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-90"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                                </span>
                                <span>{{ isToday(pinnedReminder.startsAt) ? 'Happening Today' : 'Immediate Upcoming' }}</span>
                            </span>

                            <span class="px-2 py-0.5 text-xs font-semibold rounded-md bg-white border border-red-200 text-red-800">
                                {{ getRelativeScheduleLabel(pinnedReminder.startsAt) }}
                            </span>

                            <span 
                                class="px-2 py-0.5 text-xs font-semibold rounded-md uppercase tracking-wider"
                                :class="pinnedReminder.audience === 'everyone' ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-gray-100 text-gray-700 border border-gray-200'"
                            >
                                {{ pinnedReminder.audience === 'everyone' ? 'Broadcast: Everyone' : 'Targeted / Personal' }}
                            </span>

                            <span v-if="pinnedReminder.category" class="text-xs text-gray-500 font-medium">
                                {{ pinnedReminder.category }}
                            </span>
                        </div>

                        <div class="text-xs font-mono text-gray-500 font-semibold">
                            {{ formatReminderDateTime(pinnedReminder.startsAt) }}
                        </div>
                    </div>

                    <div class="text-base font-bold text-gray-900">{{ pinnedReminder.title }}</div>
                    <p v-if="pinnedReminder.description" class="text-xs text-gray-600 mt-1 leading-relaxed line-clamp-2">
                        {{ pinnedReminder.description }}
                    </p>
                </div>

                <!-- Empty State -->
                <div v-else class="py-8 text-center bg-gray-50/60 rounded-xl border border-dashed border-gray-200">
                    <p class="text-xs font-semibold text-gray-500">No upcoming schedules found for the selected filter.</p>
                    <button 
                        @click="navigateToTab('reminders')" 
                        class="mt-2 text-xs font-semibold text-red-600 hover:underline"
                    >
                        + Create office reminder or directive
                    </button>
                </div>

                <!-- COMPACT SCROLLABLE REMAINING SCHEDULE LIST -->
                <div v-if="remainingReminders.length > 0" class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                    <div class="px-4 py-2 bg-gray-50/80 border-b border-gray-200 flex items-center justify-between text-xs font-semibold text-gray-600">
                        <span>Other Scheduled Reminders ({{ remainingReminders.length }})</span>
                        <span class="text-[11px] text-gray-400">Scroll to oversee full calendar</span>
                    </div>

                    <div class="max-h-48 overflow-y-auto divide-y divide-gray-100">
                        <div 
                            v-for="item in remainingReminders" 
                            :key="item.id"
                            class="px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-gray-50/80 transition"
                        >
                            <div class="min-w-0 flex-1 flex items-center gap-2.5">
                                <span 
                                    class="w-2 h-2 rounded-full shrink-0" 
                                    :class="isToday(item.startsAt) ? 'bg-red-500' : 'bg-gray-300'"
                                ></span>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-900 truncate">{{ item.title }}</div>
                                    <div class="text-[11px] text-gray-500 truncate flex items-center gap-2">
                                        <span>{{ item.category || 'General' }}</span>
                                        <span>•</span>
                                        <span :class="item.audience === 'everyone' ? 'text-purple-700 font-semibold' : 'text-gray-500'">
                                            {{ item.audience === 'everyone' ? 'Everyone' : 'Personal' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span 
                                    class="px-2 py-0.5 text-[11px] font-semibold rounded-md"
                                    :class="isToday(item.startsAt) ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'"
                                >
                                    {{ getRelativeScheduleLabel(item.startsAt) }}
                                </span>
                                <div class="text-[10px] text-gray-400 font-mono mt-0.5">
                                    {{ formatReminderDateTime(item.startsAt) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. TWO-COLUMN OPERATIONAL MONITORING (PROJECT HEALTH & RISK MATRIX + STAFF DIRECTIVES) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Column 1: Projects Health & Delayed Risk Matrix (7 cols) -->
            <div class="lg:col-span-7 bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Infrastructure Projects Health & Progress</h3>
                                <p class="text-xs text-gray-500">Live accomplishment tracking across municipal public works</p>
                            </div>
                        </div>

                        <button 
                            @click="navigateToTab('projects')"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline"
                        >
                            View All Projects &rarr;
                        </button>
                    </div>

                    <!-- Progress Status Badges -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 my-4">
                        <div class="p-3 bg-gray-50 border border-gray-200/80 rounded-xl text-center">
                            <div class="text-xs font-bold text-blue-700 uppercase">Ongoing</div>
                            <div class="text-lg font-extrabold text-gray-900 mt-0.5">{{ projectStats.ongoing }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 border border-gray-200/80 rounded-xl text-center">
                            <div class="text-xs font-bold text-emerald-700 uppercase">Completed</div>
                            <div class="text-lg font-extrabold text-gray-900 mt-0.5">{{ projectStats.completed }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 border border-gray-200/80 rounded-xl text-center">
                            <div class="text-xs font-bold text-rose-700 uppercase">Delayed</div>
                            <div class="text-lg font-extrabold text-rose-600 mt-0.5">{{ projectStats.delayed }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 border border-gray-200/80 rounded-xl text-center">
                            <div class="text-xs font-bold text-gray-600 uppercase">Avg Progress</div>
                            <div class="text-lg font-extrabold text-gray-900 mt-0.5">{{ projectStats.avgAccomplishment }}%</div>
                        </div>
                    </div>

                    <!-- Delayed / Critical Projects Alert Box (If any) -->
                    <div v-if="criticalProjects.length > 0" class="mb-4 p-3.5 bg-rose-50 border border-rose-200 rounded-xl">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-rose-800 uppercase tracking-wider mb-2">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Projects Requiring Executive Attention (Delayed)</span>
                        </div>
                        <div class="space-y-2">
                            <div 
                                v-for="proj in criticalProjects" 
                                :key="'crit-' + proj.id"
                                class="p-2.5 bg-white border border-rose-200 rounded-lg flex items-center justify-between gap-3 text-xs"
                            >
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 truncate">{{ proj.project_name || proj.name }}</div>
                                    <div class="text-[11px] text-gray-500 truncate">{{ proj.location || 'Municipal Scope' }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="px-2 py-0.5 bg-rose-100 text-rose-800 font-bold rounded text-[11px]">
                                        {{ proj.accomplishment_rate || proj.accomplishment || 0 }}% Done
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Projects List -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600">
                            Recent Active Portfolios
                        </div>
                        <div class="divide-y divide-gray-100 text-xs">
                            <div 
                                v-for="proj in recentProjects" 
                                :key="proj.id"
                                class="p-3 flex items-center justify-between gap-3 hover:bg-gray-50/60 transition"
                            >
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 truncate">{{ proj.project_name || proj.name }}</div>
                                    <div class="text-[11px] text-gray-500 truncate flex items-center gap-2">
                                        <span>{{ proj.fund_source || 'General Fund' }}</span>
                                        <span>•</span>
                                        <span>{{ proj.location || 'MEO' }}</span>
                                    </div>
                                </div>
                                <div class="w-32 shrink-0">
                                    <div class="flex justify-between text-[11px] font-semibold text-gray-700 mb-1">
                                        <span>Accomplishment</span>
                                        <span>{{ proj.accomplishment_rate || proj.accomplishment || 0 }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                        <div 
                                            class="bg-blue-600 h-1.5 rounded-full" 
                                            :style="{ width: (proj.accomplishment_rate || proj.accomplishment || 0) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 flex justify-between items-center text-xs text-gray-500">
                    <span>Total Managed Projects: <strong class="text-gray-900">{{ projectStats.total }}</strong></span>
                    <button 
                        @click="navigateToTab('findproject')" 
                        class="text-blue-600 font-semibold hover:underline"
                    >
                        Search Project Records &rarr;
                    </button>
                </div>
            </div>

            <!-- Column 2: Staff Workloads & Urgent Directives (5 cols) -->
            <div class="lg:col-span-5 bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Staff Directives & Operations</h3>
                                <p class="text-xs text-gray-500">Active tasks and assigned directives</p>
                            </div>
                        </div>

                        <button 
                            @click="navigateToTab('staff')"
                            class="text-xs font-semibold text-amber-700 hover:underline"
                        >
                            Staff Tab &rarr;
                        </button>
                    </div>

                    <!-- Directives Breakdown Pills -->
                    <div class="grid grid-cols-3 gap-2.5 my-4">
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-center">
                            <div class="text-xs font-bold text-gray-500 uppercase">Active</div>
                            <div class="text-lg font-extrabold text-gray-900 mt-0.5">{{ activeDirectives.length }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-center">
                            <div class="text-xs font-bold text-rose-700 uppercase">Urgent</div>
                            <div class="text-lg font-extrabold text-rose-600 mt-0.5">{{ urgentDirectives.length }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-center">
                            <div class="text-xs font-bold text-emerald-700 uppercase">Staff Active</div>
                            <div class="text-lg font-extrabold text-emerald-700 mt-0.5">{{ userMetrics.staff }}</div>
                        </div>
                    </div>

                    <!-- Directives Feed -->
                    <div v-if="directives.length > 0" class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 flex justify-between">
                            <span>Live Assignment Log</span>
                            <span>Priority</span>
                        </div>
                        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                            <div 
                                v-for="d in directives.slice(0, 6)" 
                                :key="d.id"
                                class="p-3 text-xs flex items-center justify-between gap-3 hover:bg-gray-50/60 transition"
                            >
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 truncate">{{ d.title || d.directive }}</div>
                                    <div class="text-[11px] text-gray-500 truncate flex items-center gap-1.5">
                                        <span class="font-medium text-gray-700">{{ d.staff_name || 'Staff' }}</span>
                                        <span>•</span>
                                        <span>{{ d.targetDeadline ? formatReminderDateTime(d.targetDeadline) : 'No Deadline' }}</span>
                                    </div>
                                </div>
                                <span 
                                    class="px-2 py-0.5 text-[10px] font-bold rounded uppercase tracking-wider shrink-0"
                                    :class="{
                                        'bg-rose-100 text-rose-800': d.priority === 'urgent',
                                        'bg-amber-100 text-amber-800': d.priority === 'high',
                                        'bg-gray-100 text-gray-700': d.priority !== 'urgent' && d.priority !== 'high'
                                    }"
                                >
                                    {{ d.priority || 'Normal' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty Directives State -->
                    <div v-else class="py-10 text-center bg-gray-50/60 rounded-xl border border-dashed border-gray-200 text-xs text-gray-400">
                        No active directives found. Assign tasks via the Staff tab.
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 flex justify-between items-center text-xs text-gray-500">
                    <span>Monitored Staff: <strong class="text-gray-900">{{ userMetrics.staff }}</strong></span>
                    <button 
                        @click="navigateToTab('staff')" 
                        class="text-amber-700 font-semibold hover:underline"
                    >
                        Assign New Task &rarr;
                    </button>
                </div>
            </div>

        </div>

        <!-- 5. EXECUTIVE QUICK ACTION SHORTCUTS (ALL-WHITE TILES) -->
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Executive Management Shortcuts</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                
                <!-- Tile 1: Provision New User -->
                <div 
                    @click="navigateToTab('users')"
                    class="group bg-white border border-gray-200 p-5 cursor-pointer hover:border-blue-500/50 hover:shadow-md transition-all duration-200 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="p-2.5 bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-200 rounded-xl">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                        <span class="text-gray-300 group-hover:text-blue-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-4">
                        <h4 class="font-bold text-gray-900 group-hover:text-blue-600 transition-colors text-sm">User & Credential Control</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Provision new staff accounts, manage roles, and reset credentials.</p>
                    </div>
                </div>

                <!-- Tile 2: Project Management -->
                <div 
                    @click="navigateToTab('projects')"
                    class="group bg-white border border-gray-200 p-5 cursor-pointer hover:border-emerald-500/50 hover:shadow-md transition-all duration-200 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="p-2.5 bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-200 rounded-xl">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-gray-300 group-hover:text-emerald-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-4">
                        <h4 class="font-bold text-gray-900 group-hover:text-emerald-600 transition-colors text-sm">Project Master Registry</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Create and track engineering project records, technical preparations, and budgets.</p>
                    </div>
                </div>

                <!-- Tile 3: Public Bulletin Broadcasts -->
                <div 
                    @click="navigateToTab('bulletin')"
                    class="group bg-white border border-gray-200 p-5 cursor-pointer hover:border-purple-500/50 hover:shadow-md transition-all duration-200 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="p-2.5 bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-colors duration-200 rounded-xl">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                            </svg>
                        </div>
                        <span class="text-gray-300 group-hover:text-purple-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-4">
                        <h4 class="font-bold text-gray-900 group-hover:text-purple-600 transition-colors text-sm">Municipal Bulletin Broadcast</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Broadcast announcements, safety advisories, and office notices.</p>
                    </div>
                </div>

                <!-- Tile 4: System Logs & Audit Trail -->
                <div 
                    @click="navigateToTab('logs')"
                    class="group bg-white border border-gray-200 p-5 cursor-pointer hover:border-gray-500/50 hover:shadow-md transition-all duration-200 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="p-2.5 bg-gray-100 text-gray-700 group-hover:bg-gray-800 group-hover:text-white transition-colors duration-200 rounded-xl">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-gray-300 group-hover:text-gray-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-4">
                        <h4 class="font-bold text-gray-900 group-hover:text-gray-700 transition-colors text-sm">System Security & Logs</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Review system events, transaction logs, and authentication records.</p>
                    </div>
                </div>

                <!-- Tile 5: Welcome Portal CMS -->
                <div 
                    @click="navigateToTab('welcome')"
                    class="group bg-white border border-gray-200 p-5 cursor-pointer hover:border-rose-500/50 hover:shadow-md transition-all duration-200 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="p-2.5 bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-colors duration-200 rounded-xl">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-gray-300 group-hover:text-rose-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-4">
                        <h4 class="font-bold text-gray-900 group-hover:text-rose-600 transition-colors text-sm">Portal Homepage CMS</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Update public municipal homepage hero banners, mayor's desk, and services.</p>
                    </div>
                </div>

                <!-- Tile 6: Portal Settings -->
                <div 
                    @click="navigateToTab('settings')"
                    class="group bg-white border border-gray-200 p-5 cursor-pointer hover:border-gray-900 hover:shadow-md transition-all duration-200 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="p-2.5 bg-gray-50 text-gray-800 group-hover:bg-gray-900 group-hover:text-white transition-colors duration-200 rounded-xl">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <span class="text-gray-300 group-hover:text-gray-900 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-4">
                        <h4 class="font-bold text-gray-900 group-hover:text-gray-900 transition-colors text-sm">System & Office Config</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Manage system parameters, backup protocols, and operational preferences.</p>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>

<style scoped>
/* Scoped overrides if required */
</style>