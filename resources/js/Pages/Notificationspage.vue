<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminSidebar from './Admin/Partials/Sidebar.vue';
import StaffSidebar from './Staff/Partials/Sidebar.vue';
import SuperadminSidebar from './Superadmin/Partials/Sidebar.vue';

const props = defineProps({
    initialProjects: {
        type: Array,
        default: () => [],
    },
    initialBulletins: {
        type: Array,
        default: () => [],
    },
    initialReminders: {
        type: Array,
        default: () => [],
    },
    initialAssignments: {
        type: Array,
        default: () => [],
    },
    initialInquiries: {
        type: Array,
        default: () => [],
    },
    initialSystemLogs: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);
const userRole = computed(() => currentUser.value?.role || 'staff');
const isStaff = computed(() => userRole.value === 'staff');
const isAdmin = computed(() => userRole.value === 'admin');
const isSuperadmin = computed(() => userRole.value === 'superadmin');
const isAdminOrSuperadmin = computed(() => isAdmin.value || isSuperadmin.value);

const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');

// State for dynamic notification sources
const projects = ref([...props.initialProjects]);
const bulletins = ref([...props.initialBulletins]);
const reminders = ref([...props.initialReminders]);
const assignments = ref([...props.initialAssignments]);
const inquiries = ref([...props.initialInquiries]);
const systemLogs = ref([...props.initialSystemLogs]);
const loading = ref(false);

// Read / Archived / Dismissed storage keys
const getStorageKey = () => `meo_notifications_read_${currentUser.value?.id || 'guest'}`;
const getArchivedKey = () => `meo_notifications_archived_${currentUser.value?.id || 'guest'}`;
const getDismissedKey = () => `meo_notifications_dismissed_${currentUser.value?.id || 'guest'}`;
const getSoundKey = () => `meo_notifications_sound_${currentUser.value?.id || 'guest'}`;

const readIds = ref(new Set());
const archivedIds = ref(new Set());
const dismissedIds = ref(new Set());
const soundEnabled = ref(localStorage.getItem(getSoundKey()) === 'true');

// View state: 'inbox' | 'archived'
const activeView = ref('inbox');

// Filtering & Search
const activeCategory = ref('all'); // 'all' | 'reminders' | 'projects' | 'bulletins' | 'assignments' | 'inquiries'
const readFilter = ref('all'); // 'all' | 'unread' | 'read'
const priorityFilter = ref('all'); // 'all' | 'urgent'
const sortBy = ref('newest'); // 'newest' | 'oldest' | 'urgent'
const searchQuery = ref('');

// Details Modal
const selectedNotification = ref(null);
const showDetailModal = ref(false);

// Toast Feedback State
const toast = ref({ show: false, message: '', type: 'success' });
let toastTimer = null;
const showToast = (message, type = 'success') => {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { show: true, message, type };
    toastTimer = setTimeout(() => {
        toast.value.show = false;
    }, 2500);
};

// Storage helpers
const loadSavedState = () => {
    try {
        const storedRead = localStorage.getItem(getStorageKey());
        if (storedRead) readIds.value = new Set(JSON.parse(storedRead));
        else readIds.value = new Set();

        const storedArchived = localStorage.getItem(getArchivedKey());
        if (storedArchived) archivedIds.value = new Set(JSON.parse(storedArchived));
        else archivedIds.value = new Set();

        const storedDismissed = localStorage.getItem(getDismissedKey());
        if (storedDismissed) dismissedIds.value = new Set(JSON.parse(storedDismissed));
        else dismissedIds.value = new Set();
    } catch (e) {
        readIds.value = new Set();
        archivedIds.value = new Set();
        dismissedIds.value = new Set();
    }
};

const saveReadIds = () => {
    try {
        localStorage.setItem(getStorageKey(), JSON.stringify(Array.from(readIds.value)));
    } catch (e) {}
};

const saveArchivedIds = () => {
    try {
        localStorage.setItem(getArchivedKey(), JSON.stringify(Array.from(archivedIds.value)));
    } catch (e) {}
};

const saveDismissedIds = () => {
    try {
        localStorage.setItem(getDismissedKey(), JSON.stringify(Array.from(dismissedIds.value)));
    } catch (e) {}
};

const toggleSound = () => {
    soundEnabled.value = !soundEnabled.value;
    try {
        localStorage.setItem(getSoundKey(), soundEnabled.value ? 'true' : 'false');
    } catch (e) {}
    if (soundEnabled.value) {
        playNotificationTone();
        showToast('Sound enabled', 'info');
    } else {
        showToast('Sound muted', 'info');
    }
};

// Synthesized subtle tone
const playNotificationTone = () => {
    try {   
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(659.25, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.05, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.22);
    } catch (e) {}
};

let previousUnreadCount = 0;
let pollTimer = null;

// Refresh Data
const refreshData = async () => {
    loading.value = true;
    try {
        const params = isStaff.value && currentUser.value?.id ? { user_id: currentUser.value.id } : {};
        const requests = [
            axios.get('/reminders'),
            axios.get('/bulletins'),
            axios.get('/staff-assignments', { params }),
            axios.get('/inquiries'),
        ];

        const responses = await Promise.allSettled(requests);

        if (responses[0].status === 'fulfilled' && Array.isArray(responses[0].value.data)) {
            reminders.value = responses[0].value.data;
        }
        if (responses[1].status === 'fulfilled' && Array.isArray(responses[1].value.data)) {
            bulletins.value = responses[1].value.data;
        }
        if (responses[2].status === 'fulfilled' && responses[2].value.data?.assignments) {
            assignments.value = responses[2].value.data.assignments;
        }
        if (responses[3] && responses[3].status === 'fulfilled' && Array.isArray(responses[3].value.data)) {
            inquiries.value = responses[3].value.data;
        }
        showToast('Updated', 'success');
    } catch (err) {
        showToast('Update failed', 'error');
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

        if (isSuperadmin.value) {
            requests.push(axios.get('/superadmin/activity-logs', { params: { severity: 'warning', per_page: 15 } }));
        }

        const responses = await Promise.allSettled(requests);

        if (responses[0].status === 'fulfilled' && Array.isArray(responses[0].value.data)) {
            reminders.value = responses[0].value.data;
        }
        if (responses[1].status === 'fulfilled' && Array.isArray(responses[1].value.data)) {
            bulletins.value = responses[1].value.data;
        }
        if (responses[2].status === 'fulfilled' && responses[2].value.data?.assignments) {
            assignments.value = responses[2].value.data.assignments;
        }
        if (responses[3] && responses[3].status === 'fulfilled' && Array.isArray(responses[3].value.data)) {
            inquiries.value = responses[3].value.data;
        }
        if (responses[4] && responses[4].status === 'fulfilled' && responses[4].value.data?.logs?.data) {
            systemLogs.value = responses[4].value.data.logs.data;
        }

        const currentUnread = unreadCount.value;
        if (soundEnabled.value && currentUnread > previousUnreadCount && previousUnreadCount > 0) {
            playNotificationTone();
        }
        previousUnreadCount = currentUnread;
    } catch (err) {
        // Silent error
    }
};

// Build all raw notifications
const rawNotifications = computed(() => {
    const list = [];
    const now = new Date();
    const currentUserId = Number(currentUser.value?.id || 0);

    // 1. Reminders
    reminders.value.forEach(item => {
        if (item.isDone) return;
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
                timeLabel = `Today ${startsAt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
                isUrgent = true;
            } else if (diffDays === 1) {
                timeLabel = `Tomorrow`;
            } else {
                timeLabel = startsAt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
        }

        const notifId = `reminder-${item.id}`;
        if (dismissedIds.value.has(notifId)) return;

        list.push({
            id: notifId,
            rawId: item.id,
            type: 'reminders',
            typeLabel: 'Reminder',
            title: item.title,
            description: item.description || (item.location ? `Location: ${item.location}` : `Category: ${item.category}`),
            category: item.category || 'Reminder',
            time: timeLabel,
            timestamp: startsAt ? startsAt.getTime() : (item.createdAt ? new Date(item.createdAt).getTime() : now.getTime()),
            isUrgent,
            isRead: readIds.value.has(notifId),
            isArchived: archivedIds.value.has(notifId),
            targetTab: 'reminders',
            tabName: 'Reminders',
            fullData: item,
        });
    });

    // 2. Project Alerts
    const projectsList = Array.isArray(projects.value) ? projects.value : [];
    projectsList.forEach(proj => {
        const rawStatus = (proj.status || '').toString().toLowerCase();
        const isDelayed = rawStatus.includes('delay') || proj.status === 2;
        const isSuspended = rawStatus.includes('suspend') || proj.status === 4;

        if (isDelayed) {
            const notifId = `project-delay-${proj.id}`;
            if (!dismissedIds.value.has(notifId)) {
                list.push({
                    id: notifId,
                    rawId: proj.id,
                    type: 'projects',
                    typeLabel: 'Project Delay',
                    title: `Delayed: ${proj.title || proj.name}`,
                    description: `Project is delayed (${proj.accomplishment || proj.progress || 0}% accomplishment). Target: ${proj.targetCompletionDate || 'Scheduled'}.`,
                    category: 'Delay',
                    time: proj.location || 'Infrastructure',
                    timestamp: now.getTime() - 1000 * 60 * 60 * 2,
                    isUrgent: true,
                    isRead: readIds.value.has(notifId),
                    isArchived: archivedIds.value.has(notifId),
                    targetTab: 'projects',
                    tabName: isStaff.value ? 'My Projects' : 'Projects',
                    fullData: proj,
                });
            }
        } else if (isSuspended) {
            const notifId = `project-suspend-${proj.id}`;
            if (!dismissedIds.value.has(notifId)) {
                list.push({
                    id: notifId,
                    rawId: proj.id,
                    type: 'projects',
                    typeLabel: 'Suspension',
                    title: `Suspended: ${proj.title || proj.name}`,
                    description: `Project has a suspension order in place${proj.daysSuspensionOrder ? ` (${proj.daysSuspensionOrder} days)` : ''}.`,
                    category: 'Suspended',
                    time: proj.location || 'Infrastructure',
                    timestamp: now.getTime() - 1000 * 60 * 60 * 4,
                    isUrgent: true,
                    isRead: readIds.value.has(notifId),
                    isArchived: archivedIds.value.has(notifId),
                    targetTab: 'projects',
                    tabName: isStaff.value ? 'My Projects' : 'Projects',
                    fullData: proj,
                });
            }
        }

        if (isAdminOrSuperadmin.value && proj.technical_preparations) {
            const tp = proj.technical_preparations;
            const issues = Object.entries(tp).filter(([_, val]) => val?.status === 'red' || val?.status === 'yellow');
            if (issues.length > 0 && !isDelayed && !isSuspended) {
                const notifId = `project-tp-${proj.id}`;
                if (!dismissedIds.value.has(notifId)) {
                    list.push({
                        id: notifId,
                        rawId: proj.id,
                        type: 'projects',
                        typeLabel: 'Tech Prep',
                        title: `Pending Prep: ${proj.title || proj.name}`,
                        description: `${issues.length} technical preparation requirements flagged.`,
                        category: 'Prep Flag',
                        time: proj.location || 'Pre-Engineering',
                        timestamp: now.getTime() - 1000 * 60 * 60 * 12,
                        isUrgent: issues.some(([_, val]) => val?.status === 'red'),
                        isRead: readIds.value.has(notifId),
                        isArchived: archivedIds.value.has(notifId),
                        targetTab: 'projects',
                        tabName: 'Projects',
                        fullData: proj,
                    });
                }
            }
        }
    });

    // 3. Bulletins
    bulletins.value.forEach(bulletin => {
        if (bulletin.isArchived) return;

        const notifId = `bulletin-${bulletin.id}`;
        if (dismissedIds.value.has(notifId)) return;

        list.push({
            id: notifId,
            rawId: bulletin.id,
            type: 'bulletins',
            typeLabel: 'Bulletin',
            title: bulletin.title,
            description: bulletin.summary || 'New municipal announcement posted.',
            category: bulletin.category || 'Announcement',
            time: bulletin.date || 'Notice',
            timestamp: bulletin.date ? new Date(bulletin.date).getTime() : now.getTime(),
            isUrgent: false,
            isRead: readIds.value.has(notifId),
            isArchived: archivedIds.value.has(notifId),
            targetTab: 'bulletin',
            tabName: 'Bulletin',
            fullData: bulletin,
        });
    });

    // 4. Staff Assignments, Directives & Deadlines
    assignments.value.forEach(asgn => {
        if (asgn.status === 'completed' || asgn.status === 'cancelled') return;

        const asgnUserId = Number(asgn.userId || asgn.user_id);
        const isAssignedToCurrent = asgnUserId === currentUserId;

        if (isStaff.value) {
            if (!isAssignedToCurrent) return;

            const isUrgent = (asgn.priority || '').toLowerCase() === 'urgent' || (asgn.priority || '').toLowerCase() === 'high';
            const notifId = `assignment-${asgn.id}`;
            let itemTitle = asgn.title || 'New Task Assignment';
            let itemDesc = asgn.note || (asgn.projectName ? `Project: ${asgn.projectName}` : 'New assignment pending action.');

            if (asgn.type === 'assignment') {
                itemTitle = asgn.projectName ? `Assigned: ${asgn.projectName}` : asgn.title;
                itemDesc = asgn.roleInProject ? `Role: ${asgn.roleInProject}. ${asgn.note || ''}` : asgn.note || 'You have been assigned to this project.';
            } else if (asgn.type === 'deadline') {
                itemTitle = `Deadline: ${asgn.title}`;
            } else if (asgn.type === 'note') {
                itemTitle = `Directive: ${asgn.title}`;
            }

            if (!dismissedIds.value.has(notifId)) {
                list.push({
                    id: notifId,
                    rawId: asgn.id,
                    type: 'assignments',
                    typeLabel: asgn.type === 'assignment' ? 'Assignment' : (asgn.type === 'deadline' ? 'Deadline' : 'Directive'),
                    title: itemTitle,
                    description: itemDesc,
                    category: asgn.priority ? asgn.priority.toUpperCase() : 'Directive',
                    time: asgn.targetDeadline ? `Due ${new Date(asgn.targetDeadline).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}` : 'Active',
                    timestamp: asgn.createdAt ? new Date(asgn.createdAt).getTime() : now.getTime(),
                    isUrgent,
                    isRead: readIds.value.has(notifId),
                    isArchived: archivedIds.value.has(notifId),
                    targetTab: 'projects',
                    tabName: 'My Projects',
                    fullData: asgn,
                });
            }

            // Parse conversation messages from Admin
            if (Array.isArray(asgn.conversation) && asgn.conversation.length > 0) {
                const adminMsgs = asgn.conversation.filter(m => m.sender_role && m.sender_role.toLowerCase() !== 'staff' && Number(m.sender_id) !== currentUserId);
                if (adminMsgs.length > 0) {
                    const latestAdminMsg = adminMsgs[adminMsgs.length - 1];
                    const msgNotifId = `asgn-msg-${asgn.id}-${latestAdminMsg.id || latestAdminMsg.created_at || adminMsgs.length}`;
                    if (!dismissedIds.value.has(msgNotifId)) {
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
                            isArchived: archivedIds.value.has(msgNotifId),
                            targetTab: 'projects',
                            tabName: 'My Projects',
                            fullData: asgn,
                        });
                    }
                }
            }
        } else if (isAdminOrSuperadmin.value) {
            const hasReply = !!asgn.staffReply;
            const isOverdue = asgn.targetDeadline && new Date(asgn.targetDeadline) < now;

            if (hasReply) {
                const notifId = `admin-asgn-${asgn.id}-reply`;
                if (!dismissedIds.value.has(notifId)) {
                    list.push({
                        id: notifId,
                        rawId: asgn.id,
                        type: 'assignments',
                        typeLabel: 'Reply',
                        title: `Reply from ${asgn.userName || 'Personnel'}: ${asgn.projectName || asgn.title}`,
                        description: asgn.staffReply,
                        category: 'Staff Reply',
                        time: asgn.staffRepliedAt ? new Date(asgn.staffRepliedAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'Recent',
                        timestamp: asgn.staffRepliedAt ? new Date(asgn.staffRepliedAt).getTime() : now.getTime(),
                        isUrgent: false,
                        isRead: readIds.value.has(notifId),
                        isArchived: archivedIds.value.has(notifId),
                        targetTab: 'staff',
                        tabName: 'Staff Directory',
                        fullData: asgn,
                    });
                }
            }

            // Parse conversation messages from Staff
            if (Array.isArray(asgn.conversation) && asgn.conversation.length > 0) {
                const staffMsgs = asgn.conversation.filter(m => (!m.sender_role || m.sender_role.toLowerCase() === 'staff') && Number(m.sender_id) !== currentUserId);
                if (staffMsgs.length > 0) {
                    const latestStaffMsg = staffMsgs[staffMsgs.length - 1];
                    const msgNotifId = `admin-asgn-msg-${asgn.id}-${latestStaffMsg.id || latestStaffMsg.created_at || staffMsgs.length}`;
                    if (!dismissedIds.value.has(msgNotifId)) {
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
                            isArchived: archivedIds.value.has(msgNotifId),
                            targetTab: 'staff',
                            tabName: 'Staff Directory',
                            fullData: asgn,
                        });
                    }
                }
            }

            if (isOverdue) {
                const notifId = `admin-asgn-${asgn.id}-overdue`;
                if (!dismissedIds.value.has(notifId)) {
                    list.push({
                        id: notifId,
                        rawId: asgn.id,
                        type: 'assignments',
                        typeLabel: 'Overdue',
                        title: `Overdue: ${asgn.title} (${asgn.userName || 'Staff'})`,
                        description: asgn.note || `Target deadline was ${new Date(asgn.targetDeadline).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}.`,
                        category: 'Overdue',
                        time: `Due ${new Date(asgn.targetDeadline).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`,
                        timestamp: asgn.targetDeadline ? new Date(asgn.targetDeadline).getTime() : now.getTime(),
                        isUrgent: true,
                        isRead: readIds.value.has(notifId),
                        isArchived: archivedIds.value.has(notifId),
                        targetTab: 'staff',
                        tabName: 'Staff Directory',
                        fullData: asgn,
                    });
                }
            }
        }
    });

    // 5. Citizen Inquiries & Cancellation Requests
    inquiries.value.forEach(inq => {
        const isCancelRequested = inq.status === 'cancel_requested';
        const isPending = inq.status === 'pending';

        if (isCancelRequested) {
            const notifId = `inquiry-cancel-${inq.id}`;
            if (dismissedIds.value.has(notifId)) return;

            list.push({
                id: notifId,
                rawId: inq.id,
                type: 'inquiries',
                typeLabel: 'Cancellation Request',
                title: `Cancel Request: ${inq.subject || 'Citizen Inquiry'}`,
                description: `${inq.fullname} requested cancellation: "${inq.cancellation_reason || 'No reason provided'}". Tracking Token: #${inq.tracking_token}. Requires staff/admin confirmation.`,
                category: 'Cancel Request',
                time: inq.created_at_relative || 'Pending Confirmation',
                timestamp: inq.cancelled_at ? new Date(inq.cancelled_at).getTime() : (inq.createdAt ? new Date(inq.createdAt).getTime() : now.getTime()),
                isUrgent: true,
                isRead: readIds.value.has(notifId),
                isArchived: archivedIds.value.has(notifId),
                targetTab: 'messages',
                tabName: 'Messages',
                fullData: inq,
            });
        } else if (isPending) {
            const notifId = `inquiry-${inq.id}`;
            if (dismissedIds.value.has(notifId)) return;

            list.push({
                id: notifId,
                rawId: inq.id,
                type: 'inquiries',
                typeLabel: 'Citizen Concern',
                title: `Inquiry: ${inq.subject || 'Citizen Question'}`,
                description: `From ${inq.fullname}: ${(inq.message || '').substring(0, 90)}${(inq.message || '').length > 90 ? '...' : ''}`,
                category: 'Pending Concern',
                time: inq.created_at_relative || 'Pending',
                timestamp: inq.createdAt ? new Date(inq.createdAt).getTime() : now.getTime(),
                isUrgent: true,
                isRead: readIds.value.has(notifId),
                isArchived: archivedIds.value.has(notifId),
                targetTab: 'messages',
                tabName: 'Messages',
                fullData: inq,
            });
        }
    });

    // 6. Security & Audit Activity Alerts (For Superadmin)
    if (isSuperadmin.value && Array.isArray(systemLogs.value)) {
        systemLogs.value.forEach(log => {
            const notifId = `system-log-${log.id}`;
            if (dismissedIds.value.has(notifId)) return;

            list.push({
                id: notifId,
                rawId: log.id,
                type: 'system',
                typeLabel: log.severity === 'danger' ? 'Critical Security' : 'System Warning',
                title: `${log.action?.toUpperCase() || 'AUDIT'}: ${log.user_name || 'System User'}`,
                description: log.description || `Action recorded under module ${log.module}.`,
                category: log.severity === 'danger' ? 'Danger Alert' : 'Audit Warning',
                time: log.created_at_relative || 'Recent',
                timestamp: log.createdAt ? new Date(log.createdAt).getTime() : now.getTime() - 1000 * 60 * 30,
                isUrgent: log.severity === 'danger',
                isRead: readIds.value.has(notifId),
                isArchived: archivedIds.value.has(notifId),
                targetTab: 'logs',
                tabName: 'Audit Logs',
                fullData: log,
            });
        });
    }

    return list;
});

// Inbox vs Archived Lists
const inboxNotifications = computed(() => rawNotifications.value.filter(n => !n.isArchived));
const archivedNotifications = computed(() => rawNotifications.value.filter(n => n.isArchived));

// Counts
const inboxCount = computed(() => inboxNotifications.value.length);
const unreadCount = computed(() => inboxNotifications.value.filter(n => !n.isRead).length);
const urgentCount = computed(() => inboxNotifications.value.filter(n => n.isUrgent && !n.isRead).length);
const archivedCount = computed(() => archivedNotifications.value.length);

const remindersCount = computed(() => inboxNotifications.value.filter(n => n.type === 'reminders').length);
const projectsCount = computed(() => inboxNotifications.value.filter(n => n.type === 'projects').length);
const bulletinsCount = computed(() => inboxNotifications.value.filter(n => n.type === 'bulletins').length);
const assignmentsCount = computed(() => inboxNotifications.value.filter(n => n.type === 'assignments').length);
const inquiriesCount = computed(() => inboxNotifications.value.filter(n => n.type === 'inquiries').length);
const systemCount = computed(() => inboxNotifications.value.filter(n => n.type === 'system').length);

// Filtered List based on Active View (Inbox / Archived)
const displayedNotifications = computed(() => {
    let list = activeView.value === 'archived' ? archivedNotifications.value : inboxNotifications.value;

    // Category Filter (only for inbox)
    if (activeView.value === 'inbox' && activeCategory.value !== 'all') {
        list = list.filter(n => n.type === activeCategory.value);
    }

    // Read Filter
    if (readFilter.value === 'unread') {
        list = list.filter(n => !n.isRead);
    } else if (readFilter.value === 'read') {
        list = list.filter(n => n.isRead);
    }

    // Priority Filter
    if (priorityFilter.value === 'urgent') {
        list = list.filter(n => n.isUrgent);
    }

    // Search Query Filter
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(n =>
            (n.title && n.title.toLowerCase().includes(q)) ||
            (n.description && n.description.toLowerCase().includes(q)) ||
            (n.category && n.category.toLowerCase().includes(q)) ||
            (n.typeLabel && n.typeLabel.toLowerCase().includes(q))
        );
    }

    // Sorting
    return list.sort((a, b) => {
        if (sortBy.value === 'urgent') {
            if (a.isUrgent !== b.isUrgent) return a.isUrgent ? -1 : 1;
            if (a.isRead !== b.isRead) return a.isRead ? 1 : -1;
            return b.timestamp - a.timestamp;
        } else if (sortBy.value === 'oldest') {
            return a.timestamp - b.timestamp;
        } else {
            if (a.isRead !== b.isRead) return a.isRead ? 1 : -1;
            return b.timestamp - a.timestamp;
        }
    });
});

// Actions
const markAsRead = (notif, e) => {
    if (e) e.stopPropagation();
    readIds.value.add(notif.id);
    saveReadIds();
    showToast('Marked as read', 'info');
};

const markAsUnread = (notif, e) => {
    if (e) e.stopPropagation();
    readIds.value.delete(notif.id);
    saveReadIds();
    showToast('Marked as unread', 'info');
};

const markAllAsRead = () => {
    inboxNotifications.value.forEach(n => readIds.value.add(n.id));
    saveReadIds();
    showToast('All marked as read', 'success');
};

const archiveNotification = (notif, e) => {
    if (e) e.stopPropagation();
    archivedIds.value.add(notif.id);
    readIds.value.add(notif.id);
    saveArchivedIds();
    saveReadIds();
    if (selectedNotification.value?.id === notif.id) {
        showDetailModal.value = false;
    }
    showToast('Archived', 'info');
};

const unarchiveNotification = (notif, e) => {
    if (e) e.stopPropagation();
    archivedIds.value.delete(notif.id);
    saveArchivedIds();
    if (selectedNotification.value?.id === notif.id) {
        showDetailModal.value = false;
    }
    showToast('Restored to inbox', 'success');
};

const archiveAllRead = () => {
    const readItems = inboxNotifications.value.filter(n => n.isRead);
    readItems.forEach(n => archivedIds.value.add(n.id));
    saveArchivedIds();
    showToast(`Archived ${readItems.length} read notifications`, 'success');
};

const deletePermanently = (notif, e) => {
    if (e) e.stopPropagation();
    dismissedIds.value.add(notif.id);
    archivedIds.value.delete(notif.id);
    saveDismissedIds();
    saveArchivedIds();
    if (selectedNotification.value?.id === notif.id) {
        showDetailModal.value = false;
    }
    showToast('Deleted', 'info');
};

const clearAllArchived = () => {
    archivedNotifications.value.forEach(n => {
        dismissedIds.value.add(n.id);
        archivedIds.value.delete(n.id);
    });
    saveDismissedIds();
    saveArchivedIds();
    showToast('Archived cleared', 'success');
};

// Navigation
const navigateToTarget = (notif) => {
    markAsRead(notif);
    const roleKey = isSuperadmin.value 
        ? 'meo_superadmin_active_tab' 
        : (isAdmin.value ? 'meo_admin_active_tab' : 'meo_staff_active_tab');
    
    if (notif.targetTab) {
        localStorage.setItem(roleKey, notif.targetTab);
        localStorage.setItem('meo_active_tab', notif.targetTab);
    }

    const targetRoute = isSuperadmin.value 
        ? '/superadmin/dashboard' 
        : (isAdmin.value ? '/admin/dashboard' : '/staff/dashboard');

    router.visit(targetRoute);
};

const openDetails = (notif) => {
    selectedNotification.value = notif;
    showDetailModal.value = true;
    if (!notif.isRead) {
        markAsRead(notif);
    }
};

const handleSidebarTabChange = (tabId) => {
    const roleKey = isSuperadmin.value 
        ? 'meo_superadmin_active_tab' 
        : (isAdmin.value ? 'meo_admin_active_tab' : 'meo_staff_active_tab');
    localStorage.setItem(roleKey, tabId);
    localStorage.setItem('meo_active_tab', tabId);

    const targetRoute = isSuperadmin.value 
        ? '/superadmin/dashboard' 
        : (isAdmin.value ? '/admin/dashboard' : '/staff/dashboard');
    router.visit(targetRoute);
};

const formatTimestamp = (ts) => {
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

const getRelativeTime = (ts) => {
    if (!ts) return '';
    const now = Date.now();
    const diffSec = Math.floor((now - ts) / 1000);
    if (diffSec < 60) return 'now';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr}h ago`;
    const diffDays = Math.floor(diffHr / 24);
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays}d ago`;
    return new Date(ts).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

onMounted(() => {
    loadSavedState();
    if (soundEnabled.value && urgentCount.value > 0) {
        playNotificationTone();
    }
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(silentSyncNotificationsData, 6000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});
</script>

<template>
    <div class="h-screen w-screen overflow-hidden bg-slate-100 text-slate-800 flex">
        <Head title="Notifications - MEO" />

        <!-- Role Sidebar -->
        <SuperadminSidebar
            v-if="isSuperadmin"
            :activeTab="'notifications'"
            :inquiries="inquiries"
            @tab-change="handleSidebarTabChange"
            @collapse-change="sidebarCollapsed = $event"
        />
        <AdminSidebar
            v-else-if="isAdmin"
            :activeTab="'notifications'"
            :inquiries="inquiries"
            @tab-change="handleSidebarTabChange"
            @collapse-change="sidebarCollapsed = $event"
        />
        <StaffSidebar
            v-else
            :activeTab="'notifications'"
            :inquiries="inquiries"
            @tab-change="handleSidebarTabChange"
            @collapse-change="sidebarCollapsed = $event"
        />

        <!-- Fullscreen Fluid Content Area -->
        <div :class="['flex-1 flex flex-col h-screen overflow-hidden transition-all duration-150', sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64']">
            <!-- Sleek Top Header Bar (Full Width) -->
            <header class="bg-white border-b border-slate-300 shrink-0 z-20">
                <div class="px-4 sm:px-8 py-2.5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="handleSidebarTabChange('dashboard')"
                            class="inline-flex items-center justify-center h-8 w-8 text-slate-700 hover:text-slate-950 hover:bg-slate-100 border border-slate-300 transition"
                            title="Back to Dashboard"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div class="h-4 w-px bg-slate-300"></div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xs sm:text-sm font-black text-slate-900 uppercase tracking-widest">Notification Center</h1>
                            <span
                                v-if="unreadCount > 0"
                                class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold bg-red-600 text-white"
                            >
                                {{ unreadCount }} UNREAD
                            </span>
                        </div>
                    </div>

                    <!-- Right Controls -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <!-- Sound Button -->
                        <button
                            type="button"
                            @click="toggleSound"
                            class="p-1.5 border text-xs transition"
                            :class="soundEnabled ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-white text-slate-500 border-slate-300 hover:text-slate-900'"
                            :title="soundEnabled ? 'Mute alert sound' : 'Enable alert sound'"
                        >
                            <svg v-if="soundEnabled" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                            </svg>
                        </button>

                        <!-- Refresh -->
                        <button
                            type="button"
                            @click="refreshData"
                            :disabled="loading"
                            class="p-1.5 bg-white border border-slate-300 text-slate-700 hover:text-slate-950 hover:bg-slate-50 transition"
                            title="Refresh"
                        >
                            <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>

                        <!-- Mark all read -->
                        <button
                            v-if="activeView === 'inbox' && unreadCount > 0"
                            type="button"
                            @click="markAllAsRead"
                            class="px-2.5 py-1 text-[11px] font-bold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition"
                        >
                            <span class="hidden sm:inline">Mark all read</span>
                            <span class="sm:hidden">Read all</span>
                        </button>

                        <!-- Archive all read -->
                        <button
                            v-if="activeView === 'inbox' && inboxNotifications.some(n => n.isRead)"
                            type="button"
                            @click="archiveAllRead"
                            class="px-2.5 py-1 text-[11px] font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 transition"
                            title="Archive all read notifications"
                        >
                            <span class="hidden sm:inline">Archive read</span>
                            <span class="sm:hidden">Archive</span>
                        </button>

                        <!-- Clear Archive -->
                        <button
                            v-if="activeView === 'archived' && archivedCount > 0"
                            type="button"
                            @click="clearAllArchived"
                            class="px-2.5 py-1 text-[11px] font-bold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition"
                        >
                            Clear Archive
                        </button>
                    </div>
                </div>
            </header>

            <!-- Navigation Bar (Full Width) -->
            <div class="bg-white border-b border-slate-300 px-4 sm:px-8 py-2 shrink-0 flex flex-wrap items-center justify-between gap-3">
                <!-- Inbox / Archived Tabs -->
                <div class="flex items-center gap-1.5 text-xs">
                    <button
                        type="button"
                        @click="activeView = 'inbox'"
                        :class="[
                            'px-4 py-1.5 font-bold transition flex items-center gap-2 border',
                            activeView === 'inbox'
                                ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                                : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <span>INBOX</span>
                        <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="activeView === 'inbox' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-800'">
                            {{ inboxCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeView = 'archived'"
                        :class="[
                            'px-4 py-1.5 font-bold transition flex items-center gap-2 border',
                            activeView === 'archived'
                                ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                                : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        <span>ARCHIVED</span>
                        <span class="px-1.5 py-0.2 text-[10px] font-bold" :class="activeView === 'archived' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-800'">
                            {{ archivedCount }}
                        </span>
                    </button>
                </div>

                <!-- Stats summary badges -->
                <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-600">
                    <span v-if="urgentCount > 0" class="px-2 py-0.5 bg-amber-100 text-amber-900 border border-amber-300 uppercase tracking-wide">
                        {{ urgentCount }} Urgent
                    </span>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-700 border border-slate-300 uppercase tracking-wide">
                        {{ displayedNotifications.length }} Displayed
                    </span>
                </div>
            </div>

            <!-- Toolbar (Full Width Search & Filter Bar) -->
            <div class="bg-white border-b border-slate-300 px-4 sm:px-8 py-2.5 shrink-0 space-y-2 text-xs">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-2.5">
                    <!-- Search Input (Expands smoothly) -->
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Filter by title, directive, message, location, or tag..."
                            class="w-full pl-9 pr-8 py-1.5 bg-slate-50 border border-slate-300 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-slate-900 transition"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="searchQuery = ''"
                            class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-800"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Dropdown Filters -->
                    <div class="grid grid-cols-3 sm:flex items-center gap-2">
                        <select
                            v-model="readFilter"
                            class="bg-slate-50 border border-slate-300 text-xs font-medium text-slate-800 py-1.5 pl-2.5 pr-6 focus:outline-none focus:border-slate-900"
                        >
                            <option value="all">Status: All</option>
                            <option value="unread">Status: Unread</option>
                            <option value="read">Status: Read</option>
                        </select>

                        <select
                            v-model="priorityFilter"
                            class="bg-slate-50 border border-slate-300 text-xs font-medium text-slate-800 py-1.5 pl-2.5 pr-6 focus:outline-none focus:border-slate-900"
                        >
                            <option value="all">Priority: All</option>
                            <option value="urgent">Priority: Urgent</option>
                        </select>

                        <select
                            v-model="sortBy"
                            class="bg-slate-50 border border-slate-300 text-xs font-medium text-slate-800 py-1.5 pl-2.5 pr-6 focus:outline-none focus:border-slate-900"
                        >
                            <option value="newest">Sort: Newest</option>
                            <option value="urgent">Sort: Priority</option>
                            <option value="oldest">Sort: Oldest</option>
                        </select>
                    </div>
                </div>

                <!-- Category Filters Row (Inbox View) -->
                <div v-if="activeView === 'inbox'" class="flex items-center gap-1.5 overflow-x-auto pt-1.5 border-t border-slate-200 no-scrollbar">
                    <button
                        type="button"
                        @click="activeCategory = 'all'"
                        :class="[
                            'px-2.5 py-1 text-[11px] font-bold border transition shrink-0 uppercase tracking-wider',
                            activeCategory === 'all' ? 'bg-red-700 text-white border-red-700' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        All ({{ inboxCount }})
                    </button>
                    <button
                        type="button"
                        @click="activeCategory = 'reminders'"
                        :class="[
                            'px-2.5 py-1 text-[11px] font-bold border transition shrink-0 uppercase tracking-wider',
                            activeCategory === 'reminders' ? 'bg-purple-700 text-white border-purple-700' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        Reminders ({{ remindersCount }})
                    </button>
                    <button
                        type="button"
                        @click="activeCategory = 'projects'"
                        :class="[
                            'px-2.5 py-1 text-[11px] font-bold border transition shrink-0 uppercase tracking-wider',
                            activeCategory === 'projects' ? 'bg-amber-700 text-white border-amber-700' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        Projects ({{ projectsCount }})
                    </button>
                    <button
                        type="button"
                        @click="activeCategory = 'bulletins'"
                        :class="[
                            'px-2.5 py-1 text-[11px] font-bold border transition shrink-0 uppercase tracking-wider',
                            activeCategory === 'bulletins' ? 'bg-blue-700 text-white border-blue-700' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        Bulletin ({{ bulletinsCount }})
                    </button>
                    <button
                        type="button"
                        @click="activeCategory = 'assignments'"
                        :class="[
                            'px-2.5 py-1 text-[11px] font-bold border transition shrink-0 uppercase tracking-wider',
                            activeCategory === 'assignments' ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        {{ isStaff ? 'Directives' : 'Tasks' }} ({{ assignmentsCount }})
                    </button>
                    <button
                        v-if="isAdminOrSuperadmin && inquiriesCount > 0"
                        type="button"
                        @click="activeCategory = 'inquiries'"
                        :class="[
                            'px-2.5 py-1 text-[11px] font-bold border transition shrink-0 uppercase tracking-wider',
                            activeCategory === 'inquiries' ? 'bg-rose-700 text-white border-rose-700' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        Inquiries ({{ inquiriesCount }})
                    </button>
                    <button
                        v-if="isSuperadmin && systemCount > 0"
                        type="button"
                        @click="activeCategory = 'system'"
                        :class="[
                            'px-2.5 py-1 text-[11px] font-bold border transition shrink-0 uppercase tracking-wider',
                            activeCategory === 'system' ? 'bg-indigo-700 text-white border-indigo-700' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        ]"
                    >
                        Audit Alerts ({{ systemCount }})
                    </button>
                </div>
            </div>

            <!-- Fullscreen Scrollable Feed List -->
            <div class="flex-1 overflow-y-auto bg-slate-100 divide-y divide-slate-200">
                <!-- Loading -->
                <div v-if="loading && rawNotifications.length === 0" class="p-12 text-center text-slate-500">
                    <div class="inline-block h-6 w-6 animate-spin border-2 border-slate-300 border-t-red-600"></div>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-wider">Syncing notifications...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="displayedNotifications.length === 0" class="p-16 text-center">
                    <div class="inline-flex items-center justify-center h-12 w-12 border border-slate-300 bg-white text-slate-400 mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                        {{ activeView === 'archived' ? 'Archive is empty' : 'No notifications in inbox' }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        {{ activeView === 'archived' ? 'Items you archive will appear here for reference.' : 'You have no alerts matching your selected criteria.' }}
                    </p>
                </div>

                <!-- Notification Row Item (Sharp Minimal Full-Width) -->
                <div
                    v-for="item in displayedNotifications"
                    :key="item.id"
                    @click="openDetails(item)"
                    class="bg-white hover:bg-slate-50/90 transition cursor-pointer px-4 sm:px-8 py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs group relative border-l-4"
                    :class="[
                        !item.isRead
                            ? (item.category === 'Cancel Request' ? 'border-l-amber-500 bg-amber-50/20' : 'border-l-red-600 bg-red-50/15')
                            : 'border-l-transparent'
                    ]"
                >
                    <!-- Left Section: Badge + Title + Description -->
                    <div class="flex items-start gap-3 min-w-0 flex-1">
                        <!-- Category Badge -->
                        <span
                            class="inline-flex items-center px-2 py-0.5 text-[9px] font-black uppercase tracking-widest shrink-0 mt-0.5 border"
                            :class="[
                                item.type === 'reminders' ? 'bg-purple-50 text-purple-800 border-purple-300' :
                                item.type === 'projects' ? 'bg-amber-50 text-amber-800 border-amber-300' :
                                item.type === 'assignments' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' :
                                item.type === 'system' ? 'bg-indigo-50 text-indigo-800 border-indigo-300' :
                                item.category === 'Cancel Request' ? 'bg-amber-100 text-amber-900 border-amber-400 font-black' :
                                item.type === 'inquiries' ? 'bg-rose-50 text-rose-800 border-rose-300' :
                                'bg-blue-50 text-blue-800 border-blue-300'
                            ]"
                        >
                            {{ item.typeLabel }}
                        </span>

                        <!-- Main Text Details -->
                        <div class="min-w-0 flex-1 space-y-0.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-slate-900 group-hover:text-red-700 transition tracking-tight">
                                    {{ item.title }}
                                </h3>
                                <span v-if="item.isUrgent" class="px-1.5 py-0.2 text-[9px] font-black bg-red-600 text-white uppercase tracking-wider">
                                    URGENT
                                </span>
                                <span v-if="!item.isRead" class="px-1.5 py-0.2 text-[9px] font-bold bg-slate-900 text-white uppercase tracking-wider">
                                    NEW
                                </span>
                            </div>
                            <p class="text-slate-600 text-[11px] leading-relaxed line-clamp-1">
                                {{ item.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Right Section: Time + Actions -->
                    <div class="flex items-center justify-between md:justify-end gap-3 shrink-0 border-t md:border-t-0 pt-2 md:pt-0 border-slate-100" @click.stop>
                        <span class="text-[11px] font-mono text-slate-500 whitespace-nowrap">
                            {{ getRelativeTime(item.timestamp) }}
                        </span>

                        <div class="flex items-center gap-1.5">
                            <!-- Direct Tab Jump -->
                            <button
                                type="button"
                                @click="navigateToTarget(item)"
                                class="px-2.5 py-1 bg-red-700 hover:bg-red-800 text-white font-bold text-[10px] uppercase tracking-wider transition whitespace-nowrap"
                                title="Open destination tab"
                            >
                                {{ item.tabName }}
                            </button>

                            <!-- Read/Unread Toggle -->
                            <button
                                v-if="!item.isRead"
                                type="button"
                                @click="markAsRead(item, $event)"
                                class="p-1 border border-slate-300 bg-white text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition"
                                title="Mark as read"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="markAsUnread(item, $event)"
                                class="p-1 border border-slate-300 bg-white text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition"
                                title="Mark as unread"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                            </button>

                            <!-- Archive / Unarchive -->
                            <button
                                v-if="!item.isArchived"
                                type="button"
                                @click="archiveNotification(item, $event)"
                                class="p-1 border border-slate-300 bg-white text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition"
                                title="Archive"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                </svg>
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="unarchiveNotification(item, $event)"
                                class="p-1 border border-slate-300 bg-white text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition"
                                title="Restore to Inbox"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            </button>

                            <!-- Delete -->
                            <button
                                type="button"
                                @click="deletePermanently(item, $event)"
                                class="p-1 border border-slate-300 bg-white text-slate-600 hover:text-red-700 hover:bg-red-50 transition"
                                title="Delete"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- SHARP FLAT DETAIL MODAL                                  -->
        <!-- ======================================================== -->
        <Teleport to="body">
            <div
                v-if="showDetailModal && selectedNotification"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
                @click.self="showDetailModal = false"
            >
                <div class="bg-white max-w-xl w-full border border-slate-400 text-xs shadow-2xl">
                    <!-- Modal Header -->
                    <div class="px-5 py-3 border-b border-slate-300 flex items-center justify-between bg-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-[10px] uppercase tracking-wider px-2 py-0.5 bg-slate-900 text-white">
                                {{ selectedNotification.typeLabel }}
                            </span>
                            <span class="text-slate-600 text-[11px] font-medium">{{ selectedNotification.category }}</span>
                        </div>
                        <button
                            type="button"
                            @click="showDetailModal = false"
                            class="p-1 text-slate-500 hover:text-slate-900 border border-transparent hover:border-slate-300"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-3.5">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">{{ selectedNotification.title }}</h2>
                            <p class="text-[11px] text-slate-500 mt-0.5 font-mono">{{ formatTimestamp(selectedNotification.timestamp) }}</p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed whitespace-pre-wrap">
                            {{ selectedNotification.description }}
                        </div>

                        <!-- Extra Context for Cancellation Requests -->
                        <div v-if="selectedNotification.category === 'Cancel Request' && selectedNotification.fullData" class="p-3.5 bg-amber-50 border border-amber-200 rounded-lg space-y-1 text-[11px]">
                            <div class="font-bold text-amber-900 flex items-center gap-1.5">
                                <i class="ri-alert-line text-amber-600"></i>
                                <span>Citizen Cancellation Request Details</span>
                            </div>
                            <div class="text-amber-800 text-xs">
                                <strong>Citizen Reason:</strong> {{ selectedNotification.fullData.cancellation_reason || 'None provided' }}
                            </div>
                            <div class="text-[11px] text-amber-700 flex items-center gap-3">
                                <span><strong>Tracking Token:</strong> #{{ selectedNotification.fullData.tracking_token }}</span>
                                <span><strong>Contact:</strong> {{ selectedNotification.fullData.email || selectedNotification.fullData.phone || 'N/A' }}</span>
                            </div>
                        </div>

                        <!-- Extra Context for System Security Audit -->
                        <div v-if="selectedNotification.type === 'system' && selectedNotification.fullData" class="p-3.5 bg-indigo-50 border border-indigo-200 rounded-lg space-y-1 text-[11px]">
                            <div class="font-bold text-indigo-900 flex items-center gap-1.5">
                                <i class="ri-shield-keyhole-line text-indigo-600"></i>
                                <span>Security & Audit Metadata</span>
                            </div>
                            <div class="text-indigo-800 text-xs">
                                <strong>Actor:</strong> {{ selectedNotification.fullData.user_name }} ({{ selectedNotification.fullData.user_role }})
                            </div>
                            <div class="text-[11px] text-indigo-700 flex items-center gap-3">
                                <span><strong>Module:</strong> {{ selectedNotification.fullData.module }}</span>
                                <span><strong>IP Address:</strong> {{ selectedNotification.fullData.ip_address || '127.0.0.1' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-3 bg-slate-100 border-t border-slate-300 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <button
                                v-if="!selectedNotification.isArchived"
                                type="button"
                                @click="archiveNotification(selectedNotification)"
                                class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 font-bold text-slate-800 transition uppercase tracking-wider text-[10px]"
                            >
                                Archive
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="unarchiveNotification(selectedNotification)"
                                class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 font-bold text-slate-800 transition uppercase tracking-wider text-[10px]"
                            >
                                Restore to Inbox
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="showDetailModal = false"
                                class="px-3.5 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 font-bold text-slate-800 transition uppercase tracking-wider text-[10px]"
                            >
                                Close
                            </button>
                            <button
                                type="button"
                                @click="navigateToTarget(selectedNotification)"
                                class="px-4 py-1.5 bg-red-700 hover:bg-red-800 text-white font-bold transition inline-flex items-center gap-1.5 uppercase tracking-wider text-[10px]"
                            >
                                <span>Open {{ selectedNotification.tabName }}</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Sharp Toast Notification -->
        <Teleport to="body">
            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="transform translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform translate-y-2 opacity-0"
            >
                <div
                    v-if="toast.show"
                    class="fixed bottom-4 right-4 z-50 px-4 py-2 bg-slate-900 text-white text-xs font-bold border border-slate-700 shadow-xl pointer-events-none flex items-center gap-2 uppercase tracking-wide"
                >
                    <span>{{ toast.message }}</span>
                </div>
            </transition>
        </Teleport>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
