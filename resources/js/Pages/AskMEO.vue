<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    activeInquiry: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const activeTab = ref(props.activeInquiry ? 'status' : 'form'); // 'form' | 'status' | 'lookup'
const currentInquiry = ref(props.activeInquiry);
const lastSyncedAt = ref(new Date());
let pollTimer = null;

const form = useForm({
    fullname: props.activeInquiry ? props.activeInquiry.fullname : '',
    phone: props.activeInquiry ? props.activeInquiry.phone : '',
    email: props.activeInquiry ? (props.activeInquiry.email || '') : '',
    location: '',
    subject: '',
    message: '',
    photos: [], // Array of up to 5 files
});

const photosPreview = ref([]);
const fileInput = ref(null);
const isSubmitting = ref(false);

// Lightbox modal state for viewing images full screen
const lightboxImage = ref(null);
const openLightbox = (url) => {
    lightboxImage.value = url;
};
const closeLightbox = () => {
    lightboxImage.value = null;
};

// Lookup tracking state
const lookupQuery = ref('');
const isLookingUp = ref(false);
const lookupError = ref('');
const lookupResults = ref([]);

const flashSuccess = computed(() => {
    return page.props.flash?.success || null;
});

const handlePhotoChange = (e) => {
    const files = Array.from(e.target.files || []);
    if (files.length === 0) return;

    const remainingSlots = 5 - photosPreview.value.length;
    if (remainingSlots <= 0) {
        alert('You have already attached the maximum of 5 pictures.');
        if (fileInput.value) fileInput.value.value = '';
        return;
    }

    if (files.length > remainingSlots) {
        alert(`You can only attach ${remainingSlots} more picture(s). The first ${remainingSlots} will be added.`);
    }

    const filesToAdd = files.slice(0, remainingSlots);

    for (const file of filesToAdd) {
        if (file.size > 5 * 1024 * 1024) {
            alert(`File "${file.name}" exceeds the maximum 5MB size limit.`);
            continue;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            photosPreview.value.push({
                file: file,
                preview: event.target.result,
                name: file.name,
                size: (file.size / 1024).toFixed(1) + ' KB',
            });
            form.photos = photosPreview.value.map(p => p.file);
        };
        reader.readAsDataURL(file);
    }

    if (fileInput.value) fileInput.value.value = '';
};

const removePhoto = (index) => {
    photosPreview.value.splice(index, 1);
    form.photos = photosPreview.value.map(p => p.file);
    if (fileInput.value) fileInput.value.value = '';
};

const getCancelUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo.cancel')) {
            return route('ask.meo.cancel');
        }
    } catch (e) {}
    return '/ask-meo/cancel';
};

const getWithdrawCancelUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo.withdraw-cancel')) {
            return route('ask.meo.withdraw-cancel');
        }
    } catch (e) {}
    return '/ask-meo/withdraw-cancel';
};

const getSendUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo.send')) {
            return route('ask.meo.send');
        }
    } catch (e) {}
    return '/ask-meo';
};

const getCheckStatusUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo.check-status')) {
            return route('ask.meo.check-status');
        }
    } catch (e) {}
    return '/ask-meo/check-status';
};

const getResetUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo.reset')) {
            return route('ask.meo.reset');
        }
    } catch (e) {}
    return '/ask-meo/reset';
};

const getResolvedUrl = (token) => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo.resolved')) {
            return route('ask.meo.resolved', { token });
        }
    } catch (e) {}
    return `/resolve-concern/${token}`;
};

// Cancellation Modal & Request State
const showCancelModal = ref(false);
const isCancelling = ref(false);
const isWithdrawingCancel = ref(false);
const cancelError = ref('');
const selectedReasonPreset = ref('Issue has been resolved on our own');
const customCancelReason = ref('');

const presetReasons = [
    'Issue has been resolved on our own',
    'Submitted by mistake / duplicate report',
    'Work / repair already done by community',
    'No longer needed or applicable',
    'Other reason (specified below)',
];

const openCancelModal = () => {
    cancelError.value = '';
    selectedReasonPreset.value = 'Issue has been resolved on our own';
    customCancelReason.value = '';
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    if (isCancelling.value) return;
    showCancelModal.value = false;
    cancelError.value = '';
};

const submitCancellationRequest = async () => {
    if (!currentInquiry.value?.tracking_token) return;

    let reason = selectedReasonPreset.value;
    if (customCancelReason.value.trim()) {
        reason = selectedReasonPreset.value === 'Other reason (specified below)' 
            ? customCancelReason.value.trim() 
            : `${selectedReasonPreset.value} — ${customCancelReason.value.trim()}`;
    }

    isCancelling.value = true;
    cancelError.value = '';

    try {
        const response = await axios.post(getCancelUrl(), {
            tracking_token: currentInquiry.value.tracking_token,
            cancellation_reason: reason,
        });

        if (response.data && response.data.success && response.data.inquiry) {
            currentInquiry.value = response.data.inquiry;
            showCancelModal.value = false;
        } else {
            cancelError.value = response.data?.message || 'Failed to submit cancellation request.';
        }
    } catch (err) {
        cancelError.value = err.response?.data?.message || 'An error occurred while submitting cancellation request.';
    } finally {
        isCancelling.value = false;
    }
};

const withdrawCancellationRequest = async () => {
    if (!currentInquiry.value?.tracking_token) return;
    if (!confirm('Are you sure you want to withdraw your cancellation request and keep this concern active?')) return;

    isWithdrawingCancel.value = true;
    try {
        const response = await axios.post(getWithdrawCancelUrl(), {
            tracking_token: currentInquiry.value.tracking_token,
        });

        if (response.data && response.data.success && response.data.inquiry) {
            currentInquiry.value = response.data.inquiry;
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to withdraw cancellation request.');
    } finally {
        isWithdrawingCancel.value = false;
    }
};

const submitConcern = () => {
    if (!form.fullname?.trim() || !form.phone?.trim() || !form.location?.trim() || !form.message?.trim()) {
        return;
    }

    isSubmitting.value = true;

    form.post(getSendUrl(), {
        forceFormData: true,
        onSuccess: (pageRes) => {
            isSubmitting.value = false;
            if (pageRes.props.activeInquiry) {
                currentInquiry.value = pageRes.props.activeInquiry;
            }
            activeTab.value = 'status';
            photosPreview.value = [];
            form.photos = [];
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const performLookup = async () => {
    if (!lookupQuery.value.trim()) return;

    isLookingUp.value = true;
    lookupError.value = '';
    lookupResults.value = [];

    try {
        const response = await axios.post(getCheckStatusUrl(), {
            query: lookupQuery.value.trim(),
        });

        if (response.data && response.data.success) {
            lookupResults.value = response.data.inquiries || [];
        }
    } catch (err) {
        lookupError.value = err.response?.data?.message || 'No concern records found with that contact number or reference code.';
    } finally {
        isLookingUp.value = false;
    }
};

const selectInquiryFromLookup = (inquiry) => {
    if (inquiry.status === 'resolved') {
        router.visit(getResolvedUrl(inquiry.tracking_token));
        return;
    }
    currentInquiry.value = inquiry;
    activeTab.value = 'status';
};

const syncActiveInquiry = async () => {
    if (!currentInquiry.value?.tracking_token) return;
    if (typeof document !== 'undefined' && document.hidden) return;

    try {
        const response = await axios.post(getCheckStatusUrl(), {
            query: currentInquiry.value.tracking_token,
        });

        if (response.data && response.data.success && Array.isArray(response.data.inquiries) && response.data.inquiries.length > 0) {
            const updated = response.data.inquiries[0];
            // Compare and update if changed
            if (JSON.stringify(updated) !== JSON.stringify(currentInquiry.value)) {
                currentInquiry.value = updated;
                lastSyncedAt.value = new Date();
            }
        }
    } catch (err) {
        // Silent error handling for background polling
    }
};

onMounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(syncActiveInquiry, 4000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});

const startRelayNewConcern = () => {
    router.post(getResetUrl(), {}, {
        onSuccess: () => {
            currentInquiry.value = null;
            form.fullname = '';
            form.phone = '';
            form.email = '';
            form.location = '';
            form.subject = '';
            form.message = '';
            form.photos = [];
            form.clearErrors();
            photosPreview.value = [];
            if (fileInput.value) fileInput.value.value = '';
            activeTab.value = 'form';
        },
        onError: () => {
            currentInquiry.value = null;
            form.fullname = '';
            form.phone = '';
            form.email = '';
            form.location = '';
            form.subject = '';
            form.message = '';
            form.photos = [];
            photosPreview.value = [];
            activeTab.value = 'form';
        },
    });
};

const switchToForm = () => {
    if (currentInquiry.value && (currentInquiry.value.status === 'resolved' || currentInquiry.value.status === 'declined' || currentInquiry.value.status === 'cancelled')) {
        startRelayNewConcern();
    } else {
        activeTab.value = 'form';
    }
};

const statusBadgeConfig = {
    pending: {
        label: 'Waiting for Acceptance',
        sub: 'Your concern is currently in queue and awaiting review by the Municipal Engineering Office.',
        badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
        dotClass: 'bg-amber-500 animate-pulse',
        icon: 'clock',
    },
    accepted: {
        label: 'Concern Accepted',
        sub: 'Your concern has been accepted by the Municipal Engineering Office team for action.',
        badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        dotClass: 'bg-emerald-500',
        icon: 'check',
    },
    cancel_requested: {
        label: 'Cancellation Pending Confirmation',
        sub: 'You have submitted a request to cancel this concern. Awaiting confirmation by MEO staff/admin.',
        badgeClass: 'bg-rose-50 text-rose-700 border-rose-200',
        dotClass: 'bg-rose-500 animate-pulse',
        icon: 'x-circle',
    },
    cancelled: {
        label: 'Concern Cancelled',
        sub: 'This concern was cancelled and confirmed by the Municipal Engineering Office.',
        badgeClass: 'bg-slate-100 text-slate-700 border-slate-300',
        dotClass: 'bg-slate-500',
        icon: 'ban',
    },
    resolved: {
        label: 'Concern Resolved',
        sub: 'This concern has been addressed and completed by the Municipal Engineering Office.',
        badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
        dotClass: 'bg-blue-500',
        icon: 'check-double',
    },
    declined: {
        label: 'Closed / Out of Scope',
        sub: 'This inquiry was reviewed and marked as closed or directed to another municipal department.',
        badgeClass: 'bg-slate-50 text-slate-700 border-slate-200',
        dotClass: 'bg-slate-500',
        icon: 'x',
    },
};

const inputClass = 'w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition placeholder:text-gray-400 bg-white text-gray-900 shadow-sm';
const labelClass = 'block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wider';
const errorClass = 'mt-1.5 text-xs text-red-600 font-medium';
</script>

<template>
    <Head title="Ask MEO - Public Inquiries &amp; Concern Portal" />

    <div class="min-h-screen flex flex-col justify-between relative overflow-hidden py-10 px-4 sm:px-6 lg:px-8 bg-slate-950">
        <!-- Full Screen Blurred Background MEO Logo -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <img 
                src="/image/meo_logo2.png" 
                alt="Background MEO" 
                class="w-full h-full object-cover blur-[10px] scale-110 opacity-60"
            />
            <!-- Dark Overlay for Readability -->
            <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"></div>
            <!-- Red & Slate Ambient Gradient -->
            <div class="absolute inset-0 bg-gradient-to-br from-red-950/40 via-slate-900/70 to-slate-950/90"></div>
            <!-- Radial Center Glow -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-red-600/10 rounded-full blur-3xl pointer-events-none"></div>
        </div>

        <div class="w-full max-w-2xl mx-auto relative z-10 my-auto">
            <!-- Header Branding -->
            <div class="text-center mb-6">
                <Link href="/" class="inline-flex items-center justify-center mb-3 relative group">
                    <div class="absolute inset-0 bg-red-600 rounded-2xl blur-xl opacity-40 group-hover:opacity-60 transition duration-300"></div>
                    <img 
                        src="/image/meo_logo2.png" 
                        alt="MEO Logo" 
                        class="w-16 h-16 rounded-2xl shadow-xl border border-white/20 object-cover relative transform group-hover:scale-105 transition duration-300"
                    />
                </Link>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Ask MEO</h1>
                <p class="mt-1 text-xs sm:text-sm font-medium text-red-400">Municipal Engineering Office — Municipality of Opol</p>
                <p class="mt-1 text-xs text-slate-300 max-w-lg mx-auto">
                    Direct public inquiry, community concern, and site observation reporting platform for all citizens.
                </p>
            </div>

            <!-- Navigation Tabs -->
            <div class="flex items-center justify-center gap-2 mb-4">
                <button 
                    @click="switchToForm"
                    class="px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 flex items-center gap-2 shadow-sm"
                    :class="activeTab === 'form' ? 'bg-red-600 text-white shadow-red-900/50' : 'bg-slate-800/80 text-slate-300 hover:bg-slate-700 hover:text-white border border-slate-700/60'"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Send Concern
                </button>

                <button 
                    v-if="currentInquiry"
                    @click="activeTab = 'status'"
                    class="px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 flex items-center gap-2 shadow-sm"
                    :class="activeTab === 'status' ? 'bg-red-600 text-white shadow-red-900/50' : 'bg-slate-800/80 text-slate-300 hover:bg-slate-700 hover:text-white border border-slate-700/60'"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Current Status
                    <span 
                        class="w-2 h-2 rounded-full"
                        :class="currentInquiry.status === 'resolved' ? 'bg-blue-400' : (currentInquiry.status === 'accepted' ? 'bg-emerald-400' : 'bg-amber-400 animate-ping')"
                    ></span>
                </button>

                <button 
                    @click="activeTab = 'lookup'"
                    class="px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 flex items-center gap-2 shadow-sm"
                    :class="activeTab === 'lookup' ? 'bg-red-600 text-white shadow-red-900/50' : 'bg-slate-800/80 text-slate-300 hover:bg-slate-700 hover:text-white border border-slate-700/60'"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Track Concern
                </button>
            </div>

            <!-- Main Card Container -->
            <div class="bg-white rounded-2xl shadow-2xl border border-white/20 overflow-hidden backdrop-blur-md">
                
                <!-- ==================== TAB 1: SUBMISSION FORM ==================== -->
                <div v-if="activeTab === 'form'">
                    <!-- Card Top Banner -->
                    <div class="bg-gradient-to-r from-red-600 to-rose-700 px-6 py-4 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                    Submit a Concern / Inquiry
                                </h2>
                                <p class="text-xs text-red-100 mt-0.5">Please provide accurate contact details and location for verification.</p>
                            </div>
                            <span class="text-[10px] uppercase font-bold bg-white/20 backdrop-blur-sm px-2.5 py-1 rounded-full text-white">
                                Public Portal
                            </span>
                        </div>
                    </div>

                    <!-- Form Body -->
                    <form @submit.prevent="submitConcern" class="p-6 space-y-4">
                        <!-- Full Name -->
                        <div>
                            <label for="fullname" :class="labelClass">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="fullname"
                                v-model="form.fullname"
                                type="text"
                                required
                                placeholder="e.g., Juan Dela Cruz"
                                :class="[inputClass, form.errors.fullname ? 'border-red-400 bg-red-50/30' : '']"
                            />
                            <p v-if="form.errors.fullname" :class="errorClass">{{ form.errors.fullname }}</p>
                        </div>

                        <!-- Contact Number & Email -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Contact Number (Required) -->
                            <div>
                                <label for="phone" :class="labelClass">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        required
                                        placeholder="e.g., 09171234567"
                                        :class="[inputClass, form.errors.phone ? 'border-red-400 bg-red-50/30' : '']"
                                    />
                                </div>
                                <p class="text-[11px] text-gray-500 mt-1">Used to track and send updates regarding your concern.</p>
                                <p v-if="form.errors.phone" :class="errorClass">{{ form.errors.phone }}</p>
                            </div>

                            <!-- Email (Optional) -->
                            <div>
                                <label for="email" :class="labelClass">
                                    Email Address <span class="text-gray-400 lowercase font-normal">(optional)</span>
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="e.g., juandelacruz@gmail.com"
                                    :class="[inputClass, form.errors.email ? 'border-red-400 bg-red-50/30' : '']"
                                />
                                <p v-if="form.errors.email" :class="errorClass">{{ form.errors.email }}</p>
                            </div>
                        </div>

                        <!-- Location / Barangay (Required) -->
                        <div>
                            <label for="location" :class="labelClass">
                                Location / Barangay / Street <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <input
                                    id="location"
                                    v-model="form.location"
                                    type="text"
                                    required
                                    placeholder="e.g., Barangay Poblacion, Purok 3 / Barra / Igpit / Taboc"
                                    :class="[inputClass, 'pl-10', form.errors.location ? 'border-red-400 bg-red-50/30' : '']"
                                />
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1">Specify where the engineering concern, damaged facility, or project inquiry is located.</p>
                            <p v-if="form.errors.location" :class="errorClass">{{ form.errors.location }}</p>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label for="subject" :class="labelClass">
                                Subject / Topic <span class="text-gray-400 lowercase font-normal">(optional)</span>
                            </label>
                            <input
                                id="subject"
                                v-model="form.subject"
                                type="text"
                                placeholder="e.g., Road Repair Inquiry, Drainage Observation, Building Permit Question"
                                :class="[inputClass, form.errors.subject ? 'border-red-400 bg-red-50/30' : '']"
                            />
                            <p v-if="form.errors.subject" :class="errorClass">{{ form.errors.subject }}</p>
                        </div>

                        <!-- Message / Concern Details -->
                        <div>
                            <label for="message" :class="labelClass">
                                Detailed Concern / Message <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="4"
                                required
                                placeholder="Please describe the site details, issue, or question clearly..."
                                :class="[inputClass, 'resize-none', form.errors.message ? 'border-red-400 bg-red-50/30' : '']"
                            ></textarea>
                            <div class="flex justify-between items-center mt-1">
                                <p v-if="form.errors.message" :class="errorClass">{{ form.errors.message }}</p>
                                <span class="text-[11px] text-gray-400 ml-auto">{{ form.message.length }} characters</span>
                            </div>
                        </div>

                        <!-- Picture Attachments (Optional - Up to 5 Pictures) -->
                        <div class="pt-1">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Photo Attachments <span class="text-gray-400 lowercase font-normal">(optional — up to 5 photos, max 5MB each)</span>
                                </label>
                                <span class="text-xs font-bold" :class="photosPreview.length >= 5 ? 'text-red-600' : 'text-slate-500'">
                                    {{ photosPreview.length }} / 5 photos
                                </span>
                            </div>
                            
                            <!-- Hidden input for file selection -->
                            <input 
                                ref="fileInput"
                                type="file" 
                                accept="image/*"
                                multiple
                                class="hidden" 
                                @change="handlePhotoChange"
                            />

                            <!-- Gallery of attached photos -->
                            <div v-if="photosPreview.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 mb-2.5">
                                <div 
                                    v-for="(p, idx) in photosPreview" 
                                    :key="idx"
                                    class="relative group rounded-xl overflow-hidden border border-gray-200 bg-gray-50 shadow-sm aspect-video sm:aspect-square flex items-center justify-center"
                                >
                                    <img 
                                        :src="p.preview" 
                                        alt="Attached preview" 
                                        class="w-full h-full object-cover"
                                    />
                                    <!-- Delete Overlay Button -->
                                    <button 
                                        type="button" 
                                        @click="removePhoto(idx)"
                                        class="absolute top-1.5 right-1.5 p-1 bg-black/60 hover:bg-red-600 text-white rounded-lg transition shadow"
                                        title="Remove this photo"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    <!-- Index Badge -->
                                    <span class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 bg-black/60 text-white text-[10px] font-bold rounded">
                                        #{{ idx + 1 }}
                                    </span>
                                </div>

                                <!-- Add More Tile (if under 5) -->
                                <button 
                                    v-if="photosPreview.length < 5"
                                    type="button"
                                    @click="fileInput.click()"
                                    class="border-2 border-dashed border-gray-300 hover:border-red-400 hover:bg-red-50/20 rounded-xl transition flex flex-col items-center justify-center gap-1 p-2 text-gray-500 aspect-video sm:aspect-square"
                                >
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span class="text-[11px] font-semibold text-gray-700">Add More</span>
                                    <span class="text-[9px] text-gray-400">({{ 5 - photosPreview.length }} left)</span>
                                </button>
                            </div>

                            <!-- Upload Box when zero photos -->
                            <div 
                                v-else 
                                class="border-2 border-dashed border-gray-300 hover:border-red-400 rounded-xl p-4 transition-colors text-center bg-slate-50/60 cursor-pointer" 
                                @click="fileInput.click()"
                            >
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div class="text-xs text-gray-600 font-medium">
                                        <span class="text-red-600 font-semibold underline">Click to upload up to 5 photos</span> of the site or issue
                                    </div>
                                    <p class="text-[10px] text-gray-400">PNG, JPG, JPEG, or WebP up to 5MB each</p>
                                </div>
                            </div>

                            <p v-if="form.errors.photos" :class="errorClass">{{ form.errors.photos }}</p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <Link 
                                href="/" 
                                class="text-xs text-gray-500 hover:text-gray-900 transition flex items-center gap-1.5 font-medium"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Return to Portal
                            </Link>
                            
                            <button
                                type="submit"
                                :disabled="form.processing || !form.fullname?.trim() || !form.phone?.trim() || !form.location?.trim() || !form.message?.trim()"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-red-600 to-rose-600 text-white text-xs sm:text-sm font-semibold rounded-xl hover:from-red-700 hover:to-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-md shadow-red-900/20"
                            >
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                {{ form.processing ? 'Submitting...' : 'Submit Concern' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ==================== TAB 2: ACTIVE CONCERN STATUS ==================== -->
                <div v-else-if="activeTab === 'status'" class="p-6">
                    <div v-if="currentInquiry" class="space-y-5">
                        
                        <!-- Top Live Sync Indicator -->
                        <div class="flex items-center justify-between px-3.5 py-2 rounded-xl bg-slate-100 border border-slate-200/80 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                </span>
                                <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Live Realtime Updates Active</span>
                            </div>
                            <span class="text-[11px] text-slate-500 font-medium hidden sm:inline">
                                Auto-checking for MEO engineering team responses
                            </span>
                        </div>

                        <!-- Status Alert Card -->
                        <div 
                            class="rounded-2xl border p-5 transition-all shadow-sm"
                            :class="{
                                'bg-blue-50/90 border-blue-300': currentInquiry.status === 'resolved',
                                'bg-emerald-50/80 border-emerald-200': currentInquiry.status === 'accepted',
                                'bg-rose-50/90 border-rose-300': currentInquiry.status === 'cancel_requested',
                                'bg-slate-100/90 border-slate-300': currentInquiry.status === 'cancelled',
                                'bg-amber-50/80 border-amber-200': currentInquiry.status === 'pending' || !['resolved', 'accepted', 'cancel_requested', 'cancelled'].includes(currentInquiry.status)
                            }"
                        >
                            <div class="flex items-start gap-4">
                                <!-- Status Icon -->
                                <div 
                                    class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                                    :class="{
                                        'bg-blue-600 text-white': currentInquiry.status === 'resolved',
                                        'bg-emerald-600 text-white': currentInquiry.status === 'accepted',
                                        'bg-rose-600 text-white': currentInquiry.status === 'cancel_requested',
                                        'bg-slate-600 text-white': currentInquiry.status === 'cancelled',
                                        'bg-amber-500 text-white': currentInquiry.status === 'pending' || !['resolved', 'accepted', 'cancel_requested', 'cancelled'].includes(currentInquiry.status)
                                    }"
                                >
                                    <!-- Resolved Double-Check / Trophy Icon -->
                                    <svg v-if="currentInquiry.status === 'resolved'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <!-- Accepted Check Icon -->
                                    <svg v-else-if="currentInquiry.status === 'accepted'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <!-- Cancel Requested Icon -->
                                    <svg v-else-if="currentInquiry.status === 'cancel_requested'" class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <!-- Cancelled Icon -->
                                    <svg v-else-if="currentInquiry.status === 'cancelled'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    <!-- Pending Loading Spinner -->
                                    <svg v-else class="w-6 h-6 animate-spin" style="animation-duration: 4s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="30 10"/>
                                        <polyline points="12 6 12 12 16 14" stroke-width="2"/>
                                    </svg>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <!-- Resolved State -->
                                    <div v-if="currentInquiry.status === 'resolved'">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800 border border-blue-300 mb-1">
                                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                                            Status: Concern Resolved
                                        </div>
                                        <h3 class="text-base sm:text-lg font-bold text-blue-900 leading-tight">
                                            Your concern has already been resolved!
                                        </h3>
                                        <p class="text-xs sm:text-sm text-blue-800 mt-1">
                                            The Municipal Engineering Office has addressed, inspected, and completed action on your reported concern. Thank you for helping keep our community well-maintained!
                                        </p>
                                        <p v-if="currentInquiry.resolved_by_user" class="text-xs font-semibold text-blue-950 mt-1.5 flex items-center gap-1">
                                            <span>Inspected &amp; Resolved by:</span>
                                            <span class="bg-blue-200/70 px-2 py-0.5 rounded text-blue-900 font-bold">{{ currentInquiry.resolved_by_user.name }} ({{ currentInquiry.resolved_by_user.role?.toUpperCase() }})</span>
                                        </p>
                                    </div>

                                    <!-- Accepted State -->
                                    <div v-else-if="currentInquiry.status === 'accepted'">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 mb-1">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                            Status: Already Accepted
                                        </div>
                                        <h3 class="text-base sm:text-lg font-bold text-emerald-900 leading-tight">
                                            Your concern has already been accepted!
                                        </h3>
                                        <p class="text-xs sm:text-sm text-emerald-800 mt-1">
                                            The Municipal Engineering Office has verified and accepted your report for site inspection / action.
                                        </p>
                                        <p v-if="currentInquiry.accepted_by_user" class="text-xs font-semibold text-emerald-950 mt-1.5 flex items-center gap-1">
                                            <span>Accepted by:</span>
                                            <span class="bg-emerald-200/70 px-2 py-0.5 rounded text-emerald-900 font-bold">{{ currentInquiry.accepted_by_user.name }} ({{ currentInquiry.accepted_by_user.role?.toUpperCase() }})</span>
                                        </p>
                                    </div>

                                    <!-- Cancellation Requested State -->
                                    <div v-else-if="currentInquiry.status === 'cancel_requested'">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-300 mb-1">
                                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                            Status: Cancellation Pending Confirmation
                                        </div>
                                        <h3 class="text-base sm:text-lg font-bold text-rose-900 leading-tight">
                                            Cancellation requested — awaiting MEO confirmation
                                        </h3>
                                        <p class="text-xs sm:text-sm text-rose-800 mt-1">
                                            You submitted a cancellation request for this concern. An MEO staff, administrator, or superadmin will review and confirm your cancellation shortly.
                                        </p>
                                        <div v-if="currentInquiry.cancellation_reason" class="mt-2 p-2.5 rounded-xl bg-white/80 border border-rose-200 text-xs text-rose-900">
                                            <strong class="font-bold">Reason Provided:</strong> {{ currentInquiry.cancellation_reason }}
                                        </div>
                                    </div>

                                    <!-- Cancelled State -->
                                    <div v-else-if="currentInquiry.status === 'cancelled'">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-200 text-slate-800 border border-slate-300 mb-1">
                                            <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                                            Status: Concern Cancelled
                                        </div>
                                        <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">
                                            This concern has been cancelled
                                        </h3>
                                        <p class="text-xs sm:text-sm text-slate-700 mt-1">
                                            This concern was cancelled and confirmed by the Municipal Engineering Office team.
                                        </p>
                                        <p v-if="currentInquiry.cancelled_by_user" class="text-xs font-semibold text-slate-900 mt-1.5 flex items-center gap-1">
                                            <span>Cancellation Confirmed by:</span>
                                            <span class="bg-slate-200 px-2 py-0.5 rounded text-slate-900 font-bold">{{ currentInquiry.cancelled_by_user.name }} ({{ currentInquiry.cancelled_by_user.role?.toUpperCase() }})</span>
                                            <span v-if="currentInquiry.cancelled_at" class="text-slate-500 font-normal">on {{ currentInquiry.cancelled_at }}</span>
                                        </p>
                                        <div v-if="currentInquiry.cancellation_reason" class="mt-2 p-2.5 rounded-xl bg-white border border-slate-200 text-xs text-slate-800">
                                            <strong class="font-bold">Cancellation Reason:</strong> {{ currentInquiry.cancellation_reason }}
                                        </div>
                                    </div>

                                    <!-- Pending State -->
                                    <div v-else>
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300 mb-1">
                                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                            Status: Waiting for Acceptance
                                        </div>
                                        <h3 class="text-base sm:text-lg font-bold text-amber-900 leading-tight">
                                            Your concern is waiting for acceptance
                                        </h3>
                                        <p class="text-xs sm:text-sm text-amber-800 mt-1">
                                            Your concern is in the queue and will be reviewed by the designated MEO engineering staff.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Prompts per Status -->
                            
                            <!-- Resolved State Actions -->
                            <div 
                                v-if="currentInquiry.status === 'resolved'"
                                class="mt-4 pt-3 border-t border-blue-200/70 flex flex-col sm:flex-row items-center justify-between gap-3"
                            >
                                <p class="text-xs font-semibold text-blue-900">
                                    Your previous concern is resolved.
                                </p>
                                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                                    <Link 
                                        :href="getResolvedUrl(currentInquiry.tracking_token)"
                                        class="flex-1 sm:flex-initial px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-1.5"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        View Full Resolution Notice
                                    </Link>
                                    <button 
                                        @click="startRelayNewConcern"
                                        class="flex-1 sm:flex-initial px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold rounded-xl transition shadow-2xs flex items-center justify-center gap-1.5"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Relay Concerns Again
                                    </button>
                                </div>
                            </div>

                            <!-- Accepted State Actions -->
                            <div 
                                v-else-if="currentInquiry.status === 'accepted'"
                                class="mt-4 pt-3 border-t border-emerald-200/60 flex flex-col sm:flex-row items-center justify-between gap-3"
                            >
                                <p class="text-xs font-semibold text-emerald-900">
                                    Concern is currently in progress with MEO team.
                                </p>
                                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                                    <!-- Cancel Concern Button -->
                                    <button 
                                        @click="openCancelModal"
                                        class="px-3.5 py-1.5 bg-white hover:bg-rose-50 text-rose-700 hover:text-rose-800 border border-rose-200 text-xs font-bold rounded-xl transition shadow-2xs flex items-center justify-center gap-1.5"
                                    >
                                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Cancel Concern
                                    </button>

                                    <button 
                                        @click="startRelayNewConcern"
                                        class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-1.5"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Relay Another Concern
                                    </button>
                                </div>
                            </div>

                            <!-- Pending State Actions -->
                            <div 
                                v-else-if="currentInquiry.status === 'pending'"
                                class="mt-4 pt-3 border-t border-amber-200/60 flex flex-col sm:flex-row items-center justify-between gap-3"
                            >
                                <p class="text-xs font-semibold text-amber-900">
                                    Waiting for review by Municipal Engineering Office.
                                </p>
                                <button 
                                    @click="openCancelModal"
                                    class="px-3.5 py-1.5 bg-white hover:bg-rose-50 text-rose-700 hover:text-rose-800 border border-rose-200 text-xs font-bold rounded-xl transition shadow-2xs flex items-center justify-center gap-1.5"
                                >
                                    <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Cancel Concern
                                </button>
                            </div>

                            <!-- Cancellation Pending State Actions -->
                            <div 
                                v-else-if="currentInquiry.status === 'cancel_requested'"
                                class="mt-4 pt-3 border-t border-rose-200/60 flex flex-col sm:flex-row items-center justify-between gap-3"
                            >
                                <p class="text-xs font-semibold text-rose-900">
                                    Changed your mind? You can withdraw the request before confirmation.
                                </p>
                                <button 
                                    @click="withdrawCancellationRequest"
                                    :disabled="isWithdrawingCancel"
                                    class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-1.5 disabled:opacity-50"
                                >
                                    <svg v-if="isWithdrawingCancel" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Withdraw Cancellation Request
                                </button>
                            </div>

                            <!-- Cancelled State Actions -->
                            <div 
                                v-else-if="currentInquiry.status === 'cancelled'"
                                class="mt-4 pt-3 border-t border-slate-300 flex flex-col sm:flex-row items-center justify-between gap-3"
                            >
                                <p class="text-xs font-semibold text-slate-800">
                                    Concern has been cancelled.
                                </p>
                                <button 
                                    @click="startRelayNewConcern"
                                    class="w-full sm:w-auto px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Submit a New Concern
                                </button>
                            </div>
                        </div>

                        <!-- Inquiry Details Summary Card -->
                        <div class="border border-gray-200 rounded-2xl p-5 bg-slate-50/50 space-y-3">
                            <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                                <div>
                                    <span class="text-[10px] uppercase font-bold tracking-wider text-gray-500">Tracking Reference</span>
                                    <p class="text-sm font-mono font-bold text-gray-900">{{ currentInquiry.tracking_token }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] uppercase font-bold tracking-wider text-gray-500">Date Logged</span>
                                    <p class="text-xs font-semibold text-gray-700">{{ currentInquiry.created_at }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span class="text-gray-500 font-medium">Citizen Name:</span>
                                    <p class="font-semibold text-gray-900 mt-0.5">{{ currentInquiry.fullname }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500 font-medium">Contact Number:</span>
                                    <p class="font-semibold text-gray-900 mt-0.5">{{ currentInquiry.phone }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <span class="text-gray-500 font-medium">Location:</span>
                                    <p class="font-semibold text-red-700 mt-0.5 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                        {{ currentInquiry.location }}
                                    </p>
                                </div>
                                <div class="sm:col-span-2" v-if="currentInquiry.subject">
                                    <span class="text-gray-500 font-medium">Subject:</span>
                                    <p class="font-semibold text-gray-900 mt-0.5">{{ currentInquiry.subject }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <span class="text-gray-500 font-medium">Concern Message:</span>
                                    <div class="mt-1 bg-white p-3 rounded-xl border border-gray-200 text-gray-800 leading-relaxed whitespace-pre-line">
                                        {{ currentInquiry.message }}
                                    </div>
                                </div>

                                <!-- Cancellation Reason in Details (if any) -->
                                <div class="sm:col-span-2" v-if="currentInquiry.cancellation_reason">
                                    <span class="text-rose-700 font-semibold block mb-1">Cancellation Request / Reason:</span>
                                    <div class="bg-rose-50/70 p-3 rounded-xl border border-rose-200 text-rose-950 text-xs">
                                        {{ currentInquiry.cancellation_reason }}
                                    </div>
                                </div>

                                <!-- Attached Photos Gallery (Up to 5 Photos) -->
                                <div class="sm:col-span-2" v-if="(currentInquiry.photo_urls && currentInquiry.photo_urls.length > 0) || currentInquiry.photo_url">
                                    <span class="text-gray-500 font-medium block mb-1.5">Attached Site Photos:</span>
                                    
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        <div 
                                            v-for="(photoUrl, pIdx) in (currentInquiry.photo_urls?.length ? currentInquiry.photo_urls : [currentInquiry.photo_url])" 
                                            :key="pIdx"
                                            @click="openLightbox(photoUrl)"
                                            class="relative group rounded-xl overflow-hidden border border-gray-200 bg-black/5 aspect-video cursor-pointer"
                                        >
                                            <img 
                                                :src="photoUrl" 
                                                alt="Concern Attachment" 
                                                class="w-full h-full object-cover group-hover:scale-105 transition duration-200"
                                            />
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-semibold transition">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                                                Enlarge
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Actions -->
                        <div class="flex items-center justify-between pt-2">
                            <button 
                                @click="switchToForm"
                                class="text-xs text-gray-600 hover:text-gray-900 font-semibold flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 0118 0z" />
                                </svg>
                                Back to Form
                            </button>

                            <button 
                                @click="startRelayNewConcern"
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-2"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Submit Another Concern
                            </button>
                        </div>

                    </div>

                    <!-- No active inquiry state in status tab -->
                    <div v-else class="text-center py-8">
                        <p class="text-sm text-gray-500 mb-3">No active concern submission found in your current session.</p>
                        <button 
                            @click="activeTab = 'form'" 
                            class="px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-xl"
                        >
                            Submit a Concern
                        </button>
                    </div>
                </div>

                <!-- ==================== TAB 3: TRACK / LOOKUP CONCERN ==================== -->
                <div v-else-if="activeTab === 'lookup'" class="p-6 space-y-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Track Concern Status</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Enter your Contact Number or Reference Code (e.g. MEO-2026...) to see real-time updates.</p>
                    </div>

                    <form @submit.prevent="performLookup" class="flex gap-2">
                        <div class="relative flex-1">
                            <input 
                                v-model="lookupQuery" 
                                type="text"
                                required
                                placeholder="Enter Contact Number or Reference Code..."
                                :class="inputClass"
                            />
                        </div>
                        <button 
                            type="submit" 
                            :disabled="isLookingUp || !lookupQuery.trim()"
                            class="px-5 py-2.5 bg-red-600 text-white text-xs sm:text-sm font-semibold rounded-xl hover:bg-red-700 disabled:opacity-50 transition shadow-sm shrink-0 flex items-center gap-2"
                        >
                            <svg v-if="isLookingUp" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Search
                        </button>
                    </form>

                    <!-- Error Alert -->
                    <div v-if="lookupError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700 font-medium">
                        {{ lookupError }}
                    </div>

                    <!-- Search Results -->
                    <div v-if="lookupResults.length > 0" class="space-y-3 pt-2">
                        <p class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Found {{ lookupResults.length }} Concern Record(s):</p>
                        
                        <div 
                            v-for="item in lookupResults" 
                            :key="item.id"
                            @click="selectInquiryFromLookup(item)"
                            class="p-4 rounded-xl border border-gray-200 hover:border-red-400 bg-slate-50/50 hover:bg-red-50/20 transition cursor-pointer flex items-center justify-between gap-4"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ item.tracking_token }}</span>
                                    <span 
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase"
                                        :class="statusBadgeConfig[item.status]?.badgeClass || 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ statusBadgeConfig[item.status]?.label || item.status }}
                                    </span>
                                </div>
                                <p class="text-xs font-semibold text-gray-800 truncate">{{ item.location }} — {{ item.subject || 'Concern' }}</p>
                                <p class="text-[11px] text-gray-500 truncate mt-0.5">{{ item.message }}</p>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-[10px] text-gray-400 block">{{ item.created_at_relative }}</span>
                                <span class="text-xs font-semibold text-red-600 hover:underline inline-flex items-center gap-1 mt-1">
                                    View
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Assistance Notice -->
            <div class="mt-6 text-center text-slate-400 text-xs flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                For immediate urgent public hazards, you may also visit the MEO office at Municipal Hall, Opol, Misamis Oriental.
            </div>
        </div>

        <!-- ==================== CANCEL CONCERN MODAL ==================== -->
        <div 
            v-if="showCancelModal" 
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="closeCancelModal"
        >
            <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden my-6">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-rose-600 to-red-600 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-white shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold">Request Concern Cancellation</h3>
                            <p class="text-[11px] text-rose-100">Subject to confirmation by MEO Staff / Admin</p>
                        </div>
                    </div>

                    <button 
                        @click="closeCancelModal"
                        :disabled="isCancelling"
                        class="p-1 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4 text-xs">
                    <!-- Tracking Badge -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-slate-500">Concern Reference Code</span>
                            <p class="font-mono font-bold text-slate-900 text-xs">{{ currentInquiry?.tracking_token }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] font-bold uppercase text-slate-500">Current Status</span>
                            <p class="font-bold text-xs" :class="currentInquiry?.status === 'accepted' ? 'text-emerald-700' : 'text-amber-700'">
                                {{ currentInquiry?.status === 'accepted' ? 'Accepted / Active' : 'Waiting for Review' }}
                            </p>
                        </div>
                    </div>

                    <p class="text-gray-600 leading-relaxed">
                        Please indicate the reason why you wish to cancel this reported concern. Your cancellation request will be submitted to the <strong>Municipal Engineering Office</strong> team to be reviewed and confirmed.
                    </p>

                    <!-- Reason Selection -->
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-2">
                            Select Reason for Cancellation <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <label 
                                v-for="(reason, rIdx) in presetReasons" 
                                :key="rIdx"
                                class="flex items-center gap-2.5 p-2.5 rounded-xl border transition cursor-pointer text-xs"
                                :class="selectedReasonPreset === reason ? 'border-rose-500 bg-rose-50/60 font-semibold text-rose-950' : 'border-gray-200 hover:bg-gray-50 text-gray-700'"
                            >
                                <input 
                                    type="radio" 
                                    :value="reason" 
                                    v-model="selectedReasonPreset" 
                                    class="text-rose-600 focus:ring-rose-500"
                                />
                                <span>{{ reason }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Additional Details / Custom Textarea -->
                    <div>
                        <label class="block font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-1.5">
                            Additional Details / Remarks <span class="text-gray-400 lowercase font-normal">(optional)</span>
                        </label>
                        <textarea
                            v-model="customCancelReason"
                            rows="3"
                            placeholder="Provide any additional explanation for the engineering office..."
                            class="w-full px-3.5 py-2 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none transition placeholder:text-gray-400 bg-white text-gray-900"
                        ></textarea>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="cancelError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700 font-medium">
                        {{ cancelError }}
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3.5 bg-gray-50 border-t border-gray-200 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        @click="closeCancelModal"
                        :disabled="isCancelling"
                        class="px-4 py-2 border border-gray-300 hover:bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl transition"
                    >
                        Keep Concern Active
                    </button>

                    <button
                        type="button"
                        @click="submitCancellationRequest"
                        :disabled="isCancelling"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 text-white text-xs font-bold rounded-xl transition shadow-md shadow-rose-900/20 disabled:opacity-50"
                    >
                        <svg v-if="isCancelling" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {{ isCancelling ? 'Submitting Request...' : 'Submit Cancellation Request' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Lightbox Modal -->
        <div 
            v-if="lightboxImage" 
            class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4"
            @click.self="closeLightbox"
        >
            <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center">
                <button 
                    @click="closeLightbox" 
                    class="absolute -top-10 right-0 p-2 text-white/80 hover:text-white bg-white/10 rounded-full transition"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <img :src="lightboxImage" alt="Enlarged site attachment" class="max-w-full max-h-[80vh] object-contain rounded-xl shadow-2xl" />
                <a :href="lightboxImage" target="_blank" class="mt-3 text-xs text-slate-300 hover:text-white underline">
                    Open original image in new tab
                </a>
            </div>
        </div>
    </div>
</template>