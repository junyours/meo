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
                    sidebarCollapsed ? 'ml-0 lg:ml-16' : 'ml-0 lg:ml-56',
                    'flex min-h-screen flex-col transition-[margin-left] duration-200'
                ]"
            >
                <!-- Sticky Top Header -->
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-white shadow-2xs print:hidden">
                    <div class="flex flex-col gap-1 px-4 py-2.5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="goBack"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                                <span>Back</span>
                            </button>
                            <div class="h-4 w-px bg-slate-200"></div>
                            <div>
                                <h1 class="text-sm sm:text-base font-bold text-slate-900 truncate">
                                    {{ projectData.name || 'Project Details' }}
                                </h1>
                                <p class="text-[11px] font-semibold text-red-700">Project Information Sheet</p>
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
