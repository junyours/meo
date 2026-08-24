<script setup>
import { computed, reactive, ref, watch, onMounted, onUnmounted } from 'vue';
import { resolveSourceOfFund } from '@/composables/useProjectFundSources';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['back', 'delete', 'update']);

const showEditModal = ref(false);
const isSaving = ref(false);
const formErrors = ref({});
const statusOptions = ['Not Started', 'Ongoing', 'Completed', 'Suspended', 'Delayed'];
const fundSources = ref({});
const isLoadingSources = ref(false);
const showCustomSourceInput = ref(false);
const customSourceInput = ref('');

// Fund source hierarchical structure
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
    form.totalCostDisplay = formatNumberWithCommas(event.target.value);
};

const onOriginalCostInput = (event) => {
    form.originalCostDisplay = formatNumberWithCommas(event.target.value);
};

const onRevisedCostInput = (event) => {
    form.revisedCostDisplay = formatNumberWithCommas(event.target.value);
};

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
        const { data } = await window.axios.get(route('admin.projects.fund-sources', { category }));
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

    if (project.fundCategory) {
        const lowerCategory = project.fundCategory.toLowerCase();
        if (fundCategories[lowerCategory]) {
            return lowerCategory;
        }
    }

    if (project.sourceOfFund && fundCategories[project.sourceOfFund]) {
        return project.sourceOfFund;
    }

    if (project.sourceOfFund) {
        const lowerSource = project.sourceOfFund.toLowerCase();
        if (fundCategories[lowerSource]) {
            return lowerSource;
        }
    }

    return Object.entries(fundCategories).find(([, category]) => category.sources.includes(project.sourceOfFund))?.[0] || 'national';
};

const getFundCategoryLabel = (project) => {
    if (project.fundCategory && fundCategories[project.fundCategory]) {
        return fundCategories[project.fundCategory].label;
    }

    if (project.fundCategory) {
        const lowerCategory = project.fundCategory.toLowerCase();
        if (fundCategories[lowerCategory]) {
            return fundCategories[lowerCategory].label;
        }
    }

    if (project.sourceOfFund && fundCategories[project.sourceOfFund]) {
        return fundCategories[project.sourceOfFund].label;
    }

    if (project.sourceOfFund) {
        const lowerSource = project.sourceOfFund.toLowerCase();
        if (fundCategories[lowerSource]) {
            return fundCategories[lowerSource].label;
        }
    }

    const category = Object.values(fundCategories).find(item => item.sources.includes(project.sourceOfFund));
    return category?.label || 'Uncategorized';
};

const syncFormFromProject = (project) => {
    Object.assign(form, {
        name: project.name,
        location: project.location,
        totalCost: project.totalCost,
        totalCostDisplay: project.totalCost ? formatNumberWithCommas(project.totalCost) : '',
        originalCost: project.originalCost || '',
        originalCostDisplay: project.originalCost ? formatNumberWithCommas(project.originalCost) : '',
        revisedCost: project.revisedCost || '',
        revisedCostDisplay: project.revisedCost ? formatNumberWithCommas(project.revisedCost) : '',
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
    syncFormFromProject(props.project);
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
    const totalCostValue = String(form.totalCostDisplay || '').replace(/,/g, '');
    const originalCostValue = String(form.originalCostDisplay || '').replace(/,/g, '');
    const revisedCostValue = String(form.revisedCostDisplay || '').replace(/,/g, '');

    if (!form.name || !form.location || !totalCostValue || !form.fundCategory || !sourceOfFund || !form.year || !form.duration || !form.startDate || !form.targetCompletionDate || !form.contractor) {
        alert('Please fill in all required fields');
        return;
    }

    isSaving.value = true;
    formErrors.value = {};

    try {
        const { data } = await window.axios.put(route('admin.projects.update', props.project.id), {
            ...form,
            totalCost: Number(totalCostValue || 0),
            originalCost: originalCostValue ? Number(originalCostValue) : null,
            revisedCost: revisedCostValue ? Number(revisedCostValue) : null,
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

            <!-- Actions Menu -->
            <div class="relative self-end sm:self-auto" ref="actionsMenuRef">
                <button
                    type="button"
                    @click="toggleActionsMenu"
                    class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    Actions
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div
                    v-if="showActionsMenu"
                    class="absolute right-0 z-30 mt-2 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5"
                >
                    <button
                        type="button"
                        @click="handleEdit"
                        class="flex w-full items-center px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                    >
                        <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Project
                    </button>
                    <button
                        type="button"
                        @click="handleDelete"
                        class="flex w-full items-center px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                    >
                        <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete Project
                    </button>
                </div>
            </div>
        </div>

        <!-- Overview Section -->
        <div class="space-y-6">
            <!-- Key Metrics Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Project Cost</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ formatCurrency(project.totalCost) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Fund: {{ project.sourceOfFund || 'Not specified' }}</p>
                </div>

                <div class="border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Physical Accomplishment</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <p class="text-2xl font-bold text-gray-900">{{ project.accomplishment || 0 }}%</p>
                    </div>
                    <div class="mt-3 h-2 w-full overflow-hidden bg-gray-100">
                        <div
                            :class="[getAccomplishmentColor(project.accomplishment), 'h-full transition-all duration-300']"
                            :style="{ width: `${Math.min(project.accomplishment || 0, 100)}%` }"
                        ></div>
                    </div>
                </div>

                <div class="border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Project Duration</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ project.duration || 0 }} CD</p>
                    <p class="mt-1 text-xs text-gray-500">Calendar Days</p>
                </div>

                <div class="border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Target Completion</p>
                    <p class="mt-2 text-xl font-bold text-gray-900">{{ project.targetCompletionDate || 'Not set' }}</p>
                    <p class="mt-1 text-xs text-gray-500">Target Timeline</p>
                </div>
            </div>

            <!-- Project Details Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- General Info -->
                <div class="border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Project Specifications</h3>
                    <dl class="mt-4 divide-y divide-gray-100">
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Contractor / Implementer</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.contractor || 'Not assigned' }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Funding Category</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ getFundCategoryLabel(project) }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Source of Fund</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.sourceOfFund || 'Not specified' }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Fiscal Year</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.year }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Original Cost</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ formatCurrency(project.originalCost || project.totalCost) }}</dd>
                        </div>
                        <div v-if="project.revisedCost" class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Revised Cost</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ formatCurrency(project.revisedCost) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Schedule & Timeline -->
                <div class="border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Timeline & Schedule</h3>
                    <dl class="mt-4 divide-y divide-gray-100">
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Start Date</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.startDate || 'Not set' }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Target Completion Date</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.targetCompletionDate || 'Not set' }}</dd>
                        </div>
                        <div v-if="project.actualCompletionDate" class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Actual Completion Date</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.actualCompletionDate }}</dd>
                        </div>
                        <div v-if="project.revisedCompletionDate" class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Revised Completion Date</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.revisedCompletionDate }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Time Extension</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.timeExtension || 0 }} Days</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium text-gray-500">Days Suspension Order</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ project.daysSuspensionOrder || 0 }} Days</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Description & Remarks -->
            <div class="border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Project Description & Scope of Work</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 whitespace-pre-line">{{ project.description || 'No detailed scope of work provided.' }}</p>
                </div>
                <div v-if="project.remarks" class="border-t border-gray-100 pt-4">
                    <h4 class="text-sm font-semibold text-gray-800">Operational Remarks</h4>
                    <p class="mt-1 text-sm text-gray-600 whitespace-pre-line">{{ project.remarks }}</p>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div
            v-if="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
        >
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white p-6 shadow-xl border border-gray-200">
                <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Project Details</h3>
                    <button type="button" @click="closeEditModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveUpdate" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Project Title *</label>
                        <input type="text" v-model="form.name" :class="inputClass" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Location *</label>
                        <input type="text" v-model="form.location" :class="inputClass" required />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Total Project Cost (₱) *</label>
                            <input
                                type="text"
                                :value="form.totalCostDisplay"
                                @input="onTotalCostInput"
                                :class="inputClass"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contractor / Implementer *</label>
                            <input type="text" v-model="form.contractor" :class="inputClass" required />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Funding Category *</label>
                            <select v-model="form.fundCategory" @change="handleFundCategoryChange" :class="inputClass" required>
                                <option v-for="opt in fundCategoryOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Source of Fund *</label>
                            <select v-model="form.sourceOfFund" :class="inputClass" required>
                                <option value="" disabled>Select Source</option>
                                <option v-for="src in selectedCategoryFundSources" :key="src" :value="src">{{ src }}</option>
                                <option value="__custom">+ Custom Source</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="form.sourceOfFund === '__custom'" class="flex gap-2">
                        <input
                            type="text"
                            v-model="customSourceInput"
                            placeholder="Enter new source name"
                            :class="inputClass"
                        />
                        <button
                            type="button"
                            @click="addCustomFundSource"
                            class="mt-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm"
                        >
                            Add
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Project Status</label>
                            <select v-model="form.status" :class="inputClass">
                                <option v-for="st in statusOptions" :key="st" :value="st">{{ st }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Accomplishment (%)</label>
                            <input type="number" v-model="form.accomplishment" min="0" max="100" :class="inputClass" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Duration (Days) *</label>
                            <input type="number" v-model="form.duration" min="1" :class="inputClass" required />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date *</label>
                            <input type="date" v-model="form.startDate" :class="inputClass" required />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Target Completion Date *</label>
                            <input type="date" v-model="form.targetCompletionDate" :class="inputClass" required />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Project Description</label>
                        <textarea v-model="form.description" rows="3" :class="inputClass"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea v-model="form.remarks" rows="2" :class="inputClass"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSaving"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ isSaving ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
