<script setup>
import { ref, computed, reactive, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    users: {
        type: Array,
        default: () => []
    },
});

// ========== STATE MANAGEMENT ==========
const searchQuery = ref('');
const roleFilter = ref('all');
const verifiedFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const sortBy = ref('created_at');
const sortDir = ref('desc');
const page = ref(1);
const pageSize = ref(10);

// Modals
const isModalOpen = ref(false);
const isEditing = ref(false);
const isResetModalOpen = ref(false);
const isSubmitting = ref(false);
const isSendingLink = ref(false);
const isDeleting = ref(false);
const copiedPassword = ref(false);

// Toast notification state
const toast = reactive({
    show: false,
    type: 'success', // 'success' | 'error'
    message: '',
});

let toastTimeout = null;
const showToast = (message, type = 'success') => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.show = false;
    }, 4000);
};

const localUsers = ref([...(props.users || [])]);

watch(() => props.users, (newUsers) => {
    localUsers.value = [...(newUsers || [])];
}, { deep: true });

// Form state for Add/Edit
const initialForm = {
    id: null,
    name: '',
    email: '',
    role: 'staff',
    password: '',
    email_verified: false,
};
const form = reactive({ ...initialForm });
const editingUser = ref(null);

// Form state for Password Reset / Account Management
const selectedUser = ref(null);
const resetForm = reactive({
    password: '',
    autoGenerate: true,
    temporaryPasswordResult: '',
});

// ========== STATS COMPUTED ==========
const userStats = computed(() => {
    const all = localUsers.value || [];
    return {
        total: all.length,
        superadmins: all.filter(u => u.role === 'superadmin').length,
        admins: all.filter(u => u.role === 'admin').length,
        staff: all.filter(u => u.role === 'staff' || !u.role).length,
        verified: all.filter(u => u.email_verified_at).length,
    };
});

// ========== FILTERING & SORTING ==========
const filteredUsers = computed(() => {
    let list = (localUsers.value || []).slice();

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(u => 
            (u.name && u.name.toLowerCase().includes(q)) || 
            (u.email && u.email.toLowerCase().includes(q))
        );
    }

    if (roleFilter.value !== 'all') {
        list = list.filter(u => u.role === roleFilter.value);
    }

    if (verifiedFilter.value !== 'all') {
        if (verifiedFilter.value === 'verified') {
            list = list.filter(u => !!u.email_verified_at);
        } else if (verifiedFilter.value === 'unverified') {
            list = list.filter(u => !u.email_verified_at);
        }
    }

    if (dateFrom.value) {
        const from = new Date(dateFrom.value);
        from.setHours(0, 0, 0, 0);
        list = list.filter(u => u.created_at && new Date(u.created_at) >= from);
    }
    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);
        list = list.filter(u => u.created_at && new Date(u.created_at) <= to);
    }

    list.sort((a, b) => {
        let fieldA = a[sortBy.value];
        let fieldB = b[sortBy.value];

        if (sortBy.value === 'created_at') {
            fieldA = new Date(fieldA || 0).getTime();
            fieldB = new Date(fieldB || 0).getTime();
        } else if (typeof fieldA === 'string') {
            fieldA = fieldA.toLowerCase();
            fieldB = (fieldB || '').toLowerCase();
        }

        if (fieldA < fieldB) return sortDir.value === 'asc' ? -1 : 1;
        if (fieldA > fieldB) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });

    return list;
});

const hasActiveFilters = computed(() => {
    return searchQuery.value !== '' || 
           roleFilter.value !== 'all' || 
           verifiedFilter.value !== 'all' || 
           dateFrom.value !== '' || 
           dateTo.value !== '' || 
           sortBy.value !== 'created_at' || 
           sortDir.value !== 'desc';
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / pageSize.value)));
const paginatedUsers = computed(() => {
    const start = (page.value - 1) * pageSize.value;
    return filteredUsers.value.slice(start, start + pageSize.value);
});

watch([searchQuery, roleFilter, verifiedFilter, dateFrom, dateTo, pageSize], () => {
    page.value = 1;
});

// ========== ACTIONS ==========
const openModal = (user = null) => {
    if (user) {
        isEditing.value = true;
        editingUser.value = user;
        Object.assign(form, {
            id: user.id,
            name: user.name || '',
            email: user.email || '',
            role: user.role || 'staff',
            password: '',
            email_verified: !!user.email_verified_at,
        });
    } else {
        isEditing.value = false;
        editingUser.value = null;
        Object.assign(form, { ...initialForm });
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingUser.value = null;
};

const openResetModal = (user) => {
    selectedUser.value = user;
    resetForm.password = '';
    resetForm.autoGenerate = true;
    resetForm.temporaryPasswordResult = '';
    copiedPassword.value = false;
    isResetModalOpen.value = true;
};

const closeResetModal = () => {
    isResetModalOpen.value = false;
    selectedUser.value = null;
};

const saveUser = async () => {
    if (!form.name.trim() || !form.email.trim()) return;

    isSubmitting.value = true;
    try {
        if (isEditing.value) {
            const res = await axios.put(route('superadmin.users.update', form.id), {
                name: form.name,
                email: form.email,
                role: form.role,
                email_verified: form.email_verified,
            });
            const idx = localUsers.value.findIndex(u => u.id === form.id);
            if (idx !== -1 && res.data.user) {
                localUsers.value[idx] = res.data.user;
            }
            showToast('User profile updated successfully.');
        } else {
            const res = await axios.post(route('superadmin.users.store'), {
                name: form.name,
                email: form.email,
                role: form.role,
                password: form.password || undefined,
                email_verified: form.email_verified,
            });
            if (res.data.user) {
                localUsers.value.unshift(res.data.user);
            }
            if (res.data.temporary_password) {
                showToast(`User created with temporary password: ${res.data.temporary_password}`);
            } else {
                showToast('New user account created successfully.');
            }
        }
        closeModal();
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save user account.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const handleDirectResetPassword = async () => {
    if (!selectedUser.value) return;

    isSubmitting.value = true;
    try {
        const res = await axios.post(route('superadmin.users.reset-password', selectedUser.value.id), {
            password: resetForm.autoGenerate ? null : resetForm.password,
            auto_generate: resetForm.autoGenerate,
        });

        resetForm.temporaryPasswordResult = res.data.temporary_password;
        showToast(`Password successfully reset for ${selectedUser.value.name}!`);
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to reset user password.';
        showToast(msg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const handleSendResetEmail = async () => {
    if (!selectedUser.value) return;

    isSendingLink.value = true;
    try {
        const res = await axios.post(route('superadmin.users.send-reset-link', selectedUser.value.id));
        showToast(res.data.message || `Password reset link sent to ${selectedUser.value.email}.`);
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to send password reset link.';
        showToast(msg, 'error');
    } finally {
        isSendingLink.value = false;
    }
};

const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        copiedPassword.value = true;
        setTimeout(() => {
            copiedPassword.value = false;
        }, 2500);
    });
};

const handleDeleteUser = async (user) => {
    if (!confirm(`Are you sure you want to delete user "${user.name}" (${user.email})? This action cannot be undone.`)) {
        return;
    }

    isDeleting.value = true;
    try {
        await axios.delete(route('superadmin.users.destroy', user.id));
        localUsers.value = localUsers.value.filter(u => u.id !== user.id);
        showToast(`User ${user.name} has been deleted.`);
        if (isResetModalOpen.value) closeResetModal();
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to delete user account.';
        showToast(msg, 'error');
    } finally {
        isDeleting.value = false;
    }
};

const exportCSV = () => {
    if (filteredUsers.value.length === 0) {
        showToast('No users to export.', 'error');
        return;
    }
    
    const rows = filteredUsers.value.map(u => ({
        ID: u.id,
        Name: u.name,
        Email: u.email,
        Role: getRoleLabel(u.role),
        Status: u.email_verified_at ? 'Verified' : 'Pending',
        'Registered Date': formatDate(u.created_at),
    }));
    
    const header = Object.keys(rows[0]).join(',') + '\n';
    const csv = header + rows.map(r => 
        Object.values(r).map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')
    ).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `users_directory_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const getRoleBadge = (role) => {
    const map = {
        superadmin: 'bg-rose-50 text-rose-700 border-rose-200 ring-1 ring-rose-500/10',
        admin: 'bg-blue-50 text-blue-700 border-blue-200 ring-1 ring-blue-500/10',
        staff: 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-1 ring-emerald-500/10',
    };
    return map[role] || map.staff;
};

const getRoleDot = (role) => {
    const map = {
        superadmin: 'bg-rose-500',
        admin: 'bg-blue-500',
        staff: 'bg-emerald-500',
    };
    return map[role] || map.staff;
};

const getRoleLabel = (role) => {
    const map = {
        superadmin: 'Super Admin',
        admin: 'Admin',
        staff: 'Staff',
    };
    return map[role] || role || 'Staff';
};

const getInitials = (name) => {
    if (!name) return 'U';
    return name.split(' ')
        .filter(Boolean)
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const formatDate = (date) => {
    if (!date) return '—';
    try {
        return new Date(date).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    } catch {
        return '—';
    }
};

const clearFilters = () => {
    searchQuery.value = '';
    roleFilter.value = 'all';
    verifiedFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    sortBy.value = 'created_at';
    sortDir.value = 'desc';
    page.value = 1;
};
</script>

<template>
    <div class="space-y-6">
        
        <!-- Toast Notification -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="toast.show" 
                class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl text-sm font-medium border"
                :class="toast.type === 'error' ? 'bg-rose-900 text-white border-rose-700' : 'bg-gray-900 text-white border-gray-700'"
            >
                <svg v-if="toast.type === 'error'" class="w-5 h-5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ toast.message }}</span>
                <button @click="toast.show = false" class="text-white/60 hover:text-white p-1 ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </transition>

        <!-- Top Header & Action Bar -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 sm:text-2xl">User Directory & Account Management</h2>
                <p class="text-xs text-gray-500 sm:text-sm mt-0.5">Manage user accounts, privileges, password resets, and account recovery</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <button 
                    @click="exportCSV" 
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 active:bg-gray-100 transition shadow-sm"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export CSV</span>
                </button>
                <button 
                    @click="openModal()" 
                    class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-semibold text-white bg-red-600 hover:bg-red-700 active:bg-red-800 rounded-xl shadow-sm hover:shadow transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add User</span>
                </button>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4">
            <div class="border border-gray-200/80 bg-white p-4 sm:p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Users</span>
                    <span class="flex h-8 w-8 items-center justify-center bg-gray-100 text-gray-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900">{{ userStats.total }}</div>
                <div class="mt-1 text-xs text-gray-500">Active municipal accounts</div>
            </div>

            <div class="border border-rose-100 bg-white p-4 sm:p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-rose-600">Super Admin</span>
                    <span class="flex h-8 w-8 items-center justify-center bg-rose-50 text-rose-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-2 text-2xl font-bold text-rose-700">{{ userStats.superadmins }}</div>
                <div class="mt-1 text-xs text-rose-500">Executive & recovery authority</div>
            </div>

            <div class="border border-blue-100 bg-white p-4 sm:p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-blue-600">Admin</span>
                    <span class="flex h-8 w-8 items-center justify-center bg-blue-50 text-blue-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-2 text-2xl font-bold text-blue-700">{{ userStats.admins }}</div>
                <div class="mt-1 text-xs text-blue-500">Department management</div>
            </div>

            <div class="border border-emerald-100 bg-white p-4 sm:p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Staff</span>
                    <span class="flex h-8 w-8 items-center justify-center bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-2 text-2xl font-bold text-emerald-700">{{ userStats.staff }}</div>
                <div class="mt-1 text-xs text-emerald-500">Field & project viewers</div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="border border-gray-200/80 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3">
                <!-- Search row -->
                <div class="flex flex-col sm:flex-row gap-2.5">
                    <div class="relative flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 absolute left-3.5 top-3 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.604 10.604z" />
                        </svg>
                        <input 
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by name, email address..."
                            class="w-full pl-10 pr-9 py-2 text-xs sm:text-sm bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition placeholder:text-gray-400"
                        />
                        <button 
                            v-if="searchQuery" 
                            @click="searchQuery = ''"
                            class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <button 
                        v-if="hasActiveFilters"
                        @click="clearFilters" 
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs sm:text-sm text-red-600 bg-red-50 hover:bg-red-100 border border-red-100 rounded-xl transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span>Reset Filters</span>
                    </button>
                </div>

                <!-- Filters row -->
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-2.5 pt-1">
                    <!-- Role Filter -->
                    <div class="flex items-center">
                        <select 
                            v-model="roleFilter" 
                            class="w-full sm:w-auto text-xs sm:text-sm px-3 py-1.5 bg-gray-50/60 border border-gray-200 rounded-xl text-gray-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition cursor-pointer"
                        >
                            <option value="all">All Roles</option>
                            <option value="superadmin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>

                    <!-- Verification Filter -->
                    <div class="flex items-center">
                        <select 
                            v-model="verifiedFilter" 
                            class="w-full sm:w-auto text-xs sm:text-sm px-3 py-1.5 bg-gray-50/60 border border-gray-200 rounded-xl text-gray-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition cursor-pointer"
                        >
                            <option value="all">All Status</option>
                            <option value="verified">Verified</option>
                            <option value="unverified">Pending</option>
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="col-span-2 sm:col-span-1 flex items-center gap-1.5 bg-gray-50/60 border border-gray-200 rounded-xl px-2.5 py-1">
                        <span class="text-[11px] font-medium text-gray-400">Date:</span>
                        <input 
                            v-model="dateFrom" 
                            type="date" 
                            class="text-xs bg-transparent border-0 p-0 text-gray-700 focus:ring-0 focus:outline-none" 
                        />
                        <span class="text-xs text-gray-300">—</span>
                        <input 
                            v-model="dateTo" 
                            type="date" 
                            class="text-xs bg-transparent border-0 p-0 text-gray-700 focus:ring-0 focus:outline-none" 
                        />
                    </div>

                    <!-- Sort Filter -->
                    <div class="flex items-center">
                        <select 
                            v-model="sortBy" 
                            class="w-full sm:w-auto text-xs sm:text-sm px-3 py-1.5 bg-gray-50/60 border border-gray-200 rounded-xl text-gray-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition cursor-pointer"
                        >
                            <option value="created_at">Sort: Registration Date</option>
                            <option value="name">Sort: Full Name</option>
                            <option value="email">Sort: Email Address</option>
                            <option value="role">Sort: Role</option>
                        </select>
                    </div>

                    <!-- Sort Direction Toggle -->
                    <button 
                        @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'" 
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs sm:text-sm text-gray-700 bg-gray-50/60 hover:bg-gray-100 border border-gray-200 rounded-xl transition cursor-pointer"
                        :title="sortDir === 'asc' ? 'Ascending (Click for Descending)' : 'Descending (Click for Ascending)'"
                    >
                        <span>{{ sortDir === 'asc' ? 'Ascending' : 'Descending' }}</span>
                        <svg class="w-3.5 h-3.5 text-gray-500 transition-transform" :class="sortDir === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Page Size Selector -->
                    <div class="ml-auto hidden lg:flex items-center gap-2">
                        <span class="text-xs text-gray-400">Show:</span>
                        <select 
                            v-model="pageSize" 
                            class="text-xs px-2.5 py-1 bg-gray-50/60 border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition"
                        >
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Container (Desktop Table + Mobile Cards) -->
        <div class="border border-gray-200/80 bg-white shadow-sm overflow-hidden">
            
            <!-- Desktop Table View -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-200/80 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3.5">User Identity</th>
                            <th class="px-6 py-3.5">Access Role</th>
                            <th class="px-6 py-3.5">Email Status</th>
                            <th class="px-6 py-3.5">Registered</th>
                            <th class="px-6 py-3.5 text-right">Account Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr 
                            v-for="user in paginatedUsers" 
                            :key="user.id" 
                            class="group hover:bg-gray-50/60 transition"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 shrink-0 rounded-xl overflow-hidden bg-gradient-to-tr from-gray-800 to-gray-600 text-white font-semibold flex items-center justify-center text-xs shadow-sm border border-gray-200">
                                        <img 
                                            v-if="user.profile_photo_url" 
                                            :src="user.profile_photo_url" 
                                            :alt="user.name" 
                                            class="w-full h-full object-cover" 
                                        />
                                        <span v-else>{{ getInitials(user.name) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 truncate group-hover:text-red-600 transition-colors">{{ user.name || 'Unnamed User' }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ user.email }}</div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full border" :class="getRoleBadge(user.role)">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="getRoleDot(user.role)"></span>
                                    {{ getRoleLabel(user.role) }}
                                </span>
                            </td>
                            
                            <td class="px-6 py-4">
                                <span v-if="user.email_verified_at" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200/60 px-2.5 py-0.5 rounded-full">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Verified</span>
                                </span>
                                <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200/60 px-2.5 py-0.5 rounded-full">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Pending</span>
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 text-xs text-gray-500">
                                {{ formatDate(user.created_at) }}
                            </td>
                            
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Reset / Recover Password Button -->
                                    <button 
                                        @click="openResetModal(user)" 
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200/70 rounded-lg transition shadow-2xs"
                                        title="Manage Forgotten Password / Credentials"
                                    >
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                        <span>Reset / Recovery</span>
                                    </button>

                                    <!-- Edit User Button -->
                                    <button 
                                        @click="openModal(user)" 
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition shadow-2xs"
                                    >
                                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Stacked Card View -->
            <div class="sm:hidden divide-y divide-gray-100">
                <div 
                    v-for="user in paginatedUsers" 
                    :key="'mob-' + user.id"
                    class="p-4 flex flex-col gap-3 hover:bg-gray-50/60 transition"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 shrink-0 rounded-xl overflow-hidden bg-gradient-to-tr from-gray-800 to-gray-600 text-white font-semibold flex items-center justify-center text-xs shadow-sm border border-gray-200">
                                <img 
                                    v-if="user.profile_photo_url" 
                                    :src="user.profile_photo_url" 
                                    :alt="user.name" 
                                    class="w-full h-full object-cover" 
                                />
                                <span v-else>{{ getInitials(user.name) }}</span>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 text-sm">{{ user.name || 'Unnamed User' }}</div>
                                <div class="text-xs text-gray-500">{{ user.email }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button 
                                @click="openResetModal(user)" 
                                class="p-1.5 text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200/70 rounded-lg transition"
                                title="Reset Forgotten Account"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </button>
                            <button 
                                @click="openModal(user)" 
                                class="p-1.5 text-gray-500 hover:text-gray-900 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition"
                                title="Edit User"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1 border-t border-gray-100 text-xs text-gray-500">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full border" :class="getRoleBadge(user.role)">
                                <span class="w-1.5 h-1.5 rounded-full" :class="getRoleDot(user.role)"></span>
                                {{ getRoleLabel(user.role) }}
                            </span>
                            <span v-if="user.email_verified_at" class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                Verified
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-[11px] font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                                Pending
                            </span>
                        </div>
                        <span class="text-[11px] text-gray-400">{{ formatDate(user.created_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredUsers.length === 0" class="py-16 text-center px-4">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 mb-3.5">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-900">No users found</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">No accounts match your current search criteria or active filters.</p>
                <div class="mt-4">
                    <button 
                        @click="clearFilters" 
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200/80 rounded-xl transition"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Pagination Bar -->
            <div class="px-4 sm:px-6 py-3.5 border-t border-gray-200/80 bg-gray-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-xs text-gray-500 text-center sm:text-left">
                    Showing <span class="font-semibold text-gray-800">{{ filteredUsers.length ? ((page - 1) * pageSize) + 1 : 0 }}</span> to 
                    <span class="font-semibold text-gray-800">{{ Math.min(page * pageSize, filteredUsers.length) }}</span> of 
                    <span class="font-semibold text-gray-800">{{ filteredUsers.length }}</span> users
                </div>

                <div class="flex items-center justify-center gap-1.5">
                    <button 
                        @click="page = Math.max(1, page - 1)" 
                        :disabled="page <= 1" 
                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-2xs"
                    >
                        Previous
                    </button>
                    
                    <div class="flex items-center gap-1 px-1">
                        <span class="px-2.5 py-1 text-xs font-semibold text-red-600 bg-red-50 border border-red-200/70 rounded-lg">
                            {{ page }}
                        </span>
                        <span class="text-xs text-gray-400">/ {{ totalPages }}</span>
                    </div>

                    <button 
                        @click="page = Math.min(totalPages, page + 1)" 
                        :disabled="page >= totalPages" 
                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-2xs"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Add / Edit User Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 backdrop-blur-xs p-4 animate-fade-in">
            <div class="bg-white shadow-xl border border-gray-200/80 w-full max-w-md overflow-hidden">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-50 text-red-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">
                                {{ isEditing ? 'Edit User Profile' : 'Add New User' }}
                            </h3>
                            <p class="text-xs text-gray-400">Configure account details and access level</p>
                        </div>
                    </div>
                    <button 
                        @click="closeModal" 
                        class="text-gray-400 hover:text-gray-600 transition p-1.5 hover:bg-gray-100 rounded-xl"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <!-- Modal Form -->
                <form @submit.prevent="saveUser" class="p-6 space-y-4">
                    <!-- User Profile Snapshot (When Editing) -->
                    <div v-if="isEditing && editingUser" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-200/80 mb-2">
                        <div class="w-11 h-11 shrink-0 rounded-xl overflow-hidden bg-gradient-to-tr from-gray-800 to-gray-700 text-white font-bold flex items-center justify-center text-sm shadow-sm border border-gray-200">
                            <img 
                                v-if="editingUser.profile_photo_url" 
                                :src="editingUser.profile_photo_url" 
                                :alt="editingUser.name" 
                                class="w-full h-full object-cover" 
                            />
                            <span v-else>{{ getInitials(editingUser.name) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ editingUser.name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ editingUser.email }}</div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full border" :class="getRoleBadge(editingUser.role)">
                            {{ getRoleLabel(editingUser.role) }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            v-model="form.name" 
                            required 
                            type="text" 
                            placeholder="e.g. Maria Santos" 
                            class="w-full px-3.5 py-2.5 text-sm bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition" 
                        />
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input 
                            v-model="form.email" 
                            required 
                            type="email" 
                            placeholder="e.g. maria.santos@meo.gov.ph" 
                            class="w-full px-3.5 py-2.5 text-sm bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition" 
                        />
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                            Access Role
                        </label>
                        <select 
                            v-model="form.role" 
                            class="w-full px-3.5 py-2.5 text-sm bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition cursor-pointer"
                        >
                            <option value="staff">Staff (View Only & Scanner)</option>
                            <option value="admin">Admin (Project & Document Management)</option>
                            <option value="superadmin">Super Admin (Executive Full Access)</option>
                        </select>
                    </div>

                    <div v-if="!isEditing">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                            Initial Password (Optional)
                        </label>
                        <input 
                            v-model="form.password" 
                            type="password" 
                            placeholder="Leave blank to auto-generate a secure password" 
                            class="w-full px-3.5 py-2.5 text-sm bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition" 
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input 
                                type="checkbox" 
                                v-model="form.email_verified"
                                class="rounded border-gray-300 text-red-600 focus:ring-red-600/20"
                            />
                            <span class="text-xs text-gray-700 font-medium">Mark email as verified</span>
                        </label>

                        <button 
                            v-if="isEditing" 
                            type="button" 
                            @click="handleDeleteUser(form)"
                            :disabled="isDeleting"
                            class="text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline"
                        >
                            Delete User
                        </button>
                    </div>

                    <div class="flex gap-2.5 pt-4">
                        <button 
                            type="button" 
                            @click="closeModal" 
                            class="flex-1 px-4 py-2.5 text-xs sm:text-sm font-medium border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="isSubmitting"
                            class="flex-1 px-4 py-2.5 text-xs sm:text-sm font-semibold bg-red-600 hover:bg-red-700 active:bg-red-800 text-white rounded-xl transition shadow-sm disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <svg v-if="isSubmitting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isEditing ? 'Save Changes' : 'Create User' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset Password & Forgotten Account Recovery Modal -->
        <div v-if="isResetModalOpen && selectedUser" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs p-4 animate-fade-in">
            <div class="bg-white shadow-2xl border border-gray-200/80 w-full max-w-lg overflow-hidden">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-amber-50/60">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-800 font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Manage Forgotten Account</h3>
                            <p class="text-xs text-gray-500">Reset credentials or dispatch recovery email for staff/admin</p>
                        </div>
                    </div>
                    <button 
                        @click="closeResetModal" 
                        class="text-gray-400 hover:text-gray-600 transition p-1.5 hover:bg-gray-100 rounded-xl"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <!-- User Snapshot -->
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-200/80">
                        <div class="w-10 h-10 shrink-0 rounded-xl overflow-hidden bg-gray-800 text-white font-bold flex items-center justify-center text-xs border border-gray-200">
                            <img 
                                v-if="selectedUser.profile_photo_url" 
                                :src="selectedUser.profile_photo_url" 
                                :alt="selectedUser.name" 
                                class="w-full h-full object-cover" 
                            />
                            <span v-else>{{ getInitials(selectedUser.name) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ selectedUser.name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ selectedUser.email }}</div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full border" :class="getRoleBadge(selectedUser.role)">
                            {{ getRoleLabel(selectedUser.role) }}
                        </span>
                    </div>

                    <!-- Temporary Password Result Banner (If generated) -->
                    <div v-if="resetForm.temporaryPasswordResult" class="p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider">New Password Generated</span>
                            <span v-if="copiedPassword" class="text-xs font-semibold text-emerald-700">Copied to clipboard!</span>
                        </div>
                        <div class="flex items-center justify-between bg-white border border-emerald-200 px-3.5 py-2.5 rounded-lg">
                            <code class="text-sm font-mono font-bold text-emerald-900 select-all">{{ resetForm.temporaryPasswordResult }}</code>
                            <button 
                                type="button" 
                                @click="copyToClipboard(resetForm.temporaryPasswordResult)" 
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100/70 hover:bg-emerald-200 rounded-md transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                                <span>Copy</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-emerald-700 mt-2">
                            Provide this temporary password to the user. They will be able to log in and change it in their profile settings.
                        </p>
                    </div>

                    <!-- Method 1: Instant Password Override -->
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-700">Option 1: Set / Generate Password Directly</span>
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-600">
                                <input 
                                    type="checkbox" 
                                    v-model="resetForm.autoGenerate" 
                                    class="rounded border-gray-300 text-red-600 focus:ring-red-600/20" 
                                />
                                <span>Auto-generate password</span>
                            </label>
                        </div>

                        <div v-if="!resetForm.autoGenerate">
                            <input 
                                v-model="resetForm.password" 
                                type="text" 
                                placeholder="Enter custom new password (min. 8 characters)" 
                                class="w-full px-3.5 py-2 text-sm bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-600/20 focus:border-red-600 transition" 
                            />
                        </div>

                        <button 
                            type="button" 
                            @click="handleDirectResetPassword" 
                            :disabled="isSubmitting || (!resetForm.autoGenerate && (!resetForm.password || resetForm.password.length < 8))"
                            class="w-full inline-flex items-center justify-center gap-2 py-2 px-3 text-xs sm:text-sm font-semibold bg-gray-900 hover:bg-black text-white rounded-xl transition shadow-2xs disabled:opacity-50"
                        >
                            <svg v-if="isSubmitting" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ resetForm.autoGenerate ? 'Generate & Apply New Password' : 'Apply Custom Password' }}</span>
                        </button>
                    </div>

                    <!-- Method 2: Send Email Reset Link -->
                    <div class="border border-gray-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-700">Option 2: Dispatch Reset Email</div>
                            <p class="text-xs text-gray-500">Send an official password reset email link to {{ selectedUser.email }}</p>
                        </div>
                        <button 
                            type="button" 
                            @click="handleSendResetEmail" 
                            :disabled="isSendingLink"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-xl transition shrink-0 disabled:opacity-50"
                        >
                            <svg v-if="isSendingLink" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Send Reset Link</span>
                        </button>
                    </div>

                    <!-- Danger Zone / Delete -->
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                        <button 
                            type="button" 
                            @click="handleDeleteUser(selectedUser)" 
                            :disabled="isDeleting"
                            class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Delete Account</span>
                        </button>
                        
                        <button 
                            type="button" 
                            @click="closeResetModal" 
                            class="px-4 py-2 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>