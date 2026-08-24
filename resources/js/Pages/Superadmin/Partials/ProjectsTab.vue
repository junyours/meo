<script setup>
import { computed, reactive, ref, watch, onMounted, onUnmounted } from 'vue';
import ProjectInfo from './ProjectInfo.vue';
import {
    normalizeFundCategory,
    resolveSourceOfFund,
    useProjectFundSources,
} from '@/composables/useProjectFundSources';

// ========== PROPS & EMITS ==========
const props = defineProps({
    initialProjects: {
        type: [Array, Object],
        default: () => []
    },
    sortOrder: {
        type: String,
        default: 'asc' // 'asc' for oldest first, 'desc' for newest first
    }
});

const emit = defineEmits(['update:projects', 'project-selected']);

// ========== STATE ==========
const showAddModal = ref(false);
const selectedProject = ref(null);
const selectedProjectStorageKey = 'meo_superadmin_selected_project_id';
const viewMode = ref('list');
const activeTab = ref('all');
const searchQuery = ref('');
const selectedStatusFilter = ref('');
const selectedYearFilter = ref('');
const selectedFundSourceFilter = ref('');
const selectedFundCategoryFilter = ref('');
const sortBy = ref('oldest');
const isLoading = ref(false);
const formErrors = ref({});
const isEditing = ref(false);
const showActionsMenu = ref(null);
const showCustomSourceInput = ref(false);
const customSourceInput = ref('');

// ========== PAGINATION STATE ==========
const currentPage = ref(1);
const perPage = ref(10);
const totalItems = ref(0);
const totalPages = ref(0);

// ========== DEFAULT FUND CATEGORIES ==========
const defaultFundCategories = {
    national: {
        label: 'National',
        color: 'blue',
        sources: []
    },
    provincial: {
        label: 'Provincial',
        color: 'green',
        sources: []
    },
    lgu: {
        label: 'LGU',
        color: 'purple',
        sources: []
    }
};

const {
    fundSources,
    isLoadingSources,
    fundCategoryOptions,
    loadAllFundSources,
    fetchFundSources,
    loadSourcesForCategory,
    addLocalFundSource,
    getSourcesForCategory,
} = useProjectFundSources('superadmin.projects.fund-sources');

const selectedCategoryFundSources = computed(() => getSourcesForCategory(form.fundCategory, form.sourceOfFund, true));

// ========== CONSTANTS ==========
const statusOptions = ['Not Started', 'Ongoing', 'Completed', 'Suspended', 'Delayed'];
const categoryOptions = ['Infrastructure', 'Social', 'Economic', 'Environmental'];

// ========== PER PAGE OPTIONS ==========
const perPageOptions = [5, 10, 20, 50, 100];

// ========== COMPUTED ==========
// Handle both array and paginated object
const projectsList = computed(() => {
    if (!props.initialProjects) return [];
    
    // If it's already an array
    if (Array.isArray(props.initialProjects)) {
        return props.initialProjects;
    }
    
    // If it's a paginated object with data property
    if (props.initialProjects.data && Array.isArray(props.initialProjects.data)) {
        // Update pagination info from the response
        if (props.initialProjects.current_page) {
            currentPage.value = props.initialProjects.current_page;
            totalItems.value = props.initialProjects.total || 0;
            totalPages.value = props.initialProjects.last_page || 0;
            perPage.value = props.initialProjects.per_page || 10;
        }
        return props.initialProjects.data;
    }
    
    // If it's any other object, try to extract values
    if (typeof props.initialProjects === 'object') {
        const possibleData = Object.values(props.initialProjects);
        if (possibleData.length > 0 && possibleData[0] && typeof possibleData[0] === 'object' && possibleData[0].id) {
            return possibleData;
        }
    }
    
    return [];
});

const projects = computed({
    get: () => projectsList.value || [],
    set: (value) => {
        emit('update:projects', value);
    }
});

// ========== SORTED PROJECTS - Oldest First (Number 1 on top) ==========
const sortedProjects = computed(() => {
    const list = [...projects.value];
    return list.sort((a, b) => (a.id || 0) - (b.id || 0));
});

const getFundCategory = (projectOrSource) => {
    if (projectOrSource && typeof projectOrSource === 'object') {
        if (projectOrSource.fundCategory) {
            // Convert database values (National, Provincial, LGU) to lowercase keys
            return projectOrSource.fundCategory.toLowerCase();
        }
        projectOrSource = projectOrSource.sourceOfFund;
    }

    const source = projectOrSource;
    
    // Check if source is a category key itself (e.g., "national", "provincial", "lgu")
    if (source && defaultFundCategories[source]) {
        return source;
    }
    
    // Check if source is a capitalized category key from database (e.g., "National", "Provincial", "LGU")
    if (source) {
        const lowerSource = source.toLowerCase();
        if (defaultFundCategories[lowerSource]) {
            return lowerSource;
        }
    }
    
    for (const [categoryKey, sources] of Object.entries(fundSources.value)) {
        if (sources && sources.includes(source)) {
            return categoryKey;
        }
    }
    return null;
};

const getFundCategoryLabel = (project) => {
    const key = getFundCategory(project);
    if (key) {
        return defaultFundCategories[key]?.label || 
               key.charAt(0).toUpperCase() + key.slice(1);
    }
    return 'Uncategorized';
};

const yearOptions = computed(() => {
    const years = [...new Set(projects.value.map(p => p.year).filter(Boolean))];
    return years.sort((a, b) => b - a);
});

// ========== DASHBOARD STATS ==========
const dashboardStats = computed(() => {
    const all = sortedProjects.value;
    const total = all.length;
    const totalCost = all.reduce((sum, p) => sum + Number(p.totalCost || 0), 0);
    const completed = all.filter(p => p.status === 'Completed').length;
    const ongoing = all.filter(p => p.status === 'Ongoing').length;
    const delayed = all.filter(p => p.status === 'Delayed').length;
    const avgAccomplishment = total > 0 ? Math.round(all.reduce((sum, p) => sum + Number(p.accomplishment || 0), 0) / total) : 0;
    
    const costByCategory = {};
    const countByCategory = {};
    
    // Only use valid category keys from defaultFundCategories to avoid duplicates
    const validCategories = Object.keys(defaultFundCategories);
    
    validCategories.forEach(key => {
        costByCategory[key] = 0;
        countByCategory[key] = 0;
    });
    
    all.forEach(p => {
        const category = getFundCategory(p);
        if (category && costByCategory[category] !== undefined) {
            costByCategory[category] += Number(p.totalCost || 0);
            countByCategory[category]++;
        }
    });
    
    return {
        total,
        totalCost,
        completed,
        ongoing,
        delayed,
        avgAccomplishment,
        costByCategory,
        countByCategory
    };
});

// ========== FILTERED PROJECTS ==========
const filteredProjects = computed(() => {
    // Start with the sorted projects from backend
    let filtered = [...sortedProjects.value];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(p =>
            p.name?.toLowerCase().includes(query) ||
            p.location?.toLowerCase().includes(query) ||
            p.contractor?.toLowerCase().includes(query) ||
            p.sourceOfFund?.toLowerCase().includes(query)
        );
    }

    if (selectedStatusFilter.value) {
        filtered = filtered.filter(p => p.status === selectedStatusFilter.value);
    }

    if (selectedYearFilter.value !== '') {
        filtered = filtered.filter(p => p.year === Number(selectedYearFilter.value));
    }

    if (selectedFundSourceFilter.value) {
        filtered = filtered.filter(p => p.sourceOfFund === selectedFundSourceFilter.value);
    }

    if (selectedFundCategoryFilter.value) {
        filtered = filtered.filter(p => {
            const category = getFundCategory(p);
            return category === selectedFundCategoryFilter.value;
        });
    }
    
    if (activeTab.value !== 'all') {
        filtered = filtered.filter(p => getFundCategory(p) === activeTab.value);
    }

    // Apply sorting
    if (sortBy.value === 'newest') {
        filtered.sort((a, b) => (b.id || 0) - (a.id || 0));
    } else if (sortBy.value === 'costHigh') {
        filtered.sort((a, b) => Number(b.totalCost || 0) - Number(a.totalCost || 0));
    } else if (sortBy.value === 'costLow') {
        filtered.sort((a, b) => Number(a.totalCost || 0) - Number(b.totalCost || 0));
    } else if (sortBy.value === 'accomplishmentHigh') {
        filtered.sort((a, b) => Number(b.accomplishment || 0) - Number(a.accomplishment || 0));
    } else if (sortBy.value === 'accomplishmentLow') {
        filtered.sort((a, b) => Number(a.accomplishment || 0) - Number(b.accomplishment || 0));
    } else {
        // Default to oldest first (first added is number 1 on top)
        filtered.sort((a, b) => (a.id || 0) - (b.id || 0));
    }

    // Update total items for pagination
    totalItems.value = filtered.length;
    totalPages.value = Math.ceil(filtered.length / perPage.value);
    
    return filtered;
});

// ========== PAGINATED PROJECTS ==========
const paginatedProjects = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    const end = start + perPage.value;
    return filteredProjects.value.slice(start, end);
});

const filteredStats = computed(() => {
    const filtered = filteredProjects.value;
    const total = filtered.reduce((sum, p) => sum + Number(p.totalCost || 0), 0);
    const avgAccomplishment = filtered.length > 0
        ? Math.round(filtered.reduce((sum, p) => sum + Number(p.accomplishment || 0), 0) / filtered.length)
        : 0;
    const statusCounts = {
        'Not Started': filtered.filter(p => p.status === 'Not Started').length,
        'Ongoing': filtered.filter(p => p.status === 'Ongoing').length,
        'Completed': filtered.filter(p => p.status === 'Completed').length,
        'Suspended': filtered.filter(p => p.status === 'Suspended').length,
        'Delayed': filtered.filter(p => p.status === 'Delayed').length,
    };
    
    return { total, avgAccomplishment, statusCounts };
});

const hasActiveFilters = computed(() =>
    Boolean(searchQuery.value || selectedStatusFilter.value || selectedYearFilter.value !== '' || 
            selectedFundSourceFilter.value || selectedFundCategoryFilter.value || sortBy.value !== 'newest' ||
            activeTab.value !== 'all')
);

// ========== PAGINATION COMPUTED ==========
const startIndex = computed(() => {
    return (currentPage.value - 1) * perPage.value + 1;
});

const endIndex = computed(() => {
    const end = currentPage.value * perPage.value;
    return Math.min(end, totalItems.value);
});

const showingText = computed(() => {
    if (filteredProjects.value.length === 0) return 'No projects found';
    return `Showing ${startIndex.value} to ${endIndex.value} of ${totalItems.value} projects`;
});

// ========== PAGINATION METHODS ==========
const goToPage = (page) => {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    // Scroll to top of project list
    const projectList = document.querySelector('.project-list-container');
    if (projectList) {
        projectList.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const changePerPage = (newPerPage) => {
    perPage.value = newPerPage;
    currentPage.value = 1;
};

// ========== FORM ==========
const emptyProject = () => ({
    name: '',
    location: '',
    totalCost: '',
    totalCostDisplay: '',
    originalCost: '',
    originalCostDisplay: '',
    revisedCost: '',
    revisedCostDisplay: '',
    description: '',
    fundCategory: 'national',
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
    category: 'Infrastructure'
});

const form = reactive(emptyProject());

// ========== METHODS ==========
const formatCurrency = (value) => {
    const amount = Number(value);
    if (!Number.isFinite(amount) || amount <= 0) return 'Php 0.00';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};

const formatCompactCurrency = (value) => {
    const amount = Number(value);
    if (amount >= 1000000) return `Php ${(amount / 1000000).toFixed(1)}M`;
    if (amount >= 1000) return `Php ${(amount / 1000).toFixed(0)}K`;
    return formatCurrency(amount);
};

// ========== NUMBER FORMATTING ==========
const formatNumberWithCommas = (value) => {
    if (!value && value !== 0) return '';
    const cleanValue = String(value).replace(/[^0-9.]/g, '');
    const parts = cleanValue.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.length > 1 ? parts.join('.') : parts[0];
};

const onTotalCostInput = (event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const rawValue = input.value.replace(/,/g, '');
    
    if (!/^[\d.]*$/.test(rawValue)) {
        input.value = form.totalCostDisplay || '';
        return;
    }
    
    const numValue = parseFloat(rawValue);
    if (!isNaN(numValue)) {
        form.totalCost = rawValue;
        form.totalCostDisplay = formatNumberWithCommas(rawValue);
    } else {
        form.totalCost = '';
        form.totalCostDisplay = '';
    }
    
    input.value = form.totalCostDisplay;
    const newPosition = input.value.length - (input.value.length - cursorPosition);
    input.setSelectionRange(newPosition, newPosition);
};

const onOriginalCostInput = (event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const rawValue = input.value.replace(/,/g, '');
    
    if (!/^[\d.]*$/.test(rawValue)) {
        input.value = form.originalCostDisplay || '';
        return;
    }
    
    const numValue = parseFloat(rawValue);
    if (!isNaN(numValue)) {
        form.originalCost = rawValue;
        form.originalCostDisplay = formatNumberWithCommas(rawValue);
    } else {
        form.originalCost = '';
        form.originalCostDisplay = '';
    }
    
    input.value = form.originalCostDisplay;
    const newPosition = input.value.length - (input.value.length - cursorPosition);
    input.setSelectionRange(newPosition, newPosition);
};

const onRevisedCostInput = (event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const rawValue = input.value.replace(/,/g, '');
    
    if (!/^[\d.]*$/.test(rawValue)) {
        input.value = form.revisedCostDisplay || '';
        return;
    }
    
    const numValue = parseFloat(rawValue);
    if (!isNaN(numValue)) {
        form.revisedCost = rawValue;
        form.revisedCostDisplay = formatNumberWithCommas(rawValue);
    } else {
        form.revisedCost = '';
        form.revisedCostDisplay = '';
    }
    
    input.value = form.revisedCostDisplay;
    const newPosition = input.value.length - (input.value.length - cursorPosition);
    input.setSelectionRange(newPosition, newPosition);
};

watch(() => form.totalCost, (newVal) => {
    if (newVal) {
        form.totalCostDisplay = formatNumberWithCommas(newVal);
    }
});

watch(() => form.originalCost, (newVal) => {
    if (newVal) {
        form.originalCostDisplay = formatNumberWithCommas(newVal);
    }
});

watch(() => form.revisedCost, (newVal) => {
    if (newVal) {
        form.revisedCostDisplay = formatNumberWithCommas(newVal);
    }
});

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

const resetFilters = () => {
    searchQuery.value = '';
    selectedStatusFilter.value = '';
    selectedYearFilter.value = '';
    selectedFundSourceFilter.value = '';
    selectedFundCategoryFilter.value = '';
    sortBy.value = 'oldest';
    activeTab.value = 'all';
    currentPage.value = 1;
};

const resetForm = () => {
    Object.assign(form, emptyProject());
    isEditing.value = false;
    form.totalCostDisplay = '';
    form.originalCostDisplay = '';
    form.revisedCostDisplay = '';
    showCustomSourceInput.value = false;
    customSourceInput.value = '';
};

const openAddModal = async () => {
    resetForm();
    form.fundCategory = 'national';
    formErrors.value = {};
    isEditing.value = false;
    showAddModal.value = true;
    await loadSourcesForCategory(form.fundCategory);
};

const closeAddModal = () => {
    resetForm();
    formErrors.value = {};
    isEditing.value = false;
    showAddModal.value = false;
};

const openEditModal = async (project) => {
    const formattedCost = project.totalCost ? formatNumberWithCommas(String(project.totalCost)) : '';
    Object.assign(form, {
        name: project.name || '',
        location: project.location || '',
        totalCost: project.totalCost || '',
        totalCostDisplay: formattedCost,
        originalCost: project.originalCost || '',
        originalCostDisplay: project.originalCost ? formatNumberWithCommas(String(project.originalCost)) : '',
        revisedCost: project.revisedCost || '',
        revisedCostDisplay: project.revisedCost ? formatNumberWithCommas(String(project.revisedCost)) : '',
        description: project.description || '',
        fundCategory: normalizeFundCategory(project.fundCategory),
        sourceOfFund: project.sourceOfFund || '',
        year: project.year || new Date().getFullYear(),
        duration: project.duration || '',
        startDate: project.startDate || '',
        targetCompletionDate: project.targetCompletionDate || '',
        actualCompletionDate: project.actualCompletionDate || '',
        revisedCompletionDate: project.revisedCompletionDate || '',
        timeExtension: project.timeExtension || '',
        daysSuspensionOrder: project.daysSuspensionOrder || '',
        accomplishment: project.accomplishment || '',
        contractor: project.contractor || '',
        remarks: project.remarks || '',
        status: project.status || 'Ongoing',
        category: project.category || 'Infrastructure'
    });
    
    isEditing.value = true;
    formErrors.value = {};
    showAddModal.value = true;
    showCustomSourceInput.value = false;
    customSourceInput.value = '';
    await loadSourcesForCategory(form.fundCategory);
};

const handleFundCategoryChange = async () => {
    form.sourceOfFund = '';
    customSourceInput.value = '';
    showCustomSourceInput.value = false;
    await loadSourcesForCategory(form.fundCategory);
};

const addCustomFundSource = () => {
    const source = customSourceInput.value.trim();
    if (!source) {
        alert('Please enter a fund source name.');
        return;
    }
    const added = addLocalFundSource(form.fundCategory, source);
    if (!added) {
        alert('This fund source already exists.');
        return;
    }
    form.sourceOfFund = source;
    customSourceInput.value = '';
    showCustomSourceInput.value = false;
};

const addProject = async () => {
    const sourceOfFund = resolveSourceOfFund(form.sourceOfFund, customSourceInput.value);
    const totalCostValue = form.totalCost ? String(form.totalCost).replace(/,/g, '') : '';

    if (!form.name || !form.location || !totalCostValue || !form.fundCategory || !sourceOfFund || !form.duration || !form.startDate || !form.targetCompletionDate || !form.contractor) {
        alert('Please fill in all required fields, including a valid source of fund.');
        return;
    }

    isLoading.value = true;
    formErrors.value = {};

    try {
        const endpoint = isEditing.value 
            ? route('superadmin.projects.update', selectedProject.value.id)
            : route('superadmin.projects.store');
        
        const method = isEditing.value ? 'put' : 'post';
        
        const originalCostValue = form.originalCost ? String(form.originalCost).replace(/,/g, '') : '';
        const revisedCostValue = form.revisedCost ? String(form.revisedCost).replace(/,/g, '') : '';

        const payload = {
            name: form.name,
            location: form.location,
            totalCost: Number(totalCostValue) || 0,
            originalCost: originalCostValue ? Number(originalCostValue) : null,
            revisedCost: revisedCostValue ? Number(revisedCostValue) : null,
            description: form.description || null,
            fundCategory: form.fundCategory,
            sourceOfFund: sourceOfFund,
            year: Number(form.year) || new Date().getFullYear(),
            duration: Number(form.duration) || 0,
            startDate: form.startDate,
            targetCompletionDate: form.targetCompletionDate,
            actualCompletionDate: form.actualCompletionDate || null,
            revisedCompletionDate: form.revisedCompletionDate || null,
            timeExtension: Number(form.timeExtension) || 0,
            daysSuspensionOrder: Number(form.daysSuspensionOrder) || 0,
            accomplishment: Number(form.accomplishment) || 0,
            contractor: form.contractor,
            remarks: form.remarks || '',
            status: form.status || 'Ongoing',
            category: form.category || 'Infrastructure'
        };

        const { data } = await window.axios[method](endpoint, payload);

        if (isEditing.value) {
            const index = projects.value.findIndex(p => p.id === data.project.id);
            if (index !== -1) {
                const updated = [...projects.value];
                updated[index] = data.project;
                projects.value = updated;
            }
            selectedProject.value = data.project;
        } else {
            // Add new project at the end (oldest first order)
            projects.value = [...projects.value, data.project];
            // Reset to first page to see the new project
            currentPage.value = 1;
        }
        
        await loadAllFundSources();
        
        resetForm();
        showAddModal.value = false;
        isEditing.value = false;
        
    } catch (error) {
        console.error('Error saving project:', error);
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors || {};
            const errorMessages = Object.values(formErrors.value).flat();
            if (errorMessages.length > 0) {
                alert(errorMessages.join('\n'));
            }
            return;
        }
        alert('Unable to save project. Please try again.');
    } finally {
        isLoading.value = false;
    }
};

const openProject = async (project) => {
    selectedProject.value = project;
    sessionStorage.setItem(selectedProjectStorageKey, String(project.id));
    isLoading.value = true;

    try {
        const { data } = await window.axios.get(route('superadmin.projects.show', project.id));
        selectedProject.value = data.project;
        emit('project-selected', data.project);
    } catch (error) {
        emit('project-selected', project);
    } finally {
        isLoading.value = false;
    }
};

const closeProject = () => {
    selectedProject.value = null;
    sessionStorage.removeItem(selectedProjectStorageKey);
};

const toggleActionsMenu = (projectId) => {
    showActionsMenu.value = showActionsMenu.value === projectId ? null : projectId;
};

const closeActionsMenu = () => {
    showActionsMenu.value = null;
};

const handleEditFromMenu = (project) => {
    closeActionsMenu();
    openEditModal(project);
};

const handleDeleteFromMenu = (projectId) => {
    closeActionsMenu();
    deleteProject(projectId);
};

const handleClickOutside = (event) => {
    if (!event.target.closest('.project-actions-menu')) {
        closeActionsMenu();
    }
};

const updateProject = (updatedProject) => {
    const index = projects.value.findIndex(p => p.id === updatedProject.id);
    if (index !== -1) {
        const updated = [...projects.value];
        updated[index] = updatedProject;
        projects.value = updated;
        selectedProject.value = updatedProject;
    }
};

const deleteProject = (projectId) => {
    if (confirm('Are you sure you want to delete this project?')) {
        projects.value = projects.value.filter(p => p.id !== projectId);
        selectedProject.value = null;
        sessionStorage.removeItem(selectedProjectStorageKey);
    }
};

const inputClass = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm transition focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600';

const getCategoryColor = (category) => {
    const colors = {
        national: 'blue',
        provincial: 'green',
        lgu: 'purple'
    };
    return colors[category] || 'gray';
};

// ========== LIFECYCLE ==========
onMounted(() => {
    loadAllFundSources();
    document.addEventListener('click', handleClickOutside);

    const selectedProjectId = sessionStorage.getItem(selectedProjectStorageKey);
    const project = projects.value.find(item => String(item.id) === selectedProjectId);
    if (project) {
        openProject(project);
    } else if (selectedProjectId) {
        sessionStorage.removeItem(selectedProjectStorageKey);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

watch(selectedProject, (newVal) => {
    if (newVal) {
        // Handle project selection
    }
});

// Reset to page 1 when filters change
watch([searchQuery, selectedStatusFilter, selectedYearFilter, selectedFundCategoryFilter, activeTab, sortBy], () => {
    currentPage.value = 1;
});
</script>

<template>
    <div class="w-full space-y-4">
        <!-- Project Detail View -->
        <ProjectInfo
            v-if="selectedProject"
            :project="selectedProject"
            @back="closeProject"
            @delete="deleteProject"
            @update="updateProject"
            @edit="openEditModal"
        />

        <!-- Main View -->
        <template v-else>
            <!-- ===== SIMPLE DASHBOARD ===== -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Projects</h2>
                        <p class="text-xs text-gray-500">{{ dashboardStats.total }} total</p>
                    </div>
                    <div class="flex items-center gap-2">
                      
                    </div>
                </div>

                <!-- Compact Stats -->
                <div class="grid grid-cols-4 divide-x divide-gray-100">
                    <div class="px-4 py-3 text-center">
                        <p class="text-xs text-gray-500">Total</p>
                        <p class="text-lg font-bold text-gray-900">{{ dashboardStats.total }}</p>
                    </div>
                    <div class="px-4 py-3 text-center">
                        <p class="text-xs text-gray-500">Budget</p>
                        <p class="text-lg font-bold text-gray-900">{{ formatCompactCurrency(dashboardStats.totalCost) }}</p>
                    </div>
                    <div class="px-4 py-3 text-center">
                        <p class="text-xs text-gray-500">Ongoing</p>
                        <p class="text-lg font-bold text-blue-600">{{ dashboardStats.ongoing }}</p>
                        <p class="text-[10px] text-gray-400">{{ dashboardStats.avgAccomplishment }}% avg</p>
                    </div>
                    <div class="px-4 py-3 text-center">
                        <p class="text-xs text-gray-500">Completed</p>
                        <p class="text-lg font-bold text-emerald-600">{{ dashboardStats.completed }}</p>
                        <p class="text-[10px] text-gray-400">{{ dashboardStats.total ? Math.round((dashboardStats.completed / dashboardStats.total) * 100) : 0 }}%</p>
                    </div>
                </div>

                <!-- Category Mini Bars -->
                <div class="px-4 py-2 border-t border-gray-100 grid grid-cols-3 gap-2">
                    <div v-for="(category, key) in defaultFundCategories" :key="key" 
                         class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">{{ category.label }}</span>
                        <span class="font-medium text-gray-900">{{ dashboardStats.countByCategory[key] || 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- ===== FILTERS (Compact) ===== -->
            <div class="sticky top-3 z-30 bg-white/95 backdrop-blur-sm rounded-lg border border-gray-200 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-2 flex-1 min-w-[150px]">
                        <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search..."
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-red-500 focus:border-red-500 outline-none"
                        />
                    </div>
                    <select v-model="selectedStatusFilter" class="px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-red-500 focus:border-red-500 outline-none bg-white">
                        <option value="">Status</option>
                        <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                    </select>
                    <select v-model="selectedYearFilter" class="px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-red-500 focus:border-red-500 outline-none bg-white">
                        <option value="">Year</option>
                        <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                    </select>
                    <select v-model="selectedFundCategoryFilter" class="px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-red-500 focus:border-red-500 outline-none bg-white">
                        <option value="">Category</option>
                        <option v-for="cat in fundCategoryOptions" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                    </select>
                    <button
                        v-if="hasActiveFilters"
                        @click="resetFilters"
                        class="px-2 py-1.5 text-xs text-red-600 hover:text-red-700 font-medium"
                    >
                        Clear
                    </button>
                </div>

                <!-- Tabs -->
                <div class="mt-2 flex flex-wrap items-center gap-1.5 border-t border-gray-100 pt-2">
                    <button
                        @click="activeTab = 'all'"
                        :class="['px-3 py-1 rounded-full text-xs font-medium transition', 
                                 activeTab === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                    >
                        All ({{ projects.length }})
                    </button>
                    <button
                        v-for="(category, key) in defaultFundCategories"
                        :key="key"
                        @click="activeTab = key"
                        :class="[
                          'px-3 py-1 rounded-full text-xs font-medium transition',
                          activeTab === key ? 
                            (getCategoryColor(key) === 'blue' ? 'bg-blue-600 text-white' : 
                             getCategoryColor(key) === 'green' ? 'bg-green-600 text-white' : 
                             getCategoryColor(key) === 'purple' ? 'bg-purple-600 text-white' : 
                             'bg-gray-600 text-white') : 
                            (getCategoryColor(key) === 'blue' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100' : 
                             getCategoryColor(key) === 'green' ? 'bg-green-50 text-green-700 hover:bg-green-100' : 
                             getCategoryColor(key) === 'purple' ? 'bg-purple-50 text-purple-700 hover:bg-purple-100' : 
                             'bg-gray-50 text-gray-700 hover:bg-gray-100')
                        ]"
                    >
                        {{ category.label }} ({{ dashboardStats.countByCategory[key] || 0 }})
                    </button>
                    <span class="ml-auto text-xs text-gray-400">{{ filteredProjects.length }} total</span>
                </div>
            </div>

            <!-- ===== PROJECT LIST ===== -->
            <div class="project-list-container bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <h3 class="text-sm font-medium text-gray-900">List</h3>
                        <span class="text-xs text-gray-400 ml-2">{{ showingText }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="viewMode = 'list'"
                            :class="['p-1.5 rounded transition', viewMode === 'list' ? 'bg-red-100 text-red-600' : 'text-gray-400 hover:bg-gray-100']"
                            title="List View"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <button
                            @click="viewMode = 'grid'"
                            :class="['p-1.5 rounded transition', viewMode === 'grid' ? 'bg-red-100 text-red-600' : 'text-gray-400 hover:bg-gray-100']"
                            title="Grid View"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Empty States -->
                <div v-if="projects.length === 0" class="px-4 py-10 text-center text-gray-500">
                    <p class="text-sm font-medium">No projects yet</p>
                    <p class="text-xs mt-1">Create your first project</p>
                    <button @click="openAddModal" class="mt-3 text-xs text-red-600 hover:text-red-700 font-medium">Create Project</button>
                </div>
                <div v-else-if="paginatedProjects.length === 0" class="px-4 py-10 text-center text-gray-500">
                    <p class="text-sm">No projects match your filters</p>
                    <button @click="resetFilters" class="mt-2 text-xs text-red-600 hover:text-red-700 font-medium">Clear filters</button>
                </div>

                <!-- List View -->
                <div v-else-if="viewMode === 'list'" class="divide-y divide-gray-100">
                    <div
                        v-for="(project, index) in paginatedProjects"
                        :key="project.id"
                        class="px-4 py-2.5 hover:bg-gray-50 transition flex items-center justify-between gap-3"
                        @click="openProject(project)"
                    >
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <!-- Number with dot format based on global index -->
                            <span class="text-xs font-medium text-gray-400 w-6 text-right flex-shrink-0">
                                {{ (currentPage - 1) * perPage + index + 1 }}.
                            </span>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-medium text-gray-900 truncate">{{ project.name }}</span>
                                    <span :class="[statusClass(project.status), 'px-2 py-0.5 rounded-full text-[10px] font-medium']">{{ project.status }}</span>
                                </div>
                                <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-500 flex-wrap">
                                    <span>{{ project.location }}</span>
                                    <span>{{ formatCompactCurrency(project.totalCost) }}</span>
                                    <span>{{ getFundCategoryLabel(project) }}</span>
                                    <span>{{ project.accomplishment || 0 }}%</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-gray-300 flex-shrink-0 cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24" @click.stop="openProject(project)">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <div class="relative project-actions-menu">
                                <button
                                    @click.stop="toggleActionsMenu(project.id)"
                                    class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                                    title="Project actions"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </button>
                                <div v-if="showActionsMenu === project.id" class="absolute right-0 top-8 z-10 w-36 rounded-lg border border-gray-200 bg-white shadow-lg">
                                    <button @click="handleEditFromMenu(project)" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 transition">Edit</button>
                                    <button @click="handleDeleteFromMenu(project.id)" class="block w-full rounded-b-lg px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 transition">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid View -->
                <div v-else class="p-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div
                        v-for="project in paginatedProjects"
                        :key="project.id"
                        class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition"
                    >
                        <div class="flex items-start justify-between gap-1 cursor-pointer" @click="openProject(project)">
                            <h4 class="text-sm font-medium text-gray-900 line-clamp-2">{{ project.name }}</h4>
                            <span :class="[statusClass(project.status), 'px-1.5 py-0.5 rounded-full text-[9px] font-medium shrink-0 ml-1']">{{ project.status }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 truncate cursor-pointer" @click="openProject(project)">{{ project.location }}</p>
                        <div class="mt-2 flex items-center gap-1 flex-wrap cursor-pointer" @click="openProject(project)">
                            <span class="text-xs font-medium text-gray-900">{{ formatCompactCurrency(project.totalCost) }}</span>
                            <span class="text-[10px] text-gray-400">{{ getFundCategoryLabel(project) }}</span>
                        </div>
                        <div class="mt-1.5 cursor-pointer" @click="openProject(project)">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Progress</span>
                                <span class="font-medium text-red-600">{{ project.accomplishment || 0 }}%</span>
                            </div>
                            <div class="mt-0.5 h-1 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-1 rounded-full" :class="getAccomplishmentColor(project.accomplishment)" :style="{ width: (project.accomplishment || 0) + '%' }"></div>
                            </div>
                        </div>
                        <div class="mt-2 flex justify-end">
                            <div class="relative project-actions-menu">
                                <button
                                    @click.stop="toggleActionsMenu(project.id)"
                                    class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                                    title="Project actions"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </button>
                                <div v-if="showActionsMenu === project.id" class="absolute right-0 top-8 z-10 w-36 rounded-lg border border-gray-200 bg-white shadow-lg">
                                    <button @click="handleEditFromMenu(project)" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 transition">Edit</button>
                                    <button @click="handleDeleteFromMenu(project.id)" class="block w-full rounded-b-lg px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 transition">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== PAGINATION ===== -->
                <div v-if="filteredProjects.length > 0" class="px-4 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span>Show</span>
                        <select 
                            v-model="perPage" 
                            @change="changePerPage(perPage)"
                            class="px-2 py-1 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-red-500 focus:border-red-500 outline-none bg-white"
                        >
                            <option v-for="option in perPageOptions" :key="option" :value="option">
                                {{ option }}
                            </option>
                        </select>
                        <span>per page</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            @click="goToPage(1)"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded text-xs font-medium text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
                        >
                            First
                        </button>
                        <button
                            @click="goToPage(currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded text-xs font-medium text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
                        >
                            Previous
                        </button>
                        
                        <div class="flex items-center gap-1 mx-1">
                            <button
                                v-for="page in totalPages"
                                :key="page"
                                v-show="
                                    page === 1 ||
                                    page === totalPages ||
                                    Math.abs(page - currentPage) <= 1 ||
                                    (page === currentPage - 2 && currentPage > 3) ||
                                    (page === currentPage + 2 && currentPage < totalPages - 2)
                                "
                                @click="goToPage(page)"
                                :class="[
                                    'px-3 py-1 rounded text-xs font-medium transition',
                                    page === currentPage 
                                        ? 'bg-red-600 text-white' 
                                        : 'text-gray-600 hover:bg-gray-100'
                                ]"
                            >
                                {{ page }}
                            </button>
                            <span v-if="currentPage > 3 && totalPages > 5" class="px-1 text-xs text-gray-400">...</span>
                            <span v-if="currentPage < totalPages - 2 && totalPages > 5" class="px-1 text-xs text-gray-400">...</span>
                        </div>

                        <button
                            @click="goToPage(currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 rounded text-xs font-medium text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
                        >
                            Next
                        </button>
                        <button
                            @click="goToPage(totalPages)"
                            :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 rounded text-xs font-medium text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
                        >
                            Last
                        </button>
                    </div>

                    <div class="text-xs text-gray-400">
                        {{ showingText }}
                    </div>
                </div>
            </div>

            <!-- ===== FLOATING ADD BUTTON ===== -->
            <button
                v-if="!showAddModal"
                @click="openAddModal"
                class="fixed bottom-6 right-6 z-40 inline-flex items-center justify-center gap-2 px-4 h-12 bg-red-600 hover:bg-red-700 active:scale-95 text-white font-medium rounded-full shadow-lg shadow-red-600/30 transition-all"
                title="Add new project"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add Project</span>
            </button>

            <!-- ===== ADD/EDIT PROJECT MODAL ===== -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto">
                        <div class="fixed inset-0 bg-black/40" @click="closeAddModal"></div>
                        <div class="flex min-h-full items-center justify-center p-4">
                            <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-2xl" @click.stop>
                                <div class="flex items-center justify-between border-b px-6 py-4">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        {{ isEditing ? 'Edit Project' : 'Add New Project' }}
                                    </h3>
                                    <button @click="closeAddModal" class="text-gray-400 hover:text-gray-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <form @submit.prevent="addProject" class="p-6 max-h-[70vh] overflow-y-auto">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Project Name <span class="text-red-500">*</span></label>
                                            <input v-model="form.name" required type="text" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Location <span class="text-red-500">*</span></label>
                                            <input v-model="form.location" required type="text" :class="inputClass" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Project Description</label>
                                            <textarea v-model="form.description" rows="3" :class="inputClass" placeholder="Enter project description"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Total Cost (Php) <span class="text-red-500">*</span></label>
                                            <input 
                                                v-model="form.totalCostDisplay"
                                                type="text"
                                                required
                                                :class="inputClass"
                                                placeholder="0.00"
                                                @input="onTotalCostInput"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Original Cost (Php)</label>
                                            <input 
                                                v-model="form.originalCostDisplay"
                                                type="text"
                                                :class="inputClass"
                                                placeholder="0.00"
                                                @input="onOriginalCostInput"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Revised Cost (Php)</label>
                                            <input 
                                                v-model="form.revisedCostDisplay"
                                                type="text"
                                                :class="inputClass"
                                                placeholder="0.00"
                                                @input="onRevisedCostInput"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Fund Category <span class="text-red-500">*</span></label>
                                            <select v-model="form.fundCategory" required :class="inputClass" @change="handleFundCategoryChange">
                                                <option value="">Select category...</option>
                                                <option v-for="cat in fundCategoryOptions" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Source of Fund <span class="text-red-500">*</span></label>
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
                                            <label class="block text-sm font-medium text-gray-700">Year <span class="text-red-500">*</span></label>
                                            <input v-model.number="form.year" required min="2000" max="2100" type="number" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Duration (Days) <span class="text-red-500">*</span></label>
                                            <input v-model.number="form.duration" required min="0" type="number" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Start Date <span class="text-red-500">*</span></label>
                                            <input v-model="form.startDate" required type="date" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Target Completion <span class="text-red-500">*</span></label>
                                            <input v-model="form.targetCompletionDate" required type="date" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Actual Completion</label>
                                            <input v-model="form.actualCompletionDate" type="date" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Revised Date of Completion</label>
                                            <input v-model="form.revisedCompletionDate" type="date" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Time Extension (Days)</label>
                                            <input v-model.number="form.timeExtension" min="0" type="number" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Suspension Order (Days)</label>
                                            <input v-model.number="form.daysSuspensionOrder" min="0" type="number" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Status</label>
                                            <select v-model="form.status" :class="inputClass">
                                                <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Category</label>
                                            <select v-model="form.category" :class="inputClass">
                                                <option v-for="c in categoryOptions" :key="c" :value="c">{{ c }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Accomplishment %</label>
                                            <input v-model.number="form.accomplishment" min="0" max="100" step="0.01" type="number" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Contractor <span class="text-red-500">*</span></label>
                                            <input v-model="form.contractor" required type="text" :class="inputClass" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Remarks</label>
                                            <textarea v-model="form.remarks" rows="2" :class="inputClass"></textarea>
                                        </div>
                                        <div v-if="Object.keys(formErrors).length" class="col-span-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                            Please review the highlighted fields.
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-3 mt-6 border-t pt-4">
                                        <button type="button" @click="closeAddModal" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                        <button
                                            type="submit"
                                            :disabled="isLoading"
                                            class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300 text-white text-sm font-medium rounded-lg"
                                        >
                                            {{ isLoading ? 'Saving...' : (isEditing ? 'Update' : 'Save') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>
        </template>
    </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-active .relative,
.modal-leave-active .relative { 
    transition: transform 0.2s ease;
}                   
.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
}

.project-list-container {
    scroll-margin-top: 20px;
}
</style>
