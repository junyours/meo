<template>
    <Head title="Concern Resolution - Ask MEO" />

    <div class="min-h-screen flex flex-col justify-between relative overflow-hidden py-10 px-4 sm:px-6 lg:px-8 bg-slate-950">
        <!-- Full Screen Blurred Background MEO Logo -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <img 
                src="/image/meo_logo2.png" 
                alt="MEO Watermark" 
                class="w-full h-full object-cover blur-[10px] scale-110 opacity-50 brightness-75 select-none"
            />
            <!-- Dark Ambience Overlays -->
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-[2px]"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>
        </div>

        <!-- Header / Brand Section -->
        <div class="relative z-10 max-w-3xl mx-auto w-full text-center mb-6">
            <Link href="/" class="inline-block group mb-3 focus:outline-none">
                <div class="relative flex items-center justify-center">
                    <div class="absolute -inset-1.5 bg-gradient-to-r from-blue-600 to-emerald-600 rounded-2xl blur opacity-40 group-hover:opacity-75 transition duration-300"></div>
                    <img 
                        src="/image/meo_logo2.png" 
                        alt="MEO Logo" 
                        class="w-16 h-16 rounded-2xl shadow-xl border border-white/20 object-cover relative transform group-hover:scale-105 transition duration-300"
                    />
                </div>
            </Link>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Ask MEO — Concern Resolution</h1>
            <p class="mt-1 text-xs sm:text-sm font-semibold text-blue-400">Municipal Engineering Office — Municipality of Opol</p>
            <p class="mt-1 text-xs text-slate-300 max-w-lg mx-auto">
                Official status notice regarding your submitted community inquiry and site observation report.
            </p>
        </div>

        <!-- Main Card: Resolution Notice -->
        <div class="relative z-10 max-w-3xl mx-auto w-full bg-white rounded-3xl shadow-2xl border border-white/20 overflow-hidden backdrop-blur-md">
            
            <!-- Top Banner Header -->
            <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-emerald-600 px-6 py-5 text-white">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white border border-white/30 shrink-0">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/20 text-white mb-0.5">
                                Official Notice of Resolution
                            </span>
                            <h2 class="text-lg sm:text-xl font-black">Concern Marked as Resolved</h2>
                        </div>
                    </div>

                    <div class="text-left sm:text-right font-mono">
                        <span class="text-[10px] uppercase text-blue-100 block">Tracking Reference</span>
                        <span class="text-sm font-bold bg-black/20 px-2.5 py-1 rounded-lg border border-white/20 inline-block">
                            {{ inquiry?.tracking_token || 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Body Details -->
            <div class="p-6 sm:p-8 space-y-6">
                
                <!-- Resolution Notice Banner -->
                <div class="bg-blue-50/80 border-2 border-blue-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-blue-600 text-white rounded-xl shadow-md shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-bold text-blue-950">Action Completed &amp; Verified</h3>
                            <p class="text-xs sm:text-sm text-blue-900 mt-1 leading-relaxed">
                                The Municipal Engineering Office (MEO) has reviewed, inspected, and completed the required engineering actions for this reported concern.
                            </p>
                            <div class="mt-3 pt-3 border-t border-blue-200/80 flex flex-wrap items-center gap-4 text-xs text-blue-800 font-medium">
                                <span v-if="inquiry?.resolved_at"><strong>Resolved On:</strong> {{ inquiry.resolved_at }}</span>
                                <span v-if="inquiry?.resolved_by_user" class="text-blue-950 font-bold bg-blue-100/80 px-2 py-0.5 rounded border border-blue-300">
                                    <strong>Inspected &amp; Resolved By:</strong> {{ inquiry.resolved_by_user.name }} ({{ inquiry.resolved_by_user.role?.toUpperCase() }})
                                </span>
                                <span v-else-if="inquiry?.accepted_by_user" class="text-emerald-900 font-semibold">
                                    <strong>Handled By:</strong> {{ inquiry.accepted_by_user.name }}
                                </span>
                                <span v-if="inquiry?.created_at"><strong>Submitted On:</strong> {{ inquiry.created_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MEO Engineering Remarks / Action Report -->
                <div v-if="inquiry?.admin_notes" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-xs text-emerald-950">
                    <div class="flex items-center gap-2 font-bold uppercase text-[11px] text-emerald-800 mb-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        MEO Resolution Remarks &amp; Action Taken
                    </div>
                    <p class="leading-relaxed whitespace-pre-line text-emerald-900 bg-white/70 p-3.5 rounded-xl border border-emerald-200">
                        {{ inquiry.admin_notes }}
                    </p>
                </div>

                <!-- Concern Details Card -->
                <div class="border border-gray-200 rounded-2xl p-5 bg-slate-50/60 space-y-4">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 pb-2">
                        Original Inquiry Summary
                    </h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="text-gray-500 font-medium block">Citizen Name</span>
                            <span class="font-bold text-gray-900 text-sm mt-0.5 block">{{ inquiry?.fullname }}</span>
                        </div>

                        <div>
                            <span class="text-gray-500 font-medium block">Contact Number</span>
                            <span class="font-bold text-blue-700 text-sm mt-0.5 block font-mono">{{ inquiry?.phone }}</span>
                        </div>

                        <div class="sm:col-span-2">
                            <span class="text-gray-500 font-medium block">Barangay / Location</span>
                            <span class="font-semibold text-red-600 mt-0.5 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                {{ inquiry?.location }}
                            </span>
                        </div>

                        <div class="sm:col-span-2" v-if="inquiry?.subject">
                            <span class="text-gray-500 font-medium block">Subject</span>
                            <span class="font-bold text-gray-900 mt-0.5 block">{{ inquiry?.subject }}</span>
                        </div>

                        <div class="sm:col-span-2">
                            <span class="text-gray-500 font-medium block">Concern Message</span>
                            <div class="mt-1 bg-white p-3.5 rounded-xl border border-gray-200 text-gray-800 leading-relaxed whitespace-pre-line text-xs">
                                {{ inquiry?.message }}
                            </div>
                        </div>

                        <!-- Multi-Photo Gallery (Up to 5 Photos) -->
                        <div class="sm:col-span-2" v-if="(inquiry?.photo_urls && inquiry?.photo_urls.length > 0) || inquiry?.photo_url">
                            <span class="text-gray-500 font-medium block mb-2">
                                Attached Site Photos ({{ inquiry?.photo_urls?.length || 1 }}):
                            </span>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                                <div 
                                    v-for="(photoUrl, pIdx) in (inquiry?.photo_urls?.length ? inquiry.photo_urls : [inquiry.photo_url])" 
                                    :key="pIdx"
                                    @click="openLightbox(photoUrl)"
                                    class="relative group rounded-xl overflow-hidden border border-gray-300 bg-black/5 aspect-video cursor-pointer shadow-sm"
                                >
                                    <img 
                                        :src="photoUrl" 
                                        alt="Evidence attachment" 
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-200"
                                    />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-semibold transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                                        Inspect
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <Link 
                        href="/" 
                        class="text-xs text-gray-500 hover:text-gray-900 transition flex items-center gap-1.5 font-medium w-full sm:w-auto justify-center sm:justify-start"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Return to Public Portal
                    </Link>

                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto justify-center">
                        <!-- Print Notice -->
                        <button 
                            @click="printNotice" 
                            class="px-4 py-2.5 border border-gray-300 hover:border-gray-400 bg-white text-gray-700 text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Notice
                        </button>

                        <!-- Submit Another Concern / Start Fresh -->
                        <button 
                            @click="startNewConcern"
                            class="px-5 py-2.5 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white text-xs font-bold rounded-xl transition shadow-md shadow-red-900/20 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Relay Concerns Again / Start Fresh
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Footer -->
        <div class="relative z-10 max-w-3xl mx-auto w-full text-center mt-6 text-xs text-slate-400">
            <p>&copy; {{ new Date().getFullYear() }} Municipal Engineering Office — Municipality of Opol. All rights reserved.</p>
        </div>

        <!-- Lightbox Modal for Photo Inspection -->
        <div 
            v-if="lightboxImage" 
            class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4"
            @click.self="closeLightbox"
        >
            <div class="relative max-w-4xl max-h-[90vh]">
                <button 
                    @click="closeLightbox" 
                    class="absolute -top-10 right-0 p-2 text-white hover:text-gray-300 transition"
                >
                    ✕ Close
                </button>
                <img :src="lightboxImage" alt="Inspection Attachment" class="max-w-full max-h-[80vh] object-contain rounded-2xl shadow-2xl" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    inquiry: {
        type: Object,
        required: true,
    },
});

const inquiry = ref(props.inquiry || {});
let pollTimer = null;

const checkLatestStatus = async () => {
    if (!inquiry.value?.tracking_token) return;
    if (typeof document !== 'undefined' && document.hidden) return;

    try {
        let checkUrl = '/ask-meo/check-status';
        if (typeof route === 'function' && route().has && route().has('ask.meo.check-status')) {
            checkUrl = route('ask.meo.check-status');
        }

        const res = await axios.post(checkUrl, {
            query: inquiry.value.tracking_token,
        });

        if (res.data?.success && Array.isArray(res.data.inquiries) && res.data.inquiries.length > 0) {
            const latest = res.data.inquiries[0];
            if (JSON.stringify(latest) !== JSON.stringify(inquiry.value)) {
                inquiry.value = latest;
            }
        }
    } catch (e) {
        // Silent error handling for background polling
    }
};

onMounted(() => {
    pollTimer = setInterval(checkLatestStatus, 5000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});

const lightboxImage = ref(null);
const openLightbox = (url) => {
    lightboxImage.value = url;
};
const closeLightbox = () => {
    lightboxImage.value = null;
};

const printNotice = () => {
    window.print();
};

const getResetUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo.reset')) {
            return route('ask.meo.reset');
        }
    } catch (e) {}
    return '/ask-meo/reset';
};

const getAskMeoUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('ask.meo')) {
            return route('ask.meo');
        }
    } catch (e) {}
    return '/ask-meo';
};

const startNewConcern = () => {
    router.post(getResetUrl(), {}, {
        onSuccess: () => {
            router.visit(getAskMeoUrl());
        },
        onError: () => {
            router.visit(getAskMeoUrl());
        },
    });
};
</script>
