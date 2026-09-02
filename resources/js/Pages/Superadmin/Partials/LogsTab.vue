<script setup>
import { ref, reactive, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

// ================= STATE =================
const logs = ref([]);
const loading = ref(false);
const initialLoaded = ref(false);
const autoSync = ref(false);
let syncTimer = null;

const stats = reactive({
    total_logs: 0,
    today_logs: 0,
    alerts_count: 0,
    unique_users: 0,
});

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
});

const filters = reactive({
    search: '',
    module: 'all',
    severity: 'all',
    role: 'all',
    date_range: 'all',
});

// Modal State
const selectedLog = ref(null);
const showInspectModal = ref(false);
const showPruneModal = ref(false);
const pruneDays = ref(30);
const pruning = ref(false);

// Toast
const toast = reactive({
    show: false,
    message: '',
    type: 'success',
});

const showToast = (message, type = 'success') => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    setTimeout(() => {
        toast.show = false;
    }, 3500);
};

// ================= API FETCH =================
const fetchLogs = async (page = 1, showSpinner = true) => {
    if (showSpinner) loading.value = true;
    try {
        const response = await axios.get('/superadmin/activity-logs', {
            params: {
                page,
                per_page: pagination.per_page,
                search: filters.search || undefined,
                module: filters.module !== 'all' ? filters.module : undefined,
                severity: filters.severity !== 'all' ? filters.severity : undefined,
                role: filters.role !== 'all' ? filters.role : undefined,
                date_range: filters.date_range !== 'all' ? filters.date_range : undefined,
            },
        });

        if (response.data?.success) {
            const data = response.data.logs;
            logs.value = data.data || [];
            pagination.current_page = data.current_page;
            pagination.last_page = data.last_page;
            pagination.total = data.total;
            pagination.from = data.from || 0;
            pagination.to = data.to || 0;

            if (response.data.stats) {
                stats.total_logs = response.data.stats.total_logs || 0;
                stats.today_logs = response.data.stats.today_logs || 0;
                stats.alerts_count = response.data.stats.alerts_count || 0;
                stats.unique_users = response.data.stats.unique_users || 0;
            }
        }
    } catch (error) {
        console.error('Failed to load logs:', error);
        if (showSpinner) {
            showToast('Unable to load activity logs.', 'error');
        }
    } finally {
        if (showSpinner) loading.value = false;
        initialLoaded.value = true;
    }
};

// Debounced Search
let debounceTimer = null;
watch(() => filters.search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        pagination.current_page = 1;
        fetchLogs(1);
    }, 300);
});

watch([() => filters.module, () => filters.severity, () => filters.role, () => filters.date_range, () => pagination.per_page], () => {
    pagination.current_page = 1;
    fetchLogs(1);
});

// Auto Sync
const toggleAutoSync = () => {
    autoSync.value = !autoSync.value;
    if (autoSync.value) {
        syncTimer = setInterval(() => {
            if (!loading.value && !showInspectModal.value && !showPruneModal.value) {
                fetchLogs(pagination.current_page, false);
            }
        }, 10000);
        showToast('Auto-sync enabled (10s interval).');
    } else {
        if (syncTimer) clearInterval(syncTimer);
        syncTimer = null;
        showToast('Auto-sync disabled.');
    }
};

// Export CSV
const exportCsv = () => {
    const params = new URLSearchParams();
    if (filters.module !== 'all') params.append('module', filters.module);
    if (filters.severity !== 'all') params.append('severity', filters.severity);
    window.open(`/superadmin/activity-logs/export?${params.toString()}`, '_blank');
    showToast('Downloading CSV log report...');
};

// Inspect Log
const openInspect = (log) => {
    selectedLog.value = log;
    showInspectModal.value = true;
};

// Prune Logs
const handlePrune = async () => {
    pruning.value = true;
    try {
        const response = await axios.post('/superadmin/activity-logs/clear', {
            older_than_days: pruneDays.value,
        });
        if (response.data?.success) {
            showToast(response.data.message || 'Logs pruned successfully.');
            showPruneModal.value = false;
            fetchLogs(1);
        }
    } catch (err) {
        showToast('Error pruning activity logs.', 'error');
    } finally {
        pruning.value = false;
    }
};

// Helpers
const getSeverityStyle = (severity) => {
    switch (severity) {
        case 'danger':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'warning':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'success':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default:
            return 'bg-slate-50 text-slate-600 border-slate-200';
    }
};

const getRoleStyle = (role) => {
    switch (role?.toLowerCase()) {
        case 'superadmin':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'admin':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'staff':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'citizen':
            return 'bg-teal-50 text-teal-700 border-teal-200';
        default:
            return 'bg-gray-50 text-gray-600 border-gray-200';
    }
};

onMounted(() => {
    fetchLogs(1);
});

onUnmounted(() => {
    if (syncTimer) clearInterval(syncTimer);
});
</script>

<template>
    <div class="w-full space-y-5">
        
        <!-- Toast Feedback -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast.show"
                :class="[
                    'fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-lg shadow-lg border text-sm font-medium flex items-center gap-2.5',
                    toast.type === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-slate-900 text-white border-slate-800'
                ]"
            >
                <i :class="toast.type === 'error' ? 'ri-error-warning-line' : 'ri-check-line'"></i>
                <span>{{ toast.message }}</span>
            </div>
        </transition>

        <!-- 1. CLEAN HEADER -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 tracking-tight">Activity Logs</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Audit trail of system events, authentication, projects, and citizen inquiry actions.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Auto-sync Toggle -->
                <button
                    @click="toggleAutoSync"
                    :class="[
                        'px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors flex items-center gap-1.5 cursor-pointer',
                        autoSync ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
                    ]"
                    title="Toggle 10s auto-refresh"
                >
                    <span :class="['w-1.5 h-1.5 rounded-full', autoSync ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400']"></span>
                    <span>{{ autoSync ? 'Live' : 'Sync Off' }}</span>
                </button>

                <!-- Refresh -->
                <button
                    @click="fetchLogs(pagination.current_page)"
                    :disabled="loading"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                    <i :class="['ri-refresh-line text-sm', loading ? 'animate-spin' : '']"></i>
                    <span>Refresh</span>
                </button>

                <!-- Export CSV -->
                <button
                    @click="exportCsv"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer"
                >
                    <i class="ri-download-2-line text-sm text-gray-500"></i>
                    <span>Export CSV</span>
                </button>

                <!-- Prune Logs -->
                <button
                    @click="showPruneModal = true"
                    class="px-3 py-1.5 text-xs font-medium text-red-700 bg-white hover:bg-red-50 border border-red-200 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer"
                >
                    <i class="ri-delete-bin-line text-sm text-red-500"></i>
                    <span>Prune</span>
                </button>
            </div>
        </div>

        <!-- 2. COMPACT SUMMARY METRICS -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-medium text-gray-500">Total Events</span>
                <div class="text-xl font-bold text-gray-900 mt-1">{{ stats.total_logs.toLocaleString() }}</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-medium text-gray-500">Today</span>
                <div class="text-xl font-bold text-emerald-600 mt-1">{{ stats.today_logs.toLocaleString() }}</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-medium text-gray-500">Warnings / Alerts</span>
                <div class="text-xl font-bold text-amber-600 mt-1">{{ stats.alerts_count.toLocaleString() }}</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-medium text-gray-500">Unique Users</span>
                <div class="text-xl font-bold text-purple-600 mt-1">{{ stats.unique_users.toLocaleString() }}</div>
            </div>
        </div>

        <!-- 3. UNIFIED FILTER & SEARCH BAR -->
        <div class="bg-white border border-gray-200 rounded-xl p-3.5 shadow-xs flex flex-col md:flex-row items-stretch md:items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search logs by description, user, IP, action..."
                    class="w-full pl-9 pr-8 py-2 text-xs bg-gray-50 focus:bg-white border border-gray-200 focus:border-gray-400 rounded-lg outline-none transition-colors"
                />
                <button
                    v-if="filters.search"
                    @click="filters.search = ''"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                    <i class="ri-close-line text-sm"></i>
                </button>
            </div>

            <!-- Filters Group -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Module -->
                <select
                    v-model="filters.module"
                    class="text-xs py-2 px-2.5 bg-gray-50 focus:bg-white border border-gray-200 focus:border-gray-400 rounded-lg outline-none cursor-pointer"
                >
                    <option value="all">All Modules</option>
                    <option value="projects">Projects</option>
                    <option value="inquiries">Inquiries</option>
                    <option value="users">Users</option>
                    <option value="auth">Auth</option>
                    <option value="system">System</option>
                </select>

                <!-- Severity -->
                <select
                    v-model="filters.severity"
                    class="text-xs py-2 px-2.5 bg-gray-50 focus:bg-white border border-gray-200 focus:border-gray-400 rounded-lg outline-none cursor-pointer"
                >
                    <option value="all">All Severities</option>
                    <option value="info">Info</option>
                    <option value="success">Success</option>
                    <option value="warning">Warning</option>
                    <option value="danger">Critical</option>
                </select>

                <!-- Role -->
                <select
                    v-model="filters.role"
                    class="text-xs py-2 px-2.5 bg-gray-50 focus:bg-white border border-gray-200 focus:border-gray-400 rounded-lg outline-none cursor-pointer"
                >
                    <option value="all">All Roles</option>
                    <option value="superadmin">Superadmin</option>
                    <option value="admin">Admin</option>
                    <option value="staff">Staff</option>
                    <option value="citizen">Citizen</option>
                    <option value="system">System</option>
                </select>

                <!-- Timeframe -->
                <select
                    v-model="filters.date_range"
                    class="text-xs py-2 px-2.5 bg-gray-50 focus:bg-white border border-gray-200 focus:border-gray-400 rounded-lg outline-none cursor-pointer"
                >
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">Last 7 Days</option>
                    <option value="month">Last 30 Days</option>
                </select>

                <!-- Reset -->
                <button
                    v-if="filters.module !== 'all' || filters.severity !== 'all' || filters.role !== 'all' || filters.date_range !== 'all' || filters.search"
                    @click="filters.module = 'all'; filters.severity = 'all'; filters.role = 'all'; filters.date_range = 'all'; filters.search = ''"
                    class="px-2.5 py-2 text-xs text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                    title="Reset filters"
                >
                    Reset
                </button>
            </div>
        </div>

        <!-- 4. CLEAN DATA TABLE -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/75 border-b border-gray-200 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Timestamp</th>
                            <th class="py-3 px-4">Actor</th>
                            <th class="py-3 px-4">Module / Action</th>
                            <th class="py-3 px-4">Description</th>
                            <th class="py-3 px-4 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        <!-- Loading -->
                        <tr v-if="loading && !initialLoaded">
                            <td colspan="5" class="py-12 text-center text-gray-400">
                                <i class="ri-loader-4-line text-2xl animate-spin inline-block mb-1"></i>
                                <p>Loading activity logs...</p>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-else-if="logs.length === 0">
                            <td colspan="5" class="py-12 text-center text-gray-400">
                                <i class="ri-file-list-line text-2xl inline-block mb-1 text-gray-300"></i>
                                <p class="text-sm font-medium text-gray-600">No activity logs found</p>
                                <p class="text-xs text-gray-400 mt-0.5">Try adjusting your filters or search query.</p>
                            </td>
                        </tr>

                        <!-- Log Row -->
                        <tr
                            v-for="log in logs"
                            :key="log.id"
                            class="hover:bg-gray-50/60 transition-colors"
                        >
                            <!-- Timestamp -->
                            <td class="py-3.5 px-4 whitespace-nowrap align-top">
                                <div class="font-medium text-gray-900">{{ log.created_at }}</div>
                                <div class="text-[11px] text-gray-400">{{ log.created_at_relative }}</div>
                            </td>

                            <!-- Actor -->
                            <td class="py-3.5 px-4 whitespace-nowrap align-top">
                                <div class="font-medium text-gray-900">{{ log.user_name }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span :class="['px-1.5 py-0.5 text-[10px] font-medium rounded border uppercase', getRoleStyle(log.user_role)]">
                                        {{ log.user_role }}
                                    </span>
                                    <span class="text-[11px] text-gray-400 font-mono">{{ log.ip_address }}</span>
                                </div>
                            </td>

                            <!-- Module / Action -->
                            <td class="py-3.5 px-4 whitespace-nowrap align-top">
                                <div class="flex items-center gap-1.5">
                                    <span :class="['px-2 py-0.5 text-[10px] font-semibold rounded border uppercase', getSeverityStyle(log.severity)]">
                                        {{ log.severity }}
                                    </span>
                                    <span class="px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-600 rounded border border-gray-200 uppercase">
                                        {{ log.module }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-gray-500 font-mono mt-1 uppercase">{{ log.action }}</div>
                            </td>

                            <!-- Description -->
                            <td class="py-3.5 px-4 align-top max-w-md">
                                <p class="text-gray-900 leading-relaxed break-words font-normal">
                                    {{ log.description }}
                                </p>
                            </td>

                            <!-- Action -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-right align-top">
                                <button
                                    @click="openInspect(log)"
                                    class="text-xs font-medium text-gray-600 hover:text-gray-900 px-2.5 py-1 rounded hover:bg-gray-100 transition-colors cursor-pointer"
                                >
                                    Inspect
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 5. PAGINATION -->
            <div class="py-3 px-4 border-t border-gray-200 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500">
                <div>
                    Showing <span class="font-semibold text-gray-700">{{ pagination.from }}-{{ pagination.to }}</span> of <span class="font-semibold text-gray-700">{{ pagination.total }}</span> logs
                </div>

                <div class="flex items-center gap-1.5">
                    <button
                        @click="fetchLogs(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1 || loading"
                        class="px-2.5 py-1 border border-gray-200 rounded bg-white hover:bg-gray-50 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed text-gray-700 font-medium"
                    >
                        Previous
                    </button>
                    <span class="px-2 font-medium text-gray-700">
                        {{ pagination.current_page }} / {{ pagination.last_page || 1 }}
                    </span>
                    <button
                        @click="fetchLogs(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.last_page || loading"
                        class="px-2.5 py-1 border border-gray-200 rounded bg-white hover:bg-gray-50 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed text-gray-700 font-medium"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= INSPECT MODAL ================= -->
        <div
            v-if="showInspectModal && selectedLog"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-xl border border-gray-200 shadow-xl max-w-xl w-full overflow-hidden">
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Event Details #{{ selectedLog.id }}</h3>
                        <p class="text-xs text-gray-500">{{ selectedLog.created_at }}</p>
                    </div>
                    <button
                        @click="showInspectModal = false"
                        class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100"
                    >
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4 text-xs max-h-[70vh] overflow-y-auto">
                    <!-- Badges -->
                    <div class="flex items-center gap-2">
                        <span :class="['px-2 py-0.5 rounded border font-semibold uppercase', getSeverityStyle(selectedLog.severity)]">
                            {{ selectedLog.severity }}
                        </span>
                        <span class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50 text-gray-700 uppercase font-medium">
                            {{ selectedLog.module }}
                        </span>
                        <span class="px-2 py-0.5 rounded bg-gray-900 text-white uppercase font-mono">
                            {{ selectedLog.action }}
                        </span>
                    </div>

                    <!-- Narrative -->
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 text-gray-900 font-normal leading-relaxed">
                        {{ selectedLog.description }}
                    </div>

                    <!-- Actor & Context -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 space-y-0.5">
                            <span class="text-[10px] text-gray-400 font-semibold uppercase">Actor</span>
                            <div class="font-semibold text-gray-900">{{ selectedLog.user_name }}</div>
                            <div class="text-gray-500 text-[11px]">{{ selectedLog.user_email || 'No email' }}</div>
                        </div>

                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 space-y-0.5">
                            <span class="text-[10px] text-gray-400 font-semibold uppercase">Network & Device</span>
                            <div class="font-mono text-gray-900">IP: {{ selectedLog.ip_address }}</div>
                            <div class="text-gray-400 text-[11px] truncate" :title="selectedLog.user_agent">
                                {{ selectedLog.user_agent }}
                            </div>
                        </div>
                    </div>

                    <!-- JSON Metadata -->
                    <div class="space-y-1">
                        <label class="text-[10px] font-semibold uppercase text-gray-400">Metadata Payload</label>
                        <pre class="p-3 bg-gray-900 text-emerald-400 rounded-lg font-mono text-[11px] overflow-x-auto leading-relaxed">{{ selectedLog.properties ? JSON.stringify(selectedLog.properties, null, 2) : '{\n  "metadata": "None"\n}' }}</pre>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button
                        @click="showInspectModal = false"
                        class="px-4 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded-lg text-xs font-medium cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= PRUNE MODAL ================= -->
        <div
            v-if="showPruneModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-xl border border-gray-200 shadow-xl max-w-sm w-full overflow-hidden">
                <div class="p-5 space-y-3">
                    <div class="w-9 h-9 bg-red-50 text-red-600 rounded-full flex items-center justify-center">
                        <i class="ri-delete-bin-line text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900">Prune Activity Logs</h3>
                    <p class="text-xs text-gray-500">
                        Choose a retention cutoff to clean older activity logs from the database.
                    </p>

                    <select
                        v-model="pruneDays"
                        class="w-full py-2 px-3 text-xs bg-gray-50 border border-gray-200 rounded-lg outline-none cursor-pointer"
                    >
                        <option :value="90">Older than 90 days</option>
                        <option :value="60">Older than 60 days</option>
                        <option :value="30">Older than 30 days</option>
                        <option :value="7">Older than 7 days</option>
                        <option :value="0">Clear all logs</option>
                    </select>
                </div>

                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2 text-xs">
                    <button
                        @click="showPruneModal = false"
                        :disabled="pruning"
                        class="px-3 py-1.5 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-medium cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="handlePrune"
                        :disabled="pruning"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium cursor-pointer disabled:opacity-50"
                    >
                        {{ pruning ? 'Pruning...' : 'Prune' }}
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
