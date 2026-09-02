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
            admin_count: 0,
            staff_count: 0,
        }),
    },
    projects: {
        type: Array,
        default: () => [],
    },
    inquiries: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['tab-change', 'navigate-tab', 'create-user', 'view-reports', 'open-settings']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || { name: 'Authorized Official' });

// ================= PHILIPPINE STANDARD TIME & OFFICIAL DATE =================
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
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});

// ================= DATA FETCHING (REMINDERS & DIRECTIVES) =================
const reminders = ref([]);
const loadingReminders = ref(false);
const directives = ref([]);
const loadingDirectives = ref(false);
const isRefreshing = ref(false);

const fetchReminders = async () => {
    loadingReminders.value = true;
    try {
        const response = await axios.get('/reminders');
        reminders.value = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
        console.error('Failed to load official calendar records:', err);
    } finally {
        loadingReminders.value = false;
    }
};

const fetchDirectives = async () => {
    loadingDirectives.value = true;
    try {
        const res = await axios.get('/staff-assignments');
        if (res.data?.assignments) {
            directives.value = res.data.assignments;
        }
    } catch (e) {
        console.error('Failed to load staff task directives:', e);
    } finally {
        loadingDirectives.value = false;
    }
};

const refreshAllData = async () => {
    isRefreshing.value = true;
    await Promise.allSettled([
        fetchReminders(),
        fetchDirectives(),
    ]);
    isRefreshing.value = false;
};

// ================= PROJECT ANALYTICS & COMPUTATIONS =================
const normalizeStatus = (status) => {
    if (typeof status === 'number') {
        const map = { 0: 'Ongoing', 1: 'Completed', 2: 'Delayed', 3: 'Not Started', 4: 'Suspended' };
        return map[status] || 'Ongoing';
    }
    if (typeof status === 'string') {
        const s = status.trim().toLowerCase();
        if (s === 'ongoing' || s === '0') return 'Ongoing';
        if (s === 'completed' || s === '1') return 'Completed';
        if (s === 'delayed' || s === '2') return 'Delayed';
        if (s === 'not started' || s === '3') return 'Not Started';
        if (s === 'suspended' || s === '4') return 'Suspended';
        return status;
    }
    return 'Ongoing';
};

const projectStats = computed(() => {
    const list = props.projects || [];
    const total = list.length;

    let totalBudget = 0;
    let ongoing = 0;
    let completed = 0;
    let delayed = 0;
    let notStarted = 0;
    let suspended = 0;
    let totalAccomplishment = 0;

    list.forEach(p => {
        const cost = Number(p.totalCost || p.total_project_cost || 0);
        if (!isNaN(cost)) totalBudget += cost;

        const acc = Number(p.accomplishment ?? p.percentage_of_accomplishment ?? 0);
        if (!isNaN(acc)) totalAccomplishment += acc;

        const st = normalizeStatus(p.status);
        if (st === 'Ongoing') ongoing++;
        else if (st === 'Completed') completed++;
        else if (st === 'Delayed') delayed++;
        else if (st === 'Not Started') notStarted++;
        else if (st === 'Suspended') suspended++;
    });

    const avgAccomplishment = total > 0 ? Math.round(totalAccomplishment / total) : 0;

    return {
        total,
        totalBudget,
        ongoing,
        completed,
        delayed,
        notStarted,
        suspended,
        avgAccomplishment,
    };
});

// Priority Active Infrastructure Works
const activeProjectsProgress = computed(() => {
    return (props.projects || [])
        .filter(p => normalizeStatus(p.status) === 'Ongoing' || normalizeStatus(p.status) === 'Delayed')
        .slice(0, 5);
});

// Slippage / Delayed Project Concerns requiring LGU Intervention
const criticalProjectConcerns = computed(() => {
    return (props.projects || []).filter(p => {
        const st = normalizeStatus(p.status);
        return st === 'Delayed' || st === 'Suspended';
    }).slice(0, 4);
});

// ================= CITIZEN CONCERNS & ARTA (RA 11032) COMPLIANCE =================
const inquiryStats = computed(() => {
    const list = props.inquiries || [];
    const total = list.length;
    const pending = list.filter(i => i.status === 'pending').length;
    const inProgress = list.filter(i => i.status === 'in_progress' || i.status === 'accepted').length;
    const resolved = list.filter(i => i.status === 'resolved').length;
    const rate = total > 0 ? Math.round((resolved / total) * 100) : 100;

    return {
        total,
        pending,
        inProgress,
        resolved,
        rate,
    };
});

const recentCitizenConcerns = computed(() => {
    return (props.inquiries || []).slice(0, 4);
});

// ================= REMINDERS & DIRECTIVES =================
const activeReminders = computed(() => {
    return reminders.value.filter(r => !r.isDone);
});

const upcomingReminders = computed(() => {
    return [...activeReminders.value]
        .sort((a, b) => new Date(a.startsAt || a.starts_at) - new Date(b.startsAt || b.starts_at))
        .slice(0, 4);
});

const activeDirectivesList = computed(() => {
    return directives.value.filter(d => d.status !== 'completed').slice(0, 4);
});

// ================= HELPERS & OFFICIAL FORMATTERS =================
const formatCurrency = (amount) => {
    if (amount === undefined || amount === null || isNaN(amount)) return 'PHP 0.00';
    return 'PHP ' + new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
};

const formatShortDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
};

const navigateTo = (tabName) => {
    emit('tab-change', tabName);
    emit('navigate-tab', tabName);
};

const getStatusBadgeClass = (status) => {
    const s = normalizeStatus(status);
    switch (s) {
        case 'Ongoing':
            return 'bg-blue-50 text-blue-800 border-blue-300';
        case 'Completed':
            return 'bg-emerald-50 text-emerald-800 border-emerald-300';
        case 'Delayed':
            return 'bg-rose-50 text-rose-800 border-rose-300';
        case 'Suspended':
            return 'bg-amber-50 text-amber-800 border-amber-300';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-300';
    }
};

const getInquiryBadgeClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-amber-50 text-amber-800 border-amber-300';
        case 'accepted':
        case 'in_progress':
            return 'bg-blue-50 text-blue-800 border-blue-300';
        case 'resolved':
            return 'bg-emerald-50 text-emerald-800 border-emerald-300';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-300';
    }
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
    <div class="w-full font-sans antialiased text-slate-900 space-y-6 pb-12 p-4 sm:p-6 lg:p-8 bg-slate-50/60 min-h-screen">
        
        <!-- 1. OFFICIAL LGU EXECUTIVE HEADER -->
        <div class="bg-slate-900 border-l-4 border-l-red-700 border-y border-r border-slate-800 text-white p-5 sm:p-6 shadow-xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-slate-800 border border-slate-700 text-slate-200 font-mono">
                            <span class="w-1.5 h-1.5 bg-emerald-400"></span>
                            OFFICIAL SYSTEM STATUS: OPERATIONAL
                        </span>
                        <span>•</span>
                        <span>REPUBLIC OF THE PHILIPPINES</span>
                        <span>•</span>
                        <span>PROVINCE OF MISAMIS ORIENTAL</span>
                        <span>•</span>
                        <span class="text-slate-200">MUNICIPALITY OF OPOL</span>
                    </div>

                    <div class="flex items-baseline gap-3 pt-1">
                        <h2 class="text-xl sm:text-2xl font-black tracking-tight text-white uppercase font-sans">
                            Office of the Municipal Engineer
                        </h2>
                        <span class="text-[10px] font-bold px-2 py-0.5 bg-red-950 text-red-300 border border-red-700 font-mono">
                            ADMINISTRATIVE CONSOLE
                        </span>
                    </div>

                    <p class="text-xs text-slate-300 max-w-3xl leading-relaxed">
                        {{ greetingMessage }}, <strong class="text-white font-bold">{{ currentUser.name }}</strong>. Official executive surveillance covering public capital outlays, municipal infrastructure contracts, citizen public service requests, and statutory departmental directives.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row lg:flex-col items-start lg:items-end justify-between gap-3 shrink-0 border-t lg:border-t-0 border-slate-800 pt-3 lg:pt-0">
                    <div class="text-left lg:text-right font-mono">
                        <div class="text-[11px] font-bold text-slate-200">{{ currentDateStr }}</div>
                        <div class="text-xs font-black text-red-400 tracking-wider">{{ currentTimeStr }} <span class="text-[10px] font-normal text-slate-400 font-sans">PST (GMT+8)</span></div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="refreshAllData"
                            :disabled="isRefreshing"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 text-xs font-semibold uppercase tracking-wider transition-all disabled:opacity-50"
                            title="Synchronize real-time operational data"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-red-400" :class="{ 'animate-spin': isRefreshing || loadingReminders || loadingDirectives }">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span>{{ isRefreshing ? 'Syncing...' : 'Sync Records' }}</span>
                        </button>
                        <button
                            @click="navigateTo('projects')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-bold uppercase tracking-wider transition-all border border-red-600"
                        >
                            <span>Project Registry</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. EXECUTIVE SUMMARY: 4 FORMAL GOVERNMENT SURVEILLANCE CARDS -->
        <div>
            <div class="flex items-center justify-between pb-2 border-b border-slate-300 mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-red-700"></span>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">Executive Summary & Operational Indicators</h3>
                </div>
                <span class="text-[11px] text-slate-500 font-mono">AS OF FISCAL YEAR {{ new Date().getFullYear() }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Card 1: Total Appropriations & Infrastructure Portfolio -->
                <div class="bg-white border border-slate-300 p-4 sm:p-5 flex flex-col justify-between shadow-xs">
                    <div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100 pb-2">
                            <span>Capital Outlay Portfolio</span>
                            <span class="text-[10px] font-mono text-slate-400 font-bold">SEC-01</span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-slate-900 font-mono">
                                {{ projectStats.total }}
                                <span class="text-xs font-bold font-sans text-slate-500 uppercase tracking-normal">Projects</span>
                            </div>
                            <p class="text-xs font-bold text-emerald-700 mt-1 font-mono truncate">
                                {{ formatCurrency(projectStats.totalBudget) }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                        <span>Physical Delivery:</span>
                        <span class="font-bold text-slate-900 font-mono">{{ projectStats.avgAccomplishment }}% Average</span>
                    </div>
                </div>

                <!-- Card 2: COA / Physical Execution Status -->
                <div class="bg-white border border-slate-300 p-4 sm:p-5 flex flex-col justify-between shadow-xs">
                    <div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100 pb-2">
                            <span>Implementation Status</span>
                            <span class="text-[10px] font-mono text-slate-400 font-bold">SEC-02</span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-blue-900 font-mono">
                                {{ projectStats.ongoing }}
                                <span class="text-xs font-bold font-sans text-blue-700 uppercase tracking-normal">Active Ongoing</span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1 font-medium">
                                <span class="font-bold text-emerald-700">{{ projectStats.completed }}</span> Completed • 
                                <span class="font-bold text-rose-700">{{ projectStats.delayed }}</span> Delayed
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[10px] font-bold font-mono">
                        <span class="px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200">{{ projectStats.completed }} TURNED OVER</span>
                        <span class="px-2 py-0.5 bg-rose-50 text-rose-800 border border-rose-200">{{ projectStats.delayed }} DELAYED</span>
                        <span class="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200">{{ projectStats.suspended }} ON-HOLD</span>
                    </div>
                </div>

                <!-- Card 3: Citizen Public Service & ARTA RA 11032 -->
                <div class="bg-white border border-slate-300 p-4 sm:p-5 flex flex-col justify-between shadow-xs">
                    <div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100 pb-2">
                            <span>Citizen Public Inquiries</span>
                            <span class="text-[10px] font-mono text-slate-400 font-bold">RA 11032</span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-slate-900 font-mono">
                                {{ inquiryStats.total }}
                                <span class="text-xs font-bold font-sans text-slate-500 uppercase tracking-normal">Logged Tickets</span>
                            </div>
                            <p class="text-xs font-bold text-amber-700 mt-1">
                                {{ inquiryStats.pending }} Pending Administrative Action
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                        <span>Resolution Efficiency:</span>
                        <span class="font-bold text-slate-900 font-mono">{{ inquiryStats.rate }}% Cleared</span>
                    </div>
                </div>

                <!-- Card 4: Technical Personnel & Task Directives -->
                <div class="bg-white border border-slate-300 p-4 sm:p-5 flex flex-col justify-between shadow-xs">
                    <div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100 pb-2">
                            <span>Directives & Personnel</span>
                            <span class="text-[10px] font-mono text-slate-400 font-bold">SEC-04</span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-slate-900 font-mono">
                                {{ activeDirectivesList.length }}
                                <span class="text-xs font-bold font-sans text-slate-500 uppercase tracking-normal">Active Directives</span>
                            </div>
                            <p class="text-xs font-bold text-purple-700 mt-1">
                                {{ activeReminders.length }} Scheduled Statutory Milestones
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                        <span>Authorized Personnel:</span>
                        <span class="font-bold text-slate-900 font-mono">{{ props.stats.staff_count || props.users.length || 0 }} Staff</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. PROJECT EXECUTION & PHYSICAL PROGRESS OVERVIEW -->
        <div class="bg-white border border-slate-300 p-5 sm:p-6 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200 mb-4">
                <div>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Physical Accomplishment & Progress Surveillance
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Monitoring contract milestones, contractor performance, and site implementation rates.</p>
                </div>
                <button
                    @click="navigateTo('projects')"
                    class="text-xs font-bold text-red-700 hover:text-red-900 uppercase tracking-wider flex items-center gap-1 self-start sm:self-auto"
                >
                    <span>Full Project Registry ({{ projectStats.total }})</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>

            <!-- Weighted Overall Accomplishment Bar -->
            <div class="bg-slate-100 border border-slate-300 p-4 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-0.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Aggregated Municipal Infrastructure Accomplishment</span>
                    <div class="text-xl font-black text-slate-900 font-mono">{{ projectStats.avgAccomplishment }}% <span class="text-xs font-semibold text-slate-600 font-sans">Weighted Physical Delivery</span></div>
                </div>
                <div class="w-full sm:w-1/2">
                    <div class="w-full bg-slate-300 h-3 border border-slate-400 overflow-hidden">
                        <div
                            class="bg-gradient-to-r from-red-700 via-amber-600 to-emerald-600 h-3 transition-all duration-500"
                            :style="{ width: `${projectStats.avgAccomplishment}%` }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Active Projects Progress Table / Cards -->
            <div v-if="activeProjectsProgress.length === 0" class="py-8 text-center text-xs text-slate-400 border border-dashed border-slate-200">
                No active ongoing projects recorded in active registry.
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="project in activeProjectsProgress"
                    :key="project.id"
                    @click="navigateTo('projects')"
                    class="p-4 border border-slate-200 hover:border-slate-400 bg-white hover:bg-slate-50 transition-all cursor-pointer space-y-2.5"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="space-y-0.5">
                            <h4 class="text-xs font-bold text-slate-900 hover:text-red-700 transition-colors uppercase">
                                {{ project.name }}
                            </h4>
                            <p class="text-[11px] text-slate-500 flex flex-wrap items-center gap-2">
                                <span class="font-medium text-slate-700">Location: {{ project.location || 'Municipality Wide' }}</span>
                                <span>•</span>
                                <span>Contractor: {{ project.contractor || 'MEO Administered' }}</span>
                                <span v-if="project.sourceOfFund" class="font-mono text-slate-600">• [{{ project.sourceOfFund }}]</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3 self-start sm:self-auto">
                            <span class="text-xs font-mono font-bold text-slate-900">{{ formatCurrency(project.totalCost) }}</span>
                            <span :class="['px-2.5 py-0.5 text-[10px] font-bold border uppercase font-mono tracking-wider', getStatusBadgeClass(project.status)]">
                                {{ normalizeStatus(project.status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Progress Bar Component -->
                    <div class="space-y-1 pt-1">
                        <div class="flex justify-between text-[11px] font-mono">
                            <span class="text-slate-500 font-sans text-[10px] uppercase font-bold">Physical Accomplishment</span>
                            <span class="font-black text-slate-900">{{ project.accomplishment || 0 }}%</span>
                        </div>
                        <div class="w-full bg-slate-200 h-2 border border-slate-300 overflow-hidden">
                            <div
                                class="h-2 transition-all duration-300"
                                :class="Number(project.accomplishment) >= 100 ? 'bg-emerald-600' : (Number(project.accomplishment) >= 50 ? 'bg-blue-700' : 'bg-red-700')"
                                :style="{ width: `${project.accomplishment || 0}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. 2-COLUMN SECTION: CONCERNS OVERVIEW & REMINDERS OVERVIEW -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            
            <!-- CONCERNS OVERVIEW (Delayed Projects + Citizen Public Inquiries) -->
            <div class="bg-white border border-slate-300 p-5 sm:p-6 flex flex-col justify-between shadow-xs">
                <div>
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Concerns & Variance Surveillance</h3>
                            <p class="text-xs text-slate-500">Critical slippage alerts & pending citizen public service inquiries.</p>
                        </div>
                        <span class="text-xs font-bold font-mono text-rose-800 bg-rose-50 px-2.5 py-1 border border-rose-300">
                            {{ criticalProjectConcerns.length + inquiryStats.pending }} UNRESOLVED
                        </span>
                    </div>

                    <!-- Project Health Variance / Slippage Alerts -->
                    <div v-if="criticalProjectConcerns.length > 0" class="mb-5 space-y-2">
                        <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-rose-800 pb-1 border-b border-rose-100">
                            <span>Infrastructure Slippage & Critical Delays</span>
                            <span>ACTION REQUIRED</span>
                        </div>
                        <div
                            v-for="p in criticalProjectConcerns"
                            :key="p.id"
                            @click="navigateTo('projects')"
                            class="p-3 bg-rose-50/70 border border-rose-300 hover:bg-rose-100/60 transition-all cursor-pointer flex items-center justify-between gap-3 text-xs"
                        >
                            <div class="truncate max-w-sm space-y-0.5">
                                <p class="font-bold text-rose-950 truncate uppercase">{{ p.name }}</p>
                                <p class="text-[11px] text-rose-800">
                                    {{ p.location || 'Municipal Scope' }} • <span class="font-mono font-bold">{{ p.accomplishment || 0 }}% completed</span>
                                </p>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-rose-200 text-rose-900 border border-rose-400 font-mono uppercase shrink-0">
                                {{ normalizeStatus(p.status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Citizen Concerns / ARTA Inquiries -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-700 pb-1 border-b border-slate-100">
                            <span>Recent Public Service Inquiries (Ask MEO)</span>
                            <span>CITIZEN CHARTER</span>
                        </div>
                        <div v-if="recentCitizenConcerns.length === 0" class="py-4 text-center text-xs text-slate-400 border border-dashed border-slate-200">
                            No citizen inquiries on record.
                        </div>
                        <div
                            v-for="inq in recentCitizenConcerns"
                            :key="inq.id"
                            @click="navigateTo('messages')"
                            class="p-3 bg-slate-50 border border-slate-300 hover:bg-slate-100 transition-all cursor-pointer flex items-center justify-between gap-3 text-xs"
                        >
                            <div class="truncate max-w-sm space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-slate-900 truncate">{{ inq.fullname }}</p>
                                    <span :class="['px-1.5 py-0.2 text-[9px] font-bold uppercase font-mono border', getInquiryBadgeClass(inq.status)]">
                                        {{ inq.status || 'pending' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-600 truncate">{{ inq.subject || 'General Municipal Inquiry' }}</p>
                            </div>
                            <span class="text-[10px] text-slate-500 font-mono whitespace-nowrap">
                                {{ inq.created_at_relative || inq.created_at || 'Recent' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-200">
                    <button
                        @click="navigateTo('messages')"
                        class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold uppercase tracking-wider transition-all border border-slate-300 text-center"
                    >
                        Access Citizen Helpdesk Communications Center →
                    </button>
                </div>
            </div>

            <!-- REMINDERS & DIRECTIVES OVERVIEW -->
            <div class="bg-white border border-slate-300 p-5 sm:p-6 flex flex-col justify-between shadow-xs">
                <div>
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Statutory Calendar & Staff Directives</h3>
                            <p class="text-xs text-slate-500">Official schedule deadlines, BAC milestones, and assigned work orders.</p>
                        </div>
                        <span class="text-xs font-bold font-mono text-purple-800 bg-purple-50 px-2.5 py-1 border border-purple-300">
                            {{ activeReminders.length }} SCHEDULED
                        </span>
                    </div>

                    <!-- Upcoming Statutory Calendar Reminders -->
                    <div class="mb-5 space-y-2">
                        <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-700 pb-1 border-b border-slate-100">
                            <span>Upcoming Calendar Milestones & Deadlines</span>
                            <span>OFFICE SCHEDULE</span>
                        </div>
                        <div v-if="upcomingReminders.length === 0" class="py-4 text-center text-xs text-slate-400 border border-dashed border-slate-200">
                            No upcoming statutory deadlines on calendar.
                        </div>
                        <div
                            v-for="rem in upcomingReminders"
                            :key="rem.id"
                            @click="navigateTo('reminders')"
                            class="p-2.5 bg-slate-50 border border-slate-300 hover:bg-slate-100 transition-all cursor-pointer flex items-center justify-between gap-3 text-xs"
                        >
                            <div class="flex items-center gap-2 truncate">
                                <span class="w-2 h-2 bg-red-700 shrink-0"></span>
                                <p class="font-bold text-slate-900 truncate">{{ rem.title }}</p>
                            </div>
                            <span class="text-[11px] font-mono font-bold text-slate-700 whitespace-nowrap">
                                {{ formatDate(rem.startsAt || rem.starts_at) }}
                            </span>
                        </div>
                    </div>

                    <!-- Active Staff Work Directives -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-700 pb-1 border-b border-slate-100">
                            <span>Field Inspection & Internal Staff Directives</span>
                            <span>ASSIGNED TASKS</span>
                        </div>
                        <div v-if="activeDirectivesList.length === 0" class="py-4 text-center text-xs text-slate-400 border border-dashed border-slate-200">
                            No active staff task directives.
                        </div>
                        <div
                            v-for="dir in activeDirectivesList"
                            :key="dir.id"
                            @click="navigateTo('staff')"
                            class="p-2.5 bg-slate-50 border border-slate-300 hover:bg-slate-100 transition-all cursor-pointer flex items-center justify-between gap-3 text-xs"
                        >
                            <div class="truncate max-w-sm space-y-0.5">
                                <p class="font-bold text-slate-900 truncate uppercase">{{ dir.title }}</p>
                                <p class="text-[11px] text-slate-600">Assigned: <strong class="text-slate-800">{{ dir.user_name || 'Staff Engineer' }}</strong></p>
                            </div>
                            <span class="text-[10px] font-mono font-bold text-slate-700 whitespace-nowrap">
                                DUE: {{ formatShortDate(dir.target_deadline) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-200 flex items-center gap-2">
                    <button
                        @click="navigateTo('reminders')"
                        class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold uppercase tracking-wider transition-all border border-slate-300 text-center"
                    >
                        Calendar Schedule
                    </button>
                    <button
                        @click="navigateTo('staff')"
                        class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold uppercase tracking-wider transition-all border border-slate-300 text-center"
                    >
                        Staff Directives
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. OFFICIAL DEPARTMENTAL MODULE QUICK-LAUNCH DIRECTORY -->
        <div>
            <div class="flex items-center justify-between pb-2 border-b border-slate-300 mb-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">Departmental Management Modules</h3>
                <span class="text-[11px] text-slate-500 uppercase">DIRECT ACCESS DIRECTORY</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div
                    @click="navigateTo('projects')"
                    class="bg-white border border-slate-300 p-3.5 cursor-pointer hover:border-red-700 hover:bg-red-50/30 transition-all text-center flex flex-col items-center justify-center space-y-1.5 group"
                >
                    <span class="p-2 bg-slate-100 text-red-800 border border-slate-200 group-hover:bg-red-700 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">Project Master</span>
                </div>

                <div
                    @click="navigateTo('findproject')"
                    class="bg-white border border-slate-300 p-3.5 cursor-pointer hover:border-blue-700 hover:bg-blue-50/30 transition-all text-center flex flex-col items-center justify-center space-y-1.5 group"
                >
                    <span class="p-2 bg-slate-100 text-blue-800 border border-slate-200 group-hover:bg-blue-700 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">GIS Locator</span>
                </div>

                <div
                    @click="navigateTo('messages')"
                    class="bg-white border border-slate-300 p-3.5 cursor-pointer hover:border-amber-700 hover:bg-amber-50/30 transition-all text-center flex flex-col items-center justify-center space-y-1.5 group"
                >
                    <span class="p-2 bg-slate-100 text-amber-800 border border-slate-200 group-hover:bg-amber-700 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">Public Desk</span>
                </div>

                <div
                    @click="navigateTo('staff')"
                    class="bg-white border border-slate-300 p-3.5 cursor-pointer hover:border-purple-700 hover:bg-purple-50/30 transition-all text-center flex flex-col items-center justify-center space-y-1.5 group"
                >
                    <span class="p-2 bg-slate-100 text-purple-800 border border-slate-200 group-hover:bg-purple-700 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">Personnel</span>
                </div>

                <div
                    @click="navigateTo('bulletin')"
                    class="bg-white border border-slate-300 p-3.5 cursor-pointer hover:border-emerald-700 hover:bg-emerald-50/30 transition-all text-center flex flex-col items-center justify-center space-y-1.5 group"
                >
                    <span class="p-2 bg-slate-100 text-emerald-800 border border-slate-200 group-hover:bg-emerald-700 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">Issuances</span>
                </div>

                <div
                    @click="navigateTo('settings')"
                    class="bg-white border border-slate-300 p-3.5 cursor-pointer hover:border-slate-800 hover:bg-slate-100 transition-all text-center flex flex-col items-center justify-center space-y-1.5 group"
                >
                    <span class="p-2 bg-slate-100 text-slate-800 border border-slate-200 group-hover:bg-slate-900 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.754c-.29.218-.44.573-.4 1.23.013.201.02.404.02.608 0 .204-.007.407-.02.608-.04.657.11 1.012.4 1.23l1.003.754a1.125 1.125 0 0 1 .26 1.43l-1.297 2.247a1.125 1.125 0 0 1-1.37.491l-1.216-.456c-.356-.133-.751-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.754c.29-.218.44-.573.4-1.23a7.115 7.115 0 0 1-.02-.608c0-.204.007-.407.02-.608.04-.657-.11-1.012-.4-1.23l-1.004-.754a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">Config</span>
                </div>
            </div>
        </div>

    </div>
</template>