<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Sidebar from './Partials/Sidebar.vue';
import DashboardTab from './Partials/DashboardTab.vue';
import MessagesTab from './Partials/MessagesTab.vue';
import ProjectsTab from './Partials/ProjectsTab.vue';
import FindProject from './Partials/FindProject.vue';
import StaffTab from './Partials/StaffTab.vue';
import SettingsTab from './Partials/SettingsTab.vue';
import Bulletin from '../Partials/bullettin.vue';
import RemindersTab from '../Partials/RemindersTab.vue';
import NotificationDropdown from './Partials/NotificationDropdown.vue';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
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

const validTabs = ['dashboard', 'messages', 'projects', 'findproject', 'staff', 'bulletin', 'reminders', 'settings'];
const activeTabKey = 'meo_admin_active_tab';
const storedActiveTab = localStorage.getItem(activeTabKey) || localStorage.getItem('meo_active_tab');
const activeTab = ref(validTabs.includes(storedActiveTab) ? storedActiveTab : 'dashboard');
const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');
const projectList = ref([...props.projects]);

const handleTabChange = (tab) => {
    activeTab.value = tab;
    localStorage.setItem(activeTabKey, tab);
};
const handleCollapseChange = (collapsed) => {
    sidebarCollapsed.value = collapsed;
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Head title="Admin Dashboard" />

        <div class="flex">
            <Sidebar :activeTab="activeTab" :inquiries="inquiries" @tab-change="handleTabChange" @collapse-change="handleCollapseChange" />

            <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-200', sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-56']">
                <div class="bg-white border-b border-slate-200 sticky top-0 z-40">
                    <div class="px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-sm sm:text-base font-bold text-slate-900">Municipal Engineering Office</h1>
                            <p class="text-[11px] font-semibold text-red-700">Admin Control Panel</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <NotificationDropdown :projects="projectList" @navigate-tab="handleTabChange" />
                        </div>
                    </div>
                </div>

                <div class="flex-1 px-4 sm:px-6 lg:px-8 py-4 sm:py-5 overflow-visible flex flex-col">
                    <DashboardTab v-if="activeTab === 'dashboard'" :users="users" :stats="stats" />
                    <MessagesTab v-else-if="activeTab === 'messages'" :initial-inquiries="inquiries" />
                    <ProjectsTab
                        v-else-if="activeTab === 'projects'"
                        :initial-projects="projectList"
                        @update:projects="projectList = $event"
                    />
                    <FindProject v-else-if="activeTab === 'findproject'" :projects="projectList" />
                    <StaffTab v-else-if="activeTab === 'staff'" :users="users" :projects="projectList" />
                    <Bulletin v-else-if="activeTab === 'bulletin'" />
                    <RemindersTab v-else-if="activeTab === 'reminders'" />
                    <SettingsTab v-else-if="activeTab === 'settings'" />
                </div>
            </div>
        </div>
    </div>
</template>
