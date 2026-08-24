<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onUnmounted } from 'vue';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const isHelpModalOpen = ref(false);
const loadingText = ref('Sending Link');
let interval = null;

const startLoadingAnimation = () => {
    let dots = 0;
    interval = setInterval(() => {
        dots = (dots % 3) + 1;
        loadingText.value = 'Sending Link' + '.'.repeat(dots);
    }, 400);
};

const stopLoadingAnimation = () => {
    if (interval) {
        clearInterval(interval);
        interval = null;
    }
    loadingText.value = 'Sending Link';
};

const submit = () => {
    if (form.processing) return;
    startLoadingAnimation();
    form.post(route('password.email'), {
        onFinish: () => {
            stopLoadingAnimation();
        },
    });
};

onUnmounted(() => {
    stopLoadingAnimation();
});
</script>

<template>
    <Head title="Forgot Password - MEO Portal" />

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden font-sans">
        <!-- Full Screen Blurred Background Logo -->
        <div class="absolute inset-0 z-0">
            <img 
                src="/image/meo_logo2.png" 
                alt="" 
                class="w-full h-full object-cover blur-[8px] scale-110"
            />
        </div>

        <!-- Dark Overlay for Readability -->
        <div class="absolute inset-0 z-[1] bg-black/70"></div>

        <!-- Red Tint Overlay -->
        <div class="absolute inset-0 z-[1] bg-gradient-to-br from-red-900/40 via-transparent to-gray-900/60"></div>

        <!-- Subtle Pattern Overlay -->
        <div class="absolute inset-0 z-[1] opacity-10">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 25px 25px, rgba(255,255,255,0.2) 2px, transparent 0); background-size: 50px 50px;"></div>
        </div>

        <div class="w-full max-w-md relative z-10">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center relative">
                    <div class="absolute inset-0 bg-red-500 rounded-2xl blur-xl opacity-50"></div>
                    <img 
                        class="h-20 w-20 rounded-2xl shadow-2xl border-2 border-white/30 object-cover relative" 
                        src="/image/meo_logo2.png" 
                        alt="MEO logo" 
                    />
                </div>
                <h2 class="mt-6 text-2xl font-bold text-white">Reset Account Password</h2>
                <p class="mt-2 text-sm text-gray-300">Municipal Engineering Office Portal</p>
            </div>

            <!-- Card -->
            <div class="bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 overflow-hidden">
                <!-- Status Message -->
                <div v-if="status" class="bg-emerald-50 border-b border-emerald-200 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0 w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-emerald-800">{{ status }}</p>
                    </div>
                </div>

                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-5 leading-relaxed">
                        Enter your registered email address and we'll send you a secure password reset link to choose a new password.
                    </p>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-gray-400 absolute left-3.5 top-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="Enter your registered email"
                                    class="w-full pl-10 pr-4 py-2.5 bg-gray-50/50 border border-gray-300 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition placeholder:text-gray-400"
                                    :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': form.errors.email }"
                                />
                            </div>
                            <InputError class="mt-1.5" :message="form.errors.email" />
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                            >
                                <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <svg v-else class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ form.processing ? loadingText : 'Send Reset Link' }}</span>
                            </button>
                        </div>
                    </form>

                    <!-- Forgot Account Helpers -->
                    <div class="mt-6 pt-5 border-t border-gray-100 flex flex-col gap-3">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <button 
                                type="button"
                                @click="isHelpModalOpen = true"
                                class="inline-flex items-center gap-1 font-medium text-red-600 hover:text-red-700 hover:underline transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Forgot your email or account ID?</span>
                            </button>

                            <Link 
                                :href="route('login')" 
                                class="font-medium text-gray-600 hover:text-gray-900 transition flex items-center gap-1"
                            >
                                <span>Back to Sign In</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Footer Support -->
                <div class="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                    <span>Municipal Engineering Portal</span>
                    <Link :href="route('ask.meo')" class="font-medium text-red-600 hover:text-red-700 transition">
                        Help & Support
                    </Link>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="mt-6 text-center text-xs text-gray-400">
                &copy; {{ new Date().getFullYear() }} Municipal Engineering Office — Municipality of Opol
            </div>
        </div>

        <!-- Account Recovery & Help Modal -->
        <div v-if="isHelpModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-200/80 w-full max-w-lg overflow-hidden animate-fade-in">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/80">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Account Recovery Assistance</h3>
                            <p class="text-xs text-gray-500">Official options for retrieving your MEO account</p>
                        </div>
                    </div>
                    <button 
                        @click="isHelpModalOpen = false" 
                        class="text-gray-400 hover:text-gray-600 p-1.5 hover:bg-gray-100 rounded-xl transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-sm text-gray-600">
                    <div class="flex gap-3 p-3.5 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-900">
                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-xs leading-relaxed">
                            <span class="font-bold">Staff & Administrator Accounts:</span> User accounts are provisioned by the Municipal Administrator. If you cannot remember your official email address, follow the recovery channels below.
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="border border-gray-200 rounded-xl p-3.5 hover:border-gray-300 transition">
                            <div class="font-semibold text-gray-900 text-xs uppercase tracking-wider mb-1">Option 1: Contact System Administrator</div>
                            <p class="text-xs text-gray-500">
                                Visit the Municipal Engineering Office IT Administrator desk or ask your department head to look up or reset your login credentials.
                            </p>
                        </div>

                        <div class="border border-gray-200 rounded-xl p-3.5 hover:border-gray-300 transition">
                            <div class="font-semibold text-gray-900 text-xs uppercase tracking-wider mb-1">Option 2: Submit an Inquiry via Ask MEO</div>
                            <p class="text-xs text-gray-500 mb-2">
                                Send a message describing your name, department, and concern through our official inquiry desk.
                            </p>
                            <Link 
                                :href="route('ask.meo')" 
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 hover:text-red-700"
                            >
                                <span>Go to Ask MEO Form</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                        </div>

                        <div class="border border-gray-200 rounded-xl p-3.5 bg-gray-50/50">
                            <div class="font-semibold text-gray-900 text-xs uppercase tracking-wider mb-1">Office Contact Details</div>
                            <div class="text-xs text-gray-600 space-y-0.5">
                                <p><span class="font-medium text-gray-700">Office:</span> Municipal Engineering Office, Opol Municipal Hall</p>
                                <p><span class="font-medium text-gray-700">Email:</span> meo@opol.gov.ph</p>
                                <p><span class="font-medium text-gray-700">Hours:</span> Monday – Friday, 8:00 AM – 5:00 PM</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button 
                            type="button" 
                            @click="isHelpModalOpen = false" 
                            class="w-full py-2.5 text-xs sm:text-sm font-semibold bg-gray-900 hover:bg-black text-white rounded-xl transition shadow-xs"
                        >
                            Close Help
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
