<script setup>
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';

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
const isFocused = ref(false);

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
    let filtered = [...props.projects];

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

const isSearching = computed(() => {
    return searchQuery.value || selectedStatus.value || selectedYear.value || selectedCategory.value;
});

const highlightMatch = (text, query) => {
    if (!text || !query) return text;
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<em class="font-bold not-italic">$1</em>');
};

const formatCurrency = (value) => {
    const amount = Number(value);
    if (!Number.isFinite(amount) || amount <= 0) return 'Php 0.00';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 0 }).format(amount);
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

const resetFilters = () => {
    searchQuery.value = '';
    selectedStatus.value = '';
    selectedYear.value = '';
    selectedCategory.value = '';
    showSuggestions.value = false;
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

const handleBlur = () => {
    setTimeout(() => {
        isFocused.value = false;
        showSuggestions.value = false;
        highlightedIndex.value = -1;
    }, 200);
};

watch(searchQuery, () => {
    showSuggestions.value = searchQuery.value.length >= 2;
    highlightedIndex.value = -1;
});
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Google-style Search Interface -->
        <div :class="[
            'transition-all duration-500 ease-in-out bg-white border-b',
            isFocused || isSearching ? 'pt-8 pb-6' : 'pt-20 pb-10'
        ]">
            <div class="max-w-[632px] mx-auto px-4">
                <!-- Logo Area -->
                <div v-if="!isFocused && !isSearching" class="text-center mb-8">
                    <div class="text-5xl font-bold mb-3">
                        <span class="text-red-600">Projects</span>
                    </div>
                    <p class="text-sm text-gray-500">Search infrastructure projects across the Philippines</p>
                </div>

                <!-- Search Bar -->
                <div class="relative" :class="{ 'shadow-lg': isFocused || showSuggestions }">
                    <div :class="[
                        'flex items-center border rounded-full transition-all duration-200',
                        isFocused || showSuggestions
                            ? 'border-red-500 shadow-md bg-white'
                            : 'border-gray-200 hover:shadow-md bg-white'
                    ]">
                        <svg class="ml-5 h-5 w-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search projects by name, location, or contractor"
                            class="flex-1 px-4 py-4 bg-transparent border-none outline-none text-base text-gray-700 placeholder-gray-400"
                            @keydown="handleKeyDown"
                            @focus="isFocused = true; showSuggestions = searchQuery.length >= 2"
                            @blur="handleBlur"
                            autocomplete="off"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''; showSuggestions = false"
                            class="mr-3 p-1 hover:bg-gray-100 rounded-full transition-colors"
                        >
                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <div class="h-6 w-px bg-gray-200 mx-2"></div>
                        <button class="mr-4 p-1 hover:bg-gray-100 rounded-full transition-colors">
                            <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Search Suggestions Dropdown -->
                    <div
                        v-if="showSuggestions && searchSuggestions.length > 0"
                        class="absolute top-full left-0 right-0 mt-1 bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden z-50"
                    >
                        <div
                            v-for="(suggestion, index) in searchSuggestions"
                            :key="index"
                            @mousedown.prevent="selectSuggestion(suggestion)"
                            :class="[
                                'flex items-center gap-3 px-6 py-3 cursor-pointer transition-colors',
                                highlightedIndex === index ? 'bg-red-50' : 'hover:bg-gray-50'
                            ]"
                        >
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span class="text-sm text-gray-700" v-html="highlightMatch(suggestion, searchQuery)"></span>
                        </div>
                    </div>
                </div>

                <!-- Filter Chips -->
                <div v-if="isSearching" class="flex items-center justify-center gap-2 mt-4 flex-wrap">
                    <div class="flex gap-2 flex-wrap justify-center">
                        <span v-if="selectedStatus" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 text-red-700 rounded-full text-xs font-medium">
                            {{ selectedStatus }}
                            <button @click="selectedStatus = ''" class="hover:bg-red-100 rounded-full p-0.5">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                        <span v-if="selectedYear" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 text-red-700 rounded-full text-xs font-medium">
                            {{ selectedYear }}
                            <button @click="selectedYear = ''" class="hover:bg-red-100 rounded-full p-0.5">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                        <span v-if="selectedCategory" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 text-red-700 rounded-full text-xs font-medium capitalize">
                            {{ selectedCategory }}
                            <button @click="selectedCategory = ''" class="hover:bg-red-100 rounded-full p-0.5">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                    <button
                        @click="resetFilters"
                        class="text-xs text-red-600 hover:text-red-800 hover:underline ml-2"
                    >
                        Clear all
                    </button>
                </div>

                <!-- Filter Bar -->
                <div class="flex items-center justify-center gap-2 mt-6 border-b border-gray-200 pb-3">
                    <div class="flex gap-2">
                        <select
                            v-model="selectedStatus"
                            class="px-3 py-1.5 border border-gray-200 rounded-full text-xs text-gray-600 bg-white hover:bg-gray-50 focus:outline-none focus:border-red-300 cursor-pointer"
                        >
                            <option value="">Any status</option>
                            <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                        </select>
                        <select
                            v-model="selectedYear"
                            class="px-3 py-1.5 border border-gray-200 rounded-full text-xs text-gray-600 bg-white hover:bg-gray-50 focus:outline-none focus:border-red-300 cursor-pointer"
                        >
                            <option value="">Any year</option>
                            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                        </select>
                        <select
                            v-model="selectedCategory"
                            class="px-3 py-1.5 border border-gray-200 rounded-full text-xs text-gray-600 bg-white hover:bg-gray-50 focus:outline-none focus:border-red-300 cursor-pointer"
                        >
                            <option value="">Any category</option>
                            <option v-for="category in categoryOptions" :key="category" :value="category" class="capitalize">
                                {{ category }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="max-w-[1200px] mx-auto px-4 py-8">
            <!-- Result count when searching -->
            <div v-if="isSearching" class="mb-6">
                <div class="text-sm text-gray-500 mb-4">
                    About {{ filteredProjects.length }} result{{ filteredProjects.length !== 1 ? 's' : '' }}
                    <span v-if="searchQuery"> for <strong>"{{ searchQuery }}"</strong></span>
                </div>
            </div>

            <!-- No Results -->
            <div v-if="filteredProjects.length === 0" class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <p class="text-lg text-gray-700 mb-2 mt-4">No projects found matching your search</p>
                <p class="text-sm text-gray-500 mb-4">Try different keywords or remove filters</p>
                <button @click="resetFilters" class="text-red-600 hover:text-red-800 text-sm hover:underline">
                    Clear all filters
                </button>
            </div>

            <!-- Google-style Results when searching -->
            <div v-else-if="isSearching" class="max-w-[652px] mx-auto space-y-8">
                <div
                    v-for="project in filteredProjects"
                    :key="project.id"
                    class="group cursor-pointer"
                >
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <span>projects.dpwh.gov.ph</span>
                        <span>›</span>
                        <span class="truncate">{{ project.year }}</span>
                    </div>

                    <h3 class="text-xl text-red-700 hover:underline mb-1 leading-tight">
                        <span v-html="highlightMatch(project.name, searchQuery)"></span>
                    </h3>

                    <p class="text-sm text-gray-600 leading-relaxed mb-2 line-clamp-2">
                        <span v-if="project.location">
                            <svg class="h-3.5 w-3.5 inline-block mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span v-html="highlightMatch(project.location, searchQuery)"></span>
                        </span>
                        <span v-if="project.contractor" class="ml-4">
                            <svg class="h-3.5 w-3.5 inline-block mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span v-html="highlightMatch(project.contractor, searchQuery)"></span>
                        </span>
                    </p>

                    <div class="flex items-center gap-4 text-xs text-gray-500 flex-wrap">
                        <span :class="[statusClass(project.status), 'px-2 py-0.5 rounded-full text-xs font-medium flex items-center gap-1']">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ project.status }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ formatCurrency(project.totalCost) }}
                        </span>
                        <span v-if="project.sourceOfFund" class="capitalize flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            {{ project.sourceOfFund }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            {{ project.accomplishment || 0 }}% complete
                        </span>
                    </div>
                </div>
            </div>

            <!-- List View for All Projects (default view) -->
            <div v-else>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">All Projects</h2>
                    <div class="text-sm text-gray-500">
                        {{ filteredProjects.length }} project{{ filteredProjects.length !== 1 ? 's' : '' }}
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden divide-y divide-gray-100">
                    <div
                        v-for="project in filteredProjects"
                        :key="project.id"
                        class="p-6 hover:bg-gray-50 transition-colors cursor-pointer group"
                    >
                        <div class="flex items-start gap-6">
                            <!-- Status Icon -->
                            <div class="shrink-0 mt-1">
                                <div :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center',
                                    project.status === 'Completed' ? 'bg-emerald-50' :
                                    project.status === 'Ongoing' ? 'bg-blue-50' :
                                    project.status === 'Suspended' ? 'bg-amber-50' :
                                    project.status === 'Delayed' ? 'bg-red-50' :
                                    'bg-slate-50'
                                ]">
                                    <svg v-if="project.status === 'Completed'" class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else-if="project.status === 'Ongoing'" class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <svg v-else-if="project.status === 'Suspended'" class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else-if="project.status === 'Delayed'" class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Project Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-4 mb-2">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 group-hover:text-red-600 transition-colors">
                                            <svg class="h-4 w-4 inline-block mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                            </svg>
                                            {{ project.name }}
                                        </h3>
                                        <div class="flex items-center gap-3 mt-1 text-sm text-gray-500">
                                            <span class="flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                {{ project.location }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                {{ project.year }}
                                            </span>
                                        </div>
                                    </div>
                                    <span :class="[statusClass(project.status), 'px-2.5 py-1 rounded-full text-xs font-medium shrink-0']">
                                        {{ project.status }}
                                    </span>
                                </div>

                                <!-- Progress Bar -->
                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                        <span class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                            Progress
                                        </span>
                                        <span class="font-medium">{{ project.accomplishment || 0 }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-red-500 h-2 rounded-full transition-all"
                                            :style="{ width: (project.accomplishment || 0) + '%' }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Footer Details -->
                                <div class="flex items-center gap-6 text-sm">
                                    <div class="flex items-center gap-1.5 text-gray-600">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="font-medium">{{ formatCurrency(project.totalCost) }}</span>
                                    </div>
                                    <div v-if="project.contractor" class="flex items-center gap-1.5 text-gray-600">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        {{ project.contractor }}
                                    </div>
                                    <div v-if="project.sourceOfFund" class="flex items-center gap-1.5 text-gray-600 capitalize">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        {{ project.sourceOfFund }}
                                    </div>
                                </div>
                            </div>

                            <!-- Arrow -->
                            <div class="shrink-0 self-center">
                                <svg class="h-5 w-5 text-gray-300 group-hover:text-red-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>