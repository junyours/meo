<script setup>
import { reactive, watch, computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    isEditable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update', 'status-change']);

const toast = ref({ show: false, message: '', type: 'success' });
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const showConfirmModal = ref(false);
const confirmAction = ref(null);

// Auto-save related state
const autoSaveEnabled = ref(true);
const lastAutoSaveTime = ref(null);
const hasUnsavedChanges = ref(false);
const isAutoSaving = ref(false);
const autoSaveTimer = ref(null);
const AUTO_SAVE_DELAY = 3000; // 3 seconds after last change

const emptyTechnicalPreparations = () => ({
    hazardAssessment: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    powDed: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    supplementalBudget: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    alobs: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    eccCnc: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    technicalDocsToBac: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    bidding: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    contractNtp: { status: '', notes: '', updatedAt: null, updatedBy: '' },
    remarks: '',
    lastUpdated: null,
});

const prepStatusOptions = [
    {
        value: 'green',
        label: 'Done',
        shortLabel: 'Done',
        description: 'Completed or on track',
        badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        buttonClass: 'bg-emerald-500 text-white hover:bg-emerald-600',
        buttonIdleClass: 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
        ringClass: 'ring-emerald-500',
        rowClass: 'bg-emerald-50/60',
        legendDotClass: 'bg-emerald-500',
        icon: 'check',
        color: 'emerald',
    },
    {
        value: 'yellow',
        label: 'In Progress',
        shortLabel: 'Ongoing',
        description: 'Currently being processed',
        badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
        buttonClass: 'bg-amber-400 text-white hover:bg-amber-500',
        buttonIdleClass: 'bg-amber-50 text-amber-700 hover:bg-amber-100',
        ringClass: 'ring-amber-400',
        rowClass: 'bg-amber-50/60',
        legendDotClass: 'bg-amber-400',
        icon: 'clock',
        color: 'amber',
    },
    {
        value: 'red',
        label: 'Not Started',
        shortLabel: 'Pending',
        description: 'Not yet started or delayed',
        badgeClass: 'bg-red-50 text-red-700 border-red-200',
        buttonClass: 'bg-red-500 text-white hover:bg-red-600',
        buttonIdleClass: 'bg-red-50 text-red-700 hover:bg-red-100',
        ringClass: 'ring-red-500',
        rowClass: 'bg-red-50/60',
        legendDotClass: 'bg-red-500',
        icon: 'alert',
        color: 'red',
    },
    {
        value: 'na',
        label: 'N/A',
        shortLabel: 'N/A',
        description: 'Not applicable to this project',
        badgeClass: 'bg-slate-50 text-slate-600 border-slate-200',
        buttonClass: 'bg-slate-500 text-white hover:bg-slate-600',
        buttonIdleClass: 'bg-slate-50 text-slate-600 hover:bg-slate-100',
        ringClass: 'ring-slate-500',
        rowClass: 'bg-slate-50/60',
        legendDotClass: 'bg-slate-400',
        icon: 'minus',
        color: 'slate',
    },
];

const blankStatusMeta = {
    value: '',
    label: 'Not Set',
    shortLabel: 'Not Set',
    description: 'No status assigned yet',
    badgeClass: 'bg-white text-gray-500 border-gray-200',
    buttonClass: '',
    buttonIdleClass: '',
    ringClass: 'ring-gray-300',
    rowClass: 'bg-white',
    icon: 'minus',
    color: 'gray',
};

const statusItems = [
    {
        group: 'Pre-Construction Requirements',
        icon: 'clipboard',
        items: [
            { key: 'hazardAssessment', label: 'Hazard Assessment', office: 'MEO / MDRRMO' },
            { key: 'powDed', label: 'POW / DED', office: 'MEO' },
            { key: 'supplementalBudget', label: 'Supplemental Budget (SB)', office: 'End User' },
            { key: 'alobs', label: 'ALOBS', office: 'End User' },
            { key: 'eccCnc', label: 'ECC / CNC', office: 'MENRO' },
        ],
    },
    {
        group: 'Procurement Process',
        icon: 'file',
        items: [
            { key: 'technicalDocsToBac', label: 'Technical Documents to BAC', office: 'MEO' },
            { key: 'bidding', label: 'Bidding Process', office: 'BAC' },
            { key: 'contractNtp', label: 'Contract Signing & NTP', office: 'GSO' },
        ],
    },
];

const legacyStatusMap = {
    completed: 'green',
    'in-progress': 'yellow',
    pending: 'yellow',
    'not-started': 'red',
    delayed: 'red',
};

const normalizeStatus = (status) => legacyStatusMap[status] || status || '';

const form = reactive(emptyTechnicalPreparations());
const savedFormSnapshot = ref(null); // Snapshot of last saved state
const isSaving = ref(false);
const activeDropdown = ref(null);
const dropdownPosition = ref('bottom');
const isSettingAllDone = ref(false);

const allItemKeys = computed(() => statusItems.flatMap((section) => section.items.map((item) => item.key)));

const allItemsDone = computed(() => {
    return allItemKeys.value.every(key => normalizeStatus(form[key]?.status) === 'green');
});

const overallProgress = computed(() => {
    const keys = allItemKeys.value;
    if (keys.length === 0) return 0;

    const scored = keys.reduce((sum, key) => {
        const status = normalizeStatus(form[key]?.status);
        if (status === 'green') return sum + 100;
        if (status === 'yellow') return sum + 50;
        return sum;
    }, 0);

    return Math.round(scored / keys.length);
});

const statusSummary = computed(() => {
    const keys = allItemKeys.value;
    return {
        done: keys.filter((key) => normalizeStatus(form[key]?.status) === 'green').length,
        ongoing: keys.filter((key) => normalizeStatus(form[key]?.status) === 'yellow').length,
        pending: keys.filter((key) => normalizeStatus(form[key]?.status) === 'red').length,
        notSet: keys.filter((key) => !normalizeStatus(form[key]?.status)).length,
        total: keys.length,
    };
});

const getSectionProgress = (sectionItems) => {
    const total = sectionItems.length;
    const done = sectionItems.filter((item) => normalizeStatus(form[item.key]?.status) === 'green').length;
    const ongoing = sectionItems.filter((item) => normalizeStatus(form[item.key]?.status) === 'yellow').length;
    return { total, done, ongoing, percentage: total > 0 ? Math.round((done / total) * 100) : 0 };
};

// Check if form has changed from last saved state
const checkForChanges = () => {
    if (!savedFormSnapshot.value) {
        hasUnsavedChanges.value = true;
        return;
    }
    
    const currentState = JSON.stringify({
        hazardAssessment: form.hazardAssessment,
        powDed: form.powDed,
        supplementalBudget: form.supplementalBudget,
        alobs: form.alobs,
        eccCnc: form.eccCnc,
        technicalDocsToBac: form.technicalDocsToBac,
        bidding: form.bidding,
        contractNtp: form.contractNtp,
        remarks: form.remarks,
    });
    
    hasUnsavedChanges.value = currentState !== savedFormSnapshot.value;
};

// Take a snapshot of current form state
const takeSnapshot = () => {
    savedFormSnapshot.value = JSON.stringify({
        hazardAssessment: form.hazardAssessment,
        powDed: form.powDed,
        supplementalBudget: form.supplementalBudget,
        alobs: form.alobs,
        eccCnc: form.eccCnc,
        technicalDocsToBac: form.technicalDocsToBac,
        bidding: form.bidding,
        contractNtp: form.contractNtp,
        remarks: form.remarks,
    });
    hasUnsavedChanges.value = false;
};

const syncFromProject = (project) => {
    const prep = project.technical_preparations || project.technicalPreparations || emptyTechnicalPreparations();
    const defaults = emptyTechnicalPreparations();

    Object.assign(form, {
        ...defaults,
        ...prep,
        remarks: prep.remarks ?? '',
        lastUpdated: prep.lastUpdated ?? null,
    });

    allItemKeys.value.forEach((key) => {
        form[key] = {
            ...defaults[key],
            ...prep[key],
            status: normalizeStatus(prep[key]?.status),
        };
    });
    
    // Take initial snapshot after syncing
    takeSnapshot();
};

watch(
    () => props.project?.id,
    () => {
        if (props.project) syncFromProject(props.project);
    },
    { immediate: true }
);

watch(
    () => props.project?.technical_preparations || props.project?.technicalPreparations,
    () => {
        if (props.project) syncFromProject(props.project);
    },
    { deep: true }
);

// Watch for changes and trigger auto-save
watch(
    () => [
        form.hazardAssessment,
        form.powDed,
        form.supplementalBudget,
        form.alobs,
        form.eccCnc,
        form.technicalDocsToBac,
        form.bidding,
        form.contractNtp,
        form.remarks,
    ],
    () => {
        checkForChanges();
        
        if (autoSaveEnabled.value && hasUnsavedChanges.value) {
            scheduleAutoSave();
        }
    },
    { deep: true }
);

const scheduleAutoSave = () => {
    // Clear existing timer
    if (autoSaveTimer.value) {
        clearTimeout(autoSaveTimer.value);
    }
    
    // Set new timer
    autoSaveTimer.value = setTimeout(() => {
        performAutoSave();
    }, AUTO_SAVE_DELAY);
};

const getTechnicalPrepUrl = () => {
    const isSuperadmin = typeof window !== 'undefined' && window.location.pathname.startsWith('/superadmin');
    const prefix = isSuperadmin ? '/superadmin' : '/admin';
    return `${prefix}/projects/${props.project.id}/technical-preparations`;
};

const performAutoSave = async () => {
    if (!props.isEditable || isAutoSaving.value || !hasUnsavedChanges.value) return;
    
    isAutoSaving.value = true;
    
    try {
        const payload = {
            hazardAssessment: form.hazardAssessment,
            powDed: form.powDed,
            supplementalBudget: form.supplementalBudget,
            alobs: form.alobs,
            eccCnc: form.eccCnc,
            technicalDocsToBac: form.technicalDocsToBac,
            bidding: form.bidding,
            contractNtp: form.contractNtp,
            remarks: form.remarks,
        };

        const response = await axios.post(
            getTechnicalPrepUrl(),
            payload,
            {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                }
            }
        );

        if (response.data.technical_preparations || response.data.technicalPreparations) {
            const updatedPrep = response.data.technical_preparations || response.data.technicalPreparations;
            Object.assign(form, updatedPrep);
            form.lastUpdated = new Date().toISOString();
        }

        const updatedProject = {
            ...props.project,
            technical_preparations: response.data.technical_preparations || response.data.technicalPreparations,
        };
        emit('update', updatedProject);
        
        // Update snapshot and show toast
        takeSnapshot();
        lastAutoSaveTime.value = new Date();
        showToast('Draft auto-saved', 'success');
        
    } catch (error) {
        console.error('Auto-save error:', error);
        showToast('Auto-save failed', 'error');
    } finally {
        isAutoSaving.value = false;
    }
};

const getStatusMeta = (status) => {
    const normalized = normalizeStatus(status);
    if (!normalized) return blankStatusMeta;
    return prepStatusOptions.find((option) => option.value === normalized) || blankStatusMeta;
};

const getItemStatus = (key) => normalizeStatus(form[key]?.status);

const calculateDropdownPosition = (event, key) => {
    const button = event.target.closest('button');
    if (!button) return 'bottom';
    
    const rect = button.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const dropdownHeight = 320;
    
    return spaceBelow < dropdownHeight ? 'top' : 'bottom';
};

const toggleDropdown = (event, key) => {
    if (!props.isEditable) return;
    
    if (activeDropdown.value === key) {
        activeDropdown.value = null;
    } else {
        dropdownPosition.value = calculateDropdownPosition(event, key);
        activeDropdown.value = key;
    }
};

const closeDropdown = () => {
    activeDropdown.value = null;
};

const setStatus = (key, statusValue) => {
    if (!props.isEditable) return;

    const current = getItemStatus(key);
    const newStatus = current === statusValue ? '' : statusValue;

    form[key] = {
        ...form[key],
        status: newStatus,
        updatedAt: new Date().toISOString(),
        updatedBy: 'Current User',
    };

    emit('status-change', { key, oldStatus: current, newStatus, timestamp: new Date().toISOString() });

    if (newStatus !== current) {
        const statusLabels = {
            green: 'Done',
            yellow: 'In Progress',
            red: 'Not Started',
            na: 'N/A',
        };
        const statusText = newStatus ? statusLabels[newStatus] : 'Cleared';
        const itemLabels = {
            hazardAssessment: 'Hazard Assessment',
            powDed: 'POW / DED',
            supplementalBudget: 'Supplemental Budget',
            alobs: 'ALOBS',
            eccCnc: 'ECC / CNC',
            technicalDocsToBac: 'Technical Documents to BAC',
            bidding: 'Bidding Process',
            contractNtp: 'Contract Signing & NTP',
        };
        showToast(`${itemLabels[key]} status: ${statusText}`, 'success');
    }

    closeDropdown();
};

const setAllToDone = () => {
    if (!props.isEditable) return;
    
    isSettingAllDone.value = true;
    const timestamp = new Date().toISOString();
    
    allItemKeys.value.forEach((key) => {
        form[key] = {
            ...form[key],
            status: 'green',
            updatedAt: timestamp,
            updatedBy: 'Current User',
        };
        emit('status-change', { key, oldStatus: getItemStatus(key), newStatus: 'green', timestamp });
    });
    
    showToast('All items marked as Done!', 'success');
    setTimeout(() => {
        isSettingAllDone.value = false;
    }, 100);
};

const clearAllStatuses = () => {
    if (!props.isEditable) return;
    
    isSettingAllDone.value = true;
    const timestamp = new Date().toISOString();
    
    allItemKeys.value.forEach((key) => {
        form[key] = {
            ...form[key],
            status: '',
            updatedAt: timestamp,
            updatedBy: 'Current User',
        };
        emit('status-change', { key, oldStatus: getItemStatus(key), newStatus: '', timestamp });
    });
    
    showToast('All statuses cleared!', 'success');
    setTimeout(() => {
        isSettingAllDone.value = false;
    }, 100);
};

const savePreparations = async () => {
    if (!props.isEditable) return;

    isSaving.value = true;

    try {
        // Clear auto-save timer since we're manually saving
        if (autoSaveTimer.value) {
            clearTimeout(autoSaveTimer.value);
        }
        
        const payload = {
            hazardAssessment: form.hazardAssessment,
            powDed: form.powDed,
            supplementalBudget: form.supplementalBudget,
            alobs: form.alobs,
            eccCnc: form.eccCnc,
            technicalDocsToBac: form.technicalDocsToBac,
            bidding: form.bidding,
            contractNtp: form.contractNtp,
            remarks: form.remarks,
        };

        const response = await axios.post(
            getTechnicalPrepUrl(),
            payload,
            {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                }
            }
        );

        if (response.data.technical_preparations || response.data.technicalPreparations) {
            const updatedPrep = response.data.technical_preparations || response.data.technicalPreparations;
            Object.assign(form, updatedPrep);
            form.lastUpdated = new Date().toISOString();
        }

        const updatedProject = {
            ...props.project,
            technical_preparations: response.data.technical_preparations || response.data.technicalPreparations,
        };
        emit('update', updatedProject);
        
        // Update snapshot
        takeSnapshot();
        showToast('Technical preparations saved successfully!', 'success');
    } catch (error) {
        console.error('Save error:', error);
        console.error('Error response:', error.response?.data);
        
        let errorMessage = 'Error saving changes. Please try again.';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
            const errors = Object.values(error.response.data.errors).flat();
            errorMessage = errors.join('\n');
        }
        showToast(errorMessage, 'error');
    } finally {
        isSaving.value = false;
    }
};

const toggleAutoSave = () => {
    autoSaveEnabled.value = !autoSaveEnabled.value;
    if (autoSaveEnabled.value && hasUnsavedChanges.value) {
        scheduleAutoSave();
    }
    showToast(
        autoSaveEnabled.value ? 'Auto-save enabled' : 'Auto-save disabled',
        'success'
    );
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatTimeAgo = (date) => {
    if (!date) return '';
    const seconds = Math.floor((new Date() - date) / 1000);
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    return `${Math.floor(hours / 24)}d ago`;
};

const getIcon = (iconName) => {
    const icons = {
        check: 'M5 13l4 4L19 7',
        clock: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        alert: 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
        minus: 'M20 12H4',
        clipboard: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        file: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    };
    return icons[iconName] || '';
};

const handleClickOutside = (event) => {
    const clickedOnDropdown = event.target.closest('.status-dropdown-container');
    if (!clickedOnDropdown) {
        closeDropdown();
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape' && activeDropdown.value) {
        closeDropdown();
    }
};

// Save before leaving page
const handleBeforeUnload = (e) => {
    if (hasUnsavedChanges.value) {
        // Perform immediate auto-save
        performAutoSave();
        
        // Prevent default to show browser warning
        e.preventDefault();
        e.returnValue = '';
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside, true);
    document.addEventListener('keydown', handleKeydown);
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside, true);
    document.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('beforeunload', handleBeforeUnload);
    
    // Clear auto-save timer
    if (autoSaveTimer.value) {
        clearTimeout(autoSaveTimer.value);
    }
    
    // Final save if there are unsaved changes
    if (hasUnsavedChanges.value) {
        performAutoSave();
    }
});
</script>

<template>
    <div class="space-y-6" style="overflow: visible;">
        <!-- Toast -->
        <div
            v-if="toast.show"
            :class="[
                'fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg transition-all',
                toast.type === 'success' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white',
            ]"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="toast.type === 'success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'"
                />
            </svg>
            <span class="text-sm font-medium">{{ toast.message }}</span>
        </div>

        <!-- ===== HEADER ===== -->
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="px-3 sm:px-6 py-4 border-b border-gray-100 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Technical Preparations</h3>
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-xs text-gray-500">Track each requirement from preparation to completion.</p>
                            
                            <!-- Auto-save status indicator -->
                            <div class="flex items-center gap-2">
                                <span 
                                    class="inline-flex items-center gap-1 text-xs"
                                    :class="isAutoSaving ? 'text-amber-500' : hasUnsavedChanges ? 'text-gray-400' : 'text-emerald-500'"
                                >
                                    <span 
                                        class="w-1.5 h-1.5 rounded-full"
                                        :class="isAutoSaving ? 'bg-amber-400 animate-pulse' : hasUnsavedChanges ? 'bg-gray-400' : 'bg-emerald-500'"
                                    ></span>
                                    {{ isAutoSaving ? 'Saving...' : hasUnsavedChanges ? 'Unsaved changes' : 'Saved' }}
                                </span>
                                <span v-if="lastAutoSaveTime && !isAutoSaving" class="text-xs text-gray-400">
                                    {{ formatTimeAgo(lastAutoSaveTime) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                    <!-- Auto-save Toggle -->
                    <button
                        v-if="isEditable"
                        @click="toggleAutoSave"
                        :class="[
                            'inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition border',
                            autoSaveEnabled 
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' 
                                : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100'
                        ]"
                        :title="autoSaveEnabled ? 'Auto-save is ON' : 'Auto-save is OFF'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                :d="autoSaveEnabled ? 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' : 'M18.364 5.636a9 9 0 010 12.728m-12.728 0a9 9 0 010-12.728m9.9 2.829a5 5 0 010 7.07m-6.364 0a5 5 0 010-7.07'" />
                        </svg>
                        Auto-save {{ autoSaveEnabled ? 'ON' : 'OFF' }}
                    </button>

                    <!-- Mark All as Done Button -->
                    <button
                        v-if="isEditable && !allItemsDone"
                        type="button"
                        @click="setAllToDone"
                        :disabled="isSettingAllDone"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition shadow-sm"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Mark All as Done
                    </button>
                    
                    <!-- Clear All Button -->
                    <button
                        v-if="isEditable && statusSummary.done > 0"
                        type="button"
                        @click="clearAllStatuses"
                        :disabled="isSettingAllDone"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed text-gray-700 text-sm font-medium rounded-lg transition"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Clear All
                    </button>

                    <!-- All Done Indicator -->
                    <div v-if="allItemsDone" class="flex items-center gap-2 px-3 py-2 bg-emerald-50 text-emerald-700 rounded-lg">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium">All Complete</span>
                    </div>

                    <div class="text-right">
                        <p class="text-xs text-gray-500">Progress</p>
                        <p class="text-xl font-bold text-red-600">{{ overallProgress }}%</p>
                    </div>
                    <div class="relative h-12 w-12">
                        <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15" fill="none" stroke="#f3f4f6" stroke-width="3" />
                            <circle
                                cx="18"
                                cy="18"
                                r="15"
                                fill="none"
                                stroke="#dc2626"
                                stroke-width="3"
                                :stroke-dasharray="`${overallProgress * 0.94} 94`"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100">
                <div class="px-6 py-3 text-center">
                    <p class="text-xs text-gray-500">Done</p>
                    <p class="text-lg font-bold text-emerald-600">{{ statusSummary.done }}</p>
                </div>
                <div class="px-6 py-3 text-center">
                    <p class="text-xs text-gray-500">Ongoing</p>
                    <p class="text-lg font-bold text-amber-500">{{ statusSummary.ongoing }}</p>
                </div>
                <div class="px-6 py-3 text-center">
                    <p class="text-xs text-gray-500">Pending</p>
                    <p class="text-lg font-bold text-red-600">{{ statusSummary.pending }}</p>
                </div>
                <div class="px-6 py-3 text-center">
                    <p class="text-xs text-gray-500">Not Set</p>
                    <p class="text-lg font-bold text-gray-400">{{ statusSummary.notSet }}</p>
                </div>
            </div>

            <!-- Legend -->
            <div class="px-3 sm:px-6 py-3 border-t border-gray-100 flex flex-wrap items-center gap-3 sm:gap-4">
                <span class="text-xs font-medium text-gray-500">Status guide:</span>
                <span class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                    <span class="h-2.5 w-2.5 rounded-full border border-gray-300 bg-white"></span>
                    Not Set
                </span>
                <span v-for="option in prepStatusOptions" :key="option.value" class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                    <span class="h-2.5 w-2.5 rounded-full" :class="option.legendDotClass"></span>
                    {{ option.label }}
                </span>
            </div>
        </div>

        <!-- Rest of the template remains the same... -->
        <!-- ===== SECTIONS ===== -->
        <div v-for="section in statusItems" :key="section.group" class="bg-white border border-gray-200" style="overflow: visible;">
            <!-- Section Header -->
            <div class="px-3 sm:px-6 py-3 border-b border-gray-100 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between bg-gray-50">
                <div class="flex flex-wrap items-center gap-3">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getIcon(section.icon)" />
                    </svg>
                    <h4 class="text-sm font-medium text-gray-900">{{ section.group }}</h4>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        v-if="isEditable"
                        @click="section.items.forEach(item => setStatus(item.key, 'green'))"
                        class="text-xs text-emerald-600 hover:text-emerald-700 font-medium transition"
                    >
                        Mark Section as Done
                    </button>
                    <span class="text-xs text-gray-500">
                        {{ getSectionProgress(section.items).done }}/{{ getSectionProgress(section.items).total }} done
                    </span>
                    <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-emerald-500 rounded-full transition-all"
                            :style="{ width: getSectionProgress(section.items).percentage + '%' }"
                        />
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="divide-y divide-gray-100" style="overflow: visible;">
                <div v-for="(item, index) in section.items" :key="item.key" 
                     class="px-3 sm:px-6 py-4 hover:bg-gray-50 transition"
                     :class="{ 'pb-20': activeDropdown === item.key && dropdownPosition === 'bottom' }"
                     style="overflow: visible; position: relative;">
                    <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:gap-4">
                        <!-- Left: Info -->
                        <div class="flex-1 min-w-[180px]">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-gray-900">{{ item.label }}</span>
                                <span class="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded">{{ item.office }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span v-if="form[item.key]?.updatedAt" class="text-gray-400">
                                    Updated {{ formatDate(form[item.key].updatedAt) }}
                                </span>
                                <span v-else class="text-gray-400">No updates yet</span>
                            </p>
                        </div>

                        <!-- Status Dropdown -->
                        <div class="status-dropdown-container relative w-full sm:w-auto" style="overflow: visible;">
                            <button
                                type="button"
                                :disabled="!isEditable"
                                @click.stop="toggleDropdown($event, item.key)"
                                :aria-label="`${isEditable ? 'Update' : 'View'} status for ${item.label}`"
                                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition"
                                :class="[
                                    getItemStatus(item.key) 
                                        ? getStatusMeta(getItemStatus(item.key)).badgeClass
                                        : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50',
                                    !isEditable && 'cursor-not-allowed opacity-50'
                                ]"
                            >
                                <span class="w-2 h-2 rounded-full" :class="[
                                    getItemStatus(item.key) 
                                        ? getStatusMeta(getItemStatus(item.key)).legendDotClass
                                        : 'bg-gray-300'
                                ]"></span>
                                <span>{{ getItemStatus(item.key) ? (isEditable ? 'Update Status: ' : '') + getStatusMeta(getItemStatus(item.key)).label : 'Update Status' }}</span>
                                <svg 
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="{ 'rotate-180': activeDropdown === item.key }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Dropdown Options -->
                            <transition
                                enter-active-class="transition ease-out duration-200"
                                enter-from-class="transform opacity-0 scale-95"
                                enter-to-class="transform opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="transform opacity-100 scale-100"
                                leave-to-class="transform opacity-0 scale-95"
                            >
                                <div 
                                    v-if="activeDropdown === item.key"
                                    :class="[
                                        'absolute left-0 sm:left-auto sm:right-0 w-[min(18rem,calc(100vw-2rem))] max-w-[calc(100vw-2rem)] bg-white rounded-lg border border-gray-200 shadow-xl z-[100] py-1',
                                        dropdownPosition === 'top' ? 'bottom-full mb-2' : 'top-full mt-2'
                                    ]"
                                >
                                    <div class="px-3 py-2 border-b border-gray-100">
                                        <p class="text-xs font-medium text-gray-500">Set status for {{ item.label }}</p>
                                    </div>
                                    <button
                                        v-for="option in prepStatusOptions"
                                        :key="option.value"
                                        @click.stop="setStatus(item.key, option.value)"
                                        class="w-full flex items-start gap-3 px-4 py-2.5 text-sm hover:bg-gray-50 transition text-left"
                                    >
                                        <span class="w-2.5 h-2.5 rounded-full" :class="option.legendDotClass"></span>
                                        <span class="flex-1">
                                            <span class="block font-medium text-gray-700">{{ option.label }}</span>
                                            <span class="block text-xs text-gray-400">{{ option.description }}</span>
                                        </span>
                                        <span v-if="getItemStatus(item.key) === option.value" class="ml-auto text-emerald-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="border-t border-gray-100 mt-1 pt-1">
                                        <button
                                            v-if="getItemStatus(item.key)"
                                            @click.stop="setStatus(item.key, getItemStatus(item.key))"
                                            class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-500 hover:bg-gray-50 transition"
                                        >
                                            <span class="w-2.5 h-2.5 rounded-full border border-gray-300 bg-white"></span>
                                            <span>Clear Status</span>
                                        </button>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mt-2">
                        <textarea
                            v-model="form[item.key].notes"
                            :disabled="!isEditable"
                            rows="1"
                            placeholder="Add notes..."
                            class="w-full rounded border-0 bg-transparent px-0 py-1 text-xs text-gray-600 placeholder:text-gray-400 focus:ring-0 focus:outline-none resize-none"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== REMARKS ===== -->
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-3">
                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
                <h4 class="text-sm font-medium text-gray-900">General Remarks</h4>
            </div>
            <div class="p-4">
                <textarea
                    v-model="form.remarks"
                    :disabled="!isEditable"
                    rows="3"
                    placeholder="Overall notes, bottlenecks, or recommendations..."
                    class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-700 placeholder:text-gray-400 focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition disabled:cursor-not-allowed disabled:opacity-60"
                />
            </div>
        </div>

        <!-- ===== FOOTER ===== -->
        <div class="bg-white border border-gray-200 px-3 sm:px-6 py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <span class="text-xs text-gray-500">
                    Last saved: <strong class="text-gray-700">{{ formatDate(form.lastUpdated) || 'Not saved yet' }}</strong>
                </span>
                <span v-if="hasUnsavedChanges" class="text-xs text-amber-600">
                    • Unsaved changes will be auto-saved
                </span>
            </div>
            <button
                type="button"
                @click="savePreparations"
                :disabled="!isEditable || isSaving"
                class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition"
            >
                <svg v-if="isSaving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ isSaving ? 'Saving...' : 'Save Now' }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.status-dropdown-container {
    overflow: visible !important;
}

.bg-white.rounded-lg.border {
    overflow: visible !important;
}

.pb-20 {
    padding-bottom: 5rem;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
