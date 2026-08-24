<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Sidebar from './Partials/Sidebar.vue';
import DocumentScanner from '../Admin/Partials/DocumentScanner.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    documents: {
        type: Array,
        default: () => [],
    },
    assignments: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

// Reactive project data
const projectData = ref({ ...props.project });

watch(() => props.project, (newVal) => {
    projectData.value = { ...newVal };
}, { deep: true });

// Active Tab for Project Inner Details
const activeTab = ref('overview'); // 'overview' | 'directives' | 'techprep' | 'documents' | 'timeline'

// Sidebar state
const initialStaffTab = localStorage.getItem('meo_staff_active_tab') || 'projects';
const activeSidebarTab = ref(initialStaffTab);
const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');

const handleCollapseChange = (collapsed) => {
    sidebarCollapsed.value = collapsed;
    localStorage.setItem('meo_sidebar_collapsed', collapsed);
};

// Navigation
const goBack = () => {
    router.visit(route('staff.dashboard'), {
        preserveState: false,
    });
};

const navigateToTab = (tab) => {
    localStorage.setItem('meo_staff_active_tab', tab);
    router.visit(route('staff.dashboard'), {
        preserveState: false,
    });
};

// Print
const printReport = () => {
    window.print();
};

// Formatting helpers
const formatCurrency = (value) => {
    const amount = Number(value);
    if (!Number.isFinite(amount) || amount <= 0) return 'Php 0.00';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(amount);
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

const formatNumberWithCommas = (value) => {
    const stringValue = String(value || '');
    const rawValue = stringValue.replace(/,/g, '').replace(/[^\d.]/g, '');
    if (rawValue === '') return '';
    const [integer, decimal] = rawValue.split('.');
    const formattedInt = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return decimal !== undefined ? `${formattedInt}.${decimal}` : formattedInt;
};

// Status Configurations
const statusConfig = {
    'Completed':   { bg: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    'Ongoing':     { bg: 'bg-blue-50 text-blue-700 border-blue-200',       dot: 'bg-blue-500' },
    'Delayed':     { bg: 'bg-rose-50 text-rose-700 border-rose-200',         dot: 'bg-rose-500' },
    'Suspended':   { bg: 'bg-amber-50 text-amber-700 border-amber-200',     dot: 'bg-amber-500' },
    'Not Started': { bg: 'bg-slate-100 text-slate-700 border-slate-200',     dot: 'bg-slate-400' },
};
const getStatusConfig = (s) => statusConfig[s] || statusConfig['Ongoing'];

const getProgressGradient = (progress) => {
    const p = Number(progress) || 0;
    if (p >= 100) return 'from-emerald-500 to-emerald-600';
    if (p >= 70) return 'from-blue-500 to-emerald-500';
    if (p >= 30) return 'from-blue-500 to-blue-600';
    return 'from-amber-500 to-blue-500';
};

// Technical Preparations
const techPrepItems = computed(() => {
    const tp = projectData.value.technical_preparations;
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
        key,
        label,
        status: tp[key]?.status ?? '',
        notes: tp[key]?.notes ?? '',
        updatedAt: tp[key]?.updatedAt ?? '',
    }));
});

const techStatusConfig = {
    green:  { bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', label: 'Completed / Ready', dot: 'bg-emerald-500' },
    yellow: { bg: 'bg-amber-50 text-amber-800 border-amber-200',     label: 'In Progress / Pending', dot: 'bg-amber-500' },
    red:    { bg: 'bg-rose-50 text-rose-800 border-rose-200',         label: 'Issues / Delayed', dot: 'bg-rose-500' },
    na:     { bg: 'bg-slate-100 text-slate-600 border-slate-200',        label: 'Not Applicable', dot: 'bg-slate-400' },
    '':     { bg: 'bg-slate-50 text-slate-400 border-slate-200',          label: 'Not Started', dot: 'bg-slate-300' },
};
const getTechStatus = (s) => techStatusConfig[s] || techStatusConfig[''];

// Directives / Staff Assignments
const projectAssignments = ref([...props.assignments]);
const updatingAssignmentId = ref(null);

const updateAssignmentStatus = async (item, newStatus) => {
    updatingAssignmentId.value = item.id;
    try {
        let endpoint = `/staff-assignments/${item.id}/status`;
        try {
            if (typeof route === 'function' && route().has && route().has('staff-assignments.status')) {
                endpoint = route('staff-assignments.status', item.id);
            }
        } catch (e) {}

        const res = await axios.patch(endpoint, { status: newStatus });
        if (res.data?.assignment) {
            const idx = projectAssignments.value.findIndex(a => a.id === item.id);
            if (idx > -1) {
                projectAssignments.value[idx] = res.data.assignment;
            }
        }
    } catch (e) {
        console.error('Failed to update directive status:', e);
    } finally {
        updatingAssignmentId.value = null;
    }
};

// Directives Reply State
const replyingAssignment = ref(null);
const replyModalOpen = ref(false);
const replyForm = ref({
    staff_reply: '',
    status: 'in_progress'
});
const isSubmittingReply = ref(false);

const openReplyModal = (item) => {
    replyingAssignment.value = item;
    replyForm.value = {
        staff_reply: item.staffReply || '',
        status: item.status || 'in_progress',
    };
    replyModalOpen.value = true;
};

const closeReplyModal = () => {
    replyModalOpen.value = false;
    replyingAssignment.value = null;
    replyForm.value = {
        staff_reply: '',
        status: 'in_progress',
    };
};

const handleSaveReply = async () => {
    if (!replyingAssignment.value) return;
    isSubmittingReply.value = true;
    try {
        let endpoint = `/staff-assignments/${replyingAssignment.value.id}/reply`;
        try {
            if (typeof route === 'function' && route().has && route().has('staff-assignments.reply')) {
                endpoint = route('staff-assignments.reply', replyingAssignment.value.id);
            }
        } catch (e) {}

        const res = await axios.patch(endpoint, {
            staff_reply: replyForm.value.staff_reply,
            status: replyForm.value.status,
        });

        if (res.data?.assignment) {
            const idx = projectAssignments.value.findIndex(a => a.id === replyingAssignment.value.id);
            if (idx > -1) {
                projectAssignments.value[idx] = res.data.assignment;
            }
        }

        closeReplyModal();
    } catch (e) {
        console.error('Failed to submit reply to directive:', e);
        alert(e.response?.data?.message || 'Failed to submit reply. Please try again.');
    } finally {
        isSubmittingReply.value = false;
    }
};

const myProjectDirectives = computed(() => {
    if (!currentUser.value?.id) return projectAssignments.value;
    return projectAssignments.value.filter(a => Number(a.userId) === Number(currentUser.value.id));
});

// Timeline Events
const timelineEvents = computed(() => [
    { label: 'Project Encoded',    date: projectData.value.createdAt,             type: 'created', note: 'Project registered in system' },
    { label: 'Official Start Date', date: projectData.value.startDate,             type: 'start', note: 'Implementation commenced' },
    { label: 'Target Completion',  date: projectData.value.targetCompletionDate,  type: 'target', note: 'Scheduled completion date' },
    { label: 'Revised Completion', date: projectData.value.revisedCompletionDate, type: 'revised', note: 'Extension adjustment' },
    { label: 'Actual Completion',  date: projectData.value.actualCompletionDate,  type: 'actual', note: 'Turnover & acceptance' },
].filter(e => !!e.date));

// ==========================================
// ACCOMPLISHMENT QUICK UPDATE MODAL
// ==========================================
const showAccomplishmentModal = ref(false);
const isSavingAccomplishment = ref(false);
const accomplishmentForm = reactive({
    accomplishment: 0,
    status: 'Ongoing',
    remarks: '',
});

const openAccomplishmentModal = () => {
    accomplishmentForm.accomplishment = Number(projectData.value.accomplishment || 0);
    accomplishmentForm.status = projectData.value.status || 'Ongoing';
    accomplishmentForm.remarks = projectData.value.remarks || '';
    showAccomplishmentModal.value = true;
};

const closeAccomplishmentModal = () => {
    showAccomplishmentModal.value = false;
};

const setAccomplishmentPreset = (val) => {
    accomplishmentForm.accomplishment = Math.min(Math.max(val, 0), 100);
    if (accomplishmentForm.accomplishment >= 100) {
        accomplishmentForm.status = 'Completed';
    }
};

const adjustAccomplishment = (delta) => {
    const cur = Number(accomplishmentForm.accomplishment || 0);
    accomplishmentForm.accomplishment = Math.min(Math.max(Number((cur + delta).toFixed(1)), 0), 100);
    if (accomplishmentForm.accomplishment >= 100) {
        accomplishmentForm.status = 'Completed';
    }
};

const saveAccomplishment = async () => {
    isSavingAccomplishment.value = true;
    try {
        let endpoint = `/staff/projects/${projectData.value.id}/accomplishment`;
        try {
            if (typeof route === 'function' && route().has && route().has('staff.projects.accomplishment')) {
                endpoint = route('staff.projects.accomplishment', projectData.value.id);
            }
        } catch (e) {}

        const res = await axios.patch(endpoint, {
            accomplishment: Number(accomplishmentForm.accomplishment),
            status: accomplishmentForm.status,
            remarks: accomplishmentForm.remarks,
        });

        if (res.data?.project) {
            projectData.value = { ...projectData.value, ...res.data.project };
        } else {
            projectData.value.accomplishment = Number(accomplishmentForm.accomplishment);
            projectData.value.status = accomplishmentForm.status;
            if (accomplishmentForm.remarks) {
                projectData.value.remarks = accomplishmentForm.remarks;
            }
        }

        closeAccomplishmentModal();
    } catch (err) {
        console.error('Error saving accomplishment:', err);
        alert(err.response?.data?.message || 'Failed to update accomplishment. Please try again.');
    } finally {
        isSavingAccomplishment.value = false;
    }
};

// ==========================================
// PROJECT SPECIFICATIONS EDIT MODAL
// ==========================================
const showSpecsModal = ref(false);
const isSavingSpecs = ref(false);
const specsErrors = ref({});
const statusOptions = ['Not Started', 'Ongoing', 'Completed', 'Suspended', 'Delayed'];

const fundCategoryOptions = [
    { value: 'national', label: 'National Funded' },
    { value: 'provincial', label: 'Provincial Local Funded' },
    { value: 'lgu', label: 'LGU Funded' },
    { value: 'uncategorized', label: 'Uncategorized' },
];

const availableFundSources = ref([]);
const isLoadingSources = ref(false);

const specsForm = reactive({
    name: '',
    location: '',
    totalCost: '',
    totalCostDisplay: '',
    originalCost: '',
    originalCostDisplay: '',
    revisedCost: '',
    revisedCostDisplay: '',
    description: '',
    fundCategory: 'lgu',
    sourceOfFund: '',
    year: new Date().getFullYear(),
    duration: 0,
    startDate: '',
    targetCompletionDate: '',
    actualCompletionDate: '',
    revisedCompletionDate: '',
    timeExtension: 0,
    daysSuspensionOrder: 0,
    accomplishment: 0,
    contractor: '',
    remarks: '',
    status: 'Ongoing',
});

const fetchFundSources = async (category) => {
    isLoadingSources.value = true;
    try {
        let endpoint = `/staff/projects/fund-sources?category=${category}`;
        try {
            if (typeof route === 'function' && route().has && route().has('staff.projects.fund-sources')) {
                endpoint = route('staff.projects.fund-sources', { category });
            } else if (typeof route === 'function' && route().has && route().has('admin.projects.fund-sources')) {
                endpoint = route('admin.projects.fund-sources', { category });
            }
        } catch (e) {}

        const res = await axios.get(endpoint);
        availableFundSources.value = (res.data?.sources || []).map(s => typeof s === 'string' ? s : s.source).filter(Boolean);
    } catch (e) {
        console.error('Failed to load fund sources:', e);
        availableFundSources.value = [];
    } finally {
        isLoadingSources.value = false;
    }
};

const handleFundCategoryChange = async () => {
    specsForm.sourceOfFund = '';
    await fetchFundSources(specsForm.fundCategory);
    if (availableFundSources.value.length > 0 && !specsForm.sourceOfFund) {
        specsForm.sourceOfFund = availableFundSources.value[0];
    }
};

const openSpecsModal = async () => {
    specsErrors.value = {};
    const p = projectData.value;
    
    // Populate form
    specsForm.name = p.name || '';
    specsForm.location = p.location || '';
    specsForm.totalCost = p.totalCost || '';
    specsForm.totalCostDisplay = formatNumberWithCommas(p.totalCost);
    specsForm.originalCost = p.originalCost || '';
    specsForm.originalCostDisplay = p.originalCost ? formatNumberWithCommas(p.originalCost) : '';
    specsForm.revisedCost = p.revisedCost || '';
    specsForm.revisedCostDisplay = p.revisedCost ? formatNumberWithCommas(p.revisedCost) : '';
    specsForm.description = p.description || '';
    specsForm.fundCategory = (p.fundCategory || 'lgu').toLowerCase();
    specsForm.sourceOfFund = p.sourceOfFund || '';
    specsForm.year = p.year || new Date().getFullYear();
    specsForm.duration = p.duration || 0;
    specsForm.startDate = p.startDate || '';
    specsForm.targetCompletionDate = p.targetCompletionDate || '';
    specsForm.actualCompletionDate = p.actualCompletionDate || '';
    specsForm.revisedCompletionDate = p.revisedCompletionDate || '';
    specsForm.timeExtension = p.timeExtension || 0;
    specsForm.daysSuspensionOrder = p.daysSuspensionOrder || 0;
    specsForm.accomplishment = p.accomplishment || 0;
    specsForm.contractor = p.contractor || '';
    specsForm.remarks = p.remarks || '';
    specsForm.status = p.status || 'Ongoing';

    showSpecsModal.value = true;
    await fetchFundSources(specsForm.fundCategory);
    if (specsForm.sourceOfFund && !availableFundSources.value.includes(specsForm.sourceOfFund)) {
        availableFundSources.value.push(specsForm.sourceOfFund);
    }
};

const closeSpecsModal = () => {
    showSpecsModal.value = false;
    specsErrors.value = {};
};

const saveSpecs = async () => {
    isSavingSpecs.value = true;
    specsErrors.value = {};

    const totalCostVal = String(specsForm.totalCostDisplay || '').replace(/,/g, '');
    const originalCostVal = String(specsForm.originalCostDisplay || '').replace(/,/g, '');
    const revisedCostVal = String(specsForm.revisedCostDisplay || '').replace(/,/g, '');

    const payload = {
        name: specsForm.name,
        location: specsForm.location,
        totalCost: Number(totalCostVal || 0),
        originalCost: originalCostVal ? Number(originalCostVal) : null,
        revisedCost: revisedCostVal ? Number(revisedCostVal) : null,
        description: specsForm.description,
        fundCategory: specsForm.fundCategory,
        sourceOfFund: specsForm.sourceOfFund || 'LGU General Fund',
        year: Number(specsForm.year),
        duration: Number(specsForm.duration || 0),
        startDate: specsForm.startDate,
        targetCompletionDate: specsForm.targetCompletionDate,
        actualCompletionDate: specsForm.actualCompletionDate || null,
        revisedCompletionDate: specsForm.revisedCompletionDate || null,
        timeExtension: Number(specsForm.timeExtension || 0),
        daysSuspensionOrder: Number(specsForm.daysSuspensionOrder || 0),
        accomplishment: Number(specsForm.accomplishment || 0),
        contractor: specsForm.contractor,
        remarks: specsForm.remarks || null,
        status: specsForm.status,
    };

    try {
        let endpoint = `/staff/projects/${projectData.value.id}`;
        try {
            if (typeof route === 'function' && route().has && route().has('staff.projects.update')) {
                endpoint = route('staff.projects.update', projectData.value.id);
            } else if (typeof route === 'function' && route().has && route().has('admin.projects.update')) {
                endpoint = route('admin.projects.update', projectData.value.id);
            }
        } catch (e) {}

        const res = await axios.put(endpoint, payload);
        if (res.data?.project) {
            projectData.value = { ...projectData.value, ...res.data.project };
        } else {
            projectData.value = { ...projectData.value, ...payload };
        }

        closeSpecsModal();
    } catch (err) {
        if (err.response?.status === 422) {
            specsErrors.value = err.response.data.errors || {};
        } else {
            console.error('Failed to update project specifications:', err);
            alert(err.response?.data?.message || 'Failed to save project specifications.');
        }
    } finally {
        isSavingSpecs.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <Head :title="`${projectData.name} — Staff Project Details`" />

        <div class="flex">
            <!-- Sidebar with dynamic/persisted activeTab -->
            <Sidebar
                class="print:hidden"
                :activeTab="activeSidebarTab"
                @tab-change="navigateToTab"
                @collapse-change="handleCollapseChange"
            />

            <div
                :class="[
                    'flex-1 flex flex-col min-h-screen transition-all duration-200 min-w-0',
                    sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64'
                ]"
            >
                <!-- Sticky Top Header (Mobile Optimized) -->
                <header class="bg-white border-b border-slate-200 sticky top-0 z-20 shadow-2xs print:hidden">
                    <div class="px-3.5 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 sm:gap-3">
                            <button
                                type="button"
                                @click="goBack"
                                class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 bg-slate-100 hover:bg-red-50 text-slate-700 hover:text-red-700 text-xs font-bold border border-slate-200 hover:border-red-200 transition shrink-0"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span>Back</span>
                            </button>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h1 class="text-xs sm:text-base font-bold text-slate-900 truncate">Municipal Engineering Office</h1>
                                    <span class="px-1.5 sm:px-2 py-0.5 bg-red-100 text-red-800 text-[9px] sm:text-[10px] font-extrabold uppercase tracking-wider shrink-0">
                                        Staff Operations
                                    </span>
                                </div>
                                <p class="text-[11px] sm:text-xs text-slate-500 truncate">Inspection & Field Oversight</p>
                            </div>
                        </div>

                        <!-- Top Action Buttons (Mobile Grid) -->
                        <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 w-full sm:w-auto">
                            <button
                                type="button"
                                @click="openAccomplishmentModal"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs active:scale-[0.99]"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span>Progress</span>
                            </button>

                            <button
                                type="button"
                                @click="openSpecsModal"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition shadow-xs active:scale-[0.99]"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Edit Specs</span>
                            </button>

                            <button
                                type="button"
                                @click="printReport"
                                class="col-span-2 sm:col-span-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-bold transition shadow-xs active:scale-[0.99]"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                <span>Print Summary</span>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Main Content Body -->
                <main class="flex-1 px-3.5 sm:px-6 py-4 sm:py-5 space-y-4 sm:space-y-5 min-w-0">
                    <!-- Project Hero Card -->
                    <div class="bg-white border border-slate-200 shadow-xs p-4 sm:p-5 space-y-4">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 lg:gap-5">
                            <div class="space-y-2 flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-base sm:text-xl font-bold text-slate-900 tracking-tight break-words">
                                        {{ projectData.name }}
                                    </h2>
                                    <span
                                        :class="[
                                            getStatusConfig(projectData.status).bg,
                                            'inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] sm:text-xs font-bold border shrink-0'
                                        ]"
                                    >
                                        <span class="w-1.5 h-1.5" :class="getStatusConfig(projectData.status).dot"></span>
                                        {{ projectData.status }}
                                    </span>
                                    <span
                                        v-if="myProjectDirectives.length > 0"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] sm:text-xs font-bold bg-red-50 text-red-700 border border-red-200 shrink-0"
                                    >
                                        ⭐ {{ myProjectDirectives.length }} Task{{ myProjectDirectives.length > 1 ? 's' : '' }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-500">
                                    <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="break-words">{{ projectData.location || 'Location Unspecified' }}</span>
                                    </span>
                                    <span class="text-slate-300">•</span>
                                    <span class="px-1.5 py-0.5 bg-slate-100 text-slate-700 font-semibold text-[10px] sm:text-[11px] border border-slate-200">
                                        {{ projectData.fundCategory || projectData.sourceOfFund || 'LGU Funded' }}
                                    </span>
                                    <span v-if="projectData.year" class="text-slate-300">•</span>
                                    <span v-if="projectData.year" class="text-slate-600">
                                        FY: <strong class="text-slate-800">{{ projectData.year }}</strong>
                                    </span>
                                    <template v-if="projectData.contractor">
                                        <span class="text-slate-300">•</span>
                                        <span class="text-slate-600 break-words">
                                            Contractor: <strong class="text-slate-900">{{ projectData.contractor }}</strong>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <!-- Interactive Accomplishment Box -->
                            <div
                                @click="openAccomplishmentModal"
                                class="bg-slate-50 hover:bg-slate-100/80 border border-slate-200 p-3.5 sm:p-4 w-full lg:w-72 shrink-0 space-y-2 cursor-pointer group transition"
                                title="Click to update accomplishment"
                            >
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px] sm:text-[11px] flex items-center gap-1.5">
                                        Accomplishment
                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-red-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                    <span class="text-sm sm:text-base font-extrabold text-slate-900 group-hover:text-red-700 transition">
                                        {{ Number(projectData.accomplishment || 0).toFixed(1) }}%
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 h-2 overflow-hidden">
                                    <div
                                        class="h-full bg-gradient-to-r transition-all duration-500"
                                        :class="getProgressGradient(projectData.accomplishment)"
                                        :style="{ width: `${Math.min(Number(projectData.accomplishment || 0), 100)}%` }"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[10px] sm:text-[11px] text-slate-500 pt-0.5">
                                    <span>Target: 100%</span>
                                    <span class="font-bold text-red-700 text-[10px] uppercase group-hover:underline">Quick Update</span>
                                </div>
                            </div>
                        </div>

                        <!-- Metric Summary Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 pt-3 border-t border-slate-100">
                            <div class="p-2.5 sm:p-3 bg-slate-50 border border-slate-200">
                                <p class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Budget</p>
                                <p class="text-xs sm:text-base font-bold text-slate-900 mt-0.5 truncate">{{ formatCurrency(projectData.totalCost) }}</p>
                            </div>
                            <div class="p-2.5 sm:p-3 bg-slate-50 border border-slate-200">
                                <p class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider">Duration</p>
                                <p class="text-xs sm:text-base font-bold text-slate-900 mt-0.5">{{ projectData.duration ? `${projectData.duration} CD` : '—' }}</p>
                            </div>
                            <div class="p-2.5 sm:p-3 bg-slate-50 border border-slate-200">
                                <p class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider">Start Date</p>
                                <p class="text-xs sm:text-base font-bold text-slate-900 mt-0.5">{{ formatDate(projectData.startDate) }}</p>
                            </div>
                            <div class="p-2.5 sm:p-3 bg-slate-50 border border-slate-200">
                                <p class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider">Target Completion</p>
                                <p class="text-xs sm:text-base font-bold text-slate-900 mt-0.5">{{ formatDate(projectData.targetCompletionDate) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Inner Navigation Tabs (Horizontally Scrollable on Mobile) -->
                    <div class="border-b border-slate-200 bg-white px-2 flex items-center gap-1 overflow-x-auto shadow-2xs no-scrollbar">
                        <button
                            type="button"
                            @click="activeTab = 'overview'"
                            :class="[
                                activeTab === 'overview'
                                    ? 'border-red-700 text-red-700 font-bold bg-red-50/50'
                                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium',
                                'px-3 sm:px-4 py-2.5 sm:py-3 text-xs border-b-2 transition whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Overview & Specs</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'directives'"
                            :class="[
                                activeTab === 'directives'
                                    ? 'border-red-700 text-red-700 font-bold bg-red-50/50'
                                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium',
                                'px-3 sm:px-4 py-2.5 sm:py-3 text-xs border-b-2 transition whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            <span>Directives ({{ projectAssignments.length }})</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'techprep'"
                            :class="[
                                activeTab === 'techprep'
                                    ? 'border-red-700 text-red-700 font-bold bg-red-50/50'
                                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium',
                                'px-3 sm:px-4 py-2.5 sm:py-3 text-xs border-b-2 transition whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Technical Preparations</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'documents'"
                            :class="[
                                activeTab === 'documents'
                                    ? 'border-red-700 text-red-700 font-bold bg-red-50/50'
                                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium',
                                'px-3 sm:px-4 py-2.5 sm:py-3 text-xs border-b-2 transition whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            <span>Documents & Scans</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'timeline'"
                            :class="[
                                activeTab === 'timeline'
                                    ? 'border-red-700 text-red-700 font-bold bg-red-50/50'
                                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium',
                                'px-3 sm:px-4 py-2.5 sm:py-3 text-xs border-b-2 transition whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0'
                            ]"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Timeline</span>
                        </button>
                    </div>

                    <!-- TAB 1: Overview & Specifications -->
                    <div v-if="activeTab === 'overview'" class="space-y-4">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <!-- Left 2 Cols: Main Info -->
                            <div class="lg:col-span-2 space-y-4">
                                <!-- Description Card -->
                                <div class="bg-white border border-slate-200 p-4 sm:p-5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Scope & Project Description
                                        </h3>
                                        <button
                                            type="button"
                                            @click="openSpecsModal"
                                            class="text-xs text-red-700 font-bold hover:underline"
                                        >
                                            Edit Scope
                                        </button>
                                    </div>
                                    <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line bg-slate-50 p-3 sm:p-4 border border-slate-200 break-words">
                                        {{ projectData.description || 'No detailed scope of work or description specified for this project.' }}
                                    </p>
                                </div>

                                <!-- Financial Cost Breakdown -->
                                <div class="bg-white border border-slate-200 p-4 sm:p-5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Financial Breakdown & Funding
                                        </h3>
                                        <button
                                            type="button"
                                            @click="openSpecsModal"
                                            class="text-xs text-red-700 font-bold hover:underline"
                                        >
                                            Edit Financials
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3">
                                        <div class="p-2.5 sm:p-3 bg-slate-50 border border-slate-200">
                                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase">Original Budget</span>
                                            <p class="text-xs sm:text-sm font-bold text-slate-900 mt-1 truncate">
                                                {{ projectData.originalCost ? formatCurrency(projectData.originalCost) : formatCurrency(projectData.totalCost) }}
                                            </p>
                                        </div>
                                        <div class="p-2.5 sm:p-3 bg-slate-50 border border-slate-200">
                                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase">Revised Budget</span>
                                            <p class="text-xs sm:text-sm font-bold text-slate-900 mt-1 truncate">
                                                {{ projectData.revisedCost ? formatCurrency(projectData.revisedCost) : '—' }}
                                            </p>
                                        </div>
                                        <div class="p-2.5 sm:p-3 bg-slate-50 border border-slate-200">
                                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase">Source of Fund</span>
                                            <p class="text-xs sm:text-sm font-bold text-slate-900 mt-1 break-words">
                                                {{ projectData.sourceOfFund || 'LGU General Fund' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Remarks & Notes -->
                                <div class="bg-white border border-slate-200 p-4 sm:p-5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                            Engineering & Field Remarks
                                        </h3>
                                        <button
                                            type="button"
                                            @click="openAccomplishmentModal"
                                            class="text-xs text-red-700 font-bold hover:underline"
                                        >
                                            Update Remarks
                                        </button>
                                    </div>
                                    <p class="text-xs sm:text-sm text-slate-700 bg-slate-50 p-3 sm:p-4 border border-slate-200 break-words">
                                        {{ projectData.remarks || 'No active remarks recorded.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Right 1 Col: Key Metadata Sidebar & Specifications -->
                            <div class="space-y-4">
                                <div class="bg-white border border-slate-200 p-4 sm:p-5 space-y-3">
                                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                            Project Specifications
                                        </h3>
                                        <button
                                            type="button"
                                            @click="openSpecsModal"
                                            class="text-[11px] text-red-700 font-bold hover:underline flex items-center gap-1"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            Edit
                                        </button>
                                    </div>

                                    <dl class="divide-y divide-slate-100 text-xs space-y-2">
                                        <div class="pt-2 flex justify-between gap-2 items-start">
                                            <dt class="text-slate-500 shrink-0">Contractor</dt>
                                            <dd class="font-bold text-slate-800 text-right break-words">{{ projectData.contractor || 'In-House' }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Duration</dt>
                                            <dd class="font-bold text-slate-800">{{ projectData.duration ? `${projectData.duration} Calendar Days` : '0 CD' }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Start Date</dt>
                                            <dd class="font-bold text-slate-800">{{ formatDate(projectData.startDate) }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Target Completion</dt>
                                            <dd class="font-bold text-slate-800">{{ formatDate(projectData.targetCompletionDate) }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Revised Completion</dt>
                                            <dd class="font-bold text-slate-800">{{ formatDate(projectData.revisedCompletionDate) }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Actual Completion</dt>
                                            <dd class="font-bold text-slate-800">{{ formatDate(projectData.actualCompletionDate) }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Time Extension</dt>
                                            <dd class="font-bold text-slate-800">{{ projectData.timeExtension ? `${projectData.timeExtension} Days` : '0 Days' }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Suspension Order</dt>
                                            <dd class="font-bold text-slate-800">{{ projectData.daysSuspensionOrder ? `${projectData.daysSuspensionOrder} Days` : '0 Days' }}</dd>
                                        </div>
                                        <div class="pt-2 flex justify-between gap-2 items-center">
                                            <dt class="text-slate-500 shrink-0">Fund Category</dt>
                                            <dd class="font-bold text-slate-800 uppercase">{{ projectData.fundCategory || 'LGU' }}</dd>
                                        </div>
                                    </dl>

                                    <div class="pt-3 border-t border-slate-100">
                                        <button
                                            type="button"
                                            @click="openSpecsModal"
                                            class="w-full py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700 transition flex items-center justify-center gap-1.5 active:scale-[0.99]"
                                        >
                                            <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            <span>Update Full Specifications</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: Staff Directives & Assigned Tasks -->
                    <div v-if="activeTab === 'directives'" class="space-y-4">
                        <div class="bg-white border border-slate-200 p-4 sm:p-5 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-3">
                                <div>
                                    <h3 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                        Assigned Directives & Project Tasks
                                    </h3>
                                    <p class="text-xs text-slate-500">Instructions, roles, and action items designated for staff execution.</p>
                                </div>
                                <span class="px-2.5 py-1 text-xs font-bold bg-red-100 text-red-800 border border-red-200 self-start sm:self-auto">
                                    {{ projectAssignments.length }} Total Record{{ projectAssignments.length > 1 ? 's' : '' }}
                                </span>
                            </div>

                            <div v-if="projectAssignments.length === 0" class="py-10 sm:py-12 text-center border border-dashed border-slate-300 space-y-2 p-4">
                                <p class="text-sm font-bold text-slate-800">No directives assigned to this project yet.</p>
                                <p class="text-xs text-slate-500">When administration assigns inspection duties or project tasks, they will appear here.</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in projectAssignments"
                                    :key="item.id"
                                    class="p-3.5 sm:p-4 bg-slate-50 border border-slate-200 space-y-3 transition hover:bg-slate-100/60"
                                >
                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2.5">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 break-words">{{ item.title }}</h4>
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 font-bold uppercase"
                                                    :class="[
                                                        item.priority === 'urgent' ? 'bg-red-100 text-red-700 border border-red-200' :
                                                        item.priority === 'high' ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                                                        'bg-slate-100 text-slate-700'
                                                    ]"
                                                >
                                                    {{ item.priority || 'normal' }}
                                                </span>
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 font-semibold"
                                                    :class="[
                                                        item.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                                        item.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                                        'bg-amber-100 text-amber-800'
                                                    ]"
                                                >
                                                    {{ item.status === 'in_progress' ? 'In Progress' : item.status }}
                                                </span>
                                            </div>
                                            <p v-if="item.userName" class="text-xs text-slate-600 break-words">
                                                Assigned to: <strong class="text-slate-900">{{ item.userName }}</strong>
                                                <span v-if="item.roleInProject" class="text-slate-400"> ({{ item.roleInProject }})</span>
                                            </p>
                                        </div>

                                        <!-- Update Status Dropdown for Staff -->
                                        <div class="flex items-center gap-2 self-start sm:self-auto">
                                            <span class="text-xs text-slate-500 font-semibold sm:hidden">Status:</span>
                                            <select
                                                :value="item.status"
                                                :disabled="updatingAssignmentId === item.id"
                                                @change="updateAssignmentStatus(item, $event.target.value)"
                                                class="text-xs font-semibold border border-slate-300 py-1.5 pl-2.5 pr-7 bg-white focus:ring-red-500 focus:border-red-500"
                                            >
                                                <option value="pending">Pending</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="completed">Completed</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                    </div>

                                    <p v-if="item.note" class="text-xs text-slate-700 bg-white p-2.5 sm:p-3 border border-slate-200 leading-relaxed break-words">
                                        <strong class="font-bold text-slate-800">Admin Directive:</strong> {{ item.note }}
                                    </p>

                                    <!-- Staff Reply / Feedback (Visible to Admin) -->
                                    <div v-if="item.staffReply" class="bg-emerald-50 border border-emerald-200 p-2.5 sm:p-3 text-xs space-y-1.5">
                                        <div class="flex items-center justify-between flex-wrap gap-1">
                                            <span class="font-bold text-emerald-800 flex items-center gap-1.5 text-[11px]">
                                                <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                                Staff Response / Field Note (Visible to Admin)
                                            </span>
                                            <span v-if="item.staffRepliedAt" class="text-[10px] text-emerald-600 font-semibold">{{ item.staffRepliedAt }}</span>
                                        </div>
                                        <p class="text-slate-800 whitespace-pre-line leading-relaxed break-words">{{ item.staffReply }}</p>
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-slate-500 pt-2 border-t border-slate-200/60">
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <span v-if="item.targetDeadline">Deadline: <strong class="text-slate-800">{{ item.targetDeadline }}</strong></span>
                                            <span v-if="item.assignerName">Assigned by: <strong class="text-slate-800">{{ item.assignerName }}</strong></span>
                                        </div>

                                        <button
                                            type="button"
                                            @click="openReplyModal(item)"
                                            class="w-full sm:w-auto justify-center inline-flex items-center gap-1.5 px-3 py-1 bg-white hover:bg-slate-100 border border-slate-300 text-slate-700 font-bold text-xs transition shadow-2xs"
                                        >
                                            <svg class="w-3.5 h-3.5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                            <span>{{ item.staffReply ? 'Edit Reply / Note to Admin' : 'Reply / Add Note to Admin' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: Technical Preparations -->
                    <div v-if="activeTab === 'techprep'" class="space-y-4">
                        <div class="bg-white border border-slate-200 p-4 sm:p-5 space-y-4">
                            <div>
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Technical Preparations Checklist
                                </h3>
                                <p class="text-xs text-slate-500">Readiness and compliance stages for project implementation.</p>
                            </div>

                            <div v-if="techPrepItems.length === 0" class="py-10 sm:py-12 text-center text-xs text-slate-400 border border-dashed border-slate-300 p-4">
                                No technical preparation data available for this project.
                            </div>

                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                                <div
                                    v-for="item in techPrepItems"
                                    :key="item.key"
                                    class="p-3.5 sm:p-4 bg-slate-50 border border-slate-200 space-y-2"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="text-xs font-bold text-slate-900 truncate">{{ item.label }}</h4>
                                        <span
                                            :class="[
                                                getTechStatus(item.status).bg,
                                                'inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 text-[10px] font-bold border shrink-0'
                                            ]"
                                        >
                                            <span class="w-1.5 h-1.5" :class="getTechStatus(item.status).dot"></span>
                                            {{ getTechStatus(item.status).label }}
                                        </span>
                                    </div>
                                    <p v-if="item.notes" class="text-xs text-slate-600 bg-white p-2 border border-slate-200 break-words">
                                        {{ item.notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: Documents & Scans -->
                    <div v-if="activeTab === 'documents'" class="space-y-4">
                        <DocumentScanner
                            :project-id="projectData.id || project.id"
                            :project-name="projectData.name || projectData.project_name || project.name || project.project_name"
                            :techprep-id="projectData.techprep?.id || project.techprep?.id"
                            :is-editable="true"
                        />
                    </div>

                    <!-- TAB 5: Schedule & Timeline -->
                    <div v-if="activeTab === 'timeline'" class="space-y-4">
                        <div class="bg-white border border-slate-200 p-4 sm:p-5 space-y-4">
                            <div>
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Project Milestones & Implementation Schedule
                                </h3>
                                <p class="text-xs text-slate-500">Chronological progression and target dates for project delivery.</p>
                            </div>

                            <div class="relative pl-5 sm:pl-6 space-y-5 sm:space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 pt-2">
                                <div
                                    v-for="(event, idx) in timelineEvents"
                                    :key="idx"
                                    class="relative space-y-1"
                                >
                                    <span class="absolute -left-5 sm:-left-6 top-1 w-3 h-3 bg-red-600 border-2 border-white shadow-2xs"></span>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-xs font-bold text-slate-900">{{ event.label }}</h4>
                                        <span class="text-xs font-semibold text-red-700">{{ formatDate(event.date) }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 break-words">{{ event.note }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL 1: QUICK ACCOMPLISHMENT UPDATE       -->
        <!-- ========================================== -->
        <div
            v-if="showAccomplishmentModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-xs p-3 sm:p-4"
            @click.self="closeAccomplishmentModal"
        >
            <div class="bg-white max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[92vh] overflow-y-auto">
                <div class="px-4 sm:px-5 py-3.5 sm:py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs shrink-0">
                            %
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 truncate">Update Accomplishment</h3>
                            <p class="text-[10px] sm:text-[11px] text-slate-500 truncate">{{ projectData.name }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeAccomplishmentModal"
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="saveAccomplishment" class="p-4 sm:p-5 space-y-3.5 sm:space-y-4 text-xs">
                    <!-- Accomplishment Input & Slider -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-slate-700">Accomplishment Percentage (%)</label>
                            <span class="text-sm sm:text-base font-extrabold text-red-700">{{ Number(accomplishmentForm.accomplishment || 0).toFixed(1) }}%</span>
                        </div>

                        <input
                            type="range"
                            min="0"
                            max="100"
                            step="0.1"
                            v-model="accomplishmentForm.accomplishment"
                            class="w-full accent-red-700 cursor-pointer h-2 bg-slate-200"
                        />

                        <!-- Presets (Responsive Grid on Mobile) -->
                        <div class="grid grid-cols-6 gap-1 pt-1">
                            <button
                                v-for="preset in [0, 25, 50, 75, 90, 100]"
                                :key="preset"
                                type="button"
                                @click="setAccomplishmentPreset(preset)"
                                class="py-1.5 bg-slate-100 hover:bg-slate-200 font-bold text-[10px] text-slate-700 border border-slate-200 transition text-center"
                            >
                                {{ preset }}%
                            </button>
                        </div>

                        <!-- Steppers -->
                        <div class="grid grid-cols-4 gap-1.5 pt-1">
                            <button
                                type="button"
                                @click="adjustAccomplishment(-5)"
                                class="py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 font-bold text-[11px] text-center"
                            >
                                -5%
                            </button>
                            <button
                                type="button"
                                @click="adjustAccomplishment(-1)"
                                class="py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 font-bold text-[11px] text-center"
                            >
                                -1%
                            </button>
                            <button
                                type="button"
                                @click="adjustAccomplishment(1)"
                                class="py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 font-bold text-[11px] text-center"
                            >
                                +1%
                            </button>
                            <button
                                type="button"
                                @click="adjustAccomplishment(5)"
                                class="py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 font-bold text-[11px] text-center"
                            >
                                +5%
                            </button>
                        </div>
                    </div>

                    <!-- Status Dropdown -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Project Status</label>
                        <select
                            v-model="accomplishmentForm.status"
                            class="w-full text-xs font-semibold border border-slate-300 py-2 px-3 bg-white focus:ring-red-500 focus:border-red-500"
                        >
                            <option v-for="st in statusOptions" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>

                    <!-- Remarks Input -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Progress Notes / Field Remarks</label>
                        <textarea
                            v-model="accomplishmentForm.remarks"
                            rows="3"
                            placeholder="Add brief inspection remark or reason for progress change..."
                            class="w-full text-xs border border-slate-300 p-2.5 focus:ring-red-500 focus:border-red-500"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-2">
                        <button
                            type="button"
                            @click="closeAccomplishmentModal"
                            class="w-full sm:w-auto px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition text-center"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSavingAccomplishment"
                            class="w-full sm:w-auto px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-bold text-xs transition disabled:opacity-50 flex items-center justify-center gap-1.5"
                        >
                            <svg v-if="isSavingAccomplishment" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75"></path></svg>
                            <span>{{ isSavingAccomplishment ? 'Saving...' : 'Save Accomplishment' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL 2: PROJECT SPECIFICATIONS EDIT       -->
        <!-- ========================================== -->
        <div
            v-if="showSpecsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-xs p-3 sm:p-4"
            @click.self="closeSpecsModal"
        >
            <div class="bg-white max-w-3xl w-full max-h-[92vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Edit Project Specifications</h3>
                        <p class="text-[11px] sm:text-xs text-slate-500">Update official technical details and schedule parameters.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeSpecsModal"
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="saveSpecs" class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-4 text-xs">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                        <div class="space-y-1 sm:col-span-2">
                            <label class="font-bold text-slate-700">Project Title / Name *</label>
                            <input
                                type="text"
                                v-model="specsForm.name"
                                required
                                class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                            />
                            <p v-if="specsErrors.name" class="text-[11px] text-rose-600">{{ specsErrors.name[0] }}</p>
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <label class="font-bold text-slate-700">Location *</label>
                            <input
                                type="text"
                                v-model="specsForm.location"
                                required
                                class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                            />
                            <p v-if="specsErrors.location" class="text-[11px] text-rose-600">{{ specsErrors.location[0] }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Contractor / Implementer *</label>
                            <input
                                type="text"
                                v-model="specsForm.contractor"
                                required
                                class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                            />
                            <p v-if="specsErrors.contractor" class="text-[11px] text-rose-600">{{ specsErrors.contractor[0] }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Project Status *</label>
                            <select
                                v-model="specsForm.status"
                                class="w-full text-xs font-semibold border border-slate-300 py-2 px-3 bg-white focus:ring-red-500 focus:border-red-500"
                            >
                                <option v-for="st in statusOptions" :key="st" :value="st">{{ st }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Scope & Description -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Project Description / Scope of Work</label>
                        <textarea
                            v-model="specsForm.description"
                            rows="3"
                            class="w-full text-xs border border-slate-300 p-2.5 focus:ring-red-500 focus:border-red-500"
                        ></textarea>
                    </div>

                    <!-- Financials -->
                    <div class="pt-2 border-t border-slate-200">
                        <h4 class="font-bold text-slate-800 mb-2">Financial Specifications & Funding</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Total Project Cost (PHP) *</label>
                                <input
                                    type="text"
                                    v-model="specsForm.totalCostDisplay"
                                    @input="specsForm.totalCostDisplay = formatNumberWithCommas($event.target.value)"
                                    required
                                    class="w-full text-xs font-bold border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Original Cost (PHP)</label>
                                <input
                                    type="text"
                                    v-model="specsForm.originalCostDisplay"
                                    @input="specsForm.originalCostDisplay = formatNumberWithCommas($event.target.value)"
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Revised Cost (PHP)</label>
                                <input
                                    type="text"
                                    v-model="specsForm.revisedCostDisplay"
                                    @input="specsForm.revisedCostDisplay = formatNumberWithCommas($event.target.value)"
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Fund Category *</label>
                                <select
                                    v-model="specsForm.fundCategory"
                                    @change="handleFundCategoryChange"
                                    class="w-full text-xs border border-slate-300 py-2 px-3 bg-white focus:ring-red-500 focus:border-red-500"
                                >
                                    <option v-for="fc in fundCategoryOptions" :key="fc.value" :value="fc.value">{{ fc.label }}</option>
                                </select>
                            </div>

                            <div class="space-y-1 sm:col-span-2">
                                <label class="font-bold text-slate-700">Source of Fund *</label>
                                <input
                                    type="text"
                                    v-model="specsForm.sourceOfFund"
                                    list="specs-fund-sources"
                                    placeholder="Select or enter fund source"
                                    required
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                                <datalist id="specs-fund-sources">
                                    <option v-for="s in availableFundSources" :key="s" :value="s" />
                                </datalist>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule & Dates -->
                    <div class="pt-2 border-t border-slate-200">
                        <h4 class="font-bold text-slate-800 mb-2">Schedule, Duration & Time Extensions</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Fiscal Year *</label>
                                <input
                                    type="number"
                                    v-model="specsForm.year"
                                    min="2000"
                                    max="2100"
                                    required
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Duration (Calendar Days) *</label>
                                <input
                                    type="number"
                                    v-model="specsForm.duration"
                                    min="0"
                                    required
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Accomplishment (%) *</label>
                                <input
                                    type="number"
                                    v-model="specsForm.accomplishment"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    required
                                    class="w-full text-xs font-bold border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Start Date *</label>
                                <input
                                    type="date"
                                    v-model="specsForm.startDate"
                                    required
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Target Completion Date *</label>
                                <input
                                    type="date"
                                    v-model="specsForm.targetCompletionDate"
                                    required
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Revised Completion Date</label>
                                <input
                                    type="date"
                                    v-model="specsForm.revisedCompletionDate"
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Actual Completion Date</label>
                                <input
                                    type="date"
                                    v-model="specsForm.actualCompletionDate"
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Time Extension (Days)</label>
                                <input
                                    type="number"
                                    v-model="specsForm.timeExtension"
                                    min="0"
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Suspension Order (Days)</label>
                                <input
                                    type="number"
                                    v-model="specsForm.daysSuspensionOrder"
                                    min="0"
                                    class="w-full text-xs border border-slate-300 p-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="space-y-1 pt-2 border-t border-slate-200">
                        <label class="font-bold text-slate-700">General Remarks / Field Notes</label>
                        <textarea
                            v-model="specsForm.remarks"
                            rows="2"
                            class="w-full text-xs border border-slate-300 p-2.5 focus:ring-red-500 focus:border-red-500"
                        ></textarea>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-3 border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-2">
                        <button
                            type="button"
                            @click="closeSpecsModal"
                            class="w-full sm:w-auto px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition text-center"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSavingSpecs"
                            class="w-full sm:w-auto px-5 py-2 bg-red-700 hover:bg-red-800 text-white font-bold text-xs transition disabled:opacity-50 flex items-center justify-center gap-1.5"
                        >
                            <svg v-if="isSavingSpecs" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75"></path></svg>
                            <span>{{ isSavingSpecs ? 'Saving Specifications...' : 'Save Specifications' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL 3: DIRECTIVE REPLY / NOTE MODAL      -->
        <!-- ========================================== -->
        <div
            v-if="replyModalOpen && replyingAssignment"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4"
            @click.self="closeReplyModal"
        >
            <div class="bg-white max-w-lg w-full shadow-2xl border border-slate-200 p-4 sm:p-6 space-y-3.5 sm:space-y-4 animate-in fade-in zoom-in-95 duration-150 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2 sm:gap-2.5">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Reply / Note to Admin</h3>
                            <p class="text-[10px] sm:text-[11px] text-slate-500">Provide updates or field comments for this directive</p>
                        </div>
                    </div>
                    <button @click="closeReplyModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Directive Summary Context -->
                <div class="bg-slate-50 p-2.5 sm:p-3 border border-slate-200 space-y-1 text-xs">
                    <div class="flex items-center justify-between flex-wrap gap-1">
                        <span class="font-bold text-slate-800 break-words">{{ replyingAssignment.title }}</span>
                        <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 bg-slate-200 text-slate-700">
                            {{ replyingAssignment.priority || 'normal' }}
                        </span>
                    </div>
                    <p v-if="replyingAssignment.note" class="text-slate-600 text-[11px] italic break-words">
                        "{{ replyingAssignment.note }}"
                    </p>
                    <div class="text-[10px] text-slate-400 pt-1 flex justify-between flex-wrap gap-1">
                        <span>Assigned by: {{ replyingAssignment.assignerName || 'Admin' }}</span>
                        <span v-if="replyingAssignment.targetDeadline">Deadline: {{ replyingAssignment.targetDeadline }}</span>
                    </div>
                </div>

                <form @submit.prevent="handleSaveReply" class="space-y-3 sm:space-y-4">
                    <!-- Update Status on Reply -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">Current Task Status</label>
                        <select
                            v-model="replyForm.status"
                            class="w-full text-xs font-semibold border border-slate-300 p-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed (Mark Task as Done)</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Staff Reply Comment / Note -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">Your Reply / Feedback / Field Note *</label>
                        <textarea
                            v-model="replyForm.staff_reply"
                            required
                            rows="4"
                            placeholder="Enter your field update, remarks, queries, or completion notes for the admin..."
                            class="w-full text-xs border border-slate-300 p-2.5 focus:ring-emerald-500 focus:border-emerald-500 leading-relaxed"
                        ></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="closeReplyModal"
                            class="w-full sm:w-auto px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition text-center"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmittingReply"
                            class="w-full sm:w-auto px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition disabled:opacity-50 flex items-center justify-center gap-1.5"
                        >
                            <svg v-if="isSubmittingReply" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75"></path></svg>
                            <span>{{ isSubmittingReply ? 'Posting Reply...' : 'Post Reply to Admin' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
