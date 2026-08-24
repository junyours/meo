<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const activeSection = ref('slideshow'); // 'slideshow' | 'achievements'

const toast = ref({ show: false, message: '', type: 'success' });
let toastTimeout = null;

const showToast = (message, type = 'success') => {
    if (toastTimeout) clearTimeout(toastTimeout);
    toast.value = { show: true, message, type };
    toastTimeout = setTimeout(() => { toast.value.show = false; }, 3500);
};

// Welcome content record from database
const welcomeContent = ref({
    id: null,
    hero_title: 'Public Infrastructure\nTransparency Portal',
    hero_description: 'Track all municipal engineering projects in real-time. Every peso. Every milestone. Every deadline — open to all citizens.',
    hero_image: '',
    hero_background_image: '',
    additional_images: [],
    slideshow_images: [],
    achievement_images: [],
    is_active: true
});

const slideshowImages = ref([]);
const achievementImages = ref([]);

const isUploadingSlideshow = ref(false);
const isUploadingAchievement = ref(false);
const isSavingSlideshow = ref(false);
const isSavingAchievement = ref(false);

const slideshowFileInput = ref(null);
const achievementFileInput = ref(null);

const isDraggingSlideshow = ref(false);
const isDraggingAchievement = ref(false);

const previewImage = ref(null);
const editingAchievement = ref(null);

// Live preview carousel state for Hero Slideshow
const currentSlide = ref(0);
let slideshowTimer = null;

const startPreviewTimer = () => {
    stopPreviewTimer();
    if (slideshowImages.value.length > 1) {
        slideshowTimer = setInterval(() => {
            currentSlide.value = (currentSlide.value + 1) % slideshowImages.value.length;
        }, 4000);
    }
};

const stopPreviewTimer = () => {
    if (slideshowTimer) {
        clearInterval(slideshowTimer);
        slideshowTimer = null;
    }
};

const nextSlide = () => {
    if (slideshowImages.value.length > 0) {
        currentSlide.value = (currentSlide.value + 1) % slideshowImages.value.length;
    }
};

const prevSlide = () => {
    if (slideshowImages.value.length > 0) {
        currentSlide.value = (currentSlide.value - 1 + slideshowImages.value.length) % slideshowImages.value.length;
    }
};

// Filter category for achievement list
const achievementFilter = ref('all');
const achievementCategories = [
    { value: 'all', label: 'All Items' },
    { value: 'completed_project', label: 'Completed Projects' },
    { value: 'turnover', label: 'Turnovers & Inaugurations' },
    { value: 'achievement', label: 'Achievements & Awards' },
    { value: 'milestone', label: 'Key Milestones' },
];

const filteredAchievements = computed(() => {
    if (achievementFilter.value === 'all') return achievementImages.value;
    return achievementImages.value.filter(item => item.category === achievementFilter.value);
});

// Load content from API
const loadContent = async () => {
    try {
        const response = await window.axios.get('/superadmin/welcome-content');
        if (response.data) {
            welcomeContent.value = response.data;
            slideshowImages.value = Array.isArray(response.data.slideshow_images)
                ? [...response.data.slideshow_images]
                : [];
            achievementImages.value = Array.isArray(response.data.achievement_images)
                ? [...response.data.achievement_images]
                : [];
            currentSlide.value = 0;
            startPreviewTimer();
        }
    } catch (error) {
        console.error('Failed to load welcome content:', error);
        showToast('Failed to load portal content', 'error');
    }
};

// Save Hero Slideshow specifically
const saveSlideshow = async (silent = false) => {
    isSavingSlideshow.value = true;
    try {
        welcomeContent.value.slideshow_images = slideshowImages.value;

        const payload = {
            ...welcomeContent.value,
            slideshow_images: slideshowImages.value,
            achievement_images: achievementImages.value
        };

        if (welcomeContent.value.id) {
            await window.axios.put(`/superadmin/welcome-content/${welcomeContent.value.id}`, payload);
        } else {
            const response = await window.axios.post('/superadmin/welcome-content', payload);
            welcomeContent.value.id = response.data.id;
        }

        if (!silent) {
            showToast('Hero slideshow saved successfully');
        }
        startPreviewTimer();
    } catch (error) {
        console.error('Failed to save slideshow:', error);
        showToast('Failed to save hero slideshow', 'error');
    } finally {
        isSavingSlideshow.value = false;
    }
};

// Save Completed Projects & Achievements specifically
const saveAchievements = async (silent = false) => {
    isSavingAchievement.value = true;
    try {
        welcomeContent.value.achievement_images = achievementImages.value;

        const payload = {
            ...welcomeContent.value,
            slideshow_images: slideshowImages.value,
            achievement_images: achievementImages.value
        };

        if (welcomeContent.value.id) {
            await window.axios.put(`/superadmin/welcome-content/${welcomeContent.value.id}`, payload);
        } else {
            const response = await window.axios.post('/superadmin/welcome-content', payload);
            welcomeContent.value.id = response.data.id;
        }

        if (!silent) {
            showToast('Completed projects & achievements saved successfully');
        }
    } catch (error) {
        console.error('Failed to save achievements:', error);
        showToast('Failed to save completed projects & achievements', 'error');
    } finally {
        isSavingAchievement.value = false;
    }
};

// Upload Slideshow Files
const uploadSlideshowFiles = async (files) => {
    if (!files || files.length === 0) return;

    isUploadingSlideshow.value = true;
    let uploadCount = 0;

    for (const file of Array.from(files)) {
        if (!file.type.startsWith('image/')) {
            showToast(`Skipped ${file.name}: Not an image`, 'error');
            continue;
        }
        if (file.size > 5 * 1024 * 1024) {
            showToast(`Skipped ${file.name}: Exceeds 5MB limit`, 'error');
            continue;
        }

        const formData = new FormData();
        formData.append('image', file);
        formData.append('type', 'slideshow');

        try {
            const response = await window.axios.post('/superadmin/welcome-content/upload-image', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            const newImage = {
                id: Date.now() + Math.random(),
                url: response.data.url,
                type: 'slideshow',
                name: file.name
            };

            slideshowImages.value.push(newImage);
            uploadCount++;
        } catch (error) {
            console.error('Upload error for slideshow:', file.name, error);
            showToast(`Failed to upload ${file.name}`, 'error');
        }
    }

    isUploadingSlideshow.value = false;
    if (slideshowFileInput.value) slideshowFileInput.value.value = '';

    if (uploadCount > 0) {
        showToast(`Uploaded ${uploadCount} new slideshow image${uploadCount > 1 ? 's' : ''}`);
        await saveSlideshow(true);
    }
};

// Upload Achievements & Completed Projects Files
const uploadAchievementFiles = async (files) => {
    if (!files || files.length === 0) return;

    isUploadingAchievement.value = true;
    let uploadCount = 0;

    for (const file of Array.from(files)) {
        if (!file.type.startsWith('image/')) {
            showToast(`Skipped ${file.name}: Not an image`, 'error');
            continue;
        }
        if (file.size > 5 * 1024 * 1024) {
            showToast(`Skipped ${file.name}: Exceeds 5MB limit`, 'error');
            continue;
        }

        const formData = new FormData();
        formData.append('image', file);
        formData.append('type', 'achievement');

        try {
            const response = await window.axios.post('/superadmin/welcome-content/upload-image', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            // Derive clean title from filename
            const cleanTitle = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');

            const newItem = {
                id: Date.now() + Math.random(),
                url: response.data.url,
                type: 'achievement',
                title: cleanTitle.charAt(0).toUpperCase() + cleanTitle.slice(1),
                category: 'completed_project', // default category
                year: new Date().getFullYear().toString(),
                location: 'Municipality of Opol',
                caption: '',
            };

            achievementImages.value.unshift(newItem);
            uploadCount++;
        } catch (error) {
            console.error('Upload error for achievement:', file.name, error);
            showToast(`Failed to upload ${file.name}`, 'error');
        }
    }

    isUploadingAchievement.value = false;
    if (achievementFileInput.value) achievementFileInput.value.value = '';

    if (uploadCount > 0) {
        showToast(`Added ${uploadCount} completed project / achievement photo${uploadCount > 1 ? 's' : ''}`);
        await saveAchievements(true);
    }
};

// Reordering slideshow
const moveSlide = async (index, direction) => {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= slideshowImages.value.length) return;

    const items = [...slideshowImages.value];
    const [movedItem] = items.splice(index, 1);
    items.splice(newIndex, 0, movedItem);
    slideshowImages.value = items;

    showToast(`Reordered slide`, 'info');
    await saveSlideshow(true);
};

// Delete slideshow
const deleteSlide = async (id) => {
    if (!confirm('Remove this slide from the hero slideshow?')) return;

    slideshowImages.value = slideshowImages.value.filter(img => img.id !== id);
    if (currentSlide.value >= slideshowImages.value.length) {
        currentSlide.value = Math.max(0, slideshowImages.value.length - 1);
    }
    showToast('Slide removed');
    await saveSlideshow(true);
};

// Reordering achievements
const moveAchievement = async (index, direction) => {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= achievementImages.value.length) return;

    const items = [...achievementImages.value];
    const [movedItem] = items.splice(index, 1);
    items.splice(newIndex, 0, movedItem);
    achievementImages.value = items;

    showToast(`Reordered item`, 'info');
    await saveAchievements(true);
};

// Delete achievement item
const deleteAchievement = async (id) => {
    if (!confirm('Delete this photo from the completed projects & achievements compilation?')) return;

    achievementImages.value = achievementImages.value.filter(item => item.id !== id);
    if (editingAchievement.value?.id === id) {
        editingAchievement.value = null;
    }
    showToast('Photo removed from achievements compilation');
    await saveAchievements(true);
};

// Save edited achievement item
const saveEditedAchievement = async () => {
    if (!editingAchievement.value) return;

    const index = achievementImages.value.findIndex(item => item.id === editingAchievement.value.id);
    if (index !== -1) {
        achievementImages.value[index] = { ...editingAchievement.value };
    }
    editingAchievement.value = null;
    showToast('Achievement details updated');
    await saveAchievements(true);
};

onMounted(() => {
    loadContent();
});

onUnmounted(() => {
    stopPreviewTimer();
});
</script>

<template>
    <div class="w-full flex-1 space-y-6">
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
                :class="[
                    'fixed top-5 right-5 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium transition-all',
                    toast.type === 'error'
                        ? 'bg-red-50 border-red-200 text-red-800'
                        : toast.type === 'info'
                        ? 'bg-blue-50 border-blue-200 text-blue-800'
                        : 'bg-emerald-50 border-emerald-200 text-emerald-800'
                ]"
            >
                <svg v-if="toast.type === 'error'" class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else-if="toast.type === 'info'" class="w-5 h-5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ toast.message }}</span>
            </div>
        </transition>

        <!-- Header Card with Section Switcher -->
        <div class="bg-white border border-slate-200 p-5 sm:p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-1">
                    <div class="p-2 bg-red-50 text-red-600 border border-red-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2 class="text-lg sm:text-xl font-bold text-slate-900">Portal Media & Visuals Management</h2>
                </div>
                <p class="text-xs sm:text-sm text-slate-500">
                    Upload and manage hero background slideshows and the official compilation of completed infrastructure projects & achievements.
                </p>
            </div>

            <!-- Global Action & Section Switcher -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Tab switch -->
                <div class="inline-flex p-1 bg-slate-100 border border-slate-200">
                    <button
                        @click="activeSection = 'slideshow'"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                            activeSection === 'slideshow'
                                ? 'bg-white text-slate-900 shadow-sm border border-slate-200/60'
                                : 'text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Hero Slideshow</span>
                        <span class="px-1.5 py-0.2 bg-red-100 text-red-700 rounded-full text-[10px]">{{ slideshowImages.length }}</span>
                    </button>
                    <button
                        @click="activeSection = 'achievements'"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                            activeSection === 'achievements'
                                ? 'bg-white text-slate-900 shadow-sm border border-slate-200/60'
                                : 'text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                        <span>Completed Projects & Achievements</span>
                        <span class="px-1.5 py-0.2 bg-emerald-100 text-emerald-700 rounded-full text-[10px]">{{ achievementImages.length }}</span>
                    </button>
                </div>

                <!-- Separate Save Button for Slideshow -->
                <button
                    v-if="activeSection === 'slideshow'"
                    @click="saveSlideshow(false)"
                    :disabled="isSavingSlideshow || isUploadingSlideshow"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 active:bg-red-800 disabled:opacity-50 text-white rounded-lg text-xs font-bold transition-colors shadow-sm cursor-pointer shrink-0"
                >
                    <svg v-if="isSavingSlideshow" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ isSavingSlideshow ? 'Saving Slideshow...' : 'Save Slideshow' }}</span>
                </button>

                <!-- Separate Save Button for Achievements -->
                <button
                    v-else-if="activeSection === 'achievements'"
                    @click="saveAchievements(false)"
                    :disabled="isSavingAchievement || isUploadingAchievement"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-50 text-white rounded-lg text-xs font-bold transition-colors shadow-sm cursor-pointer shrink-0"
                >
                    <svg v-if="isSavingAchievement" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ isSavingAchievement ? 'Saving Achievements...' : 'Save Achievements' }}</span>
                </button>
            </div>
        </div>

        <!-- SECTION 1: HERO SLIDESHOW -->
        <div v-if="activeSection === 'slideshow'" class="space-y-6">
            <!-- Upload Dropzone for Slideshow -->
            <div class="bg-white border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Upload Hero Slideshow Images
                    </h3>
                    <span class="text-xs text-slate-400">Background slides for portal header</span>
                </div>

                <div
                    @dragover.prevent="isDraggingSlideshow = true"
                    @dragleave.prevent="isDraggingSlideshow = false"
                    @drop.prevent="isDraggingSlideshow = false; uploadSlideshowFiles($event.dataTransfer.files)"
                    :class="[
                        'relative border-2 border-dashed p-7 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-3',
                        isDraggingSlideshow
                            ? 'border-red-500 bg-red-50/50 scale-[0.99]'
                            : 'border-slate-300 bg-slate-50/50 hover:bg-white hover:border-slate-400'
                    ]"
                    @click="$refs.slideshowFileInput.click()"
                >
                    <input
                        ref="slideshowFileInput"
                        type="file"
                        multiple
                        accept="image/png,image/jpeg,image/jpg,image/webp"
                        class="hidden"
                        @change="uploadSlideshowFiles($event.target.files)"
                    />

                    <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center shadow-inner">
                        <svg v-if="!isUploadingSlideshow" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <svg v-else class="w-6 h-6 animate-spin text-red-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs sm:text-sm font-semibold text-slate-800">
                            <span class="text-red-600 underline">Click to upload</span> or drag and drop slideshow images here
                        </p>
                        <p class="text-[11px] text-slate-400">
                            PNG, JPG, JPEG, or WEBP (Max 5MB each). Multiple images supported.
                        </p>
                    </div>

                    <span
                        v-if="isUploadingSlideshow"
                        class="inline-flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full animate-pulse"
                    >
                        Uploading slide(s)...
                    </span>
                </div>
            </div>

            <!-- Live Preview Carousel Widget -->
            <div v-if="slideshowImages.length > 0" class="bg-white border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            Live Landing Page Banner Preview
                        </h3>
                        <span class="text-[11px] font-medium text-slate-400">(Cycles every 4s)</span>
                    </div>

                    <!-- Slide counter & navigation buttons -->
                    <div class="flex items-center gap-2">
                        <button
                            @click="prevSlide"
                            class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 transition-colors"
                            title="Previous slide"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <span class="text-xs font-bold text-slate-600 tabular-nums px-1">
                            {{ currentSlide + 1 }} / {{ slideshowImages.length }}
                        </span>
                        <button
                            @click="nextSlide"
                            class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 transition-colors"
                            title="Next slide"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Preview Screen Frame -->
                <div class="relative w-full h-56 sm:h-80 md:h-96 overflow-hidden bg-slate-900 border border-slate-200 shadow-inner group">
                    <template v-for="(img, idx) in slideshowImages" :key="'prev-' + img.id">
                        <div
                            v-show="currentSlide === idx"
                            class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                        >
                            <img
                                :src="img.url"
                                :alt="'Slide ' + (idx + 1)"
                                class="w-full h-full object-cover"
                            />
                            <!-- Dark gradient overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-black/30"></div>
                        </div>
                    </template>

                    <!-- Landing text mock simulation -->
                    <div class="absolute bottom-4 left-4 right-4 text-white z-10 pointer-events-none drop-shadow-md">
                        <span class="inline-block px-2 py-0.5 bg-red-600 text-white rounded text-[9px] font-bold uppercase tracking-wider mb-1">
                            Slide {{ currentSlide + 1 }} Active
                        </span>
                        <p class="text-xs sm:text-sm font-semibold opacity-90 truncate">Public Infrastructure Transparency Portal</p>
                    </div>

                    <!-- Indicator dots -->
                    <div class="absolute bottom-3 right-4 flex items-center gap-1.5 z-20">
                        <button
                            v-for="(_, idx) in slideshowImages"
                            :key="'dot-' + idx"
                            @click="currentSlide = idx"
                            :class="[
                                'h-1.5 rounded-full transition-all',
                                currentSlide === idx ? 'w-5 bg-red-500' : 'w-1.5 bg-white/60 hover:bg-white'
                            ]"
                        ></button>
                    </div>
                </div>
            </div>

            <!-- Slides List / Grid Section -->
            <div class="bg-white border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Current Slideshow Images
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Use the arrow buttons to reorder how images appear on the welcome page header.</p>
                    </div>

                    <button
                        @click="saveSlideshow(false)"
                        :disabled="isSavingSlideshow || isUploadingSlideshow"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-red-600 hover:bg-red-700 active:bg-red-800 disabled:opacity-50 text-white rounded-lg text-xs font-bold transition-colors shadow-sm cursor-pointer shrink-0"
                    >
                        <svg v-if="isSavingSlideshow" class="animate-spin -ml-0.5 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ isSavingSlideshow ? 'Saving...' : 'Save Slideshow' }}</span>
                    </button>
                </div>

                <!-- Empty State -->
                <div v-if="slideshowImages.length === 0" class="text-center py-12 px-4 border border-dashed border-slate-200 bg-slate-50/50">
                    <div class="mx-auto w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-slate-700 mb-1">No slideshow images yet</h4>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto mb-4">
                        Upload images above to create an automated background slideshow on the portal home page.
                    </p>
                    <button
                        @click="$refs.slideshowFileInput.click()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition-colors shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Upload First Slide
                    </button>
                </div>

                <!-- Slides Grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-4">
                    <div
                        v-for="(img, idx) in slideshowImages"
                        :key="img.id"
                        class="group relative border border-slate-200 bg-white overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col"
                    >
                        <div class="relative h-44 bg-slate-100 overflow-hidden">
                            <img
                                :src="img.url"
                                :alt="'Slide ' + (idx + 1)"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            />

                            <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5">
                                <span class="px-2 py-0.5 bg-black/75 backdrop-blur-md text-white text-[10px] font-bold rounded-md uppercase tracking-wider">
                                    Slide #{{ idx + 1 }}
                                </span>
                                <span v-if="idx === 0" class="px-1.5 py-0.5 bg-emerald-600 text-white text-[9px] font-bold rounded-md uppercase tracking-wider">
                                    First Slide
                                </span>
                            </div>

                            <button
                                @click="previewImage = img.url"
                                class="absolute top-2.5 right-2.5 p-1.5 bg-black/60 hover:bg-black/80 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-sm"
                                title="View full image"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-3 bg-white border-t border-slate-100 flex items-center justify-between gap-2 mt-auto">
                            <div class="flex items-center gap-1">
                                <button
                                    @click="moveSlide(idx, -1)"
                                    :disabled="idx === 0"
                                    class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                    title="Move Left"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                </button>
                                <button
                                    @click="moveSlide(idx, 1)"
                                    :disabled="idx === slideshowImages.length - 1"
                                    class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                    title="Move Right"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>

                            <button
                                @click="deleteSlide(img.id)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-red-600 hover:bg-red-50 rounded-lg text-xs font-semibold transition-colors"
                                title="Delete slide"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: COMPILATION OF COMPLETED PROJECTS & ACHIEVEMENTS -->
        <div v-else-if="activeSection === 'achievements'" class="space-y-6">
            <!-- Upload Dropzone for Achievements -->
            <div class="bg-white border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Upload Pictures for Completed Projects & Achievements Compilation
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Showcase completed infrastructure projects, official turnovers, awards, and engineering achievements.</p>
                    </div>
                </div>

                <div
                    @dragover.prevent="isDraggingAchievement = true"
                    @dragleave.prevent="isDraggingAchievement = false"
                    @drop.prevent="isDraggingAchievement = false; uploadAchievementFiles($event.dataTransfer.files)"
                    :class="[
                        'relative border-2 border-dashed p-8 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-3',
                        isDraggingAchievement
                            ? 'border-emerald-500 bg-emerald-50/50 scale-[0.99]'
                            : 'border-slate-300 bg-slate-50/50 hover:bg-white hover:border-slate-400'
                    ]"
                    @click="$refs.achievementFileInput.click()"
                >
                    <input
                        ref="achievementFileInput"
                        type="file"
                        multiple
                        accept="image/png,image/jpeg,image/jpg,image/webp"
                        class="hidden"
                        @change="uploadAchievementFiles($event.target.files)"
                    />

                    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-inner">
                        <svg v-if="!isUploadingAchievement" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <svg v-else class="w-6 h-6 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs sm:text-sm font-semibold text-slate-800">
                            <span class="text-emerald-600 underline">Click to upload photos</span> or drag & drop images here
                        </p>
                        <p class="text-[11px] text-slate-400">
                            Select one or multiple project/achievement photos (PNG, JPG, WEBP up to 5MB each).
                        </p>
                    </div>

                    <span
                        v-if="isUploadingAchievement"
                        class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-full animate-pulse"
                    >
                        Uploading photos...
                    </span>
                </div>
            </div>

            <!-- Achievement Items Gallery & Management -->
            <div class="bg-white border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Compilation Gallery
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Click the "Edit Details" button on any card to update title, location, category, or caption.</p>
                    </div>

                    <!-- Category Filter Buttons & Save Achievements Button -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                            <button
                                v-for="cat in achievementCategories"
                                :key="cat.value"
                                @click="achievementFilter = cat.value"
                                :class="[
                                    'px-3 py-1 rounded-lg text-xs font-semibold transition-all whitespace-nowrap',
                                    achievementFilter === cat.value
                                        ? 'bg-emerald-600 text-white shadow-sm'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                ]"
                            >
                                {{ cat.label }}
                            </button>
                        </div>

                        <button
                            @click="saveAchievements(false)"
                            :disabled="isSavingAchievement || isUploadingAchievement"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-50 text-white rounded-lg text-xs font-bold transition-colors shadow-sm cursor-pointer shrink-0 ml-auto sm:ml-0"
                        >
                            <svg v-if="isSavingAchievement" class="animate-spin -ml-0.5 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ isSavingAchievement ? 'Saving...' : 'Save Achievements' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredAchievements.length === 0" class="text-center py-12 px-4 border border-dashed border-slate-200 bg-slate-50/50">
                    <div class="mx-auto w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-slate-700 mb-1">No pictures in this compilation category</h4>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto mb-4">
                        Upload photos above to display completed municipal projects, ribbon cuttings, and official engineering achievements on the public portal.
                    </p>
                    <button
                        @click="$refs.achievementFileInput.click()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Upload Project Pictures
                    </button>
                </div>

                <!-- Gallery Cards Grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-4">
                    <div
                        v-for="(item, idx) in filteredAchievements"
                        :key="item.id"
                        class="group relative border border-slate-200 bg-white overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col"
                    >
                        <!-- Thumbnail -->
                        <div class="relative h-48 bg-slate-100 overflow-hidden">
                            <img
                                :src="item.url"
                                :alt="item.title || 'Completed Project'"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            />

                            <!-- Category Badge -->
                            <div class="absolute top-2.5 left-2.5 flex flex-col gap-1 items-start">
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-white text-[10px] font-bold rounded-md uppercase tracking-wider backdrop-blur-md shadow-sm',
                                        item.category === 'achievement'
                                            ? 'bg-amber-600/90'
                                            : item.category === 'turnover'
                                            ? 'bg-blue-600/90'
                                            : item.category === 'milestone'
                                            ? 'bg-purple-600/90'
                                            : 'bg-emerald-600/90'
                                    ]"
                                >
                                    {{
                                        item.category === 'achievement'
                                            ? 'Achievement'
                                            : item.category === 'turnover'
                                            ? 'Turnover'
                                            : item.category === 'milestone'
                                            ? 'Milestone'
                                            : 'Completed Project'
                                    }}
                                </span>
                                <span v-if="item.year" class="px-1.5 py-0.5 bg-black/70 text-white text-[9px] font-bold rounded">
                                    {{ item.year }}
                                </span>
                            </div>

                            <!-- Preview Zoom button -->
                            <button
                                @click="previewImage = item.url"
                                class="absolute top-2.5 right-2.5 p-1.5 bg-black/60 hover:bg-black/80 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-sm"
                                title="Zoom photo"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                                </svg>
                            </button>
                        </div>

                        <!-- Content info -->
                        <div class="p-3 flex-1 flex flex-col justify-between space-y-2">
                            <div>
                                <h4 class="text-xs font-bold text-slate-800 line-clamp-1 group-hover:text-emerald-700 transition-colors">
                                    {{ item.title || 'Untitled Picture' }}
                                </h4>
                                <p v-if="item.location" class="text-[10px] text-slate-500 flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="truncate">{{ item.location }}</span>
                                </p>
                                <p v-if="item.caption" class="text-[11px] text-slate-600 line-clamp-2 mt-1">
                                    {{ item.caption }}
                                </p>
                            </div>

                            <!-- Bottom card controls -->
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-1">
                                <button
                                    @click="editingAchievement = { ...item }"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 hover:bg-emerald-50 px-2 py-1 rounded transition-colors"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    <span>Edit</span>
                                </button>

                                <div class="flex items-center gap-1">
                                    <button
                                        @click="moveAchievement(idx, -1)"
                                        :disabled="idx === 0"
                                        class="p-1 rounded border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-30"
                                        title="Move Left"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    </button>
                                    <button
                                        @click="moveAchievement(idx, 1)"
                                        :disabled="idx === achievementImages.length - 1"
                                        class="p-1 rounded border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-30"
                                        title="Move Right"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                    </button>
                                    <button
                                        @click="deleteAchievement(item.id)"
                                        class="p-1 rounded text-red-500 hover:bg-red-50 transition-colors"
                                        title="Delete photo"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Achievement Item Modal -->
        <div
            v-if="editingAchievement"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click="editingAchievement = null"
        >
            <div
                class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-4 border border-slate-200"
                @click.stop
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Picture & Achievement Details
                    </h3>
                    <button
                        @click="editingAchievement = null"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Preview thumbnail -->
                <div class="h-36 overflow-hidden bg-slate-100 border border-slate-200">
                    <img :src="editingAchievement.url" alt="Edit preview" class="w-full h-full object-cover" />
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Project / Achievement Title</label>
                        <input
                            v-model="editingAchievement.title"
                            type="text"
                            placeholder="e.g. Multi-Purpose Gymnasium Turn-over"
                            class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Category</label>
                            <select
                                v-model="editingAchievement.category"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white"
                            >
                                <option value="completed_project">Completed Project</option>
                                <option value="turnover">Turnover / Inauguration</option>
                                <option value="achievement">Achievement & Award</option>
                                <option value="milestone">Key Milestone</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Year / Timeline</label>
                            <input
                                v-model="editingAchievement.year"
                                type="text"
                                placeholder="e.g. 2026 or FY 2026"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Location / Barangay</label>
                        <input
                            v-model="editingAchievement.location"
                            type="text"
                            placeholder="e.g. Barangay Poblacion, Opol"
                            class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Caption / Details (Optional)</label>
                        <textarea
                            v-model="editingAchievement.caption"
                            rows="3"
                            placeholder="Provide brief details or highlights of this completed work or milestone..."
                            class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="editingAchievement = null"
                        class="px-3.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveEditedAchievement"
                        class="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition-colors"
                    >
                        Apply Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Image Lightbox Modal -->
        <div
            v-if="previewImage"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm"
            @click="previewImage = null"
        >
            <div class="relative max-w-4xl max-h-[90vh] bg-slate-900 overflow-hidden shadow-2xl" @click.stop>
                <img :src="previewImage" alt="Zoom preview" class="w-full max-h-[85vh] object-contain" />
                <button
                    @click="previewImage = null"
                    class="absolute top-3 right-3 p-2 bg-black/60 hover:bg-black/90 text-white rounded-full transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
