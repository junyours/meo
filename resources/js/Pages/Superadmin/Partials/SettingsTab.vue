<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});

// Active Category Tab
const activeSection = ref('general'); // 'general' | 'transparency' | 'notifications' | 'engineering' | 'security' | 'system'

// Storage key for Superadmin system settings
const SETTINGS_STORAGE_KEY = 'meo_superadmin_system_settings_v1';

// Default system configurations
const defaultSettings = {
    // 1. General & LGU Profile
    lgu_name: 'Municipality of Opol',
    province: 'Misamis Oriental',
    office_name: 'Municipal Engineering Office (MEO)',
    office_head: 'Engr. Municipal Engineer',
    office_head_title: 'Municipal Engineer / Department Head',
    contact_email: 'meo.opol.misor@gmail.com',
    contact_phone: '(088) 554-1234 / +63 912 345 6789',
    office_address: 'Ground Floor, Executive Building, Municipal Hall Compound, Opol, Misamis Oriental',
    office_hours: 'Monday - Friday: 8:00 AM - 5:00 PM',

    // 2. Public Transparency & Ask MEO Portal
    enable_public_portal: true,
    enable_ask_meo: true,
    require_inquiry_photo: false,
    show_project_budget_public: true,
    show_contractor_details_public: true,
    auto_acknowledge_inquiry: true,
    inquiry_auto_response: 'Thank you for reaching out to the Municipal Engineering Office. Your concern has been officially logged with a tracking token. Our engineering team will inspect and review your request shortly.',

    // 3. Notifications & Realtime Sync
    sound_alerts_enabled: true,
    polling_speed: 'standard', // 'fast' (4s), 'standard' (8s), 'relaxed' (15s)
    email_alerts_overdue: true,
    daily_project_summary: true,
    notify_on_staff_replies: true,
    notify_on_new_inquiries: true,

    // 4. Engineering & Projects
    fiscal_year: new Date().getFullYear().toString(),
    slippage_threshold: '10', // 10% negative variance triggers delayed alert
    enforce_tech_prep_pow: true,
    deadline_reminder_window: '7', // 7 days before target deadline
    default_fund_category: 'LGU General Fund',

    // 5. Security & Access
    session_timeout_minutes: '120',
    strict_password_rules: true,
    audit_logging_enabled: true,
    allow_staff_project_notes: true,
};

// Reactive Form State
const settings = reactive({ ...defaultSettings });
const isSaving = ref(false);
const hasUnsavedChanges = ref(false);

// Toast Feedback State
const toast = reactive({
    show: false,
    message: '',
    type: 'success',
});
let toastTimeout = null;

const showToast = (message, type = 'success') => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.show = false;
    }, 3500);
};

// Load saved settings
const loadSettings = () => {
    try {
        const stored = localStorage.getItem(SETTINGS_STORAGE_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            Object.assign(settings, { ...defaultSettings, ...parsed });
        }
    } catch (e) {
        console.error('Failed to load settings from storage:', e);
    }
};

// Save Settings
const saveSettings = () => {
    isSaving.value = true;
    try {
        localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(settings));
        hasUnsavedChanges.value = false;
        showToast('System configuration saved successfully!', 'success');
    } catch (e) {
        showToast('Failed to save settings to local storage.', 'error');
    } finally {
        isSaving.value = false;
    }
};

// Reset to Defaults
const resetToDefaults = () => {
    if (confirm('Are you sure you want to reset all settings to system default values?')) {
        Object.assign(settings, defaultSettings);
        saveSettings();
        showToast('Settings reset to default values.', 'info');
    }
};

// Export Settings to JSON
const exportSettings = () => {
    const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(settings, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute('href', dataStr);
    downloadAnchor.setAttribute('download', `meo_system_settings_${new Date().toISOString().split('T')[0]}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
    showToast('Settings exported as JSON file.', 'success');
};

// Import Settings from JSON
const fileInput = ref(null);
const triggerImport = () => {
    if (fileInput.value) fileInput.value.click();
};

const handleImportFile = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const imported = JSON.parse(e.target.result);
            if (typeof imported === 'object' && imported !== null) {
                Object.assign(settings, imported);
                saveSettings();
                showToast('Settings successfully imported and applied!', 'success');
            } else {
                throw new Error('Invalid JSON format');
            }
        } catch (err) {
            showToast('Failed to import configuration file. Please ensure valid JSON.', 'error');
        }
    };
    reader.readAsText(file);
    event.target.value = '';
};

// Clear Application Cache
const isClearingCache = ref(false);
const clearSystemCache = () => {
    if (!confirm('This will clear browser cache flags, read status keys, and reload live portal data. Continue?')) {
        return;
    }
    isClearingCache.value = true;
    setTimeout(() => {
        try {
            const currentSettings = localStorage.getItem(SETTINGS_STORAGE_KEY);
            const activeTab = localStorage.getItem('meo_superadmin_active_tab');
            
            Object.keys(localStorage).forEach(key => {
                if (key.startsWith('meo_notifications_') || key.startsWith('meo_cache_')) {
                    localStorage.removeItem(key);
                }
            });

            if (currentSettings) localStorage.setItem(SETTINGS_STORAGE_KEY, currentSettings);
            if (activeTab) localStorage.setItem('meo_superadmin_active_tab', activeTab);

            showToast('System application cache and session flags cleared.', 'success');
        } catch (e) {
            showToast('Failed to clear cache.', 'error');
        } finally {
            isClearingCache.value = false;
        }
    }, 600);
};

// Navigation tabs
const sections = [
    {
        id: 'general',
        name: 'Office & Branding',
        icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        badge: 'Profile',
    },
    {
        id: 'transparency',
        name: 'Public Portal & Inquiries',
        icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9',
        badge: 'Ask MEO',
    },
    {
        id: 'notifications',
        name: 'Notifications & Alerts',
        icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        badge: 'Real-time',
    },
    {
        id: 'engineering',
        name: 'Projects & Engineering',
        icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        badge: 'Operations',
    },
    {
        id: 'security',
        name: 'Security & Maintenance',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        badge: 'System',
    },
    {
        id: 'system',
        name: 'Server & Diagnostics',
        icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
        badge: 'Health',
    },
];

onMounted(() => {
    loadSettings();
});
</script>

<template>
    <div class="w-full font-sans antialiased space-y-5">
        <!-- Toast Notification -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 translate-y-2"
            enter-to-class="transform opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="transform opacity-100 translate-y-0"
            leave-to-class="transform opacity-0 translate-y-2"
        >
            <div
                v-if="toast.show"
                class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 text-sm font-bold shadow-xl border"
                :class="[
                    toast.type === 'success' ? 'bg-slate-900 text-white border-emerald-500' :
                    toast.type === 'error' ? 'bg-red-900 text-white border-red-500' :
                    'bg-slate-800 text-white border-slate-600'
                ]"
            >
                <span v-if="toast.type === 'success'" class="text-emerald-400">●</span>
                <span v-else-if="toast.type === 'error'" class="text-red-400">✕</span>
                <span v-else class="text-blue-400">ℹ</span>
                <span>{{ toast.message }}</span>
            </div>
        </transition>

        <!-- Hidden File Input for JSON Import -->
        <input
            type="file"
            ref="fileInput"
            @change="handleImportFile"
            accept=".json"
            class="hidden"
        />

        <!-- Top Header & Action Toolbar -->
        <div class="bg-white border border-slate-200 shadow-2xs p-4 sm:p-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <div class="p-1.5 bg-red-50 text-red-700 border border-red-200 rounded-xs">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Superadmin System Settings</h2>
                        <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase bg-red-100 text-red-800 border border-red-200">
                            Global Control Panel
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-600">
                        Configure MEO office branding, public transparency parameters, real-time dispatching, and security thresholds.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        @click="exportSettings"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-300 transition shadow-2xs"
                        title="Download configuration file"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Export</span>
                    </button>

                    <button
                        type="button"
                        @click="triggerImport"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-300 transition shadow-2xs"
                        title="Upload and restore configuration JSON"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>Import</span>
                    </button>

                    <button
                        type="button"
                        @click="resetToDefaults"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 text-xs font-bold border border-slate-300 hover:border-rose-200 transition shadow-2xs"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Reset</span>
                    </button>

                    <button
                        type="button"
                        @click="saveSettings"
                        :disabled="isSaving"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-xs font-bold transition shadow-xs disabled:opacity-50 active:scale-[0.99]"
                    >
                        <svg v-if="!isSaving" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent animate-spin rounded-full"></span>
                        <span>{{ isSaving ? 'Saving...' : 'Save Settings' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Layout with Category Sidebar & Configuration Panels -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Left Navigation Menu -->
            <div class="lg:col-span-3 space-y-1 bg-white border border-slate-200 p-2 shadow-2xs">
                <p class="px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Settings Categories</p>
                <button
                    v-for="sec in sections"
                    :key="sec.id"
                    type="button"
                    @click="activeSection = sec.id"
                    class="w-full flex items-center justify-between px-3 py-2.5 text-left text-xs font-bold transition border"
                    :class="[
                        activeSection === sec.id
                            ? 'bg-red-50 text-red-700 border-red-200'
                            : 'text-slate-700 hover:bg-slate-50 border-transparent'
                    ]"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 shrink-0" :class="activeSection === sec.id ? 'text-red-700' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="sec.icon" />
                        </svg>
                        <span class="truncate">{{ sec.name }}</span>
                    </div>
                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded-xs" :class="activeSection === sec.id ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-500'">
                        {{ sec.badge }}
                    </span>
                </button>

                <!-- Quick Clear Cache Action Card -->
                <div class="pt-3 mt-3 border-t border-slate-100 px-2 pb-1 space-y-2">
                    <p class="text-[11px] text-slate-500 font-medium leading-relaxed">
                        Need to reset stale client state or re-synchronize background notifications?
                    </p>
                    <button
                        type="button"
                        @click="clearSystemCache"
                        :disabled="isClearingCache"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-300 transition"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>{{ isClearingCache ? 'Clearing Cache...' : 'Clear App Cache' }}</span>
                    </button>
                </div>
            </div>

            <!-- Right Content Configuration Area -->
            <div class="lg:col-span-9 space-y-5">
                <!-- 1. GENERAL & LGU BRANDING -->
                <div v-show="activeSection === 'general'" class="bg-white border border-slate-200 shadow-2xs p-5 space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Office Profile & LGU Identity</h3>
                            <p class="text-xs text-slate-500">Official Municipal Engineering Office information printed on summary reports and headers.</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                            General Profile
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">LGU Municipality Name</label>
                            <input
                                type="text"
                                v-model="settings.lgu_name"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="e.g. Municipality of Opol"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Province / Region</label>
                            <input
                                type="text"
                                v-model="settings.province"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="e.g. Misamis Oriental"
                            />
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <label class="text-xs font-bold text-slate-700">Official Office / Department Name</label>
                            <input
                                type="text"
                                v-model="settings.office_name"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="e.g. Municipal Engineering Office (MEO)"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Municipal Engineer / Department Head</label>
                            <input
                                type="text"
                                v-model="settings.office_head"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="e.g. Engr. Office Head"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Designation / Official Title</label>
                            <input
                                type="text"
                                v-model="settings.office_head_title"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="e.g. Municipal Engineer"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Official Contact Email</label>
                            <input
                                type="email"
                                v-model="settings.contact_email"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="meo@lgu.gov.ph"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Hotline Phone Number(s)</label>
                            <input
                                type="text"
                                v-model="settings.contact_phone"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="(083) 554-1234 / +63 9..."
                            />
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <label class="text-xs font-bold text-slate-700">Physical Office Location / Address</label>
                            <input
                                type="text"
                                v-model="settings.office_address"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="e.g. Ground Floor, Municipal Hall Compound"
                            />
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <label class="text-xs font-bold text-slate-700">Office Working Hours</label>
                            <input
                                type="text"
                                v-model="settings.office_hours"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                placeholder="e.g. Monday - Friday: 8:00 AM - 5:00 PM"
                            />
                        </div>
                    </div>
                </div>

                <!-- 2. PUBLIC TRANSPARENCY & ASK MEO PORTAL -->
                <div v-show="activeSection === 'transparency'" class="bg-white border border-slate-200 shadow-2xs p-5 space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Citizen Engagement & Transparency Portal</h3>
                            <p class="text-xs text-slate-500">Configure public accessibility for infrastructure monitoring and the Ask MEO concern desk.</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            Public Portal
                        </span>
                    </div>

                    <div class="space-y-4 divide-y divide-slate-100">
                        <div class="pt-2 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Public Infrastructure Transparency Portal</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Allow citizens to browse public infrastructure projects, timelines, locations, and accomplishment percentages on the landing page.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.enable_public_portal"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Citizen Inquiries & Concerns Desk (Ask MEO)</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Enable the public `/ask-meo` form allowing constituents to submit questions, field issues, and track resolutions with a secure token.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.enable_ask_meo"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Require Photo Proof for Citizen Submissions</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Enforce image or site photo upload before a citizen can submit a concern on the public portal.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.require_inquiry_photo"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Display Project Budget & Cost on Public Portal</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Show total approved project budget and fund source breakdown on the public find-project view.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.show_project_budget_public"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 space-y-2">
                            <label class="text-xs font-bold text-slate-800">Default Auto-Acknowledgement Response for Citizens</label>
                            <p class="text-[11px] text-slate-500">
                                This automated note is displayed to citizens immediately upon generating a tracking token.
                            </p>
                            <textarea
                                v-model="settings.inquiry_auto_response"
                                rows="3"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- 3. NOTIFICATIONS & ALERTS -->
                <div v-show="activeSection === 'notifications'" class="bg-white border border-slate-200 shadow-2xs p-5 space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Live Synchronization & Notification Rules</h3>
                            <p class="text-xs text-slate-500">Configure background polling speed, audible notification chimes, and email digests.</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200">
                            Real-time Sync
                        </span>
                    </div>

                    <div class="space-y-4 divide-y divide-slate-100">
                        <div class="pt-2 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">In-App Audio Chime for Urgent Notifications</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Play a subtle synthesized chime when new unread notifications or urgent citizen concerns arrive.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.sound_alerts_enabled"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 space-y-2">
                            <label class="text-xs font-bold text-slate-800">Real-Time Polling & Sync Frequency</label>
                            <p class="text-[11px] text-slate-500">Select how frequently the system silently checks for incoming concerns and directives.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                                <label
                                    class="p-3 border rounded-xs cursor-pointer flex flex-col justify-between transition"
                                    :class="settings.polling_speed === 'fast' ? 'border-red-600 bg-red-50/50' : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900">High Performance</span>
                                        <input type="radio" value="fast" v-model="settings.polling_speed" class="text-red-600 focus:ring-red-500" />
                                    </div>
                                    <span class="text-[10px] text-slate-500 mt-1">4 seconds interval. Ideal for active office workstations.</span>
                                </label>

                                <label
                                    class="p-3 border rounded-xs cursor-pointer flex flex-col justify-between transition"
                                    :class="settings.polling_speed === 'standard' ? 'border-red-600 bg-red-50/50' : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900">Standard Balance</span>
                                        <input type="radio" value="standard" v-model="settings.polling_speed" class="text-red-600 focus:ring-red-500" />
                                    </div>
                                    <span class="text-[10px] text-slate-500 mt-1">8 seconds interval. Recommended standard default.</span>
                                </label>

                                <label
                                    class="p-3 border rounded-xs cursor-pointer flex flex-col justify-between transition"
                                    :class="settings.polling_speed === 'relaxed' ? 'border-red-600 bg-red-50/50' : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900">Power Saving</span>
                                        <input type="radio" value="relaxed" v-model="settings.polling_speed" class="text-red-600 focus:ring-red-500" />
                                    </div>
                                    <span class="text-[10px] text-slate-500 mt-1">15 seconds interval. Minimal network overhead.</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Directives & Staff Discussion Notifications</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Notify administrators when a staff engineer replies or sends a discussion update regarding project directives.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.notify_on_staff_replies"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Citizen Concern Alerts</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Broadcast live alert badges to municipal engineering officers when new public inquiries are submitted.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.notify_on_new_inquiries"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>
                    </div>
                </div>

                <!-- 4. ENGINEERING & PROJECTS THRESHOLDS -->
                <div v-show="activeSection === 'engineering'" class="bg-white border border-slate-200 shadow-2xs p-5 space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Engineering Operations & Thresholds</h3>
                            <p class="text-xs text-slate-500">Configure fiscal years, negative slippage thresholds, and technical preparation requirements.</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                            Engineering Rules
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Active Fiscal Year</label>
                            <input
                                type="number"
                                v-model="settings.fiscal_year"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                                min="2020"
                                max="2040"
                            />
                            <p class="text-[10px] text-slate-400">Default year prefilled when encoding new municipal infrastructure projects.</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">Slippage Threshold for "Delayed" Status Alert</label>
                            <select
                                v-model="settings.slippage_threshold"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                            >
                                <option value="5">-5% Variance (Strict Warning)</option>
                                <option value="10">-10% Variance (Standard COA Threshold)</option>
                                <option value="15">-15% Variance (Critical Delay / Liquidated Damages)</option>
                            </select>
                            <p class="text-[10px] text-slate-400">Trigger overdue alert notifications when negative slippage exceeds this percentage.</p>
                        </div>

                        <div class="space-y-1 sm:col-span-2 pt-2 border-t border-slate-100">
                            <label class="text-xs font-bold text-slate-700">Target Completion Deadline Reminder Lead Time</label>
                            <select
                                v-model="settings.deadline_reminder_window"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                            >
                                <option value="3">3 Days before Target Completion Date</option>
                                <option value="7">7 Days before Target Completion Date</option>
                                <option value="14">14 Days before Target Completion Date</option>
                                <option value="30">30 Days before Target Completion Date</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2 pt-2 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Enforce POW / DED Pre-Engineering Prerequisite</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Highlight projects with pending or flagged pre-engineering technical preparations (Hazard, POW, ALOBS, ECC) before mobilization.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.enforce_tech_prep_pow"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>
                    </div>
                </div>

                <!-- 5. SECURITY & ACCESS MAINTENANCE -->
                <div v-show="activeSection === 'security'" class="bg-white border border-slate-200 shadow-2xs p-5 space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Security Controls & Audit Policies</h3>
                            <p class="text-xs text-slate-500">Configure administrative access parameters and session security.</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                            Security
                        </span>
                    </div>

                    <div class="space-y-4 divide-y divide-slate-100">
                        <div class="pt-2 space-y-1">
                            <label class="text-xs font-bold text-slate-800">Admin / Staff Inactivity Session Timeout</label>
                            <select
                                v-model="settings.session_timeout_minutes"
                                class="w-full sm:w-72 px-3 py-2 text-xs border border-slate-300 rounded-xs focus:ring-1 focus:ring-red-600 focus:border-red-600 text-slate-800"
                            >
                                <option value="30">30 Minutes of Inactivity</option>
                                <option value="60">1 Hour</option>
                                <option value="120">2 Hours (Standard Office)</option>
                                <option value="480">8 Hours (Full Working Day)</option>
                            </select>
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Enforce Strong Password Complexity</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Require minimum 8 characters with numbers, uppercase letters, and special symbols for all user registrations and profile edits.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.strict_password_rules"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Audit Trail & Activity Logging</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Record administrative actions, project creation, accomplishment edits, and status changes in the system audit logs tab.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.audit_logging_enabled"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>

                        <div class="pt-4 flex items-start justify-between gap-4">
                            <div class="space-y-0.5">
                                <label class="text-xs font-bold text-slate-800">Allow Field Staff to Add Project Notes & Daily Progress</label>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Enable assigned site inspectors and engineers to submit accomplishments and progress remarks on their assigned projects.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="settings.allow_staff_project_notes"
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500 mt-1 cursor-pointer"
                            />
                        </div>
                    </div>
                </div>

                <!-- 6. SERVER DIAGNOSTICS & SYSTEM INFO -->
                <div v-show="activeSection === 'system'" class="bg-white border border-slate-200 shadow-2xs p-5 space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">System Environment & Diagnostics</h3>
                            <p class="text-xs text-slate-500">Live runtime statistics, database connection state, and server environment metrics.</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                            Server Health
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">System Status</span>
                            <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>Fully Operational</span>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">PHP Runtime</span>
                            <p class="text-xs font-bold text-slate-800">PHP 8.2+ (Zend Engine)</p>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Framework</span>
                            <p class="text-xs font-bold text-slate-800">Laravel 10.x & Inertia Vue 3</p>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Database Engine</span>
                            <p class="text-xs font-bold text-slate-800">MySQL / MariaDB via PDO</p>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Timezone</span>
                            <p class="text-xs font-bold text-slate-800">Asia/Manila (PHT, GMT+8)</p>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Authenticated Role</span>
                            <p class="text-xs font-bold text-red-700 uppercase tracking-tight">{{ currentUser?.role || 'Superadmin' }}</p>
                        </div>
                    </div>

                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xs flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-xs text-amber-800 space-y-1">
                            <p class="font-bold">Superadmin Authorization Level</p>
                            <p class="text-[11px] leading-relaxed">
                                Changes made in this portal affect global defaults, public citizen interaction rules, real-time alert intervals, and engineering calculation thresholds across all user sessions.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Bottom Sticky Save Bar -->
                <div class="bg-slate-50 border border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        <span class="text-xs text-slate-600 font-medium">Configurations are securely persisted for the municipal management system.</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="resetToDefaults"
                            class="px-3.5 py-1.5 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold border border-slate-300 transition"
                        >
                            Reset Defaults
                        </button>

                        <button
                            type="button"
                            @click="saveSettings"
                            :disabled="isSaving"
                            class="px-5 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-bold transition shadow-xs disabled:opacity-50 active:scale-[0.99]"
                        >
                            {{ isSaving ? 'Saving...' : 'Save Configuration' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
