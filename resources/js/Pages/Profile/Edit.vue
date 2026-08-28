<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, router, Head } from '@inertiajs/vue3';
import axios from 'axios';
import AdminSidebar from '../Admin/Partials/Sidebar.vue';
import StaffSidebar from '../Staff/Partials/Sidebar.vue';
import SuperadminSidebar from '../Superadmin/Partials/Sidebar.vue';
import NotificationDropdown from '../Admin/Partials/NotificationDropdown.vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    positions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const role = computed(() => page.props.auth?.user?.role ?? 'staff');

const photoPreview = ref(null);
const uploadedPhotoUrl = ref(null);
const photoInput = ref(null);
const coverPhotoInput = ref(null);
const isUploadingPhoto = ref(false);
const showDeletePhotoConfirm = ref(false);
const showDeleteAccountModal = ref(false);
const showCoverModal = ref(false);
const showBioModal = ref(false);
const sidebarCollapsed = ref(localStorage.getItem('meo_sidebar_collapsed') === 'true');
const sidebarActive = ref('profile');

const currentPhotoUrl = computed(() => {
    return photoPreview.value || uploadedPhotoUrl.value || user.value?.profile_photo_url || null;
});

// ==========================================
// Cover Banners: Palettes, Photos & Custom
// ==========================================
const coverPalettes = [
    { id: 'meo-crimson', name: 'MEO Crimson Mesh', class: 'from-red-950 via-red-800 to-rose-900', type: 'gradient', accent: 'bg-red-600' },
    { id: 'slate-midnight', name: 'Slate Midnight', class: 'from-slate-950 via-slate-900 to-indigo-950', type: 'gradient', accent: 'bg-indigo-600' },
    { id: 'ocean-blue', name: 'Ocean Depth', class: 'from-blue-950 via-cyan-900 to-slate-900', type: 'gradient', accent: 'bg-cyan-600' },
    { id: 'emerald-forest', name: 'Emerald Peak', class: 'from-emerald-950 via-teal-900 to-slate-900', type: 'gradient', accent: 'bg-emerald-600' },
    { id: 'sunset-amber', name: 'Sunset Glow', class: 'from-amber-950 via-red-900 to-stone-900', type: 'gradient', accent: 'bg-amber-600' },
    { id: 'royal-purple', name: 'Royal Amethyst', class: 'from-purple-950 via-fuchsia-900 to-slate-900', type: 'gradient', accent: 'bg-purple-600' },
];

const coverPhotoPresets = [
    {
        id: 'photo-blueprint',
        name: 'Engineering Blueprint',
        url: 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1600&q=80',
        thumb: 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=400&q=70',
        type: 'photo',
        desc: 'Technical architectural plans and schematics'
    },
    {
        id: 'photo-bridge',
        name: 'Modern Bridge & Highway',
        url: 'https://images.unsplash.com/photo-1545558014-8692077e9b5c?auto=format&fit=crop&w=1600&q=80',
        thumb: 'https://images.unsplash.com/photo-1545558014-8692077e9b5c?auto=format&fit=crop&w=400&q=70',
        type: 'photo',
        desc: 'Civil transportation engineering infrastructure'
    },
    {
        id: 'photo-skyline',
        name: 'Municipal City Architecture',
        url: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1600&q=80',
        thumb: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=400&q=70',
        type: 'photo',
        desc: 'Urban skyline and commercial development'
    },
    {
        id: 'photo-construction',
        name: 'Civil Works & Construction',
        url: 'https://images.unsplash.com/photo-1541888946425-d0fbb18086f6?auto=format&fit=crop&w=1600&q=80',
        thumb: 'https://images.unsplash.com/photo-1541888946425-d0fbb18086f6?auto=format&fit=crop&w=400&q=70',
        type: 'photo',
        desc: 'Active construction cranes and high-rise framework'
    },
    {
        id: 'photo-highway',
        name: 'Coastal Road Development',
        url: 'https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=1600&q=80',
        thumb: 'https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=400&q=70',
        type: 'photo',
        desc: 'Public highways, asphalt paving and transit routes'
    },
    {
        id: 'photo-geometry',
        name: 'Structural Geometry & Glass',
        url: 'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=1600&q=80',
        thumb: 'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=400&q=70',
        type: 'photo',
        desc: 'Modern glass facades and geometric facades'
    },
];

// Cover state with user-scoped localStorage
const coverModeKey = computed(() => `meo_cover_mode_${user.value?.id || 'guest'}`);
const coverIdKey = computed(() => `meo_cover_id_${user.value?.id || 'guest'}`);
const coverCustomKey = computed(() => `meo_cover_custom_${user.value?.id || 'guest'}`);

const coverMode = ref('palette');
const selectedCoverId = ref('meo-crimson');
const customCoverDataUrl = ref(null);

const initCoverState = () => {
    coverMode.value = localStorage.getItem(coverModeKey.value) || localStorage.getItem('meo_cover_mode') || 'palette';
    selectedCoverId.value = localStorage.getItem(coverIdKey.value) || localStorage.getItem('meo_cover_id') || 'meo-crimson';
    customCoverDataUrl.value = localStorage.getItem(coverCustomKey.value) || localStorage.getItem('meo_cover_custom_data') || null;
};

const currentCoverStyle = computed(() => {
    if (coverMode.value === 'custom-photo' && customCoverDataUrl.value) {
        return {
            backgroundImage: `url(${customCoverDataUrl.value})`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
        };
    }
    if (coverMode.value === 'preset-photo') {
        const photo = coverPhotoPresets.find(p => p.id === selectedCoverId.value);
        if (photo) {
            return {
                backgroundImage: `url(${photo.url})`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
            };
        }
    }
    return {};
});

const currentCoverClass = computed(() => {
    if (coverMode.value === 'palette') {
        const found = coverPalettes.find(t => t.id === selectedCoverId.value);
        return found ? found.class : 'from-red-950 via-red-800 to-rose-900';
    }
    return 'bg-slate-900';
});

const selectPalette = (id) => {
    coverMode.value = 'palette';
    selectedCoverId.value = id;
    localStorage.setItem(coverModeKey.value, 'palette');
    localStorage.setItem(coverIdKey.value, id);
    showToast('Cover palette updated!', 'success');
};

const selectPhotoPreset = (id) => {
    coverMode.value = 'preset-photo';
    selectedCoverId.value = id;
    localStorage.setItem(coverModeKey.value, 'preset-photo');
    localStorage.setItem(coverIdKey.value, id);
    showToast('Cover photo preset applied!', 'success');
};

const triggerCoverUpload = () => {
    coverPhotoInput.value?.click();
};

const handleCoverPhotoUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(file.type)) {
        showToast('Please select a valid image file (JPEG, PNG, WebP)', 'error');
        return;
    }

    if (file.size > 8 * 1024 * 1024) {
        showToast('Cover image size must be less than 8MB', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        const dataUrl = event.target.result;
        customCoverDataUrl.value = dataUrl;
        coverMode.value = 'custom-photo';
        localStorage.setItem(coverModeKey.value, 'custom-photo');
        localStorage.setItem(coverCustomKey.value, dataUrl);
        showToast('Custom cover photo uploaded successfully!', 'success');
    };
    reader.readAsDataURL(file);

    if (coverPhotoInput.value) {
        coverPhotoInput.value.value = '';
    }
};

const removeCustomCover = () => {
    customCoverDataUrl.value = null;
    localStorage.removeItem(coverCustomKey.value);
    selectPalette('meo-crimson');
    showToast('Custom cover removed. Reverted to default palette.', 'success');
};

// ==========================================
// User Bio, Headline, Skills & Details State
// ==========================================
const bioKey = computed(() => `meo_user_bio_${user.value?.id || 'guest'}`);

const getDefaultBioData = () => {
    const userRole = role.value;
    const userName = user.value?.name || 'Technical Personnel';
    const userEmail = user.value?.email || 'opol.meo@gmail.com';
    
    if (userRole === 'superadmin') {
        return {
            headline: 'Super Administrator • Municipal System Governance & Infrastructure Management',
            bioText: `Authorized Super Administrator overseeing the Municipal Engineering Office system architecture, user security clearances, administrative privileges, and infrastructure analytics for the Municipality of Opol.`,
            officeLocation: 'Municipal Engineering Office, Executive Division, Opol, Misamis Oriental',
            contactPhone: userEmail,
            skills: [
                'System Administration',
                'Infrastructure Governance',
                'User Clearances',
                'Project Auditing',
                'Database Oversight',
                'Security Compliance',
            ],
        };
    }
    if (userRole === 'admin') {
        return {
            headline: 'Municipal Engineer & Administrator • Project Operations & Planning',
            bioText: `Municipal Engineering Office administrator directing project planning, technical evaluations, contractor coordination, milestone tracking, and municipal public works in the Municipality of Opol.`,
            officeLocation: 'Municipal Engineering Office, 2nd Floor, Municipal Hall, Opol, Misamis Oriental',
            contactPhone: userEmail,
            skills: [
                'Project Planning',
                'Technical Review',
                'Staff Oversight',
                'Procurement & Bidding',
                'Civil Works Inspection',
                'Budget Allocation',
            ],
        };
    }
    return {
        headline: 'MEO Technical Personnel • Field Operations & Quality Assurance',
        bioText: `Dedicated Municipal Engineering Office technical personnel focused on infrastructure monitoring, field inspections, quantity surveying, milestone validation, and technical documentation in the Municipality of Opol.`,
        officeLocation: 'Municipal Engineering Office, Operations Unit, Opol, Misamis Oriental',
        contactPhone: userEmail,
        skills: [
            'Field Inspections',
            'Project Monitoring',
            'Technical Reporting',
            'Quantity Surveying',
            'AutoCAD & Estimation',
            'Quality Control',
        ],
    };
};

const userBioData = ref(getDefaultBioData());
const bioEditForm = ref(getDefaultBioData());
const newSkillInput = ref('');
const inlineSkillInput = ref('');
const isAddingInlineSkill = ref(false);

const loadBioData = () => {
    try {
        const defaults = getDefaultBioData();
        const saved = localStorage.getItem(bioKey.value);
        if (saved) {
            const parsed = JSON.parse(saved);
            userBioData.value = {
                ...defaults,
                ...parsed,
                skills: Array.isArray(parsed.skills) && parsed.skills.length > 0 ? parsed.skills : defaults.skills,
            };
        } else {
            userBioData.value = { ...defaults };
        }
    } catch (e) {
        userBioData.value = getDefaultBioData();
    }
};

const openBioModal = () => {
    bioEditForm.value = JSON.parse(JSON.stringify(userBioData.value));
    showBioModal.value = true;
};

const addSkillTag = () => {
    const trimmed = newSkillInput.value.trim();
    if (trimmed && !bioEditForm.value.skills.includes(trimmed)) {
        bioEditForm.value.skills.push(trimmed);
        newSkillInput.value = '';
    }
};

const removeSkillTag = (index) => {
    bioEditForm.value.skills.splice(index, 1);
};

const addInlineSkill = () => {
    const trimmed = inlineSkillInput.value.trim();
    if (trimmed && !userBioData.value.skills.includes(trimmed)) {
        userBioData.value.skills.push(trimmed);
        localStorage.setItem(bioKey.value, JSON.stringify(userBioData.value));
        showToast(`Added "${trimmed}" to specializations!`, 'success');
    }
    inlineSkillInput.value = '';
    isAddingInlineSkill.value = false;
};

const removeSkillDirect = (index) => {
    const removed = userBioData.value.skills[index];
    userBioData.value.skills.splice(index, 1);
    localStorage.setItem(bioKey.value, JSON.stringify(userBioData.value));
    showToast(`Removed skill tag.`, 'success');
};

const resetBioToDefaults = () => {
    const defaults = getDefaultBioData();
    bioEditForm.value = JSON.parse(JSON.stringify(defaults));
    userBioData.value = JSON.parse(JSON.stringify(defaults));
    localStorage.removeItem(bioKey.value);
    showToast('Bio details reset to role default values.', 'success');
};

const saveBioData = () => {
    userBioData.value = JSON.parse(JSON.stringify(bioEditForm.value));
    localStorage.setItem(bioKey.value, JSON.stringify(userBioData.value));
    showBioModal.value = false;
    showToast('Bio and profile details updated successfully!', 'success');
};

onMounted(() => {
    initCoverState();
    loadBioData();
});

// Password visibility toggles
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const profileForm = useForm({
    name: user.value?.name || '',
    email: user.value?.email || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({
    password: '',
});

const toast = ref({ show: false, message: '', type: 'success' });
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => {
        toast.value.show = false;
    }, 3200);
};

// Social Profile Active Tab (Streamlined without timeline)
const activeTab = ref('bio'); // 'bio' | 'edit' | 'security' | 'preferences' | 'danger'
const coverModalTab = ref('photos'); // 'photos' | 'palettes' | 'upload'
const emailCopied = ref(false);

const userHandle = computed(() => {
    if (!user.value?.email) return '@user';
    return '@' + user.value.email.split('@')[0].toLowerCase();
});

const copyEmail = async () => {
    if (!user.value?.email) return;
    try {
        await navigator.clipboard.writeText(user.value.email);
        emailCopied.value = true;
        showToast('Email address copied to clipboard!', 'success');
        setTimeout(() => {
            emailCopied.value = false;
        }, 2000);
    } catch (e) {
        showToast('Failed to copy email.', 'error');
    }
};

// Password Strength Meter
const passwordStrength = computed(() => {
    const pwd = passwordForm.password;
    if (!pwd) return { score: 0, label: 'None', color: 'bg-slate-200', textClass: 'text-slate-400', width: '0%' };
    let score = 0;
    if (pwd.length >= 8) score += 1;
    if (/[A-Z]/.test(pwd)) score += 1;
    if (/[0-9]/.test(pwd)) score += 1;
    if (/[^A-Za-z0-9]/.test(pwd)) score += 1;

    if (score === 1) return { score, label: 'Weak', color: 'bg-rose-500', textClass: 'text-rose-600 font-bold', width: '25%' };
    if (score === 2) return { score, label: 'Fair', color: 'bg-amber-500', textClass: 'text-amber-600 font-bold', width: '50%' };
    if (score === 3) return { score, label: 'Good', color: 'bg-blue-500', textClass: 'text-blue-600 font-bold', width: '75%' };
    return { score, label: 'Strong', color: 'bg-emerald-500', textClass: 'text-emerald-600 font-bold', width: '100%' };
});

const selectNewPhoto = () => {
    photoInput.value?.click();
};

const updatePhotoPreview = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        showToast('Please select a valid image (JPEG, PNG, GIF, WebP)', 'error');
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        showToast('Photo size must be less than 5MB', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);

    uploadPhoto(file);
};

const uploadPhoto = async (file) => {
    isUploadingPhoto.value = true;

    const formData = new FormData();
    formData.append('photo', file);
    formData.append('_method', 'PUT');

    const updateUrl = typeof route === 'function' && route().has('profile.photo.update')
        ? route('profile.photo.update')
        : '/profile/photo';

    try {
        const response = await axios.post(updateUrl, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        if (response.data?.success) {
            uploadedPhotoUrl.value = response.data.profile_photo_url;
            if (page.props.auth?.user) {
                page.props.auth.user.profile_photo_url = response.data.profile_photo_url;
            }
            showToast('Profile photo updated successfully!', 'success');
        }
    } catch (error) {
        console.error('Photo upload error:', error);
        const errMsg = error.response?.data?.message || 'Failed to upload photo.';
        showToast(errMsg, 'error');
        photoPreview.value = null;
        uploadedPhotoUrl.value = null;
    } finally {
        isUploadingPhoto.value = false;
        if (photoInput.value) {
            photoInput.value.value = '';
        }
    }
};

const deletePhoto = async () => {
    isUploadingPhoto.value = true;
    showDeletePhotoConfirm.value = false;

    const destroyUrl = typeof route === 'function' && route().has('profile.photo.destroy')
        ? route('profile.photo.destroy')
        : '/profile/photo';

    try {
        const response = await axios.delete(destroyUrl);

        if (response.data?.success) {
            uploadedPhotoUrl.value = null;
            photoPreview.value = null;
            if (page.props.auth?.user) {
                page.props.auth.user.profile_photo_url = null;
            }
            showToast('Profile photo removed successfully!', 'success');
        }
    } catch (error) {
        console.error('Photo delete error:', error);
        const errMsg = error.response?.data?.message || 'Failed to remove photo.';
        showToast(errMsg, 'error');
    } finally {
        isUploadingPhoto.value = false;
    }
};

const updateProfile = () => {
    profileForm.put(route('profile.update'), {
        onSuccess: () => {
            showToast('Account details saved!', 'success');
            activeTab.value = 'bio';
        },
        onError: () => {
            showToast('Please correct the highlighted fields.', 'error');
        },
        preserveScroll: true,
    });
};

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        onSuccess: () => {
            passwordForm.reset();
            showToast('Password updated securely!', 'success');
            activeTab.value = 'bio';
        },
        onError: () => {
            showToast('Please check the password requirements.', 'error');
        },
        preserveScroll: true,
    });
};

const confirmDeleteAccount = () => {
    showDeleteAccountModal.value = true;
};

const deleteAccount = () => {
    deleteForm.delete(route('profile.destroy'), {
        onSuccess: () => {
            // Redirect handled by controller
        },
        onError: () => {
            showToast('Please provide your correct password.', 'error');
        },
    });
};

const userInitials = computed(() => {
    if (!user.value?.name) return 'ME';
    const names = user.value.name.trim().split(/\s+/);
    if (names.length === 1) return names[0].substring(0, 2).toUpperCase();
    return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
});

const memberSinceFormatted = computed(() => {
    if (!user.value?.created_at) return 'Official Member';
    const d = new Date(user.value.created_at);
    return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

// Social tabs (Only Bio and Profile settings)
const socialTabs = [
    { id: 'bio', label: 'Bio & Overview', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { id: 'edit', label: 'Account Information', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
    { id: 'security', label: 'Security & Key', icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
    { id: 'preferences', label: 'Cover & Customization', icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01' },
    { id: 'danger', label: 'Danger Zone', icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16' },
];

const sidebarComponent = computed(() => {
    if (role.value === 'superadmin') return SuperadminSidebar;
    if (role.value === 'admin') return AdminSidebar;
    return StaffSidebar;
});

const roleLabel = computed(() => {
    if (role.value === 'superadmin') return 'Super Administrator';
    if (role.value === 'admin') return 'Administrator';
    return 'Staff Operations';
});

const roleBadgeClass = computed(() => {
    if (role.value === 'superadmin') return 'bg-amber-500/10 text-amber-600 border-amber-300';
    if (role.value === 'admin') return 'bg-red-500/10 text-red-600 border-red-300';
    return 'bg-blue-500/10 text-blue-600 border-blue-300';
});

const handleSidebarCollapse = (collapsed) => {
    sidebarCollapsed.value = collapsed;
};

const handleSidebarTabChange = (tab) => {
    if (role.value === 'superadmin') {
        localStorage.setItem('meo_superadmin_active_tab', tab);
        router.visit(route('superadmin.dashboard'));
    } else if (role.value === 'admin') {
        localStorage.setItem('meo_admin_active_tab', tab);
        router.visit(route('admin.dashboard'));
    } else {
        localStorage.setItem('meo_staff_active_tab', tab);
        router.visit(route('staff.dashboard'));
    }
};
</script>

<template>
    <Head title="Profile & Bio Settings" />

    <div class="min-h-screen bg-slate-100/70 flex font-sans antialiased text-slate-800 selection:bg-red-500 selection:text-white">
        <!-- Dynamic Role Sidebar -->
        <component
            :is="sidebarComponent"
            :activeTab="sidebarActive"
            @tab-change="handleSidebarTabChange"
            @collapse-change="handleSidebarCollapse"
        />

        <!-- Main Content Area -->
        <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-300', sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-56']">
            
            <!-- Floating Glass Header Bar -->
            <header class="bg-white/95 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30 shadow-2xs">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-15 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 border border-red-200/70 shadow-2xs">
                            <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h1 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight truncate">{{ user?.name }}</h1>
                                <svg class="h-4 w-4 text-blue-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" title="Verified Municipal Profile">
                                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <p class="text-[11px] text-slate-400 font-mono">{{ userHandle }}</p>
                        </div>
                    </div>

                    <!-- Right Navigation Header Actions -->
                    <div class="flex items-center gap-2.5">
                        <NotificationDropdown @navigate-tab="handleSidebarTabChange" />
                    </div>
                </div>
            </header>

            <!-- Social Main Container -->
            <main class="flex-1 pb-16 px-3 sm:px-6 lg:px-8 max-w-6xl mx-auto w-full pt-4 space-y-4">

                <!-- ============================================== -->
                <!-- 1. SOCIAL PROFILE HEADER & COVER BANNER CARD   -->
                <!-- ============================================== -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden relative">
                    
                    <!-- Cover Photo Banner with Dynamic Gradients / Photos / Custom Upload -->
                    <div
                        :style="currentCoverStyle"
                        :class="['h-48 sm:h-64 relative overflow-hidden transition-all duration-500 bg-gradient-to-r', currentCoverClass]"
                    >
                        <!-- Dark Overlay for Readability on Photos -->
                        <div v-if="coverMode !== 'palette'" class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-black/20"></div>

                        <!-- Decorative Abstract Shapes for Gradients -->
                        <template v-if="coverMode === 'palette'">
                            <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
                            <div class="absolute left-1/4 -top-10 w-72 h-72 rounded-full bg-black/15 blur-2xl pointer-events-none"></div>
                        </template>

                        <!-- Top Right Banner Watermark & Change Cover Button -->
                        <div class="absolute top-4 right-4 flex items-center gap-2 z-10">
                            <div class="px-3 py-1 rounded-full bg-black/40 backdrop-blur-md border border-white/20 text-[11px] font-semibold text-white/90 flex items-center gap-1.5 shadow-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>Official MEO Portal</span>
                            </div>

                            <button
                                type="button"
                                @click="showCoverModal = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-black/40 hover:bg-black/60 backdrop-blur-md border border-white/25 text-white/95 text-xs font-semibold transition cursor-pointer shadow-sm"
                                title="Change Cover Banner"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Edit Cover</span>
                            </button>

                            <!-- Hidden Cover Photo Input -->
                            <input
                                ref="coverPhotoInput"
                                type="file"
                                class="hidden"
                                accept="image/*"
                                @change="handleCoverPhotoUpload"
                            />
                        </div>
                    </div>

                    <!-- Profile Info & Overlapping Avatar Row -->
                    <div class="px-5 sm:px-8 pb-6">
                        <div class="flex flex-col sm:flex-row sm:items-end justify-between -mt-16 sm:-mt-20 gap-4 mb-4">
                            
                            <!-- Avatar with Overlap & Status Indicator -->
                            <div class="relative inline-block self-start group">
                                <div class="h-28 w-28 sm:h-36 sm:w-36 rounded-3xl overflow-hidden ring-4 ring-white shadow-xl bg-slate-100 border border-slate-200/80 relative">
                                    <img
                                        v-if="currentPhotoUrl"
                                        :src="currentPhotoUrl"
                                        :alt="user?.name || 'Profile Avatar'"
                                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    />
                                    <div
                                        v-else
                                        class="h-full w-full bg-gradient-to-br from-red-700 via-red-800 to-rose-950 flex items-center justify-center text-white text-3xl sm:text-4xl font-black tracking-wider"
                                    >
                                        {{ userInitials }}
                                    </div>

                                    <!-- Uploading Spinner -->
                                    <div v-if="isUploadingPhoto" class="absolute inset-0 bg-slate-900/70 backdrop-blur-2xs flex flex-col items-center justify-center text-white rounded-3xl">
                                        <div class="h-7 w-7 animate-spin rounded-full border-3 border-white border-t-transparent mb-1"></div>
                                        <span class="text-[10px] font-bold">Saving...</span>
                                    </div>
                                </div>

                                <!-- Online Status Badge -->
                                <span class="absolute bottom-1 right-1 h-5 w-5 rounded-full bg-emerald-500 ring-3 ring-white flex items-center justify-center shadow-sm" title="Online Active">
                                    <span class="h-2 w-2 rounded-full bg-white"></span>
                                </span>

                                <!-- Quick Change Avatar Icon -->
                                <button
                                    type="button"
                                    @click="selectNewPhoto"
                                    :disabled="isUploadingPhoto"
                                    class="absolute -top-1 -right-1 p-2 rounded-2xl bg-white text-slate-700 border border-slate-200 shadow-md hover:bg-red-50 hover:text-red-700 transition cursor-pointer"
                                    title="Upload new avatar photo"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </button>

                                <input
                                    ref="photoInput"
                                    type="file"
                                    class="hidden"
                                    accept="image/*"
                                    @change="updatePhotoPreview"
                                />
                            </div>

                            <!-- Right Profile Action Buttons -->
                            <div class="flex items-center gap-2 self-start sm:self-end pt-2 sm:pt-0 flex-wrap">
                                <button
                                    type="button"
                                    @click="openBioModal"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-700 hover:bg-red-800 text-white text-xs font-bold shadow-xs transition transform active:scale-95 cursor-pointer"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    <span>Edit Bio & Details</span>
                                </button>

                                <button
                                    type="button"
                                    @click="showCoverModal = true"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold transition shadow-2xs cursor-pointer"
                                >
                                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Cover Gallery</span>
                                </button>

                                <button
                                    type="button"
                                    @click="activeTab = 'edit'"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold transition shadow-2xs cursor-pointer"
                                >
                                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>Account</span>
                                </button>
                            </div>
                        </div>

                        <!-- User Names, Handles, Badges & Bio Section -->
                        <div class="space-y-2.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ user?.name }}</h2>
                                <svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 24 24" title="Verified Account">
                                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span :class="['px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border', roleBadgeClass]">
                                    {{ roleLabel }}
                                </span>
                            </div>

                            <!-- Professional Headline -->
                            <p class="text-xs font-semibold text-slate-700">
                                {{ userBioData.headline }}
                            </p>

                            <!-- Bio Description -->
                            <p class="text-xs text-slate-500 font-medium max-w-3xl leading-relaxed">
                                {{ userBioData.bioText }}
                            </p>

                            <!-- Social Meta Badges Row (Location, Email, Date) -->
                            <div class="flex flex-wrap items-center gap-y-2 gap-x-4 text-xs text-slate-500 pt-1">
                                <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>{{ userBioData.officeLocation }}</span>
                                </span>

                                <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Joined {{ memberSinceFormatted }}</span>
                                </span>

                                <span class="inline-flex items-center gap-1 text-slate-600 font-medium">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <span class="hover:text-slate-900 cursor-pointer" @click="copyEmail">{{ user?.email }}</span>
                                    <button type="button" @click="copyEmail" class="p-0.5 text-slate-400 hover:text-slate-700">
                                        <svg v-if="!emailCopied" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <svg v-else class="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- Horizontal Social Tabs Menu -->
                    <div class="px-5 sm:px-8 border-t border-slate-100 bg-slate-50/50 flex items-center gap-2 overflow-x-auto no-scrollbar py-2">
                        <button
                            v-for="tab in socialTabs"
                            :key="tab.id"
                            type="button"
                            @click="activeTab = tab.id"
                            :class="[
                                'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer',
                                activeTab === tab.id
                                    ? 'bg-red-700 text-white shadow-2xs'
                                    : tab.id === 'danger'
                                        ? 'text-rose-600 hover:bg-rose-50'
                                        : 'text-slate-600 hover:bg-white hover:text-slate-900'
                            ]"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
                            </svg>
                            <span>{{ tab.label }}</span>
                        </button>
                    </div>

                </div>

                <!-- ============================================== -->
                <!-- 2. SOCIAL TAB CONTENTS & BIO PANELS            -->
                <!-- ============================================== -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                    
                    <!-- TAB: BIO & OVERVIEW -->
                    <template v-if="activeTab === 'bio'">
                        
                        <!-- Left Sub-column: Detailed About & Contact Info -->
                        <div class="lg:col-span-5 space-y-4">
                            <!-- About & Contact Card -->
                            <div class="bg-white rounded-2xl border border-slate-200/90 p-5 sm:p-6 shadow-2xs space-y-4">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-7 w-7 rounded-lg bg-red-50 text-red-700 flex items-center justify-center">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </div>
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">Official Profile Details</h3>
                                    </div>
                                    <button type="button" @click="openBioModal" class="text-[11px] font-bold text-red-700 hover:underline cursor-pointer">Edit Details</button>
                                </div>

                                <div class="space-y-3 text-xs text-slate-600">
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-lg bg-white shadow-2xs text-red-700 flex items-center justify-center shrink-0">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[10px] text-slate-400 uppercase font-bold">Assigned Position</p>
                                            <p class="font-bold text-slate-800 truncate">{{ user?.position || roleLabel }}</p>
                                        </div>
                                    </div>

                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-lg bg-white shadow-2xs text-blue-700 flex items-center justify-center shrink-0">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[10px] text-slate-400 uppercase font-bold">Office Unit / Desk</p>
                                            <p class="font-bold text-slate-800 truncate">{{ userBioData.officeLocation || 'Municipal Engineering Office, Opol' }}</p>
                                        </div>
                                    </div>

                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="h-8 w-8 rounded-lg bg-white shadow-2xs text-purple-700 flex items-center justify-center shrink-0">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[10px] text-slate-400 uppercase font-bold">Contact Channel</p>
                                                <p class="font-bold text-slate-800 truncate">{{ userBioData.contactPhone || user?.email }}</p>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            @click="copyEmail"
                                            class="shrink-0 p-1.5 rounded-lg bg-white border border-slate-200 text-slate-500 hover:text-slate-900 hover:bg-slate-50 shadow-2xs transition"
                                            title="Copy Contact"
                                        >
                                            <svg v-if="!emailCopied" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            <svg v-else class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Specialization Tags Card -->
                            <div class="bg-white rounded-2xl border border-slate-200/90 p-5 sm:p-6 shadow-2xs space-y-3.5">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">Specialization & Skills</h3>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ userBioData.skills.length }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="isAddingInlineSkill = !isAddingInlineSkill"
                                        class="text-[11px] font-bold text-red-700 hover:underline cursor-pointer"
                                    >
                                        {{ isAddingInlineSkill ? 'Cancel' : '+ Add Skill' }}
                                    </button>
                                </div>

                                <!-- Inline Add Skill Form -->
                                <div v-if="isAddingInlineSkill" class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-200 animate-in fade-in duration-150">
                                    <input
                                        v-model="inlineSkillInput"
                                        type="text"
                                        placeholder="Skill tag name (e.g. AutoCAD)..."
                                        class="flex-1 px-3 py-1.5 rounded-lg border border-slate-300 text-xs text-slate-900 outline-none focus:border-red-600 bg-white"
                                        @keyup.enter="addInlineSkill"
                                    />
                                    <button
                                        type="button"
                                        @click="addInlineSkill"
                                        class="px-3 py-1.5 rounded-lg bg-red-700 text-white text-xs font-bold hover:bg-red-800 transition cursor-pointer"
                                    >
                                        Add
                                    </button>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="(skill, idx) in userBioData.skills"
                                        :key="skill"
                                        class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-800 text-xs font-semibold hover:bg-red-50 hover:text-red-700 transition"
                                    >
                                        <span>{{ skill }}</span>
                                        <button
                                            type="button"
                                            @click="removeSkillDirect(idx)"
                                            class="text-slate-400 hover:text-rose-600 transition"
                                            title="Remove skill"
                                        >
                                            ×
                                        </button>
                                    </div>
                                    <div v-if="userBioData.skills.length === 0" class="text-xs text-slate-400 italic">
                                        No specializations added yet. Click "+ Add Skill" above to add your engineering skills.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Sub-column: Bio Narrative & Portal Role Overview -->
                        <div class="lg:col-span-7 space-y-4">
                            
                            <!-- Biography Card -->
                            <div class="bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-7 shadow-2xs space-y-4">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-8 w-8 rounded-xl bg-red-50 text-red-700 flex items-center justify-center">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-slate-900">About & Background</h3>
                                            <p class="text-[11px] text-slate-400">Professional overview and responsibilities</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="openBioModal"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-red-700 hover:text-red-800 cursor-pointer"
                                    >
                                        <span>Edit Bio</span>
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <h4 class="text-sm font-extrabold text-slate-900">
                                        {{ userBioData.headline || 'Municipal Technical Officer' }}
                                    </h4>
                                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed whitespace-pre-line">
                                        {{ userBioData.bioText || 'No professional summary provided. Click "Edit Bio" to add your responsibilities and background.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Role Overview & Access Level -->
                            <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-2xs space-y-4">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">System Role & Security Status</h3>
                                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                                        Active & Verified
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                                        <p class="text-[10px] uppercase font-bold text-slate-400">Privilege Level</p>
                                        <p class="font-bold text-slate-800 text-sm">{{ roleLabel }}</p>
                                        <p class="text-[11px] text-slate-500">Access to dashboard operations and technical files.</p>
                                    </div>

                                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                                        <p class="text-[10px] uppercase font-bold text-slate-400">Authentication</p>
                                        <p class="font-bold text-emerald-700 text-sm">Encrypted Session</p>
                                        <p class="text-[11px] text-slate-500">Official Municipal Engineering Portal identity (ID #{{ user?.id }}).</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Action Shortcuts -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <button
                                    type="button"
                                    @click="openBioModal"
                                    class="p-4 rounded-2xl bg-white hover:bg-red-50 hover:border-red-200 border border-slate-200/90 text-left transition group shadow-2xs cursor-pointer"
                                >
                                    <svg class="h-5 w-5 text-slate-500 group-hover:text-red-700 mb-2 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    <p class="text-xs font-bold text-slate-800 group-hover:text-red-700">Edit Bio</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Headline & details</p>
                                </button>

                                <button
                                    type="button"
                                    @click="showCoverModal = true"
                                    class="p-4 rounded-2xl bg-white hover:bg-red-50 hover:border-red-200 border border-slate-200/90 text-left transition group shadow-2xs cursor-pointer"
                                >
                                    <svg class="h-5 w-5 text-slate-500 group-hover:text-red-700 mb-2 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="text-xs font-bold text-slate-800 group-hover:text-red-700">Cover & Photos</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Palettes & custom upload</p>
                                </button>

                                <button
                                    type="button"
                                    @click="activeTab = 'security'"
                                    class="p-4 rounded-2xl bg-white hover:bg-red-50 hover:border-red-200 border border-slate-200/90 text-left transition group shadow-2xs cursor-pointer"
                                >
                                    <svg class="h-5 w-5 text-slate-500 group-hover:text-red-700 mb-2 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <p class="text-xs font-bold text-slate-800 group-hover:text-red-700">Security Key</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Change password</p>
                                </button>
                            </div>
                        </div>

                    </template>

                    <!-- TAB: EDIT ACCOUNT -->
                    <div v-else-if="activeTab === 'edit'" class="lg:col-span-12 bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-8 shadow-2xs space-y-6 animate-in fade-in duration-200">
                        <div class="border-b border-slate-100 pb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Edit Account Information</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Update your public display name and official communication email.</p>
                            </div>
                            <button type="button" @click="activeTab = 'bio'" class="text-xs font-bold text-slate-500 hover:text-slate-800">
                                Back to Bio
                            </button>
                        </div>

                        <form @submit.prevent="updateProfile" class="space-y-5 max-w-2xl">
                            <!-- Full Name -->
                            <div class="space-y-1.5">
                                <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Display Name <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="name"
                                    v-model="profileForm.name"
                                    type="text"
                                    required
                                    placeholder="Your Full Name"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                />
                                <p v-if="profileForm.errors.name" class="text-xs text-rose-600 font-medium">{{ profileForm.errors.name }}</p>
                            </div>

                            <!-- Email Address -->
                            <div class="space-y-1.5">
                                <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Official Email Address <span class="text-rose-600">*</span>
                                </label>
                                <input
                                    id="email"
                                    v-model="profileForm.email"
                                    type="email"
                                    required
                                    placeholder="user@example.gov.ph"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                />
                                <p v-if="profileForm.errors.email" class="text-xs text-rose-600 font-medium">{{ profileForm.errors.email }}</p>
                            </div>

                            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                                <button
                                    type="submit"
                                    :disabled="profileForm.processing || !profileForm.isDirty"
                                    class="px-5 py-2.5 rounded-xl bg-red-700 hover:bg-red-800 text-white text-xs font-bold transition shadow-xs disabled:opacity-50 cursor-pointer"
                                >
                                    {{ profileForm.processing ? 'Saving...' : 'Save Account Changes' }}
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'bio'"
                                    class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB: SECURITY & KEY -->
                    <div v-else-if="activeTab === 'security'" class="lg:col-span-12 bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-8 shadow-2xs space-y-6 animate-in fade-in duration-200">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Security & Password Protection</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Ensure your municipal account credentials are strong and unique.</p>
                        </div>

                        <form @submit.prevent="updatePassword" class="space-y-5 max-w-2xl">
                            <!-- Current Password -->
                            <div class="space-y-1.5">
                                <label for="current_password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Current Password <span class="text-rose-600">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="current_password"
                                        v-model="passwordForm.current_password"
                                        :type="showCurrentPassword ? 'text' : 'password'"
                                        required
                                        placeholder="Enter your current password"
                                        class="w-full px-4 py-2.5 pr-10 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                    />
                                    <button
                                        type="button"
                                        @click="showCurrentPassword = !showCurrentPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition"
                                        tabindex="-1"
                                    >
                                        <svg v-if="!showCurrentPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.current_password" class="text-xs text-rose-600 font-medium">{{ passwordForm.errors.current_password }}</p>
                            </div>

                            <!-- New Password -->
                            <div class="space-y-1.5">
                                <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    New Password <span class="text-rose-600">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        v-model="passwordForm.password"
                                        :type="showNewPassword ? 'text' : 'password'"
                                        required
                                        placeholder="Minimum 8 characters"
                                        class="w-full px-4 py-2.5 pr-10 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                    />
                                    <button
                                        type="button"
                                        @click="showNewPassword = !showNewPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition"
                                        tabindex="-1"
                                    >
                                        <svg v-if="!showNewPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                    </button>
                                </div>

                                <!-- Password Strength Bar -->
                                <div v-if="passwordForm.password" class="pt-2 space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-[11px] text-slate-500 font-medium">Strength Assessment:</span>
                                        <span :class="passwordStrength.textClass" class="text-xs uppercase">{{ passwordStrength.label }}</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div
                                            class="h-full transition-all duration-300 rounded-full"
                                            :class="passwordStrength.color"
                                            :style="{ width: passwordStrength.width }"
                                        ></div>
                                    </div>
                                </div>
                                <p v-if="passwordForm.errors.password" class="text-xs text-rose-600 font-medium">{{ passwordForm.errors.password }}</p>
                            </div>

                            <!-- Confirm New Password -->
                            <div class="space-y-1.5">
                                <label for="password_confirmation" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Confirm New Password <span class="text-rose-600">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="password_confirmation"
                                        v-model="passwordForm.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        required
                                        placeholder="Repeat new password"
                                        class="w-full px-4 py-2.5 pr-10 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                    />
                                    <button
                                        type="button"
                                        @click="showConfirmPassword = !showConfirmPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition"
                                        tabindex="-1"
                                    >
                                        <svg v-if="!showConfirmPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                                <button
                                    type="submit"
                                    :disabled="passwordForm.processing"
                                    class="px-5 py-2.5 rounded-xl bg-red-700 hover:bg-red-800 text-white text-xs font-bold transition shadow-xs disabled:opacity-50 cursor-pointer"
                                >
                                    {{ passwordForm.processing ? 'Updating...' : 'Update Password Key' }}
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'bio'"
                                    class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB: COVER & CUSTOMIZATION -->
                    <div v-else-if="activeTab === 'preferences'" class="lg:col-span-12 bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-8 shadow-2xs space-y-6 animate-in fade-in duration-200">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900">Cover Banner & Photos Gallery</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Customize your profile header with engineering photography, color palettes, or custom photos.</p>
                        </div>

                        <!-- 1. Custom Photo Upload Section -->
                        <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/70 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900">Upload Custom Image</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Upload any personal engineering photo or wallpaper from your device (Max 8MB).</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="triggerCoverUpload"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-700 hover:bg-red-800 text-white text-xs font-bold shadow-xs transition cursor-pointer"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <span>Upload Photo</span>
                                    </button>
                                    <button
                                        v-if="coverMode === 'custom-photo'"
                                        type="button"
                                        @click="removeCustomCover"
                                        class="px-3.5 py-2 rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-slate-200 text-xs font-semibold transition"
                                    >
                                        Remove Custom
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Photographic Cover Presets Gallery -->
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900">Curated Engineering & Architecture Photos</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                <div
                                    v-for="photo in coverPhotoPresets"
                                    :key="photo.id"
                                    @click="selectPhotoPreset(photo.id)"
                                    :class="[
                                        'group relative rounded-2xl overflow-hidden border-2 transition-all cursor-pointer shadow-2xs hover:shadow-md aspect-video',
                                        coverMode === 'preset-photo' && selectedCoverId === photo.id
                                            ? 'border-red-600 ring-2 ring-red-600/30'
                                            : 'border-slate-200 hover:border-slate-400'
                                    ]"
                                >
                                    <img :src="photo.thumb" :alt="photo.name" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent p-3 flex flex-col justify-end text-white">
                                        <p class="text-xs font-bold">{{ photo.name }}</p>
                                        <p class="text-[10px] text-slate-200 truncate">{{ photo.desc }}</p>
                                    </div>
                                    <span v-if="coverMode === 'preset-photo' && selectedCoverId === photo.id" class="absolute top-2 right-2 h-6 w-6 rounded-full bg-red-600 text-white flex items-center justify-center text-xs font-bold shadow-md">
                                        ✓
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Gradient Color Palettes Gallery -->
                        <div class="space-y-3 pt-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900">Modern Gradient Mesh Palettes</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                <div
                                    v-for="palette in coverPalettes"
                                    :key="palette.id"
                                    @click="selectPalette(palette.id)"
                                    :class="[
                                        'p-3.5 rounded-2xl border-2 transition-all cursor-pointer flex items-center justify-between',
                                        coverMode === 'palette' && selectedCoverId === palette.id
                                            ? 'border-red-600 bg-red-50/30 shadow-xs'
                                            : 'border-slate-200 hover:border-slate-300'
                                    ]"
                                >
                                    <div class="flex items-center gap-3">
                                        <div :class="['h-9 w-12 rounded-xl bg-gradient-to-r shadow-xs', palette.class]"></div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">{{ palette.name }}</p>
                                            <p class="text-[10px] text-slate-400">{{ coverMode === 'palette' && selectedCoverId === palette.id ? 'Active Palette' : 'Click to apply' }}</p>
                                        </div>
                                    </div>
                                    <span v-if="coverMode === 'palette' && selectedCoverId === palette.id" class="h-5 w-5 rounded-full bg-red-600 text-white flex items-center justify-center text-[10px] font-bold">
                                        ✓
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: DANGER ZONE -->
                    <div v-else-if="activeTab === 'danger'" class="lg:col-span-12 bg-white rounded-2xl border border-rose-200 p-6 sm:p-8 shadow-2xs space-y-6 animate-in fade-in duration-200">
                        <div class="border-b border-rose-100 pb-4">
                            <h3 class="text-base font-bold text-rose-900">Danger Zone</h3>
                            <p class="text-xs text-rose-700/80 mt-0.5">Irreversible actions regarding account termination and data erasure.</p>
                        </div>

                        <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <h4 class="text-xs font-bold text-rose-900 uppercase tracking-wide">Permanently Terminate Account</h4>
                                <p class="text-xs text-rose-700 leading-relaxed max-w-lg">
                                    Once deleted, your account will be removed and cannot be recovered.
                                </p>
                            </div>
                            <button
                                @click="confirmDeleteAccount"
                                type="button"
                                class="shrink-0 px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition transform active:scale-95 cursor-pointer"
                            >
                                Delete Account
                            </button>
                        </div>
                    </div>

                </div>

            </main>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: EDIT BIO, HEADLINE & SKILLS             -->
        <!-- ============================================== -->
        <Teleport to="body">
            <div v-if="showBioModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs animate-in fade-in duration-150" role="dialog" aria-modal="true">
                <div class="bg-white rounded-3xl shadow-2xl max-w-xl w-full max-h-[90vh] flex flex-col border border-slate-200 animate-in zoom-in-95 duration-150 overflow-hidden">
                    
                    <!-- Modal Header -->
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Edit Professional Bio & Skills</h3>
                            <p class="text-xs text-slate-500">Update your headline, description, and municipal engineering specializations.</p>
                        </div>
                        <button
                            type="button"
                            @click="showBioModal = false"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="p-6 overflow-y-auto space-y-4 max-h-[60vh]">
                        <!-- Headline -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Professional Headline
                            </label>
                            <input
                                v-model="bioEditForm.headline"
                                type="text"
                                placeholder="e.g. Municipal Project Engineer • Civil Works Oversight"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                            />
                        </div>

                        <!-- Bio Description -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Bio / Overview Summary
                            </label>
                            <textarea
                                v-model="bioEditForm.bioText"
                                rows="3"
                                placeholder="Briefly describe your municipal engineering responsibilities, tasks, and background..."
                                class="w-full p-3 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition resize-none"
                            ></textarea>
                        </div>

                        <!-- Office Location & Contact Phone Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Office Unit / Desk
                                </label>
                                <input
                                    v-model="bioEditForm.officeLocation"
                                    type="text"
                                    placeholder="e.g. Municipal Engineering Office, Opol"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Contact Channel / Phone / Email
                                </label>
                                <input
                                    v-model="bioEditForm.contactPhone"
                                    type="text"
                                    placeholder="e.g. contact number or email address"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                />
                            </div>
                        </div>

                        <!-- Specialization Skills Tags Editor -->
                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Specialization Tags
                            </label>
                            
                            <!-- Add Skill Input -->
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="newSkillInput"
                                    type="text"
                                    placeholder="Add a new skill tag (e.g. Road Inspection, Estimation, AutoCAD)..."
                                    class="flex-1 px-3.5 py-2 rounded-xl border border-slate-300 text-xs text-slate-900 shadow-2xs focus:border-red-600 focus:ring-2 focus:ring-red-600/20 outline-none transition"
                                    @keyup.enter="addSkillTag"
                                />
                                <button
                                    type="button"
                                    @click="addSkillTag"
                                    class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition cursor-pointer"
                                >
                                    Add Tag
                                </button>
                            </div>

                            <!-- Skills Pills -->
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <span
                                    v-for="(skill, idx) in bioEditForm.skills"
                                    :key="skill"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-100 text-slate-800 text-xs font-medium border border-slate-200"
                                >
                                    <span>{{ skill }}</span>
                                    <button
                                        type="button"
                                        @click="removeSkillTag(idx)"
                                        class="text-slate-400 hover:text-rose-600 transition"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-between gap-2.5">
                        <button
                            type="button"
                            @click="resetBioToDefaults"
                            class="text-xs font-semibold text-slate-500 hover:text-red-700 hover:underline cursor-pointer"
                        >
                            Reset to Role Defaults
                        </button>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="showBioModal = false"
                                class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="saveBioData"
                                class="px-5 py-2 text-xs font-bold text-white bg-red-700 hover:bg-red-800 rounded-xl shadow-xs transition cursor-pointer"
                            >
                                Save Bio & Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ============================================== -->
        <!-- MODAL: EDIT COVER BANNER & PHOTOS GALLERY       -->
        <!-- ============================================== -->
        <Teleport to="body">
            <div v-if="showCoverModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs animate-in fade-in duration-150" role="dialog" aria-modal="true">
                <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col border border-slate-200 animate-in zoom-in-95 duration-150 overflow-hidden">
                    
                    <!-- Modal Header -->
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Profile Cover Customizer</h3>
                            <p class="text-xs text-slate-500">Select an engineering photo, gradient palette, or upload your own.</p>
                        </div>
                        <button
                            type="button"
                            @click="showCoverModal = false"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Modal Nav Tabs -->
                    <div class="px-6 border-b border-slate-100 bg-slate-50/60 flex items-center gap-2 py-2">
                        <button
                            type="button"
                            @click="coverModalTab = 'photos'"
                            :class="coverModalTab === 'photos' ? 'bg-red-700 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-200/70'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        >
                            🖼️ Photos Gallery
                        </button>
                        <button
                            type="button"
                            @click="coverModalTab = 'palettes'"
                            :class="coverModalTab === 'palettes' ? 'bg-red-700 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-200/70'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        >
                            🎨 Gradient Palettes
                        </button>
                        <button
                            type="button"
                            @click="coverModalTab = 'upload'"
                            :class="coverModalTab === 'upload' ? 'bg-red-700 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-200/70'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        >
                            📸 Upload Image
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="p-6 overflow-y-auto space-y-4 max-h-[60vh]">
                        
                        <!-- TAB: Photos Gallery -->
                        <div v-if="coverModalTab === 'photos'" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div
                                v-for="photo in coverPhotoPresets"
                                :key="photo.id"
                                @click="selectPhotoPreset(photo.id)"
                                :class="[
                                    'group relative rounded-2xl overflow-hidden border-2 transition-all cursor-pointer shadow-2xs hover:shadow-md aspect-video',
                                    coverMode === 'preset-photo' && selectedCoverId === photo.id
                                        ? 'border-red-600 ring-2 ring-red-600/30'
                                        : 'border-slate-200 hover:border-slate-400'
                                ]"
                            >
                                <img :src="photo.thumb" :alt="photo.name" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent p-3 flex flex-col justify-end text-white">
                                    <p class="text-xs font-bold">{{ photo.name }}</p>
                                    <p class="text-[10px] text-slate-200 truncate">{{ photo.desc }}</p>
                                </div>
                                <span v-if="coverMode === 'preset-photo' && selectedCoverId === photo.id" class="absolute top-2 right-2 h-6 w-6 rounded-full bg-red-600 text-white flex items-center justify-center text-xs font-bold shadow-md">
                                    ✓
                                </span>
                            </div>
                        </div>

                        <!-- TAB: Palettes -->
                        <div v-else-if="coverModalTab === 'palettes'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                v-for="palette in coverPalettes"
                                :key="palette.id"
                                @click="selectPalette(palette.id)"
                                :class="[
                                    'p-4 rounded-2xl border-2 transition-all cursor-pointer flex items-center justify-between',
                                    coverMode === 'palette' && selectedCoverId === palette.id
                                        ? 'border-red-600 bg-red-50/30 shadow-xs'
                                        : 'border-slate-200 hover:border-slate-300'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <div :class="['h-10 w-14 rounded-xl bg-gradient-to-r shadow-xs', palette.class]"></div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-900">{{ palette.name }}</p>
                                        <p class="text-[10px] text-slate-400">{{ coverMode === 'palette' && selectedCoverId === palette.id ? 'Active Theme' : 'Click to apply' }}</p>
                                    </div>
                                </div>
                                <span v-if="coverMode === 'palette' && selectedCoverId === palette.id" class="h-5 w-5 rounded-full bg-red-600 text-white flex items-center justify-center text-[10px] font-bold">
                                    ✓
                                </span>
                            </div>
                        </div>

                        <!-- TAB: Upload Custom Photo -->
                        <div v-else-if="coverModalTab === 'upload'" class="space-y-4">
                            <div
                                @click="triggerCoverUpload"
                                class="border-2 border-dashed border-slate-300 hover:border-red-500 rounded-3xl p-8 text-center cursor-pointer hover:bg-red-50/20 transition group"
                            >
                                <div class="mx-auto h-12 w-12 rounded-2xl bg-red-50 text-red-700 flex items-center justify-center mb-3 group-hover:scale-110 transition">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                </div>
                                <h4 class="text-xs font-bold text-slate-800">Click to upload custom cover photo</h4>
                                <p class="text-[11px] text-slate-400 mt-1">Supports PNG, JPG, or WebP (recommended 1600x600 px, max 8MB)</p>
                            </div>

                            <div v-if="customCoverDataUrl" class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img :src="customCoverDataUrl" class="h-10 w-16 rounded-lg object-cover border border-slate-300" alt="Custom Preview" />
                                    <div>
                                        <p class="text-xs font-bold text-slate-800">Custom Photo Active</p>
                                        <p class="text-[10px] text-emerald-600 font-semibold">Loaded from device storage</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="removeCustomCover"
                                    class="px-3 py-1.5 rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-slate-200 text-xs font-bold transition"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-end">
                        <button
                            type="button"
                            @click="showCoverModal = false"
                            class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition cursor-pointer"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Floating Toast Notification -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-4 opacity-0 sm:translate-y-0 sm:translate-x-4"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast.show"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-2xl px-4 py-3 shadow-2xl border text-xs font-semibold backdrop-blur-md"
                :class="toast.type === 'success' ? 'bg-slate-900/95 text-white border-slate-800' : 'bg-rose-950/95 text-white border-rose-800'"
            >
                <div class="h-6 w-6 rounded-full flex items-center justify-center shrink-0" :class="toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'">
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

        <!-- Modal: Delete Photo Confirmation -->
        <Teleport to="body">
            <div v-if="showDeletePhotoConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs animate-in fade-in duration-150" role="dialog" aria-modal="true">
                <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-6 text-center border border-slate-200 animate-in zoom-in-95 duration-150 space-y-4">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 border border-rose-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Remove Avatar Photo</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Are you sure you want to remove your profile photo? Your initials will be displayed instead.
                        </p>
                    </div>
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button
                            type="button"
                            @click="showDeletePhotoConfirm = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="deletePhoto"
                            class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs transition"
                        >
                            Yes, Remove
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Delete Account Confirmation -->
        <Teleport to="body">
            <div v-if="showDeleteAccountModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs animate-in fade-in duration-150" role="dialog" aria-modal="true">
                <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 border border-slate-200 animate-in zoom-in-95 duration-150 space-y-4">
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 border border-rose-100">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Authorize Account Deletion</h3>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                Please confirm your current password to execute permanent account deletion.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-1.5 pt-2">
                        <label for="delete-password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Password <span class="text-rose-600">*</span>
                        </label>
                        <input
                            id="delete-password"
                            v-model="deleteForm.password"
                            type="password"
                            required
                            placeholder="Enter password"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-900 shadow-2xs focus:border-rose-600 focus:ring-2 focus:ring-rose-600/20 outline-none transition"
                            @keyup.enter="deleteAccount"
                        />
                        <p v-if="deleteForm.errors.password" class="text-xs text-rose-600 font-medium">{{ deleteForm.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3">
                        <button
                            type="button"
                            @click="showDeleteAccountModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="deleteAccount"
                            :disabled="deleteForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs transition disabled:opacity-50"
                        >
                            <svg v-if="deleteForm.processing" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <span>Confirm Deletion</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
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
</style>