<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Sidebar from './Partials/Sidebar.vue';
import DashboardTab from './Partials/DashboardTab.vue';
import UsersTab from './Partials/UsersTab.vue';
import MessagesTab from './Partials/MessagesTab.vue';
import ProjectsTab from './Partials/ProjectsTab.vue';
import FindProject from '../Admin/Partials/FindProject.vue';
import SettingsTab from './Partials/SettingsTab.vue';
import LogsTab from './Partials/LogsTab.vue';
import Bulletin from '../Partials/bullettin.vue';
import RemindersTab from '../Partials/RemindersTab.vue';
import WelcomeContentTab from './Partials/WelcomeContentTab.vue';
import StaffTab from '../Admin/Partials/StaffTab.vue';
import NotificationDropdown from '../Admin/Partials/NotificationDropdown.vue';

const props = defineProps({
    users: {
        type: Array,
        default: [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    projects: {
        type: Array,
        default: () => [],
    },
});

const validTabs = ['dashboard', 'staff', 'users', 'messages', 'projects', 'findproject', 'bulletin', 'reminders', 'welcome', 'settings', 'logs'];
const activeTabKey = 'meo_superadmin_active_tab';
const storedActiveTab = localStorage.getItem(activeTabKey) || localStorage.getItem('meo_active_tab');
const activeTab = ref(validTabs.includes(storedActiveTab) ? storedActiveTab : 'dashboard');
const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');
const projectList = ref([...props.projects]);

const handleTabChange = (tab) => {
    activeTab.value = tab;
    localStorage.setItem(activeTabKey, tab);
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Head title="Superadmin Dashboard" />

        <div>
            <Sidebar
                :activeTab="activeTab"
                @tab-change="handleTabChange"
                @collapse-change="sidebarCollapsed = $event"
            />

            <div
                :class="[
                    sidebarCollapsed ? 'ml-0 lg:ml-20' : 'ml-0 lg:ml-64',
                    'flex min-h-screen flex-col transition-[margin-left] duration-200'
                ]"
            >
                <div class="sticky top-0 z-10 border-b border-gray-200 bg-white/95 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
                        <div>
                            <h1 class="text-lg sm:text-xl font-semibold text-gray-900">Municipal Engineering Office</h1>
                            <p class="text-xs sm:text-sm text-gray-600">System Administration Panel</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <NotificationDropdown :projects="projectList" @navigate-tab="handleTabChange" />
                        </div>
                    </div>
                </div>

                <div class="flex-1 px-4 py-6 sm:px-6 lg:px-8 flex flex-col">
                    <DashboardTab 
                        v-if="activeTab === 'dashboard'" 
                        :users="users" 
                        :stats="stats" 
                        :projects="projectList"
                        @tab-change="handleTabChange"
                        @create-user="handleTabChange('users')"
                        @view-reports="handleTabChange('projects')"
                        @open-settings="handleTabChange('settings')"
                    />
                    <StaffTab v-else-if="activeTab === 'staff'" :users="users" :projects="projectList" />
                    <UsersTab v-else-if="activeTab === 'users'" :users="users" />
                    <MessagesTab v-else-if="activeTab === 'messages'" />
                    <ProjectsTab
                        v-else-if="activeTab === 'projects'"
                        :initial-projects="projectList"
                        @update:projects="projectList = $event"
                    />
                    <FindProject v-else-if="activeTab === 'findproject'" :projects="projectList" />
                    <Bulletin v-else-if="activeTab === 'bulletin'" />
                    <RemindersTab v-else-if="activeTab === 'reminders'" />
                    <WelcomeContentTab v-else-if="activeTab === 'welcome'" />
                    <SettingsTab v-else-if="activeTab === 'settings'" />
                    <LogsTab v-else-if="activeTab === 'logs'" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
</style>
