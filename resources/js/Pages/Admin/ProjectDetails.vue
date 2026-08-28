<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Sidebar from './Partials/Sidebar.vue';
import NotificationDropdown from './Partials/NotificationDropdown.vue';

const props = defineProps({
    project: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
});

const page = usePage();
const activeTab = ref('info');
const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');
const docFilter = ref('all');
const docSearch = ref('');
const docViewMode = ref('grid');
const previewDoc = ref(null);
const selectedDocIds = ref([]);

const handleCollapseChange = (collapsed) => {
    sidebarCollapsed.value = collapsed;
    localStorage.setItem('meo_sidebar_collapsed', collapsed);
};

const goBack = () => {
    const role = page.props.auth?.user?.role;
    if (role === 'superadmin') {
        localStorage.setItem('meo_superadmin_active_tab', 'findproject');
        router.visit(route('superadmin.dashboard'), { preserveState: false });
    } else if (role === 'staff') {
        localStorage.setItem('meo_staff_active_tab', 'findproject');
        router.visit(route('staff.dashboard'), { preserveState: false });
    } else {
        localStorage.setItem('meo_admin_active_tab', 'findproject');
        router.visit(route('admin.dashboard'), { preserveState: false });
    }
};

const navigateToTab = (tab) => {
    const role = page.props.auth?.user?.role;
    if (role === 'superadmin') {
        localStorage.setItem('meo_superadmin_active_tab', tab);
        router.visit(route('superadmin.dashboard'), { preserveState: false });
    } else if (role === 'staff') {
        localStorage.setItem('meo_staff_active_tab', tab);
        router.visit(route('staff.dashboard'), { preserveState: false });
    } else {
        localStorage.setItem('meo_admin_active_tab', tab);
        router.visit(route('admin.dashboard'), { preserveState: false });
    }
};

const printReport = () => {
    window.print();
};

const formatCurrency = (value) => {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return '—';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatFileSize = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const statusConfig = {
    'Completed':   { bg: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    'Ongoing':     { bg: 'bg-blue-50 text-blue-700 border-blue-200',       dot: 'bg-blue-500' },
    'Delayed':     { bg: 'bg-rose-50 text-rose-700 border-rose-200',         dot: 'bg-rose-500' },
    'Suspended':   { bg: 'bg-slate-100 text-slate-700 border-slate-300',     dot: 'bg-slate-400' },
    'Not Started': { bg: 'bg-slate-100 text-slate-600 border-slate-200',     dot: 'bg-slate-400' },
};
const getStatusConfig = (s) => statusConfig[s] || statusConfig['Ongoing'];

const progressColor = (val) => {
    if (val >= 90) return 'from-emerald-500 to-emerald-600';
    if (val >= 50) return 'from-emerald-400 to-emerald-500';
    return 'from-emerald-500 to-emerald-600';
};

const techPrepItems = computed(() => {
    const tp = props.project.technical_preparations;
    if (!tp) return [];
    const labels = {
        hazardAssessment:   'Hazard Assessment',
        powDed:             'POW / DED',
        supplementalBudget: 'Supplemental Budget',
        alobs:              'ALOBS',
        eccCnc:             'ECC / CNC',
        technicalDocsToBac: 'Technical Docs to BAC',
        bidding:            'Bidding',
        contractNtp:        'Contract / NTP',
    };
    return Object.entries(labels).map(([key, label]) => ({
        key, label, status: tp[key]?.status ?? '',
    }));
});

const techStatusConfig = {
    green:  { bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', type: 'done', label: 'Done', dot: 'bg-emerald-500' },
    yellow: { bg: 'bg-red-50 text-red-700 border-red-300',     type: 'pending', label: 'Pending', dot: 'bg-red-700' },
    red:    { bg: 'bg-rose-50 text-rose-800 border-rose-200',         type: 'issues', label: 'Issues', dot: 'bg-rose-500' },
    na:     { bg: 'bg-slate-100 text-slate-600 border-slate-200',        type: 'na', label: 'N/A', dot: 'bg-slate-400' },
    '':     { bg: 'bg-slate-50 text-slate-400 border-slate-200',          type: 'notset', label: 'Not Set', dot: 'bg-slate-300' },
};
const getTechStatus = (s) => techStatusConfig[s] || techStatusConfig[''];

const timelineEvents = computed(() => [
    { label: 'Project Created',    date: props.project.createdAt,             type: 'created' },
    { label: 'Start Date',         date: props.project.startDate,             type: 'start' },
    { label: 'Target Completion',  date: props.project.targetCompletionDate,  type: 'target' },
    { label: 'Revised Completion', date: props.project.revisedCompletionDate, type: 'revised' },
    { label: 'Actual Completion',  date: props.project.actualCompletionDate,  type: 'actual' },
].filter(e => !!e.date));

const docTypeFilters = computed(() => {
    const types = new Set(props.documents.map(d => d.type));
    return ['all', ...Array.from(types)];
});

const filteredDocuments = computed(() => {
    let docs = [...props.documents];
    if (docFilter.value !== 'all') docs = docs.filter(d => d.type === docFilter.value);
    if (docSearch.value) {
        const q = docSearch.value.toLowerCase();
        docs = docs.filter(d => d.name?.toLowerCase().includes(q));
    }
    return docs;
});

const isAllSelected = computed(() => {
    if (!filteredDocuments.value.length) return false;
    return filteredDocuments.value.every(d => selectedDocIds.value.includes(d.id));
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedDocIds.value = [];
    } else {
        selectedDocIds.value = filteredDocuments.value.map(d => d.id);
    }
};

const toggleSelectDoc = (docId) => {
    const idx = selectedDocIds.value.indexOf(docId);
    if (idx > -1) {
        selectedDocIds.value.splice(idx, 1);
    } else {
        selectedDocIds.value.push(docId);
    }
};

const isImage = (type) => ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(type?.toLowerCase());
const isPdf = (type) => type?.toLowerCase() === 'pdf';
const isDoc = (type) => ['doc', 'docx'].includes(type?.toLowerCase());

const procStatusConfig = {
    completed:  { bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', label: 'Verified' },
    processing: { bg: 'bg-red-50 text-red-700 border-red-200',     label: 'Processing' },
    pending:    { bg: 'bg-slate-100 text-slate-700 border-slate-200',    label: 'Pending' },
    failed:     { bg: 'bg-rose-50 text-rose-800 border-rose-200',         label: 'Failed' },
};
const getProcStatus = (s) => procStatusConfig[s] || procStatusConfig['pending'];

const openPreview = (doc) => { previewDoc.value = doc; };
const closePreview = () => { previewDoc.value = null; };

const getDocPreviewUrl = (doc) => {
    if (!doc?.id) return '';
    const role = page.props.auth?.user?.role;
    const routeName = role === 'superadmin' ? 'superadmin.documents.preview' : (role === 'staff' ? 'staff.documents.preview' : 'admin.documents.preview');
    try {
        if (typeof route === 'function' && route().has && route().has(routeName)) {
            return route(routeName, doc.id);
        }
    } catch (e) {
        // Fallback
    }
    const prefix = role === 'superadmin' ? '/superadmin' : (role === 'staff' ? '/staff' : '/admin');
    return `${prefix}/documents/${doc.id}/preview`;
};

const downloadDoc = (docId) => {
    const role = page.props.auth?.user?.role;
    const routeName = role === 'superadmin' ? 'superadmin.documents.download' : (role === 'staff' ? 'staff.documents.download' : 'admin.documents.download');
    let url = '';
    try {
        if (typeof route === 'function' && route().has && route().has(routeName)) {
            url = route(routeName, docId);
        }
    } catch (e) {
        // Fallback
    }
    if (!url) {
        const prefix = role === 'superadmin' ? '/superadmin' : (role === 'staff' ? '/staff' : '/admin');
        url = `${prefix}/documents/${docId}/download`;
    }
    window.open(url, '_blank');
};

const downloadSelectedDocs = () => {
    if (!selectedDocIds.value.length) return;
    selectedDocIds.value.forEach((id, idx) => {
        setTimeout(() => {
            downloadDoc(id);
        }, idx * 300);
    });
};

const downloadAllDocs = () => {
    if (!filteredDocuments.value.length) return;
    filteredDocuments.value.forEach((doc, idx) => {
        setTimeout(() => {
            downloadDoc(doc.id);
        }, idx * 300);
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
        <Head :title="`${project.name} — Project Details`" />
        <div class="flex">
            <Sidebar class="print:hidden" activeTab="findproject" @tab-change="navigateToTab" @collapse-change="handleCollapseChange" />

            <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-200', sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-56']">

                <!-- Compact Dashboard Top Bar -->
                <header class="bg-white border-b border-slate-200 sticky top-0 z-10 print:hidden">
                    <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-base font-bold text-slate-900">Municipal Engineering Office</h1>
                            <p class="text-[11px] text-red-700 font-semibold">
                                {{ page.props.auth?.user?.role === 'superadmin' ? 'Superadmin Control Panel' : (page.props.auth?.user?.role === 'staff' ? 'Staff Portal' : 'Admin Control Panel') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <NotificationDropdown :projects="[project]" @navigate-tab="navigateToTab" />
                        </div>
                    </div>
                </header>

                <!-- Page Main Content Body (Compact Spacing) -->
                <main class="flex-1 px-4 sm:px-6 py-4 space-y-4 print:hidden">

                    <!-- Compact Action Bar -->
                    <div class="flex items-center justify-between print:hidden">
                        <button @click="goBack" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-red-700 transition-colors bg-white hover:bg-red-50 px-3 py-1.5 rounded-lg border border-slate-200 hover:border-red-200 group">
                            <svg class="h-3.5 w-3.5 text-slate-400 group-hover:text-red-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span>Back to Projects</span>
                        </button>

                        <button @click="printReport" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-bold rounded-lg transition-colors shadow-2xs">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span>Print Formal Report</span>
                        </button>
                    </div>

                    <!-- Compact Modern Hero Summary Banner -->
                    <section class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            
                            <!-- Hero Info -->
                            <div class="space-y-1.5 flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight truncate">{{ project.name }}</h2>
                                    <span :class="[getStatusConfig(project.status).bg, 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold border shrink-0']">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="getStatusConfig(project.status).dot"></span>
                                        {{ project.status }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span class="inline-flex items-center gap-1 font-medium text-slate-700">
                                        <svg class="h-3.5 w-3.5 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ project.location }}
                                    </span>
                                    <span v-if="project.year" class="text-slate-300">•</span>
                                    <span v-if="project.year" class="bg-red-50 text-red-700 border border-red-200 px-1.5 py-0.5 rounded font-semibold text-[10px]">
                                        FY {{ project.year }}
                                    </span>
                                    <span v-if="project.contractor" class="text-slate-300">•</span>
                                    <span v-if="project.contractor" class="text-slate-600 font-medium truncate max-w-sm">
                                        Contractor: <span class="text-slate-900 font-semibold">{{ project.contractor }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Compact Accomplishment Widget -->
                            <div class="bg-slate-50 rounded-lg p-3 border border-slate-200 md:w-64 shrink-0 space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Accomplishment</span>
                                    <span class="font-bold text-slate-900">{{ project.accomplishment }}%</span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full bg-gradient-to-r transition-all duration-500" :class="progressColor(project.accomplishment)" :style="{ width: `${Math.min(project.accomplishment, 100)}%` }"></div>
                                </div>
                                <div class="flex justify-between items-center text-[10px] text-slate-500">
                                    <span>Target Completion:</span>
                                    <span class="font-semibold text-slate-800">{{ formatDate(project.targetCompletionDate) }}</span>
                                </div>
                            </div>

                        </div>
                    </section>

                    <!-- Navigation Tabs & Content Container -->
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        
                        <!-- Compact Tab Header with Sidebar Red Accent -->
                        <div class="border-b border-slate-200 bg-slate-50 px-3 pt-1.5">
                            <nav class="flex gap-1.5" role="tablist">
                                <button @click="activeTab = 'info'" :class="['flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-t-md border-t border-x transition-all relative', activeTab === 'info' ? 'bg-white border-slate-200 text-slate-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/60']">
                                    <svg class="h-3.5 w-3.5" :class="activeTab === 'info' ? 'text-red-700' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Project Info
                                    <div v-if="activeTab === 'info'" class="absolute top-0 left-0 right-0 h-0.5 bg-red-700 rounded-t"></div>
                                </button>

                                <button @click="activeTab = 'documents'" :class="['flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-t-md border-t border-x transition-all relative', activeTab === 'documents' ? 'bg-white border-slate-200 text-slate-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/60']">
                                    <svg class="h-3.5 w-3.5" :class="activeTab === 'documents' ? 'text-red-700' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    Documents
                                    <span class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-bold rounded-full transition-colors" :class="activeTab === 'documents' ? 'bg-red-100 text-red-900' : 'bg-slate-200 text-slate-600'">
                                        {{ documents.length }}
                                    </span>
                                    <div v-if="activeTab === 'documents'" class="absolute top-0 left-0 right-0 h-0.5 bg-red-700 rounded-t"></div>
                                </button>
                            </nav>
                        </div>

                        <!-- TAB 1: PROJECT INFO (COMPACT LAYOUT) -->
                        <div v-if="activeTab === 'info'" class="p-4 sm:p-5 space-y-5">

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                
                                <!-- Basic Specifications Card -->
                                <div class="bg-white rounded-lg border border-slate-200 p-4 space-y-3">
                                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                        <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Basic Specifications
                                        </h3>
                                    </div>
                                    <div class="divide-y divide-slate-100 text-xs">
                                        <div v-for="item in [
                                            { label: 'Project Name',    value: project.name },
                                            { label: 'Location',        value: project.location },
                                            { label: 'Contractor Name', value: project.contractor },
                                            { label: 'Source of Fund',  value: project.sourceOfFund },
                                            { label: 'Fund Category',   value: project.fundCategoryLabel || project.fundCategory },
                                            { label: 'Fiscal Year',     value: project.year },
                                            { label: 'Project Duration',value: project.duration ? project.duration + ' calendar days' : null },
                                            { label: 'Time Extension',  value: (project.timeExtension || 0) + ' days' },
                                            { label: 'Suspension Days', value: (project.daysSuspensionOrder || 0) + ' days' },
                                        ]" :key="item.label" class="py-1.5 flex justify-between items-center gap-3 hover:bg-slate-50/60 px-1.5 rounded transition-colors">
                                            <span class="text-slate-500 font-medium shrink-0">{{ item.label }}</span>
                                            <span class="text-slate-900 font-semibold text-right break-words max-w-xs">{{ item.value || '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status & Remarks Section -->
                                <div class="space-y-4">
                                    <div class="bg-white rounded-lg border border-slate-200 p-4 space-y-3">
                                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                            <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Status & Metrics
                                            </h3>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="p-2.5 rounded bg-slate-50 border border-slate-200 space-y-1">
                                                <span class="text-[10px] font-semibold text-slate-500 uppercase">Status</span>
                                                <div>
                                                    <span :class="[getStatusConfig(project.status).bg, 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border']">
                                                        <span class="w-1.5 h-1.5 rounded-full" :class="getStatusConfig(project.status).dot"></span>
                                                        {{ project.status }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="p-2.5 rounded bg-slate-50 border border-slate-200 space-y-1">
                                                <span class="text-[10px] font-semibold text-slate-500 uppercase">Accomplishment</span>
                                                <p class="text-sm font-extrabold text-slate-900">{{ project.accomplishment }}%</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="project.remarks" class="rounded-lg border border-red-200 bg-red-50/40 p-3 space-y-1 text-xs">
                                        <p class="font-bold text-red-900 uppercase text-[10px] tracking-wider">Latest Official Remarks</p>
                                        <p class="text-red-950 font-medium leading-relaxed">{{ project.remarks }}</p>
                                    </div>

                                    <div v-if="project.description" class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-1 text-xs">
                                        <p class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">Project Description</p>
                                        <p class="text-slate-700 leading-relaxed italic">{{ project.description }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Appropriation Breakdown -->
                            <div class="bg-white rounded-lg border border-slate-200 p-4 space-y-4">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Financial Appropriation
                                    </h3>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-3 space-y-0.5">
                                        <p class="font-semibold text-slate-500 uppercase text-[10px]">Total Contract Cost</p>
                                        <p class="text-base font-bold text-slate-900">{{ formatCurrency(project.totalCost) }}</p>
                                        <p class="text-[10px] text-slate-400">Approved budget</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-3 space-y-0.5">
                                        <p class="font-semibold text-slate-500 uppercase text-[10px]">Original Appropriation</p>
                                        <p class="text-base font-bold text-slate-900">{{ formatCurrency(project.originalCost) }}</p>
                                        <p class="text-[10px] text-slate-400">Initial allocation</p>
                                    </div>
                                    <div class="rounded-lg border border-red-200 bg-red-50/40 p-3 space-y-0.5">
                                        <p class="font-semibold text-red-900 uppercase text-[10px]">Revised Appropriation</p>
                                        <p class="text-base font-bold text-red-950">{{ formatCurrency(project.revisedCost) }}</p>
                                        <p class="text-[10px] text-red-700/80">Adjusted budget</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Technical Preparations Checklist -->
                            <div class="bg-white rounded-lg border border-slate-200 p-4 space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        Technical Preparations Checklist
                                    </h3>
                                </div>
                                <div v-if="!project.technical_preparations || techPrepItems.length === 0" class="text-center py-6 rounded border border-dashed border-slate-200">
                                    <p class="text-xs text-slate-400">No technical preparation records logged.</p>
                                </div>
                                <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                    <div v-for="item in techPrepItems" :key="item.key" :class="[getTechStatus(item.status).bg, 'rounded-lg border p-2.5 text-center flex flex-col items-center justify-between gap-1 transition-all']">
                                        <div class="flex items-center justify-center h-5 w-5">
                                            <svg v-if="getTechStatus(item.status).type === 'done'" class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                            <svg v-else-if="getTechStatus(item.status).type === 'pending'" class="h-4 w-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <svg v-else-if="getTechStatus(item.status).type === 'issues'" class="h-4 w-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                            <svg v-else class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6" /></svg>
                                        </div>
                                        <p class="text-[11px] font-bold text-slate-800 leading-tight">{{ item.label }}</p>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-bold bg-white/90 border border-slate-200">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="getTechStatus(item.status).dot"></span>
                                            {{ getTechStatus(item.status).label }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Milestones Timeline -->
                            <div class="bg-white rounded-lg border border-slate-200 p-4 space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Project Timeline Milestones
                                    </h3>
                                </div>
                                <div v-if="timelineEvents.length === 0" class="text-center py-6 rounded border border-dashed border-slate-200">
                                    <p class="text-xs text-slate-400">No timeline dates registered.</p>
                                </div>
                                <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 text-xs">
                                    <div v-for="event in timelineEvents" :key="event.label" class="p-2.5 rounded bg-slate-50 border border-slate-200 space-y-1">
                                        <p class="text-[10px] font-bold text-slate-500 uppercase">{{ event.label }}</p>
                                        <p class="font-bold text-slate-900">{{ formatDate(event.date) }}</p>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- TAB 2: DOCUMENTS (COMPACT & INLINE IMAGE PREVIEWS) -->
                        <div v-else-if="activeTab === 'documents'" class="p-4 sm:p-5 space-y-4">

                            <!-- Documents Repository Toolbar -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded bg-red-100 text-red-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-bold text-slate-900">Documents Repository</h3>
                                        <p class="text-[11px] text-slate-500 font-medium">
                                            Showing <span class="font-bold text-slate-800">{{ filteredDocuments.length }}</span> of <span class="font-bold text-slate-800">{{ documents.length }}</span> file{{ documents.length !== 1 ? 's' : '' }}
                                            <span v-if="selectedDocIds.length > 0" class="text-red-700 font-semibold ml-1">
                                                ({{ selectedDocIds.length }} selected)
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Trigger Select Button -->
                                    <button @click="toggleSelectAll" :disabled="!filteredDocuments.length" :class="['inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded transition-all border disabled:opacity-50', isAllSelected ? 'bg-red-700 text-white border-red-500 font-bold' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100']">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span>{{ isAllSelected ? 'Deselect All' : 'Trigger Select' }}</span>
                                    </button>

                                    <!-- Download Selected Button -->
                                    <button @click="downloadSelectedDocs" :disabled="!selectedDocIds.length" class="inline-flex items-center gap-1 px-3 py-1 bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-bold rounded transition-colors">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        Download Selected ({{ selectedDocIds.length }})
                                    </button>

                                    <!-- Download All Button -->
                                    <button @click="downloadAllDocs" :disabled="!filteredDocuments.length" class="inline-flex items-center gap-1 px-3 py-1 bg-red-700 hover:bg-red-800 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 text-xs font-bold rounded transition-colors shadow-2xs">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        Download All
                                    </button>

                                    <!-- View Mode Switcher -->
                                    <div class="inline-flex rounded border border-slate-300 bg-white p-0.5">
                                        <button @click="docViewMode = 'grid'" :class="['px-2 py-0.5 rounded text-xs font-semibold transition-colors flex items-center gap-1', docViewMode === 'grid' ? 'bg-slate-100 text-slate-900' : 'text-slate-500 hover:text-slate-800']">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                                            Grid
                                        </button>
                                        <button @click="docViewMode = 'table'" :class="['px-2 py-0.5 rounded text-xs font-semibold transition-colors flex items-center gap-1', docViewMode === 'table' ? 'bg-slate-100 text-slate-900' : 'text-slate-500 hover:text-slate-800']">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                                            Table
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Search & Filter Controls -->
                            <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center justify-between">
                                <div class="relative flex-1 max-w-sm">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <input v-model="docSearch" type="text" placeholder="Search documents..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none bg-white" />
                                </div>

                                <div class="flex items-center gap-1 flex-wrap">
                                    <button v-for="ft in docTypeFilters" :key="ft" @click="docFilter = ft" :class="['px-2.5 py-0.5 rounded text-[11px] font-semibold transition-all uppercase border', docFilter === ft ? 'bg-red-700 text-white border-red-500 font-bold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50']">
                                        {{ ft }}
                                    </button>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-if="filteredDocuments.length === 0" class="text-center py-12 rounded-lg border border-dashed border-slate-200 bg-slate-50/50 space-y-2">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h4 class="text-xs font-semibold text-slate-800">No documents found</h4>
                            </div>

                            <!-- COMPACT GRID VIEW WITH DIRECT IMAGE THUMBNAILS -->
                            <div v-else-if="docViewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                                <div v-for="doc in filteredDocuments" :key="doc.id" @click="toggleSelectDoc(doc.id)" :class="['bg-white rounded-lg border transition-all group overflow-hidden flex flex-col justify-between relative cursor-pointer select-none', selectedDocIds.includes(doc.id) ? 'border-red-500 ring-2 ring-red-500/20 bg-red-50/10' : 'border-slate-200 hover:border-slate-300 hover:shadow-2xs']">
                                    <div>
                                        <!-- Image / File Thumbnail Box (Compact height) -->
                                        <div class="h-24 bg-slate-100 flex items-center justify-center border-b border-slate-100 relative overflow-hidden">
                                            
                                            <!-- Checkbox Overlay -->
                                            <div class="absolute top-2 left-2 z-10" @click.stop>
                                                <input type="checkbox" :checked="selectedDocIds.includes(doc.id)" @change="toggleSelectDoc(doc.id)" class="h-3.5 w-3.5 text-red-600 rounded border-slate-300 focus:ring-red-500 cursor-pointer" />
                                            </div>

                                            <!-- DIRECT IMAGE THUMBNAIL DISPLAY -->
                                            <img
                                                v-if="isImage(doc.type)"
                                                :src="getDocPreviewUrl(doc)"
                                                :alt="doc.name"
                                                class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                @click.stop="openPreview(doc)"
                                            />

                                            <!-- Non-Image SVG Icons -->
                                            <div v-else class="p-2 rounded-lg bg-white border border-slate-200 group-hover:scale-105 transition-transform duration-200" @click.stop="openPreview(doc)">
                                                <svg v-if="isPdf(doc.type)" class="h-7 w-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                                <svg v-else-if="isDoc(doc.type)" class="h-7 w-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                <svg v-else class="h-7 w-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                            </div>

                                            <div class="absolute bottom-1.5 right-1.5">
                                                <span class="inline-flex px-1 py-0.2 rounded bg-white/90 text-[9px] font-mono font-bold text-slate-700 border border-slate-200 uppercase shadow-2xs">
                                                    {{ doc.type }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Document Info -->
                                        <div class="p-2.5 space-y-1">
                                            <p class="text-xs font-bold text-slate-900 truncate group-hover:text-red-700 transition-colors" :title="doc.name">
                                                {{ doc.name }}
                                            </p>
                                            <div class="flex items-center justify-between text-[10px]">
                                                <span :class="[getProcStatus(doc.processingStatus).bg, 'px-1.5 py-0.2 rounded font-semibold border']">
                                                    {{ getProcStatus(doc.processingStatus).label }}
                                                </span>
                                                <span class="text-slate-400 font-medium">{{ formatFileSize(doc.fileSize) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Compact Action Buttons -->
                                    <div class="px-2.5 pb-2 pt-1 border-t border-slate-100 flex items-center justify-between text-[11px]" @click.stop>
                                        <button @click.stop="openPreview(doc)" class="text-slate-600 hover:text-slate-900 font-semibold transition-colors">Preview</button>
                                        <button @click.stop="downloadDoc(doc.id)" class="text-red-700 hover:text-red-950 font-bold transition-colors">Download</button>
                                    </div>
                                </div>
                            </div>

                            <!-- COMPACT TABLE VIEW WITH INLINE IMAGE THUMBNAILS -->
                            <div v-else-if="docViewMode === 'table'" class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                            <th class="py-2 px-3 w-8">
                                                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="h-3.5 w-3.5 text-red-600 rounded border-slate-300 focus:ring-red-500 cursor-pointer" />
                                            </th>
                                            <th class="py-2 px-3">Document Name</th>
                                            <th class="py-2 px-3">Type</th>
                                            <th class="py-2 px-3">Size</th>
                                            <th class="py-2 px-3">Status</th>
                                            <th class="py-2 px-3">Uploaded Date</th>
                                            <th class="py-2 px-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white font-medium text-xs">
                                        <tr v-for="doc in filteredDocuments" :key="doc.id" @click="toggleSelectDoc(doc.id)" :class="['transition-colors cursor-pointer select-none', selectedDocIds.includes(doc.id) ? 'bg-red-50/40' : 'hover:bg-slate-50']">
                                            <td class="py-2 px-3">
                                                <input type="checkbox" :checked="selectedDocIds.includes(doc.id)" @change="toggleSelectDoc(doc.id)" class="h-3.5 w-3.5 text-red-600 rounded border-slate-300 focus:ring-red-500 cursor-pointer" />
                                            </td>
                                            <td class="py-2 px-3">
                                                <div class="flex items-center gap-2">
                                                    <!-- DIRECT THUMBNAIL IN TABLE -->
                                                    <img
                                                        v-if="isImage(doc.type)"
                                                        :src="getDocPreviewUrl(doc)"
                                                        :alt="doc.name"
                                                        class="h-8 w-8 object-cover rounded border border-slate-200 shrink-0"
                                                        @click.stop="openPreview(doc)"
                                                    />
                                                    <svg v-else-if="isPdf(doc.type)" class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                    <svg v-else class="h-4 w-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    
                                                    <span class="font-bold text-slate-900 truncate max-w-xs" :title="doc.name">{{ doc.name }}</span>
                                                </div>
                                            </td>
                                            <td class="py-2 px-3">
                                                <span class="inline-flex px-1.5 py-0.2 rounded bg-slate-100 text-[10px] font-mono font-bold text-slate-600 border border-slate-200 uppercase">
                                                    {{ doc.type }}
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-slate-500">{{ formatFileSize(doc.fileSize) }}</td>
                                            <td class="py-2 px-3">
                                                <span :class="[getProcStatus(doc.processingStatus).bg, 'px-1.5 py-0.2 rounded font-semibold border text-[10px]']">
                                                    {{ getProcStatus(doc.processingStatus).label }}
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-slate-500">{{ formatDate(doc.uploadedAt) }}</td>
                                            <td class="py-2 px-3 text-right" @click.stop>
                                                <div class="inline-flex items-center gap-2">
                                                    <button @click.stop="openPreview(doc)" class="text-slate-600 hover:text-slate-900 font-semibold transition-colors">Preview</button>
                                                    <button @click.stop="downloadDoc(doc.id)" class="text-red-700 hover:text-red-950 font-bold transition-colors">Download</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                        </div>

                    </div>
                </main>
            </div>
        </div>

        <!-- FORMAL PRINTABLE REPORT (VISIBLE ONLY WHEN PRINTING) -->
        <div class="hidden print:block p-8 bg-white text-slate-900 font-sans space-y-6">
            <!-- Formal Header -->
            <div class="border-b-2 border-slate-900 pb-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <img src="/image/meo_logo2.png" alt="Logo" class="h-16 w-16 object-contain" />
                    <div>
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-bold">Republic of the Philippines</p>
                        <h1 class="text-lg font-extrabold text-slate-900 uppercase tracking-tight">Municipal Engineering Office</h1>
                        <p class="text-xs text-slate-600 font-semibold">Infrastructure Project Status & Specification Report</p>
                    </div>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p class="font-bold text-slate-900">DATE GENERATED</p>
                    <p>{{ new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                    <p class="mt-1 font-mono text-[10px]">REF ID: {{ project.id }}</p>
                </div>
            </div>

            <!-- Report Title Banner -->
            <div class="bg-slate-100 p-4 rounded-lg border border-slate-300">
                <h2 class="text-base font-bold text-slate-900 uppercase">{{ project.name }}</h2>
                <div class="grid grid-cols-3 gap-2 mt-2 text-xs text-slate-700">
                    <p><strong>Location:</strong> {{ project.location }}</p>
                    <p><strong>Contractor:</strong> {{ project.contractor || 'N/A' }}</p>
                    <p><strong>Status:</strong> <span class="font-bold uppercase">{{ project.status }}</span> ({{ project.accomplishment }}%)</p>
                </div>
            </div>

            <!-- I. Basic Specifications -->
            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-900 uppercase border-b border-slate-300 pb-1">I. General Project Specifications</h3>
                <table class="w-full text-xs border-collapse border border-slate-300">
                    <tbody>
                        <tr class="border-b border-slate-200">
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300 w-1/4">Project Title</td>
                            <td class="p-2 border-r border-slate-300 w-1/4">{{ project.name }}</td>
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300 w-1/4">Fiscal Year</td>
                            <td class="p-2 w-1/4">{{ project.year }}</td>
                        </tr>
                        <tr class="border-b border-slate-200">
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300">Location</td>
                            <td class="p-2 border-r border-slate-300">{{ project.location }}</td>
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300">Fund Source</td>
                            <td class="p-2">{{ project.sourceOfFund }} ({{ project.fundCategory }})</td>
                        </tr>
                        <tr class="border-b border-slate-200">
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300">Contractor</td>
                            <td class="p-2 border-r border-slate-300">{{ project.contractor || '—' }}</td>
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300">Duration</td>
                            <td class="p-2">{{ project.duration ? project.duration + ' CD' : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300">Time Extension</td>
                            <td class="p-2 border-r border-slate-300">{{ project.timeExtension || 0 }} days</td>
                            <td class="p-2 bg-slate-50 font-bold border-r border-slate-300">Suspension Days</td>
                            <td class="p-2">{{ project.daysSuspensionOrder || 0 }} days</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- II. Financial Breakdown -->
            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-900 uppercase border-b border-slate-300 pb-1">II. Financial Appropriations</h3>
                <table class="w-full text-xs border-collapse border border-slate-300">
                    <thead>
                        <tr class="bg-slate-100 border-b border-slate-300 font-bold text-left">
                            <th class="p-2 border-r border-slate-300">Total Contract Cost</th>
                            <th class="p-2 border-r border-slate-300">Original Appropriation</th>
                            <th class="p-2 border-r border-slate-300">Revised Appropriation</th>
                            <th class="p-2">Revised vs Original</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="p-2 border-r border-slate-300 font-bold">{{ formatCurrency(project.totalCost) }}</td>
                            <td class="p-2 border-r border-slate-300">{{ formatCurrency(project.originalCost) }}</td>
                            <td class="p-2 border-r border-slate-300 font-bold">{{ formatCurrency(project.revisedCost) }}</td>
                            <td class="p-2">{{ project.originalCost && project.revisedCost ? ((project.revisedCost / project.originalCost) * 100).toFixed(1) + '%' : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- III. Technical Preparations Status -->
            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-900 uppercase border-b border-slate-300 pb-1">III. Technical Preparations Checklist</h3>
                <div class="grid grid-cols-4 gap-2 text-xs">
                    <div v-for="item in techPrepItems" :key="item.key" class="p-2 border border-slate-300 rounded bg-slate-50">
                        <p class="font-bold text-slate-700 text-[10px] uppercase">{{ item.label }}</p>
                        <p class="font-extrabold mt-1 text-slate-900 uppercase">{{ getTechStatus(item.status).label }}</p>
                    </div>
                </div>
            </div>

            <!-- IV. Official Remarks & Description -->
            <div class="space-y-3" v-if="project.remarks || project.description">
                <h3 class="text-xs font-bold text-slate-900 uppercase border-b border-slate-300 pb-1">IV. Official Remarks & Scope</h3>
                <div v-if="project.remarks" class="p-3 border border-slate-300 rounded text-xs bg-slate-50">
                    <p class="font-bold text-slate-800">Remarks:</p>
                    <p class="mt-1 text-slate-700">{{ project.remarks }}</p>
                </div>
                <div v-if="project.description" class="p-3 border border-slate-300 rounded text-xs">
                    <p class="font-bold text-slate-800">Description:</p>
                    <p class="mt-1 text-slate-700 italic">{{ project.description }}</p>
                </div>
            </div>

            <!-- V. Certification & Signatures -->
            <div class="pt-12 space-y-12">
                <div class="grid grid-cols-3 gap-8 text-center text-xs">
                    <div>
                        <div class="border-b border-slate-900 pb-1 font-bold">PROJECT ENGINEER / INSPECTOR</div>
                        <p class="text-[10px] text-slate-500 mt-1">Prepared By</p>
                    </div>
                    <div>
                        <div class="border-b border-slate-900 pb-1 font-bold">MUNICIPAL ENGINEER</div>
                        <p class="text-[10px] text-slate-500 mt-1">Checked & Verified By</p>
                    </div>
                    <div>
                        <div class="border-b border-slate-900 pb-1 font-bold">MUNICIPAL MAYOR</div>
                        <p class="text-[10px] text-slate-500 mt-1">Approved By</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Preview Modal -->
        <Transition name="fade">
            <div v-if="previewDoc" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-xs p-4" @click.self="closePreview">
                <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full h-[85vh] flex flex-col overflow-hidden border border-slate-200">
                    
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 bg-white shrink-0">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="p-1.5 bg-red-50 text-red-700 border border-red-200 rounded shrink-0">
                                <svg v-if="isImage(previewDoc.type)" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <svg v-else-if="isPdf(previewDoc.type)" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-xs font-bold text-slate-900 truncate" :title="previewDoc.name">{{ previewDoc.name }}</h3>
                                <p class="text-[10px] text-slate-500 font-medium">
                                    {{ formatFileSize(previewDoc.fileSize) }} • {{ previewDoc.type?.toUpperCase() }} • Uploaded {{ formatDate(previewDoc.uploadedAt) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="downloadDoc(previewDoc.id)" class="inline-flex items-center gap-1 px-3 py-1 bg-red-700 hover:bg-red-800 text-white rounded text-xs font-bold transition-colors">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download
                            </button>
                            <button @click="closePreview" class="text-slate-400 hover:text-slate-700 transition-colors p-1 rounded hover:bg-slate-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 bg-slate-50 p-4 flex items-center justify-center overflow-auto relative">
                        <img v-if="isImage(previewDoc.type)" :src="getDocPreviewUrl(previewDoc)" :alt="previewDoc.name" class="max-w-full max-h-full object-contain rounded border border-slate-200 bg-white" />
                        <iframe v-else-if="isPdf(previewDoc.type)" :src="getDocPreviewUrl(previewDoc)" class="w-full h-full rounded border border-slate-200 bg-white"></iframe>

                        <div v-else class="text-center p-6 max-w-sm bg-white rounded-lg border border-slate-200 space-y-3">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-red-50 text-red-700 border border-red-200">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">{{ previewDoc.name }}</h4>
                                <p class="text-[11px] text-slate-500 mt-1">Direct inline preview is not supported for {{ previewDoc.type?.toUpperCase() }} files.</p>
                            </div>
                            <button @click="downloadDoc(previewDoc.id)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white rounded text-xs font-bold transition-colors">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download File
                            </button>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-5 py-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0 text-[11px] text-slate-500 font-medium">
                        <span>Municipal Engineering Office • Document Repository</span>
                        <button @click="closePreview" class="text-slate-600 hover:text-slate-900 font-semibold">Close</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media print {
    body {
        background-color: white !important;
        color: black !important;
    }
    .print\:hidden {
        display: none !important;
    }
    .print\:block {
        display: block !important;
    }
}
</style>
