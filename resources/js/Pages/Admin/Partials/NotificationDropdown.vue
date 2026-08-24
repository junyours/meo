<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    projects: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['navigate-tab', 'open-project']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);
const userRole = computed(() => currentUser.value?.role || 'staff');
const isStaff = computed(() => userRole.value === 'staff');
const isAdminOrSuperadmin = computed(() => userRole.value === 'admin' || userRole.value === 'superadmin');

const isOpen = ref(false);
const showViewAllModal = ref(false);
const activeFilter = ref('all'); // 'all' | 'reminders' | 'projects' | 'bulletins' | 'assignments'
const modalActiveFilter = ref('all');
const modalReadFilter = ref('all'); // 'all' | 'unread' | 'read'
const modalSearchQuery = ref('');

const reminders = ref([]);
const bulletins = ref([]);
const assignments = ref([]);
const loading = ref(false);

const readIds = ref(new Set());

// Dynamic Storage Key unique to each authenticated user
const getStorageKey = () => `meo_notifications_read_${currentUser.value?.id || 'guest'}`;

// Load saved read IDs from localStorage for the current user
const loadReadIds = () => {
    try {
        const stored = localStorage.getItem(getStorageKey());
        if (stored) {
            readIds.value = new Set(JSON.parse(stored));
        } else {
            readIds.value = new Set();
        }
    } catch (e) {
        readIds.value = new Set();
    }
};

const saveReadIds = () => {
    try {
        localStorage.setItem(getStorageKey(), JSON.stringify(Array.from(readIds.value)));
    } catch (e) {}
};

// Fetch data from API endpoints
const fetchNotificationsData = async () => {
    loading.value = true;
    try {
        const params = isStaff.value && currentUser.value?.id ? { user_id: currentUser.value.id } : {};

        const [remindersRes, bulletinsRes, assignmentsRes] = await Promise.allSettled([
            axios.get('/reminders'),
            axios.get('/bulletins'),
            axios.get('/staff-assignments', { params }),
        ]);

        if (remindersRes.status === 'fulfilled' && Array.isArray(remindersRes.value.data)) {
            reminders.value = remindersRes.value.data;
        }

        if (bulletinsRes.status === 'fulfilled' && Array.isArray(bulletinsRes.value.data)) {
            bulletins.value = bulletinsRes.value.data;
        }

        if (assignmentsRes.status === 'fulfilled' && assignmentsRes.value.data?.assignments && Array.isArray(assignmentsRes.value.data.assignments)) {
            assignments.value = assignmentsRes.value.data.assignments;
        }
    } catch (err) {
        console.error('Failed to load notification items', err);
    } finally {
        loading.value = false;
    }
};

// Build list of all notifications tailored for each user role
const notifications = computed(() => {
    const list = [];
    const now = new Date();
    const currentUserId = Number(currentUser.value?.id || 0);

    // 1. Reminders (upcoming, today, or pending)
    reminders.value.forEach(item => {
        if (item.isDone) return;

        // Staff sees reminders assigned to everyone or created by/assigned to them
        if (isStaff.value && item.audience !== 'everyone' && Number(item.userId || item.user_id) !== currentUserId) {
            return;
        }

        const startsAt = item.startsAt ? new Date(item.startsAt) : null;
        let timeLabel = 'Upcoming';
        let isUrgent = false;

        if (startsAt) {
            const isToday = startsAt.toDateString() === now.toDateString();
            const isPast = startsAt < now;
            const diffDays = Math.ceil((startsAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

            if (isPast) {
                timeLabel = 'Overdue';
                isUrgent = true;
            } else if (isToday) {
                timeLabel = `Today at ${startsAt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
                isUrgent = true;
            } else if (diffDays === 1) {
                timeLabel = `Tomorrow at ${startsAt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
            } else {
                timeLabel = startsAt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
        }

        const notifId = `reminder-${item.id}`;
        list.push({
            id: notifId,
            rawId: item.id,
            type: 'reminders',
            typeLabel: 'Reminder',
            title: item.title,
            description: item.description || (item.location ? `Location: ${item.location}` : `Category: ${item.category}`),
            category: item.category || 'Reminder',
            time: timeLabel,
            timestamp: startsAt ? startsAt.getTime() : now.getTime(),
            isUrgent,
            isRead: readIds.value.has(notifId),
            targetTab: 'reminders',
            tabName: 'Reminders Tab',
        });
    });

    // 2. Project Alerts (delayed, suspended, pending tech prep)
    const projectsList = Array.isArray(props.projects) ? props.projects : [];
    projectsList.forEach(proj => {
        const rawStatus = (proj.status || '').toString().toLowerCase();
        const isDelayed = rawStatus.includes('delay') || proj.status === 2;
        const isSuspended = rawStatus.includes('suspend') || proj.status === 4;

        if (isDelayed) {
            const notifId = `project-delay-${proj.id}`;
            list.push({
                id: notifId,
                rawId: proj.id,
                type: 'projects',
                typeLabel: 'Project Alert',
                title: proj.title || proj.name || 'Project Delayed',
                description: `Project is marked as Delayed (${proj.progress || proj.accomplishment || 0}% accomplishment).`,
                category: 'Delayed Alert',
                time: proj.location || 'Municipal Project',
                timestamp: now.getTime() - 1000 * 60 * 60 * 2,
                isUrgent: true,
                isRead: readIds.value.has(notifId),
                targetTab: 'projects',
                tabName: isStaff.value ? 'My Projects' : 'Projects Tab',
                projectItem: proj,
            });
        } else if (isSuspended) {
            const notifId = `project-suspend-${proj.id}`;
            list.push({
                id: notifId,
                rawId: proj.id,
                type: 'projects',
                typeLabel: 'Project Alert',
                title: proj.title || proj.name || 'Project Suspended',
                description: `Project is currently suspended${proj.daysSuspensionOrder ? ` (${proj.daysSuspensionOrder} days order)` : ''}.`,
                category: 'Suspension',
                time: proj.location || 'Municipal Project',
                timestamp: now.getTime() - 1000 * 60 * 60 * 4,
                isUrgent: true,
                isRead: readIds.value.has(notifId),
                targetTab: 'projects',
                tabName: isStaff.value ? 'My Projects' : 'Projects Tab',
                projectItem: proj,
            });
        }

        // Check if there are critical pending tech prep items (for Admin/Superadmin)
        if (isAdminOrSuperadmin.value && proj.technical_preparations) {
            const tp = proj.technical_preparations;
            const issues = Object.entries(tp).filter(([_, val]) => val?.status === 'red' || val?.status === 'yellow');
            if (issues.length > 0 && !isDelayed && !isSuspended) {
                const notifId = `project-tp-${proj.id}`;
                list.push({
                    id: notifId,
                    rawId: proj.id,
                    type: 'projects',
                    typeLabel: 'Project Alert',
                    title: proj.title || proj.name || 'Tech Prep Pending',
                    description: `${issues.length} technical preparation requirement(s) pending or flagged.`,
                    category: 'Tech Prep',
                    time: proj.location || 'Preparations',
                    timestamp: now.getTime() - 1000 * 60 * 60 * 12,
                    isUrgent: false,
                    isRead: readIds.value.has(notifId),
                    targetTab: 'projects',
                    tabName: 'Projects Tab',
                    projectItem: proj,
                });
            }
        }
    });

    // 3. Bulletins / Announcements (Everyone)
    bulletins.value.forEach(bulletin => {
        if (bulletin.isArchived) return;

        const notifId = `bulletin-${bulletin.id}`;
        list.push({
            id: notifId,
            rawId: bulletin.id,
            type: 'bulletins',
            typeLabel: 'Bulletin',
            title: bulletin.title,
            description: bulletin.summary || 'New bulletin announcement posted.',
            category: bulletin.category || 'Announcement',
            time: bulletin.date || 'Notice',
            timestamp: bulletin.date ? new Date(bulletin.date).getTime() : now.getTime(),
            isUrgent: false,
            isRead: readIds.value.has(notifId),
            targetTab: 'bulletin',
            tabName: 'Bulletin Tab',
        });
    });

    // 4. Staff Assignments, Deadlines & Directives (Role-based filtering)
    assignments.value.forEach(asgn => {
        if (asgn.status === 'completed' || asgn.status === 'cancelled') return;

        const asgnUserId = Number(asgn.userId || asgn.user_id);
        const isAssignedToCurrent = asgnUserId === currentUserId;

        // Staff user: only see items assigned to them
        if (isStaff.value) {
            if (!isAssignedToCurrent) return;

            const isUrgent = (asgn.priority || '').toLowerCase() === 'urgent' || (asgn.priority || '').toLowerCase() === 'high';
            const notifId = `assignment-${asgn.id}`;
            let itemTitle = asgn.title || 'New Task Assignment';
            let itemDesc = asgn.note || (asgn.projectName ? `Project: ${asgn.projectName}` : 'New assignment pending action.');

            if (asgn.type === 'assignment') {
                itemTitle = asgn.projectName ? `Project Assigned: ${asgn.projectName}` : asgn.title;
                itemDesc = asgn.roleInProject ? `Role: ${asgn.roleInProject}. ${asgn.note || ''}` : asgn.note || 'You have been assigned to this project.';
            } else if (asgn.type === 'deadline') {
                itemTitle = `Target Deadline: ${asgn.title}`;
            } else if (asgn.type === 'note') {
                itemTitle = `Admin Directive: ${asgn.title}`;
            }

            list.push({
                id: notifId,
                rawId: asgn.id,
                type: 'assignments',
                typeLabel: asgn.type === 'assignment' ? 'Assignment' : (asgn.type === 'deadline' ? 'Deadline' : 'Directive'),
                title: itemTitle,
                description: itemDesc,
                category: (asgn.priority ? asgn.priority.toUpperCase() : 'Directive'),
                time: asgn.targetDeadline ? `Due: ${new Date(asgn.targetDeadline).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}` : 'Active',
                timestamp: asgn.createdAt ? new Date(asgn.createdAt).getTime() : now.getTime(),
                isUrgent,
                isRead: readIds.value.has(notifId),
                targetTab: 'projects',
                tabName: 'My Projects',
            });
        } 
        // Admin / Superadmin user: see staff replies and overdue items
        else if (isAdminOrSuperadmin.value) {
            const hasReply = !!asgn.staffReply;
            const isOverdue = asgn.targetDeadline && new Date(asgn.targetDeadline) < now;
            const notifId = `admin-asgn-${asgn.id}${hasReply ? '-reply' : ''}`;

            if (hasReply) {
                list.push({
                    id: notifId,
                    rawId: asgn.id,
                    type: 'assignments',
                    typeLabel: 'Staff Reply',
                    title: `Reply from ${asgn.userName || 'Staff'}: ${asgn.projectName || asgn.title}`,
                    description: asgn.staffReply,
                    category: 'Staff Reply',
                    time: asgn.staffRepliedAt || 'Recently',
                    timestamp: asgn.staffRepliedAt ? new Date(asgn.staffRepliedAt).getTime() : now.getTime(),
                    isUrgent: false,
                    isRead: readIds.value.has(notifId),
                    targetTab: 'staff',
                    tabName: 'Staff Directory',
                });
            } else if (isOverdue) {
                list.push({
                    id: notifId,
                    rawId: asgn.id,
                    type: 'assignments',
                    typeLabel: 'Overdue Task',
                    title: `Overdue: ${asgn.title} (${asgn.userName || 'Staff'})`,
                    description: asgn.note || `Target deadline was ${new Date(asgn.targetDeadline).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}.`,
                    category: 'Overdue',
                    time: `Due: ${new Date(asgn.targetDeadline).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`,
                    timestamp: asgn.targetDeadline ? new Date(asgn.targetDeadline).getTime() : now.getTime(),
                    isUrgent: true,
                    isRead: readIds.value.has(notifId),
                    targetTab: 'staff',
                    tabName: 'Staff Directory',
                });
            }
        }
    });

    // Sort: unread & urgent first, then by timestamp descending
    return list.sort((a, b) => {
        if (a.isRead !== b.isRead) return a.isRead ? 1 : -1;
        if (a.isUrgent !== b.isUrgent) return a.isUrgent ? -1 : 1;
        return b.timestamp - a.timestamp;
    });
});

const filteredNotifications = computed(() => {
    if (activeFilter.value === 'all') return notifications.value;
    return notifications.value.filter(n => n.type === activeFilter.value);
});

// Filtered list for "View All" Modal
const modalFilteredNotifications = computed(() => {
    let list = notifications.value;

    // Type filter
    if (modalActiveFilter.value !== 'all') {
        list = list.filter(n => n.type === modalActiveFilter.value);
    }

    // Read status filter
    if (modalReadFilter.value === 'unread') {
        list = list.filter(n => !n.isRead);
    } else if (modalReadFilter.value === 'read') {
        list = list.filter(n => n.isRead);
    }

    // Search query filter
    if (modalSearchQuery.value.trim()) {
        const q = modalSearchQuery.value.trim().toLowerCase();
        list = list.filter(n =>
            (n.title && n.title.toLowerCase().includes(q)) ||
            (n.description && n.description.toLowerCase().includes(q)) ||
            (n.category && n.category.toLowerCase().includes(q)) ||
            (n.typeLabel && n.typeLabel.toLowerCase().includes(q))
        );
    }

    return list;
});

const unreadCount = computed(() => {
    return notifications.value.filter(n => !n.isRead).length;
});

const remindersCount = computed(() => notifications.value.filter(n => n.type === 'reminders').length);
const projectsCount = computed(() => notifications.value.filter(n => n.type === 'projects').length);
const bulletinsCount = computed(() => notifications.value.filter(n => n.type === 'bulletins').length);
const assignmentsCount = computed(() => notifications.value.filter(n => n.type === 'assignments').length);

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotificationsData();
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

const openViewAll = () => {
    closeDropdown();
    modalActiveFilter.value = activeFilter.value;
    modalSearchQuery.value = '';
    showViewAllModal.value = true;
};

const closeViewAll = () => {
    showViewAllModal.value = false;
};

const markAsRead = (notif, event) => {
    if (event) event.stopPropagation();
    readIds.value.add(notif.id);
    saveReadIds();
};

const markAsUnread = (notif, event) => {
    if (event) event.stopPropagation();
    readIds.value.delete(notif.id);
    saveReadIds();
};

const markAllAsRead = () => {
    notifications.value.forEach(n => readIds.value.add(n.id));
    saveReadIds();
};

const markAllAsUnread = () => {
    readIds.value.clear();
    saveReadIds();
};

const handleNotificationClick = (notif) => {
    markAsRead(notif);
    closeDropdown();
    closeViewAll();

    if (notif.targetTab) {
        emit('navigate-tab', notif.targetTab);
    }
};

// Format timestamp for display
const formatNotificationDate = (ts) => {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

// Click outside handling
const dropdownRef = ref(null);
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        closeDropdown();
    }
};

// Keyboard listener for Escape key
const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
        if (showViewAllModal.value) {
            closeViewAll();
        } else if (isOpen.value) {
            closeDropdown();
        }
    }
};

onMounted(() => {
    loadReadIds();
    fetchNotificationsData();
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('keydown', handleKeyDown);
});

watch(() => props.projects, () => {
    // Reactively update projects alerts if props change
}, { deep: true });

watch(currentUser, () => {
    loadReadIds();
    fetchNotificationsData();
});
</script>

<template>
    <div class="relative inline-block" ref="dropdownRef">
        <!-- Notification Trigger Button -->
        <button
            type="button"
            @click="toggleDropdown"
            class="relative inline-flex items-center justify-center p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-150"
            :class="{ 'bg-slate-100 text-slate-900 shadow-inner': isOpen }"
            aria-label="View notifications"
            :aria-expanded="isOpen"
        >
            <!-- Bell Icon -->
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>

            <!-- Badge Count -->
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold text-white shadow-sm ring-2 ring-white"
            >
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
            </span>
        </button>

        <!-- Notification Dropdown Panel: Fixed Stable Container Height (h-[460px]) -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 sm:w-[410px] h-[460px] max-h-[85vh] rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 border border-slate-200 z-50 overflow-hidden flex flex-col text-slate-800"
            >
                <!-- 1. Top Header (Fixed Height) -->
                <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/90 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="p-1 rounded-lg bg-red-50 text-red-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Notifications</h3>
                        <span
                            v-if="unreadCount > 0"
                            class="px-2 py-0.5 text-[10px] font-bold bg-red-100 text-red-700 rounded-full"
                        >
                            {{ unreadCount }} new
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            @click="markAllAsRead"
                            class="text-xs font-semibold text-red-700 hover:text-red-800 hover:underline px-2 py-0.5 rounded transition-colors"
                        >
                            Mark all read
                        </button>
                        <button
                            type="button"
                            @click="closeDropdown"
                            class="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200/60 transition-colors"
                            aria-label="Close notifications"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 2. Filter Tabs (Fixed Height, Horizontal Scrollable) -->
                <div class="flex items-center gap-1 px-3 py-2 border-b border-slate-100 bg-white overflow-x-auto text-xs shrink-0 no-scrollbar">
                    <button
                        type="button"
                        @click="activeFilter = 'all'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 text-xs',
                            activeFilter === 'all'
                                ? 'bg-red-700 text-white font-semibold shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800'
                        ]"
                    >
                        All ({{ notifications.length }})
                    </button>
                    <button
                        type="button"
                        @click="activeFilter = 'reminders'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 text-xs',
                            activeFilter === 'reminders'
                                ? 'bg-red-700 text-white font-semibold shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800'
                        ]"
                    >
                        Reminders ({{ remindersCount }})
                    </button>
                    <button
                        type="button"
                        @click="activeFilter = 'projects'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 text-xs',
                            activeFilter === 'projects'
                                ? 'bg-red-700 text-white font-semibold shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800'
                        ]"
                    >
                        Projects ({{ projectsCount }})
                    </button>
                    <button
                        type="button"
                        @click="activeFilter = 'bulletins'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 text-xs',
                            activeFilter === 'bulletins'
                                ? 'bg-red-700 text-white font-semibold shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800'
                        ]"
                    >
                        Bulletin ({{ bulletinsCount }})
                    </button>
                    <button
                        v-if="assignmentsCount > 0"
                        type="button"
                        @click="activeFilter = 'assignments'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 text-xs',
                            activeFilter === 'assignments'
                                ? 'bg-red-700 text-white font-semibold shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800'
                        ]"
                    >
                        {{ isStaff ? 'Directives' : 'Staff Activity' }} ({{ assignmentsCount }})
                    </button>
                </div>

                <!-- 3. Notifications List Body (Takes remaining space, fixed scrollable) -->
                <div class="overflow-y-auto divide-y divide-slate-100 flex-1 min-h-0 bg-slate-50/40">
                    <!-- Loading state -->
                    <div v-if="loading && notifications.length === 0" class="h-full flex flex-col items-center justify-center p-6 text-center text-slate-400">
                        <div class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-red-600"></div>
                        <p class="mt-2 text-xs font-medium">Checking notifications...</p>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="filteredNotifications.length === 0" class="h-full flex flex-col items-center justify-center p-6 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-2">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-xs font-bold text-slate-700">No notifications in this filter</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">You are all caught up on alerts and updates.</p>
                    </div>

                    <!-- List of items -->
                    <div
                        v-for="item in filteredNotifications"
                        :key="item.id"
                        @click="handleNotificationClick(item)"
                        :class="[
                            'p-3 flex items-start gap-3 hover:bg-slate-100/80 transition-colors cursor-pointer group relative',
                            !item.isRead ? 'bg-red-50/30' : 'bg-white'
                        ]"
                    >
                        <!-- Unread Dot -->
                        <span
                            v-if="!item.isRead"
                            class="absolute left-1.5 top-4 h-2 w-2 rounded-full bg-red-600 ring-2 ring-red-100"
                        ></span>

                        <!-- Type Icon -->
                        <div
                            class="h-8.5 w-8.5 rounded-xl flex items-center justify-center shrink-0 ml-1 mt-0.5"
                            :class="[
                                item.type === 'reminders' ? 'bg-purple-100 text-purple-700' :
                                item.type === 'projects' ? 'bg-amber-100 text-amber-700' :
                                item.type === 'assignments' ? 'bg-emerald-100 text-emerald-700' :
                                'bg-blue-100 text-blue-700'
                            ]"
                        >
                            <!-- Reminders Icon -->
                            <svg v-if="item.type === 'reminders'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>

                            <!-- Project Alert Icon -->
                            <svg v-else-if="item.type === 'projects'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>

                            <!-- Directives / Assignment Icon -->
                            <svg v-else-if="item.type === 'assignments'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>

                            <!-- Bulletin Icon -->
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                            </svg>
                        </div>

                        <!-- Content Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1.5">
                                <span class="text-xs font-bold text-slate-900 truncate group-hover:text-red-700 transition-colors">
                                    {{ item.title }}
                                </span>
                                <span class="text-[10px] font-medium text-slate-400 shrink-0">
                                    {{ item.time }}
                                </span>
                            </div>

                            <p class="text-xs text-slate-600 line-clamp-2 mt-0.5">
                                {{ item.description }}
                            </p>

                            <div class="flex items-center gap-2 mt-1.5">
                                <span
                                    class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium"
                                    :class="[
                                        item.type === 'reminders' ? 'bg-purple-50 text-purple-700 border border-purple-200' :
                                        item.type === 'projects' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                        item.type === 'assignments' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                        'bg-blue-50 text-blue-700 border border-blue-200'
                                    ]"
                                >
                                    {{ item.category }}
                                </span>

                                <span v-if="item.isUrgent" class="inline-flex items-center gap-1 text-[10px] font-bold text-red-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                    Urgent
                                </span>
                            </div>
                        </div>

                        <!-- Action Button on Hover -->
                        <button
                            v-if="!item.isRead"
                            type="button"
                            @click="markAsRead(item, $event)"
                            class="opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-slate-600 rounded transition-opacity"
                            title="Mark as read"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 4. Footer Quick Navigation & "View All" Button (Fixed Height) -->
                <div class="p-2.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600 shrink-0">
                    <button
                        type="button"
                        @click="openViewAll"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg font-bold text-xs text-red-700 shadow-2xs hover:text-red-800 transition-colors"
                    >
                        <span>View All Notifications ({{ notifications.length }})</span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </transition>

        <!-- ======================================================== -->
        <!-- VIEW ALL NOTIFICATIONS MODAL (Comprehensive Dashboard)   -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="showViewAllModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6 animate-in fade-in duration-150"
                @click.self="closeViewAll"
            >
                <div class="bg-white max-w-3xl w-full h-[85vh] max-h-[750px] rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
                    <!-- Modal Header -->
                    <div class="px-5 py-4 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-red-50 text-red-700 border border-red-100 flex items-center justify-center font-bold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base sm:text-lg font-bold text-slate-900">All Notifications & Alerts</h2>
                                    <span v-if="unreadCount > 0" class="px-2 py-0.5 text-[11px] font-bold bg-red-100 text-red-700 rounded-full">
                                        {{ unreadCount }} Unread
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">System alerts, scheduled reminders, project flags, and directives</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="fetchNotificationsData"
                                class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition"
                                title="Refresh"
                            >
                                <svg class="h-4 w-4" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="closeViewAll"
                                class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition"
                                title="Close"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Search & Filter Bar -->
                    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 shrink-0">
                        <!-- Search Box -->
                        <div class="relative flex-1">
                            <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                v-model="modalSearchQuery"
                                placeholder="Search by title, description, category..."
                                class="w-full pl-9 pr-8 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                            />
                            <button
                                v-if="modalSearchQuery"
                                type="button"
                                @click="modalSearchQuery = ''"
                                class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Read Filter Selector & Actions -->
                        <div class="flex items-center gap-2">
                            <select
                                v-model="modalReadFilter"
                                class="bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 py-1.5 pl-2.5 pr-7 focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                <option value="all">All Read Status</option>
                                <option value="unread">Unread Only</option>
                                <option value="read">Read Only</option>
                            </select>

                            <button
                                v-if="unreadCount > 0"
                                type="button"
                                @click="markAllAsRead"
                                class="px-2.5 py-1.5 text-xs font-semibold bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg transition"
                            >
                                Mark All Read
                            </button>
                            <button
                                v-else-if="readIds.size > 0"
                                type="button"
                                @click="markAllAsUnread"
                                class="px-2.5 py-1.5 text-xs font-semibold bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg transition"
                            >
                                Mark All Unread
                            </button>
                        </div>
                    </div>

                    <!-- Category Filter Tabs -->
                    <div class="px-5 py-2.5 border-b border-slate-100 bg-white flex items-center gap-1.5 overflow-x-auto text-xs shrink-0 no-scrollbar">
                        <button
                            type="button"
                            @click="modalActiveFilter = 'all'"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-semibold transition-all shrink-0',
                                modalActiveFilter === 'all'
                                    ? 'bg-red-700 text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            All Categories ({{ notifications.length }})
                        </button>
                        <button
                            type="button"
                            @click="modalActiveFilter = 'reminders'"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-semibold transition-all shrink-0',
                                modalActiveFilter === 'reminders'
                                    ? 'bg-red-700 text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Reminders & Schedules ({{ remindersCount }})
                        </button>
                        <button
                            type="button"
                            @click="modalActiveFilter = 'projects'"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-semibold transition-all shrink-0',
                                modalActiveFilter === 'projects'
                                    ? 'bg-red-700 text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Projects ({{ projectsCount }})
                        </button>
                        <button
                            type="button"
                            @click="modalActiveFilter = 'bulletins'"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-semibold transition-all shrink-0',
                                modalActiveFilter === 'bulletins'
                                    ? 'bg-red-700 text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Bulletin ({{ bulletinsCount }})
                        </button>
                        <button
                            v-if="assignmentsCount > 0"
                            type="button"
                            @click="modalActiveFilter = 'assignments'"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-semibold transition-all shrink-0',
                                modalActiveFilter === 'assignments'
                                    ? 'bg-red-700 text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            {{ isStaff ? 'Directives & Deadlines' : 'Staff Activity' }} ({{ assignmentsCount }})
                        </button>
                    </div>

                    <!-- Modal Scrollable List Body -->
                    <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-slate-100 bg-slate-50/30 p-4 space-y-2">
                        <!-- Loading State -->
                        <div v-if="loading && notifications.length === 0" class="h-full flex flex-col items-center justify-center p-12 text-center text-slate-400">
                            <div class="inline-block h-8 w-8 animate-spin rounded-full border-3 border-slate-300 border-t-red-600"></div>
                            <p class="mt-3 text-xs font-medium">Loading all notifications...</p>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="modalFilteredNotifications.length === 0" class="h-full flex flex-col items-center justify-center p-12 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-800">No matching notifications</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-sm">No notification records matched your filter criteria or search query.</p>
                            <button
                                v-if="modalSearchQuery || modalActiveFilter !== 'all' || modalReadFilter !== 'all'"
                                type="button"
                                @click="modalSearchQuery = ''; modalActiveFilter = 'all'; modalReadFilter = 'all'"
                                class="mt-3 text-xs font-bold text-red-700 hover:underline"
                            >
                                Reset All Filters
                            </button>
                        </div>

                        <!-- List Items -->
                        <div
                            v-for="item in modalFilteredNotifications"
                            :key="item.id"
                            class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs hover:border-slate-300 hover:shadow-xs transition-all flex flex-col sm:flex-row items-start justify-between gap-3.5 relative"
                            :class="{ 'ring-1 ring-red-200/70 bg-red-50/15': !item.isRead }"
                        >
                            <!-- Left Indicator Dot -->
                            <span
                                v-if="!item.isRead"
                                class="absolute -left-1 top-4 h-2.5 w-2.5 rounded-full bg-red-600 ring-2 ring-white"
                                title="Unread notification"
                            ></span>

                            <!-- Main Content Block -->
                            <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                <!-- Type Icon -->
                                <div
                                    class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                                    :class="[
                                        item.type === 'reminders' ? 'bg-purple-100 text-purple-700' :
                                        item.type === 'projects' ? 'bg-amber-100 text-amber-700' :
                                        item.type === 'assignments' ? 'bg-emerald-100 text-emerald-700' :
                                        'bg-blue-100 text-blue-700'
                                    ]"
                                >
                                    <svg v-if="item.type === 'reminders'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else-if="item.type === 'projects'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <svg v-else-if="item.type === 'assignments'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                    </svg>
                                </div>

                                <!-- Item Texts -->
                                <div class="space-y-1 min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                            :class="[
                                                item.type === 'reminders' ? 'bg-purple-50 text-purple-700 border border-purple-200' :
                                                item.type === 'projects' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                                item.type === 'assignments' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                'bg-blue-50 text-blue-700 border border-blue-200'
                                            ]"
                                        >
                                            {{ item.typeLabel }}: {{ item.category }}
                                        </span>

                                        <span v-if="item.isUrgent" class="inline-flex items-center gap-1 text-[10px] font-bold text-red-600 bg-red-50 border border-red-200 px-1.5 py-0.5 rounded">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                            Urgent Attention
                                        </span>

                                        <span class="text-[11px] text-slate-400 ml-auto">
                                            {{ formatNotificationDate(item.timestamp) }}
                                        </span>
                                    </div>

                                    <h4 class="text-sm font-bold text-slate-900 leading-snug">
                                        {{ item.title }}
                                    </h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>

                            <!-- Right Actions & Jump Button -->
                            <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-2 shrink-0 self-stretch sm:self-auto pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                                <button
                                    type="button"
                                    @click="handleNotificationClick(item)"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white rounded-lg text-xs font-bold transition shadow-xs"
                                >
                                    <span>Open {{ item.tabName }}</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>

                                <button
                                    v-if="!item.isRead"
                                    type="button"
                                    @click="markAsRead(item, $event)"
                                    class="text-[11px] text-slate-500 hover:text-slate-800 font-semibold px-2 py-0.5 rounded hover:bg-slate-100 transition"
                                >
                                    Mark as read
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="markAsUnread(item, $event)"
                                    class="text-[11px] text-slate-400 hover:text-slate-700 font-medium px-2 py-0.5 rounded hover:bg-slate-100 transition"
                                >
                                    Mark as unread
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 shrink-0">
                        <span>Showing {{ modalFilteredNotifications.length }} of {{ notifications.length }} notifications</span>
                        <button
                            type="button"
                            @click="closeViewAll"
                            class="px-4 py-1.5 bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-semibold rounded-lg transition"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* Custom subtle scrollbar */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
