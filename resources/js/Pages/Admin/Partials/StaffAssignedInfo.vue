<script setup>
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    staff: {
        type: Object,
        required: true,
    },
    projects: {
        type: Array,
        default: () => [],
    },
    assignments: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['back', 'refresh']);

let discussionPollTimer = null;

onMounted(() => {
    discussionPollTimer = setInterval(() => {
        if (typeof document !== 'undefined' && !document.hidden) {
            emit('refresh');
        }
    }, 4000);
});

onUnmounted(() => {
    if (discussionPollTimer) {
        clearInterval(discussionPollTimer);
        discussionPollTimer = null;
    }
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});

// Active Tab
const activeTab = ref('projects'); // 'projects', 'deadlines', 'notes'

// Quick Modals inside Detail View
const showAssignModal = ref(false);
const showDeadlineModal = ref(false);
const showNoteModal = ref(false);
const isSubmitting = ref(false);

// Active Conversations State
const openConversations = reactive({});
const conversationInputs = reactive({});
const isSendingMessage = reactive({});

// Toast Notification
const toast = reactive({
    show: false,
    type: 'success',
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
    project_id: '',
    role_in_project: 'Project Inspector',
    target_deadline: '',
    priority: 'normal',
    title: '',
    note: '',
});

const deadlineForm = reactive({
    project_id: '',
    title: '',
    target_deadline: '',
    priority: 'high',
    note: '',
});

const noteForm = reactive({
    title: '',
    priority: 'normal',
    note: '',
});

const roleSuggestions = [
    'Project Inspector',
    'Site Engineer',
    'Material Engineer',
    'Estimator',
    'Focal Person',
    'Lead Documenter',
    'Safety Officer',
];

// Filter data for this staff member
const staffAssignments = computed(() => {
    return (props.assignments || []).filter(a => Number(a.userId || a.user_id) === Number(props.staff.id));
});

const projectAssignments = computed(() => {
    return staffAssignments.value.filter(a => a.type === 'assignment' && (a.projectId || a.project_id));
});

const deadlineItems = computed(() => {
    return staffAssignments.value.filter(a => a.type === 'deadline' || (a.targetDeadline && a.type === 'assignment'));
});

const activeDeadlines = computed(() => {
    return deadlineItems.value.filter(d => d.status !== 'completed');
});

const overdueDeadlines = computed(() => {
    const today = new Date().toISOString().split('T')[0];
    return activeDeadlines.value.filter(d => d.targetDeadline && d.targetDeadline < today);
});

const staffNotes = computed(() => {
    return staffAssignments.value.filter(a => a.type === 'note' || a.type === 'message');
});

// Profile Photo & Initials Helpers
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

const isOverdue = (dateStr, status) => {
    if (!dateStr || status === 'completed') return false;
    const today = new Date().toISOString().split('T')[0];
    return dateStr < today;
};

const getPriorityBadge = (priority) => {
    switch (priority) {
        case 'urgent':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'high':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'low':
            return 'bg-slate-50 text-slate-600 border-slate-200';
        default:
            return 'bg-blue-50 text-blue-700 border-blue-200';
    }
};

// Modal open triggers
const openAssign = () => {
    assignForm.project_id = '';
    assignForm.role_in_project = 'Project Inspector';
    assignForm.target_deadline = '';
    assignForm.priority = 'normal';
    assignForm.title = '';
    assignForm.note = '';
    showAssignModal.value = true;
};

const openDeadline = () => {
    deadlineForm.project_id = '';
    deadlineForm.title = '';
    deadlineForm.target_deadline = '';
    deadlineForm.priority = 'high';
    deadlineForm.note = '';
    showDeadlineModal.value = true;
};

const openNote = () => {
    noteForm.title = '';
    noteForm.priority = 'normal';
    noteForm.note = '';
    showNoteModal.value = true;
};

// Conversation Triggers & Handlers
const toggleConversation = (item) => {
    openConversations[item.id] = !openConversations[item.id];
    if (openConversations[item.id] && conversationInputs[item.id] === undefined) {
        conversationInputs[item.id] = '';
    }
};

const getConversation = (item) => {
    if (Array.isArray(item.conversation) && item.conversation.length > 0) {
        return item.conversation;
    }
    const fallback = [];
    if (item.note) {
        fallback.push({
            id: 'note_' + item.id,
            sender_id: item.assignedBy || item.assigned_by,
            sender_name: item.assignerName || 'Admin / Assigner',
            sender_role: 'admin',
            message: item.note,
            created_at: item.createdAt ? formatDate(item.createdAt) : '',
        });
    }
    if (item.staffReply) {
        fallback.push({
            id: 'reply_' + item.id,
            sender_id: item.userId || item.user_id,
            sender_name: item.userName || props.staff.name || 'Staff',
            sender_role: 'staff',
            message: item.staffReply,
            created_at: item.staffRepliedAt || '',
        });
    }
    return fallback;
};

const sendConversationMessage = async (item) => {
    const text = (conversationInputs[item.id] || '').trim();
    if (!text) {
        showToast('Please type a message before sending.', 'error');
        return;
    }

    isSendingMessage[item.id] = true;
    try {
        let endpoint = `/staff-assignments/${item.id}/message`;
        if (typeof route === 'function' && route().has && route().has('staff-assignments.message')) {
            endpoint = route('staff-assignments.message', item.id);
        }
        await axios.post(endpoint, {
            message: text,
        });
        showToast('Message sent to discussion!');
        conversationInputs[item.id] = '';
        emit('refresh');
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to send message.';
        showToast(msg, 'error');
    } finally {
        isSendingMessage[item.id] = false;
    }
};

const handleKeyDown = (e, item) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendConversationMessage(item);
    }
};

const isMyMessage = (msg) => {
    if (!msg) return false;
    const myId = currentUser.value?.id;
    if (myId && msg.sender_id) {
        return Number(myId) === Number(msg.sender_id);
    }
    if (msg.sender_role) {
        const myRole = (currentUser.value?.role || 'admin').toLowerCase();
        if (msg.sender_role.toLowerCase() === myRole && myRole !== 'staff') {
            return true;
        }
    }
    return false;
};

// Form Submissions
const handleSaveAssignment = async () => {
    if (!assignForm.project_id) {
        showToast('Please select a project.', 'error');
        return;
    }

    const selectedProj = (props.projects || []).find(p => Number(p.id) === Number(assignForm.project_id));
    const projectName = selectedProj?.project_name || selectedProj?.name || 'Infrastructure Project';
    const title = assignForm.title.trim() || `Assignment: ${projectName}`;

    isSubmitting.value = true;
    try {
        const payload = {
            user_id: props.staff.id,
            project_id: assignForm.project_id,
            type: 'assignment',
            title: title,
            role_in_project: assignForm.role_in_project,
            target_deadline: assignForm.target_deadline || null,
            priority: assignForm.priority,
            note: assignForm.note,
            status: 'in_progress',
        };

        await axios.post(route('staff-assignments.store'), payload);
        showToast('Project assigned successfully!');
        showAssignModal.value = false;
        emit('refresh');
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save assignment.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const handleSaveDeadline = async () => {
    if (!deadlineForm.title.trim() || !deadlineForm.target_deadline) {
        showToast('Please provide a title and target deadline.', 'error');
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            user_id: props.staff.id,
            project_id: deadlineForm.project_id || null,
            type: 'deadline',
            title: deadlineForm.title.trim(),
            target_deadline: deadlineForm.target_deadline,
            priority: deadlineForm.priority,
            note: deadlineForm.note,
            status: 'pending',
        };

        await axios.post(route('staff-assignments.store'), payload);
        showToast('Target deadline set successfully!');
        showDeadlineModal.value = false;
        emit('refresh');
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to set target deadline.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const handleSaveNote = async () => {
    if (!noteForm.title.trim() || !noteForm.note.trim()) {
        showToast('Please provide a note subject and content.', 'error');
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            user_id: props.staff.id,
            type: 'note',
            title: noteForm.title.trim(),
            priority: noteForm.priority,
            note: noteForm.note.trim(),
            status: 'pending',
        };

        await axios.post(route('staff-assignments.store'), payload);
        showToast('Directive recorded successfully!');
        showNoteModal.value = false;
        emit('refresh');
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save note.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const toggleDeadlineStatus = async (item) => {
    const newStatus = item.status === 'completed' ? 'pending' : 'completed';
    try {
        await axios.patch(route('staff-assignments.status', item.id), { status: newStatus });
        showToast(newStatus === 'completed' ? 'Task marked as completed! ✓' : 'Task reopened.');
        emit('refresh');
    } catch (err) {
        showToast('Failed to update task status.', 'error');
    }
};

// Confirmation Modal State & Handlers
const confirmModal = reactive({
    show: false,
    item: null,
    type: 'assignment', // 'assignment' | 'deadline' | 'note'
    title: '',
    message: '',
    confirmButtonText: 'Confirm Unassign',
    confirmButtonClass: 'bg-rose-700 hover:bg-rose-800 text-white',
    isProcessing: false,
});

const promptUnassignProject = (item) => {
    confirmModal.item = item;
    confirmModal.type = 'assignment';
    confirmModal.title = 'Unassign Project Assignment';
    confirmModal.message = `Are you sure you want to unassign ${props.staff.name} from "${item.projectName || item.title}"? This will revoke project access and field responsibilities from this staff member.`;
    confirmModal.confirmButtonText = 'Yes, Unassign Project';
    confirmModal.confirmButtonClass = 'bg-rose-700 hover:bg-rose-800 text-white';
    confirmModal.isProcessing = false;
    confirmModal.show = true;
};

const promptDeleteDeadline = (item) => {
    confirmModal.item = item;
    confirmModal.type = 'deadline';
    confirmModal.title = 'Delete Milestone / Target Deadline';
    confirmModal.message = `Are you sure you want to delete the target deadline "${item.title}"?`;
    confirmModal.confirmButtonText = 'Yes, Delete Milestone';
    confirmModal.confirmButtonClass = 'bg-rose-700 hover:bg-rose-800 text-white';
    confirmModal.isProcessing = false;
    confirmModal.show = true;
};

const promptDeleteNote = (item) => {
    confirmModal.item = item;
    confirmModal.type = 'note';
    confirmModal.title = 'Delete Directive / Note';
    confirmModal.message = `Are you sure you want to delete the directive "${item.title}"?`;
    confirmModal.confirmButtonText = 'Yes, Delete Directive';
    confirmModal.confirmButtonClass = 'bg-rose-700 hover:bg-rose-800 text-white';
    confirmModal.isProcessing = false;
    confirmModal.show = true;
};

const closeConfirmModal = () => {
    if (confirmModal.isProcessing) return;
    confirmModal.show = false;
    confirmModal.item = null;
};

const executeConfirmAction = async () => {
    if (!confirmModal.item) return;
    confirmModal.isProcessing = true;
    try {
        await axios.delete(route('staff-assignments.destroy', confirmModal.item.id));
        if (confirmModal.type === 'assignment') {
            showToast('Project unassigned successfully.');
        } else if (confirmModal.type === 'deadline') {
            showToast('Target deadline removed successfully.');
        } else {
            showToast('Directive removed successfully.');
        }
        confirmModal.show = false;
        confirmModal.item = null;
        emit('refresh');
    } catch (err) {
        showToast('Failed to remove record. Please try again.', 'error');
    } finally {
        confirmModal.isProcessing = false;
    }
};
</script>

<template>
    <div class="w-full space-y-3">
        
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
                class="fixed bottom-4 right-4 z-50 flex items-center gap-2.5 px-3.5 py-2.5 shadow-lg text-xs font-medium border max-w-sm"
                :class="toast.type === 'error' ? 'bg-rose-900 text-white border-rose-700' : 'bg-gray-900 text-white border-gray-700'"
            >
                <svg v-if="toast.type === 'error'" class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="flex-1">{{ toast.message }}</span>
                <button @click="toast.show = false" class="text-white/60 hover:text-white p-0.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </transition>

        <!-- Top Navigation Bar: Compact Header -->
        <div class="flex items-center justify-between pb-2 border-b border-gray-200">
            <button 
                @click="emit('back')"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 shadow-2xs transition"
            >
                <svg class="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Back to Staff Directory</span>
            </button>

            <button 
                @click="emit('refresh')"
                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 transition"
                title="Refresh assignments"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                <span>Refresh</span>
            </button>
        </div>

        <!-- Staff Profile Compact Header Card -->
        <div class="bg-white border border-gray-200 shadow-2xs overflow-hidden">
            <div class="p-3 sm:p-4 bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                
                <!-- Avatar & Details -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 sm:w-11 sm:h-11 bg-slate-700 text-white font-bold flex items-center justify-center text-xs sm:text-sm shadow-xs overflow-hidden border border-slate-500 shrink-0">
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
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-sm sm:text-base font-bold text-white tracking-tight truncate">{{ staff.name }}</h2>
                            <span class="text-[10px] px-1.5 py-0.2 font-semibold bg-red-600 text-white">
                                Staff
                            </span>
                        </div>
                        <p class="text-xs text-slate-300 truncate">{{ staff.email }}</p>
                        <p v-if="staff.created_at" class="text-[10px] text-slate-400">
                            Joined {{ formatDate(staff.created_at) }}
                        </p>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button 
                        @click="openAssign"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 border border-red-600 transition"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                        <span>Assign Project</span>
                    </button>
                    <button 
                        @click="openDeadline"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-900 bg-amber-400 hover:bg-amber-300 transition"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Set Deadline</span>
                    </button>
                    <button 
                        @click="openNote"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-slate-700 hover:bg-slate-600 border border-slate-600 transition"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                        <span>Add Note</span>
                    </button>
                </div>
            </div>

            <!-- Compact Metrics Summary Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 border-t border-gray-200 bg-slate-50 text-center py-2 divide-x divide-gray-200 text-xs">
                <div class="px-2">
                    <span class="font-bold text-blue-700">{{ projectAssignments.length }}</span>
                    <span class="text-gray-500 ml-1">Assigned Projects</span>
                </div>
                <div class="px-2">
                    <span class="font-bold text-amber-700">{{ activeDeadlines.length }}</span>
                    <span class="text-gray-500 ml-1">Active Tasks</span>
                </div>
                <div class="px-2">
                    <span class="font-bold" :class="overdueDeadlines.length > 0 ? 'text-rose-700' : 'text-gray-400'">
                        {{ overdueDeadlines.length }}
                    </span>
                    <span class="text-gray-500 ml-1">Overdue</span>
                </div>
                <div class="px-2">
                    <span class="font-bold text-emerald-700">{{ staffNotes.length }}</span>
                    <span class="text-gray-500 ml-1">Directives</span>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs Bar -->
        <div class="border-b border-gray-200 bg-white flex gap-4 px-3 sm:px-4 overflow-x-auto text-xs">
            <button
                @click="activeTab = 'projects'"
                :class="activeTab === 'projects' ? 'border-red-600 text-red-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 font-medium'"
                class="py-2.5 border-b-2 transition flex items-center gap-1.5 shrink-0 whitespace-nowrap"
            >
                <span>Assigned Projects</span>
                <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="activeTab === 'projects' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'">
                    {{ projectAssignments.length }}
                </span>
            </button>
            <button
                @click="activeTab = 'deadlines'"
                :class="activeTab === 'deadlines' ? 'border-red-600 text-red-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 font-medium'"
                class="py-2.5 border-b-2 transition flex items-center gap-1.5 shrink-0 whitespace-nowrap"
            >
                <span>Target Deadlines & Tasks</span>
                <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="activeTab === 'deadlines' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'">
                    {{ deadlineItems.length }}
                </span>
            </button>
            <button
                @click="activeTab = 'notes'"
                :class="activeTab === 'notes' ? 'border-red-600 text-red-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 font-medium'"
                class="py-2.5 border-b-2 transition flex items-center gap-1.5 shrink-0 whitespace-nowrap"
            >
                <span>Directives & Notes</span>
                <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="activeTab === 'notes' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'">
                    {{ staffNotes.length }}
                </span>
            </button>
        </div>

        <!-- ==================== TAB CONTENT ==================== -->
        <div class="space-y-2.5">
            
            <!-- TAB 1: LIST OF ASSIGNED PROJECTS -->
            <div v-if="activeTab === 'projects'" class="space-y-2.5">
                <div 
                    v-for="item in projectAssignments" 
                    :key="item.id"
                    class="p-3 sm:p-3.5 border border-gray-200 bg-white hover:border-gray-300 transition text-xs space-y-2"
                >
                    <!-- Project Header Line -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-gray-900 text-sm truncate">{{ item.projectName || item.title }}</h3>
                            <span class="px-2 py-0.2 text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                {{ item.roleInProject || 'Project Assigned' }}
                            </span>
                            <span class="text-gray-400 text-[11px] flex items-center gap-1">
                                <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                                <span>{{ item.projectLocation || 'Site Location' }}</span>
                            </span>
                        </div>

                        <!-- Action Buttons: Conversation & Unassign -->
                        <div class="flex items-center gap-1.5 self-end sm:self-auto">
                            <button 
                                @click="toggleConversation(item)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold transition border"
                                :class="openConversations[item.id] ? 'bg-slate-900 text-white border-slate-900' : 'bg-gray-50 text-gray-700 hover:bg-gray-100 border-gray-300'"
                                title="Open Conversation Thread"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                <span>Discussion</span>
                                <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="openConversations[item.id] ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-800'">
                                    {{ getConversation(item).length }}
                                </span>
                            </button>
                            <button 
                                @click="promptUnassignProject(item)"
                                class="px-2 py-1 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition"
                                title="Unassign Project"
                            >
                                Unassign
                            </button>
                        </div>
                    </div>

                    <!-- Instructions / Description -->
                    <div v-if="item.note" class="text-xs text-gray-700 bg-gray-50 p-2 border-l-2 border-slate-300">
                        <strong class="font-semibold text-gray-900">Initial Directive:</strong> {{ item.note }}
                    </div>

                    <!-- Conversation Thread Panel -->
                    <div v-if="openConversations[item.id]" class="mt-2 border border-slate-200 bg-slate-50/80 p-3 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-emerald-500 animate-pulse"></span>
                                <span class="font-bold text-slate-900 text-xs">Project Discussion & Field Updates</span>
                                <span class="text-[10px] text-slate-500">({{ getConversation(item).length }} message{{ getConversation(item).length === 1 ? '' : 's' }})</span>
                            </div>
                            <button @click="openConversations[item.id] = false" class="text-slate-400 hover:text-slate-600 text-xs font-semibold">
                                Collapse ✕
                            </button>
                        </div>

                        <!-- Thread Messages -->
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            <div v-if="getConversation(item).length === 0" class="text-center py-4 text-slate-400 text-xs italic">
                                No messages in this discussion yet. Start the conversation below.
                            </div>
                            <div 
                                v-for="(msg, mIdx) in getConversation(item)" 
                                :key="msg.id || mIdx"
                                class="flex items-start gap-2.5"
                                :class="isMyMessage(msg) ? 'flex-row-reverse' : 'flex-row'"
                            >
                                <div 
                                    class="w-7 h-7 shrink-0 text-[10px] font-bold flex items-center justify-center text-white overflow-hidden shadow-2xs"
                                    :class="isMyMessage(msg) ? 'bg-red-700' : (msg.sender_role === 'staff' ? 'bg-emerald-700' : 'bg-slate-700')"
                                >
                                    <img v-if="msg.sender_photo" :src="msg.sender_photo" class="w-full h-full object-cover" />
                                    <span v-else>{{ getInitials(msg.sender_name) }}</span>
                                </div>

                                <div 
                                    class="max-w-[85%] sm:max-w-[75%] p-2.5 text-xs shadow-2xs border"
                                    :class="isMyMessage(msg) ? 'bg-red-700 border-red-800 text-white' : 'bg-white border-slate-200 text-slate-900'"
                                >
                                    <div class="flex items-center gap-2 mb-1 text-[10px]" :class="isMyMessage(msg) ? 'justify-end text-red-100' : 'justify-start text-slate-500'">
                                        <strong class="font-bold" :class="isMyMessage(msg) ? 'text-white' : 'text-slate-900'">
                                            {{ isMyMessage(msg) ? 'You' : msg.sender_name }}
                                        </strong>
                                        <span class="px-1 py-0.2 text-[9px] font-bold uppercase tracking-wider" :class="isMyMessage(msg) ? 'bg-red-900/60 text-red-100' : (msg.sender_role === 'staff' ? 'bg-emerald-200 text-emerald-900' : 'bg-slate-200 text-slate-800')">
                                            {{ msg.sender_role || 'user' }}
                                        </span>
                                        <span v-if="msg.created_at" class="font-normal" :class="isMyMessage(msg) ? 'text-red-200' : 'text-slate-400'">{{ msg.created_at }}</span>
                                    </div>
                                    <p class="whitespace-pre-line leading-relaxed break-words" :class="isMyMessage(msg) ? 'text-white' : 'text-slate-800'">{{ msg.message }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Message Input -->
                        <div class="pt-2 border-t border-slate-200 space-y-2">
                            <textarea 
                                v-model="conversationInputs[item.id]"
                                @keydown="handleKeyDown($event, item)"
                                rows="2"
                                :placeholder="`Type a message, progress inquiry, or remarks to ${staff.name}... (Press Enter to send)`"
                                class="w-full text-xs border border-slate-300 p-2.5 focus:border-red-600 focus:ring-1 focus:ring-red-600 bg-white placeholder:text-slate-400"
                            ></textarea>
                            <div class="flex items-center justify-between gap-2 flex-wrap text-[11px]">
                                <span class="text-slate-500 text-[10px]">💡 <kbd class="px-1 py-0.5 bg-slate-200 text-slate-700">Enter</kbd> to send • <kbd class="px-1 py-0.5 bg-slate-200 text-slate-700">Shift+Enter</kbd> for new line</span>
                                <button 
                                    type="button" 
                                    @click="sendConversationMessage(item)"
                                    :disabled="isSendingMessage[item.id] || !(conversationInputs[item.id] && conversationInputs[item.id].trim())"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-red-700 hover:bg-red-800 transition disabled:opacity-50 shadow-2xs"
                                >
                                    <svg v-if="isSendingMessage[item.id]" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                    <span>{{ isSendingMessage[item.id] ? 'Sending...' : 'Send Message' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Details -->
                    <div class="flex items-center gap-3 text-[11px] text-gray-400 pt-0.5 flex-wrap">
                        <span v-if="item.targetDeadline">Target Date: <strong class="text-gray-700">{{ formatDate(item.targetDeadline) }}</strong></span>
                        <span>Assigned by: {{ item.assignerName || 'Admin' }}</span>
                        <span v-if="item.createdAt">Assigned on: {{ formatDate(item.createdAt) }}</span>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="projectAssignments.length === 0" class="text-center py-10 px-4 bg-white border border-dashed border-gray-200">
                    <p class="text-xs text-gray-500 font-medium">No infrastructure projects currently assigned to {{ staff.name }}.</p>
                    <button 
                        @click="openAssign"
                        class="mt-2 px-3 py-1.5 text-xs font-bold text-white bg-red-700 hover:bg-red-800 transition"
                    >
                        + Assign to a Project
                    </button>
                </div>
            </div>

            <!-- TAB 2: TARGET DEADLINES & TASKS -->
            <div v-if="activeTab === 'deadlines'" class="space-y-2.5">
                <div 
                    v-for="item in deadlineItems" 
                    :key="item.id"
                    class="p-3 sm:p-3.5 border transition text-xs space-y-2"
                    :class="item.status === 'completed' ? 'bg-gray-50/90 border-gray-200 opacity-85' : (isOverdue(item.targetDeadline, item.status) ? 'bg-rose-50/40 border-rose-200' : 'bg-white border-gray-200')"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0 flex-1">
                            <input 
                                type="checkbox"
                                :checked="item.status === 'completed'"
                                @change="toggleDeadlineStatus(item)"
                                class="mt-0.5 h-4 w-4 text-red-700 focus:ring-red-600 cursor-pointer shrink-0"
                                title="Toggle completion"
                            />
                            <div class="space-y-1 min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h4 
                                        class="font-bold text-sm"
                                        :class="item.status === 'completed' ? 'line-through text-gray-500' : 'text-gray-900'"
                                    >
                                        {{ item.title }}
                                    </h4>
                                    <span class="px-1.5 py-0.2 text-[10px] font-semibold border" :class="getPriorityBadge(item.priority)">
                                        {{ item.priority }}
                                    </span>
                                    <span v-if="item.projectName" class="px-1.5 py-0.2 text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ item.projectName }}
                                    </span>
                                </div>
                                <p v-if="item.note" class="text-xs text-gray-600">{{ item.note }}</p>
                            </div>
                        </div>

                        <!-- Actions: Conversation & Delete -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button 
                                @click="toggleConversation(item)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold transition border"
                                :class="openConversations[item.id] ? 'bg-slate-900 text-white border-slate-900' : 'bg-gray-50 text-gray-700 hover:bg-gray-100 border-gray-300'"
                                title="Open Conversation Thread"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                <span>Discussion</span>
                                <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="openConversations[item.id] ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-800'">
                                    {{ getConversation(item).length }}
                                </span>
                            </button>
                            <button 
                                @click="promptDeleteDeadline(item)"
                                class="text-gray-400 hover:text-rose-600 p-1 transition"
                                title="Delete Milestone / Target Deadline"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Conversation Thread Panel -->
                    <div v-if="openConversations[item.id]" class="mt-2 border border-slate-200 bg-slate-50/80 p-3 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-amber-500 animate-pulse"></span>
                                <span class="font-bold text-slate-900 text-xs">Milestone & Deadline Discussion</span>
                                <span class="text-[10px] text-slate-500">({{ getConversation(item).length }} message{{ getConversation(item).length === 1 ? '' : 's' }})</span>
                            </div>
                            <button @click="openConversations[item.id] = false" class="text-slate-400 hover:text-slate-600 text-xs font-semibold">
                                Collapse ✕
                            </button>
                        </div>

                        <!-- Thread Messages -->
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            <div v-if="getConversation(item).length === 0" class="text-center py-4 text-slate-400 text-xs italic">
                                No messages in this discussion yet. Start the conversation below.
                            </div>
                            <div 
                                v-for="(msg, mIdx) in getConversation(item)" 
                                :key="msg.id || mIdx"
                                class="flex items-start gap-2.5"
                                :class="isMyMessage(msg) ? 'flex-row-reverse' : 'flex-row'"
                            >
                                <div 
                                    class="w-7 h-7 shrink-0 text-[10px] font-bold flex items-center justify-center text-white overflow-hidden shadow-2xs"
                                    :class="isMyMessage(msg) ? 'bg-amber-600' : (msg.sender_role === 'staff' ? 'bg-emerald-700' : 'bg-slate-700')"
                                >
                                    <img v-if="msg.sender_photo" :src="msg.sender_photo" class="w-full h-full object-cover" />
                                    <span v-else>{{ getInitials(msg.sender_name) }}</span>
                                </div>

                                <div 
                                    class="max-w-[85%] sm:max-w-[75%] p-2.5 text-xs shadow-2xs border"
                                    :class="isMyMessage(msg) ? 'bg-amber-600 border-amber-700 text-white' : 'bg-white border-slate-200 text-slate-900'"
                                >
                                    <div class="flex items-center gap-2 mb-1 text-[10px]" :class="isMyMessage(msg) ? 'justify-end text-amber-100' : 'justify-start text-slate-500'">
                                        <strong class="font-bold" :class="isMyMessage(msg) ? 'text-white' : 'text-slate-900'">
                                            {{ isMyMessage(msg) ? 'You' : msg.sender_name }}
                                        </strong>
                                        <span class="px-1 py-0.2 text-[9px] font-bold uppercase tracking-wider" :class="isMyMessage(msg) ? 'bg-amber-800/60 text-amber-100' : (msg.sender_role === 'staff' ? 'bg-emerald-200 text-emerald-900' : 'bg-slate-200 text-slate-800')">
                                            {{ msg.sender_role || 'user' }}
                                        </span>
                                        <span v-if="msg.created_at" class="font-normal" :class="isMyMessage(msg) ? 'text-amber-200' : 'text-slate-400'">{{ msg.created_at }}</span>
                                    </div>
                                    <p class="whitespace-pre-line leading-relaxed break-words" :class="isMyMessage(msg) ? 'text-white' : 'text-slate-800'">{{ msg.message }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Message Input -->
                        <div class="pt-2 border-t border-slate-200 space-y-2">
                            <textarea 
                                v-model="conversationInputs[item.id]"
                                @keydown="handleKeyDown($event, item)"
                                rows="2"
                                :placeholder="`Type milestone status, deadline update, or instructions to ${staff.name}... (Press Enter to send)`"
                                class="w-full text-xs border border-slate-300 p-2.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600 bg-white placeholder:text-slate-400"
                            ></textarea>
                            <div class="flex items-center justify-between gap-2 flex-wrap text-[11px]">
                                <span class="text-slate-500 text-[10px]">💡 <kbd class="px-1 py-0.5 bg-slate-200 text-slate-700">Enter</kbd> to send • <kbd class="px-1 py-0.5 bg-slate-200 text-slate-700">Shift+Enter</kbd> for new line</span>
                                <button 
                                    type="button" 
                                    @click="sendConversationMessage(item)"
                                    :disabled="isSendingMessage[item.id] || !(conversationInputs[item.id] && conversationInputs[item.id].trim())"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 transition disabled:opacity-50 shadow-2xs"
                                >
                                    <svg v-if="isSendingMessage[item.id]" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                    <span>{{ isSendingMessage[item.id] ? 'Sending...' : 'Send Message' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-[11px] pt-0.5 flex-wrap">
                        <span 
                            class="font-semibold"
                            :class="isOverdue(item.targetDeadline, item.status) ? 'text-rose-700 font-bold' : 'text-amber-700'"
                        >
                            Due: {{ formatDate(item.targetDeadline) }}
                            <span v-if="isOverdue(item.targetDeadline, item.status)" class="text-rose-600 font-bold ml-1">(OVERDUE)</span>
                        </span>
                        <span v-if="item.status === 'completed'" class="text-emerald-700 font-bold">✓ Completed</span>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="deadlineItems.length === 0" class="text-center py-10 px-4 bg-white border border-dashed border-gray-200">
                    <p class="text-xs text-gray-500 font-medium">No target milestones or deadlines recorded.</p>
                    <button 
                        @click="openDeadline"
                        class="mt-2 px-3 py-1.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 transition"
                    >
                        + Set Target Deadline
                    </button>
                </div>
            </div>

            <!-- TAB 3: DIRECTIVES & NOTES -->
            <div v-if="activeTab === 'notes'" class="space-y-2.5">
                <div 
                    v-for="item in staffNotes" 
                    :key="item.id"
                    class="p-3 sm:p-3.5 border border-gray-200 bg-white hover:border-gray-300 transition text-xs space-y-2"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <h4 class="font-bold text-gray-900 text-sm">{{ item.title }}</h4>
                                <span class="px-1.5 py-0.2 text-[10px] font-semibold border" :class="getPriorityBadge(item.priority)">
                                    {{ item.priority }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-700 whitespace-pre-line bg-gray-50 p-2 border-l-2 border-blue-400">
                                {{ item.note }}
                            </p>
                        </div>

                        <!-- Actions: Conversation & Delete -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button 
                                @click="toggleConversation(item)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold transition border"
                                :class="openConversations[item.id] ? 'bg-slate-900 text-white border-slate-900' : 'bg-gray-50 text-gray-700 hover:bg-gray-100 border-gray-300'"
                                title="Open Conversation Thread"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                <span>Discussion</span>
                                <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="openConversations[item.id] ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-800'">
                                    {{ getConversation(item).length }}
                                </span>
                            </button>
                            <button 
                                @click="promptDeleteNote(item)"
                                class="text-gray-400 hover:text-rose-600 p-1 transition"
                                title="Delete Directive / Note"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Conversation Thread Panel -->
                    <div v-if="openConversations[item.id]" class="mt-2 border border-slate-200 bg-slate-50/80 p-3 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-blue-500 animate-pulse"></span>
                                <span class="font-bold text-slate-900 text-xs">Directive Follow-up & Discussion</span>
                                <span class="text-[10px] text-slate-500">({{ getConversation(item).length }} message{{ getConversation(item).length === 1 ? '' : 's' }})</span>
                            </div>
                            <button @click="openConversations[item.id] = false" class="text-slate-400 hover:text-slate-600 text-xs font-semibold">
                                Collapse ✕
                            </button>
                        </div>

                        <!-- Thread Messages -->
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            <div v-if="getConversation(item).length === 0" class="text-center py-4 text-slate-400 text-xs italic">
                                No messages in this discussion yet. Start the conversation below.
                            </div>
                            <div 
                                v-for="(msg, mIdx) in getConversation(item)" 
                                :key="msg.id || mIdx"
                                class="flex items-start gap-2.5"
                                :class="isMyMessage(msg) ? 'flex-row-reverse' : 'flex-row'"
                            >
                                <div 
                                    class="w-7 h-7 shrink-0 text-[10px] font-bold flex items-center justify-center text-white overflow-hidden shadow-2xs"
                                    :class="isMyMessage(msg) ? 'bg-blue-600' : (msg.sender_role === 'staff' ? 'bg-emerald-700' : 'bg-slate-700')"
                                >
                                    <img v-if="msg.sender_photo" :src="msg.sender_photo" class="w-full h-full object-cover" />
                                    <span v-else>{{ getInitials(msg.sender_name) }}</span>
                                </div>

                                <div 
                                    class="max-w-[85%] sm:max-w-[75%] p-2.5 text-xs shadow-2xs border"
                                    :class="isMyMessage(msg) ? 'bg-blue-600 border-blue-700 text-white' : 'bg-white border-slate-200 text-slate-900'"
                                >
                                    <div class="flex items-center gap-2 mb-1 text-[10px]" :class="isMyMessage(msg) ? 'justify-end text-blue-100' : 'justify-start text-slate-500'">
                                        <strong class="font-bold" :class="isMyMessage(msg) ? 'text-white' : 'text-slate-900'">
                                            {{ isMyMessage(msg) ? 'You' : msg.sender_name }}
                                        </strong>
                                        <span class="px-1 py-0.2 text-[9px] font-bold uppercase tracking-wider" :class="isMyMessage(msg) ? 'bg-blue-800/60 text-blue-100' : (msg.sender_role === 'staff' ? 'bg-emerald-200 text-emerald-900' : 'bg-slate-200 text-slate-800')">
                                            {{ msg.sender_role || 'user' }}
                                        </span>
                                        <span v-if="msg.created_at" class="font-normal" :class="isMyMessage(msg) ? 'text-blue-200' : 'text-slate-400'">{{ msg.created_at }}</span>
                                    </div>
                                    <p class="whitespace-pre-line leading-relaxed break-words" :class="isMyMessage(msg) ? 'text-white' : 'text-slate-800'">{{ msg.message }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Message Input -->
                        <div class="pt-2 border-t border-slate-200 space-y-2">
                            <textarea 
                                v-model="conversationInputs[item.id]"
                                @keydown="handleKeyDown($event, item)"
                                rows="2"
                                :placeholder="`Type a directive note or instruction to ${staff.name}... (Press Enter to send)`"
                                class="w-full text-xs border border-slate-300 p-2.5 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 bg-white placeholder:text-slate-400"
                            ></textarea>
                            <div class="flex items-center justify-between gap-2 flex-wrap text-[11px]">
                                <span class="text-slate-500 text-[10px]">💡 <kbd class="px-1 py-0.5 bg-slate-200 text-slate-700">Enter</kbd> to send • <kbd class="px-1 py-0.5 bg-slate-200 text-slate-700">Shift+Enter</kbd> for new line</span>
                                <button 
                                    type="button" 
                                    @click="sendConversationMessage(item)"
                                    :disabled="isSendingMessage[item.id] || !(conversationInputs[item.id] && conversationInputs[item.id].trim())"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition disabled:opacity-50 shadow-2xs"
                                >
                                    <svg v-if="isSendingMessage[item.id]" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                    <span>{{ isSendingMessage[item.id] ? 'Sending...' : 'Post Message' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-[11px] text-gray-400 pt-0.5 flex-wrap">
                        <span>Posted: {{ formatDate(item.createdAt) }}</span>
                        <span>By: {{ item.assignerName || 'Admin' }}</span>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="staffNotes.length === 0" class="text-center py-10 px-4 bg-white border border-dashed border-gray-200">
                    <p class="text-xs text-gray-500 font-medium">No directives or administrative notes recorded for {{ staff.name }}.</p>
                    <button 
                        @click="openNote"
                        class="mt-2 px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition"
                    >
                        + Post Directive Note
                    </button>
                </div>
            </div>

        </div>

        <!-- ==================== SUB-MODALS ==================== -->
        
        <!-- Modal: Assign Project -->
        <Teleport to="body">
            <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4" @click.self="showAssignModal = false">
                <div class="bg-white max-w-lg w-full max-h-[92vh] flex flex-col p-4 sm:p-5 shadow-2xl border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-red-50 text-red-700 flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Assign Project to {{ staff.name }}</h3>
                                <p class="text-[11px] text-gray-500">Deploy staff member to an infrastructure project</p>
                            </div>
                        </div>
                        <button @click="showAssignModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="handleSaveAssignment" class="mt-3 space-y-3 overflow-y-auto flex-1 pr-1 text-xs">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Select Project *</label>
                            <select 
                                v-model="assignForm.project_id" 
                                required
                                class="w-full text-xs border border-gray-300 p-2 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                            >
                                <option value="" disabled>Select Infrastructure Project</option>
                                <option v-for="proj in props.projects" :key="proj.id" :value="proj.id">
                                    {{ proj.project_name || proj.name }} ({{ proj.location || 'Site Location' }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Designation / Role in Project</label>
                            <input 
                                type="text"
                                v-model="assignForm.role_in_project"
                                placeholder="e.g. Project Inspector, Site Engineer"
                                class="w-full text-xs border border-gray-300 p-2 focus:border-red-600 focus:ring-1 focus:ring-red-600 mb-1.5"
                            />
                            <div class="flex flex-wrap gap-1">
                                <button 
                                    v-for="role in roleSuggestions" 
                                    :key="role" 
                                    type="button"
                                    @click="assignForm.role_in_project = role"
                                    class="text-[10px] px-1.5 py-0.5 bg-gray-100 hover:bg-red-50 hover:text-red-700 text-gray-600 transition"
                                >
                                    {{ role }}
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Target Deadline (Optional)</label>
                                <input 
                                    type="date"
                                    v-model="assignForm.target_deadline"
                                    class="w-full text-xs border border-gray-300 p-2 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                                />
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Priority</label>
                                <select 
                                    v-model="assignForm.priority"
                                    class="w-full text-xs border border-gray-300 p-2 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                                >
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Special Instructions (Optional)</label>
                            <textarea 
                                v-model="assignForm.note"
                                rows="3"
                                placeholder="Specific instructions for this project..."
                                class="w-full text-xs border border-gray-300 p-2 focus:border-red-600 focus:ring-1 focus:ring-red-600"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-1.5 pt-2.5 border-t border-gray-100">
                            <button 
                                type="button" 
                                @click="showAssignModal = false"
                                class="px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                :disabled="isSubmitting"
                                class="px-4 py-1.5 text-xs font-bold text-white bg-red-700 hover:bg-red-800 shadow-xs transition disabled:opacity-50"
                            >
                                {{ isSubmitting ? 'Saving...' : 'Confirm Assignment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Set Target Deadline -->
        <Teleport to="body">
            <div v-if="showDeadlineModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4" @click.self="showDeadlineModal = false">
                <div class="bg-white max-w-lg w-full max-h-[92vh] flex flex-col p-4 sm:p-5 shadow-2xl border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-amber-50 text-amber-700 flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Set Deadline for {{ staff.name }}</h3>
                                <p class="text-[11px] text-gray-500">Create an actionable milestone or task</p>
                            </div>
                        </div>
                        <button @click="showDeadlineModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="handleSaveDeadline" class="mt-3 space-y-3 overflow-y-auto flex-1 pr-1 text-xs">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Milestone / Task Title *</label>
                            <input 
                                type="text"
                                v-model="deadlineForm.title"
                                required
                                placeholder="e.g. Complete POW Preparation, Site Inspection"
                                class="w-full text-xs border border-gray-300 p-2 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                            />
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Linked Project (Optional)</label>
                            <select 
                                v-model="deadlineForm.project_id"
                                class="w-full text-xs border border-gray-300 p-2 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                            >
                                <option value="">No Specific Project (General Task)</option>
                                <option v-for="proj in props.projects" :key="proj.id" :value="proj.id">
                                    {{ proj.project_name || proj.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Due Date *</label>
                                <input 
                                    type="date"
                                    v-model="deadlineForm.target_deadline"
                                    required
                                    class="w-full text-xs border border-gray-300 p-2 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                                />
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Priority</label>
                                <select 
                                    v-model="deadlineForm.priority"
                                    class="w-full text-xs border border-gray-300 p-2 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                                >
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="normal">Normal</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Details (Optional)</label>
                            <textarea 
                                v-model="deadlineForm.note"
                                rows="3"
                                placeholder="Additional details..."
                                class="w-full text-xs border border-gray-300 p-2 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-1.5 pt-2.5 border-t border-gray-100">
                            <button 
                                type="button" 
                                @click="showDeadlineModal = false"
                                class="px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                :disabled="isSubmitting"
                                class="px-4 py-1.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-xs transition disabled:opacity-50"
                            >
                                {{ isSubmitting ? 'Saving...' : 'Set Deadline' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Add Directive Note -->
        <Teleport to="body">
            <div v-if="showNoteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4" @click.self="showNoteModal = false">
                <div class="bg-white max-w-lg w-full max-h-[92vh] flex flex-col p-4 sm:p-5 shadow-2xl border border-gray-200 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-blue-50 text-blue-700 flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Post Directive for {{ staff.name }}</h3>
                                <p class="text-[11px] text-gray-500">Post instructions or an administrative memo</p>
                            </div>
                        </div>
                        <button @click="showNoteModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="handleSaveNote" class="mt-3 space-y-3 overflow-y-auto flex-1 pr-1 text-xs">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Subject / Title *</label>
                            <input 
                                type="text"
                                v-model="noteForm.title"
                                required
                                placeholder="e.g. Priority Review: Drainage POW Documents"
                                class="w-full text-xs border border-gray-300 p-2 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            />
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Priority</label>
                            <select 
                                v-model="noteForm.priority"
                                class="w-full text-xs border border-gray-300 p-2 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            >
                                <option value="normal">Normal Information</option>
                                <option value="high">High Priority Action</option>
                                <option value="urgent">Urgent Directive</option>
                                <option value="low">FYI / Reference</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Message / Note Content *</label>
                            <textarea 
                                v-model="noteForm.note"
                                required
                                rows="3"
                                placeholder="Write direct instructions or remarks..."
                                class="w-full text-xs border border-gray-300 p-2 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-1.5 pt-2.5 border-t border-gray-100">
                            <button 
                                type="button" 
                                @click="showNoteModal = false"
                                class="px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                :disabled="isSubmitting"
                                class="px-4 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-xs transition disabled:opacity-50"
                            >
                                {{ isSubmitting ? 'Saving...' : 'Post Directive' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ========================================== -->
        <!-- MODAL: DEDICATED CONFIRMATION DIALOG       -->
        <!-- ========================================== -->
        <Teleport to="body">
            <div 
                v-if="confirmModal.show" 
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-xs animate-in fade-in duration-150"
                @click.self="closeConfirmModal"
                role="dialog"
                aria-modal="true"
            >
                <div class="bg-white max-w-md w-full border border-slate-200 shadow-2xl p-5 sm:p-6 space-y-4 animate-in zoom-in-95 duration-150">
                    
                    <!-- Header with Icon -->
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="space-y-1 min-w-0 flex-1">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 leading-tight">
                                {{ confirmModal.title }}
                            </h3>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                {{ confirmModal.message }}
                            </p>
                        </div>
                    </div>

                    <!-- Target Item Details Preview Box -->
                    <div v-if="confirmModal.item" class="bg-slate-50 border border-slate-200 p-3 space-y-1.5 text-xs">
                        <div v-if="confirmModal.type === 'assignment'" class="space-y-1">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-semibold uppercase tracking-wider">Project:</span>
                                <span class="font-bold text-slate-900 text-right truncate max-w-[220px]">{{ confirmModal.item.projectName || confirmModal.item.title }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-semibold uppercase tracking-wider">Staff:</span>
                                <span class="font-bold text-slate-900">{{ staff.name }}</span>
                            </div>
                            <div v-if="confirmModal.item.roleInProject" class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-semibold uppercase tracking-wider">Assigned Role:</span>
                                <span class="font-bold text-slate-700">{{ confirmModal.item.roleInProject }}</span>
                            </div>
                        </div>

                        <div v-else-if="confirmModal.type === 'deadline'" class="space-y-1">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-semibold uppercase tracking-wider">Milestone:</span>
                                <span class="font-bold text-slate-900 text-right truncate max-w-[220px]">{{ confirmModal.item.title }}</span>
                            </div>
                            <div v-if="confirmModal.item.targetDeadline" class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-semibold uppercase tracking-wider">Target Due Date:</span>
                                <span class="font-bold text-amber-700">{{ formatDate(confirmModal.item.targetDeadline) }}</span>
                            </div>
                            <div v-if="confirmModal.item.projectName" class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-semibold uppercase tracking-wider">Project:</span>
                                <span class="font-medium text-slate-700 truncate max-w-[220px]">{{ confirmModal.item.projectName }}</span>
                            </div>
                        </div>

                        <div v-else-if="confirmModal.type === 'note'" class="space-y-1">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-semibold uppercase tracking-wider">Subject:</span>
                                <span class="font-bold text-slate-900 text-right truncate max-w-[220px]">{{ confirmModal.item.title }}</span>
                            </div>
                            <div v-if="confirmModal.item.note" class="text-[11px] text-slate-600 italic line-clamp-2 pt-0.5">
                                "{{ confirmModal.item.note }}"
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="closeConfirmModal"
                            :disabled="confirmModal.isProcessing"
                            class="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button 
                            type="button"
                            @click="executeConfirmAction"
                            :disabled="confirmModal.isProcessing"
                            class="px-4 py-1.5 text-xs font-bold transition flex items-center gap-1.5 shadow-2xs disabled:opacity-50"
                            :class="confirmModal.confirmButtonClass"
                        >
                            <svg v-if="confirmModal.isProcessing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ confirmModal.isProcessing ? 'Processing...' : confirmModal.confirmButtonText }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </div>
</template>
