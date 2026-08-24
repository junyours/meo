<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showPasswordConfirm = ref(false);

const passwordStrength = computed(() => {
    const p = form.password;
    if (!p) return { score: 0, text: '', color: 'bg-gray-200' };
    let score = 0;
    if (p.length >= 8) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;

    if (score <= 1) return { score: 1, text: 'Weak', color: 'bg-rose-500', width: 'w-1/4' };
    if (score === 2) return { score: 2, text: 'Fair', color: 'bg-amber-500', width: 'w-2/4' };
    if (score === 3) return { score: 3, text: 'Good', color: 'bg-blue-500', width: 'w-3/4' };
    return { score: 4, text: 'Strong', color: 'bg-emerald-500', width: 'w-full' };
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password - MEO Portal" />

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden font-sans">
        <!-- Full Screen Blurred Background Logo -->
        <div class="absolute inset-0 z-0">
            <img 
                src="/image/meo_logo2.png" 
                alt="" 
                class="w-full h-full object-cover blur-[8px] scale-110"
            />
        </div>

        <!-- Dark Overlay -->
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
                <h2 class="mt-6 text-2xl font-bold text-white">Create New Password</h2>
                <p class="mt-2 text-sm text-gray-300">Set a secure password for your MEO account</p>
            </div>

            <!-- Card -->
            <div class="bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 overflow-hidden">
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Email Address
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            readonly
                            class="w-full px-3.5 py-2.5 bg-gray-100/80 border border-gray-200 rounded-xl text-sm text-gray-600 outline-none cursor-not-allowed"
                        />
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Enter new password (min. 8 characters)"
                                class="w-full pl-3.5 pr-10 py-2.5 bg-gray-50/50 border border-gray-300 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition placeholder:text-gray-400"
                                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': form.errors.password }"
                            />
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword" 
                                class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 transition"
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
                        <!-- Strength Meter -->
                        <div v-if="form.password" class="mt-2 space-y-1">
                            <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full transition-all duration-300" :class="[passwordStrength.color, passwordStrength.width]"></div>
                            </div>
                            <div class="flex justify-between text-[11px] text-gray-500">
                                <span>Strength: <strong :class="passwordStrength.color.replace('bg-', 'text-')">{{ passwordStrength.text }}</strong></span>
                                <span>Use 8+ letters, numbers & symbols</span>
                            </div>
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Confirm New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Re-enter new password"
                                class="w-full pl-3.5 pr-10 py-2.5 bg-gray-50/50 border border-gray-300 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition placeholder:text-gray-400"
                                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': form.errors.password_confirmation }"
                            />
                            <button 
                                type="button" 
                                @click="showPasswordConfirm = !showPasswordConfirm" 
                                class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 transition"
                            >
                                <svg v-if="!showPasswordConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="pt-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                        >
                            <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ form.processing ? 'Updating Password...' : 'Save & Reset Password' }}</span>
                        </button>
                    </div>
                </form>

                <div class="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                    <Link :href="route('login')" class="font-medium text-gray-600 hover:text-gray-900 transition flex items-center gap-1">
                        <svg class="w-3 h-3 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Cancel & Return to Login</span>
                    </Link>
                    <Link :href="route('ask.meo')" class="font-medium text-red-600 hover:text-red-700 transition">
                        Need Help?
                    </Link>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="mt-6 text-center text-xs text-gray-400">
                &copy; {{ new Date().getFullYear() }} Municipal Engineering Office — Municipality of Opol
            </div>
        </div>
    </div>
</template>
