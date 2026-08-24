<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { watch, computed, onMounted, ref } from 'vue';

const form = useForm({
    fullname: '',
    phone: '',
    phone_na: false,
    email: '',
    email_na: false,
    recaptcha_token: '',
    not_robot: false,
    subject: '',
    message: '',
});

const page = usePage();
const isSubmitting = ref(false);

watch(() => form.phone_na, (v) => { if (v) form.phone = '' });
watch(() => form.email_na, (v) => { if (v) form.email = '' });

const hasSuccess = computed(() => {
    return !!(page.props.value && page.props.value.flash && page.props.value.flash.success);
});

const hasErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) return resolve();
        const s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.defer = true;
        s.onload = () => resolve();
        s.onerror = () => reject(new Error('recaptcha load error'));
        document.head.appendChild(s);
    });
}

onMounted(async () => {
    if (!recaptchaSiteKey) return;
    try {
        await loadScript(`https://www.google.com/recaptcha/api.js?render=${recaptchaSiteKey}`);
    } catch (e) {
        console.warn('reCAPTCHA v3 failed to load', e);
    }
});

const submit = async () => {
    if (!form.fullname || !String(form.fullname).trim()) return;
    
    isSubmitting.value = true;

    if (recaptchaSiteKey && window.grecaptcha && window.grecaptcha.execute) {
        try {
            const token = await window.grecaptcha.execute(recaptchaSiteKey, { action: 'ask_meo' });
            form.recaptcha_token = token;
        } catch (e) {
            console.warn('grecaptcha.execute failed', e);
        }
    }

    form.post(route('ask.meo.send'), {
        onSuccess: () => {
            form.reset('subject', 'message', 'recaptcha_token', 'not_robot');
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const inputClass = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition placeholder:text-gray-400 disabled:bg-gray-50 disabled:text-gray-400 bg-white';
const labelClass = 'block text-sm font-medium text-gray-700 mb-1.5';
const errorClass = 'mt-1.5 text-xs text-red-600';
</script>

<template>
    <GuestLayout>
        <Head title="Ask MEO - Municipal Engineering Office" />

        <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <!-- Full Screen Blurred Background Logo -->
            <div class="absolute inset-0 z-0">
                <img 
                    src="/image/meo_logo2.png" 
                    alt="" 
                    class="w-full h-full object-cover blur-[8px] scale-110"
                />
            </div>

            <!-- Dark Overlay for Readability -->
            <div class="absolute inset-0 z-[1] bg-black/60"></div>

            <!-- Red Tint Overlay -->
            <div class="absolute inset-0 z-[1] bg-gradient-to-br from-red-900/30 via-transparent to-gray-900/50"></div>

            <div class="w-full max-w-2xl relative z-10">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center mb-4 relative">
                        <div class="absolute inset-0 bg-red-500 rounded-2xl blur-xl opacity-50"></div>
                        <img 
                            src="/image/meo_logo2.png" 
                            alt="MEO Logo" 
                            class="w-20 h-20 rounded-2xl shadow-2xl border-2 border-white/30 object-cover relative"
                        />
                    </div>
                    <h1 class="text-2xl font-bold text-white">Ask MEO</h1>
                    <p class="mt-2 text-sm text-gray-300">Municipal Engineering Office — Municipality of Opol</p>
                    <p class="mt-1 text-xs text-gray-400">Send us your inquiries, concerns, or feedback. We'll get back to you as soon as possible.</p>
                </div>

                <!-- Form Card -->
                <div class="bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 overflow-hidden">
                    <!-- Success Message -->
                    <div 
                        v-if="hasSuccess" 
                        class="bg-emerald-50 border-b border-emerald-200 px-6 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-emerald-800">Message Sent Successfully</p>
                                <p class="text-xs text-emerald-600 mt-0.5">{{ page.props.value.flash.success }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div 
                        v-if="hasErrors && !hasSuccess" 
                        class="bg-red-50 border-b border-red-200 px-6 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-red-800">Please correct the errors below</p>
                                <p class="text-xs text-red-600 mt-0.5">Some fields need your attention</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="p-6 space-y-5">
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
                                autofocus
                                placeholder="Enter your full name"
                                :class="[inputClass, form.errors.fullname ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : '']"
                            />
                            <p v-if="form.errors.fullname" :class="errorClass">{{ form.errors.fullname }}</p>
                        </div>

                        <!-- Phone & Email -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Phone -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="phone" class="text-sm font-medium text-gray-700">Phone Number</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            v-model="form.phone_na" 
                                            class="w-3.5 h-3.5 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                        />
                                        <span class="text-xs text-gray-500">N/A</span>
                                    </label>
                                </div>
                                <input
                                    id="phone"
                                    v-model="form.phone"
                                    type="tel"
                                    placeholder="+63 9XX XXX XXXX"
                                    :disabled="form.phone_na"
                                    :class="[inputClass, form.errors.phone ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : '']"
                                />
                                <p v-if="form.errors.phone" :class="errorClass">{{ form.errors.phone }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="email" class="text-sm font-medium text-gray-700">Email Address</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            v-model="form.email_na" 
                                            class="w-3.5 h-3.5 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                        />
                                        <span class="text-xs text-gray-500">N/A</span>
                                    </label>
                                </div>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="you@example.com"
                                    :disabled="form.email_na"
                                    :class="[inputClass, form.errors.email ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : '']"
                                />
                                <p v-if="form.errors.email" :class="errorClass">{{ form.errors.email }}</p>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label for="subject" :class="labelClass">Subject</label>
                            <input
                                id="subject"
                                v-model="form.subject"
                                type="text"
                                placeholder="What is this regarding?"
                                :class="[inputClass, form.errors.subject ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : '']"
                            />
                            <p v-if="form.errors.subject" :class="errorClass">{{ form.errors.subject }}</p>
                        </div>

                        <!-- Message -->
                        <div>
                            <label for="message" :class="labelClass">
                                Message <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="5"
                                placeholder="Describe your inquiry or concern in detail..."
                                :class="[inputClass, 'resize-none', form.errors.message ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : '']"
                            ></textarea>
                            <div class="flex justify-between mt-1.5">
                                <p v-if="form.errors.message" :class="errorClass">{{ form.errors.message }}</p>
                                <span class="text-xs text-gray-400 ml-auto">{{ form.message.length }} characters</span>
                            </div>
                        </div>

                        <!-- Verification -->
                        <div class="pt-2">
                            <div v-if="recaptchaSiteKey" id="recaptcha-container"></div>
                            <div v-else class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <input 
                                    id="not_robot" 
                                    type="checkbox" 
                                    v-model="form.not_robot" 
                                    class="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                />
                                <label for="not_robot" class="text-sm text-gray-600 cursor-pointer">
                                    I'm not a robot
                                 </label>
                            </div>
                            <p v-if="form.errors.not_robot || form.errors.recaptcha_token || form.errors.recaptcha || form.errors.recaptcha_response" :class="errorClass">
                                {{ form.errors.not_robot || form.errors.recaptcha_token || form.errors.recaptcha || form.errors.recaptcha_response }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <Link 
                                href="/" 
                                class="text-sm text-gray-500 hover:text-gray-700 transition flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Home
                            </Link>
                            
                            <button
                                type="submit"
                                :disabled="form.processing || !form.fullname?.trim() || (!recaptchaSiteKey && !form.not_robot)"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                            >
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                {{ form.processing ? 'Sending...' : 'Send Message' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Footer Info -->
                <div class="mt-6 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <img 
                            src="/image/meo_logo2.png" 
                            alt="MEO" 
                            class="w-5 h-5 rounded shadow-sm object-cover opacity-80"
                        />
                        <p class="text-xs text-gray-300">
                            You can also reach us at <span class="font-medium text-white">engineeringopol@gmail.com</span> or visit our office during business hours.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>