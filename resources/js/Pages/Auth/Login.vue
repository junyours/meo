<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onUnmounted } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const isHelpModalOpen = ref(false);
const loadingText = ref('Signing in');
let interval = null;

// Start the loading animation with dots
const startLoadingAnimation = () => {
    let dots = 0;
    interval = setInterval(() => {
        dots = (dots % 3) + 1;
        loadingText.value = 'Signing in' + '.'.repeat(dots);
    }, 400);
};

// Stop the loading animation
const stopLoadingAnimation = () => {
    if (interval) {
        clearInterval(interval);
        interval = null;
    }
    loadingText.value = 'Signing in';
};

const submit = () => {
    if (form.processing) return;
    startLoadingAnimation();
    form.post(route('login'), {
        onFinish: () => {
            stopLoadingAnimation();
            form.reset('password');
        },
    });
};

// Clean up on component unmount
onUnmounted(() => {
    stopLoadingAnimation();
});
</script>

<template>
    <Head title="Log in - MEO Portal" />

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
        <div class="absolute inset-0 z-[1] bg-black/65"></div>

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
                <h2 class="mt-6 text-2xl font-bold text-white">Welcome Back</h2>
                <p class="mt-2 text-sm text-gray-300">Sign in to Municipal Engineering Office Portal</p>
                <p class="mt-1 text-xs text-gray-400">Secure access for staff and authorized users</p>
            </div>

            <!-- Login Card -->
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

                <!-- Form -->
                <form @submit.prevent="submit" class="p-6 space-y-5">
                    <!-- Email -->
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
                                placeholder="e.g. yourname@meo.gov.ph"
                                class="w-full pl-10 pr-4 py-2.5 bg-gray-50/50 border border-gray-300 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition placeholder:text-gray-400"
                                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': form.errors.email }"
                            />
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <Link
                                :href="route('password.request')"
                                class="text-xs font-medium text-red-600 hover:text-red-700 hover:underline transition"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-gray-400 absolute left-3.5 top-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your account password"
                                class="w-full pl-10 pr-10 py-2.5 bg-gray-50/50 border border-gray-300 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition placeholder:text-gray-400"
                                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': form.errors.password }"
                            />
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword" 
                                class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 transition"
                                title="Toggle password visibility"
                            >
                                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <!-- Remember Me & Account Recovery -->
                    <div class="flex items-center justify-between text-xs sm:text-sm">
                        <label class="flex items-center cursor-pointer">
                            <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
                            <span class="ml-2 text-gray-600">Remember me</span>
                        </label>

                        <button
                            type="button"
                            @click="isHelpModalOpen = true"
                            class="text-xs text-gray-500 hover:text-gray-900 transition underline decoration-dotted"
                        >
                            Forgot account?
                        </button>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                        >
                            <!-- Lock Icon -->
                            <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            
                            <!-- Spinner -->
                            <svg v-else class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            
                            <span>{{ form.processing ? loadingText : 'Sign In' }}</span>
                        </button>
                    </div>
                </form>

                <!-- Footer -->
                <div class="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                    <button 
                        type="button" 
                        @click="isHelpModalOpen = true" 
                        class="hover:text-gray-800 transition"
                    >
                        Account Assistance
                    </button>
                    <Link :href="route('ask.meo')" class="font-medium text-red-600 hover:text-red-700 transition">
                        Need Help? Contact MEO
                    </Link>
                </div>
            </div>

            <!-- Bottom Info -->
            <div class="mt-6 text-center">
                <div class="flex items-center justify-center gap-2">
                    <img 
                        src="/image/meo_logo2.png" 
                        alt="MEO" 
                        class="w-5 h-5 rounded shadow-sm object-cover opacity-80"
                    />
                    <p class="text-xs text-gray-300">
                        Municipal Engineering Office — Municipality of Opol
                    </p>
                </div>
                <p class="mt-1 text-xs text-gray-400">
                    &copy; {{ new Date().getFullYear() }} MEO. All rights reserved.
                </p>
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
                            <h3 class="text-base font-bold text-gray-900">Forgot Your Account?</h3>
                            <p class="text-xs text-gray-500">Account assistance for MEO staff and administrators</p>
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
                    <div class="flex gap-3 p-3.5 rounded-xl bg-red-50/80 border border-red-200/80 text-red-950">
                        <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-xs leading-relaxed">
                            <span class="font-bold">Official Municipal System:</span> All employee and administrative accounts are created and managed by the Municipal Engineering Office IT Division.
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="border border-gray-200 rounded-xl p-3.5 hover:border-gray-300 transition">
                            <div class="font-semibold text-gray-900 text-xs uppercase tracking-wider mb-1">1. Forgot your registered email address?</div>
                            <p class="text-xs text-gray-500">
                                Contact the Super Administrator or IT Office with your employee name and station to verify your registered email.
                            </p>
                        </div>

                        <div class="border border-gray-200 rounded-xl p-3.5 hover:border-gray-300 transition">
                            <div class="font-semibold text-gray-900 text-xs uppercase tracking-wider mb-1">2. Forgot your password?</div>
                            <p class="text-xs text-gray-500 mb-2">
                                If you know your registered email, use the automatic password reset tool to receive a reset link.
                            </p>
                            <Link 
                                :href="route('password.request')" 
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 hover:text-red-700"
                            >
                                <span>Go to Reset Password Tool</span>
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
                                <p><span class="font-medium text-gray-700">Inquiry Form:</span> <Link :href="route('ask.meo')" class="text-red-600 underline">Ask MEO</Link></p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button 
                            type="button" 
                            @click="isHelpModalOpen = false" 
                            class="w-full py-2.5 text-xs sm:text-sm font-semibold bg-gray-900 hover:bg-black text-white rounded-xl transition shadow-xs"
                        >
                            Got It
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>