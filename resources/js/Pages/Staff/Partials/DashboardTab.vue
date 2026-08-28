<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_tasks: 0,
            active_projects: 0,
            upcoming_inspections: 0,
        }),
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

// Directives and Notes state
const directives = ref([]);
const isLoadingDirectives = ref(true);
const currentSlideIndex = ref(0);
const autoPlay = ref(true);
let autoPlayTimer = null;

// Reminders state
const reminders = ref([]);
const isLoadingReminders = ref(true);
const reminderAudienceFilter = ref('all'); // 'all' | 'everyone' | 'personal'

// Modal & Reply states
const showPopupModal = ref(false);
const popupSlideIndex = ref(0);
const replyingDirective = ref(null);
const showReplyModal = ref(false);
const replyForm = ref({
    staff_reply: '',
    status: 'in_progress',
});
const isSubmittingReply = ref(false);
const updatingStatusId = ref(null);
const togglingReminderId = ref(null);

// Priority weights (Must show highest priority first)
const priorityWeights = {
    urgent: 4,
    high: 3,
    normal: 2,
    low: 1,
};

// Priority sorted directives (Urgent > High > Normal > Low, then active status, then nearest deadline)
const sortedDirectives = computed(() => {
    return [...directives.value].sort((a, b) => {
        const weightA = priorityWeights[a.priority?.toLowerCase()] || 2;
        const weightB = priorityWeights[b.priority?.toLowerCase()] || 2;
        if (weightB !== weightA) {
            return weightB - weightA;
        }

        // Active first
        const isCompletedA = a.status === 'completed' ? 1 : 0;
        const isCompletedB = b.status === 'completed' ? 1 : 0;
        if (isCompletedA !== isCompletedB) {
            return isCompletedA - isCompletedB;
        }

        // Soonest deadline next
        if (a.targetDeadline && b.targetDeadline) {
            return new Date(a.targetDeadline) - new Date(b.targetDeadline);
        }
        return new Date(b.createdAt || 0) - new Date(a.createdAt || 0);
    });
});

const currentSlide = computed(() => {
    if (!sortedDirectives.value.length) return null;
    const safeIdx = Math.max(0, Math.min(currentSlideIndex.value, sortedDirectives.value.length - 1));
    return sortedDirectives.value[safeIdx];
});

const popupSlide = computed(() => {
    if (!sortedDirectives.value.length) return null;
    const safeIdx = Math.max(0, Math.min(popupSlideIndex.value, sortedDirectives.value.length - 1));
    return sortedDirectives.value[safeIdx];
});

const urgentCount = computed(() => {
    return sortedDirectives.value.filter(d => (d.priority === 'urgent' || d.priority === 'high') && d.status !== 'completed').length;
});

const activeDirectivesCount = computed(() => {
    return sortedDirectives.value.filter(d => d.status !== 'completed').length;
});

// Reminders computations: All active reminders chronologically sorted
const activeReminders = computed(() => {
    return reminders.value
        .filter(r => r.startsAt && !r.isDone)
        .sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt));
});

const filteredReminders = computed(() => {
    if (reminderAudienceFilter.value === 'all') return activeReminders.value;
    return activeReminders.value.filter(r => (r.audience || 'personal') === reminderAudienceFilter.value);
});

const pinnedReminder = computed(() => {
    return filteredReminders.value.length > 0 ? filteredReminders.value[0] : null;
});

const otherReminders = computed(() => {
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

let directivePollTimer = null;

// Load directives for this staff member
const loadDirectives = async () => {
    isLoadingDirectives.value = true;
    try {
        const userId = currentUser.value?.id;
        const url = userId ? `/staff-assignments?user_id=${userId}` : '/staff-assignments';
        const res = await axios.get(url);
        if (res.data?.assignments) {
            directives.value = res.data.assignments;

            // Trigger popup once on dashboard load if active directives exist
            const popupAlreadyShown = sessionStorage.getItem('meo_staff_directive_popup_shown');
            if (!popupAlreadyShown && sortedDirectives.value.length > 0 && activeDirectivesCount.value > 0) {
                showPopupModal.value = true;
                sessionStorage.setItem('meo_staff_directive_popup_shown', 'true');
            }
        }
    } catch (e) {
        console.error('Failed to fetch staff directives:', e);
    } finally {
        isLoadingDirectives.value = false;
    }
};

const silentSyncDirectives = async () => {
    if (typeof document !== 'undefined' && document.hidden) return;
    if (isLoadingDirectives.value) return;

    try {
        const userId = currentUser.value?.id;
        const url = userId ? `/staff-assignments?user_id=${userId}` : '/staff-assignments';
        const res = await axios.get(url);
        if (res.data?.assignments && Array.isArray(res.data.assignments)) {
            directives.value = res.data.assignments;
        }
    } catch (e) {
        // Silent background polling error
    }
};

// Load reminders
const loadReminders = async () => {
    isLoadingReminders.value = true;
    try {
        const res = await axios.get('/reminders');
        if (Array.isArray(res.data)) {
            reminders.value = res.data;
        }
    } catch (e) {
        console.error('Failed to fetch reminders:', e);
    } finally {
        isLoadingReminders.value = false;
    }
};

const toggleReminderDone = async (reminder) => {
    if (togglingReminderId.value) return;
    togglingReminderId.value = reminder.id;
    try {
        const res = await axios.patch(`/reminders/${reminder.id}/complete`, { is_done: !reminder.isDone });
        if (res.data) {
            const idx = reminders.value.findIndex(r => r.id === reminder.id);
            if (idx > -1) {
                reminders.value[idx] = res.data;
            }
        }
    } catch (e) {
        console.error('Failed to toggle reminder status:', e);
    } finally {
        togglingReminderId.value = null;
    }
};

// Slideshow Navigation for Card
const nextSlide = () => {
    if (sortedDirectives.value.length <= 1) return;
    currentSlideIndex.value = (currentSlideIndex.value + 1) % sortedDirectives.value.length;
};

const prevSlide = () => {
    if (sortedDirectives.value.length <= 1) return;
    currentSlideIndex.value = (currentSlideIndex.value - 1 + sortedDirectives.value.length) % sortedDirectives.value.length;
};

const goToSlide = (index) => {
    currentSlideIndex.value = index;
};

// Slideshow Navigation for Popup Modal
const nextPopupSlide = () => {
    if (sortedDirectives.value.length <= 1) return;
    popupSlideIndex.value = (popupSlideIndex.value + 1) % sortedDirectives.value.length;
};

const prevPopupSlide = () => {
    if (sortedDirectives.value.length <= 1) return;
    popupSlideIndex.value = (popupSlideIndex.value - 1 + sortedDirectives.value.length) % sortedDirectives.value.length;
};

const openPopupFromCard = () => {
    popupSlideIndex.value = currentSlideIndex.value;
    showPopupModal.value = true;
};

// Autoplay Timer
const startAutoPlay = () => {
    stopAutoPlay();
    if (sortedDirectives.value.length > 1 && autoPlay.value) {
        autoPlayTimer = setInterval(() => {
            nextSlide();
        }, 7000);
    }
};

const stopAutoPlay = () => {
    if (autoPlayTimer) {
        clearInterval(autoPlayTimer);
        autoPlayTimer = null;
    }
};

// Priority styling helper
const getPriorityMeta = (priority) => {
    const p = (priority || 'normal').toLowerCase();
    if (p === 'urgent') {
        return {
            label: 'URGENT',
            badge: 'bg-red-50 text-red-700 border border-red-200 font-black',
            dot: 'bg-red-500',
        };
    }
    if (p === 'high') {
        return {
            label: 'HIGH PRIORITY',
            badge: 'bg-amber-50 text-amber-700 border border-amber-200 font-bold',
            dot: 'bg-amber-500',
        };
    }
    if (p === 'low') {
        return {
            label: 'LOW',
            badge: 'bg-gray-50 text-gray-600 border border-gray-200 font-medium',
            dot: 'bg-gray-400',
        };
    }
    return {
        label: 'NORMAL',
        badge: 'bg-blue-50 text-blue-700 border border-blue-200 font-semibold',
        dot: 'bg-blue-500',
    };
};

const getStatusMeta = (status) => {
    const s = (status || '').toLowerCase();
    if (s === 'completed') return { label: 'Completed', bg: 'bg-emerald-50 text-emerald-700 border border-emerald-200' };
    if (s === 'in_progress') return { label: 'In Progress', bg: 'bg-blue-50 text-blue-700 border border-blue-200' };
    if (s === 'cancelled') return { label: 'Cancelled', bg: 'bg-gray-50 text-gray-600 border border-gray-200' };
    return { label: 'Pending', bg: 'bg-amber-50 text-amber-700 border border-amber-200' };
};

const getReminderCategoryBadge = (category) => {
    const c = (category || '').toLowerCase();
    if (c === 'inspection') return 'bg-amber-50 text-amber-700 border-amber-200';
    if (c === 'meeting') return 'bg-blue-50 text-blue-700 border-blue-200';
    if (c === 'training') return 'bg-purple-50 text-purple-700 border-purple-200';
    if (c === 'deadline') return 'bg-red-50 text-red-700 border-red-200';
    if (c === 'field visit') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    return 'bg-gray-50 text-gray-700 border-gray-200';
};

// Date helper
const formatDate = (val) => {
    if (!val) return '—';
    try {
        const d = new Date(val);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch (e) {
        return val;
    }
};

const formatTime = (val) => {
    if (!val) return '';
    try {
        const d = new Date(val);
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    } catch (e) {
        return '';
    }
};

const isOverdue = (deadlineStr, status) => {
    if (!deadlineStr || status === 'completed' || status === 'cancelled') return false;
    const due = new Date(deadlineStr);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return due < now;
};

// Quick status updater
const updateStatus = async (item, newStatus) => {
    updatingStatusId.value = item.id;
    try {
        const res = await axios.patch(`/staff-assignments/${item.id}/status`, { status: newStatus });
        if (res.data?.assignment) {
            const idx = directives.value.findIndex(d => d.id === item.id);
            if (idx > -1) {
                directives.value[idx] = res.data.assignment;
            }
        }
    } catch (e) {
        console.error('Failed to update status:', e);
    } finally {
        updatingStatusId.value = null;
    }
};

// Reply Handling
const openReply = (item) => {
    replyingDirective.value = item;
    replyForm.value = {
        staff_reply: item.staffReply || '',
        status: item.status || 'in_progress',
    };
    showReplyModal.value = true;
};

const closeReply = () => {
    showReplyModal.value = false;
    replyingDirective.value = null;
    replyForm.value = {
        staff_reply: '',
        status: 'in_progress',
    };
};

const handleSendReply = async () => {
    if (!replyingDirective.value) return;
    isSubmittingReply.value = true;
    try {
        const res = await axios.patch(`/staff-assignments/${replyingDirective.value.id}/reply`, {
            staff_reply: replyForm.value.staff_reply,
            status: replyForm.value.status,
        });

        if (res.data?.assignment) {
            const idx = directives.value.findIndex(d => d.id === replyingDirective.value.id);
            if (idx > -1) {
                directives.value[idx] = res.data.assignment;
            }
        }
        closeReply();
    } catch (e) {
        console.error('Failed to submit reply:', e);
        alert(e.response?.data?.message || 'Failed to submit reply.');
    } finally {
        isSubmittingReply.value = false;
    }
};

const goToProject = (projectId) => {
    if (!projectId) return;
    localStorage.setItem('meo_staff_active_tab', 'projects');
    router.visit(`/staff/projects/${projectId}/my-details`);
};

const goToRemindersTab = () => {
    localStorage.setItem('meo_staff_active_tab', 'reminders');
    router.visit('/staff/dashboard');
};

// Keyboard Arrow navigation for modals & slideshows
const handleKeyDown = (e) => {
    if (showPopupModal.value && !showReplyModal.value) {
        if (e.key === 'ArrowRight') nextPopupSlide();
        if (e.key === 'ArrowLeft') prevPopupSlide();
        if (e.key === 'Escape') showPopupModal.value = false;
    }
};

onMounted(() => {
    loadDirectives();
    loadReminders();
    startAutoPlay();
    window.addEventListener('keydown', handleKeyDown);
    if (directivePollTimer) clearInterval(directivePollTimer);
    directivePollTimer = setInterval(silentSyncDirectives, 5000);
});

onUnmounted(() => {
    stopAutoPlay();
    window.removeEventListener('keydown', handleKeyDown);
    if (directivePollTimer) {
        clearInterval(directivePollTimer);
        directivePollTimer = null;
    }
});

watch(sortedDirectives, () => {
    if (currentSlideIndex.value >= sortedDirectives.value.length) {
        currentSlideIndex.value = 0;
    }
    if (popupSlideIndex.value >= sortedDirectives.value.length) {
        popupSlideIndex.value = 0;
    }
});
</script>

<template>
    <div class="w-full font-sans antialiased space-y-4">

        <!-- ======================================================== -->
        <!-- 1. TOP STATS CARDS (WHITE CARDS)                         -->
        <!-- ======================================================== -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white border border-gray-200 p-3.5 flex items-center justify-between shadow-2xs hover:border-gray-300 transition">
                <div class="space-y-0.5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Active Directives</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-red-700 tracking-tight">{{ activeDirectivesCount }}</p>
                </div>
                <div class="p-2 bg-red-50 text-red-700 border border-red-100 rounded-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
            </div>

            <div class="bg-white border border-gray-200 p-3.5 flex items-center justify-between shadow-2xs hover:border-gray-300 transition">
                <div class="space-y-0.5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Urgent / High</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-amber-600 tracking-tight">{{ urgentCount }}</p>
                </div>
                <div class="p-2 bg-amber-50 text-amber-600 border border-amber-100 rounded-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>

            <div class="bg-white border border-gray-200 p-3.5 flex items-center justify-between shadow-2xs hover:border-gray-300 transition">
                <div class="space-y-0.5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Upcoming Schedules</p>
                    <div class="flex items-baseline gap-1.5">
                        <p class="text-xl sm:text-2xl font-extrabold text-blue-600 tracking-tight">{{ activeReminders.length }}</p>
                        <span v-if="officeWideRemindersCount > 0" class="text-[10px] font-semibold text-purple-700">({{ officeWideRemindersCount }} office)</span>
                    </div>
                </div>
                <div class="p-2 bg-blue-50 text-blue-600 border border-blue-100 rounded-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>

            <div class="bg-white border border-gray-200 p-3.5 flex items-center justify-between shadow-2xs hover:border-gray-300 transition">
                <div class="space-y-0.5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Task Completion</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-emerald-600 tracking-tight">
                        {{ directives.length > 0 ? Math.round((directives.filter(d => d.status === 'completed').length / directives.length) * 100) : 100 }}%
                    </p>
                </div>
                <div class="p-2 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- 2. BLOCK GRID LAYOUT: DIRECTIVES (LEFT) & REMINDERS (RIGHT) -->
        <!-- ======================================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

            <!-- LEFT BLOCK: DIRECTIVES & NOTES SLIDESHOW (ALL-WHITE CARD) -->
            <div 
                class="bg-white border border-gray-200 shadow-2xs overflow-hidden flex flex-col"
                @mouseenter="stopAutoPlay"
                @mouseleave="startAutoPlay"
            >
                <!-- Card Header (Clean White) -->
                <div class="px-4 py-3 bg-white border-b border-gray-100 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-6 h-6 rounded bg-red-50 text-red-700 border border-red-100 flex items-center justify-center font-bold text-xs shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <h2 class="text-xs sm:text-sm font-bold text-gray-900 truncate">Directives & Admin Notes</h2>
                        <span v-if="urgentCount > 0" class="px-1.5 py-0.2 text-[9px] font-black bg-red-50 text-red-700 border border-red-200 uppercase shrink-0">
                            {{ urgentCount }} Urgent
                        </span>
                    </div>

                    <!-- Slide Controls -->
                    <div class="flex items-center gap-1 shrink-0">
                        <span v-if="sortedDirectives.length > 0" class="text-[11px] font-mono font-bold text-gray-600 bg-gray-50 px-2 py-0.5 border border-gray-200">
                            {{ currentSlideIndex + 1 }} / {{ sortedDirectives.length }}
                        </span>

                        <button
                            type="button"
                            @click="prevSlide"
                            :disabled="sortedDirectives.length <= 1"
                            class="p-1 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 disabled:opacity-30 transition"
                            title="Previous Directive"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>

                        <button
                            type="button"
                            @click="nextSlide"
                            :disabled="sortedDirectives.length <= 1"
                            class="p-1 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 disabled:opacity-30 transition"
                            title="Next Directive"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>

                        <button
                            type="button"
                            @click="openPopupFromCard"
                            class="px-2 py-1 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 font-bold text-[10px] transition shadow-2xs ml-0.5 flex items-center gap-1"
                            title="Open Slideshow Modal"
                        >
                            <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                            <span>Popup</span>
                        </button>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoadingDirectives" class="p-6 text-center text-xs text-gray-400 space-y-1.5 bg-white">
                    <svg class="animate-spin w-5 h-5 text-red-600 mx-auto" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75"></path></svg>
                    <p>Loading directives...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="sortedDirectives.length === 0" class="p-8 text-center space-y-1 bg-white">
                    <div class="w-7 h-7 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full flex items-center justify-center mx-auto mb-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p class="text-xs font-bold text-gray-800">No Pending Directives</p>
                    <p class="text-[11px] text-gray-500">You are all caught up on all tasks and admin directives.</p>
                </div>

                <!-- Active Slide Content Body -->
                <div v-else-if="currentSlide" class="p-4 space-y-3 bg-white flex-1 flex flex-col justify-between">
                    
                    <div class="space-y-3">
                        <!-- Priority, Status & Deadline Row -->
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-2 text-xs">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span 
                                    class="px-2 py-0.5 text-[10px] uppercase tracking-wider rounded-xs"
                                    :class="getPriorityMeta(currentSlide.priority).badge"
                                >
                                    {{ getPriorityMeta(currentSlide.priority).label }}
                                </span>

                                <span 
                                    class="px-2 py-0.5 text-[10px] rounded-xs"
                                    :class="getStatusMeta(currentSlide.status).bg"
                                >
                                    {{ getStatusMeta(currentSlide.status).label }}
                                </span>

                                <span v-if="currentSlide.projectName" class="px-2 py-0.5 text-[10px] font-semibold bg-gray-50 text-gray-700 border border-gray-200 truncate max-w-xs inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                    <span class="truncate">{{ currentSlide.projectName }}</span>
                                </span>
                            </div>

                            <div class="flex items-center gap-2 text-[11px] text-gray-500">
                                <span v-if="currentSlide.targetDeadline" :class="isOverdue(currentSlide.targetDeadline, currentSlide.status) ? 'text-red-700 font-bold' : 'text-gray-600 font-medium'">
                                    Due: {{ formatDate(currentSlide.targetDeadline) }}
                                    <span v-if="isOverdue(currentSlide.targetDeadline, currentSlide.status)" class="text-[9px] bg-red-50 text-red-700 border border-red-200 px-1 font-bold ml-1">OVERDUE</span>
                                </span>
                                <span>• By: {{ currentSlide.assignerName || 'Admin' }}</span>
                            </div>
                        </div>

                        <!-- Directive Title -->
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-gray-900 leading-snug">
                                {{ currentSlide.title }}
                            </h3>
                        </div>

                        <!-- Admin Instruction Note Box (All-White with subtle left border) -->
                        <div v-if="currentSlide.note" class="bg-white border border-gray-200 border-l-4 border-l-red-600 p-2.5 text-xs text-gray-800 space-y-0.5">
                            <div class="flex items-center gap-1 text-[10px] font-bold text-gray-500 uppercase">
                                <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Instruction Note:</span>
                            </div>
                            <p class="leading-relaxed whitespace-pre-line text-gray-700 font-normal">
                                {{ currentSlide.note }}
                            </p>
                        </div>

                        <!-- Staff Reply Note (All-White with emerald border) -->
                        <div v-if="currentSlide.staffReply" class="bg-white border border-gray-200 border-l-4 border-l-emerald-600 p-2.5 text-xs space-y-0.5">
                            <div class="flex items-center justify-between text-[10px]">
                                <div class="flex items-center gap-1 font-bold text-emerald-800">
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    <span>Your Field Note (Visible to Admin):</span>
                                </div>
                                <span v-if="currentSlide.staffRepliedAt" class="text-emerald-600">{{ currentSlide.staffRepliedAt }}</span>
                            </div>
                            <p class="text-gray-700 leading-relaxed font-normal whitespace-pre-line">{{ currentSlide.staffReply }}</p>
                        </div>
                    </div>

                    <!-- Card Footer & Actions -->
                    <div class="flex flex-wrap items-center justify-between gap-2.5 pt-2.5 border-t border-gray-100 text-xs">
                        <!-- Dots -->
                        <div class="flex items-center gap-1">
                            <button
                                v-for="(item, idx) in sortedDirectives"
                                :key="item.id"
                                type="button"
                                @click="goToSlide(idx)"
                                class="h-1.5 rounded-full transition-all"
                                :class="currentSlideIndex === idx ? 'w-4 bg-red-600' : 'w-1.5 bg-gray-300 hover:bg-gray-400'"
                                :title="`${item.title} (${item.priority})`"
                            ></button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-1.5">
                            <!-- Quick Status Selector -->
                            <select
                                :value="currentSlide.status"
                                :disabled="updatingStatusId === currentSlide.id"
                                @change="updateStatus(currentSlide, $event.target.value)"
                                class="text-[11px] font-semibold border border-gray-200 py-1 pl-2 pr-5 bg-white text-gray-700 focus:ring-red-500 focus:border-red-500 shadow-2xs"
                            >
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>

                            <!-- Reply button -->
                            <button
                                type="button"
                                @click="openReply(currentSlide)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-white hover:bg-gray-50 text-emerald-700 border border-emerald-300 font-bold text-[11px] transition shadow-2xs"
                            >
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                <span>{{ currentSlide.staffReply ? 'Edit Reply' : 'Reply Note' }}</span>
                            </button>

                            <!-- Project link -->
                            <button
                                v-if="currentSlide.projectId"
                                type="button"
                                @click="goToProject(currentSlide.projectId)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 font-bold text-[11px] transition shadow-2xs"
                            >
                                <svg class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                <span>View Project</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT BLOCK: PINNED MOST UPCOMING & SCROLLABLE SCHEDULE LIST -->
            <div class="bg-white border border-gray-200 shadow-2xs overflow-hidden flex flex-col">
                <!-- Reminders Header (Clean White with Audience Toggle) -->
                <div class="px-4 py-3 bg-white border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center font-bold text-xs shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-gray-900">Reminders & Schedules</h3>
                            <p class="text-[10px] text-gray-500">Pinned upcoming + scrollable office & personal list</p>
                        </div>
                    </div>

                    <!-- Audience Filter Pills with SVG Icons -->
                    <div class="flex items-center gap-1 self-start sm:self-auto">
                        <button
                            type="button"
                            @click="reminderAudienceFilter = 'all'"
                            class="px-2 py-0.5 text-[10px] font-bold rounded-xs transition border flex items-center gap-1"
                            :class="reminderAudienceFilter === 'all' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            <span>All ({{ activeReminders.length }})</span>
                        </button>
                        <button
                            type="button"
                            @click="reminderAudienceFilter = 'everyone'"
                            class="px-2 py-0.5 text-[10px] font-bold rounded-xs transition border flex items-center gap-1"
                            :class="reminderAudienceFilter === 'everyone' ? 'bg-purple-700 text-white border-purple-700' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                            <span>Office ({{ officeWideRemindersCount }})</span>
                        </button>
                        <button
                            type="button"
                            @click="reminderAudienceFilter = 'personal'"
                            class="px-2 py-0.5 text-[10px] font-bold rounded-xs transition border flex items-center gap-1"
                            :class="reminderAudienceFilter === 'personal' ? 'bg-slate-700 text-white border-slate-700' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span>Personal ({{ personalRemindersCount }})</span>
                        </button>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoadingReminders" class="p-6 text-center text-xs text-gray-400 space-y-1 bg-white">
                    <svg class="animate-spin w-4 h-4 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75"></path></svg>
                    <p class="text-[11px]">Loading schedule...</p>
                </div>

                <!-- Empty State: truly no active reminders -->
                <div v-else-if="filteredReminders.length === 0" class="p-8 text-center space-y-2 bg-white flex-1 flex flex-col justify-center">
                    <div class="w-8 h-8 bg-gray-50 text-gray-400 flex items-center justify-center mx-auto rounded-full text-sm font-bold border border-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-xs sm:text-sm font-bold text-gray-800">No Reminders or Scheduled Events</p>
                        <p class="text-[11px] text-gray-500">
                            {{ reminderAudienceFilter === 'everyone' ? 'No active office-wide reminders.' : (reminderAudienceFilter === 'personal' ? 'No active personal reminders.' : 'You have no pending meetings, inspections, or deadlines.') }}
                        </p>
                    </div>
                </div>

                <!-- Active Schedule: PINNED MOST UPCOMING + SCROLLABLE LIST -->
                <div v-else class="p-3.5 space-y-3 bg-white flex-1 flex flex-col">
                    
                    <!-- 1. PINNED MOST UPCOMING SPOTLIGHT BOX -->
                    <div v-if="pinnedReminder" class="bg-white border border-blue-200 border-l-4 border-l-blue-600 p-3 shadow-2xs space-y-2 ring-1 ring-blue-50">
                        <div class="flex items-center justify-between gap-2 flex-wrap text-xs">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <!-- Pinned Badge with Eye-Catching Blinking / Pulsing Dot -->
                                <span class="px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-xs inline-flex items-center gap-1.5 shadow-2xs" :class="isToday(pinnedReminder.startsAt) ? 'bg-amber-50 text-amber-800 border border-amber-300' : 'bg-blue-50 text-blue-800 border border-blue-300'">
                                    <!-- Animated Radar / Ping Dot -->
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="isToday(pinnedReminder.startsAt) ? 'bg-amber-400' : 'bg-blue-400'"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2" :class="isToday(pinnedReminder.startsAt) ? 'bg-amber-600' : 'bg-blue-600'"></span>
                                    </span>
                                    <svg v-if="isToday(pinnedReminder.startsAt)" class="w-3 h-3 text-amber-600 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <svg v-else class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                    <span class="tracking-wide">{{ isToday(pinnedReminder.startsAt) ? 'Happening Today' : 'Most Upcoming' }}</span>
                                </span>

                                <!-- Audience Badge with SVG -->
                                <span 
                                    v-if="pinnedReminder.audience === 'everyone'"
                                    class="px-1.5 py-0.2 text-[9px] font-bold bg-purple-50 text-purple-700 border border-purple-200 rounded-xs inline-flex items-center gap-1"
                                >
                                    <svg class="w-2.5 h-2.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                    <span>Office-Wide</span>
                                </span>
                                <span 
                                    v-else
                                    class="px-1.5 py-0.2 text-[9px] font-semibold bg-gray-50 text-gray-600 border border-gray-200 rounded-xs inline-flex items-center gap-1"
                                >
                                    <svg class="w-2.5 h-2.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <span>Personal</span>
                                </span>

                                <!-- Category Badge -->
                                <span class="px-1.5 py-0.2 text-[9px] font-bold uppercase border rounded-xs" :class="getReminderCategoryBadge(pinnedReminder.category)">
                                    {{ pinnedReminder.category }}
                                </span>
                            </div>

                            <!-- Date / Time with SVGs -->
                            <div class="text-[11px] font-bold text-blue-800 flex items-center gap-2">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    {{ getRelativeScheduleLabel(pinnedReminder.startsAt) }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-gray-600 font-normal">
                                    <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ formatTime(pinnedReminder.startsAt) }}
                                </span>
                            </div>
                        </div>

                        <!-- Title and Checkbox -->
                        <div class="flex items-start gap-2">
                            <button
                                type="button"
                                @click="toggleReminderDone(pinnedReminder)"
                                :disabled="togglingReminderId === pinnedReminder.id"
                                class="mt-0.5 w-4 h-4 rounded border flex items-center justify-center text-[10px] font-bold transition shrink-0"
                                :class="pinnedReminder.isDone ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-gray-300 hover:border-blue-500 bg-white'"
                                title="Mark completed"
                            >
                                <svg v-if="pinnedReminder.isDone" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </button>
                            <div class="space-y-0.5 min-w-0 flex-1">
                                <h4 class="text-xs sm:text-sm font-bold text-gray-900 leading-snug">
                                    {{ pinnedReminder.title }}
                                </h4>
                                <p v-if="pinnedReminder.description" class="text-[11px] text-gray-600 leading-relaxed line-clamp-2">
                                    {{ pinnedReminder.description }}
                                </p>
                                <div class="flex items-center gap-2.5 text-[10px] text-gray-400 flex-wrap pt-0.5">
                                    <span v-if="pinnedReminder.location" class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        {{ pinnedReminder.location }}
                                    </span>
                                    <span v-if="pinnedReminder.creatorName" class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <span>By: <strong class="text-gray-600">{{ pinnedReminder.creatorName }}</strong></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. SCROLLABLE SCHEDULE LIST (OTHER UPCOMING & PERSONAL/EVERYONE ITEMS) -->
                    <div class="space-y-1.5 flex-1 flex flex-col">
                        <div class="flex items-center justify-between text-[11px] text-gray-500 font-semibold border-b border-gray-100 pb-1">
                            <span>Other Upcoming Schedules ({{ otherReminders.length }})</span>
                            <span class="text-[10px] text-gray-400 font-normal">Scroll to oversee</span>
                        </div>

                        <!-- Scrollable List Container with max-height -->
                        <div v-if="otherReminders.length > 0" class="max-h-40 overflow-y-auto space-y-1.5 pr-1 divide-y divide-gray-100">
                            <div
                                v-for="item in otherReminders"
                                :key="item.id"
                                class="pt-1.5 first:pt-0 flex items-start justify-between gap-2 text-xs hover:bg-gray-50/80 p-1.5 rounded-xs transition"
                            >
                                <div class="flex items-start gap-2 min-w-0 flex-1">
                                    <button
                                        type="button"
                                        @click="toggleReminderDone(item)"
                                        :disabled="togglingReminderId === item.id"
                                        class="mt-0.5 w-3.5 h-3.5 rounded border flex items-center justify-center text-[9px] font-bold transition shrink-0"
                                        :class="item.isDone ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-gray-300 hover:border-blue-500 bg-white'"
                                        title="Mark completed"
                                    >
                                        <svg v-if="item.isDone" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    </button>

                                    <div class="space-y-0.5 min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span 
                                                v-if="item.audience === 'everyone'" 
                                                class="px-1 py-0.2 text-[8px] font-bold bg-purple-50 text-purple-700 border border-purple-200 rounded-xs inline-flex items-center gap-0.5"
                                            >
                                                <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                                <span>All</span>
                                            </span>
                                            <span 
                                                v-else 
                                                class="px-1 py-0.2 text-[8px] font-semibold bg-gray-50 text-gray-600 border border-gray-200 rounded-xs inline-flex items-center gap-0.5"
                                            >
                                                <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                <span>Personal</span>
                                            </span>

                                            <span class="px-1 py-0.2 text-[8px] font-bold uppercase border rounded-xs" :class="getReminderCategoryBadge(item.category)">
                                                {{ item.category }}
                                            </span>

                                            <h5 class="text-xs font-semibold text-gray-800 truncate" :class="{ 'line-through text-gray-400': item.isDone }">
                                                {{ item.title }}
                                            </h5>
                                        </div>

                                        <div class="flex items-center gap-2 text-[10px] text-gray-400 flex-wrap">
                                            <span class="font-semibold text-blue-700 inline-flex items-center gap-1">
                                                <svg class="w-2.5 h-2.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                {{ getRelativeScheduleLabel(item.startsAt) }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-gray-500">
                                                <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ formatTime(item.startsAt) }}
                                            </span>
                                            <span v-if="item.location" class="truncate max-w-[120px] inline-flex items-center gap-0.5 text-gray-400">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                {{ item.location }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- When only 1 reminder exists (which is pinned) -->
                        <div v-else class="py-2 text-center text-[11px] text-gray-400">
                            No other pending schedules. The next upcoming event is pinned above.
                        </div>
                    </div>

                </div>

                <!-- Footer link to Reminders Tab -->
                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs">
                    <span class="text-[11px] text-gray-500">Total active schedules: <strong>{{ activeReminders.length }}</strong></span>
                    <button
                        type="button"
                        @click="goToRemindersTab"
                        class="text-[11px] text-blue-700 hover:text-blue-900 font-bold transition flex items-center gap-1"
                    >
                        <span>Open Full Schedule</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

        </div>

        <!-- ======================================================== -->
        <!-- 3. MODAL: SLIDESHOW POPUP MODAL (ALL-WHITE CARD)         -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div 
                v-if="showPopupModal && popupSlide"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs p-4 animate-in fade-in duration-150"
                @click.self="showPopupModal = false"
            >
                <div class="bg-white max-w-xl w-full shadow-2xl border border-gray-200 overflow-hidden flex flex-col animate-in zoom-in-95 duration-200">
                    
                    <!-- Popup Header (Clean White) -->
                    <div class="px-5 py-3.5 bg-white text-gray-900 flex items-center justify-between border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded bg-red-50 text-red-700 border border-red-100 flex items-center justify-center font-bold text-xs shrink-0">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            </div>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-gray-900">Important Directives & Notes</h3>
                                <p class="text-[10px] text-gray-500">Slide {{ popupSlideIndex + 1 }} of {{ sortedDirectives.length }} (Highest Priority First)</p>
                            </div>
                        </div>

                        <button 
                            @click="showPopupModal = false"
                            class="text-gray-400 hover:text-gray-700 p-1 transition"
                            title="Close Popup"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Slide Body -->
                    <div class="p-5 space-y-3 overflow-y-auto max-h-[65vh] bg-white">
                        <!-- Badges -->
                        <div class="flex items-center justify-between gap-2 flex-wrap border-b border-gray-100 pb-2 text-xs">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span 
                                    class="px-2 py-0.5 text-[10px] uppercase rounded-xs"
                                    :class="getPriorityMeta(popupSlide.priority).badge"
                                >
                                    {{ getPriorityMeta(popupSlide.priority).label }}
                                </span>
                                <span 
                                    class="px-2 py-0.5 text-[10px] rounded-xs"
                                    :class="getStatusMeta(popupSlide.status).bg"
                                >
                                    {{ getStatusMeta(popupSlide.status).label }}
                                </span>
                            </div>

                            <span v-if="popupSlide.targetDeadline" class="text-[11px] font-bold" :class="isOverdue(popupSlide.targetDeadline, popupSlide.status) ? 'text-red-700' : 'text-gray-700'">
                                Target: {{ formatDate(popupSlide.targetDeadline) }}
                                <span v-if="isOverdue(popupSlide.targetDeadline, popupSlide.status)" class="ml-1 text-[9px] bg-red-50 text-red-700 border border-red-200 px-1 uppercase">OVERDUE</span>
                            </span>
                        </div>

                        <!-- Title & Project -->
                        <div class="space-y-1">
                            <h2 class="text-sm sm:text-base font-bold text-gray-900 leading-snug">{{ popupSlide.title }}</h2>
                            <p v-if="popupSlide.projectName" class="text-xs font-semibold text-red-700 inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                <span>Project: {{ popupSlide.projectName }}</span>
                            </p>
                        </div>

                        <!-- Admin Note -->
                        <div v-if="popupSlide.note" class="bg-white border border-gray-200 border-l-4 border-l-red-600 p-3 space-y-1">
                            <div class="flex items-center gap-1 text-[10px] font-bold text-gray-500 uppercase">
                                <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Instruction from Administration:</span>
                            </div>
                            <p class="text-xs text-gray-800 leading-relaxed whitespace-pre-line">
                                {{ popupSlide.note }}
                            </p>
                            <p class="text-[10px] text-gray-400 pt-0.5">
                                Issued by: {{ popupSlide.assignerName || 'Administration' }} • {{ formatDate(popupSlide.createdAt) }}
                            </p>
                        </div>

                        <!-- Staff Reply Preview -->
                        <div v-if="popupSlide.staffReply" class="bg-white border border-gray-200 border-l-4 border-l-emerald-600 p-3 text-xs space-y-0.5">
                            <div class="flex items-center justify-between text-[10px]">
                                <div class="flex items-center gap-1 font-bold text-emerald-800">
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    <span>Your Reply Note (Visible to Admin):</span>
                                </div>
                                <span v-if="popupSlide.staffRepliedAt" class="text-emerald-600">{{ popupSlide.staffRepliedAt }}</span>
                            </div>
                            <p class="text-gray-700 whitespace-pre-line leading-relaxed">{{ popupSlide.staffReply }}</p>
                        </div>
                    </div>

                    <!-- Popup Footer / Controls -->
                    <div class="px-5 py-3 bg-white border-t border-gray-100 flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="prevPopupSlide"
                                :disabled="sortedDirectives.length <= 1"
                                class="px-2.5 py-1 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-xs font-bold disabled:opacity-30 transition inline-flex items-center gap-1"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                <span>Prev</span>
                            </button>

                            <button
                                type="button"
                                @click="nextPopupSlide"
                                :disabled="sortedDirectives.length <= 1"
                                class="px-2.5 py-1 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-xs font-bold disabled:opacity-30 transition inline-flex items-center gap-1"
                            >
                                <span>Next</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="openReply(popupSlide)"
                                class="px-3 py-1.5 bg-white hover:bg-gray-50 text-emerald-700 border border-emerald-300 text-xs font-bold transition shadow-2xs inline-flex items-center gap-1"
                            >
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                <span>{{ popupSlide.staffReply ? 'Edit Reply' : 'Reply Note' }}</span>
                            </button>

                            <button
                                v-if="popupSlide.projectId"
                                type="button"
                                @click="goToProject(popupSlide.projectId)"
                                class="px-3 py-1.5 bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 text-xs font-bold transition shadow-2xs inline-flex items-center gap-1"
                            >
                                <svg class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                <span>View Project</span>
                            </button>

                            <button
                                type="button"
                                @click="showPopupModal = false"
                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition"
                            >
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ======================================================== -->
        <!-- 4. DIRECTIVE REPLY MODAL (ALL-WHITE)                     -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="showReplyModal && replyingDirective"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs p-4"
                @click.self="closeReply"
            >
                <div class="bg-white max-w-lg w-full shadow-2xl border border-gray-200 p-5 space-y-3.5 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between pb-2.5 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-emerald-50 text-emerald-700 border border-emerald-100 flex items-center justify-center font-bold text-xs rounded-xs">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-gray-900">Reply / Note to Admin</h3>
                                <p class="text-[10px] text-gray-500">Provide field updates or completion notes</p>
                            </div>
                        </div>
                        <button @click="closeReply" class="text-gray-400 hover:text-gray-600 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="bg-white p-2.5 border border-gray-200 border-l-4 border-l-emerald-600 space-y-0.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-800">{{ replyingDirective.title }}</span>
                            <span class="text-[9px] uppercase font-bold px-1 py-0.2 bg-gray-100 text-gray-700">
                                {{ replyingDirective.priority || 'normal' }}
                            </span>
                        </div>
                        <p v-if="replyingDirective.note" class="text-gray-600 text-[11px] italic line-clamp-2">
                            "{{ replyingDirective.note }}"
                        </p>
                    </div>

                    <form @submit.prevent="handleSendReply" class="space-y-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-gray-700">Current Task Status</label>
                            <select
                                v-model="replyForm.status"
                                class="w-full text-xs font-semibold border border-gray-300 p-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                            >
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed (Mark Task as Done)</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-gray-700">Your Reply / Field Note *</label>
                            <textarea
                                v-model="replyForm.staff_reply"
                                required
                                rows="3"
                                placeholder="Write your field update, remarks, queries, or completion notes for the admin..."
                                class="w-full text-xs border border-gray-300 p-2 focus:ring-emerald-500 focus:border-emerald-500 leading-relaxed bg-white"
                            ></textarea>
                        </div>

                        <div class="pt-2.5 border-t border-gray-100 flex items-center justify-end gap-2 text-xs">
                            <button
                                type="button"
                                @click="closeReply"
                                class="px-3 py-1.5 font-semibold text-gray-600 hover:bg-gray-100 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingReply"
                                class="px-4 py-1.5 font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1"
                            >
                                <svg v-if="isSubmittingReply" class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75"></path></svg>
                                <span>{{ isSubmittingReply ? 'Posting...' : 'Post Reply' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </div>
</template>
