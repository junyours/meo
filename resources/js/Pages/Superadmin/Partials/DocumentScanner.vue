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

const toast = ref({ show: false, message: '', type: 'success' });
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const documents = ref([]);
const selectedDocument = ref(null);
const showPreview = ref(false);
const searchTerm = ref('');
const filterType = ref('all');
const showScannerModal = ref(false);
const scannerVideoRef = ref(null);
const cameraStream = ref(null);
const capturedImage = ref(null);
const selectedFilePreview = ref(null);
const selectedDocumentIds = ref([]);
const isSelectingDocuments = ref(false);

const documentTypes = [
    { value: 'all', label: 'All Types' },
    { value: 'pdf', label: 'PDF' },
    { value: 'jpg', label: 'JPG' },
    { value: 'png', label: 'PNG' },
    { value: 'docx', label: 'DOCX' },
];

const processingStatuses = {
    pending: { label: 'Pending', color: 'bg-gray-100 text-gray-700' },
    processing: { label: 'Processing', color: 'bg-blue-100 text-blue-700' },
    completed: { label: 'Completed', color: 'bg-emerald-100 text-emerald-700' },
    failed: { label: 'Failed', color: 'bg-red-100 text-red-700' },
};

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

    return filtered;
});

const isImageDocument = (document) => ['jpg', 'jpeg', 'png'].includes(document?.document_type?.toLowerCase());
const isPdfDocument = (document) => document?.document_type?.toLowerCase() === 'pdf';
const getDocumentPreviewUrl = (document) => `/superadmin/documents/${document.id}/preview`;

const previousDocument = () => {
    const index = filteredDocuments.value.findIndex(document => document.id === selectedDocument.value?.id);
    if (index > 0) selectedDocument.value = filteredDocuments.value[index - 1];
};
const nextDocument = () => {
    const index = filteredDocuments.value.findIndex(document => document.id === selectedDocument.value?.id);
    if (index >= 0 && index < filteredDocuments.value.length - 1) selectedDocument.value = filteredDocuments.value[index + 1];
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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

const handleDragOver = (e) => {
    e.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (e) => {
    e.preventDefault();
    isDragging.value = false;
};

const handleDrop = (e) => {
    e.preventDefault();
    isDragging.value = false;
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        uploadFile(files[0]);
    }
};

const handleFileSelect = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        uploadFile(files[0]);
    }
};

const uploadFile = async (file) => {
    if (!props.isEditable) return;

    selectedFilePreview.value = {
        name: file.name,
        url: URL.createObjectURL(file),
        kind: file.type === 'application/pdf' ? 'pdf' : file.type.startsWith('image/') ? 'image' : 'file',
    };

    isUploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_name', file.name);
    formData.append('page_number', String(documents.value.length + 1));
    if (props.projectId) formData.append('project_id', props.projectId);
    if (props.techprepId) formData.append('techprep_id', props.techprepId);

    try {
        const response = await window.axios.post('/superadmin/documents', formData, {
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            },
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        documents.value.unshift(response.data.document);
        emit('document-uploaded', response.data.document);
        showToast('Document uploaded successfully!', 'success');
    } catch (error) {
        console.error('Upload error:', error.response?.data || error);
        showToast(error.response?.data?.message || 'Failed to upload document. Please try again.', 'error');
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

const bindVideoTrack = async () => {
    if (!scannerVideoRef.value || !cameraStream.value) return;
    if (scannerVideoRef.value.srcObject !== cameraStream.value) {
        scannerVideoRef.value.srcObject = cameraStream.value;
    }
    try {
        await scannerVideoRef.value.play();
    } catch (e) {
        console.warn('Camera video play warning:', e);
    }
};

watch([scannerVideoRef, cameraStream, showScannerModal], () => {
    nextTick(() => {
        bindVideoTrack();
    });
}, { immediate: true });

const callGetUserMedia = (constraints) => {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        return navigator.mediaDevices.getUserMedia(constraints);
    }
    const legacyFn = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
    if (legacyFn) {
        return new Promise((resolve, reject) => {
            legacyFn.call(navigator, constraints, resolve, reject);
        });
    }
    return Promise.reject(new Error('MEDIA_DEVICES_NOT_SUPPORTED'));
};

const openScanner = async () => {
    showScannerModal.value = true;
    capturedImage.value = null;
    
    // Stop any existing stream first
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => {
            try { track.stop(); } catch (e) {}
        });
        cameraStream.value = null;
    }

    try {
        let stream = null;
        const attempts = [
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            { video: { width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
            { video: true, audio: false }
        ];

        let lastErr = null;
        for (const constraints of attempts) {
            try {
                stream = await callGetUserMedia(constraints);
                if (stream) break;
            } catch (err) {
                lastErr = err;
            }
        }

        if (!stream) throw lastErr || new Error('Could not obtain stream');

        cameraStream.value = stream;
        await nextTick();
        await bindVideoTrack();
    } catch (error) {
        console.error('Camera access error:', error);
        showToast('Could not access live camera. Try Quick Photo Capture.', 'error');
        showScannerModal.value = false;
    }
};

const closeScanner = () => {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => track.stop());
        cameraStream.value = null;
    }
    capturedImage.value = null;
    showScannerModal.value = false;
};

const captureImage = () => {
    const video = scannerVideoRef.value || document.getElementById('scanner-video');
    if (!video || !cameraStream.value) return;

    const width = video.videoWidth || 1280;
    const height = video.videoHeight || 960;
    if (!width || !height) return;

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(video, 0, 0, width, height);
    capturedImage.value = canvas.toDataURL('image/jpeg', 0.92);
};

const uploadCapturedImage = () => {
    if (!capturedImage.value) return;

    const canvas = document.createElement('canvas');
    const img = new Image();
    img.onload = () => {
        canvas.width = img.width;
        canvas.height = img.height;
        canvas.getContext('2d').drawImage(img, 0, 0);
        
        canvas.toBlob(async (blob) => {
            const file = new File([blob], 'scanned_document.jpg', { type: 'image/jpeg' });
            await uploadFile(file);
            closeScanner();
        }, 'image/jpeg');
    };
    img.src = capturedImage.value;
};

const handleSelectDocument = (doc) => {
    selectedDocument.value = doc;
    showPreview.value = true;
    emit('document-selected', doc);
};

const closePreview = () => {
    showPreview.value = false;
    selectedDocument.value = null;
};

const downloadDocument = async (document) => {
    try {
        const response = await window.axios.get(`/superadmin/documents/${document.id}/download`, {
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
        showToast('Failed to download document.', 'error');
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
    if (!confirm('Are you sure you want to delete this document?')) return;

    try {
        await window.axios.delete(`/superadmin/documents/${document.id}`);
        documents.value = documents.value.filter(d => d.id !== document.id);
        showToast('Document deleted successfully.', 'success');
    } catch (error) {
        console.error('Delete error:', error);
        showToast('Failed to delete document.', 'error');
    }
};

const savePageNumber = async (document) => {
    const pageNumber = Number(document.page_number);
    if (!Number.isInteger(pageNumber) || pageNumber < 1) return;
    try {
        const response = await window.axios.put(`/superadmin/documents/${document.id}`, { page_number: pageNumber });
        Object.assign(document, response.data.document);
        showToast('Page number updated', 'success');
    } catch (error) {
        console.error('Page update error:', error);
        showToast('Failed to update page number', 'error');
    }
};

const loadDocuments = async () => {
    try {
        const params = {};
        if (props.projectId) params.project_id = props.projectId;
        if (props.techprepId) params.techprep_id = props.techprepId;

        const response = await window.axios.get('/superadmin/documents', { params });
        documents.value = response.data.data;
    } catch (error) {
        console.error('Load documents error:', error);
    }
};

watch(() => [props.projectId, props.techprepId], () => {
    loadDocuments();
}, { immediate: true });

onMounted(() => {
    loadDocuments();
});

onUnmounted(() => {
    closeScanner();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Toast -->
        <div
            v-if="toast.show"
            :class="[
                'fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg transition-all',
                toast.type === 'success' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white',
            ]"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="toast.type === 'success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'"
                />
            </svg>
            <span class="text-sm font-medium">{{ toast.message }}</span>
        </div>

        <!-- ===== HEADER ===== -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Document Scanner</h3>
                        <p class="text-xs text-gray-500">Upload, scan, and manage project documents</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Total Documents</p>
                        <p class="text-xl font-bold text-red-600">{{ documents.length }}</p>
                    </div>
                    <div class="relative h-12 w-12">
                        <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15" fill="none" stroke="#f3f4f6" stroke-width="3" />
                            <circle
                                cx="18"
                                cy="18"
                                r="15"
                                fill="none"
                                stroke="#dc2626"
                                stroke-width="3"
                                :stroke-dasharray="`${(documents.length > 0 ? 100 : 0) * 0.94} 94`"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                </div>
            </div>

        </div>
        <!-- ===== UPLOAD SECTION ===== -->
        <div v-if="isEditable" class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-3">
                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <h4 class="text-sm font-medium text-gray-900">Upload Document</h4>
            </div>
            <div class="p-6">
                <div
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop"
                    :class="[
                        'relative rounded-lg border-2 border-dashed p-8 text-center transition',
                        isDragging ? 'border-red-500 bg-red-50' : 'border-gray-300 hover:border-red-400 hover:bg-gray-50'
                    ]"
                >
                    <input
                        type="file"
                        ref="fileInput"
                        @change="handleFileSelect"
                        accept=".pdf,.jpg,.jpeg,.png,.docx,.doc,.txt"
                        class="absolute inset-0 cursor-pointer opacity-0"
                    />
                    
                    <div class="flex flex-col items-center gap-3">
                        <div class="rounded-full bg-red-100 p-4">
                            <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                Drag and drop your document here, or click to browse
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                Supports PDF, JPG, PNG, DOCX, TXT (Max 50MB)
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Scanner Button -->
                <button
                    @click="openScanner"
                    type="button"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Scan Document with Camera
                </button>

                <!-- Upload Progress -->
                <div v-if="isUploading" class="mt-4">
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="text-gray-600">Uploading...</span>
                        <span class="font-medium text-gray-900">{{ uploadProgress }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-200">
                        <div
                            class="h-full rounded-full bg-red-600 transition-all duration-300"
                            :style="{ width: uploadProgress + '%' }"
                        ></div>
                    </div>
                </div>
                <div v-if="selectedFilePreview" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="mb-2 text-xs font-medium text-gray-600">Selected file preview</p>
                    <img v-if="selectedFilePreview.kind === 'image'" :src="selectedFilePreview.url" :alt="selectedFilePreview.name" class="max-h-64 w-full rounded-lg object-contain" />
                    <iframe v-else-if="selectedFilePreview.kind === 'pdf'" :src="selectedFilePreview.url" :title="selectedFilePreview.name" class="h-64 w-full rounded-lg border border-gray-200 bg-white"></iframe>
                    <div v-else class="flex items-center gap-2 rounded-lg bg-white p-4 text-sm text-gray-600">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 3h7l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 3v5h5M9 13h6m-6 4h6"/></svg>
                        {{ selectedFilePreview.name }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== DOCUMENTS LIST SECTION ===== -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h4 class="text-sm font-medium text-gray-900">Documents</h4>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button @click="toggleSelectionMode" class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        {{ isSelectingDocuments ? 'Cancel Selection' : 'Select Documents' }}
                    </button>
                    <button v-if="isSelectingDocuments" @click="downloadSelectedDocuments" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>
                        Batch Download ({{ selectedDocumentIds.length }})
                    </button>
                    <div class="relative">
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search documents..."
                            class="w-64 rounded-lg border border-gray-300 px-4 py-2 pl-10 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                        />
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    
                    <select
                        v-model="filterType"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                    >
                        <option v-for="type in documentTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="filteredDocuments.length === 0" class="p-12 text-center">
                <div class="mx-auto mb-4 rounded-full bg-gray-100 p-4 w-fit">
                    <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <p class="text-sm text-gray-500">No documents found</p>
            </div>

            <div v-else class="divide-y divide-gray-100">
                <div
                    v-for="document in filteredDocuments"
                    :key="document.id"
                    @click="handleSelectDocument(document)"
                    class="px-6 py-4 hover:bg-gray-50 transition"
                >
                    <div class="flex flex-wrap items-start gap-4">
                        <input v-if="isSelectingDocuments" type="checkbox" :checked="selectedDocumentIds.includes(document.id)" @click.stop="toggleDocumentSelection(document)" class="mt-1 h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500" aria-label="Select document" />
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                            <img v-if="isImageDocument(document)" :src="getDocumentPreviewUrl(document)" :alt="document.document_name" class="h-full w-full object-cover" />
                            <svg v-else class="mx-auto mt-3 h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 3h7l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 3v5h5M9 13h6m-6 4h6"/></svg>
                        </div>
                        <!-- Left: Info -->
                        <div class="flex-1 min-w-[180px]">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-gray-900">{{ document.document_name }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span>{{ formatFileSize(document.file_size) }}</span>
                                <span class="mx-1">·</span>
                                <span>{{ formatDate(document.created_at) }}</span>
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2" @click.stop>
                            <button
                                @click="downloadDocument(document)"
                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                                title="Download"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </button>
                            <button
                                v-if="isEditable"
                                @click="deleteDocument(document)"
                                class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 transition"
                                title="Delete"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== FOOTER ===== -->
        <div class="bg-white rounded-lg border border-gray-200 px-6 py-3 flex items-center justify-between">
            <span class="text-xs text-gray-500">
                Total documents: <strong class="text-gray-700">{{ documents.length }}</strong>
            </span>
            <button
                @click="loadDocuments"
                class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>

        <!-- Scanner Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showScannerModal"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="fixed inset-0 bg-black/80" @click="closeScanner"></div>
                    
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="relative w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl">
                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                                <h3 class="text-lg font-semibold text-gray-900">Document Scanner</h3>
                                <button
                                    @click="closeScanner"
                                    class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Camera View -->
                            <div class="relative bg-black aspect-[4/3]">
                                <video
                                    v-show="cameraStream && !capturedImage"
                                    ref="scannerVideoRef"
                                    id="scanner-video"
                                    autoplay
                                    playsinline
                                    muted
                                    @loadedmetadata="bindVideoTrack"
                                    class="w-full h-full object-cover"
                                ></video>
                                
                                <img
                                    v-if="capturedImage"
                                    :src="capturedImage"
                                    alt="Captured"
                                    class="w-full h-full object-cover"
                                />

                                <!-- Capture Button -->
                                <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-4">
                                    <button
                                        v-if="!capturedImage"
                                        @click="captureImage"
                                        class="rounded-full bg-white p-4 shadow-lg hover:bg-gray-100 transition"
                                    >
                                        <svg class="h-8 w-8 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                    
                                    <button
                                        v-if="capturedImage"
                                        @click="capturedImage = null"
                                        class="rounded-full bg-gray-800 p-4 text-white shadow-lg hover:bg-gray-700 transition"
                                    >
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    
                                    <button
                                        v-if="capturedImage"
                                        @click="uploadCapturedImage"
                                        class="rounded-full bg-red-600 p-4 text-white shadow-lg hover:bg-red-700 transition"
                                    >
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Document Preview Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showPreview && selectedDocument"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="fixed inset-0 bg-black/90" @click="closePreview"></div>
                    
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="relative w-full max-w-5xl overflow-hidden rounded-xl bg-transparent shadow-none">
                            <!-- Header -->
                            <div class="flex items-center justify-between px-2 py-3 text-white">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">{{ selectedDocument.document_name }}</h3>
                                    <p class="mt-1 text-sm text-white/70">
                                        {{ formatFileSize(selectedDocument.file_size) }} · 
                                        {{ formatDate(selectedDocument.created_at) }}
                                    </p>
                                </div>
                                <button
                                    @click="closePreview"
                                    class="rounded-lg p-2 text-white/80 hover:bg-white/15 hover:text-white"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Content -->
                            <div class="p-6">
                                <div class="mb-2 rounded-lg bg-transparent p-2 text-center">
                                    <img v-if="isImageDocument(selectedDocument)" :src="getDocumentPreviewUrl(selectedDocument)" :alt="selectedDocument.document_name" class="mx-auto max-h-[65vh] w-full rounded-lg object-contain" />
                                    <iframe v-else-if="isPdfDocument(selectedDocument)" :src="getDocumentPreviewUrl(selectedDocument)" :title="selectedDocument.document_name" class="mx-auto h-[65vh] w-full rounded-lg border border-gray-200 bg-white"></iframe>
                                    <svg v-else class="mx-auto h-24 w-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 3h7l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 3v5h5M9 13h6m-6 4h6"/></svg>
                                </div>
                                <!-- Processing Status -->
                                <div class="mb-6 rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-3 text-sm font-semibold text-gray-900">Processing Status</h4>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="flex items-center gap-3">
                                        </div>
                                        
                                        <div v-if="selectedDocument.ocr_processed" class="flex items-center gap-2 text-sm text-gray-600">
                                            <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            OCR Processed ({{ selectedDocument.ocr_confidence }}%)
                                        </div>
                                        
                                        <div v-if="selectedDocument.ai_processed" class="flex items-center gap-2 text-sm text-gray-600">
                                            <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            AI Analyzed ({{ selectedDocument.ai_classification }})
                                        </div>
                                        
                                    </div>
                                </div>

                                <!-- Extracted Text -->
                                <div v-if="false && selectedDocument.extracted_text" class="mb-6 rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-3 text-sm font-semibold text-gray-900">Extracted Text</h4>
                                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ selectedDocument.extracted_text }}</p>
                                </div>

                                <!-- AI Tags -->
                                <div v-if="false && selectedDocument.ai_tags && selectedDocument.ai_tags.length > 0" class="mb-6 rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-3 text-sm font-semibold text-gray-900">AI-Generated Tags</h4>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="tag in selectedDocument.ai_tags"
                                            :key="tag"
                                            class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700"
                                        >
                                            {{ tag }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Metadata -->
                                <div v-if="false" class="rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-3 text-sm font-semibold text-gray-900">Document Metadata</h4>
                                    <dl class="grid gap-2 text-sm">
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">File Hash</dt>
                                            <dd class="font-mono text-gray-900">{{ selectedDocument.file_hash.substring(0, 16) }}...</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Version</dt>
                                            <dd class="text-gray-900">{{ selectedDocument.version }}</dd>
                                        </div>
                                        <div v-if="selectedDocument.scan_device" class="flex justify-between">
                                            <dt class="text-gray-500">Scan Device</dt>
                                            <dd class="text-gray-900">{{ selectedDocument.scan_device }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex items-center justify-between px-2 py-3 text-white">
                                <div class="flex items-center gap-2">
                                    <button @click="previousDocument" :disabled="filteredDocuments.findIndex(document => document.id === selectedDocument.id) === 0" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">← Previous</button>
                                    <button @click="nextDocument" :disabled="filteredDocuments.findIndex(document => document.id === selectedDocument.id) === filteredDocuments.length - 1" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">Next →</button>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-white/80">
                                    <label :for="`page-number-${selectedDocument.id}`">Page</label>
                                    <input :id="`page-number-${selectedDocument.id}`" v-model.number="selectedDocument.page_number" type="number" min="1" class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-sm" @change="savePageNumber(selectedDocument)" />
                                </div>
                                <button
                                    @click="downloadDocument(selectedDocument)"
                                    class="flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download
                                </button>
                                <button
                                    @click="closePreview"
                                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    opacity: 0;
    transform: scale(0.96);
}
</style>
