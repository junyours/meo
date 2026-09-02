<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

// ================= STATE =================
const activeSection = ref('slideshow'); // 'slideshow' | 'achievements'

const toast = ref({ show: false, message: '', type: 'success' });
let toastTimeout = null;

const showToast = (message, type = 'success') => {
    if (toastTimeout) clearTimeout(toastTimeout);
    toast.value = { show: true, message, type };
    toastTimeout = setTimeout(() => { toast.value.show = false; }, 3500);
};

// Welcome content model
const welcomeContent = ref({
    id: null,
    hero_title: 'Public Infrastructure\nTransparency Portal',
    hero_description: 'Track all municipal engineering projects in real-time. Every peso. Every milestone. Every deadline — open to all citizens.',
    hero_image: '',
    hero_background_image: '',
    additional_images: [],
    slideshow_images: [],
    achievement_images: [],
    is_active: true,
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
        }, 4500);
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
    { value: 'turnover', label: 'Turnovers' },
    { value: 'achievement', label: 'Achievements' },
    { value: 'milestone', label: 'Milestones' },
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
        showToast('Failed to load portal media content', 'error');
    }
};

// Save Hero Slideshow
const saveSlideshow = async (silent = false) => {
    isSavingSlideshow.value = true;
    try {
        welcomeContent.value.slideshow_images = slideshowImages.value;

        const payload = {
            ...welcomeContent.value,
            slideshow_images: slideshowImages.value,
            achievement_images: achievementImages.value,
        };

        if (welcomeContent.value.id) {
            await window.axios.put(`/superadmin/welcome-content/${welcomeContent.value.id}`, payload);
        } else {
            const response = await window.axios.post('/superadmin/welcome-content', payload);
            welcomeContent.value.id = response.data.id;
        }

        if (!silent) {
            showToast('Hero slideshow updated successfully');
        }
        startPreviewTimer();
    } catch (error) {
        console.error('Failed to save slideshow:', error);
        showToast('Failed to save hero slideshow', 'error');
    } finally {
        isSavingSlideshow.value = false;
    }
};

// Save Achievements
const saveAchievements = async (silent = false) => {
    isSavingAchievement.value = true;
    try {
        welcomeContent.value.achievement_images = achievementImages.value;

        const payload = {
            ...welcomeContent.value,
            slideshow_images: slideshowImages.value,
            achievement_images: achievementImages.value,
        };

        if (welcomeContent.value.id) {
            await window.axios.put(`/superadmin/welcome-content/${welcomeContent.value.id}`, payload);
        } else {
            const response = await window.axios.post('/superadmin/welcome-content', payload);
            welcomeContent.value.id = response.data.id;
        }

        if (!silent) {
            showToast('Projects and achievements updated successfully');
        }
    } catch (error) {
        console.error('Failed to save achievements:', error);
        showToast('Failed to save achievements', 'error');
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
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const newImage = {
                id: Date.now() + Math.random(),
                url: response.data.url,
                type: 'slideshow',
                name: file.name,
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
        showToast(`Uploaded ${uploadCount} slide image${uploadCount > 1 ? 's' : ''}`);
        await saveSlideshow(true);
    }
};

// Upload Achievement Files
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
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const cleanTitle = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');

            const newItem = {
                id: Date.now() + Math.random(),
                url: response.data.url,
                type: 'achievement',
                title: cleanTitle.charAt(0).toUpperCase() + cleanTitle.slice(1),
                category: 'completed_project',
                year: new Date().getFullYear().toString(),
                location: 'Municipality of Opol',
                caption: '',
            };

            achievementImages.value.push(newItem);
            uploadCount++;
        } catch (error) {
            console.error('Upload error for achievement:', file.name, error);
            showToast(`Failed to upload ${file.name}`, 'error');
        }
    }

    isUploadingAchievement.value = false;
    if (achievementFileInput.value) achievementFileInput.value.value = '';

    if (uploadCount > 0) {
        showToast(`Uploaded ${uploadCount} project photo${uploadCount > 1 ? 's' : ''}`);
        await saveAchievements(true);
    }
};

// Reorder slide
const moveSlide = async (index, direction) => {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= slideshowImages.value.length) return;

    const items = [...slideshowImages.value];
    const [movedItem] = items.splice(index, 1);
    items.splice(newIndex, 0, movedItem);
    slideshowImages.value = items;

    showToast('Slide reordered');
    await saveSlideshow(true);
};

// Delete slide
const deleteSlide = async (id) => {
    slideshowImages.value = slideshowImages.value.filter(img => img.id !== id);
    if (currentSlide.value >= slideshowImages.value.length) {
        currentSlide.value = Math.max(0, slideshowImages.value.length - 1);
    }
    showToast('Slide removed');
    await saveSlideshow(true);
};

// Reorder achievement
const moveAchievement = async (index, direction) => {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= achievementImages.value.length) return;

    const items = [...achievementImages.value];
    const [movedItem] = items.splice(index, 1);
    items.splice(newIndex, 0, movedItem);
    achievementImages.value = items;

    showToast('Item reordered');
    await saveAchievements(true);
};

// Delete achievement
const deleteAchievement = async (id) => {
    achievementImages.value = achievementImages.value.filter(item => item.id !== id);
    if (editingAchievement.value?.id === id) {
        editingAchievement.value = null;
    }
    showToast('Photo removed from achievements');
    await saveAchievements(true);
};

// Save edited achievement
const saveEditedAchievement = async () => {
    if (!editingAchievement.value) return;

    const index = achievementImages.value.findIndex(item => item.id === editingAchievement.value.id);
    if (index !== -1) {
        achievementImages.value[index] = { ...editingAchievement.value };
    }
    editingAchievement.value = null;
    showToast('Project details updated');
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
    <div class="w-full space-y-5">
        
        <!-- Toast Feedback -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast.show"
                :class="[
                    'fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-lg shadow-lg border text-sm font-medium flex items-center gap-2.5',
                    toast.type === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-slate-900 text-white border-slate-800'
                ]"
            >
                <i :class="toast.type === 'error' ? 'ri-error-warning-line' : 'ri-check-line'"></i>
                <span>{{ toast.message }}</span>
            </div>
        </transition>

        <!-- 1. CLEAN TOP HEADER -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 tracking-tight">Portal Media & Visuals</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Manage public landing page hero carousel slides and completed infrastructure achievements.
                </p>
            </div>

            <!-- Save Action Button -->
            <div>
                <button
                    v-if="activeSection === 'slideshow'"
                    @click="saveSlideshow(false)"
                    :disabled="isSavingSlideshow || isUploadingSlideshow"
                    class="w-full sm:w-auto px-4 py-2 bg-gray-900 hover:bg-gray-800 active:bg-black text-white rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-xs"
                >
                    <i :class="['text-sm', isSavingSlideshow ? 'ri-loader-4-line animate-spin' : 'ri-save-line']"></i>
                    <span>{{ isSavingSlideshow ? 'Saving Slides...' : 'Save Slideshow' }}</span>
                </button>

                <button
                    v-else
                    @click="saveAchievements(false)"
                    :disabled="isSavingAchievement || isUploadingAchievement"
                    class="w-full sm:w-auto px-4 py-2 bg-gray-900 hover:bg-gray-800 active:bg-black text-white rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-xs"
                >
                    <i :class="['text-sm', isSavingAchievement ? 'ri-loader-4-line animate-spin' : 'ri-save-line']"></i>
                    <span>{{ isSavingAchievement ? 'Saving Gallery...' : 'Save Achievements' }}</span>
                </button>
            </div>
        </div>

        <!-- 2. DEDICATED PROFESSIONAL TAB BAR -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-xs overflow-hidden">
            <div class="flex border-b border-gray-200 overflow-x-auto">
                <!-- Tab 1: Hero Slideshow -->
                <button
                    @click="activeSection = 'slideshow'"
                    :class="[
                        'flex-1 min-w-[200px] sm:min-w-[240px] px-5 py-3.5 text-left border-b-2 transition-all flex items-center gap-3 cursor-pointer group',
                        activeSection === 'slideshow'
                            ? 'border-red-600 bg-red-50/20 text-gray-900'
                            : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-50/70'
                    ]"
                >
                    <div
                        :class="[
                            'w-9 h-9 rounded-lg flex items-center justify-center text-base transition-colors shrink-0',
                            activeSection === 'slideshow'
                                ? 'bg-red-100 text-red-600'
                                : 'bg-gray-100 text-gray-400 group-hover:bg-gray-200 group-hover:text-gray-600'
                        ]"
                    >
                        <i class="ri-slideshow-3-line"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span :class="['text-sm font-semibold truncate', activeSection === 'slideshow' ? 'text-gray-900 font-bold' : 'text-gray-600']">
                                Hero Slideshow
                            </span>
                            <span
                                :class="[
                                    'px-2 py-0.2 text-[10px] font-semibold rounded-full font-mono',
                                    activeSection === 'slideshow'
                                        ? 'bg-red-100 text-red-700'
                                        : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                {{ slideshowImages.length }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-400 truncate">Landing page carousel background</p>
                    </div>
                </button>

                <!-- Tab 2: Achievements & Completed Projects -->
                <button
                    @click="activeSection = 'achievements'"
                    :class="[
                        'flex-1 min-w-[220px] sm:min-w-[260px] px-5 py-3.5 text-left border-b-2 transition-all flex items-center gap-3 cursor-pointer group',
                        activeSection === 'achievements'
                            ? 'border-emerald-600 bg-emerald-50/20 text-gray-900'
                            : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-50/70'
                    ]"
                >
                    <div
                        :class="[
                            'w-9 h-9 rounded-lg flex items-center justify-center text-base transition-colors shrink-0',
                            activeSection === 'achievements'
                                ? 'bg-emerald-100 text-emerald-600'
                                : 'bg-gray-100 text-gray-400 group-hover:bg-gray-200 group-hover:text-gray-600'
                        ]"
                    >
                        <i class="ri-medal-line"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span :class="['text-sm font-semibold truncate', activeSection === 'achievements' ? 'text-gray-900 font-bold' : 'text-gray-600']">
                                Achievements & Gallery
                            </span>
                            <span
                                :class="[
                                    'px-2 py-0.2 text-[10px] font-semibold rounded-full font-mono',
                                    activeSection === 'achievements'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                {{ achievementImages.length }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-400 truncate">Completed projects compilation</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- ================= SECTION 1: HERO SLIDESHOW ================= -->
        <div v-if="activeSection === 'slideshow'" class="space-y-5">
            
            <!-- Live Preview Carousel Banner -->
            <div v-if="slideshowImages.length > 0" class="bg-white border border-gray-200 rounded-xl p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Live Header Preview</h2>
                        <p class="text-xs text-gray-500">Simulates how slides transition on the citizen portal header</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="prevSlide"
                            class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors cursor-pointer"
                            title="Previous slide"
                        >
                            <i class="ri-arrow-left-s-line text-sm"></i>
                        </button>
                        <span class="text-xs font-mono text-gray-600 font-medium px-1">
                            {{ currentSlide + 1 }} / {{ slideshowImages.length }}
                        </span>
                        <button
                            @click="nextSlide"
                            class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors cursor-pointer"
                            title="Next slide"
                        >
                            <i class="ri-arrow-right-s-line text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Preview Frame -->
                <div class="relative w-full h-56 sm:h-72 md:h-80 rounded-lg overflow-hidden bg-slate-900 border border-gray-200 group">
                    <template v-for="(img, idx) in slideshowImages" :key="'prev-' + img.id">
                        <div
                            v-show="currentSlide === idx"
                            class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                        >
                            <img :src="img.url" :alt="'Slide ' + (idx + 1)" class="w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-black/30"></div>
                        </div>
                    </template>

                    <!-- Mock Caption -->
                    <div class="absolute bottom-4 left-4 right-4 text-white z-10 pointer-events-none">
                        <span class="inline-block px-2 py-0.5 bg-red-600 text-white rounded text-[10px] font-semibold uppercase tracking-wider mb-1">
                            Active Slide {{ currentSlide + 1 }}
                        </span>
                        <p class="text-sm font-semibold opacity-95">Public Infrastructure Transparency Portal</p>
                    </div>

                    <!-- Slide dots -->
                    <div class="absolute bottom-3 right-4 flex items-center gap-1.5 z-20">
                        <button
                            v-for="(_, idx) in slideshowImages"
                            :key="'dot-' + idx"
                            @click="currentSlide = idx"
                            :class="[
                                'h-1.5 rounded-full transition-all cursor-pointer',
                                currentSlide === idx ? 'w-5 bg-red-500' : 'w-1.5 bg-white/60 hover:bg-white'
                            ]"
                        ></button>
                    </div>
                </div>
            </div>

            <!-- Upload Dropzone -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-xs">
                <div
                    @dragover.prevent="isDraggingSlideshow = true"
                    @dragleave.prevent="isDraggingSlideshow = false"
                    @drop.prevent="isDraggingSlideshow = false; uploadSlideshowFiles($event.dataTransfer.files)"
                    :class="[
                        'relative border-2 border-dashed rounded-lg p-6 sm:p-8 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-2.5',
                        isDraggingSlideshow
                            ? 'border-red-500 bg-red-50/50'
                            : 'border-gray-300 bg-gray-50/50 hover:bg-white hover:border-gray-400'
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

                    <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                        <i :class="['text-xl', isUploadingSlideshow ? 'ri-loader-4-line animate-spin' : 'ri-upload-cloud-2-line']"></i>
                    </div>

                    <div class="space-y-0.5">
                        <p class="text-xs sm:text-sm font-semibold text-gray-800">
                            <span class="text-red-600 hover:underline">Click to upload</span> or drag and drop slide images
                        </p>
                        <p class="text-[11px] text-gray-400">
                            Supports PNG, JPG, JPEG, or WEBP (Max 5MB each). Multiple images allowed.
                        </p>
                    </div>

                    <span
                        v-if="isUploadingSlideshow"
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-full animate-pulse mt-1"
                    >
                        Uploading slide images...
                    </span>
                </div>
            </div>

            <!-- Slides Grid Section -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Current Slide Deck ({{ slideshowImages.length }})</h2>
                        <p class="text-xs text-gray-500">Order from left to right corresponds to the display sequence</p>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="slideshowImages.length === 0" class="text-center py-10 px-4 border border-dashed border-gray-200 rounded-lg bg-gray-50/50">
                    <i class="ri-image-line text-2xl text-gray-400 block mb-1"></i>
                    <p class="text-xs font-semibold text-gray-700">No slides configured</p>
                    <p class="text-[11px] text-gray-400 mt-0.5 mb-3">Upload images above to activate the hero carousel.</p>
                    <button
                        @click="$refs.slideshowFileInput.click()"
                        class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium transition-colors cursor-pointer"
                    >
                        Upload Slide
                    </button>
                </div>

                <!-- Cards Grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-3.5">
                    <div
                        v-for="(img, idx) in slideshowImages"
                        :key="img.id"
                        class="group relative border border-gray-200 rounded-lg bg-white overflow-hidden shadow-2xs hover:shadow-sm transition-all flex flex-col"
                    >
                        <!-- Thumbnail -->
                        <div class="relative h-40 bg-gray-100 overflow-hidden">
                            <img :src="img.url" :alt="'Slide ' + (idx + 1)" class="w-full h-full object-cover" />

                            <div class="absolute top-2 left-2 flex items-center gap-1">
                                <span class="px-2 py-0.5 bg-black/75 text-white text-[10px] font-semibold rounded font-mono">
                                    #{{ idx + 1 }}
                                </span>
                                <span v-if="idx === 0" class="px-1.5 py-0.5 bg-emerald-600 text-white text-[9px] font-semibold rounded uppercase">
                                    First
                                </span>
                            </div>

                            <button
                                @click="previewImage = img.url"
                                class="absolute top-2 right-2 p-1.5 bg-black/60 hover:bg-black/80 text-white rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                                title="View full image"
                            >
                                <i class="ri-zoom-in-line text-xs"></i>
                            </button>
                        </div>

                        <!-- Card Controls -->
                        <div class="p-2.5 bg-white border-t border-gray-100 flex items-center justify-between gap-1 mt-auto">
                            <div class="flex items-center gap-1">
                                <button
                                    @click="moveSlide(idx, -1)"
                                    :disabled="idx === 0"
                                    class="p-1 rounded border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                    title="Move left"
                                >
                                    <i class="ri-arrow-left-s-line text-xs"></i>
                                </button>
                                <button
                                    @click="moveSlide(idx, 1)"
                                    :disabled="idx === slideshowImages.length - 1"
                                    class="p-1 rounded border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                    title="Move right"
                                >
                                    <i class="ri-arrow-right-s-line text-xs"></i>
                                </button>
                            </div>

                            <button
                                @click="deleteSlide(img.id)"
                                class="text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded transition-colors cursor-pointer"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= SECTION 2: ACHIEVEMENTS & COMPILATION ================= -->
        <div v-else-if="activeSection === 'achievements'" class="space-y-5">
            
            <!-- Upload Dropzone -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-xs">
                <div
                    @dragover.prevent="isDraggingAchievement = true"
                    @dragleave.prevent="isDraggingAchievement = false"
                    @drop.prevent="isDraggingAchievement = false; uploadAchievementFiles($event.dataTransfer.files)"
                    :class="[
                        'relative border-2 border-dashed rounded-lg p-6 sm:p-8 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-2.5',
                        isDraggingAchievement
                            ? 'border-emerald-500 bg-emerald-50/50'
                            : 'border-gray-300 bg-gray-50/50 hover:bg-white hover:border-gray-400'
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

                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i :class="['text-xl', isUploadingAchievement ? 'ri-loader-4-line animate-spin' : 'ri-image-add-line']"></i>
                    </div>

                    <div class="space-y-0.5">
                        <p class="text-xs sm:text-sm font-semibold text-gray-800">
                            <span class="text-emerald-600 hover:underline">Click to upload</span> project & achievement photos
                        </p>
                        <p class="text-[11px] text-gray-400">
                            Upload photos for completed projects, ribbon-cuttings, and awards (PNG, JPG, WEBP up to 5MB).
                        </p>
                    </div>

                    <span
                        v-if="isUploadingAchievement"
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-medium rounded-full animate-pulse mt-1"
                    >
                        Uploading photos...
                    </span>
                </div>
            </div>

            <!-- Gallery & Management Cards -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Project & Achievement Gallery ({{ filteredAchievements.length }})</h2>
                        <p class="text-xs text-gray-500">Edit titles, categories, years, and captions for each photo</p>
                    </div>

                    <!-- Category Filter Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                        <button
                            v-for="cat in achievementCategories"
                            :key="cat.value"
                            @click="achievementFilter = cat.value"
                            :class="[
                                'px-2.5 py-1 rounded-md text-xs font-medium transition-colors whitespace-nowrap cursor-pointer',
                                achievementFilter === cat.value
                                    ? 'bg-gray-900 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                        >
                            {{ cat.label }}
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredAchievements.length === 0" class="text-center py-10 px-4 border border-dashed border-gray-200 rounded-lg bg-gray-50/50">
                    <i class="ri-folder-image-line text-2xl text-gray-400 block mb-1"></i>
                    <p class="text-xs font-semibold text-gray-700">No photos in this category</p>
                    <p class="text-[11px] text-gray-400 mt-0.5 mb-3">Upload project photos or switch categories above.</p>
                    <button
                        @click="$refs.achievementFileInput.click()"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium transition-colors cursor-pointer"
                    >
                        Upload Photos
                    </button>
                </div>

                <!-- Gallery Cards Grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-3.5">
                    <div
                        v-for="(item, idx) in filteredAchievements"
                        :key="item.id"
                        class="group relative border border-gray-200 rounded-lg bg-white overflow-hidden shadow-2xs hover:shadow-sm transition-all flex flex-col"
                    >
                        <!-- Thumbnail -->
                        <div class="relative h-44 bg-gray-100 overflow-hidden">
                            <img :src="item.url" :alt="item.title || 'Project'" class="w-full h-full object-cover" />

                            <!-- Category Badge -->
                            <div class="absolute top-2 left-2 flex items-center gap-1">
                                <span class="px-2 py-0.5 bg-black/75 text-white text-[10px] font-semibold rounded uppercase font-mono">
                                    {{ item.category === 'completed_project' ? 'Project' : item.category }}
                                </span>
                                <span v-if="item.year" class="px-1.5 py-0.5 bg-black/60 text-white text-[9px] rounded font-mono">
                                    {{ item.year }}
                                </span>
                            </div>

                            <button
                                @click="previewImage = item.url"
                                class="absolute top-2 right-2 p-1.5 bg-black/60 hover:bg-black/80 text-white rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                                title="Zoom photo"
                            >
                                <i class="ri-zoom-in-line text-xs"></i>
                            </button>
                        </div>

                        <!-- Card Info -->
                        <div class="p-3 flex-1 flex flex-col justify-between space-y-2">
                            <div class="space-y-1">
                                <h3 class="text-xs font-bold text-gray-900 line-clamp-1">
                                    {{ item.title || 'Untitled Picture' }}
                                </h3>
                                <p v-if="item.location" class="text-[11px] text-gray-500 flex items-center gap-1 truncate">
                                    <i class="ri-map-pin-line text-gray-400"></i>
                                    <span class="truncate">{{ item.location }}</span>
                                </p>
                                <p v-if="item.caption" class="text-[11px] text-gray-600 line-clamp-2 leading-relaxed">
                                    {{ item.caption }}
                                </p>
                            </div>

                            <!-- Controls -->
                            <div class="pt-2 border-t border-gray-100 flex items-center justify-between gap-1 text-xs">
                                <button
                                    @click="editingAchievement = { ...item }"
                                    class="font-medium text-emerald-700 hover:text-emerald-900 px-1.5 py-0.5 rounded hover:bg-emerald-50 transition-colors cursor-pointer"
                                >
                                    Edit Details
                                </button>

                                <div class="flex items-center gap-1">
                                    <button
                                        @click="moveAchievement(idx, -1)"
                                        :disabled="idx === 0"
                                        class="p-1 rounded border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-30"
                                        title="Move left"
                                    >
                                        <i class="ri-arrow-left-s-line text-xs"></i>
                                    </button>
                                    <button
                                        @click="moveAchievement(idx, 1)"
                                        :disabled="idx === achievementImages.length - 1"
                                        class="p-1 rounded border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-30"
                                        title="Move right"
                                    >
                                        <i class="ri-arrow-right-s-line text-xs"></i>
                                    </button>
                                    <button
                                        @click="deleteAchievement(item.id)"
                                        class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition-colors"
                                        title="Delete photo"
                                    >
                                        <i class="ri-delete-bin-line text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODAL: EDIT DETAILS ================= -->
        <div
            v-if="editingAchievement"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-xl border border-gray-200 shadow-xl max-w-lg w-full overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900">Edit Photo Details</h3>
                    <button @click="editingAchievement = null" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <div class="p-5 space-y-3.5 text-xs max-h-[75vh] overflow-y-auto">
                    <!-- Thumbnail preview -->
                    <div class="h-36 rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                        <img :src="editingAchievement.url" alt="Preview" class="w-full h-full object-cover" />
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Title / Headline</label>
                        <input
                            v-model="editingAchievement.title"
                            type="text"
                            placeholder="e.g. Multi-Purpose Building Turn-over"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:border-emerald-600 text-xs"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Category</label>
                            <select
                                v-model="editingAchievement.category"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:border-emerald-600 bg-white text-xs"
                            >
                                <option value="completed_project">Completed Project</option>
                                <option value="turnover">Turnover / Inauguration</option>
                                <option value="achievement">Achievement & Award</option>
                                <option value="milestone">Milestone</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Year / Timeline</label>
                            <input
                                v-model="editingAchievement.year"
                                type="text"
                                placeholder="e.g. 2026"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:border-emerald-600 text-xs"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Location / Barangay</label>
                        <input
                            v-model="editingAchievement.location"
                            type="text"
                            placeholder="e.g. Barangay Poblacion, Opol"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:border-emerald-600 text-xs"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Caption / Summary</label>
                        <textarea
                            v-model="editingAchievement.caption"
                            rows="3"
                            placeholder="Provide brief details or highlights..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:border-emerald-600 text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2 text-xs">
                    <button
                        @click="editingAchievement = null"
                        class="px-3.5 py-1.5 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-medium cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveEditedAchievement"
                        class="px-4 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded-lg font-medium cursor-pointer"
                    >
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= LIGHTBOX ZOOM ================= -->
        <div
            v-if="previewImage"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-xs"
            @click="previewImage = null"
        >
            <div class="relative max-w-4xl max-h-[90vh] bg-slate-900 rounded-lg overflow-hidden shadow-2xl" @click.stop>
                <img :src="previewImage" alt="Zoom preview" class="w-full max-h-[85vh] object-contain" />
                <button
                    @click="previewImage = null"
                    class="absolute top-3 right-3 p-2 bg-black/60 hover:bg-black/90 text-white rounded-full transition-colors cursor-pointer"
                >
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>
        </div>

    </div>
</template>
