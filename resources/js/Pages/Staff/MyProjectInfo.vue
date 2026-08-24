<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Sidebar from './Partials/Sidebar.vue';
import MyProjectInfo from './Partials/MyProjectInfo.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const projectData = ref({ ...props.project });
const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');

const handleCollapseChange = (collapsed) => {
    sidebarCollapsed.value = collapsed;
    localStorage.setItem('meo_sidebar_collapsed', collapsed);
};

const goBack = () => {
    localStorage.setItem('meo_staff_active_tab', 'projects');
    const role = page.props.auth?.user?.role;
    if (role === 'superadmin') {
        router.visit(route('superadmin.dashboard'), { preserveState: false });
    } else if (role === 'admin') {
        router.visit(route('admin.dashboard'), { preserveState: false });
    } else {
        router.visit(route('staff.dashboard'), { preserveState: false });
    }
};

const navigateToTab = (tab) => {
    localStorage.setItem('meo_staff_active_tab', tab);
    const role = page.props.auth?.user?.role;
    if (role === 'superadmin') {
        router.visit(route('superadmin.dashboard'), { preserveState: false });
    } else if (role === 'admin') {
        router.visit(route('admin.dashboard'), { preserveState: false });
    } else {
        router.visit(route('staff.dashboard'), { preserveState: false });
    }
};

const handleProjectUpdate = (updatedProject) => {
    if (updatedProject) {
        projectData.value = { ...updatedProject };
    }
};

const handleProjectDelete = () => {
    goBack();
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Head :title="`${projectData.name || 'Project'} — Project Info`" />

        <div>
            <Sidebar
                activeTab="projects"
                @tab-change="navigateToTab"
                @collapse-change="handleCollapseChange"
            />

            <div
                :class="[
                    sidebarCollapsed ? 'ml-0 lg:ml-20' : 'ml-0 lg:ml-64',
                    'flex min-h-screen flex-col transition-[margin-left] duration-200'
                ]"
            >
                <!-- Sticky Top Header -->
                <div class="sticky top-0 z-10 border-b border-gray-200 bg-white/95 shadow-sm backdrop-blur print:hidden">
                    <div class="flex flex-col gap-1 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="goBack"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-red-700 bg-gray-50 hover:bg-red-50 px-2.5 py-1.5 rounded-lg border border-gray-200 hover:border-red-200 transition"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span>Back</span>
                            </button>
                            <div>
                                <h1 class="text-lg sm:text-xl font-semibold text-gray-900">Municipal Engineering Office</h1>
                                <p class="text-xs text-gray-500">Staff Operations Panel &mdash; Project Information</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-7xl">
                        <MyProjectInfo
                            :project="projectData"
                            @back="goBack"
                            @update="handleProjectUpdate"
                            @delete="handleProjectDelete"
                        />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
