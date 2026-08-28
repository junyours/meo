<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
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
const activeFilter = ref('all'); // 'all' | 'reminders' | 'projects' | 'bulletins' | 'assignments' | 'inquiries'

const reminders = ref([]);
const bulletins = ref([]);
const assignments = ref([]);
const inquiries = ref([]);
const loading = ref(false);

const readIds = ref(new Set());

// Dynamic Storage Key unique to each authenticated user
const getStorageKey = () => `meo_notifications_read_${currentUser.value?.id || 'guest'}`;
const getSoundKey = () => `meo_notifications_sound_${currentUser.value?.id || 'guest'}`;

const soundEnabled = ref(false);
let previousUnreadCount = 0;

// Synthesized subtle notification chime
const playNotificationChime = () => {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(659.25, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.06, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.22);
    } catch (e) {}
};

const toggleSound = () => {
    soundEnabled.value = !soundEnabled.value;
    try {
        localStorage.setItem(getSoundKey(), soundEnabled.value ? 'true' : 'false');
    } catch (e) {}
    if (soundEnabled.value) {
        playNotificationChime();
    }
};

// Load saved read IDs from localStorage for the current user
const loadReadIds = () => {
    try {
        const stored = localStorage.getItem(getStorageKey());
        if (stored) {
            readIds.value = new Set(JSON.parse(stored));
        } else {
            readIds.value = new Set();
        }

        const storedSound = localStorage.getItem(getSoundKey());
        soundEnabled.value = storedSound === 'true';
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
let notificationPollTimer = null;

const fetchNotificationsData = async () => {
    loading.value = true;
    try {
        const params = isStaff.value && currentUser.value?.id ? { user_id: currentUser.value.id } : {};

        const requests = [
            axios.get('/reminders'),
            axios.get('/bulletins'),
            axios.get('/staff-assignments', { params }),
            axios.get('/inquiries'),
        ];

        const [remindersRes, bulletinsRes, assignmentsRes, inquiriesRes] = await Promise.allSettled(requests);

        if (remindersRes.status === 'fulfilled' && Array.isArray(remindersRes.value.data)) {
            reminders.value = remindersRes.value.data;
        }

        if (bulletinsRes.status === 'fulfilled' && Array.isArray(bulletinsRes.value.data)) {
            bulletins.value = bulletinsRes.value.data;
        }

        if (assignmentsRes.status === 'fulfilled' && assignmentsRes.value.data?.assignments && Array.isArray(assignmentsRes.value.data.assignments)) {
            assignments.value = assignmentsRes.value.data.assignments;
        }

        if (inquiriesRes && inquiriesRes.status === 'fulfilled' && Array.isArray(inquiriesRes.value.data)) {
            inquiries.value = inquiriesRes.value.data;
        }
    } catch (err) {
        console.error('Failed to load notification items', err);
    } finally {
        loading.value = false;
    }
};

const silentSyncNotificationsData = async () => {
    if (typeof document !== 'undefined' && document.hidden) return;
    if (loading.value) return;

    try {
        const params = isStaff.value && currentUser.value?.id ? { user_id: currentUser.value.id } : {};

        const requests = [
            axios.get('/reminders'),
            axios.get('/bulletins'),
            axios.get('/staff-assignments', { params }),
            axios.get('/inquiries'),
        ];

        const [remindersRes, bulletinsRes, assignmentsRes, inquiriesRes] = await Promise.allSettled(requests);

        if (remindersRes.status === 'fulfilled' && Array.isArray(remindersRes.value.data)) {
            reminders.value = remindersRes.value.data;
        }

        if (bulletinsRes.status === 'fulfilled' && Array.isArray(bulletinsRes.value.data)) {
            bulletins.value = bulletinsRes.value.data;
        }

        if (assignmentsRes.status === 'fulfilled' && assignmentsRes.value.data?.assignments && Array.isArray(assignmentsRes.value.data.assignments)) {
            assignments.value = assignmentsRes.value.data.assignments;
        }

        if (inquiriesRes && inquiriesRes.status === 'fulfilled' && Array.isArray(inquiriesRes.value.data)) {
            inquiries.value = inquiriesRes.value.data;
        }

        // Chime if unread count increased and sound is enabled
        const currentUnread = unreadCount.value;
        if (soundEnabled.value && currentUnread > previousUnreadCount && previousUnreadCount > 0) {
            playNotificationChime();
        }
        previousUnreadCount = currentUnread;
    } catch (err) {
        // Silent error
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

        // Staff user: see assignments, deadlines, notes, and admin discussion messages
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
                assignmentItem: asgn,
            });

            // Parse conversation messages from Admin
            if (Array.isArray(asgn.conversation) && asgn.conversation.length > 0) {
                const adminMsgs = asgn.conversation.filter(m => m.sender_role && m.sender_role.toLowerCase() !== 'staff' && Number(m.sender_id) !== currentUserId);
                if (adminMsgs.length > 0) {
                    const latestAdminMsg = adminMsgs[adminMsgs.length - 1];
                    const msgNotifId = `asgn-msg-${asgn.id}-${latestAdminMsg.id || latestAdminMsg.created_at || adminMsgs.length}`;
                    list.push({
                        id: msgNotifId,
                        rawId: asgn.id,
                        type: 'assignments',
                        typeLabel: 'Discussion',
                        title: `Message from ${latestAdminMsg.sender_name || 'Admin'}: ${asgn.projectName || asgn.title}`,
                        description: latestAdminMsg.message,
                        category: 'Directive Message',
                        time: latestAdminMsg.created_at || 'Recent',
                        timestamp: latestAdminMsg.created_at ? new Date(latestAdminMsg.created_at).getTime() : now.getTime(),
                        isUrgent: false,
                        isRead: readIds.value.has(msgNotifId),
                        targetTab: 'projects',
                        tabName: 'My Projects',
                        assignmentItem: asgn,
                    });
                }
            }
        } 
        // Admin / Superadmin user: see staff replies, discussion messages, and overdue items
        else if (isAdminOrSuperadmin.value) {
            const hasReply = !!asgn.staffReply;
            const isOverdue = asgn.targetDeadline && new Date(asgn.targetDeadline) < now;

            if (hasReply) {
                const notifId = `admin-asgn-${asgn.id}-reply`;
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
                    assignmentItem: asgn,
                });
            }

            // Parse conversation messages from Staff
            if (Array.isArray(asgn.conversation) && asgn.conversation.length > 0) {
                const staffMsgs = asgn.conversation.filter(m => (!m.sender_role || m.sender_role.toLowerCase() === 'staff') && Number(m.sender_id) !== currentUserId);
                if (staffMsgs.length > 0) {
                    const latestStaffMsg = staffMsgs[staffMsgs.length - 1];
                    const msgNotifId = `admin-asgn-msg-${asgn.id}-${latestStaffMsg.id || latestStaffMsg.created_at || staffMsgs.length}`;
                    list.push({
                        id: msgNotifId,
                        rawId: asgn.id,
                        type: 'assignments',
                        typeLabel: 'Staff Message',
                        title: `Message from ${latestStaffMsg.sender_name || asgn.userName || 'Staff'}: ${asgn.projectName || asgn.title}`,
                        description: latestStaffMsg.message,
                        category: 'Discussion',
                        time: latestStaffMsg.created_at || 'Recent',
                        timestamp: latestStaffMsg.created_at ? new Date(latestStaffMsg.created_at).getTime() : now.getTime(),
                        isUrgent: false,
                        isRead: readIds.value.has(msgNotifId),
                        targetTab: 'staff',
                        tabName: 'Staff Directory',
                        assignmentItem: asgn,
                    });
                }
            }

            if (isOverdue) {
                const notifId = `admin-asgn-${asgn.id}-overdue`;
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
                    assignmentItem: asgn,
                });
            }
        }
    });

    // 5. Citizen Inquiries (For Admin, Superadmin & Staff)
    inquiries.value.forEach(inq => {
        if (inq.status === 'pending') {
            const notifId = `inquiry-${inq.id}`;
            list.push({
                id: notifId,
                rawId: inq.id,
                type: 'inquiries',
                typeLabel: 'Citizen Concern',
                title: `New Inquiry: ${inq.subject || 'Citizen Question'}`,
                description: `From ${inq.fullname}: ${(inq.message || '').substring(0, 90)}${(inq.message || '').length > 90 ? '...' : ''}`,
                category: 'Pending Concern',
                time: inq.created_at_relative || 'Pending',
                timestamp: inq.createdAt ? new Date(inq.createdAt).getTime() : now.getTime(),
                isUrgent: true,
                isRead: readIds.value.has(notifId),
                targetTab: 'messages',
                tabName: 'Messages Tab',
                inquiryItem: inq,
            });
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

const unreadCount = computed(() => {
    return notifications.value.filter(n => !n.isRead).length;
});

const remindersCount = computed(() => notifications.value.filter(n => n.type === 'reminders').length);
const projectsCount = computed(() => notifications.value.filter(n => n.type === 'projects').length);
const bulletinsCount = computed(() => notifications.value.filter(n => n.type === 'bulletins').length);
const assignmentsCount = computed(() => notifications.value.filter(n => n.type === 'assignments').length);
const inquiriesCount = computed(() => notifications.value.filter(n => n.type === 'inquiries').length);

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
    router.visit('/notifications');
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

    if (notif.targetTab) {
        const roleKey = userRole.value === 'superadmin' 
            ? 'meo_superadmin_active_tab' 
            : (userRole.value === 'admin' ? 'meo_admin_active_tab' : 'meo_staff_active_tab');
        localStorage.setItem(roleKey, notif.targetTab);
        localStorage.setItem('meo_active_tab', notif.targetTab);

        emit('navigate-tab', notif.targetTab);

        const currentPath = window.location.pathname;
        if (!currentPath.includes('/dashboard')) {
            const dashboardPath = userRole.value === 'superadmin' 
                ? '/superadmin/dashboard' 
                : (userRole.value === 'admin' ? '/admin/dashboard' : '/staff/dashboard');
            router.visit(dashboardPath);
        }
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
    if (notificationPollTimer) clearInterval(notificationPollTimer);
    notificationPollTimer = setInterval(silentSyncNotificationsData, 8000);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('keydown', handleKeyDown);
    if (notificationPollTimer) {
        clearInterval(notificationPollTimer);
        notificationPollTimer = null;
    }
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
    <div class="relative inline-block z-40" ref="dropdownRef">
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
                class="absolute right-0 mt-2 w-80 sm:w-[380px] h-[430px] max-h-[85vh] rounded-xl bg-white shadow-2xl ring-1 ring-black/5 border border-slate-200 z-50 overflow-hidden flex flex-col text-slate-800"
            >
                <!-- 1. Top Header (Fixed Height) -->
                <div class="px-3.5 py-2.5 border-b border-slate-100 bg-slate-50/90 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="p-1 rounded-md bg-red-50 text-red-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <h3 class="text-xs font-bold text-slate-900">Notifications</h3>
                        <span
                            v-if="unreadCount > 0"
                            class="px-1.5 py-0.2 text-[9px] font-bold bg-red-100 text-red-700 rounded-full"
                        >
                            {{ unreadCount }} new
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <!-- Sound Toggle -->
                        <button
                            type="button"
                            @click="toggleSound"
                            class="p-1 rounded-md transition-colors"
                            :class="soundEnabled ? 'text-emerald-700 hover:bg-emerald-50' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'"
                            :title="soundEnabled ? 'Sound alert enabled (Click to mute)' : 'Sound alert muted (Click to enable)'"
                        >
                            <svg v-if="soundEnabled" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                            </svg>
                        </button>

                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            @click="markAllAsRead"
                            class="text-[11px] font-semibold text-red-700 hover:text-red-800 hover:underline px-1.5 py-0.5 rounded transition-colors"
                        >
                            Mark all read
                        </button>
                        <button
                            type="button"
                            @click="closeDropdown"
                            class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-200/60 transition-colors"
                            aria-label="Close notifications"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 2. Filter Tabs (Fixed Height, Horizontal Scrollable) -->
                <div class="flex items-center gap-1 px-2.5 py-1.5 border-b border-slate-100 bg-white overflow-x-auto text-[11px] shrink-0 no-scrollbar">
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
                    <button
                        v-if="isAdminOrSuperadmin && inquiriesCount > 0"
                        type="button"
                        @click="activeFilter = 'inquiries'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 text-xs',
                            activeFilter === 'inquiries'
                                ? 'bg-red-700 text-white font-semibold shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800'
                        ]"
                    >
                        Inquiries ({{ inquiriesCount }})
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
                                item.type === 'inquiries' ? 'bg-rose-100 text-rose-700' :
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

                            <!-- Citizen Inquiries Icon -->
                            <svg v-else-if="item.type === 'inquiries'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
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
                                        item.type === 'inquiries' ? 'bg-rose-50 text-rose-700 border border-rose-200' :
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
