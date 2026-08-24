<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import axios from 'axios';
import StaffAssignedInfo from './StaffAssignedInfo.vue';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    projects: {
        type: Array,
        default: () => [],
    },
});

// ==================== STATE MANAGEMENT ====================
// Strictly filter only users with role === 'staff'
const rawStaff = computed(() => {
    return (props.users || []).filter(u => u.role === 'staff');
});

const searchQuery = ref('');
const filterType = ref('all'); // 'all', 'assigned', 'with_deadlines', 'with_notes', 'overdue'
const isLoading = ref(false);
const assignments = ref([]);

// Selected Staff for Full-Tab Detail View
const selectedStaffDetail = ref(null);

// Pagination & Sorting
const page = ref(1);
const pageSize = ref(10);
const sortBy = ref('name'); // 'name', 'projects', 'deadlines', 'notes'
const sortDir = ref('asc');

// Quick Modals in List View
const showAssignModal = ref(false);
const showDeadlineModal = ref(false);
const showNoteModal = ref(false);

const selectedStaff = ref(null);
const isSubmitting = ref(false);

// Toast Notification
const toast = reactive({
    show: false,
    type: 'success', // 'success' | 'error'
    message: '',
});
let toastTimeout = null;
const showToast = (message, type = 'success') => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.show = false;
    }, 4000);
};

// Form States
const assignForm = reactive({
    id: null,
    user_id: null,
    project_id: '',
    role_in_project: 'Project Inspector',
    target_deadline: '',
    priority: 'normal',
    title: '',
    note: '',
});

const deadlineForm = reactive({
    id: null,
    user_id: null,
    project_id: '',
    title: '',
    target_deadline: '',
    priority: 'high',
    note: '',
});

const noteForm = reactive({
    id: null,
    user_id: null,
    title: '',
    priority: 'normal',
    note: '',
});

// Role chips for quick filling
const roleSuggestions = [
    'Project Inspector',
    'Site Engineer',
    'Material Engineer',
    'Estimator',
    'Focal Person',
    'Lead Documenter',
    'Safety Officer',
];

// ==================== API FETCH ====================
const fetchAssignments = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get(route('staff-assignments.index'));
        assignments.value = res.data.assignments || [];
    } catch (err) {
        console.error('Error fetching staff assignments:', err);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchAssignments();
});

// ==================== COMPUTED METRICS ====================
const staffListWithData = computed(() => {
    const list = rawStaff.value.map(staff => {
        const staffAssignments = assignments.value.filter(a => Number(a.userId || a.user_id) === Number(staff.id));
        const projectAssignments = staffAssignments.filter(a => a.type === 'assignment' && (a.projectId || a.project_id));
        const deadlineItems = staffAssignments.filter(a => a.type === 'deadline' || (a.targetDeadline && a.type === 'assignment'));
        const activeDeadlines = deadlineItems.filter(d => d.status !== 'completed');
        const completedDeadlines = deadlineItems.filter(d => d.status === 'completed');
        
        // Check overdue deadlines
        const today = new Date().toISOString().split('T')[0];
        const overdueDeadlines = activeDeadlines.filter(d => d.targetDeadline && d.targetDeadline < today);
        const notes = staffAssignments.filter(a => a.type === 'note' || a.type === 'message');

        // Next upcoming deadline
        const sortedDeadlines = [...activeDeadlines].sort((a, b) => (a.targetDeadline || '').localeCompare(b.targetDeadline || ''));
        const nextDeadline = sortedDeadlines.length > 0 ? sortedDeadlines[0] : null;

        return {
            ...staff,
            projectAssignments,
            deadlineItems,
            activeDeadlines,
            completedDeadlines,
            overdueDeadlines,
            notes,
            nextDeadline,
            totalAssignedProjects: projectAssignments.length,
            totalDeadlines: deadlineItems.length,
            totalNotes: notes.length,
        };
    });

    let filtered = list;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        filtered = filtered.filter(s =>
            (s.name && s.name.toLowerCase().includes(q)) ||
            (s.email && s.email.toLowerCase().includes(q))
        );
    }

    if (filterType.value === 'assigned') {
        filtered = filtered.filter(s => s.totalAssignedProjects > 0);
    } else if (filterType.value === 'with_deadlines') {
        filtered = filtered.filter(s => s.activeDeadlines.length > 0);
    } else if (filterType.value === 'with_notes') {
        filtered = filtered.filter(s => s.totalNotes > 0);
    } else if (filterType.value === 'overdue') {
        filtered = filtered.filter(s => s.overdueDeadlines.length > 0);
    }

    // Sort
    filtered.sort((a, b) => {
        let valA = a.name;
        let valB = b.name;

        if (sortBy.value === 'projects') {
            valA = a.totalAssignedProjects;
            valB = b.totalAssignedProjects;
        } else if (sortBy.value === 'deadlines') {
            valA = a.activeDeadlines.length;
            valB = b.activeDeadlines.length;
        } else if (sortBy.value === 'notes') {
            valA = a.totalNotes;
            valB = b.totalNotes;
        }

        if (typeof valA === 'string') {
            return sortDir.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
        }
        return sortDir.value === 'asc' ? valA - valB : valB - valA;
    });

    return filtered;
});

// Overall summary counts for filter badges
const totalAssignedStaffCount = computed(() => {
    return rawStaff.value.filter(s => {
        const staffAssignments = assignments.value.filter(a => Number(a.userId || a.user_id) === Number(s.id));
        return staffAssignments.some(a => a.type === 'assignment' && (a.projectId || a.project_id));
    }).length;
});
const totalActiveDeadlinesCount = computed(() => {
    return assignments.value.filter(a => (a.type === 'deadline' || (a.targetDeadline && a.type === 'assignment')) && a.status !== 'completed').length;
});
const totalOverdueCount = computed(() => {
    const today = new Date().toISOString().split('T')[0];
    return assignments.value.filter(a => (a.type === 'deadline' || (a.targetDeadline && a.type === 'assignment')) && a.status !== 'completed' && a.targetDeadline && a.targetDeadline < today).length;
});
const totalDirectivesCount = computed(() => {
    return assignments.value.filter(a => a.type === 'note' || a.type === 'message').length;
});

// Pagination
const totalPages = computed(() => Math.max(1, Math.ceil(staffListWithData.value.length / pageSize.value)));
const paginatedStaff = computed(() => {
    const start = (page.value - 1) * pageSize.value;
    return staffListWithData.value.slice(start, start + pageSize.value);
});

// ==================== PROFILE PHOTO HELPERS ====================
const getProfilePhotoUrl = (user) => {
    if (!user) return null;
    if (user.profile_photo_url) return user.profile_photo_url;
    if (user.profile_photo_path) {
        return user.profile_photo_path.startsWith('http') 
            ? user.profile_photo_path 
            : `/storage/${user.profile_photo_path}`;
    }
    return null;
};

// ==================== MODAL & NAVIGATION TRIGGERS ====================
const openAssignModal = (staff = null) => {
    selectedStaff.value = staff;
    assignForm.id = null;
    assignForm.user_id = staff ? staff.id : (rawStaff.value[0]?.id || null);
    assignForm.project_id = '';
    assignForm.role_in_project = 'Project Inspector';
    assignForm.target_deadline = '';
    assignForm.priority = 'normal';
    assignForm.title = '';
    assignForm.note = '';
    showAssignModal.value = true;
};

const openDeadlineModal = (staff = null, projectId = null) => {
    selectedStaff.value = staff;
    deadlineForm.id = null;
    deadlineForm.user_id = staff ? staff.id : (rawStaff.value[0]?.id || null);
    deadlineForm.project_id = projectId || '';
    deadlineForm.title = '';
    deadlineForm.target_deadline = '';
    deadlineForm.priority = 'high';
    deadlineForm.note = '';
    showDeadlineModal.value = true;
};

const openNoteModal = (staff = null) => {
    selectedStaff.value = staff;
    noteForm.id = null;
    noteForm.user_id = staff ? staff.id : (rawStaff.value[0]?.id || null);
    noteForm.title = '';
    noteForm.priority = 'normal';
    noteForm.note = '';
    showNoteModal.value = true;
};

const viewStaffDetails = (staff) => {
    selectedStaffDetail.value = staff;
};

// ==================== ACTIONS ====================
const handleSaveAssignment = async () => {
    if (!assignForm.user_id || !assignForm.project_id) {
        showToast('Please select both a staff member and a project.', 'error');
        return;
    }

    const selectedProj = props.projects.find(p => Number(p.id) === Number(assignForm.project_id));
    const projectName = selectedProj?.project_name || selectedProj?.name || 'Infrastructure Project';
    const title = assignForm.title.trim() || `Assignment: ${projectName}`;

    isSubmitting.value = true;
    try {
        const payload = {
            user_id: assignForm.user_id,
            project_id: assignForm.project_id,
            type: 'assignment',
            title: title,
            role_in_project: assignForm.role_in_project,
            target_deadline: assignForm.target_deadline || null,
            priority: assignForm.priority,
            note: assignForm.note,
            status: 'in_progress',
        };

        const res = await axios.post(route('staff-assignments.store'), payload);
        assignments.value.unshift(res.data.assignment);
        showToast(`Successfully assigned project to staff member!`);
        showAssignModal.value = false;
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save assignment.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const handleSaveDeadline = async () => {
    if (!deadlineForm.user_id || !deadlineForm.title.trim() || !deadlineForm.target_deadline) {
        showToast('Please provide a title, staff member, and target deadline.', 'error');
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            user_id: deadlineForm.user_id,
            project_id: deadlineForm.project_id || null,
            type: 'deadline',
            title: deadlineForm.title.trim(),
            target_deadline: deadlineForm.target_deadline,
            priority: deadlineForm.priority,
            note: deadlineForm.note,
            status: 'pending',
        };

        const res = await axios.post(route('staff-assignments.store'), payload);
        assignments.value.unshift(res.data.assignment);
        showToast(`Target deadline set successfully!`);
        showDeadlineModal.value = false;
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save target deadline.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const handleSaveNote = async () => {
    if (!noteForm.user_id || !noteForm.title.trim() || !noteForm.note.trim()) {
        showToast('Please provide a note subject and message content.', 'error');
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            user_id: noteForm.user_id,
            type: 'note',
            title: noteForm.title.trim(),
            priority: noteForm.priority,
            note: noteForm.note.trim(),
            status: 'pending',
        };

        const res = await axios.post(route('staff-assignments.store'), payload);
        assignments.value.unshift(res.data.assignment);
        showToast(`Directive / note recorded for staff member!`);
        showNoteModal.value = false;
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save note.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

// ==================== HELPERS ====================
const getInitials = (name) => {
    if (!name) return 'ST';
    return name.split(' ').filter(Boolean).map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return dateStr;
    }
};

const getRelativeDeadlineText = (dateStr, status) => {
    if (!dateStr || status === 'completed') return '';
    try {
        const target = new Date(dateStr);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        target.setHours(0, 0, 0, 0);
        const diffTime = target - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 0) return `${Math.abs(diffDays)}d overdue`;
        if (diffDays === 0) return 'Due today';
        if (diffDays === 1) return 'Due tomorrow';
        return `in ${diffDays} days`;
    } catch {
        return '';
    }
};

const isOverdue = (dateStr, status) => {
    if (!dateStr || status === 'completed') return false;
    const today = new Date().toISOString().split('T')[0];
    return dateStr < today;
};

const handleSelectFilter = (filterKey) => {
    filterType.value = filterKey;
    page.value = 1;
};
</script>

<template>
    <div class="w-full space-y-4">
        
        <!-- ==================== VIEW 1: FULL STAFF DETAIL VIEW ==================== -->
        <StaffAssignedInfo
            v-if="selectedStaffDetail"
            :staff="selectedStaffDetail"
            :projects="props.projects"
            :assignments="assignments"
            @back="selectedStaffDetail = null"
            @refresh="fetchAssignments"
        />

        <!-- ==================== VIEW 2: MAIN STAFF DIRECTORY ==================== -->
        <div v-else class="space-y-4">
            
            <!-- Toast Notification -->
            <transition
                enter-active-class="transform ease-out duration-300 transition"
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div 
                    v-if="toast.show" 
                    class="fixed bottom-4 right-4 sm:bottom-5 sm:right-5 z-50 flex items-center gap-3 px-4 py-3 shadow-xl text-xs sm:text-sm font-medium border max-w-sm"
                    :class="toast.type === 'error' ? 'bg-rose-900 text-white border-rose-700' : 'bg-gray-900 text-white border-gray-700'"
                >
                    <svg v-if="toast.type === 'error'" class="w-5 h-5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="flex-1">{{ toast.message }}</span>
                    <button @click="toast.show = false" class="text-white/60 hover:text-white p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </transition>

            <!-- Top Header & Action Bar (Fully Responsive) -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between pb-3 border-b border-gray-200">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900 tracking-tight">Staff Management & Deployments</h2>
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                            {{ rawStaff.length }} Engineers & Staff
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Deploy engineering personnel, establish target deliverables, issue administrative directives, and review staff reports.
                    </p>
                </div>
                
                <!-- Action Button Row / Grid on Mobile -->
                <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2 pt-1 lg:pt-0">
                    <!-- Action 1: Assign to Project -->
                    <button 
                        @click="openAssignModal()" 
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 shadow-xs transition active:scale-[0.98]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Assign Project</span>
                    </button>

                    <!-- Action 2: Target Deadline -->
                    <button 
                        @click="openDeadlineModal()" 
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-semibold text-gray-800 bg-white border border-gray-300 hover:bg-gray-50 shadow-xs transition active:scale-[0.98]"
                    >
                        <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="truncate">Set Deadline</span>
                    </button>

                    <!-- Action 3: Add Note -->
                    <button 
                        @click="openNoteModal()" 
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-semibold text-gray-800 bg-white border border-gray-300 hover:bg-gray-50 shadow-xs transition active:scale-[0.98]"
                    >
                        <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        <span>Add Note</span>
                    </button>

                    <!-- Refresh Button -->
                    <button 
                        @click="fetchAssignments" 
                        :disabled="isLoading"
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 hover:text-gray-900 transition disabled:opacity-50"
                        title="Reload data"
                    >
                        <svg class="w-4 h-4 text-gray-500" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span class="sm:hidden">Refresh</span>
                    </button>
                </div>
            </div>

            <!-- ==================== FILTER & SEARCH TOOLBAR ==================== -->
            <div class="border border-gray-200 bg-white p-3 space-y-3">
                <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center justify-between">
                    <!-- Search input -->
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input 
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search staff by name or email..."
                            class="w-full pl-9 pr-8 py-2 sm:py-1.5 text-xs sm:text-sm bg-gray-50/75 border border-gray-200 focus:bg-white focus:outline-none focus:ring-1 focus:ring-red-600 focus:border-red-600 transition"
                        />
                        <button 
                            v-if="searchQuery" 
                            @click="searchQuery = ''"
                            class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 p-0.5"
                            title="Clear search"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Sort & Controls -->
                    <div class="flex items-center gap-2 justify-end">
                        <label class="text-xs text-gray-500 font-medium whitespace-nowrap">Sort:</label>
                        <select 
                            v-model="sortBy"
                            class="text-xs border border-gray-300 py-1.5 px-2 bg-white focus:outline-none focus:ring-1 focus:ring-red-600 focus:border-red-600"
                        >
                            <option value="name">Staff Name</option>
                            <option value="projects">Assigned Projects</option>
                            <option value="deadlines">Active Deadlines</option>
                            <option value="notes">Directives Count</option>
                        </select>

                        <button 
                            @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'"
                            class="px-2.5 py-1.5 text-xs font-semibold border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 transition whitespace-nowrap"
                            :title="sortDir === 'asc' ? 'Ascending Order' : 'Descending Order'"
                        >
                            <span v-if="sortDir === 'asc'">↑ ASC</span>
                            <span v-else>↓ DESC</span>
                        </button>
                    </div>
                </div>

                <!-- Filter Tabs Pills (Smooth Horizontal Touch Scroll on Mobile) -->
                <div class="flex items-center gap-1.5 pt-2 border-t border-gray-100 overflow-x-auto pb-1 sm:pb-0 scrollbar-thin">
                    <button
                        @click="handleSelectFilter('all')"
                        :class="filterType === 'all' ? 'bg-slate-900 text-white font-bold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1 text-xs transition shrink-0 whitespace-nowrap"
                    >
                        All Staff ({{ rawStaff.length }})
                    </button>
                    <button
                        @click="handleSelectFilter('assigned')"
                        :class="filterType === 'assigned' ? 'bg-blue-700 text-white font-bold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1 text-xs transition shrink-0 whitespace-nowrap"
                    >
                        With Projects ({{ totalAssignedStaffCount }})
                    </button>
                    <button
                        @click="handleSelectFilter('with_deadlines')"
                        :class="filterType === 'with_deadlines' ? 'bg-amber-700 text-white font-bold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1 text-xs transition shrink-0 whitespace-nowrap"
                    >
                        Active Deadlines ({{ totalActiveDeadlinesCount }})
                    </button>
                    <button
                        @click="handleSelectFilter('with_notes')"
                        :class="filterType === 'with_notes' ? 'bg-emerald-700 text-white font-bold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1 text-xs transition shrink-0 whitespace-nowrap"
                    >
                        Directives / Notes ({{ totalDirectivesCount }})
                    </button>
                    <button
                        v-if="totalOverdueCount > 0"
                        @click="handleSelectFilter('overdue')"
                        :class="filterType === 'overdue' ? 'bg-rose-700 text-white font-bold' : 'bg-rose-50 text-rose-700 hover:bg-rose-100 font-medium border border-rose-200'"
                        class="px-3 py-1 text-xs transition shrink-0 whitespace-nowrap"
                    >
                        ⚠ Overdue Tasks ({{ totalOverdueCount }})
                    </button>
                </div>
            </div>

            <!-- ==================== MAIN STAFF VIEW ==================== -->
            <div class="border border-gray-200 bg-white shadow-xs overflow-hidden">
                
                <!-- 1. DESKTOP & TABLET TABLE VIEW (Visible on md and larger) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                                <th class="px-5 py-3 min-w-[220px]">Staff Member</th>
                                <th class="px-5 py-3 min-w-[180px]">Assigned Projects</th>
                                <th class="px-5 py-3 min-w-[200px]">Target Deadlines & Tasks</th>
                                <th class="px-5 py-3 min-w-[180px]">Directives & Notes</th>
                                <th class="px-5 py-3 text-right min-w-[240px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr 
                                v-for="staff in paginatedStaff" 
                                :key="staff.id"
                                class="hover:bg-slate-50/75 transition group"
                            >
                                <!-- Staff Member Info (with Profile Photo) -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <!-- Profile Avatar -->
                                        <div 
                                            @click="viewStaffDetails(staff)"
                                            class="w-10 h-10 bg-slate-800 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-xs overflow-hidden border border-slate-300 relative group cursor-pointer hover:ring-2 hover:ring-red-600 transition"
                                            :title="`View profile details for ${staff.name}`"
                                        >
                                            <img 
                                                v-if="getProfilePhotoUrl(staff)" 
                                                :src="getProfilePhotoUrl(staff)" 
                                                :alt="staff.name" 
                                                class="w-full h-full object-cover"
                                                @error="$event.target.style.display = 'none'"
                                            />
                                            <span v-else>{{ getInitials(staff.name) }}</span>
                                        </div>

                                        <!-- Details -->
                                        <div class="min-w-0">
                                            <div 
                                                @click="viewStaffDetails(staff)"
                                                class="font-bold text-gray-900 text-sm truncate cursor-pointer hover:text-red-700 transition"
                                            >
                                                {{ staff.name }}
                                            </div>
                                            <div class="text-xs text-gray-500 truncate">{{ staff.email }}</div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="inline-block text-[10px] font-semibold text-slate-600 bg-slate-100 px-1.5 py-0.2 border border-slate-200">
                                                    Role: Staff
                                                </span>
                                                <span v-if="staff.created_at" class="text-[10px] text-gray-400">
                                                    Joined {{ formatDate(staff.created_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Assigned Projects -->
                                <td class="px-5 py-3.5">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <button 
                                                @click="viewStaffDetails(staff)"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold transition border"
                                                :class="staff.totalAssignedProjects > 0 ? 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100' : 'bg-gray-50 text-gray-500 border-gray-200'"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                                <span>{{ staff.totalAssignedProjects }} {{ staff.totalAssignedProjects === 1 ? 'Project' : 'Projects' }}</span>
                                            </button>
                                        </div>

                                        <div v-if="staff.projectAssignments.length > 0" class="text-xs text-gray-600 truncate max-w-xs">
                                            <span class="font-medium text-gray-900 block truncate">{{ staff.projectAssignments[0].projectName || staff.projectAssignments[0].title }}</span>
                                            <span class="text-gray-400 text-[11px] block">{{ staff.projectAssignments[0].roleInProject || 'Project Assigned' }}</span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400 italic">
                                            No project assigned
                                        </div>
                                    </div>
                                </td>

                                <!-- Target Deadlines -->
                                <td class="px-5 py-3.5">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <button 
                                                @click="viewStaffDetails(staff)"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold transition border"
                                                :class="staff.overdueDeadlines.length > 0 ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' : (staff.activeDeadlines.length > 0 ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-gray-50 text-gray-500 border-gray-200')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <span>{{ staff.activeDeadlines.length }} Active</span>
                                                <span v-if="staff.overdueDeadlines.length > 0" class="text-[10px] text-rose-700 font-bold ml-1">({{ staff.overdueDeadlines.length }} Overdue)</span>
                                            </button>
                                        </div>

                                        <div v-if="staff.nextDeadline" class="text-xs">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-gray-500">Next:</span>
                                                <span :class="isOverdue(staff.nextDeadline.targetDeadline, staff.nextDeadline.status) ? 'text-rose-600 font-bold' : 'text-amber-700 font-semibold'">
                                                    {{ formatDate(staff.nextDeadline.targetDeadline) }}
                                                </span>
                                                <span v-if="getRelativeDeadlineText(staff.nextDeadline.targetDeadline, staff.nextDeadline.status)" class="text-[10px] text-gray-400 font-medium">
                                                    ({{ getRelativeDeadlineText(staff.nextDeadline.targetDeadline, staff.nextDeadline.status) }})
                                                </span>
                                            </div>
                                            <span class="text-gray-700 block truncate max-w-xs text-[11px]">{{ staff.nextDeadline.title }}</span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400 italic">
                                            No pending deadlines
                                        </div>
                                    </div>
                                </td>

                                <!-- Directives & Notes -->
                                <td class="px-5 py-3.5">
                                    <div class="space-y-1">
                                        <button 
                                            @click="viewStaffDetails(staff)"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold transition border"
                                            :class="staff.totalNotes > 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-gray-50 text-gray-500 border-gray-200'"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                                            <span>{{ staff.totalNotes }} Directives</span>
                                        </button>
                                        <p v-if="staff.notes.length > 0" class="text-xs text-gray-600 truncate max-w-xs">
                                            {{ staff.notes[0].title }}
                                        </p>
                                        <p v-else class="text-xs text-gray-400 italic">
                                            No recorded notes
                                        </p>
                                    </div>
                                </td>

                                <!-- Action Buttons -->
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button 
                                            @click="openAssignModal(staff)"
                                            class="px-2.5 py-1 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition"
                                            title="Assign to Project"
                                        >
                                            Assign
                                        </button>
                                        <button 
                                            @click="openDeadlineModal(staff)"
                                            class="px-2.5 py-1 text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition"
                                            title="Set Target Deadline"
                                        >
                                            Deadline
                                        </button>
                                        <button 
                                            @click="openNoteModal(staff)"
                                            class="px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition"
                                            title="Add Directive Note"
                                        >
                                            Note
                                        </button>
                                        <button 
                                            @click="viewStaffDetails(staff)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-300 shadow-2xs transition"
                                            title="View Staff Details"
                                        >
                                            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <span>View</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. MOBILE CARD VIEW (Visible on small screens < md) -->
                <div class="block md:hidden divide-y divide-gray-200">
                    <div 
                        v-for="staff in paginatedStaff" 
                        :key="staff.id"
                        class="p-4 space-y-3 hover:bg-slate-50/50 transition"
                    >
                        <!-- Staff Header: Avatar + Info -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div 
                                    @click="viewStaffDetails(staff)"
                                    class="w-11 h-11 bg-slate-800 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-xs overflow-hidden border border-slate-300 cursor-pointer"
                                >
                                    <img 
                                        v-if="getProfilePhotoUrl(staff)" 
                                        :src="getProfilePhotoUrl(staff)" 
                                        :alt="staff.name" 
                                        class="w-full h-full object-cover"
                                        @error="$event.target.style.display = 'none'"
                                    />
                                    <span v-else>{{ getInitials(staff.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h3 
                                        @click="viewStaffDetails(staff)"
                                        class="font-bold text-gray-900 text-sm truncate cursor-pointer hover:text-red-700"
                                    >
                                        {{ staff.name }}
                                    </h3>
                                    <p class="text-xs text-gray-500 truncate">{{ staff.email }}</p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-1.5 py-0.2 border border-slate-200">
                                            Staff
                                        </span>
                                        <span v-if="staff.created_at" class="text-[10px] text-gray-400">
                                            Joined {{ formatDate(staff.created_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Main View Button -->
                            <button 
                                @click="viewStaffDetails(staff)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-300 shrink-0"
                                title="View Full Details"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>View</span>
                            </button>
                        </div>

                        <!-- Metrics Badges Row on Mobile -->
                        <div class="grid grid-cols-3 gap-1.5 pt-1">
                            <button 
                                @click="viewStaffDetails(staff)"
                                class="p-2 border text-left flex flex-col justify-between transition"
                                :class="staff.totalAssignedProjects > 0 ? 'bg-blue-50/50 text-blue-800 border-blue-200' : 'bg-gray-50 text-gray-500 border-gray-200'"
                            >
                                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-tight">Projects</span>
                                <span class="text-xs font-bold mt-0.5">{{ staff.totalAssignedProjects }}</span>
                            </button>

                            <button 
                                @click="viewStaffDetails(staff)"
                                class="p-2 border text-left flex flex-col justify-between transition"
                                :class="staff.overdueDeadlines.length > 0 ? 'bg-rose-50 text-rose-800 border-rose-200' : (staff.activeDeadlines.length > 0 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-gray-50 text-gray-500 border-gray-200')"
                            >
                                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-tight">Deadlines</span>
                                <span class="text-xs font-bold mt-0.5">
                                    {{ staff.activeDeadlines.length }}
                                    <span v-if="staff.overdueDeadlines.length > 0" class="text-[10px] text-rose-700 block">({{ staff.overdueDeadlines.length }} Overdue)</span>
                                </span>
                            </button>

                            <button 
                                @click="viewStaffDetails(staff)"
                                class="p-2 border text-left flex flex-col justify-between transition"
                                :class="staff.totalNotes > 0 ? 'bg-emerald-50/50 text-emerald-800 border-emerald-200' : 'bg-gray-50 text-gray-500 border-gray-200'"
                            >
                                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-tight">Notes</span>
                                <span class="text-xs font-bold mt-0.5">{{ staff.totalNotes }}</span>
                            </button>
                        </div>

                        <!-- Highlighted Detail snippet on Mobile -->
                        <div v-if="staff.projectAssignments.length > 0 || staff.nextDeadline" class="text-xs bg-gray-50 p-2 border border-gray-200 space-y-1">
                            <div v-if="staff.projectAssignments.length > 0" class="flex items-center gap-1.5 truncate">
                                <span class="text-gray-500 text-[11px] font-medium shrink-0">Current:</span>
                                <span class="font-bold text-gray-800 truncate">{{ staff.projectAssignments[0].projectName || staff.projectAssignments[0].title }}</span>
                            </div>
                            <div v-if="staff.nextDeadline" class="flex items-center gap-1.5 truncate">
                                <span class="text-gray-500 text-[11px] font-medium shrink-0">Next Due:</span>
                                <span :class="isOverdue(staff.nextDeadline.targetDeadline, staff.nextDeadline.status) ? 'text-rose-600 font-bold' : 'text-amber-700 font-semibold'">
                                    {{ formatDate(staff.nextDeadline.targetDeadline) }}
                                </span>
                                <span class="text-gray-600 truncate text-[11px]">({{ staff.nextDeadline.title }})</span>
                            </div>
                        </div>

                        <!-- Action Buttons on Mobile Card -->
                        <div class="grid grid-cols-3 gap-1.5 pt-1">
                            <button 
                                @click="openAssignModal(staff)"
                                class="py-1.5 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 text-center transition"
                            >
                                + Assign
                            </button>
                            <button 
                                @click="openDeadlineModal(staff)"
                                class="py-1.5 text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-center transition"
                            >
                                + Deadline
                            </button>
                            <button 
                                @click="openNoteModal(staff)"
                                class="py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 text-center transition"
                            >
                                + Note
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State Row / Container -->
                <div v-if="paginatedStaff.length === 0" class="py-12 sm:py-16 text-center px-4">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center bg-gray-100 text-gray-400 mb-2.5">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </div>
                    <h3 class="text-sm sm:text-base font-bold text-gray-900">No staff members found</h3>
                    <p class="text-xs text-gray-500 mt-1">No staff members match the selected filter or search query.</p>
                    <button 
                        v-if="filterType !== 'all' || searchQuery"
                        @click="filterType = 'all'; searchQuery = ''; page = 1"
                        class="mt-3 px-3.5 py-1.5 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 transition"
                    >
                        Reset Filters
                    </button>
                </div>

                <!-- Table Pagination Bar -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-5 py-3 border-t border-gray-200 bg-gray-50 text-xs text-gray-600">
                    <div>
                        Showing <strong>{{ staffListWithData.length ? (page - 1) * pageSize + 1 : 0 }}</strong> to <strong>{{ Math.min(page * pageSize, staffListWithData.length) }}</strong> of <strong>{{ staffListWithData.length }}</strong> staff members
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button 
                            @click="page = Math.max(1, page - 1)"
                            :disabled="page === 1"
                            class="px-3 py-1.5 border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition font-semibold"
                        >
                            Prev
                        </button>
                        <span class="px-2 font-bold">Page {{ page }} of {{ totalPages }}</span>
                        <button 
                            @click="page = Math.min(totalPages, page + 1)"
                            :disabled="page >= totalPages"
                            class="px-3 py-1.5 border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition font-semibold"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <!-- ==================== MODAL: ASSIGN TO PROJECT ==================== -->
            <Teleport to="body">
                <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4" @click.self="showAssignModal = false">
                    <div class="bg-white max-w-lg w-full max-h-[92vh] flex flex-col p-4 sm:p-6 shadow-2xl border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                        <div class="flex items-center justify-between pb-3 sm:pb-4 border-b border-gray-100 shrink-0">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 bg-red-50 text-red-700 flex items-center justify-center font-bold">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">Assign Staff to Project</h3>
                                    <p class="text-xs text-gray-500">Deploy staff member to an infrastructure project</p>
                                </div>
                            </div>
                            <button @click="showAssignModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="handleSaveAssignment" class="mt-4 space-y-4 overflow-y-auto flex-1 pr-1">
                            <!-- Select Staff -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Staff Member *</label>
                                <select 
                                    v-model="assignForm.user_id" 
                                    required
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                                >
                                    <option :value="null" disabled>Select Staff Member</option>
                                    <option v-for="user in rawStaff" :key="user.id" :value="user.id">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                            </div>

                            <!-- Select Project -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Project *</label>
                                <select 
                                    v-model="assignForm.project_id" 
                                    required
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                                >
                                    <option value="" disabled>Select Infrastructure Project</option>
                                    <option v-for="proj in props.projects" :key="proj.id" :value="proj.id">
                                        {{ proj.project_name || proj.name }} ({{ proj.location || 'Site Location' }} • {{ proj.year || proj.calendar_year || 'Current' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Role in Project with Suggestions -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Assigned Role / Designation</label>
                                <input 
                                    type="text"
                                    v-model="assignForm.role_in_project"
                                    placeholder="e.g. Project Inspector, Estimator"
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-red-600 focus:ring-1 focus:ring-red-600 mb-2"
                                />
                                <div class="flex flex-wrap gap-1.5">
                                    <button 
                                        v-for="role in roleSuggestions" 
                                        :key="role" 
                                        type="button"
                                        @click="assignForm.role_in_project = role"
                                        class="text-[11px] px-2 py-0.5 bg-gray-100 hover:bg-red-50 hover:text-red-700 text-gray-600 transition"
                                    >
                                        {{ role }}
                                    </button>
                                </div>
                            </div>

                            <!-- Target Deadline & Priority -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Target Completion Deadline</label>
                                    <input 
                                        type="date"
                                        v-model="assignForm.target_deadline"
                                        class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Priority Level</label>
                                    <select 
                                        v-model="assignForm.priority"
                                        class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                                    >
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Assignment Instructions / Notes -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Special Instructions or Notes (Optional)</label>
                                <textarea 
                                    v-model="assignForm.note"
                                    rows="3"
                                    placeholder="Specific instructions for this staff member regarding this project..."
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                                ></textarea>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button 
                                    type="button" 
                                    @click="showAssignModal = false"
                                    class="px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit"
                                    :disabled="isSubmitting"
                                    class="px-5 py-2 text-xs font-bold text-white bg-red-700 hover:bg-red-800 shadow-xs transition disabled:opacity-50"
                                >
                                    {{ isSubmitting ? 'Saving...' : 'Confirm Assignment' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <!-- ==================== MODAL: SET TARGET DEADLINE ==================== -->
            <Teleport to="body">
                <div v-if="showDeadlineModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4" @click.self="showDeadlineModal = false">
                    <div class="bg-white max-w-lg w-full max-h-[92vh] flex flex-col p-4 sm:p-6 shadow-2xl border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                        <div class="flex items-center justify-between pb-3 sm:pb-4 border-b border-gray-100 shrink-0">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 bg-amber-50 text-amber-700 flex items-center justify-center font-bold">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">Set Target Deadline</h3>
                                    <p class="text-xs text-gray-500">Create an actionable milestone or deadline for staff</p>
                                </div>
                            </div>
                            <button @click="showDeadlineModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="handleSaveDeadline" class="mt-4 space-y-4 overflow-y-auto flex-1 pr-1">
                            <!-- Select Staff -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Assignee (Staff) *</label>
                                <select 
                                    v-model="deadlineForm.user_id" 
                                    required
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                                >
                                    <option :value="null" disabled>Select Staff Member</option>
                                    <option v-for="user in rawStaff" :key="user.id" :value="user.id">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                            </div>

                            <!-- Task / Milestone Title -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Target Task / Milestone Title *</label>
                                <input 
                                type="text"
                                v-model="deadlineForm.title"
                                required
                                placeholder="e.g. Complete POW Preparation, Submit Site Inspection Report"
                                class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                            />
                            </div>

                            <!-- Linked Project (Optional) -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Linked Project (Optional)</label>
                                <select 
                                    v-model="deadlineForm.project_id"
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                                >
                                    <option value="">No Specific Project (General Engineering Task)</option>
                                    <option v-for="proj in props.projects" :key="proj.id" :value="proj.id">
                                        {{ proj.project_name || proj.name }} ({{ proj.location || 'Site Location' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Date & Priority -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Due Date *</label>
                                    <input 
                                        type="date"
                                        v-model="deadlineForm.target_deadline"
                                        required
                                        class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Priority</label>
                                    <select 
                                        v-model="deadlineForm.priority"
                                        class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                                    >
                                        <option value="urgent">Urgent</option>
                                        <option value="high">High</option>
                                        <option value="normal">Normal</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Notes / Description -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Task Details & Deliverables (Optional)</label>
                                <textarea 
                                    v-model="deadlineForm.note"
                                    rows="3"
                                    placeholder="Additional details, required documents, or instructions..."
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                                ></textarea>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button 
                                    type="button" 
                                    @click="showDeadlineModal = false"
                                    class="px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit"
                                    :disabled="isSubmitting"
                                    class="px-5 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-xs transition disabled:opacity-50"
                                >
                                    {{ isSubmitting ? 'Saving...' : 'Set Deadline' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <!-- ==================== MODAL: ADD DIRECTIVE NOTE / MESSAGE ==================== -->
            <Teleport to="body">
                <div v-if="showNoteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4" @click.self="showNoteModal = false">
                    <div class="bg-white max-w-lg w-full max-h-[92vh] flex flex-col p-4 sm:p-6 shadow-2xl border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                        <div class="flex items-center justify-between pb-3 sm:pb-4 border-b border-gray-100 shrink-0">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 bg-blue-50 text-blue-700 flex items-center justify-center font-bold">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">Add Directive / Message</h3>
                                    <p class="text-xs text-gray-500">Post instructions or an administrative directive to staff</p>
                                </div>
                            </div>
                            <button @click="showNoteModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="handleSaveNote" class="mt-4 space-y-4 overflow-y-auto flex-1 pr-1">
                            <!-- Select Staff -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Staff Member *</label>
                                <select 
                                    v-model="noteForm.user_id" 
                                    required
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                                >
                                    <option :value="null" disabled>Select Staff Member</option>
                                    <option v-for="user in rawStaff" :key="user.id" :value="user.id">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                            </div>

                            <!-- Subject -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Subject / Title *</label>
                                <input 
                                    type="text"
                                    v-model="noteForm.title"
                                    required
                                    placeholder="e.g. Priority Review: Drainage POW Documents"
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                                />
                            </div>

                            <!-- Priority Tag -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Directive Priority</label>
                                <select 
                                    v-model="noteForm.priority"
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                                >
                                    <option value="normal">Normal Information</option>
                                    <option value="high">High Priority Action</option>
                                    <option value="urgent">Urgent Directive</option>
                                    <option value="low">FYI / Reference</option>
                                </select>
                            </div>

                            <!-- Message Content -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Message Content *</label>
                                <textarea 
                                    v-model="noteForm.note"
                                    required
                                    rows="4"
                                    placeholder="Write direct instructions, remarks, or updates for this staff member..."
                                    class="w-full text-xs sm:text-sm border border-gray-300 p-2.5 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                                ></textarea>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button 
                                    type="button" 
                                    @click="showNoteModal = false"
                                    class="px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit"
                                    :disabled="isSubmitting"
                                    class="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-xs transition disabled:opacity-50"
                                >
                                    {{ isSubmitting ? 'Saving...' : 'Post Directive' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

        </div>

    </div>
</template>
