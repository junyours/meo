<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Sidebar from './Partials/Sidebar.vue';
import DashboardTab from './Partials/DashboardTab.vue';
import MessagesTab from './Partials/MessagesTab.vue';
import MyProjectsTab from './Partials/MyProjectsTab.vue';
import FindProject from './Partials/FindProject.vue';
import Bulletin from '../Partials/bullettin.vue';
import RemindersTab from '../Partials/RemindersTab.vue';
import NotificationDropdown from '../Admin/Partials/NotificationDropdown.vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
    projects: {
        type: Array,
        default: () => [],
    },
});

const validTabs = ['dashboard', 'messages', 'projects', 'findproject', 'bulletin', 'reminders'];
const activeTabKey = 'meo_staff_active_tab';
const storedActiveTab = localStorage.getItem(activeTabKey) || localStorage.getItem('meo_active_tab');
const activeTab = ref(validTabs.includes(storedActiveTab) ? storedActiveTab : 'dashboard');
const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');
const projectList = ref([...props.projects]);

watch(() => props.projects, (newProjects) => {
    projectList.value = [...(newProjects || [])];
}, { deep: true });

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
        <Head title="Staff Dashboard" />

        <div>
            <Sidebar
                :activeTab="activeTab"
                @tab-change="handleTabChange"
                @collapse-change="handleCollapseChange"
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
                            <p class="text-xs sm:text-sm text-gray-600">Staff Operations Panel</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <NotificationDropdown :projects="projectList" @navigate-tab="handleTabChange" />
                        </div>
                    </div>
                </div>

                <div class="flex-1 px-4 py-6 sm:px-6 lg:px-8 flex flex-col">
                    <DashboardTab v-if="activeTab === 'dashboard'" :stats="stats" />
                    <MessagesTab v-else-if="activeTab === 'messages'" />
                    <MyProjectsTab
                        v-else-if="activeTab === 'projects'"
                        :initial-projects="projectList"
                        @update:projects="projectList = $event"
                    />
                    <FindProject v-else-if="activeTab === 'findproject'" :projects="projectList" />
                    <Bulletin v-else-if="activeTab === 'bulletin'" />
                    <RemindersTab v-else-if="activeTab === 'reminders'" />
                </div>
            </div>
        </div>
    </div>
</template>
