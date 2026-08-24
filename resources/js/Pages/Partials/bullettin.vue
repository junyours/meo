<script setup>
import axios from 'axios'
import { ref, reactive, computed, onMounted } from 'vue'

const props = defineProps({
    initialBulletins: { type: Array, default: () => [] },
})

// State
const showModal = ref(false)
const modalMode = ref('create') // 'create' | 'edit'
const editingId = ref(null)
const activeTab = ref('active') // 'active' | 'archived'
const searchQuery = ref('')
const selectedCategory = ref('All')
const viewLayout = ref('grid') // 'grid' | 'list'

const bulletins = ref([...props.initialBulletins])

// Form state
const form = reactive({
    title: '',
    category: '',
    summary: '',
    isPublic: true,
})

const categories = ['Operations', 'Notice', 'Schedule', 'Announcement', 'Reminder']
const isSubmitting = ref(false)

const toast = ref({ show: false, message: '', type: 'success' })
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type }
    setTimeout(() => {
        toast.value.show = false
    }, 2800)
}

// Category color mappings (Clean sharp badges)
const getCategoryStyle = (category) => {
    switch (category?.toLowerCase()) {
        case 'operations':
            return { badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' }
        case 'notice':
            return { badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' }
        case 'schedule':
            return { badge: 'bg-indigo-50 text-indigo-700 border-indigo-200', dot: 'bg-indigo-500' }
        case 'announcement':
            return { badge: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' }
        case 'reminder':
            return { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' }
        default:
            return { badge: 'bg-slate-50 text-slate-700 border-slate-200', dot: 'bg-slate-400' }
    }
}

// Computed
const hasFormErrors = computed(() => {
    return !form.title.trim() || !form.category || !form.summary.trim()
})

const modalTitle = computed(() => {
    return modalMode.value === 'create' ? 'Create Bulletin Notice' : 'Edit Bulletin Notice'
})

const modalSubtitle = computed(() => {
    return modalMode.value === 'create'
        ? 'Publish a new official announcement for the office portal.'
        : 'Update details for this bulletin publication.'
})

const activeBulletins = computed(() => {
    return bulletins.value.filter(item => !item.isArchived)
})

const archivedBulletins = computed(() => {
    return bulletins.value.filter(item => item.isArchived)
})

const activeCount = computed(() => activeBulletins.value.length)
const archivedCount = computed(() => archivedBulletins.value.length)

const filteredBulletins = computed(() => {
    let list = activeTab.value === 'active' ? activeBulletins.value : archivedBulletins.value

    if (selectedCategory.value !== 'All') {
        list = list.filter(item => item.category === selectedCategory.value)
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        list = list.filter(item =>
            (item.title && item.title.toLowerCase().includes(q)) ||
            (item.summary && item.summary.toLowerCase().includes(q)) ||
            (item.category && item.category.toLowerCase().includes(q))
        )
    }

    return list
})

// Methods
const openCreateModal = () => {
    modalMode.value = 'create'
    editingId.value = null
    resetForm()
    showModal.value = true
}

const openEditModal = (item) => {
    modalMode.value = 'edit'
    editingId.value = item.id
    form.title = item.title
    form.category = item.category
    form.summary = item.summary
    form.isPublic = item.isPublic ?? true
    showModal.value = true
}

const resetForm = () => {
    form.title = ''
    form.category = ''
    form.summary = ''
    form.isPublic = true
}

const closeModal = () => {
    showModal.value = false
    isSubmitting.value = false
    resetForm()
}

const submitNotice = async () => {
    if (hasFormErrors.value) return

    isSubmitting.value = true

    try {
        const payload = {
            title: form.title.trim(),
            category: form.category,
            summary: form.summary.trim(),
            is_public: form.isPublic
        }
        const response = modalMode.value === 'create'
            ? await axios.post('/bulletins', payload)
            : await axios.put(`/bulletins/${editingId.value}`, payload)

        if (modalMode.value === 'create') {
            bulletins.value.unshift(response.data)
            showToast('Bulletin notice created successfully!', 'success')
        } else {
            bulletins.value = bulletins.value.map(item => item.id === editingId.value ? response.data : item)
            showToast('Bulletin notice updated!', 'success')
        }
        closeModal()
    } catch (error) {
        showToast(error.response?.data?.message || 'Unable to save this bulletin.', 'error')
    } finally {
        isSubmitting.value = false
    }
}

const archiveNotice = async (id) => {
    try {
        const response = await axios.post(`/bulletins/${id}/archive`)
        bulletins.value = bulletins.value.map(item => item.id === id ? response.data : item)
        showToast('Notice moved to archive.', 'success')
    } catch (e) {
        showToast('Failed to archive notice.', 'error')
    }
}

const unarchiveNotice = async (id) => {
    try {
        const response = await axios.post(`/bulletins/${id}/restore`)
        bulletins.value = bulletins.value.map(item => item.id === id ? response.data : item)
        showToast('Notice restored to active.', 'success')
    } catch (e) {
        showToast('Failed to restore notice.', 'error')
    }
}

const setVisibility = async (id, isPublic) => {
    try {
        const response = await axios.patch(`/bulletins/${id}/visibility`, { is_public: isPublic })
        bulletins.value = bulletins.value.map(item => item.id === id ? response.data : item)
        showToast(isPublic ? 'Notice is now Public.' : 'Notice is now Private (Internal only).', 'success')
    } catch (e) {
        showToast('Failed to update visibility.', 'error')
    }
}

const deleteNotice = async (id) => {
    if (confirm('Are you sure you want to permanently remove this notice?')) {
        try {
            await axios.delete(`/bulletins/${id}`)
            bulletins.value = bulletins.value.filter(item => item.id !== id)
            showToast('Notice deleted permanently.', 'success')
        } catch (e) {
            showToast('Failed to delete notice.', 'error')
        }
    }
}

onMounted(async () => {
    try {
        const response = await axios.get('/bulletins')
        bulletins.value = response.data
    } catch (error) {
        // Fallback to initial server data
    }
})

const handleEscape = (event) => {
    if (event.key === 'Escape' && showModal.value) {
        closeModal()
    }
}
</script>

<template>
    <div class="space-y-3 font-sans text-slate-800" @keydown="handleEscape">
        
        <!-- Header Banner Card (Clean Square Border, Small & Aesthetic) -->
        <div class="bg-white border border-slate-200 p-4 sm:p-5 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 bg-red-50 text-red-700 border border-red-200 flex items-center justify-center shrink-0">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">Office Bulletins & Notices</h2>
                            <span class="px-2 py-0.5 text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                                {{ activeCount }} Active
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Official directives, schedule announcements, and engineering advisories.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <button
                        @click="openCreateModal"
                        type="button"
                        class="inline-flex items-center gap-1.5 bg-red-700 hover:bg-red-800 text-white px-3 py-1.5 text-xs font-semibold shadow-xs transition cursor-pointer"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>New Notice</span>
                    </button>
                </div>
            </div>

            <!-- Controls: Search, Tabs & Category Filters -->
            <div class="mt-3.5 pt-3 border-t border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-3">
                
                <!-- Left: Status Tabs (Active / Archived) -->
                <div class="flex items-center gap-1 bg-slate-100 p-1 border border-slate-200">
                    <button
                        @click="activeTab = 'active'"
                        type="button"
                        :class="[
                            'px-3 py-1 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer',
                            activeTab === 'active' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        <span class="h-1.5 w-1.5 bg-emerald-500"></span>
                        <span>Active</span>
                        <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 text-slate-600 font-semibold border border-slate-200">{{ activeCount }}</span>
                    </button>
                    <button
                        @click="activeTab = 'archived'"
                        type="button"
                        :class="[
                            'px-3 py-1 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer',
                            activeTab === 'archived' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        <span class="h-1.5 w-1.5 bg-slate-400"></span>
                        <span>Archived</span>
                        <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 text-slate-600 font-semibold border border-slate-200">{{ archivedCount }}</span>
                    </button>
                </div>

                <!-- Right: Search Bar & View Toggle -->
                <div class="flex items-center gap-2 flex-1 md:max-w-md justify-end">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Filter by keyword or category..."
                            class="w-full pl-8.5 pr-3 py-1.5 bg-slate-50 border border-slate-300 text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none transition"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Layout Mode Toggle -->
                    <div class="flex items-center bg-slate-100 p-0.5 border border-slate-200">
                        <button
                            @click="viewLayout = 'grid'"
                            type="button"
                            :class="viewLayout === 'grid' ? 'bg-white text-red-700 shadow-xs' : 'text-slate-400 hover:text-slate-700'"
                            class="p-1.5 transition cursor-pointer"
                            title="Grid Cards"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                        <button
                            @click="viewLayout = 'list'"
                            type="button"
                            :class="viewLayout === 'list' ? 'bg-white text-red-700 shadow-xs' : 'text-slate-400 hover:text-slate-700'"
                            class="p-1.5 transition cursor-pointer"
                            title="Compact List"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Category Pills Quick Filter Bar -->
            <div class="mt-2.5 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1">
                <button
                    @click="selectedCategory = 'All'"
                    type="button"
                    :class="[
                        'px-2.5 py-1 text-[11px] font-bold transition whitespace-nowrap cursor-pointer border',
                        selectedCategory === 'All'
                            ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                            : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'
                    ]"
                >
                    All Categories
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat"
                    @click="selectedCategory = cat"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold transition whitespace-nowrap cursor-pointer border',
                        selectedCategory === cat
                            ? 'bg-red-50 text-red-700 border-red-300 font-bold shadow-xs'
                            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                    ]"
                >
                    <span class="h-1.5 w-1.5" :class="getCategoryStyle(cat).dot"></span>
                    <span>{{ cat }}</span>
                </button>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- GRID VIEW (Small, Readable, Sharp Cards)       -->
        <!-- ============================================== -->
        <div v-if="viewLayout === 'grid' && filteredBulletins.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <div
                v-for="item in filteredBulletins"
                :key="item.id"
                class="group bg-white border border-slate-200 p-4 shadow-2xs hover:shadow-xs transition-all duration-150 flex flex-col justify-between relative hover:border-slate-400"
            >
                <div>
                    <!-- Card Top Header: Category & Visibility Badges -->
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider border', getCategoryStyle(item.category).badge]">
                            <span class="h-1.5 w-1.5" :class="getCategoryStyle(item.category).dot"></span>
                            {{ item.category }}
                        </span>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-slate-400 font-medium">{{ item.date }}</span>
                            
                            <span
                                v-if="item.isArchived"
                                class="px-1.5 py-0.2 text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200"
                            >
                                Archived
                            </span>
                            <span
                                v-else-if="item.isPublic"
                                class="inline-flex items-center gap-1 px-1.5 py-0.2 text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                title="Visible to Public"
                            >
                                <span class="h-1.5 w-1.5 bg-emerald-500"></span>
                                Public
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 px-1.5 py-0.2 text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                title="Internal Only"
                            >
                                <span class="h-1.5 w-1.5 bg-amber-500"></span>
                                Private
                            </span>
                        </div>
                    </div>

                    <!-- Notice Title -->
                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 group-hover:text-red-700 transition line-clamp-1 leading-snug">
                        {{ item.title }}
                    </h3>

                    <!-- Notice Summary -->
                    <p class="mt-1.5 text-xs text-slate-600 leading-relaxed line-clamp-3">
                        {{ item.summary }}
                    </p>
                </div>

                <!-- Card Footer Actions (Clean & Sharp) -->
                <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                    <span v-if="item.archivedAt" class="text-[10px] text-slate-400 italic truncate max-w-[140px]">
                        Archived {{ item.archivedAt }}
                    </span>
                    <span v-else class="text-[10px] text-slate-400 font-mono">
                        Official Notice
                    </span>

                    <div class="flex items-center gap-1">
                        <!-- Edit Button -->
                        <button
                            v-if="!item.isArchived"
                            @click="openEditModal(item)"
                            class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition"
                            title="Edit Notice"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>

                        <!-- Toggle Visibility -->
                        <button
                            v-if="!item.isArchived && !item.isPublic"
                            @click="setVisibility(item.id, true)"
                            class="p-1 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition"
                            title="Make Public"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <button
                            v-if="!item.isArchived && item.isPublic"
                            @click="setVisibility(item.id, false)"
                            class="p-1 text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition"
                            title="Make Private (Internal only)"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                        </button>

                        <!-- Archive / Restore -->
                        <button
                            v-if="!item.isArchived"
                            @click="archiveNotice(item.id)"
                            class="p-1 text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition"
                            title="Archive Notice"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                        </button>
                        <button
                            v-else
                            @click="unarchiveNotice(item.id)"
                            class="p-1 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition"
                            title="Restore from Archive"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>

                        <!-- Delete Button -->
                        <button
                            @click="deleteNotice(item.id)"
                            class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                            title="Permanently Delete"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- COMPACT LIST VIEW (Sharp Bordered Rows)        -->
        <!-- ============================================== -->
        <div v-else-if="viewLayout === 'list' && filteredBulletins.length > 0" class="bg-white border border-slate-200 shadow-2xs divide-y divide-slate-100 overflow-hidden">
            <div
                v-for="item in filteredBulletins"
                :key="item.id"
                class="p-3 sm:p-3.5 hover:bg-slate-50/70 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 group"
            >
                <div class="space-y-1 min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span :class="['inline-flex items-center gap-1 px-2 py-0.2 text-[10px] font-bold uppercase tracking-wider border', getCategoryStyle(item.category).badge]">
                            <span class="h-1 w-1" :class="getCategoryStyle(item.category).dot"></span>
                            {{ item.category }}
                        </span>
                        
                        <span v-if="item.isPublic" class="px-1.5 py-0.2 text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Public
                        </span>
                        <span v-else class="px-1.5 py-0.2 text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                            Private
                        </span>

                        <span class="text-[11px] text-slate-400">{{ item.date }}</span>
                    </div>

                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 group-hover:text-red-700 transition truncate">
                        {{ item.title }}
                    </h4>
                    <p class="text-xs text-slate-500 line-clamp-1">
                        {{ item.summary }}
                    </p>
                </div>

                <div class="flex items-center gap-1 self-end sm:self-center shrink-0">
                    <button
                        v-if="!item.isArchived"
                        @click="openEditModal(item)"
                        class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition"
                        title="Edit"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button
                        v-if="!item.isArchived"
                        @click="archiveNotice(item.id)"
                        class="p-1 text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition"
                        title="Archive"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </button>
                    <button
                        v-else
                        @click="unarchiveNotice(item.id)"
                        class="p-1 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition"
                        title="Restore"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                    <button
                        @click="deleteNotice(item.id)"
                        class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                        title="Delete"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- EMPTY STATE                                    -->
        <!-- ============================================== -->
        <div v-if="filteredBulletins.length === 0" class="bg-white border border-dashed border-slate-300 p-8 text-center space-y-3">
            <div class="h-9 w-9 mx-auto bg-slate-100 text-slate-400 flex items-center justify-center border border-slate-200">
                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div>
                <h3 class="text-xs sm:text-sm font-bold text-slate-800">
                    {{ searchQuery ? 'No matching notices found' : (activeTab === 'active' ? 'No active bulletin notices' : 'No archived notices') }}
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ searchQuery ? 'Try adjusting your search keywords or category filters.' : 'Click "New Notice" to publish an official bulletin update.' }}
                </p>
            </div>
            <button
                v-if="!searchQuery && activeTab === 'active'"
                @click="openCreateModal"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold shadow-xs transition"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Create Notice</span>
            </button>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: CREATE / EDIT BULLETIN NOTICE           -->
        <!-- ============================================== -->
        <Teleport to="body">
            <div
                v-if="showModal"
                @click.self="closeModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs animate-in fade-in duration-150"
                role="dialog"
                aria-modal="true"
            >
                <div class="bg-white shadow-2xl max-w-lg w-full flex flex-col border border-slate-300 animate-in zoom-in-95 duration-150 overflow-hidden">
                    
                    <!-- Modal Header -->
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">{{ modalTitle }}</h3>
                            <p class="text-xs text-slate-500">{{ modalSubtitle }}</p>
                        </div>
                        <button
                            @click="closeModal"
                            type="button"
                            class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition"
                        >
                            <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form Body -->
                    <form @submit.prevent="submitNotice" class="p-5 space-y-3.5">
                        
                        <!-- Title -->
                        <div class="space-y-1">
                            <label for="notice-title" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Title <span class="text-rose-600">*</span>
                            </label>
                            <input
                                id="notice-title"
                                v-model="form.title"
                                type="text"
                                placeholder="e.g. Schedule of Road Widening Assessment"
                                class="w-full px-3 py-2 border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none transition"
                                required
                                autofocus
                            />
                        </div>

                        <!-- Category Selection -->
                        <div class="space-y-1">
                            <label for="notice-category" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Category <span class="text-rose-600">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    id="notice-category"
                                    v-model="form.category"
                                    class="w-full px-3 py-2 border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none transition appearance-none bg-white"
                                    required
                                >
                                    <option value="" disabled>Select category...</option>
                                    <option v-for="cat in categories" :key="cat" :value="cat">
                                        {{ cat }}
                                    </option>
                                </select>
                                <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="space-y-1">
                            <label for="notice-summary" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Summary / Content <span class="text-rose-600">*</span>
                            </label>
                            <textarea
                                id="notice-summary"
                                v-model="form.summary"
                                rows="3"
                                placeholder="Provide brief and clear instructions or announcement details..."
                                class="w-full p-2.5 border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none transition resize-none"
                                required
                                maxlength="300"
                            ></textarea>
                            <div class="flex justify-between text-[11px] text-slate-400">
                                <span>{{ form.summary.length }}/300 characters</span>
                                <span v-if="form.summary.length > 260" class="text-amber-600 font-bold">
                                    {{ 300 - form.summary.length }} left
                                </span>
                            </div>
                        </div>

                        <!-- Visibility Segment -->
                        <div class="space-y-1 pt-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Target Audience Visibility
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    :class="[
                                        'p-2.5 border-2 transition cursor-pointer flex items-center gap-2',
                                        form.isPublic ? 'border-emerald-600 bg-emerald-50/50' : 'border-slate-200 hover:border-slate-300'
                                    ]"
                                >
                                    <input type="radio" v-model="form.isPublic" :value="true" class="sr-only" />
                                    <div class="h-6 w-6 bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-200">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-900">Public Notice</p>
                                        <p class="text-[10px] text-slate-400">Visible to all</p>
                                    </div>
                                </label>

                                <label
                                    :class="[
                                        'p-2.5 border-2 transition cursor-pointer flex items-center gap-2',
                                        !form.isPublic ? 'border-amber-600 bg-amber-50/50' : 'border-slate-200 hover:border-slate-300'
                                    ]"
                                >
                                    <input type="radio" v-model="form.isPublic" :value="false" class="sr-only" />
                                    <div class="h-6 w-6 bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 border border-amber-200">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-900">Internal Only</p>
                                        <p class="text-[10px] text-slate-400">MEO staff only</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="hasFormErrors || isSubmitting"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold text-white bg-red-700 hover:bg-red-800 shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                            >
                                <svg v-if="isSubmitting" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span>{{ isSubmitting ? 'Saving...' : (modalMode === 'create' ? 'Publish Notice' : 'Save Changes') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Floating Toast Notification -->
        <transition
            enter-active-class="transform ease-out duration-200 transition"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast.show"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-3.5 py-2 shadow-lg border text-xs font-semibold"
                :class="toast.type === 'success' ? 'bg-slate-900 text-white border-slate-800' : 'bg-rose-950 text-white border-rose-800'"
            >
                <div class="h-4 w-4 flex items-center justify-center shrink-0" :class="toast.type === 'success' ? 'text-emerald-400' : 'text-rose-400'">
                    <svg v-if="toast.type === 'success'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <span>{{ toast.message }}</span>
            </div>
        </transition>

    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
