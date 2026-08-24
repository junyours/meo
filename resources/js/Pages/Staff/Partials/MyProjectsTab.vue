<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    initialProjects: {
        type: [Array, Object],
        default: () => []
    },
    sortOrder: {
        type: String,
        default: 'asc'
    }
});

const emit = defineEmits(['update:projects', 'project-selected']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

const rawProjects = computed(() => {
    if (!props.initialProjects) return [];
    return Array.isArray(props.initialProjects) ? props.initialProjects : Object.values(props.initialProjects);
});

// State
const assignedProjectIds = ref(new Set());
const assignments = ref([]);
const loadingAssignments = ref(false);
const showDirectivesModal = ref(false);

// Filter, Search & Sort
const searchQuery = ref('');
const statusFilter = ref('all'); // 'all' | 'ongoing' | 'completed' | 'delayed' | 'suspended' | 'not_started'
const fundFilter = ref('all');
const sortBy = ref('id_asc'); // 'id_asc' | 'id_desc' | 'name_asc' | 'progress_desc' | 'progress_asc' | 'budget_desc'

// Pagination
const currentPage = ref(1);
const perPage = ref(12);

// Fetch official staff assignments from backend
const loadAssignedProjects = async () => {
    if (!currentUser.value?.id) return;
    loadingAssignments.value = true;

    try {
        let endpoint = '/staff-assignments';
        try {
            if (typeof route === 'function' && route().has && route().has('staff-assignments.index')) {
                endpoint = route('staff-assignments.index');
            }
        } catch (e) {}

        const res = await axios.get(endpoint, {
            params: { user_id: currentUser.value.id }
        });

        const serverAssignments = res.data.assignments || [];
        assignments.value = serverAssignments;

        const newAssignedIds = new Set();
        serverAssignments.forEach(a => {
            if (a.projectId) {
                newAssignedIds.add(Number(a.projectId));
            }
        });

        // Auto-match projects where staff is explicitly named or linked in fields
        const userName = (currentUser.value?.name || '').toLowerCase();
        rawProjects.value.forEach(p => {
            if (p.userId && Number(p.userId) === Number(currentUser.value.id)) {
                newAssignedIds.add(Number(p.id));
            } else if (p.staffId && Number(p.staffId) === Number(currentUser.value.id)) {
                newAssignedIds.add(Number(p.id));
            } else if (p.handledBy && p.handledBy.toLowerCase() === userName) {
                newAssignedIds.add(Number(p.id));
            }
        });

        assignedProjectIds.value = newAssignedIds;
    } catch (err) {
        console.error('Error loading assignments for staff:', err);
    } finally {
        loadingAssignments.value = false;
    }
};

// Map of project ID to list of assignments
const projectAssignmentsMap = computed(() => {
    const map = {};
    assignments.value.forEach(item => {
        if (!item.projectId) return;
        const pid = Number(item.projectId);
        if (!map[pid]) map[pid] = [];
        map[pid].push(item);
    });
    return map;
});

// All assigned projects
const allAssignedProjects = computed(() => {
    return rawProjects.value.filter(p => assignedProjectIds.value.has(Number(p.id)));
});

// Unique fund categories for filter
const availableFundSources = computed(() => {
    const set = new Set();
    allAssignedProjects.value.forEach(p => {
        if (p.fundCategory) set.add(p.fundCategory);
        else if (p.sourceOfFund) set.add(p.sourceOfFund);
    });
    return Array.from(set).filter(Boolean);
});

// Filtered & Sorted Projects
const filteredProjects = computed(() => {
    let list = [...allAssignedProjects.value];

    // Search query filter
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(p =>
            (p.title && p.title.toLowerCase().includes(q)) ||
            (p.name && p.name.toLowerCase().includes(q)) ||
            (p.location && p.location.toLowerCase().includes(q)) ||
            (p.contractor && p.contractor.toLowerCase().includes(q)) ||
            (p.sourceOfFund && p.sourceOfFund.toLowerCase().includes(q))
        );
    }

    // Status filter
    if (statusFilter.value !== 'all') {
        list = list.filter(p => {
            const rawStatus = (p.status || '').toString().toLowerCase();
            if (statusFilter.value === 'ongoing') return rawStatus === 'ongoing' || p.status === 0;
            if (statusFilter.value === 'completed') return rawStatus === 'completed' || p.status === 1;
            if (statusFilter.value === 'delayed') return rawStatus.includes('delay') || p.status === 2;
            if (statusFilter.value === 'suspended') return rawStatus.includes('suspend') || p.status === 4;
            if (statusFilter.value === 'not_started') return rawStatus.includes('not') || p.status === 3;
            return true;
        });
    }

    // Fund filter
    if (fundFilter.value !== 'all') {
        list = list.filter(p => {
            const fc = (p.fundCategory || p.sourceOfFund || '').toLowerCase();
            return fc === fundFilter.value.toLowerCase();
        });
    }

    // Sorting
    list.sort((a, b) => {
        const idA = Number(a.id) || 0;
        const idB = Number(b.id) || 0;
        const progA = Number(a.progress ?? a.accomplishment) || 0;
        const progB = Number(b.progress ?? b.accomplishment) || 0;
        const costA = Number(a.budget ?? a.totalCost) || 0;
        const costB = Number(b.budget ?? b.totalCost) || 0;
        const nameA = (a.title || a.name || '').toLowerCase();
        const nameB = (b.title || b.name || '').toLowerCase();

        if (sortBy.value === 'id_asc') return idA - idB;
        if (sortBy.value === 'id_desc') return idB - idA;
        if (sortBy.value === 'name_asc') return nameA.localeCompare(nameB);
        if (sortBy.value === 'progress_desc') return progB - progA;
        if (sortBy.value === 'progress_asc') return progA - progB;
        if (sortBy.value === 'budget_desc') return costB - costA;
        return 0;
    });

    return list;
});

// Pagination computed
const totalPages = computed(() => Math.ceil(filteredProjects.value.length / perPage.value) || 1);
const paginatedProjects = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredProjects.value.slice(start, start + perPage.value);
});

// Reset page on filter changes
watch([searchQuery, statusFilter, fundFilter, perPage], () => {
    currentPage.value = 1;
});

// Metrics
const ongoingCount = computed(() => {
    return allAssignedProjects.value.filter(p => {
        const st = (p.status || '').toString().toLowerCase();
        return st === 'ongoing' || p.status === 0;
    }).length;
});

const completedCount = computed(() => {
    return allAssignedProjects.value.filter(p => {
        const st = (p.status || '').toString().toLowerCase();
        return st === 'completed' || p.status === 1;
    }).length;
});

const delayedCount = computed(() => {
    return allAssignedProjects.value.filter(p => {
        const st = (p.status || '').toString().toLowerCase();
        return st.includes('delay') || p.status === 2;
    }).length;
});

const activeAssignmentsList = computed(() => {
    return assignments.value.filter(a => a.status !== 'completed' && a.status !== 'cancelled');
});

// Helpers & Formatting
const formatCurrency = (val) => {
    const num = Number(val);
    if (!Number.isFinite(num)) return '—';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(num);
};

const getStatusBadge = (status) => {
    const s = (status || '').toString().toLowerCase();
    if (s === 'completed' || status === 1) return { label: 'Completed', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' };
    if (s.includes('delay') || status === 2) return { label: 'Delayed', bg: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' };
    if (s.includes('suspend') || status === 4) return { label: 'Suspended', bg: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' };
    if (s.includes('not') || status === 3) return { label: 'Not Started', bg: 'bg-slate-100 text-slate-700 border-slate-200', dot: 'bg-slate-400' };
    return { label: 'Ongoing', bg: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' };
};

const getProgressGradient = (progress) => {
    const p = Number(progress) || 0;
    if (p >= 100) return 'from-emerald-500 to-emerald-600';
    if (p >= 70) return 'from-blue-500 to-emerald-500';
    if (p >= 30) return 'from-blue-500 to-blue-600';
    return 'from-amber-500 to-blue-500';
};

// Navigation
const openProjectDetails = (project) => {
    localStorage.setItem('meo_staff_active_tab', 'projects');
    emit('project-selected', project);
    try {
        if (typeof route === 'function' && route().has && route().has('staff.projects.my-details')) {
            router.visit(route('staff.projects.my-details', project.id));
            return;
        }
    } catch (e) {}
    router.visit(`/staff/projects/${project.id}/my-details`);
};

// Toggle directive status
const updatingStatusId = ref(null);
const updateAssignmentStatus = async (item, newStatus) => {
    updatingStatusId.value = item.id;
    try {
        let endpoint = `/staff-assignments/${item.id}/status`;
        try {
            if (typeof route === 'function' && route().has && route().has('staff-assignments.status')) {
                endpoint = route('staff-assignments.status', item.id);
            }
        } catch (e) {}

        const res = await axios.patch(endpoint, { status: newStatus });
        if (res.data?.assignment) {
            const idx = assignments.value.findIndex(a => a.id === item.id);
            if (idx > -1) {
                assignments.value[idx] = res.data.assignment;
            }
        }
    } catch (e) {
        console.error('Failed to update assignment status:', e);
    } finally {
        updatingStatusId.value = null;
    }
};

// Directives Reply State
const replyingAssignment = ref(null);
const replyModalOpen = ref(false);
const replyForm = ref({
    staff_reply: '',
    status: 'in_progress'
});
const isSubmittingReply = ref(false);

const openReplyModal = (item) => {
    replyingAssignment.value = item;
    replyForm.value = {
        staff_reply: item.staffReply || '',
        status: item.status || 'in_progress',
    };
    replyModalOpen.value = true;
};

const closeReplyModal = () => {
    replyModalOpen.value = false;
    replyingAssignment.value = null;
    replyForm.value = {
        staff_reply: '',
        status: 'in_progress',
    };
};

const handleSaveReply = async () => {
    if (!replyingAssignment.value) return;
    isSubmittingReply.value = true;
    try {
        let endpoint = `/staff-assignments/${replyingAssignment.value.id}/reply`;
        try {
            if (typeof route === 'function' && route().has && route().has('staff-assignments.reply')) {
                endpoint = route('staff-assignments.reply', replyingAssignment.value.id);
            }
        } catch (e) {}

        const res = await axios.patch(endpoint, {
            staff_reply: replyForm.value.staff_reply,
            status: replyForm.value.status,
        });

        if (res.data?.assignment) {
            const idx = assignments.value.findIndex(a => a.id === replyingAssignment.value.id);
            if (idx > -1) {
                assignments.value[idx] = res.data.assignment;
            }
        }

        closeReplyModal();
    } catch (e) {
        console.error('Failed to submit reply:', e);
        alert(e.response?.data?.message || 'Failed to submit reply.');
    } finally {
        isSubmittingReply.value = false;
    }
};

onMounted(() => {
    loadAssignedProjects();
});

watch(() => props.initialProjects, () => {
    loadAssignedProjects();
}, { deep: true });
</script>

<template>
    <div class="w-full space-y-4">
        <!-- Summary Header Card -->
        <div class="bg-white border border-slate-200 shadow-sm p-4 sm:p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-base sm:text-xl font-bold text-slate-900">My Assigned Projects</h2>
                        <span class="inline-flex items-center px-2 py-0.5 text-[11px] sm:text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                            {{ allAssignedProjects.length }} Total Assigned
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-600">
                        Official infrastructure projects assigned to <strong class="text-slate-900 font-semibold">{{ currentUser?.name || 'Staff' }}</strong>.
                    </p>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <!-- View Directives Modal Button -->
                    <button
                        v-if="assignments.length > 0"
                        type="button"
                        @click="showDirectivesModal = true"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 text-xs font-bold transition shadow-xs active:scale-[0.99]"
                    >
                        <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>View Directives ({{ activeAssignmentsList.length }})</span>
                    </button>
                </div>
            </div>

            <!-- Metric Quick Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 pt-1 border-t border-slate-100">
                <div class="bg-slate-50 border border-slate-200 p-2.5 sm:p-3">
                    <p class="text-[10px] sm:text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Assigned</p>
                    <p class="text-base sm:text-lg font-bold text-slate-900 mt-0.5">{{ allAssignedProjects.length }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-2.5 sm:p-3">
                    <p class="text-[10px] sm:text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Ongoing</p>
                    <p class="text-base sm:text-lg font-bold text-blue-700 mt-0.5">{{ ongoingCount }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-2.5 sm:p-3">
                    <p class="text-[10px] sm:text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Completed</p>
                    <p class="text-base sm:text-lg font-bold text-emerald-700 mt-0.5">{{ completedCount }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-2.5 sm:p-3">
                    <p class="text-[10px] sm:text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Active Directives</p>
                    <p class="text-base sm:text-lg font-bold text-red-700 mt-0.5">{{ activeAssignmentsList.length }}</p>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="bg-white border border-slate-200 shadow-sm p-3.5 sm:p-4 space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <!-- Search Box -->
                <div class="relative flex-1">
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search by title, location, contractor..."
                        class="w-full pl-9 pr-8 py-2 text-xs sm:text-sm border border-slate-200 bg-slate-50/60 focus:bg-white focus:outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition"
                    />
                    <svg class="absolute left-3 top-2.5 text-slate-400 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 p-0.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Dropdown Filters (Responsive Grid on Mobile) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:flex lg:items-center gap-2">
                    <!-- Status Filter -->
                    <select
                        v-model="statusFilter"
                        class="w-full lg:w-auto text-xs font-semibold border border-slate-200 bg-slate-50 py-2 pl-3 pr-7 focus:ring-red-500 focus:border-red-500"
                    >
                        <option value="all">All Statuses</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="delayed">Delayed</option>
                        <option value="suspended">Suspended</option>
                        <option value="not_started">Not Started</option>
                    </select>

                    <!-- Fund Filter -->
                    <select
                        v-if="availableFundSources.length > 0"
                        v-model="fundFilter"
                        class="w-full lg:w-auto text-xs font-semibold border border-slate-200 bg-slate-50 py-2 pl-3 pr-7 focus:ring-red-500 focus:border-red-500"
                    >
                        <option value="all">All Funds</option>
                        <option v-for="fund in availableFundSources" :key="fund" :value="fund">{{ fund }}</option>
                    </select>

                    <!-- Sort -->
                    <select
                        v-model="sortBy"
                        class="w-full lg:w-auto text-xs font-semibold border border-slate-200 bg-slate-50 py-2 pl-3 pr-7 focus:ring-red-500 focus:border-red-500"
                    >
                        <option value="id_asc">Default (Oldest First)</option>
                        <option value="id_desc">Default (Newest First)</option>
                        <option value="name_asc">Project Name (A-Z)</option>
                        <option value="progress_desc">Accomplishment (High → Low)</option>
                        <option value="progress_asc">Accomplishment (Low → High)</option>
                        <option value="budget_desc">Budget (Highest)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Content Area: Empty State -->
        <div v-if="filteredProjects.length === 0" class="bg-white border border-dashed border-slate-300 p-8 sm:p-12 text-center space-y-3">
            <div class="w-12 h-12 bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zM13 21h8V11h-8v10zM13 3v6h8V3h-8z"/></svg>
            </div>
            <div>
                <h3 class="text-sm sm:text-base font-bold text-slate-900">No Assigned Projects Found</h3>
                <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto mt-1">
                    {{ searchQuery || statusFilter !== 'all' || fundFilter !== 'all' ? 'No projects match your current search and filter settings.' : 'You have no infrastructure projects assigned to you yet.' }}
                </p>
            </div>
            <button
                v-if="searchQuery || statusFilter !== 'all' || fundFilter !== 'all'"
                type="button"
                @click="searchQuery = ''; statusFilter = 'all'; fundFilter = 'all'"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
            >
                Reset Filters
            </button>
        </div>

        <!-- Content Area: List View -->
        <div v-else class="bg-white border border-slate-200 shadow-xs divide-y divide-slate-100">
            <div
                v-for="project in paginatedProjects"
                :key="project.id"
                @click="openProjectDetails(project)"
                class="p-3.5 sm:p-5 hover:bg-slate-50 transition-all flex flex-col md:flex-row md:items-center justify-between gap-3.5 sm:gap-4 cursor-pointer group"
            >
                <div class="flex items-start gap-3 flex-1 min-w-0">
                    <div class="flex-1 min-w-0 space-y-1.5">
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 group-hover:text-red-700 transition-colors break-words">
                                {{ project.title || project.name }}
                            </h3>
                            <span
                                :class="[
                                    getStatusBadge(project.status).bg,
                                    'inline-flex items-center gap-1 px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold border shrink-0'
                                ]"
                            >
                                <span class="w-1.5 h-1.5" :class="getStatusBadge(project.status).dot"></span>
                                {{ getStatusBadge(project.status).label }}
                            </span>
                            <span
                                v-if="projectAssignmentsMap[Number(project.id)]?.length"
                                class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0"
                            >
                                <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ projectAssignmentsMap[Number(project.id)][0].roleInProject || 'Assigned Staff' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-x-2.5 gap-y-1 text-xs text-slate-500 flex-wrap">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="break-words">{{ project.location || 'Location not set' }}</span>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="inline-flex items-center px-1.5 py-0.5 bg-slate-100 font-medium text-slate-700 text-[10px] sm:text-[11px] border border-slate-200">
                                {{ project.fundCategory || project.sourceOfFund || 'LGU' }}
                            </span>
                            <template v-if="project.contractor">
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-600 break-words">
                                    Contractor: <strong class="text-slate-800 font-semibold">{{ project.contractor }}</strong>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right Metrics & Actions (Mobile Optimized) -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between md:justify-end gap-3 sm:gap-5 shrink-0 border-t md:border-t-0 pt-2.5 md:pt-0 border-slate-100">
                    <div class="grid grid-cols-2 sm:flex sm:items-center gap-3 sm:gap-5">
                        <!-- Cost -->
                        <div class="text-left md:text-right">
                            <p class="text-[10px] uppercase font-semibold text-slate-400">Total Budget</p>
                            <p class="text-xs sm:text-sm font-bold text-slate-900">{{ formatCurrency(project.budget ?? project.totalCost) }}</p>
                        </div>

                        <!-- Accomplishment Progress -->
                        <div class="w-full sm:w-28 md:w-32 space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-[10px] text-slate-400 font-semibold uppercase">Progress</span>
                                <span class="text-xs font-bold text-slate-800">{{ Number(project.progress ?? project.accomplishment ?? 0).toFixed(1) }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-1.5 overflow-hidden">
                                <div
                                    class="h-full bg-gradient-to-r transition-all duration-500"
                                    :class="getProgressGradient(project.progress ?? project.accomplishment)"
                                    :style="{ width: `${Math.min(Number(project.progress ?? project.accomplishment ?? 0), 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <button
                        type="button"
                        @click.stop="openProjectDetails(project)"
                        class="w-full sm:w-auto justify-center inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 group-hover:bg-red-700 text-slate-700 group-hover:text-white text-xs font-semibold border border-slate-200 group-hover:border-red-700 transition shadow-2xs"
                    >
                        <span>View Details</span>
                        <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination Controls (Mobile Optimized) -->
        <div v-if="filteredProjects.length > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 text-xs text-slate-600">
            <div class="flex flex-wrap items-center justify-between sm:justify-start gap-2">
                <span>Showing <strong>{{ Math.min((currentPage - 1) * perPage + 1, filteredProjects.length) }}</strong> - <strong>{{ Math.min(currentPage * perPage, filteredProjects.length) }}</strong> of <strong>{{ filteredProjects.length }}</strong></span>
                <select
                    v-model="perPage"
                    class="text-xs font-semibold border border-slate-200 bg-white py-1 pl-2 pr-6 focus:ring-red-500 focus:border-red-500"
                >
                    <option :value="6">6 / page</option>
                    <option :value="12">12 / page</option>
                    <option :value="24">24 / page</option>
                    <option :value="48">48 / page</option>
                </select>
            </div>

            <!-- Page Nav Buttons -->
            <div v-if="totalPages > 1" class="flex items-center justify-center sm:justify-end gap-1 w-full sm:w-auto">
                <button
                    type="button"
                    :disabled="currentPage <= 1"
                    @click="currentPage--"
                    class="px-2.5 py-1.5 border border-slate-200 bg-white font-semibold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 transition"
                >
                    Previous
                </button>
                <span class="px-3 py-1 font-bold text-slate-800 text-xs">
                    {{ currentPage }} / {{ totalPages }}
                </span>
                <button
                    type="button"
                    :disabled="currentPage >= totalPages"
                    @click="currentPage++"
                    class="px-2.5 py-1.5 border border-slate-200 bg-white font-semibold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 transition"
                >
                    Next
                </button>
            </div>
        </div>

        <!-- Directives & Task Instructions Modal (Responsive) -->
        <div
            v-if="showDirectivesModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4"
            @click.self="showDirectivesModal = false"
        >
            <div class="bg-white max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Admin Directives & Assigned Tasks</h3>
                        <p class="text-[11px] sm:text-xs text-slate-500">Official assignments and roles designated to you.</p>
                    </div>
                    <button
                        type="button"
                        @click="showDirectivesModal = false"
                        class="text-slate-400 hover:text-slate-600 p-1.5 transition"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <div class="p-3.5 sm:p-6 overflow-y-auto flex-1 space-y-3 divide-y divide-slate-100">
                    <div
                        v-for="item in assignments"
                        :key="item.id"
                        class="pt-3 first:pt-0 space-y-2 bg-slate-50/70 p-3.5 sm:p-4 border border-slate-200"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2.5 sm:gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 break-words">{{ item.title }}</h4>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 font-bold uppercase"
                                        :class="[
                                            item.priority === 'urgent' ? 'bg-red-100 text-red-700 border border-red-200' :
                                             item.priority === 'high' ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                                            'bg-slate-100 text-slate-700'
                                        ]"
                                    >
                                        {{ item.priority || 'normal' }}
                                    </span>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 font-semibold"
                                        :class="[
                                            item.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                            item.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                            'bg-yellow-100 text-yellow-800'
                                        ]"
                                    >
                                        {{ item.status === 'in_progress' ? 'In Progress' : item.status }}
                                    </span>
                                </div>

                                <p v-if="item.projectName" class="text-xs font-semibold text-red-700 break-words">
                                    📁 {{ item.projectName }}
                                </p>
                            </div>

                            <!-- Status update dropdown -->
                            <div class="flex items-center gap-1.5 shrink-0 self-start sm:self-auto">
                                <span class="text-[11px] text-slate-500 sm:hidden">Status:</span>
                                <select
                                    :value="item.status"
                                    :disabled="updatingStatusId === item.id"
                                    @change="updateAssignmentStatus(item, $event.target.value)"
                                    class="text-xs font-semibold border border-slate-300 py-1 pl-2 pr-6 bg-white focus:ring-red-500 focus:border-red-500"
                                >
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <p v-if="item.note" class="text-xs text-slate-600 bg-white p-2.5 border border-slate-200 break-words">
                            <strong class="font-semibold text-slate-800">Admin Directive:</strong> {{ item.note }}
                        </p>

                        <!-- Staff Reply / Note Display -->
                        <div v-if="item.staffReply" class="bg-emerald-50 border border-emerald-200 p-2.5 rounded text-xs space-y-1">
                            <div class="flex items-center justify-between text-[10px] text-emerald-800 font-bold flex-wrap gap-1">
                                <span>💬 My Reply / Field Note (Visible to Admin)</span>
                                <span v-if="item.staffRepliedAt">{{ item.staffRepliedAt }}</span>
                            </div>
                            <p class="text-slate-800 whitespace-pre-line break-words">{{ item.staffReply }}</p>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-slate-500 pt-1 border-t border-slate-200/60">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span v-if="item.roleInProject">Role: <strong class="text-slate-700">{{ item.roleInProject }}</strong></span>
                                <span v-if="item.targetDeadline">Deadline: <strong class="text-slate-700">{{ item.targetDeadline }}</strong></span>
                            </div>

                            <button
                                type="button"
                                @click="openReplyModal(item)"
                                class="w-full sm:w-auto justify-center inline-flex items-center gap-1 px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-300 text-slate-700 font-bold text-xs transition shadow-2xs"
                            >
                                <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                <span>{{ item.staffReply ? 'Edit Reply' : 'Reply / Note' }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="assignments.length === 0" class="text-center py-8 text-xs text-slate-500">
                        No active directives found for your account.
                    </div>
                </div>

                <div class="px-4 sm:px-6 py-3 border-t border-slate-200 flex justify-end bg-slate-50">
                    <button
                        type="button"
                        @click="showDirectivesModal = false"
                        class="w-full sm:w-auto px-4 py-2 bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition text-center"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Directives Reply Modal (Responsive) -->
        <div
            v-if="replyModalOpen && replyingAssignment"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4"
            @click.self="closeReplyModal"
        >
            <div class="bg-white max-w-lg w-full shadow-2xl border border-slate-200 p-4 sm:p-6 space-y-3.5 sm:space-y-4 animate-in fade-in zoom-in-95 duration-150 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2 sm:gap-2.5">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Reply / Note to Admin</h3>
                            <p class="text-[10px] sm:text-[11px] text-slate-500">Provide updates or field comments for this directive</p>
                        </div>
                    </div>
                    <button @click="closeReplyModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="bg-slate-50 p-2.5 sm:p-3 border border-slate-200 space-y-1 text-xs">
                    <div class="flex items-center justify-between flex-wrap gap-1">
                        <span class="font-bold text-slate-800 break-words">{{ replyingAssignment.title }}</span>
                        <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 bg-slate-200 text-slate-700">
                            {{ replyingAssignment.priority || 'normal' }}
                        </span>
                    </div>
                    <p v-if="replyingAssignment.note" class="text-slate-600 text-[11px] italic break-words">
                        "{{ replyingAssignment.note }}"
                    </p>
                </div>

                <form @submit.prevent="handleSaveReply" class="space-y-3 sm:space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">Current Task Status</label>
                        <select
                            v-model="replyForm.status"
                            class="w-full text-xs font-semibold border border-slate-300 p-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed (Mark Task as Done)</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">Your Reply / Feedback / Field Note *</label>
                        <textarea
                            v-model="replyForm.staff_reply"
                            required
                            rows="4"
                            placeholder="Enter your field update, remarks, queries, or completion notes for the admin..."
                            class="w-full text-xs border border-slate-300 p-2.5 focus:ring-emerald-500 focus:border-emerald-500 leading-relaxed"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="closeReplyModal"
                            class="w-full sm:w-auto px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition text-center"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmittingReply"
                            class="w-full sm:w-auto px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition disabled:opacity-50 flex items-center justify-center gap-1.5"
                        >
                            <svg v-if="isSubmittingReply" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75"></path></svg>
                            <span>{{ isSubmittingReply ? 'Posting Reply...' : 'Post Reply to Admin' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
