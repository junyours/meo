<script setup>
import { computed, reactive, ref, watch, onMounted, onUnmounted } from 'vue';
import { resolveSourceOfFund } from '@/composables/useProjectFundSources';
import TechnicalPreparationsTab from './TechnicalPreparationsTab.vue';
import DocumentScanner from '../../Admin/Partials/DocumentScanner.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['back', 'delete', 'update']);

const showEditModal = ref(false);
const detailTabs = ['overview', 'technical', 'documents'];
const detailTabStorageKey = `meo_superadmin_project_tab_${props.project.id}`;
const storedDetailTab = sessionStorage.getItem(detailTabStorageKey);
const activeTab = ref(detailTabs.includes(storedDetailTab) ? storedDetailTab : 'overview');
const isSaving = ref(false);
const formErrors = ref({});
const statusOptions = ['Not Started', 'Ongoing', 'Completed', 'Suspended', 'Delayed'];
const fundSources = ref({});
const isLoadingSources = ref(false);
const showCustomSourceInput = ref(false);
const customSourceInput = ref('');

watch(activeTab, (tab) => {
    sessionStorage.setItem(detailTabStorageKey, tab);
});

// Fund source hierarchical structure (same as main component)
const fundCategories = {
    national: {
        label: 'National Funded',
        icon: '🏛️',
        sources: []
    },
    provincial: {
        label: 'Provincial Local Funded',
        icon: '🏢',
        sources: []
    },
    lgu: {
        label: 'LGU Funded',
        icon: '🏘️',
        sources: []
    },
    uncategorized: {
        label: 'Uncategorized',
        icon: '📋',
        sources: []
    }
};

const tabs = [
    {
        id: 'overview',
        label: 'Overview',
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        id: 'technical',
        label: 'Technical Preparations',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    },
    {
        id: 'documents',
        label: 'Archived Documents',
        icon: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    },
];

const form = reactive({
    name: '',
    location: '',
    totalCost: '',
    totalCostDisplay: '',
    originalCost: '',
    originalCostDisplay: '',
    revisedCost: '',
    revisedCostDisplay: '',
    description: '',
    fundCategory: '',
    sourceOfFund: '',
    year: new Date().getFullYear(),
    duration: '',
    startDate: '',
    targetCompletionDate: '',
    actualCompletionDate: '',
    revisedCompletionDate: '',
    timeExtension: '',
    daysSuspensionOrder: '',
    accomplishment: '',
    contractor: '',
    remarks: '',
    status: 'Ongoing',
});

const formatCurrency = (value) => {
    const amount = Number(value);
    if (!Number.isFinite(amount) || amount <= 0) {
        return 'Php 0.00';
    }
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const statusClass = (status) => {
    const classes = {
        'Not Started': 'bg-slate-100 text-slate-700',
        Ongoing: 'bg-blue-50 text-blue-700',
        Completed: 'bg-emerald-50 text-emerald-700',
        Suspended: 'bg-amber-50 text-amber-700',
        Delayed: 'bg-red-50 text-red-700',
    };
    return classes[status] || classes.Ongoing;
};

const getAccomplishmentColor = (percentage) => {
    const value = Number(percentage) || 0;
    if (value >= 75) return 'bg-emerald-500';
    if (value >= 50) return 'bg-blue-500';
    if (value >= 25) return 'bg-amber-500';
    return 'bg-red-500';
};

const inputClass =
    'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm transition focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600';

const formatNumberWithCommas = (value) => {
    const stringValue = String(value || '');
    const rawValue = stringValue.replace(/,/g, '').replace(/[^\d.]/g, '');
    if (rawValue === '') return '';
    const [integer, decimal] = rawValue.split('.');
    const formattedInt = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return decimal !== undefined ? `${formattedInt}.${decimal}` : formattedInt;
};

const onTotalCostInput = (event) => {
    const rawValue = event.target.value.replace(/,/g, '');
    form.totalCost = rawValue;
    form.totalCostDisplay = formatNumberWithCommas(rawValue);
};

const onOriginalCostInput = (event) => {
    const rawValue = event.target.value.replace(/,/g, '');
    form.originalCost = rawValue;
    form.originalCostDisplay = formatNumberWithCommas(rawValue);
};

const onRevisedCostInput = (event) => {
    const rawValue = event.target.value.replace(/,/g, '');
    form.revisedCost = rawValue;
    form.revisedCostDisplay = formatNumberWithCommas(rawValue);
};

watch(() => form.totalCost, (newVal) => {
    form.totalCostDisplay = formatNumberWithCommas(newVal);
});

watch(() => form.originalCost, (newVal) => {
    form.originalCostDisplay = formatNumberWithCommas(newVal);
});

watch(() => form.revisedCost, (newVal) => {
    form.revisedCostDisplay = formatNumberWithCommas(newVal);
});

const fundCategoryOptions = computed(() => {
    return Object.entries(fundCategories).map(([key, value]) => ({
        value: key,
        label: value.label,
    }));
});

const selectedCategoryFundSources = computed(() => {
    const sources = [...(fundSources.value[form.fundCategory] || [])];
    if (form.sourceOfFund && form.sourceOfFund !== '__custom' && !sources.includes(form.sourceOfFund)) {
        sources.push(form.sourceOfFund);
    }
    return sources.sort();
});

const fetchFundSources = async (category) => {
    isLoadingSources.value = true;
    try {
        const { data } = await window.axios.get(route('superadmin.projects.fund-sources', { category }));
        return (data.sources || []).map(s => typeof s === 'string' ? s : s.source).filter(Boolean);
    } catch (error) {
        console.error('Error fetching fund sources:', error);
        return [];
    } finally {
        isLoadingSources.value = false;
    }
};

const getFundCategoryKey = (project) => {
    if (project.fundCategory && fundCategories[project.fundCategory]) {
        return project.fundCategory;
    }

    // Convert database values (National, Provincial, LGU) to lowercase keys
    if (project.fundCategory) {
        const lowerCategory = project.fundCategory.toLowerCase();
        if (fundCategories[lowerCategory]) {
            return lowerCategory;
        }
    }

    // Check if sourceOfFund is a category key itself
    if (project.sourceOfFund && fundCategories[project.sourceOfFund]) {
        return project.sourceOfFund;
    }

    // Check if sourceOfFund is a capitalized category key from database
    if (project.sourceOfFund) {
        const lowerSource = project.sourceOfFund.toLowerCase();
        if (fundCategories[lowerSource]) {
            return lowerSource;
        }
    }

    // Check if sourceOfFund is in any category's sources
    return Object.entries(fundCategories).find(([, category]) => category.sources.includes(project.sourceOfFund))?.[0] || 'national';
};

const getFundCategoryLabel = (project) => {
    if (project.fundCategory && fundCategories[project.fundCategory]) {
        return fundCategories[project.fundCategory].label;
    }

    // Convert database values (National, Provincial, LGU) to lowercase keys
    if (project.fundCategory) {
        const lowerCategory = project.fundCategory.toLowerCase();
        if (fundCategories[lowerCategory]) {
            return fundCategories[lowerCategory].label;
        }
    }

    // Check if sourceOfFund is a category key itself
    if (project.sourceOfFund && fundCategories[project.sourceOfFund]) {
        return fundCategories[project.sourceOfFund].label;
    }

    // Check if sourceOfFund is a capitalized category key from database
    if (project.sourceOfFund) {
        const lowerSource = project.sourceOfFund.toLowerCase();
        if (fundCategories[lowerSource]) {
            return fundCategories[lowerSource].label;
        }
    }

    // Check if sourceOfFund is in any category's sources
    const category = Object.values(fundCategories).find(item => item.sources.includes(project.sourceOfFund));
    return category?.label || 'Uncategorized';
};

const syncFormFromProject = (project) => {
    Object.assign(form, {
        name: project.name || '',
        location: project.location || '',
        totalCost: project.totalCost !== undefined && project.totalCost !== null ? String(project.totalCost) : '',
        totalCostDisplay: project.totalCost !== undefined && project.totalCost !== null ? formatNumberWithCommas(String(project.totalCost)) : '',
        originalCost: project.originalCost !== undefined && project.originalCost !== null ? String(project.originalCost) : '',
        originalCostDisplay: project.originalCost !== undefined && project.originalCost !== null ? formatNumberWithCommas(String(project.originalCost)) : '',
        revisedCost: project.revisedCost !== undefined && project.revisedCost !== null ? String(project.revisedCost) : '',
        revisedCostDisplay: project.revisedCost !== undefined && project.revisedCost !== null ? formatNumberWithCommas(String(project.revisedCost)) : '',
        description: project.description || '',
        fundCategory: getFundCategoryKey(project),
        sourceOfFund: project.sourceOfFund,
        year: project.year,
        duration: project.duration || '',
        startDate: project.startDate || '',
        targetCompletionDate: project.targetCompletionDate || '',
        actualCompletionDate: project.actualCompletionDate || '',
        revisedCompletionDate: project.revisedCompletionDate || '',
        timeExtension: project.timeExtension || '',
        daysSuspensionOrder: project.daysSuspensionOrder || '',
        accomplishment: project.accomplishment,
        contractor: project.contractor || '',
        remarks: project.remarks || '',
        status: project.status,
    });
};

watch(
    () => props.project,
    (project) => {
        if (project) {
            syncFormFromProject(project);
        }
    },
    { immediate: true }
);

const handleTechnicalUpdate = (updatedProject) => {
    emit('update', updatedProject);
};

const openEditModal = async () => {
    syncFormFromProject(props.project);
    formErrors.value = {};
    showEditModal.value = true;
    showCustomSourceInput.value = false;
    customSourceInput.value = '';
    const sources = await fetchFundSources(form.fundCategory);
    fundSources.value[form.fundCategory] = sources;
};

const closeEditModal = () => {
    showEditModal.value = false;
    formErrors.value = {};
    syncFormFromProject(props.project); // Reset form
};

const handleFundCategoryChange = async () => {
    form.sourceOfFund = '';
    customSourceInput.value = '';
    showCustomSourceInput.value = false;
    const sources = await fetchFundSources(form.fundCategory);
    fundSources.value[form.fundCategory] = sources;
};

const addCustomFundSource = () => {
    const source = customSourceInput.value.trim();
    if (!source) {
        alert('Please enter a fund source name.');
        return;
    }
    const existingSources = fundSources.value[form.fundCategory] || [];
    if (existingSources.includes(source)) {
        alert('This fund source already exists.');
        return;
    }
    if (!fundSources.value[form.fundCategory]) {
        fundSources.value[form.fundCategory] = [];
    }
    fundSources.value[form.fundCategory] = [...fundSources.value[form.fundCategory], source].sort();
    form.sourceOfFund = source;
    customSourceInput.value = '';
    showCustomSourceInput.value = false;
};

const saveUpdate = async () => {
    const sourceOfFund = resolveSourceOfFund(form.sourceOfFund, customSourceInput.value);
    form.sourceOfFund = sourceOfFund;
    if (!form.name || !form.location || !form.totalCost || !form.fundCategory || !sourceOfFund || !form.year || !form.duration || !form.startDate || !form.targetCompletionDate || !form.contractor) {
        alert('Please fill in all required fields');
        return;
    }

    isSaving.value = true;
    formErrors.value = {};

    try {
        const { data } = await window.axios.put(route('superadmin.projects.update', props.project.id), {
            ...form,
            totalCost: Number(form.totalCost || 0),
            originalCost: form.originalCost ? Number(form.originalCost) : null,
            revisedCost: form.revisedCost ? Number(form.revisedCost) : null,
            description: form.description || null,
            accomplishment: Number(form.accomplishment || 0),
            year: Number(form.year),
            duration: Number(form.duration || 0),
            timeExtension: Number(form.timeExtension || 0),
            daysSuspensionOrder: Number(form.daysSuspensionOrder || 0),
        });

        emit('update', data.project);
        closeEditModal();
    } catch (error) {
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors || {};
            return;
        }

        alert('Unable to update project. Please try again.');
    } finally {
        isSaving.value = false;
    }
};

const showActionsMenu = ref(false);
const actionsMenuRef = ref(null);

const toggleActionsMenu = (event) => {
    event.stopPropagation();
    showActionsMenu.value = !showActionsMenu.value;
};

const closeActionsMenu = () => {
    showActionsMenu.value = false;
};

const handleDelete = () => {
    closeActionsMenu();
    if (confirm('Are you sure you want to delete this project?')) {
        emit('delete', props.project.id);
    }
};

const handleEdit = () => {
    closeActionsMenu();
    openEditModal();
};

const handleClickOutside = (event) => {
    if (actionsMenuRef.value && !actionsMenuRef.value.contains(event.target)) {
        closeActionsMenu();
    }
};

// Keyboard handler for modal
const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        if (showEditModal.value) {
            closeEditModal();
        } else if (showActionsMenu.value) {
            closeActionsMenu();
        }
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="-mx-1 px-1 py-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between border-b border-gray-200 bg-gray-50/95">
            <div class="flex min-w-0 items-start gap-3">
                <button
                    type="button"
                    @click="emit('back')"
                    class="mt-0.5 rounded-lg border border-gray-300 p-2 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                    title="Back to projects"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="break-words text-lg font-semibold text-gray-900 sm:text-xl">{{ project.name }}</h2>
                        <span :class="[statusClass(project.status), 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold']">
                            {{ project.status }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ project.location }}</p>
                </div>
            </div>

            <div ref="actionsMenuRef" class="relative flex w-full shrink-0 justify-end sm:w-auto">
                <button
                    type="button"
                    @click="toggleActionsMenu"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                    aria-haspopup="true"
                    :aria-expanded="showActionsMenu"
                    aria-label="Open project actions"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </button>

                <div
                    v-if="showActionsMenu"
                    class="project-action-menu absolute right-0 top-12 z-50 w-44 rounded-xl border border-gray-200 bg-white shadow-lg"
                >
                    <button
                        type="button"
                        @click="handleEdit"
                        class="block w-full px-4 py-3 text-left text-sm text-gray-700 transition hover:bg-gray-50"
                    >
                        Edit Project
                    </button>
                    <button
                        type="button"
                        @click="handleDelete"
                        class="block w-full rounded-b-xl px-4 py-3 text-left text-sm text-red-700 transition hover:bg-red-50"
                    >
                        Delete Project
                    </button>
                </div>
            </div>
        </div>

        <!-- Compact Tab Header with Sidebar Red Accent -->
        <div class="border-b border-slate-200 bg-slate-50 px-3 pt-1.5 rounded-t-xl overflow-x-auto">
            <nav class="flex gap-1.5 min-w-max" role="tablist">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id"
                    :aria-current="activeTab === tab.id ? 'page' : undefined"
                    :class="[
                        'flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-t-md border-t border-x transition-all relative shrink-0',
                        activeTab === tab.id
                            ? 'bg-white border-slate-200 text-slate-900 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/60',
                    ]"
                >
                    <svg class="h-3.5 w-3.5 shrink-0" :class="activeTab === tab.id ? 'text-red-700' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
                    </svg>
                    {{ tab.label }}
                    <div v-if="activeTab === tab.id" class="absolute top-0 left-0 right-0 h-0.5 bg-red-700 rounded-t"></div>
                </button>
            </nav>
        </div>

        <!-- Overview Tab -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-5">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Project Cost</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 break-words whitespace-normal">{{ project.totalCost ? formatCurrency(project.totalCost) : '—' }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Original Cost</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 break-words whitespace-normal">{{ project.originalCost ? formatCurrency(project.originalCost) : '—' }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Revised Cost</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 break-words whitespace-normal">{{ project.revisedCost ? formatCurrency(project.revisedCost) : '—' }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Accomplishment</p>
                    <p class="mt-2 text-2xl font-bold text-red-700 break-words whitespace-normal">{{ project.accomplishment }}%</p>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="getAccomplishmentColor(project.accomplishment)"
                            :style="{ width: (project.accomplishment || 0) + '%' }"
                        ></div>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Year</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 break-words whitespace-normal">{{ project.year }}</p>
                    <p class="mt-1 text-sm text-gray-500 break-words whitespace-normal">{{ project.sourceOfFund }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Project Information</h3>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Fund Category</dt>
                            <dd class="text-right font-medium text-gray-900">{{ getFundCategoryLabel(project) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Source of Fund</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.sourceOfFund }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Project Duration</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.duration || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Contractor</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.contractor || '—' }}</dd>
                        </div>
                    </dl>
                    <div v-if="project.description" class="mt-4 border-t border-gray-100 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Project Description</h4>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ project.description }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Schedule of Implementation</h3>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Start Date</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.startDate || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Target Completion</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.targetCompletionDate || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Revised Date of Completion</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.revisedCompletionDate || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Actual Completion</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.actualCompletionDate || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Time Extension (Days)</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.timeExtension || '0' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Suspension Order (Days)</dt>
                            <dd class="text-right font-medium text-gray-900">{{ project.daysSuspensionOrder || '0' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <h3 class="font-semibold text-gray-900">Remarks</h3>
                    <p class="mt-3 whitespace-pre-line text-sm text-gray-600">{{ project.remarks || 'No remarks added.' }}</p>
                </div>
            </div>
        </div>

        <!-- Technical Preparations Tab -->
        <TechnicalPreparationsTab
            v-if="activeTab === 'technical'"
            :project="project"
            @update="handleTechnicalUpdate"
        />

        <!-- Documents Scanned Tab -->
        <DocumentScanner
            v-if="activeTab === 'documents'"
            :project-id="project.id"
            :project-name="project.project_name || project.name"
            :techprep-id="project.techprep?.id"
            :is-editable="true"
        />

        <!-- Edit Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showEditModal"
                    class="fixed inset-0 z-[9999] overflow-y-auto"
                    aria-labelledby="modal-title"
                    role="dialog"
                    aria-modal="true"
                >
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black/50 transition-opacity" @click="closeEditModal"></div>

                    <!-- Modal Panel -->
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div
                            class="modal-panel relative w-full max-w-3xl max-h-[calc(100vh-4rem)] overflow-hidden rounded-xl bg-white shadow-2xl"
                            @click.stop
                        >
                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900" id="modal-title">Edit Project</h3>
                                    <p class="mt-1 text-sm text-gray-500">Update project information below.</p>
                                </div>
                                <button
                                    type="button"
                                    @click="closeEditModal"
                                    class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                                    aria-label="Close modal"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Form Body -->
                            <form id="edit-project-form" @submit.prevent="saveUpdate">
                                <div class="max-h-[65vh] overflow-y-auto px-6 py-5">
                                    <div class="space-y-6">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Name of Project <span class="text-red-600">*</span></label>
                                                <input v-model="form.name" required type="text" :class="inputClass" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Location <span class="text-red-600">*</span></label>
                                                <input v-model="form.location" required type="text" :class="inputClass" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Project Cost (Php) <span class="text-red-600">*</span></label>
                                                <input v-model="form.totalCostDisplay" required type="text" :class="inputClass" @input="onTotalCostInput" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Original Cost (Php)</label>
                                                <input v-model="form.originalCostDisplay" type="text" :class="inputClass" @input="onOriginalCostInput" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Revised Cost (Php)</label>
                                                <input v-model="form.revisedCostDisplay" type="text" :class="inputClass" @input="onRevisedCostInput" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Fund Category <span class="text-red-600">*</span></label>
                                                <select v-model="form.fundCategory" required :class="inputClass" @change="handleFundCategoryChange">
                                                    <option value="">Select category...</option>
                                                    <option v-for="cat in fundCategoryOptions" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Source of Fund <span class="text-red-600">*</span></label>
                                                <select v-model="form.sourceOfFund" required :class="inputClass" @change="showCustomSourceInput = form.sourceOfFund === '__custom'">
                                                    <option value="">Select source...</option>
                                                    <option v-for="source in selectedCategoryFundSources" :key="source" :value="source">{{ source }}</option>
                                                    <option value="__custom">+ Add New Source</option>
                                                </select>
                                                <div v-if="showCustomSourceInput" class="mt-2 flex gap-2">
                                                    <input v-model="customSourceInput" type="text" :class="inputClass" placeholder="Enter new fund source" />
                                                    <button type="button" @click="addCustomFundSource" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm whitespace-nowrap">Add</button>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Year <span class="text-red-600">*</span></label>
                                                <input v-model.number="form.year" required min="2000" max="2100" type="number" :class="inputClass" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Project Duration</label>
                                                <input v-model="form.duration" type="text" placeholder="Example: 120 calendar days" :class="inputClass" />
                                            </div>
                                        </div>

                                        <!-- Project Description -->
                                        <div class="border-t border-gray-200 pt-4">
                                            <h4 class="mb-4 text-sm font-semibold text-gray-900">Project Description</h4>
                                            <textarea v-model="form.description" rows="3" :class="inputClass" placeholder="Enter project description"></textarea>
                                        </div>

                                        <!-- Schedule of Implementation -->
                                        <div class="border-t border-gray-200 pt-4">
                                            <h4 class="mb-4 text-sm font-semibold text-gray-900">Schedule of Implementation</h4>
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                                    <input v-model="form.startDate" type="date" :class="inputClass" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Target Date of Completion</label>
                                                    <input v-model="form.targetCompletionDate" type="date" :class="inputClass" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Revised Date of Completion</label>
                                                    <input v-model="form.revisedCompletionDate" type="date" :class="inputClass" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Actual Date of Completion</label>
                                                    <input v-model="form.actualCompletionDate" type="date" :class="inputClass" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Time Extension (Days)</label>
                                                    <input v-model.number="form.timeExtension" min="0" type="number" :class="inputClass" placeholder="0" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Suspension Order (Days)</label>
                                                    <input v-model.number="form.daysSuspensionOrder" min="0" type="number" :class="inputClass" placeholder="0" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Additional Fields -->
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">% of Accomplishment</label>
                                                <input v-model.number="form.accomplishment" min="0" max="100" step="0.01" type="number" :class="inputClass" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Contractor</label>
                                                <input v-model="form.contractor" type="text" :class="inputClass" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                                <select v-model="form.status" :class="inputClass">
                                                    <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Remarks</label>
                                                <textarea v-model="form.remarks" rows="2" :class="inputClass"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="flex items-center justify-between border-t border-gray-200 px-6 py-4">
                                    <p class="text-xs text-gray-400"><span class="text-red-600">*</span> Required fields</p>
                                    <div class="flex gap-3">
                                        <button
                                            type="button"
                                            @click="closeEditModal"
                                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-800"
                                        >
                                            Update Project
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* Modal animations */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.modal-enter-from .modal-panel,
.modal-leave-to .modal-panel {
    opacity: 0;
    transform: scale(0.96) translateY(12px);
}
</style>
