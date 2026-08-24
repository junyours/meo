<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { jsPDF } from 'jspdf';

const props = defineProps({
    projectId: {
        type: Number,
        default: null,
    },
    projectName: {
        type: String,
        default: 'project-documents',
    },
    techprepId: {
        type: Number,
        default: null,
    },
    isEditable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['document-uploaded', 'document-selected']);

// Toast notification system
const toast = ref({ show: false, message: '', type: 'success' });
let toastTimeout = null;

const showToast = (message, type = 'success') => {
    if (toastTimeout) clearTimeout(toastTimeout);
    toast.value = { show: true, message, type };
    toastTimeout = setTimeout(() => { 
        toast.value.show = false; 
    }, 3000);
};

// Drag and drop state
const isDragging = ref(false);
const dragCounter = ref(0);

// Upload state
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadQueue = ref([]);
const currentUploadIndex = ref(0);
const showUploadQueueModal = ref(false);

// Document management state
const documents = ref([]);
const selectedDocument = ref(null);
const showPreview = ref(false);
const isLoading = ref(false);
const error = ref(null);

// Search and filter state
const searchTerm = ref('');
const filterType = ref('all');
const sortBy = ref('page_number');
const sortOrder = ref('asc');
const viewMode = ref('grid'); // 'grid' | 'list'

// Pagination state
const currentPage = ref(1);
const itemsPerPage = ref(18);

const selectedDocumentIds = ref([]);
const isSelectingDocuments = ref(false);

// Info modal state
const showInfoModal = ref(false);

const getFilePreviewKind = (file) => file.type === 'application/pdf' ? 'pdf' : file.type.startsWith('image/') ? 'image' : 'file';

// Document types configuration
const documentTypes = [
    { value: 'all', label: 'All Types' },
    { value: 'pdf', label: 'PDF' },
    { value: 'jpg', label: 'JPG' },
    { value: 'png', label: 'PNG' },
    { value: 'docx', label: 'DOCX' },
];

// Processing status configuration
const processingStatuses = {
    pending: { label: 'Pending', color: 'bg-gray-100 text-gray-700' },
    processing: { label: 'Processing', color: 'bg-blue-100 text-blue-700' },
    completed: { label: 'Completed', color: 'bg-emerald-100 text-emerald-700' },
    failed: { label: 'Failed', color: 'bg-red-100 text-red-700' },
};

// Computed properties
const filteredDocuments = computed(() => {
    let filtered = documents.value;

    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        filtered = filtered.filter(doc => 
            doc.document_name.toLowerCase().includes(term) ||
            (doc.extracted_text && doc.extracted_text.toLowerCase().includes(term))
        );
    }

    if (filterType.value !== 'all') {
        filtered = filtered.filter(doc => doc.document_type === filterType.value);
    }

    // Sort documents
    filtered.sort((a, b) => {
        let comparison = 0;
        switch (sortBy.value) {
            case 'page_number':
                comparison = (a.page_number || 0) - (b.page_number || 0);
                break;
            case 'name':
                comparison = a.document_name.localeCompare(b.document_name);
                break;
            case 'date':
                comparison = new Date(b.created_at) - new Date(a.created_at);
                break;
            case 'size':
                comparison = (a.file_size || 0) - (b.file_size || 0);
                break;
            default:
                comparison = 0;
        }
        return sortOrder.value === 'asc' ? comparison : -comparison;
    });

    return filtered;
});

const totalPages = computed(() => Math.ceil(filteredDocuments.value.length / itemsPerPage.value));

const paginatedDocuments = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredDocuments.value.slice(start, end);
});

const totalDocuments = computed(() => documents.value.length);
const totalFileSize = computed(() => {
    return documents.value.reduce((total, doc) => total + (doc.file_size || 0), 0);
});

// Utility functions
const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isImageDocument = (document) => ['jpg', 'jpeg', 'png'].includes(document?.document_type?.toLowerCase());
const isPdfDocument = (document) => document?.document_type?.toLowerCase() === 'pdf';
const isSuperAdminContext = computed(() => {
    return typeof window !== 'undefined' && (window.location.pathname.startsWith('/superadmin') || window.location.href.includes('/superadmin'));
});
const isStaffContext = computed(() => {
    return typeof window !== 'undefined' && (window.location.pathname.startsWith('/staff') || window.location.href.includes('/staff'));
});
const baseApiUrl = computed(() => {
    if (isSuperAdminContext.value) return '/superadmin/documents';
    if (isStaffContext.value) return '/staff/documents';
    return '/admin/documents';
});
const getDocumentPreviewUrl = (document) => `${baseApiUrl.value}/${document.id}/preview`;

// Preview Viewer State
const previewZoom = ref(1);
const previewRotation = ref(0);

const currentDocumentIndex = computed(() => {
    if (!selectedDocument.value) return -1;
    return filteredDocuments.value.findIndex(d => d.id === selectedDocument.value.id);
});

const zoomIn = () => {
    previewZoom.value = Math.min(3, +(previewZoom.value + 0.25).toFixed(2));
};

const zoomOut = () => {
    previewZoom.value = Math.max(0.5, +(previewZoom.value - 0.25).toFixed(2));
};

const resetPreviewView = () => {
    previewZoom.value = 1;
    previewRotation.value = 0;
};

const rotatePreview = () => {
    previewRotation.value = (previewRotation.value + 90) % 360;
};

const openInNewTab = (document) => {
    if (!document) return;
    window.open(getDocumentPreviewUrl(document), '_blank');
};

const previousDocument = () => {
    const index = filteredDocuments.value.findIndex(document => document.id === selectedDocument.value?.id);
    if (index > 0) {
        selectedDocument.value = filteredDocuments.value[index - 1];
        resetPreviewView();
        emit('document-selected', selectedDocument.value);
    }
};
const nextDocument = () => {
    const index = filteredDocuments.value.findIndex(document => document.id === selectedDocument.value?.id);
    if (index >= 0 && index < filteredDocuments.value.length - 1) {
        selectedDocument.value = filteredDocuments.value[index + 1];
        resetPreviewView();
        emit('document-selected', selectedDocument.value);
    }
};

// Drag and drop handlers
const handleDragEnter = (e) => {
    e.preventDefault();
    dragCounter.value++;
    isDragging.value = true;
};

const handleDragLeave = (e) => {
    e.preventDefault();
    dragCounter.value--;
    if (dragCounter.value === 0) {
        isDragging.value = false;
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
};

const handleDrop = (e) => {
    e.preventDefault();
    isDragging.value = false;
    dragCounter.value = 0;
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        uploadMultipleFiles(files);
    }
};

// File upload handlers
const handleFileSelect = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        uploadMultipleFiles(files);
        e.target.value = ''; // Reset file input
    }
};

const validateFile = (file) => {
    const maxSize = 50 * 1024 * 1024; // 50MB
    const allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'text/plain'
    ];

    if (file.size > maxSize) {
        throw new Error(`File "${file.name}" exceeds 50MB limit`);
    }

    if (!allowedTypes.includes(file.type)) {
        throw new Error(`File type "${file.type}" is not supported`);
    }

    return true;
};

const uploadMultipleFiles = async (files) => {
    if (!props.isEditable) {
        showToast('You do not have permission to upload files', 'error');
        return;
    }

    const fileArray = Array.from(files);
    
    // Validate all files first
    const invalidFiles = [];
    const validFiles = [];
    
    fileArray.forEach(file => {
        try {
            validateFile(file);
            validFiles.push(file);
        } catch (error) {
            invalidFiles.push({ file, error: error.message });
        }
    });

    if (invalidFiles.length > 0) {
        showToast(`${invalidFiles.length} file(s) are invalid and will be skipped`, 'error');
    }

    if (validFiles.length === 0) return;

    // Prepare upload queue
    uploadQueue.value = validFiles.map((file, index) => ({
        file,
        name: file.name,
        size: file.size,
        previewUrl: URL.createObjectURL(file),
        previewKind: getFilePreviewKind(file),
        status: 'pending',
        progress: 0,
        pageNumber: documents.value.length + index + 1,
    }));
    
    showUploadQueueModal.value = true;
    
    // Process files sequentially
    for (let i = 0; i < uploadQueue.value.length; i++) {
        currentUploadIndex.value = i;
        const item = uploadQueue.value[i];
        item.status = 'uploading';
        
        try {
            await uploadSingleFile(item.file, item.pageNumber);
            item.status = 'completed';
            item.progress = 100;
        } catch (error) {
            item.status = 'failed';
            item.error = error.message || 'Upload failed';
            showToast(`Failed to upload "${item.name}": ${error.message}`, 'error');
        }
    }
    
    // Close queue modal after delay
    setTimeout(() => {
        if (uploadQueue.value.every(item => item.status !== 'uploading')) {
            showUploadQueueModal.value = false;
            setTimeout(() => {
                uploadQueue.value = [];
                currentUploadIndex.value = 0;
            }, 300);
        }
    }, 2000);
};

const uploadSingleFile = async (file, pageNumber) => {
    if (!props.isEditable) throw new Error('Permission denied');

    isUploading.value = true;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_name', file.name);
    formData.append('page_number', pageNumber.toString());
    if (props.projectId) formData.append('project_id', props.projectId.toString());
    if (props.techprepId) formData.append('techprep_id', props.techprepId.toString());

    try {
        const response = await window.axios.post(`${baseApiUrl.value}`, formData, {
            onUploadProgress: (progressEvent) => {
                const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                const queueItem = uploadQueue.value[currentUploadIndex.value];
                if (queueItem) {
                    queueItem.progress = progress;
                }
                uploadProgress.value = progress;
            },
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        documents.value.unshift(response.data.document);
        emit('document-uploaded', response.data.document);
        return response.data;
    } catch (error) {
        console.error('Upload error:', error.response?.data || error);
        throw error;
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};


// ========== IN-APP SYSTEM CAMERA (FRONT / BACK, ANY PHONE/DEVICE) ==========
const showCameraModal = ref(false);
const cameraVideoRef = ref(null);
const cameraStream = ref(null);
const cameraFacingMode = ref('environment'); // 'environment' (back) or 'user' (front)
const videoDevices = ref([]);
const currentCameraIndex = ref(0);
const hasMultipleCameras = ref(true);
const isCameraLoading = ref(false);
const cameraError = ref(null);
const torchEnabled = ref(false);
const hasTorch = ref(false);
const nativeCameraInputRef = ref(null);

const triggerNativeCameraCapture = () => {
    if (nativeCameraInputRef.value) {
        nativeCameraInputRef.value.click();
    }
};

const loadCameraDevices = async () => {
    try {
        if (navigator.mediaDevices?.enumerateDevices) {
            const devices = await navigator.mediaDevices.enumerateDevices();
            videoDevices.value = devices.filter(d => d.kind === 'videoinput');
            hasMultipleCameras.value = videoDevices.value.length > 1;
        }
    } catch (e) {
        console.warn('Device enumeration warning:', e);
    }
};

const openCameraModal = async () => {
    if (!props.isEditable) {
        showToast('You do not have permission to upload files', 'error');
        return;
    }
    showCameraModal.value = true;
    cameraError.value = null;
    isCameraLoading.value = true;

    await loadCameraDevices();
    await startCameraStream();
};

const startCameraStream = async () => {
    stopCameraStream();
    isCameraLoading.value = true;
    cameraError.value = null;

    try {
        const selectedDevice = videoDevices.value[currentCameraIndex.value];
        const selectedDeviceId = selectedDevice?.deviceId;

        const attempts = [];
        if (selectedDeviceId) {
            attempts.push({ video: { deviceId: { exact: selectedDeviceId } }, audio: false });
        }

        attempts.push(
            { video: { facingMode: { ideal: cameraFacingMode.value }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false },
            { video: { facingMode: { ideal: cameraFacingMode.value } }, audio: false },
            { video: { facingMode: cameraFacingMode.value }, audio: false },
            { video: { width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
            { video: true, audio: false }
        );

        let stream = null;
        let lastErr = null;

        const getUserMediaFn = (constraints) => {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                return navigator.mediaDevices.getUserMedia(constraints);
            }
            const legacy = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
            if (legacy) {
                return new Promise((resolve, reject) => legacy.call(navigator, constraints, resolve, reject));
            }
            return Promise.reject(new Error('CAMERA_NOT_SUPPORTED'));
        };

        for (const constraints of attempts) {
            try {
                stream = await getUserMediaFn(constraints);
                if (stream) break;
            } catch (err) {
                lastErr = err;
            }
        }

        if (!stream) throw lastErr || new Error('Camera access not supported or allowed');

        cameraStream.value = stream;

        // Detect torch support
        const track = stream.getVideoTracks()[0];
        if (track && typeof track.getCapabilities === 'function') {
            const capabilities = track.getCapabilities();
            hasTorch.value = !!capabilities.torch;
        } else {
            hasTorch.value = false;
        }

        await nextTick();
        if (cameraVideoRef.value) {
            const video = cameraVideoRef.value;
            video.srcObject = stream;
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.muted = true;

            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.catch((e) => {
                    console.warn('Video play warning:', e);
                });
            }
        }
    } catch (err) {
        console.error('Camera stream error:', err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            cameraError.value = 'Camera permission was blocked. Please allow camera access in your browser settings, or use Device Camera.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            cameraError.value = 'No camera found on this device. You can choose a photo from your gallery.';
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            cameraError.value = 'Camera is in use by another tab or app. Please close other camera tabs and retry.';
        } else {
            cameraError.value = 'Live stream unavailable (requires HTTPS on mobile). Click "Capture with Device Camera" below to take a photo!';
        }
    } finally {
        isCameraLoading.value = false;
    }
};

const switchCameraFacing = async () => {
    if (videoDevices.value.length > 1) {
        currentCameraIndex.value = (currentCameraIndex.value + 1) % videoDevices.value.length;
    }
    cameraFacingMode.value = cameraFacingMode.value === 'environment' ? 'user' : 'environment';
    await startCameraStream();
};

const toggleTorch = async () => {
    if (!cameraStream.value || !hasTorch.value) return;
    try {
        const track = cameraStream.value.getVideoTracks()[0];
        if (track) {
            torchEnabled.value = !torchEnabled.value;
            await track.applyConstraints({
                advanced: [{ torch: torchEnabled.value }]
            });
        }
    } catch (e) {
        console.warn('Torch error:', e);
    }
};

const stopCameraStream = () => {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => {
            try { track.stop(); } catch (e) {}
        });
        cameraStream.value = null;
    }
    torchEnabled.value = false;
};

const closeCameraModal = () => {
    stopCameraStream();
    showCameraModal.value = false;
};

const captureSnapshot = () => {
    if (!cameraVideoRef.value) return;
    const video = cameraVideoRef.value;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1920;
    canvas.height = video.videoHeight || 1080;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.95);

    closeCameraModal();
    openFilterEditor(dataUrl);
};

// ========== CAMSCANNER & ENHANCEMENT STATE ==========
const showFilterModal = ref(false);
const scannerFileInputRef = ref(null);
const capturedImageSrc = ref(null);
const filterCanvasRef = ref(null);
const selectedFilter = ref('magic'); // 'magic', 'bw', 'mono', 'real_document', 'original'
const rotationAngle = ref(0); // 0, 90, 180, 270
const brightness = ref(0); // -50 to 50
const contrast = ref(0); // -50 to 50
const isSavingScan = ref(false);
let rawImageElement = null;

const filterPresets = [
    { id: 'magic', name: 'Magic Color', desc: 'Whitens paper & boosts ink clarity' },
    { id: 'bw', name: 'B&W Clean', desc: 'High-contrast black & white document' },
    { id: 'mono', name: 'Grayscale', desc: 'Smooth clear grayscale' },
    { id: 'real_document', name: 'Real Doc', desc: 'Balanced lighting & crisp text' },
    { id: 'original', name: 'Original', desc: 'Raw captured photo' },
];

const isProcessingImage = ref(false);

const triggerScanCapture = () => {
    if (!props.isEditable) {
        showToast('You do not have permission to upload files', 'error');
        return;
    }
    if (scannerFileInputRef.value) {
        scannerFileInputRef.value.click();
    }
};

const handleCapturedImage = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    e.target.value = '';

    isProcessingImage.value = true;
    showFilterModal.value = true;
    selectedFilter.value = 'magic';
    rotationAngle.value = 0;
    brightness.value = 0;
    contrast.value = 0;

    const objectUrl = URL.createObjectURL(file);
    capturedImageSrc.value = objectUrl;

    const img = new Image();
    img.onload = () => {
        rawImageElement = img;
        ensureCanvasAndRender();
    };
    img.onerror = (err) => {
        console.error('Mobile image load error:', err);
        const reader = new FileReader();
        reader.onload = (ev) => {
            const fallbackImg = new Image();
            fallbackImg.onload = () => {
                rawImageElement = fallbackImg;
                ensureCanvasAndRender();
            };
            fallbackImg.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    };
    img.src = objectUrl;
};

const ensureCanvasAndRender = () => {
    let attempts = 0;
    const tryRender = () => {
        attempts++;
        if (filterCanvasRef.value && rawImageElement) {
            renderFilter();
            isProcessingImage.value = false;
        } else if (attempts < 20) {
            requestAnimationFrame(tryRender);
        } else {
            isProcessingImage.value = false;
        }
    };
    nextTick(() => {
        tryRender();
    });
};

watch(filterCanvasRef, (newCanvas) => {
    if (newCanvas && rawImageElement) {
        renderFilter();
    }
});

const openFilterEditor = (imageUrl) => {
    capturedImageSrc.value = imageUrl;
    selectedFilter.value = 'magic';
    rotationAngle.value = 0;
    brightness.value = 0;
    contrast.value = 0;
    showFilterModal.value = true;
    isProcessingImage.value = true;

    const img = new Image();
    img.onload = () => {
        rawImageElement = img;
        ensureCanvasAndRender();
    };
    img.src = imageUrl;
};

const rotateClockwise = () => {
    rotationAngle.value = (rotationAngle.value + 90) % 360;
    renderFilter();
};

const resetAdjustments = () => {
    brightness.value = 0;
    contrast.value = 0;
    rotationAngle.value = 0;
    selectedFilter.value = 'magic';
    renderFilter();
};

const renderFilter = () => {
    if (!rawImageElement || !filterCanvasRef.value) return;
    const canvas = filterCanvasRef.value;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) return;
    
    const isRotated90or270 = rotationAngle.value === 90 || rotationAngle.value === 270;
    const targetWidth = isRotated90or270 ? rawImageElement.height : rawImageElement.width;
    const targetHeight = isRotated90or270 ? rawImageElement.width : rawImageElement.height;

    // Mobile-optimized dimension limit for smooth rendering and low memory footprint
    const maxDim = 1600;
    let scale = 1;
    if (targetWidth > maxDim || targetHeight > maxDim) {
        scale = Math.min(maxDim / targetWidth, maxDim / targetHeight);
    }

    canvas.width = Math.max(1, Math.round(targetWidth * scale));
    canvas.height = Math.max(1, Math.round(targetHeight * scale));

    ctx.save();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate((rotationAngle.value * Math.PI) / 180);
    
    const drawW = Math.round(rawImageElement.width * scale);
    const drawH = Math.round(rawImageElement.height * scale);
    ctx.drawImage(rawImageElement, -drawW / 2, -drawH / 2, drawW, drawH);
    ctx.restore();

    // Pixel manipulation
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imgData.data;
    const len = data.length;

    // User Brightness & Contrast
    const userB = brightness.value * 2.55;
    const userC = (contrast.value + 100) / 100;
    const filter = selectedFilter.value;

    for (let i = 0; i < len; i += 4) {
        let r = data[i];
        let g = data[i + 1];
        let b = data[i + 2];

        // Apply custom brightness/contrast sliders
        if (brightness.value !== 0 || contrast.value !== 0) {
            r = ((r - 128) * userC + 128) + userB;
            g = ((g - 128) * userC + 128) + userB;
            b = ((b - 128) * userC + 128) + userB;
        }

        const lum = 0.299 * r + 0.587 * g + 0.114 * b;

        if (filter === 'magic') {
            // Magic Color / Enhanced (CamScanner effect)
            const whiteThreshold = 180;
            let boostLum = lum;
            if (lum > whiteThreshold) {
                const diff = (lum - whiteThreshold) / (255 - whiteThreshold);
                boostLum = lum + diff * 35;
            } else {
                boostLum = lum * 0.92;
            }
            const lumRatio = lum > 0 ? (boostLum / lum) : 1;
            
            // Saturation boost
            const sat = 1.25;
            r = lum + (r - lum) * sat;
            g = lum + (g - lum) * sat;
            b = lum + (b - lum) * sat;

            // Apply contrast curve
            r = (r - 128) * 1.3 + 128 + (lumRatio - 1) * 80;
            g = (g - 128) * 1.3 + 128 + (lumRatio - 1) * 80;
            b = (b - 128) * 1.3 + 128 + (lumRatio - 1) * 80;

        } else if (filter === 'bw') {
            // High-Contrast Clean B&W Document
            const threshold = 145;
            let val;
            if (lum > threshold) {
                val = 255;
            } else if (lum > threshold - 30) {
                val = Math.min(255, (lum - (threshold - 30)) * 8);
            } else {
                val = Math.max(0, lum * 0.3);
            }
            r = val;
            g = val;
            b = val;

        } else if (filter === 'mono') {
            // Smooth Grayscale with background whitening
            let gray = lum;
            if (gray > 170) {
                gray = gray + (gray - 170) * 0.6;
            } else {
                gray = (gray - 128) * 1.2 + 128;
            }
            r = gray;
            g = gray;
            b = gray;

        } else if (filter === 'real_document') {
            // Real Document Photo: Balanced illumination & crisp colors
            const avgLum = (r + g + b) / 3;
            r = r * 0.98 + avgLum * 0.02;
            g = g * 0.98 + avgLum * 0.02;
            b = b * 1.05; // slight blue compensation for yellow lamp light
            
            r = (r - 128) * 1.15 + 130;
            g = (g - 128) * 1.15 + 130;
            b = (b - 128) * 1.15 + 130;
        }

        data[i] = Math.min(255, Math.max(0, r));
        data[i + 1] = Math.min(255, Math.max(0, g));
        data[i + 2] = Math.min(255, Math.max(0, b));
    }

    ctx.putImageData(imgData, 0, 0);
};

watch([selectedFilter, brightness, contrast], () => {
    renderFilter();
});

const saveAndUploadScannedDocument = async (scanNext = false) => {
    if (!filterCanvasRef.value) return;
    isSavingScan.value = true;

    try {
        const canvas = filterCanvasRef.value;
        const blob = await new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', 0.92);
        });

        if (!blob) throw new Error('Canvas conversion failed');

        const nextPage = documents.value.length + 1;
        const fileName = `Scanned_Page_${nextPage}.jpg`;
        const file = new File([blob], fileName, { type: 'image/jpeg' });

        uploadQueue.value = [{
            file,
            name: fileName,
            size: file.size,
            previewUrl: URL.createObjectURL(blob),
            previewKind: 'image',
            status: 'uploading',
            progress: 0,
            pageNumber: nextPage,
        }];

        showFilterModal.value = false;
        showUploadQueueModal.value = true;
        currentUploadIndex.value = 0;

        await uploadSingleFile(file, nextPage);
        uploadQueue.value[0].status = 'completed';
        uploadQueue.value[0].progress = 100;

        setTimeout(() => {
            showUploadQueueModal.value = false;
            uploadQueue.value = [];
        }, 1200);

        showToast('Scanned document enhanced & uploaded successfully!', 'success');

        if (scanNext) {
            setTimeout(() => {
                openCameraModal();
            }, 500);
        }
    } catch (err) {
        console.error('Scan save error:', err);
        showToast('Failed to save scanned document', 'error');
    } finally {
        isSavingScan.value = false;
    }
};


// Document actions
const handleSelectDocument = (doc) => {
    selectedDocument.value = doc;
    resetPreviewView();
    showPreview.value = true;
    emit('document-selected', doc);
};

const closePreview = () => {
    showPreview.value = false;
    selectedDocument.value = null;
    resetPreviewView();
};

const downloadDocument = async (document) => {
    try {
        showToast('Downloading document...', 'success');
        const response = await window.axios.get(`${baseApiUrl.value}/${document.id}/download`, {
            responseType: 'blob',
        });
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = window.document.createElement('a');
        link.href = url;
        link.setAttribute('download', document.document_name);
        window.document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Download error:', error);
        showToast('Failed to download document', 'error');
    }
};

const toggleDocumentSelection = (document) => {
    selectedDocumentIds.value = selectedDocumentIds.value.includes(document.id)
        ? selectedDocumentIds.value.filter(id => id !== document.id)
        : [...selectedDocumentIds.value, document.id];
};

const toggleSelectionMode = () => {
    isSelectingDocuments.value = !isSelectingDocuments.value;
    if (!isSelectingDocuments.value) selectedDocumentIds.value = [];
};

const downloadSelectedDocuments = async () => {
    const selected = documents.value.filter(document => selectedDocumentIds.value.includes(document.id));
    if (!selected.length) return showToast('Select at least one document first', 'error');
    if (!confirm(`Download ${selected.length} selected document(s) as one PDF?`)) {
        for (const document of selected) await downloadDocument(document);
        return;
    }

    try {
        const imageDocuments = selected.filter(isImageDocument);
        if (!imageDocuments.length) return showToast('Only image documents can be combined into a PDF.', 'error');
        const pdf = new jsPDF({ unit: 'px', format: 'a4' });
        for (let index = 0; index < imageDocuments.length; index++) {
            const document = imageDocuments[index];
            const blob = await (await fetch(getDocumentPreviewUrl(document))).blob();
            const dataUrl = await new Promise(resolve => { const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.readAsDataURL(blob); });
            const image = await new Promise(resolve => { const img = new Image(); img.onload = () => resolve(img); img.src = dataUrl; });
            if (index) pdf.addPage();
            const scale = Math.min(560 / image.width, 760 / image.height);
            pdf.addImage(dataUrl, document.document_type.toLowerCase() === 'png' ? 'PNG' : 'JPEG', 20, 20, image.width * scale, image.height * scale);
        }
        const filename = props.projectName?.trim() || 'project_name';
        pdf.save(`${filename}.pdf`);
        selectedDocumentIds.value = [];
    } catch (error) {
        console.error('Batch PDF error:', error);
        showToast('Failed to create the PDF.', 'error');
    }
};

const deleteDocument = async (document) => {
    if (!props.isEditable) {
        showToast('You do not have permission to delete documents', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to delete "${document.document_name}"? This action cannot be undone.`)) return;

    try {
        await window.axios.delete(`${baseApiUrl.value}/${document.id}`);
        documents.value = documents.value.filter(d => d.id !== document.id);
        if (selectedDocument.value?.id === document.id) {
            closePreview();
        }
        showToast('Document deleted successfully', 'success');
    } catch (error) {
        console.error('Delete error:', error);
        showToast('Failed to delete document', 'error');
    }
};

const savePageNumber = async (document) => {
    const pageNumber = Number(document.page_number);
    if (!Number.isInteger(pageNumber) || pageNumber < 1) return;
    try {
        const response = await window.axios.put(`${baseApiUrl.value}/${document.id}`, { page_number: pageNumber });
        Object.assign(document, response.data.document);
        showToast('Page number updated', 'success');
    } catch (error) {
        console.error('Page update error:', error);
        showToast('Failed to update page number', 'error');
    }
};

// Data loading
const loadDocuments = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        const params = {};
        if (props.projectId) params.project_id = props.projectId;
        if (props.techprepId) params.techprep_id = props.techprepId;

        const response = await window.axios.get(`${baseApiUrl.value}`, { params });
        documents.value = response.data.data || [];
    } catch (error) {
        console.error('Load documents error:', error);
        error.value = 'Failed to load documents. Please try again.';
        showToast('Failed to load documents', 'error');
    } finally {
        isLoading.value = false;
    }
};

// Pagination
const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// Watchers
watch(() => [props.projectId, props.techprepId], () => {
    loadDocuments();
}, { immediate: true });

watch(searchTerm, () => {
    currentPage.value = 1; // Reset to first page on search
});

watch(filterType, () => {
    currentPage.value = 1; // Reset to first page on filter change
});

// Lifecycle hooks
onMounted(() => {
    loadDocuments();
});

onUnmounted(() => {
    if (toastTimeout) clearTimeout(toastTimeout);
    stopCameraStream();
    window.removeEventListener('keydown', handleKeydown);
});

// Keyboard shortcuts
const handleKeydown = (e) => {
    if (showPreview.value) {
        if (e.key === 'Escape') {
            closePreview();
        } else if (e.key === 'ArrowLeft') {
            previousDocument();
        } else if (e.key === 'ArrowRight') {
            nextDocument();
        } else if (e.key === '+' || e.key === '=') {
            zoomIn();
        } else if (e.key === '-' || e.key === '_') {
            zoomOut();
        } else if (e.key === '0') {
            resetPreviewView();
        } else if (e.key.toLowerCase() === 'r' && !['INPUT', 'TEXTAREA'].includes(e.target?.tagName)) {
            rotatePreview();
        }
        return;
    }
    if (e.key === 'Escape') {
        if (showFilterModal.value) showFilterModal.value = false;
        if (showScannerModal.value) closeScannerModal();
        if (showCameraModal.value) closeCameraModal();
        if (showUploadQueueModal.value) {
            showUploadQueueModal.value = false;
            uploadQueue.value = [];
        }
        if (showInfoModal.value) showInfoModal.value = false;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div class="space-y-3 font-sans text-slate-800">
        <!-- Toast Notifications -->
        <Transition name="slide-fade">
            <div
                v-if="toast.show"
                :class="[
                    'fixed top-4 right-4 z-50 flex items-center gap-2.5 rounded-lg px-4 py-2.5 text-xs font-semibold shadow-xl border transition-all duration-300',
                    toast.type === 'success' 
                        ? 'bg-emerald-600 text-white border-emerald-500' 
                        : 'bg-red-600 text-white border-red-500',
                ]"
            >
                <svg v-if="toast.type === 'success'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{{ toast.message }}</span>
                <button @click="toast.show = false" class="ml-1 text-white/80 hover:text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </Transition>

        <!-- Main Container Card -->
        <div class="bg-white border border-slate-200 rounded-lg shadow-2xs overflow-hidden">
            <!-- Compact Header Section -->
            <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-md bg-red-100 text-red-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-bold text-slate-900">Technical Documents & Digital Archive</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">
                                {{ totalDocuments }} files · {{ formatFileSize(totalFileSize) }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500">Official drawings, scanned POW/DED records, and attachments.</p>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 self-end sm:self-auto">
                    <!-- View mode switcher -->
                    <div class="inline-flex rounded-md border border-slate-200 bg-white p-0.5 text-xs">
                        <button
                            type="button"
                            @click="viewMode = 'grid'"
                            :class="['px-2 py-1 rounded text-xs font-semibold flex items-center gap-1 transition', viewMode === 'grid' ? 'bg-red-700 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900']"
                            title="Grid View"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            <span>Grid</span>
                        </button>
                        <button
                            type="button"
                            @click="viewMode = 'list'"
                            :class="['px-2 py-1 rounded text-xs font-semibold flex items-center gap-1 transition', viewMode === 'list' ? 'bg-red-700 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900']"
                            title="List View"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            <span>List</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="showInfoModal = true"
                        class="px-2.5 py-1 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 rounded-md transition flex items-center gap-1"
                        title="Document Information & Stats"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Info</span>
                    </button>

                    <button
                        type="button"
                        @click="loadDocuments"
                        :disabled="isLoading"
                        class="px-2.5 py-1 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 rounded-md transition flex items-center gap-1 disabled:opacity-50"
                        title="Reload Documents"
                    >
                        <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Compact Upload & Scan Action Bar (if editable) -->
            <div v-if="isEditable" class="p-3 bg-white border-b border-slate-100">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <!-- Live Camera Scanner Card -->
                    <button
                        type="button"
                        @click="openCameraModal"
                        class="flex items-center gap-3 p-2.5 rounded-lg border border-red-200 bg-gradient-to-r from-red-50/70 to-rose-50/30 hover:bg-red-50 hover:border-red-300 hover:shadow-xs transition text-left group cursor-pointer"
                    >
                        <input type="file" ref="scannerFileInputRef" accept="image/*" class="hidden" @change="handleCapturedImage" />
                        <input type="file" ref="nativeCameraInputRef" accept="image/*" capture="environment" class="hidden" @change="handleCapturedImage" />
                        <div class="w-9 h-9 rounded-lg bg-red-700 text-white flex items-center justify-center shadow-xs shrink-0 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-900">Scan via Live Camera</span>
                                <span class="text-[9px] uppercase font-bold px-1.5 py-0.2 bg-red-100 text-red-800 rounded">Live / Filters</span>
                            </div>
                            <p class="text-[11px] text-slate-500 truncate">Capture paper documents, crop & enhance with filters</p>
                        </div>
                    </button>

                    <!-- Drop & Browse Card -->
                    <div
                        @dragenter="handleDragEnter"
                        @dragleave="handleDragLeave"
                        @dragover="handleDragOver"
                        @drop="handleDrop"
                        :class="[
                            'flex items-center gap-3 p-2.5 rounded-lg border border-dashed transition cursor-pointer relative',
                            isDragging ? 'border-red-500 bg-red-50/50' : 'border-slate-300 bg-slate-50/70 hover:bg-slate-100 hover:border-slate-400'
                        ]"
                    >
                        <input
                            type="file"
                            ref="fileInput"
                            @change="handleFileSelect"
                            accept=".pdf,.jpg,.jpeg,.png,.docx,.doc,.txt"
                            multiple
                            class="absolute inset-0 cursor-pointer opacity-0"
                        />
                        <div class="w-9 h-9 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-900">Upload Attachments</span>
                                <span class="text-[10px] text-red-700 font-semibold underline">Browse</span>
                            </div>
                            <p class="text-[11px] text-slate-500 truncate">Drop PDF, JPG, PNG, DOCX (Max 50MB per file)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 text-xs">
                <div class="relative flex-1 max-w-sm">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search document name or content..."
                        class="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-md bg-white focus:outline-none focus:border-red-600 focus:ring-1 focus:ring-red-500"
                    />
                </div>

                <div class="flex items-center gap-1.5 flex-wrap">
                    <!-- Type Filter -->
                    <select
                        v-model="filterType"
                        class="text-xs font-semibold border border-slate-200 bg-white py-1.5 pl-2.5 pr-7 rounded-md focus:border-red-600 focus:ring-1 focus:ring-red-500"
                    >
                        <option v-for="type in documentTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>

                    <!-- Sort By -->
                    <select
                        v-model="sortBy"
                        class="text-xs font-semibold border border-slate-200 bg-white py-1.5 pl-2.5 pr-7 rounded-md focus:border-red-600 focus:ring-1 focus:ring-red-500"
                    >
                        <option value="page_number">Sort: Page #</option>
                        <option value="name">Sort: Name</option>
                        <option value="date">Sort: Date</option>
                        <option value="size">Sort: Size</option>
                    </select>

                    <!-- Sort Order Toggle -->
                    <button
                        type="button"
                        @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'"
                        class="p-1.5 text-slate-600 hover:text-slate-900 bg-white border border-slate-200 rounded-md hover:bg-slate-100 transition"
                        :title="sortOrder === 'asc' ? 'Ascending (Click for Descending)' : 'Descending (Click for Ascending)'"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="sortOrder === 'asc' ? 'M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12' : 'M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4'" />
                        </svg>
                    </button>

                    <div class="h-4 w-px bg-slate-200"></div>

                    <!-- Selection Toggle -->
                    <button
                        type="button"
                        @click="toggleSelectionMode"
                        :class="['px-2.5 py-1.5 text-xs font-semibold rounded-md border transition flex items-center gap-1', isSelectingDocuments ? 'bg-slate-900 text-white border-slate-800 font-bold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100']"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ isSelectingDocuments ? 'Cancel' : 'Select' }}</span>
                    </button>

                    <!-- Download Selected -->
                    <button
                        v-if="isSelectingDocuments"
                        @click="downloadSelectedDocuments"
                        :disabled="!selectedDocumentIds.length"
                        class="px-3 py-1.5 text-xs font-bold text-white bg-red-700 hover:bg-red-800 disabled:opacity-40 disabled:cursor-not-allowed rounded-md transition shadow-2xs flex items-center gap-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download Selected ({{ selectedDocumentIds.length }})</span>
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="p-12 text-center">
                <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-red-600"></div>
                <p class="mt-3 text-xs text-slate-500">Loading documents...</p>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="p-8 text-center">
                <div class="mx-auto mb-3 rounded-full bg-red-50 text-red-600 p-3 w-fit">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <p class="text-xs text-red-600 font-semibold">{{ error }}</p>
                <button
                    @click="loadDocuments"
                    class="mt-3 rounded-md bg-red-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-800 transition"
                >
                    Try Again
                </button>
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredDocuments.length === 0" class="py-12 px-4 text-center">
                <div class="mx-auto mb-3 rounded-full bg-slate-100 p-3 w-fit text-slate-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h4 class="text-xs font-bold text-slate-800">No documents found</h4>
                <p class="mt-1 text-[11px] text-slate-500">
                    {{ searchTerm || filterType !== 'all' ? 'Try adjusting your search query or filter.' : 'Upload or scan documents to get started.' }}
                </p>
            </div>

            <!-- Grid View -->
            <div v-else-if="viewMode === 'grid'" class="p-3.5 sm:p-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <div
                        v-for="document in paginatedDocuments"
                        :key="document.id"
                        @click="handleSelectDocument(document)"
                        class="group relative cursor-pointer rounded-lg border border-slate-200 bg-white hover:border-red-500 hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col"
                        :title="`Click to preview ${document.document_name}`"
                    >
                        <!-- Top Badges / Selection Checkbox -->
                        <div class="absolute left-2 top-2 z-20">
                            <div v-if="isSelectingDocuments">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedDocumentIds.includes(document.id)" 
                                    @click.stop="toggleDocumentSelection(document)" 
                                    class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500 shadow-xs cursor-pointer" 
                                    aria-label="Select document" 
                                />
                            </div>
                            <div v-else>
                                <span class="inline-flex items-center rounded-md bg-slate-900/80 backdrop-blur-md px-1.5 py-0.5 text-[10px] font-bold text-white shadow-xs">
                                    P{{ document.page_number || '—' }}
                                </span>
                            </div>
                        </div>

                        <!-- Quick Action Buttons (Top Right on Hover) -->
                        <div class="absolute right-2 top-2 z-20 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                            <button
                                @click.stop="downloadDocument(document)"
                                class="rounded-md bg-white/95 backdrop-blur-md p-1 text-slate-700 hover:bg-red-50 hover:text-red-700 shadow-xs border border-slate-200 transition"
                                title="Download"
                            >
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </button>
                            <button
                                v-if="isEditable"
                                @click.stop="deleteDocument(document)"
                                class="rounded-md bg-white/95 backdrop-blur-md p-1 text-red-600 hover:bg-red-50 hover:text-red-700 shadow-xs border border-slate-200 transition"
                                title="Delete"
                            >
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- Picture / Thumbnail Area -->
                        <div class="aspect-[4/5] bg-slate-50 flex items-center justify-center p-2.5 relative overflow-hidden border-b border-slate-100">
                            <!-- Image Preview -->
                            <div class="w-full h-full flex items-center justify-center">
                                <img 
                                    v-if="isImageDocument(document)" 
                                    :src="getDocumentPreviewUrl(document)" 
                                    :alt="document.document_name" 
                                    loading="lazy"
                                    class="max-h-full max-w-full object-contain rounded drop-shadow-2xs transition-transform duration-200 group-hover:scale-105" 
                                />
                                <div v-else-if="isPdfDocument(document)" class="flex flex-col items-center justify-center text-center p-2">
                                    <div class="rounded-lg bg-red-50 border border-red-100 p-2.5 text-red-700 shadow-2xs mb-1 group-hover:scale-105 transition-transform">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-red-700">PDF Document</span>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center text-center p-2">
                                    <div class="rounded-lg bg-slate-100 border border-slate-200 p-2.5 text-slate-700 shadow-2xs mb-1 group-hover:scale-105 transition-transform">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-600">{{ document.document_type || 'FILE' }}</span>
                                </div>
                            </div>

                            <!-- Hover Overlay -->
                            <div class="absolute inset-0 bg-slate-950/30 opacity-0 group-hover:opacity-100 transition-opacity duration-150 flex items-center justify-center pointer-events-none">
                                <span class="inline-flex items-center gap-1 rounded bg-slate-950/80 backdrop-blur-xs px-2 py-1 text-[10px] font-bold text-white shadow">
                                    <svg class="h-3 w-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Preview
                                </span>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="p-2 bg-white flex flex-col justify-between flex-1 gap-1">
                            <div class="flex items-start justify-between gap-1">
                                <p class="text-xs font-semibold text-slate-900 truncate" :title="document.document_name">
                                    {{ document.document_name }}
                                </p>
                                <span class="shrink-0 text-[9px] font-bold uppercase px-1 py-0.2 rounded bg-slate-100 text-slate-600">
                                    {{ document.document_type }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-400">
                                <span>{{ formatFileSize(document.file_size) }}</span>
                                <span>{{ formatDate(document.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List View (Compact Table) -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                            <th class="py-2.5 px-3 w-10 text-center" v-if="isSelectingDocuments">
                                <span class="sr-only">Select</span>
                            </th>
                            <th class="py-2.5 px-3 w-16">Page</th>
                            <th class="py-2.5 px-3">Document Name</th>
                            <th class="py-2.5 px-3 w-20">Type</th>
                            <th class="py-2.5 px-3 w-24">Size</th>
                            <th class="py-2.5 px-3 w-36">Uploaded</th>
                            <th class="py-2.5 px-3 w-28 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr
                            v-for="document in paginatedDocuments"
                            :key="document.id"
                            @click="handleSelectDocument(document)"
                            class="hover:bg-slate-50/80 cursor-pointer transition"
                        >
                            <td class="py-2 px-3 text-center" v-if="isSelectingDocuments" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="selectedDocumentIds.includes(document.id)"
                                    @click.stop="toggleDocumentSelection(document)"
                                    class="h-3.5 w-3.5 rounded border-slate-300 text-red-600 focus:ring-red-500"
                                />
                            </td>
                            <td class="py-2 px-3 font-mono font-bold text-slate-700">
                                P{{ document.page_number || '—' }}
                            </td>
                            <td class="py-2 px-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-slate-100 text-slate-600 flex items-center justify-center text-[10px] font-bold uppercase shrink-0">
                                        {{ document.document_type || 'DOC' }}
                                    </div>
                                    <span class="font-semibold text-slate-900 truncate max-w-xs md:max-w-md" :title="document.document_name">
                                        {{ document.document_name }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-2 px-3 uppercase font-bold text-[10px] text-slate-500">
                                {{ document.document_type }}
                            </td>
                            <td class="py-2 px-3 text-slate-500 font-medium">
                                {{ formatFileSize(document.file_size) }}
                            </td>
                            <td class="py-2 px-3 text-slate-500">
                                {{ formatDate(document.created_at) }}
                            </td>
                            <td class="py-2 px-3 text-right" @click.stop>
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        @click.stop="handleSelectDocument(document)"
                                        class="px-2 py-1 text-[11px] font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded transition"
                                    >
                                        View
                                    </button>
                                    <button
                                        @click.stop="downloadDocument(document)"
                                        class="px-2 py-1 text-[11px] font-semibold text-red-700 hover:text-red-800 hover:bg-red-50 rounded transition"
                                    >
                                        Download
                                    </button>
                                    <button
                                        v-if="isEditable"
                                        @click.stop="deleteDocument(document)"
                                        class="p-1 text-slate-400 hover:text-red-600 transition"
                                        title="Delete"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Compact Pagination Bar -->
            <div v-if="totalPages > 1" class="px-4 py-2.5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-between text-xs">
                <div class="text-slate-500 text-[11px]">
                    Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredDocuments.length) }} of {{ filteredDocuments.length }} documents
                </div>
                <nav class="flex items-center gap-1">
                    <button
                        @click="changePage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="p-1 text-slate-500 hover:bg-slate-200 rounded disabled:opacity-30 disabled:cursor-not-allowed transition"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    
                    <template v-for="page in totalPages" :key="page">
                        <button
                            v-if="Math.abs(page - currentPage) <= 2 || page === 1 || page === totalPages"
                            @click="changePage(page)"
                            :class="[
                                'px-2.5 py-0.5 rounded text-xs font-semibold transition',
                                currentPage === page
                                    ? 'bg-red-700 text-white shadow-2xs font-bold'
                                    : 'text-slate-700 hover:bg-slate-200'
                            ]"
                        >
                            {{ page }}
                        </button>
                        <span v-else-if="Math.abs(page - currentPage) === 3" class="text-slate-400 px-1 text-xs">...</span>
                    </template>

                    <button
                        @click="changePage(currentPage + 1)"
                        :disabled="currentPage === totalPages"
                        class="p-1 text-slate-500 hover:bg-slate-200 rounded disabled:opacity-30 disabled:cursor-not-allowed transition"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </nav>
            </div>
        </div>

        <!-- Document Info Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showInfoModal"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="showInfoModal = false"></div>
                    
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                            <!-- Header -->
                            <div class="border-b border-gray-200 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="rounded-xl bg-blue-50 p-2">
                                            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Document Information</h3>
                                            <p class="text-sm text-gray-500">Overview of all documents</p>
                                        </div>
                                    </div>
                                    <button
                                        @click="showInfoModal = false"
                                        class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-6 space-y-4">
                                <!-- Summary Cards -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="rounded-lg bg-blue-500 p-2">
                                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-blue-900">Total Pages</p>
                                                <p class="text-xl font-bold text-blue-900">{{ totalDocuments }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="rounded-lg bg-emerald-500 p-2">
                                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7c0-2 1-3 3-3h10c2 0 3 1 3 3M4 7h16" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-emerald-900">Total Size</p>
                                                <p class="text-xl font-bold text-emerald-900">{{ formatFileSize(totalFileSize) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Type Breakdown -->
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">File Type Breakdown</h4>
                                    <div class="space-y-2">
                                        <div v-for="type in documentTypes.filter(t => t.value !== 'all')" :key="type.value">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs font-medium text-gray-600 uppercase">{{ type.label }}</span>
                                                <span class="text-xs font-medium text-gray-900">
                                                    {{ documents.filter(d => d.document_type === type.value).length }}
                                                </span>
                                            </div>
                                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full bg-blue-500 rounded-full transition-all duration-300"
                                                    :style="{ width: totalDocuments > 0 ? (documents.filter(d => d.document_type === type.value).length / totalDocuments * 100) + '%' : '0%' }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Processing Status -->
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Processing Status</h4>
                                    <div class="grid grid-cols-4 gap-2">
                                        <div
                                            v-for="status in Object.keys(processingStatuses)"
                                            :key="status"
                                            :class="[
                                                'rounded-lg p-3 text-center',
                                                processingStatuses[status].color
                                            ]"
                                        >
                                            <p class="text-lg font-bold">{{ documents.filter(d => d.processing_status === status).length }}</p>
                                            <p class="text-xs font-medium">{{ processingStatuses[status].label }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Document List Summary -->
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Rece   nt Documents</h4>
                                    <div class="space-y-2 max-h-48 overflow-y-auto">
                                        <div
                                            v-for="document in documents.slice().sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 5)"
                                            :key="document.id"
                                            class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50 p-2"
                                        >
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="text-xs font-medium text-gray-500 w-8">#{{ document.page_number || '—' }}</span>
                                                <span class="text-xs text-gray-900 truncate">{{ document.document_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-gray-400">{{ formatFileSize(document.file_size) }}</span>
                                                <span class="text-xs font-medium text-gray-500 uppercase">{{ document.document_type }}</span>
                                            </div>
                                        </div>
                                        <div v-if="documents.length === 0" class="text-center py-4">
                                            <p class="text-sm text-gray-400">No documents uploaded yet</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Info -->
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Additional Information</h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <p class="text-xs text-gray-500">Document Types</p>
                                            <p class="text-sm font-medium text-gray-900">{{ new Set(documents.map(d => d.document_type)).size }} unique types</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Average Size</p>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ totalDocuments > 0 ? formatFileSize(Math.round(totalFileSize / totalDocuments)) : '0 Bytes' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Editable</p>
                                            <p class="text-sm font-medium text-gray-900">{{ isEditable ? 'Yes' : 'No' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Project ID</p>
                                            <p class="text-sm font-medium text-gray-900">{{ projectId || '—' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">Last updated: {{ formatDate(new Date().toISOString()) }}</span>
                                    <button
                                        @click="showInfoModal = false"
                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Upload Queue Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showUploadQueueModal"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="showUploadQueueModal = false"></div>
                    
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                            <!-- Header -->
                            <div class="border-b border-gray-200 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Uploading Documents</h3>
                                        <p class="text-sm text-gray-500">
                                            {{ currentUploadIndex + 1 }} of {{ uploadQueue.length }} files
                                        </p>
                                    </div>
                                    <button
                                        @click="showUploadQueueModal = false"
                                        class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Upload List -->
                            <div class="max-h-96 overflow-y-auto p-6 space-y-3">
                                <div
                                    v-for="(item, index) in uploadQueue"
                                    :key="index"
                                    :class="[
                                        'flex items-center gap-4 rounded-lg border p-4 transition-all duration-200',
                                        item.status === 'pending' && 'border-gray-200 bg-white',
                                        item.status === 'uploading' && 'border-blue-200 bg-blue-50',
                                        item.status === 'completed' && 'border-emerald-200 bg-emerald-50',
                                        item.status === 'failed' && 'border-red-200 bg-red-50',
                                    ]"
                                >
                                    <!-- File Icon -->
                                    <div class="flex-shrink-0">
                                        <div :class="[
                                            'rounded-lg p-2',
                                            item.status === 'uploading' && 'bg-blue-100',
                                            item.status === 'completed' && 'bg-emerald-100',
                                            item.status === 'failed' && 'bg-red-100',
                                            item.status === 'pending' && 'bg-gray-100',
                                        ]">
                                            <svg class="h-5 w-5" :class="[
                                                item.status === 'uploading' && 'text-blue-600',
                                                item.status === 'completed' && 'text-emerald-600',
                                                item.status === 'failed' && 'text-red-600',
                                                item.status === 'pending' && 'text-gray-400',
                                            ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- File Info -->
                                    <div class="flex-1 min-w-0">
                                        <div v-if="item.previewKind === 'image'" class="mb-3 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                            <img :src="item.previewUrl" :alt="item.name" class="h-32 w-full object-contain" />
                                        </div>
                                        <iframe v-else-if="item.previewKind === 'pdf'" :src="item.previewUrl" :title="item.name" class="mb-3 h-32 w-full rounded-lg border border-gray-200 bg-white"></iframe>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium text-gray-500">Page {{ item.pageNumber }}</span>
                                            <span class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</span>
                                        </div>
                                        <div class="mt-2">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                    <div
                                                        class="h-full rounded-full transition-all duration-300"
                                                        :class="{
                                                            'bg-blue-500': item.status === 'uploading',
                                                            'bg-emerald-500': item.status === 'completed',
                                                            'bg-red-500': item.status === 'failed',
                                                        }"
                                                        :style="{ width: item.progress + '%' }"
                                                    ></div>
                                                </div>
                                                <span class="text-xs font-medium text-gray-600 w-10 text-right">{{ item.progress }}%</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Icon -->
                                    <div class="flex-shrink-0">
                                        <svg v-if="item.status === 'pending'" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l2 2m6-2a8 8 0 11-16 0 8 8 0 0116 0z"/></svg>
                                        <svg v-else-if="item.status === 'uploading'" class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0L8 8m4-4l4 4M5 20h14"/></svg>
                                        <svg v-else-if="item.status === 'completed'" class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else-if="item.status === 'failed'" class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">
                                        {{ uploadQueue.filter(i => i.status === 'completed').length }} completed, 
                                        {{ uploadQueue.filter(i => i.status === 'failed').length }} failed
                                    </span>
                                    <button
                                        @click="showUploadQueueModal = false"
                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>


        <!-- Professional Fullscreen Document Preview Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showPreview && selectedDocument"
                    class="fixed inset-0 z-50 overflow-hidden bg-slate-950/95 backdrop-blur-md flex flex-col justify-between select-none"
                    role="dialog"
                    aria-modal="true"
                >
                    <!-- TOP TOOLBAR -->
                    <div class="h-16 px-4 sm:px-6 bg-slate-900/90 border-b border-white/10 flex items-center justify-between z-30 flex-shrink-0 backdrop-blur-md">
                        <!-- Left: Document Info & Back Button -->
                        <div class="flex items-center gap-3 min-w-0 max-w-[50%]">
                            <button
                                @click="closePreview"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white/90 hover:text-white px-3 py-2 text-xs font-semibold tracking-wide transition-all duration-200 border border-white/10 shadow-sm flex-shrink-0"
                                title="Close Preview (Esc)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span class="hidden sm:inline">Close</span>
                            </button>

                            <div class="h-5 w-px bg-white/15 hidden sm:block flex-shrink-0"></div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm sm:text-base font-semibold text-white truncate" :title="selectedDocument.document_name">
                                        {{ selectedDocument.document_name }}
                                    </h3>
                                    <span class="flex-shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-400/30">
                                        {{ selectedDocument.document_type }}
                                    </span>
                                </div>
                                <p class="text-xs text-white/60 truncate flex items-center gap-2 mt-0.5">
                                    <span>Page {{ selectedDocument.page_number || '—' }}</span>
                                    <span>·</span>
                                    <span>{{ formatFileSize(selectedDocument.file_size) }}</span>
                                    <span>·</span>
                                    <span>{{ formatDate(selectedDocument.created_at) }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Center: Document Index Quick Navigator -->
                        <div class="hidden md:flex items-center gap-1.5 bg-black/40 border border-white/10 rounded-full px-3 py-1 shadow-inner">
                            <button
                                @click="previousDocument"
                                :disabled="currentDocumentIndex <= 0"
                                class="p-1 rounded-full text-white/70 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                                title="Previous Document (← Left Arrow)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <span class="text-xs font-semibold text-white/90 px-2 tracking-wide">
                                {{ currentDocumentIndex + 1 }} of {{ filteredDocuments.length }}
                            </span>
                            <button
                                @click="nextDocument"
                                :disabled="currentDocumentIndex >= filteredDocuments.length - 1"
                                class="p-1 rounded-full text-white/70 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                                title="Next Document (→ Right Arrow)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>

                        <!-- Right: Zoom & Action Buttons -->
                        <div class="flex items-center gap-2">
                            <!-- Image Zoom Controls (if image) -->
                            <div v-if="isImageDocument(selectedDocument)" class="flex items-center gap-1 bg-black/40 border border-white/10 rounded-xl p-1">
                                <button
                                    @click="zoomOut"
                                    class="p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                                    title="Zoom Out (-)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                    </svg>
                                </button>
                                <button
                                    @click="resetPreviewView"
                                    class="px-2 py-1 text-xs font-mono font-semibold text-white/90 hover:bg-white/10 rounded-md transition-colors"
                                    title="Reset Zoom & Rotation (0)"
                                >
                                    {{ Math.round(previewZoom * 100) }}%
                                </button>
                                <button
                                    @click="zoomIn"
                                    class="p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                                    title="Zoom In (+)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                                <div class="h-4 w-px bg-white/10 my-auto"></div>
                                <button
                                    @click="rotatePreview"
                                    class="p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                                    title="Rotate 90° (R)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Open Raw in New Tab -->
                            <button
                                @click="openInNewTab(selectedDocument)"
                                class="hidden sm:inline-flex items-center gap-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white px-3 py-2 text-xs font-semibold tracking-wide transition-all border border-white/10"
                                title="Open Original File in New Tab"
                            >
                                <svg class="h-4 w-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                <span>Open</span>
                            </button>

                            <!-- Download Button -->
                            <button
                                @click="downloadDocument(selectedDocument)"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white px-3.5 py-2 text-xs font-semibold tracking-wide shadow-lg shadow-blue-600/30 transition-all duration-200"
                                title="Download Document"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span class="hidden sm:inline">Download</span>
                            </button>
                        </div>
                    </div>

                    <!-- MAIN VIEWPORT AREA -->
                    <div class="relative flex-1 overflow-hidden flex items-center justify-center p-2 sm:p-6">
                        <!-- Floating Previous Document Button -->
                        <button
                            v-if="currentDocumentIndex > 0"
                            @click="previousDocument"
                            class="absolute left-3 sm:left-6 z-30 p-3 rounded-full bg-slate-900/80 hover:bg-slate-800 text-white/80 hover:text-white border border-white/15 shadow-2xl backdrop-blur-md transition-all duration-200 hover:scale-110 active:scale-95"
                            title="Previous Document (← Left Arrow)"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <!-- Document Display -->
                        <div class="w-full h-full flex items-center justify-center overflow-auto p-2">
                            <!-- Image View -->
                            <div 
                                v-if="isImageDocument(selectedDocument)"
                                class="flex items-center justify-center w-full h-full"
                            >
                                <img
                                    :src="getDocumentPreviewUrl(selectedDocument)"
                                    :alt="selectedDocument.document_name"
                                    class="max-h-[78vh] max-w-[90vw] object-contain rounded-lg shadow-2xl transition-transform duration-200 select-none"
                                    :style="{
                                        transform: `scale(${previewZoom}) rotate(${previewRotation}deg)`,
                                        transformOrigin: 'center center',
                                    }"
                                    draggable="false"
                                />
                            </div>

                            <!-- PDF View -->
                            <div 
                                v-else-if="isPdfDocument(selectedDocument)"
                                class="w-full h-full max-w-5xl max-h-[80vh] rounded-2xl overflow-hidden shadow-2xl border border-white/10 bg-white"
                            >
                                <iframe
                                    :src="getDocumentPreviewUrl(selectedDocument)"
                                    :title="selectedDocument.document_name"
                                    class="w-full h-full border-0"
                                ></iframe>
                            </div>

                            <!-- Other File Format Fallback -->
                            <div 
                                v-else 
                                class="bg-slate-900/80 border border-white/15 rounded-3xl p-10 max-w-md text-center shadow-2xl backdrop-blur-md flex flex-col items-center"
                            >
                                <div class="rounded-3xl bg-blue-500/10 border border-blue-500/20 p-5 text-blue-400 mb-4 shadow-inner">
                                    <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <h4 class="text-lg font-bold text-white mb-1">{{ selectedDocument.document_name }}</h4>
                                <p class="text-xs text-white/60 mb-6">This document type ({{ selectedDocument.document_type?.toUpperCase() }}) can be downloaded or opened directly in your default office viewer.</p>
                                <button
                                    @click="downloadDocument(selectedDocument)"
                                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 text-sm font-semibold tracking-wide shadow-lg shadow-blue-600/30 transition-all duration-200"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download to View File
                                </button>
                            </div>
                        </div>

                        <!-- Floating Next Document Button -->
                        <button
                            v-if="currentDocumentIndex < filteredDocuments.length - 1"
                            @click="nextDocument"
                            class="absolute right-3 sm:right-6 z-30 p-3 rounded-full bg-slate-900/80 hover:bg-slate-800 text-white/80 hover:text-white border border-white/15 shadow-2xl backdrop-blur-md transition-all duration-200 hover:scale-110 active:scale-95"
                            title="Next Document (→ Right Arrow)"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- BOTTOM STATUS & METADATA BAR -->
                    <div class="h-16 px-4 sm:px-6 bg-slate-900/90 border-t border-white/10 flex items-center justify-between z-30 flex-shrink-0 backdrop-blur-md">
                        <!-- Left: Page Quick Edit & Details -->
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-xs font-semibold text-white/80">
                                <span>Page Number:</span>
                                <input 
                                    v-model.number="selectedDocument.page_number" 
                                    type="number" 
                                    min="1" 
                                    class="w-16 rounded-lg border border-white/20 bg-black/40 text-white px-2.5 py-1 text-xs font-mono font-bold text-center focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" 
                                    @change="savePageNumber(selectedDocument)" 
                                />
                            </label>
                            <span class="text-xs text-white/40 hidden sm:inline">|</span>
                            <span class="text-xs text-white/60 hidden sm:inline">
                                File Size: <strong class="text-white/90">{{ formatFileSize(selectedDocument.file_size) }}</strong>
                            </span>
                        </div>

                        <!-- Right: Actions -->
                        <div class="flex items-center gap-2">
                            <button
                                v-if="isEditable"
                                @click="deleteDocument(selectedDocument)"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-red-500/15 hover:bg-red-500/25 text-red-400 hover:text-red-300 border border-red-500/30 px-3 py-1.5 text-xs font-semibold transition-colors"
                                title="Delete this document"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Delete</span>
                            </button>
                            <button
                                @click="closePreview"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white px-4 py-1.5 text-xs font-semibold transition-colors"
                            >
                                Close Viewer
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- In-App System Camera Viewfinder Modal (Front/Back, Zero-Refresh) -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showCameraModal"
                    class="fixed inset-0 z-50 overflow-hidden bg-black/95 backdrop-blur-md flex flex-col items-center justify-center p-0 sm:p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="relative w-full h-full sm:h-auto sm:max-h-[92vh] sm:max-w-2xl bg-black sm:bg-slate-900 text-white sm:rounded-3xl overflow-hidden shadow-2xl border-0 sm:border border-slate-800 flex flex-col">
                        <!-- Top Bar -->
                        <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-b from-black/80 to-transparent absolute sm:relative top-0 inset-x-0 z-30">
                            <div class="flex items-center gap-2.5 bg-black/50 backdrop-blur-md px-3 py-1.5 rounded-full border border-white/10">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-xs font-semibold text-white tracking-wide">
                                    {{ cameraFacingMode === 'environment' ? 'Back Camera' : 'Front Camera' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Torch/Flash Toggle if supported -->
                                <button
                                    v-if="hasTorch"
                                    @click="toggleTorch"
                                    type="button"
                                    :class="['p-2.5 rounded-full backdrop-blur-md border transition', torchEnabled ? 'bg-amber-500 text-white border-amber-400 shadow-lg shadow-amber-500/30' : 'bg-black/50 text-white/80 border-white/10 hover:bg-black/70']"
                                    title="Toggle Flash"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </button>

                                <!-- Switch Camera (Back / Front) -->
                                <button
                                    @click="switchCameraFacing"
                                    type="button"
                                    class="p-2.5 rounded-full bg-black/50 hover:bg-black/70 text-white backdrop-blur-md border border-white/10 transition active:scale-95"
                                    title="Switch Camera (Back / Front)"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>

                                <!-- Close Camera -->
                                <button
                                    @click="closeCameraModal"
                                    type="button"
                                    class="p-2.5 rounded-full bg-black/50 hover:bg-black/70 text-white/80 hover:text-white backdrop-blur-md border border-white/10 transition"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Live Viewfinder Video -->
                        <div class="relative flex-1 bg-black flex items-center justify-center min-h-[380px] sm:min-h-[500px] overflow-hidden">
                            <video
                                ref="cameraVideoRef"
                                autoplay
                                playsinline
                                webkit-playsinline
                                muted
                                :style="{ transform: cameraFacingMode === 'user' ? 'scaleX(-1)' : 'none' }"
                                class="w-full h-full object-cover sm:object-contain max-h-[75vh]"
                            ></video>

                            <!-- Document Alignment Guide Frame Overlay -->
                            <div class="absolute inset-8 sm:inset-12 pointer-events-none border border-white/20 rounded-2xl flex flex-col justify-between p-4 shadow-2xl">
                                <div class="flex justify-between">
                                    <div class="w-7 h-7 border-t-2 border-l-2 border-blue-400 rounded-tl-lg"></div>
                                    <div class="w-7 h-7 border-t-2 border-r-2 border-blue-400 rounded-tr-lg"></div>
                                </div>
                                <div class="text-center">
                                    <span class="bg-black/70 backdrop-blur-md text-white/90 px-3.5 py-1.5 rounded-full text-xs font-medium tracking-wide shadow-lg border border-white/10">
                                        Align document inside frame
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <div class="w-7 h-7 border-b-2 border-l-2 border-blue-400 rounded-bl-lg"></div>
                                    <div class="w-7 h-7 border-b-2 border-r-2 border-blue-400 rounded-br-lg"></div>
                                </div>
                            </div>

                            <!-- Camera Loading Overlay -->
                            <div v-if="isCameraLoading" class="absolute inset-0 bg-black/90 flex flex-col items-center justify-center gap-3 z-20">
                                <div class="h-10 w-10 animate-spin rounded-full border-3 border-slate-700 border-t-blue-500"></div>
                                <p class="text-xs text-slate-300">Starting in-app camera...</p>
                            </div>

                            <!-- Camera Permission / Error Overlay with Fallback -->
                            <div v-else-if="cameraError" class="absolute inset-0 bg-slate-950/95 p-6 flex flex-col items-center justify-center text-center gap-4 z-20">
                                <div class="w-14 h-14 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    </svg>
                                </div>
                                <div class="max-w-sm">
                                    <h4 class="text-sm font-semibold text-white">Camera Access</h4>
                                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ cameraError }}</p>
                                </div>
                                <div class="flex flex-wrap items-center justify-center gap-2.5 mt-2">
                                    <button
                                        @click="closeCameraModal(); triggerNativeCameraCapture()"
                                        type="button"
                                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold transition shadow-lg shadow-emerald-500/25 inline-flex items-center gap-2"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Capture with Device Camera</span>
                                    </button>
                                    <button @click="closeCameraModal(); triggerScanCapture()" type="button" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>Choose Photo</span>
                                    </button>
                                    <button @click="startCameraStream" type="button" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition inline-flex items-center gap-1.5">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span>Retry Stream</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Shutter Bar -->
                        <div class="px-8 py-5 bg-gradient-to-t from-black via-black/90 to-transparent sm:bg-slate-900 absolute sm:relative bottom-0 inset-x-0 z-30 flex items-center justify-between">
                            <!-- Gallery Button -->
                            <button
                                @click="closeCameraModal(); triggerScanCapture()"
                                type="button"
                                class="flex flex-col items-center gap-1 text-slate-300 hover:text-white transition group"
                                title="Choose photo from device"
                            >
                                <div class="p-3 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md transition">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span class="text-[10px] text-slate-400 font-medium">Gallery</span>
                            </button>

                            <!-- Big Shutter Button -->
                            <button
                                @click="captureSnapshot"
                                :disabled="isCameraLoading || !!cameraError"
                                type="button"
                                class="w-18 h-18 sm:w-20 sm:h-20 rounded-full border-4 border-white p-1.5 flex items-center justify-center hover:scale-105 active:scale-95 transition-all disabled:opacity-40 disabled:pointer-events-none shadow-2xl shadow-blue-500/30"
                                title="Capture Photo"
                            >
                                <div class="w-full h-full rounded-full bg-white hover:bg-blue-50 transition-colors shadow-inner"></div>
                            </button>

                            <!-- Switch Front/Back Button -->
                            <button
                                @click="switchCameraFacing"
                                type="button"
                                class="flex flex-col items-center gap-1 text-slate-300 hover:text-white transition group"
                                title="Flip Camera"
                            >
                                <div class="p-3 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md transition active:rotate-180 duration-300">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <span class="text-[10px] text-slate-400 font-medium">Flip</span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- CamScanner Document Filter Editor Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showFilterModal"
                    class="fixed inset-0 z-50 overflow-y-auto bg-black/85 backdrop-blur-sm flex flex-col items-center justify-center p-2 sm:p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="relative w-full max-w-4xl bg-slate-900 text-white rounded-2xl overflow-hidden shadow-2xl border border-slate-800 flex flex-col max-h-[96vh]">
                        <!-- Editor Header -->
                        <div class="flex items-center justify-between px-6 py-3.5 border-b border-slate-800 bg-slate-900/90 backdrop-blur z-20">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center border border-blue-500/30">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Document Enhancer</h3>
                                    <p class="text-[11px] text-slate-400">Choose filter & fine-tune before saving</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="rotateClockwise"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition"
                                    title="Rotate 90° Clockwise"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span>Rotate</span>
                                </button>
                                <button
                                    @click="showFilterModal = false"
                                    type="button"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Canvas Workspace -->
                        <div class="relative bg-slate-950 flex-1 flex items-center justify-center p-3 sm:p-4 min-h-[260px] sm:min-h-[440px] overflow-auto">
                            <div v-if="isProcessingImage" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-slate-950/80 z-10">
                                <div class="h-8 w-8 animate-spin rounded-full border-3 border-slate-700 border-t-blue-500"></div>
                                <p class="text-xs text-slate-400">Loading document...</p>
                            </div>
                            <canvas
                                ref="filterCanvasRef"
                                class="max-h-[50vh] sm:max-h-[55vh] max-w-full w-auto h-auto object-contain rounded-lg shadow-2xl border border-slate-800/80 bg-white block"
                            ></canvas>
                        </div>

                        <!-- Filter Controls & Presets -->
                        <div class="border-t border-slate-800 bg-slate-900 p-4 sm:p-5 flex flex-col gap-4">
                            <!-- Preset Buttons -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Document Filters</span>
                                    <button @click="resetAdjustments" type="button" class="inline-flex items-center gap-1 text-[11px] text-blue-400 hover:text-blue-300 font-medium">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span>Reset All</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-5 gap-2">
                                    <button
                                        v-for="preset in filterPresets"
                                        :key="preset.id"
                                        @click="selectedFilter = preset.id"
                                        type="button"
                                        :class="[
                                            'p-2.5 rounded-xl border flex flex-col items-center gap-1.5 transition text-center group',
                                            selectedFilter === preset.id
                                                ? 'bg-blue-600/20 border-blue-500 text-white shadow-lg shadow-blue-500/10'
                                                : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                                        ]"
                                    >
                                        <div :class="['p-1.5 rounded-lg transition', selectedFilter === preset.id ? 'text-blue-400 bg-blue-500/20' : 'text-slate-400 group-hover:text-slate-200']">
                                            <!-- Magic Color: Sparkle / Magic Wand -->
                                            <svg v-if="preset.id === 'magic'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                            </svg>
                                            <!-- B&W Clean: Document Text -->
                                            <svg v-else-if="preset.id === 'bw'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <!-- Grayscale: Half circle / contrast -->
                                            <svg v-else-if="preset.id === 'mono'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                            </svg>
                                            <!-- Real Doc: Camera/Doc View -->
                                            <svg v-else-if="preset.id === 'real_document'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <!-- Original: Image Palette -->
                                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <span class="text-[11px] font-semibold tracking-tight">{{ preset.name }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Fine-Tuning Sliders -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-800/60 text-xs">
                                <div class="flex items-center gap-3">
                                    <span class="text-slate-400 text-[11px] font-medium w-16">Brightness</span>
                                    <input
                                        v-model.number="brightness"
                                        type="range"
                                        min="-50"
                                        max="50"
                                        class="flex-1 h-1.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-500"
                                    />
                                    <span class="text-slate-400 text-[11px] font-mono w-7 text-right">{{ brightness }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-slate-400 text-[11px] font-medium w-16">Contrast</span>
                                    <input
                                        v-model.number="contrast"
                                        type="range"
                                        min="-50"
                                        max="50"
                                        class="flex-1 h-1.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-500"
                                    />
                                    <span class="text-slate-400 text-[11px] font-mono w-7 text-right">{{ contrast }}</span>
                                </div>
                            </div>

                            <!-- Actions Footer -->
                            <div class="flex items-center justify-between pt-3 border-t border-slate-800">
                                <button
                                    @click="showFilterModal = false; openCameraModal()"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium transition"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>Retake Photo</span>
                                </button>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="saveAndUploadScannedDocument(true)"
                                        :disabled="isSavingScan"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-blue-400 border border-blue-500/30 text-xs font-semibold transition disabled:opacity-50"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>Save & Scan Next</span>
                                    </button>
                                    <button
                                        @click="saveAndUploadScannedDocument(false)"
                                        :disabled="isSavingScan"
                                        type="button"
                                        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/30 transition disabled:opacity-50"
                                    >
                                        <svg v-if="isSavingScan" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ isSavingScan ? 'Uploading...' : 'Save & Upload Document' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* Transition animations */
.slide-fade-enter-active {
    transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
    transition: all 0.2s ease-in;
}

.slide-fade-enter-from {
    transform: translateX(20px);
    opacity: 0;
}

.slide-fade-leave-to {
    transform: translateX(20px);
    opacity: 0;
}

.modal-enter-active {
    transition: all 0.3s ease-out;
}

.modal-leave-active {
    transition: all 0.2s ease-in;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
    opacity: 0;
}

/* Line clamp utilities */
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Custom scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Hover scale effect */
.group:hover {
    transform: translateY(-2px);
}

/* Focus visible styles */
*:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Scanner modal: fullscreen on mobile */
@media (max-width: 639px) {
    .scanner-modal-container {
        min-height: 100dvh;
        min-height: 100vh;
        border-radius: 0;
        display: flex;
        flex-direction: column;
    }

    .scanner-camera-view {
        flex: 1;
        min-height: 0;
    }
}

@media (min-width: 640px) {
    .scanner-camera-view {
        aspect-ratio: 4 / 3;
    }
}

/* Safe area padding for notched devices (iPhone etc.) */
.scanner-safe-top {
    padding-top: max(0.75rem, env(safe-area-inset-top));
}

.scanner-safe-bottom {
    padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
}

/* Capture button outer ring */
.scanner-capture-btn {
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.3), 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Print styles */
    @media print {
    .modal {
        position: static !important;
    }
    
    .modal > div:first-child {
        display: none !important;
    }
}
</style>