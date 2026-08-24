<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    projects: {
        type: Array,
        default: () => [],
    },
});

const searchQuery = ref('');
const selectedStatus = ref('');
const selectedYear = ref('');
const selectedCategory = ref('');
const showSuggestions = ref(false);
const highlightedIndex = ref(-1);
const currentPage = ref(1);
const itemsPerPage = ref(10);

const statusOptions = ['Not Started', 'Ongoing', 'Completed', 'Suspended', 'Delayed'];

const yearOptions = computed(() => {
    const years = [...new Set(props.projects.map(p => p.year).filter(Boolean))];
    return years.sort((a, b) => b - a);
});

const categoryOptions = computed(() => {
    const categories = new Set();
    
    props.projects.forEach(project => {
        if (project.fundCategory) {
            categories.add(project.fundCategory.toLowerCase());
        }
        if (project.sourceOfFund) {
            const lowerSource = project.sourceOfFund.toLowerCase();
            if (['national', 'provincial', 'lgu'].includes(lowerSource)) {
                categories.add(lowerSource);
            }
        }
    });
    
    return Array.from(categories);
});

const searchSuggestions = computed(() => {
    if (!searchQuery.value || searchQuery.value.length < 2) return [];
    
    const query = searchQuery.value.toLowerCase();
    const suggestions = new Set();
    
    props.projects.forEach(project => {
        if (project.name?.toLowerCase().includes(query)) {
            suggestions.add(project.name);
        }
        if (project.location?.toLowerCase().includes(query)) {
            suggestions.add(project.location);
        }
        if (project.contractor?.toLowerCase().includes(query)) {
            suggestions.add(project.contractor);
        }
        if (project.sourceOfFund?.toLowerCase().includes(query)) {
            suggestions.add(project.sourceOfFund);
        }
    });
    
    return Array.from(suggestions).slice(0, 8);
});

const filteredProjects = computed(() => {
    let filtered = [...props.projects].reverse();

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(p =>
            p.name?.toLowerCase().includes(query) ||
            p.location?.toLowerCase().includes(query) ||
            p.contractor?.toLowerCase().includes(query) ||
            p.sourceOfFund?.toLowerCase().includes(query)
        );
    }

    if (selectedStatus.value) {
        filtered = filtered.filter(p => p.status === selectedStatus.value);
    }

    if (selectedYear.value) {
        filtered = filtered.filter(p => p.year === Number(selectedYear.value));
    }

    if (selectedCategory.value) {
        filtered = filtered.filter(p => 
            p.fundCategory === selectedCategory.value || 
            p.sourceOfFund === selectedCategory.value
        );
    }

    return filtered;
});

const totalPages = computed(() => Math.ceil(filteredProjects.value.length / itemsPerPage.value));

const paginatedProjects = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredProjects.value.slice(start, start + itemsPerPage.value);
});

const pageOffset = computed(() => (currentPage.value - 1) * itemsPerPage.value);

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    const pages = [];
    if (total <= 5) {
        for (let i = 1; i <= total; i++) pages.push(i);
    } else {
        pages.push(1);
        if (current > 3) pages.push('...');
        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        if (current < total - 2) pages.push('...');
        pages.push(total);
    }
    return pages;
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) currentPage.value = page;
};

const highlightMatch = (text, query) => {
    if (!text || !query) return text;
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark class="bg-red-100 text-red-900 px-0.5 rounded font-medium">$1</mark>');
};

const formatCurrency = (value) => {
    const amount = Number(value);
    if (!Number.isFinite(amount) || amount <= 0) return 'Php 0.00';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};

const statusClass = (status) => {
    const classes = {
        'Not Started': 'bg-slate-50 text-slate-600 border-slate-200',
        'Ongoing': 'bg-blue-50 text-blue-600 border-blue-200',
        'Completed': 'bg-emerald-50 text-emerald-600 border-emerald-200',
        'Suspended': 'bg-amber-50 text-amber-600 border-amber-200',
        'Delayed': 'bg-red-50 text-red-600 border-red-200',
    };
    return classes[status] || classes['Ongoing'];
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedStatus.value = '';
    selectedYear.value = '';
    selectedCategory.value = '';
    showSuggestions.value = false;
    currentPage.value = 1;
};

const selectSuggestion = (suggestion) => {
    searchQuery.value = suggestion;
    showSuggestions.value = false;
};

const handleKeyDown = (e) => {
    if (!showSuggestions.value) return;
    
    const suggestions = searchSuggestions.value;
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightedIndex.value = Math.min(highlightedIndex.value + 1, suggestions.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
    } else if (e.key === 'Enter' && highlightedIndex.value >= 0) {
        e.preventDefault();
        selectSuggestion(suggestions[highlightedIndex.value]);
    } else if (e.key === 'Escape') {
        showSuggestions.value = false;
        highlightedIndex.value = -1;
    }
};

const handleClickOutside = (event) => {
    const searchContainer = document.querySelector('.search-container');
    if (searchContainer && !searchContainer.contains(event.target)) {
        showSuggestions.value = false;
        highlightedIndex.value = -1;
    }
};

watch(searchQuery, () => {
    showSuggestions.value = searchQuery.value.length >= 2;
    highlightedIndex.value = -1;
    currentPage.value = 1;
});

watch([selectedStatus, selectedYear, selectedCategory], () => {
    currentPage.value = 1;
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const page = usePage();

const navigateToProject = (project) => {
    localStorage.setItem('meo_staff_active_tab', 'findproject');
    const role = page.props.auth?.user?.role;
    const routeName = role === 'superadmin'
        ? 'superadmin.projects.details'
        : (role === 'staff' ? 'staff.projects.details' : 'admin.projects.details');
    router.visit(route(routeName, project.id));
};
</script>

<template>
    <div class="w-full flex-1 min-h-[calc(100vh-12rem)] bg-white border border-slate-200 shadow-sm overflow-hidden flex flex-col font-sans">
        <!-- Fixed Search and Filter Section -->
        <div class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200 px-5 py-4 shrink-0">
            
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <svg class="h-4 w-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Project Directory
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Quickly find and filter infrastructure projects</p>
                </div>
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 shadow-sm shrink-0">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Results</span>
                    <span class="bg-white px-2 py-0.5 rounded text-xs font-bold text-slate-800 border border-slate-200">{{ filteredProjects.length }}</span>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="relative search-container mb-3 group/search">
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within/search:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by project name, location, or contractor..."
                        class="w-full pl-10 pr-10 py-2 border border-slate-200 rounded-lg focus:ring-4 focus:ring-red-500/10 focus:border-red-500 outline-none text-xs bg-slate-50/50 hover:bg-white transition-all placeholder:text-slate-400 font-medium text-slate-700 shadow-inner"
                        @keydown="handleKeyDown"
                        @focus="showSuggestions = searchQuery.length >= 2"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''; showSuggestions = false"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded transition-colors"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Autocomplete Suggestions -->
                <div
                    v-if="showSuggestions && searchSuggestions.length > 0"
                    class="absolute z-50 w-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl max-h-56 overflow-y-auto"
                >
                    <div
                        v-for="(suggestion, index) in searchSuggestions"
                        :key="index"
                        @click="selectSuggestion(suggestion)"
                        :class="[
                            'px-4 py-2.5 cursor-pointer text-xs transition-colors flex items-center gap-2.5',
                            highlightedIndex === index 
                                ? 'bg-red-50/50 text-red-700' 
                                : 'hover:bg-slate-50 text-slate-700'
                        ]"
                    >
                        <svg class="h-3.5 w-3.5 flex-shrink-0" :class="highlightedIndex === index ? 'text-red-500' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span v-html="highlightMatch(suggestion, searchQuery)"></span>
                    </div>
                </div>
            </div>

            <!-- Filters Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <select
                        v-model="selectedStatus"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-4 focus:ring-red-500/10 focus:border-red-500 outline-none bg-slate-50/50 hover:bg-white transition-all text-xs font-medium text-slate-700 cursor-pointer"
                    >
                        <option value="">All Statuses</option>
                        <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                    </select>
                </div>

                <div>
                    <select
                        v-model="selectedYear"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-4 focus:ring-red-500/10 focus:border-red-500 outline-none bg-slate-50/50 hover:bg-white transition-all text-xs font-medium text-slate-700 cursor-pointer"
                    >
                        <option value="">All Years</option>
                        <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>

                <div>
                    <select
                        v-model="selectedCategory"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-4 focus:ring-red-500/10 focus:border-red-500 outline-none bg-slate-50/50 hover:bg-white transition-all text-xs font-medium text-slate-700 cursor-pointer"
                    >
                        <option value="">All Funds</option>
                        <option v-for="category in categoryOptions" :key="category" :value="category">
                            {{ category.charAt(0).toUpperCase() + category.slice(1) }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Active Filters -->
            <div v-if="searchQuery || selectedStatus || selectedYear || selectedCategory" class="flex items-center gap-1.5 mt-3 flex-wrap">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mr-1">Active:</span>
                
                <span v-if="searchQuery" class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 rounded text-[10px] font-semibold">
                    "{{ searchQuery }}"
                    <button @click="searchQuery = ''" class="hover:text-red-600 transition-colors"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </span>
                
                <span v-if="selectedStatus" class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 rounded text-[10px] font-semibold">
                    {{ selectedStatus }}
                    <button @click="selectedStatus = ''" class="hover:text-red-600 transition-colors"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </span>
                
                <span v-if="selectedYear" class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 rounded text-[10px] font-semibold">
                    {{ selectedYear }}
                    <button @click="selectedYear = ''" class="hover:text-red-600 transition-colors"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </span>
                
                <span v-if="selectedCategory" class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 rounded text-[10px] font-semibold">
                    {{ selectedCategory }}
                    <button @click="selectedCategory = ''" class="hover:text-red-600 transition-colors"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </span>
                
                <button @click="resetFilters" class="text-[10px] text-red-600 hover:text-red-700 font-bold ml-1 transition-colors">
                    Clear all
                </button>
            </div>
        </div>

        <!-- Scrollable Results Section -->
        <div class="results-container flex-1 overflow-y-auto flex flex-col">
            
            <!-- Empty State -->
            <div v-if="filteredProjects.length === 0" class="flex-1 flex flex-col items-center justify-center text-center py-14">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 border border-slate-200">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-slate-800 mb-1">No projects found</h3>
                <p class="text-xs text-slate-500 mb-4">Try adjusting your search or filters.</p>
                <button @click="resetFilters" class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm">
                    Reset Filters
                </button>
            </div>

            <!-- Mobile Card Layout (below lg) -->
            <div v-else class="lg:hidden flex flex-col">
                <div
                    v-for="(project, index) in paginatedProjects"
                    :key="'m-' + project.id"
                    class="group border-b border-slate-100 last:border-b-0 cursor-pointer hover:bg-slate-50/60 transition-colors"
                    @click="navigateToProject(project)"
                >
                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="text-[10px] font-mono text-slate-400">#{{ pageOffset + index + 1 }}</span>
                                    <span :class="[statusClass(project.status), 'px-1.5 py-px rounded text-[9px] font-bold uppercase tracking-wider border']">
                                        {{ project.status }}
                                    </span>
                                </div>
                                <h3 class="text-[13px] font-semibold text-slate-800 leading-snug line-clamp-2 group-hover:text-red-600 transition-colors" v-html="highlightMatch(project.name, searchQuery)"></h3>
                            </div>
                            <svg class="h-4 w-4 text-slate-300 group-hover:text-red-400 shrink-0 mt-1 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>

                        <div class="flex items-center gap-1 text-[11px] text-slate-500 mb-3">
                            <svg class="h-3 w-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="truncate" v-html="highlightMatch(project.location, searchQuery)"></span>
                        </div>

                        <div class="flex items-center justify-between gap-3 text-[11px]">
                            <span class="font-semibold text-slate-700">{{ formatCurrency(project.totalCost) }}</span>
                            <div class="flex items-center gap-2 flex-1 max-w-[140px]">
                                <div class="flex-1 bg-slate-100 rounded-full h-1 overflow-hidden">
                                    <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 h-full rounded-full transition-all duration-500" :style="{ width: `${Math.min(project.accomplishment || 0, 100)}%` }"></div>
                                </div>
                                <span class="text-[10px] font-bold text-slate-500 tabular-nums">{{ project.accomplishment || 0 }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Table Layout (lg and above) -->
            <div v-if="filteredProjects.length > 0" class="hidden lg:block flex-1">
                <table class="project-table w-full">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200">
                            <th class="pl-5 pr-2 py-3.5 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider w-12">#</th>
                            <th class="px-4 py-3.5 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Project Name</th>
                            <th class="px-4 py-3.5 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider w-[180px]">Contractor</th>
                            <th class="px-4 py-3.5 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider w-[120px]">Source</th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-bold text-slate-500 uppercase tracking-wider w-20">Year</th>
                            <th class="px-4 py-3.5 text-right text-[10px] font-bold text-slate-500 uppercase tracking-wider w-36">Total Cost</th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-bold text-slate-500 uppercase tracking-wider w-40">Progress</th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-bold text-slate-500 uppercase tracking-wider w-28">Status</th>
                            <th class="pr-5 pl-2 py-3.5 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(project, index) in paginatedProjects"
                            :key="'d-' + project.id"
                            class="group border-b border-slate-100 last:border-b-0 cursor-pointer hover:bg-red-50/30 transition-colors"
                            @click="navigateToProject(project)"
                        >
                            <!-- ID -->
                            <td class="pl-5 pr-2 py-3.5">
                                <span class="text-[11px] font-mono text-slate-400">{{ pageOffset + index + 1 }}</span>
                            </td>

                            <!-- Project Name & Location -->
                            <td class="px-4 py-3.5">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-slate-800 group-hover:text-red-600 transition-colors leading-tight" v-html="highlightMatch(project.name, searchQuery)"></p>
                                    <div class="flex items-center gap-1 mt-1">
                                        <svg class="h-3 w-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="text-[11px] text-slate-500" v-html="highlightMatch(project.location, searchQuery)"></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Contractor -->
                            <td class="px-4 py-3.5">
                                <span class="text-[11px] text-slate-600 font-medium" v-html="highlightMatch(project.contractor, searchQuery)"></span>
                            </td>

                            <!-- Source of Fund -->
                            <td class="px-4 py-3.5">
                                <span class="text-[11px] text-slate-600" v-html="highlightMatch(project.sourceOfFund, searchQuery)"></span>
                            </td>

                            <!-- Year -->
                            <td class="px-4 py-3.5 text-center">
                                <span class="text-[11px] text-slate-600 font-medium tabular-nums">{{ project.year }}</span>
                            </td>

                            <!-- Total Cost -->
                            <td class="px-4 py-3.5 text-right">
                                <span class="text-xs font-semibold text-slate-700 tabular-nums">{{ formatCurrency(project.totalCost) }}</span>
                            </td>

                            <!-- Progress -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2 max-w-[140px] mx-auto">
                                    <div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 h-full rounded-full transition-all duration-500" :style="{ width: `${Math.min(project.accomplishment || 0, 100)}%` }"></div>
                                    </div>
                                    <span class="text-[10px] font-bold text-slate-500 tabular-nums w-8 text-right">{{ project.accomplishment || 0 }}%</span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3.5 text-center">
                                <span :class="[statusClass(project.status), 'inline-block px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border']">
                                    {{ project.status }}
                                </span>
                            </td>

                            <!-- Arrow -->
                            <td class="pr-5 pl-2 py-3.5">
                                <svg class="h-4 w-4 text-slate-300 group-hover:text-red-400 group-hover:translate-x-0.5 transition-all duration-150 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
        </div>

        <!-- Pagination Footer -->
        <div v-if="filteredProjects.length > 0" class="mt-auto border-t border-slate-200 bg-white px-5 py-3.5 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
            <!-- Left: Info & Per-page -->
            <div class="flex items-center gap-4 text-[11px] text-slate-500">
                <span>
                    Showing <span class="font-semibold text-slate-700">{{ pageOffset + 1 }}</span>–<span class="font-semibold text-slate-700">{{ Math.min(pageOffset + itemsPerPage, filteredProjects.length) }}</span> of <span class="font-semibold text-slate-700">{{ filteredProjects.length }}</span>
                </span>
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400">Per page</span>
                    <select
                        v-model.number="itemsPerPage"
                        @change="currentPage = 1"
                        class="px-1.5 py-0.5 border border-slate-200 rounded text-[11px] font-medium text-slate-700 bg-white focus:ring-2 focus:ring-red-500/10 focus:border-red-500 outline-none cursor-pointer"
                    >
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <!-- Right: Page Buttons -->
            <div class="flex items-center gap-1">
                <!-- Previous -->
                <button
                    @click="goToPage(currentPage - 1)"
                    :disabled="currentPage === 1"
                    class="p-1.5 rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>

                <!-- Page Numbers -->
                <template v-for="page in visiblePages" :key="'p-' + page">
                    <span v-if="page === '...'" class="px-1 text-[11px] text-slate-400">…</span>
                    <button
                        v-else
                        @click="goToPage(page)"
                        :class="[
                            'min-w-[28px] h-7 rounded-md text-[11px] font-semibold transition-colors',
                            currentPage === page
                                ? 'bg-red-600 text-white shadow-sm'
                                : 'text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        {{ page }}
                    </button>
                </template>

                <!-- Next -->
                <button
                    @click="goToPage(currentPage + 1)"
                    :disabled="currentPage === totalPages"
                    class="p-1.5 rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Smooth Custom Scrollbar */
.results-container {
    scroll-behavior: smooth;
}

.results-container::-webkit-scrollbar {
    width: 5px;
}

.results-container::-webkit-scrollbar-track {
    background: transparent;
}

.results-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.results-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Glassmorphism for sticky header */
.sticky {
    position: sticky;
    top: 0;
    z-index: 40;
}

/* Table styles */
.project-table {
    border-collapse: separate;
    border-spacing: 0;
}

.project-table thead {
    position: sticky;
    top: 0;
    z-index: 5;
}

.project-table th {
    white-space: nowrap;
    user-select: none;
}

.project-table tbody tr {
    transition: background-color 0.15s ease;
}

/* Line clamp utility */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Remove default select styling to let Tailwind take over cleaner */
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}
</style>
