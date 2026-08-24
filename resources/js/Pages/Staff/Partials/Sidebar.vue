<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, toRef } from 'vue';

const props = defineProps({
    activeTab: {
        type: String,
        default: 'dashboard',
    },
});

const emit = defineEmits(['tab-change', 'collapse-change']);

const page = usePage();
const activeTab = toRef(props, 'activeTab');
const role = computed(() => page.props.auth?.user?.role ?? 'staff');

const STORAGE_KEY = 'meo_sidebar_collapsed';
const collapsed = ref(localStorage.getItem(STORAGE_KEY) === 'true');
const mobileOpen = ref(false);
const showLogoutModal = ref(false);
const isLoggingOut = ref(false);

const performLogout = () => {
    isLoggingOut.value = true;
    router.post('/logout', {}, {
        onFinish: () => {
            isLoggingOut.value = false;
            showLogoutModal.value = false;
        }
    });
};

const setCollapsed = (val) => {
    collapsed.value = val;
    try {
        localStorage.setItem(STORAGE_KEY, val ? 'true' : 'false');
    } catch (e) {}
    emit('collapse-change', val);
};

const toggleCollapsed = () => setCollapsed(!collapsed.value);
const closeMobile = () => { mobileOpen.value = false; };

const tabs = computed(() => {
    const commonTabs = [
        { id: 'dashboard', label: 'Dashboard' },
        { id: 'messages', label: 'Messages' },
        { id: 'projects', label: 'Projects' },
        { id: 'findproject', label: 'Find Project' },
        { id: 'bulletin', label: 'Bulletin' },
        { id: 'reminders', label: 'Reminders' },
    ];

    const roleTabs = {
        superadmin: [
            ...commonTabs,
            { id: 'users', label: 'User Management' },
            { id: 'settings', label: 'Settings' },
            { id: 'logs', label: 'Activity Logs' },
        ],
        admin: [
            ...commonTabs,
            { id: 'settings', label: 'Settings' },
        ],
        staff: [
            { id: 'dashboard', label: 'Dashboard' },
            { id: 'messages', label: 'Messages' },
            { id: 'projects', label: 'My Projects' },
            { id: 'findproject', label: 'Find Project' },
            { id: 'bulletin', label: 'Bulletin' },
            { id: 'reminders', label: 'Reminders' },
        ],
    };

    return roleTabs[role.value] || commonTabs;
});

const icons = {
    dashboard: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 13h4V7H3v6zM13 13h4V3h-4v10zM8 13h4V3H8v10z" />
        </svg>
    `,
    messages: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H6l-4 4V5z" />
        </svg>
    `,
    projects: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zM13 21h8V11h-8v10zM13 3v6h8V3h-8z" />
        </svg>
    `,
    findproject: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
        </svg>
    `,
    users: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 4a3 3 0 100 6 3 3 0 000-6z" />
            <path d="M2 16a6 6 0 0116 0v1H2v-1z" />
        </svg>
    `,
    settings: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M11.3 1.046a1 1 0 00-2.6 0l-.197.684a8.003 8.003 0 00-1.518.54l-.63-.36a1 1 0 00-1.366.366l-.447.774a1 1 0 00.366 1.366l.63.36c-.05.326-.08.658-.08.995s.03.669.08.995l-.63.36a1 1 0 00-.366 1.366l.447.774a1 1 0 001.366.366l.63-.36c.46.28.96.5 1.518.54l.197.684a1 1 0 002.6 0l.197-.684c.558-.04 1.058-.26 1.518-.54l.63.36a1 1 0 001.366-.366l.447-.774a1 1 0 00-.366-1.366l-.63-.36c.05-.326.08-.658.08-.995s-.03-.669-.08-.995l.63-.36a1 1 0 00.366-1.366l-.447-.774a1 1 0 00-1.366-.366l-.63.36a8.003 8.003 0 00-1.518-.54L11.3 1.046zM10 13a3 3 0 110-6 3 3 0 010 6z" />
        </svg>
    `,
    logs: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6 2a1 1 0 00-1 1v12a1 1 0 001 1h8a1 1 0 001-1V7.414A2 2 0 0014.586 6L11 2.414A2 2 0 009.586 2H6z" />
        </svg>
    `,
    bulletin: `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18 11v2h4v-2h-4zm-2 6.61c.96.71 2.21 1.65 3.2 2.39.4-.53.8-1.07 1.2-1.6-.99-.74-2.24-1.68-3.2-2.4-.4.54-.8 1.08-1.2 1.61zM20.4 5.6c-.4-.53-.8-1.07-1.2-1.6-.99.74-2.24 1.68-3.2 2.4.4.53.8 1.07 1.2 1.6.96-.72 2.21-1.65 3.2-2.4zM4 9c-1.1 0-2 .9-2 2v2c0 1.1.9 2 2 2h1v4h2v-4h1l5 3V6L8 9H4zm11.5 3c0-1.33-.58-2.53-1.5-3.35v6.69c.92-.81 1.5-2.01 1.5-3.34z" />
        </svg>
    `,
};

icons.reminders = `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
        </svg>
    `;

const roleLabel = computed(() => {
    return role.value === 'superadmin'
        ? 'Superadmin'
        : role.value.charAt(0).toUpperCase() + role.value.slice(1);
});

onMounted(() => {
    emit('collapse-change', collapsed.value);
});
</script>

<template>
    <!-- Floating Mobile Sidebar Trigger (Bottom-left to prevent header overlay) -->
    <button
        v-if="!mobileOpen"
        type="button"
        class="fixed bottom-5 left-4 z-40 inline-flex items-center gap-2 px-3.5 py-2.5 rounded-full bg-red-700 hover:bg-red-800 text-white shadow-xl shadow-red-950/25 border border-red-600/40 text-xs font-bold transition-all active:scale-95 lg:hidden"
        aria-label="Open navigation menu"
        @click="mobileOpen = true"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <span>Menu</span>
    </button>
    <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" @click="closeMobile"></div>
    <aside
        :class="[
            collapsed ? 'w-72 lg:w-20' : 'w-72 lg:w-64',
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            'fixed inset-y-0 left-0 z-50 flex min-w-0 flex-col overflow-x-hidden overflow-y-auto border-r border-slate-200 bg-white text-slate-900 shadow-sm transition-[width,transform] duration-200'
        ]"
    >
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-1.5">
                    <img src="/image/meo_logo2.png" alt="MEO logo" class="h-full w-full object-contain" />
                </div>
                <div v-if="!collapsed || mobileOpen" class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-950">MEO Management</p>
                    <p class="truncate text-xs font-medium text-red-700">{{ roleLabel }} Console</p>
                </div>
            </div>
            <button
                @click="mobileOpen ? closeMobile() : toggleCollapsed()"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            >
                <svg v-if="mobileOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18" /></svg>
                <svg v-else-if="!collapsed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <nav class="min-w-0 flex-1 space-y-1 overflow-x-hidden px-3 py-4">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="emit('tab-change', tab.id); closeMobile()"
                :title="tab.label"
                :class="[
                    collapsed && !mobileOpen ? 'justify-center px-0 lg:justify-center' : 'justify-start px-3',
                    'group relative flex h-11 w-full min-w-0 items-center gap-3 rounded-lg text-sm font-medium transition-colors duration-200',
                    activeTab === tab.id
                        ? 'bg-red-700 text-white shadow-sm shadow-red-900/10'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'
                ]"
            >
                <span
                    :class="[activeTab === tab.id ? 'text-white' : 'text-slate-400 group-hover:text-slate-700', 'shrink-0']"
                    v-html="icons[tab.id]"
                ></span>
                <span v-if="!collapsed || mobileOpen" class="truncate">{{ tab.label }}</span>
            </button>
        </nav>

        <div class="min-w-0 space-y-1 border-t border-slate-200 px-3 py-4">
            <Link
                href="/profile"
                :class="[collapsed && !mobileOpen ? 'justify-center px-0 lg:justify-center' : 'justify-start px-3', 'flex h-11 items-center gap-3 rounded-lg text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950']"
                title="Profile"
                @click="closeMobile"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 4a3 3 0 100 6 3 3 0 000-6z" /><path fill-rule="evenodd" d="M2 14s2-4 8-4 8 4 8 4v2H2v-2z" clip-rule="evenodd"/></svg>
                <span v-if="!collapsed || mobileOpen" class="truncate">Profile</span>
            </Link>
            <button
                type="button"
                @click="showLogoutModal = true; closeMobile()"
                :class="[collapsed && !mobileOpen ? 'justify-center px-0 lg:justify-center' : 'justify-start px-3', 'flex h-11 w-full items-center gap-3 rounded-lg text-sm font-medium text-red-700 transition hover:bg-red-50 hover:text-red-800']"
                title="Logout"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h6a1 1 0 110 2H5v10h5a1 1 0 110 2H4a1 1 0 01-1-1V4z" clip-rule="evenodd"/><path d="M12.293 9.293a1 1 0 011.414 0L16 11.586V7a1 1 0 112 0v8a1 1 0 11-2 0v-4.586l-2.293 2.293a1 1 0 01-1.414-1.414L14.586 10 12.293 7.707a1 1 0 010-1.414z"/></svg>
                <span v-if="!collapsed || mobileOpen" class="truncate">Logout</span>
            </button>
        </div>
    </aside>

    <!-- Logout Confirmation Modal -->
    <Teleport to="body">
        <div v-if="showLogoutModal" class="fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-2xs transition-opacity" @click="showLogoutModal = false"></div>

                <div class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 text-center border border-slate-200 z-10 animate-in fade-in zoom-in-95 duration-150">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-700 mb-3.5 border border-red-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h6a1 1 0 110 2H5v10h5a1 1 0 110 2H4a1 1 0 01-1-1V4z" clip-rule="evenodd"/>
                            <path d="M12.293 9.293a1 1 0 011.414 0L16 11.586V7a1 1 0 112 0v8a1 1 0 11-2 0v-4.586l-2.293 2.293a1 1 0 01-1.414-1.414L14.586 10 12.293 7.707a1 1 0 010-1.414z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Are you sure you want to log out?</h3>
                    <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                        You will need to sign in again to access the MEO staff portal.
                    </p>
                    <div class="mt-6 flex items-center justify-center gap-2.5">
                        <button
                            type="button"
                            @click="showLogoutModal = false"
                            class="flex-1 px-4 py-2.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="performLogout"
                            :disabled="isLoggingOut"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-bold text-white bg-red-700 hover:bg-red-800 rounded-xl shadow-xs transition disabled:opacity-50"
                        >
                            <svg v-if="isLoggingOut" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <span>{{ isLoggingOut ? 'Signing out...' : 'Yes, Log Out' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
