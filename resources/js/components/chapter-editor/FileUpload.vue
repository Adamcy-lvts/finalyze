<!-- FileUpload.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Upload,
    File,
    FileText,
    X,
    Check,
    AlertCircle,
    Loader,
    Trash2,
    Download,
    Eye,
} from 'lucide-vue-next';
import { ref, computed, nextTick } from 'vue';
import { route } from 'ziggy-js';
import axios from 'axios';

interface UploadedFile {
    id: string;
    filename: string;
    size: string;
    word_count: number;
    citations_found: number;
    main_topics: string[];
    uploaded_at?: string;
    summary: string;
    analysis?: string;
}

interface Props {
    projectSlug: string;
    chapterNumber: number;
    sessionId: string;
    disabled?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'file-uploaded': [file: UploadedFile];
    'file-deleted': [fileId: string];
    'files-loaded': [files: UploadedFile[]];
}>();

// Reactive state
const fileInput = ref<HTMLInputElement>();
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref('');
const uploadSuccess = ref('');
const dragActive = ref(false);
const uploadedFiles = ref<UploadedFile[]>([]);

// Computed
const hasFiles = computed(() => uploadedFiles.value.length > 0);
const acceptedTypes = computed(() =>
    '.pdf,.doc,.docx,.txt,.rtf,.md'
);

const maxSizeDisplay = computed(() => '10 MB');

// Methods
const triggerFileInput = () => {
    if (!props.disabled && !isUploading.value) {
        fileInput.value?.click();
    }
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        uploadFile(target.files[0]);
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    dragActive.value = false;

    if (props.disabled || isUploading.value) return;

    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    if (!props.disabled && !isUploading.value) {
        dragActive.value = true;
    }
};

const handleDragLeave = () => {
    dragActive.value = false;
};

const uploadFile = async (file: File) => {
    if (props.disabled) return;

    // Reset states
    uploadError.value = '';
    uploadSuccess.value = '';
    isUploading.value = true;
    uploadProgress.value = 0;

    // Validate file
    if (!validateFile(file)) {
        isUploading.value = false;
        return;
    }

    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('session_id', props.sessionId);

        const response = await axios.post(
            route('chapters.chat-upload', {
                project: props.projectSlug,
                chapter: props.chapterNumber,
            }),
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        uploadProgress.value = Math.round(
                            (progressEvent.loaded * 100) / progressEvent.total
                        );
                    }
                },
            }
        );

        if (response.data.success) {
            uploadSuccess.value = response.data.message;
            uploadedFiles.value.unshift(response.data.upload);
            emit('file-uploaded', response.data.upload);

            // Clear file input
            if (fileInput.value) {
                fileInput.value.value = '';
            }

            // Clear success message after 3 seconds
            setTimeout(() => {
                uploadSuccess.value = '';
            }, 3000);
        } else {
            uploadError.value = response.data.error || 'Upload failed';
        }
    } catch (error: any) {
        console.error('Upload error:', error);
        uploadError.value =
            error.response?.data?.error ||
            'Failed to upload file. Please try again.';
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

const validateFile = (file: File): boolean => {
    // Check file size (10MB)
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        uploadError.value = `File size exceeds ${maxSizeDisplay.value} limit`;
        return false;
    }

    // Check file type
    const allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'md'];
    const extension = file.name.split('.').pop()?.toLowerCase();

    if (!extension || !allowedTypes.includes(extension)) {
        uploadError.value = 'Please upload PDF, DOC, DOCX, TXT, RTF, or MD files only';
        return false;
    }

    return true;
};

const deleteFile = async (fileId: string) => {
    try {
        const response = await axios.delete(
            route('chapters.chat-file-delete', {
                project: props.projectSlug,
                chapter: props.chapterNumber,
                uploadId: fileId,
            })
        );

        if (response.data.success) {
            uploadedFiles.value = uploadedFiles.value.filter(f => f.id !== fileId);
            emit('file-deleted', fileId);
        }
    } catch (error: any) {
        console.error('Delete error:', error);
        uploadError.value = 'Failed to delete file';
    }
};

const loadExistingFiles = async () => {
    try {
        const response = await axios.get(
            route('chapters.chat-files', {
                project: props.projectSlug,
                chapter: props.chapterNumber,
            }),
            {
                params: { session_id: props.sessionId }
            }
        );

        if (response.data.success) {
            uploadedFiles.value = response.data.files;
            emit('files-loaded', response.data.files);
        }
    } catch (error) {
        console.error('Failed to load existing files:', error);
    }
};

const getFileIcon = (filename: string) => {
    const extension = filename.split('.').pop()?.toLowerCase();
    return FileText; // Could expand this based on file type
};

const formatTopics = (topics: string[]) => {
    return topics.slice(0, 3).join(', ') + (topics.length > 3 ? '...' : '');
};

// Load existing files on mount
nextTick(() => {
    loadExistingFiles();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Upload Area -->
        <div
            :class="[
                'border-2 border-dashed rounded-lg p-6 text-center transition-all',
                dragActive
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                disabled || isUploading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
            ]"
            @click="triggerFileInput"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <input
                ref="fileInput"
                type="file"
                :accept="acceptedTypes"
                class="hidden"
                @change="handleFileSelect"
                :disabled="disabled || isUploading"
            />

            <div class="flex flex-col items-center gap-3">
                <div
                    :class="[
                        'flex h-12 w-12 items-center justify-center rounded-full',
                        isUploading ? 'bg-primary/10' : 'bg-muted'
                    ]"
                >
                    <Loader v-if="isUploading" class="h-6 w-6 animate-spin text-primary" />
                    <Upload v-else class="h-6 w-6 text-muted-foreground" />
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">
                        {{ isUploading ? 'Uploading and analyzing...' : 'Drop files here or click to browse' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        PDF, DOC, DOCX, TXT, RTF, MD up to {{ maxSizeDisplay }}
                    </p>
                </div>

                <!-- Upload Progress -->
                <div v-if="isUploading" class="w-full max-w-xs">
                    <Progress :value="uploadProgress" class="h-2" />
                    <p class="text-xs text-muted-foreground mt-1">{{ uploadProgress }}%</p>
                </div>
            </div>
        </div>

        <!-- Upload Messages -->
        <div v-if="uploadError" class="space-y-2">
            <Alert variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>{{ uploadError }}</AlertDescription>
            </Alert>
        </div>

        <div v-if="uploadSuccess" class="space-y-2">
            <Alert>
                <Check class="h-4 w-4" />
                <AlertDescription>{{ uploadSuccess }}</AlertDescription>
            </Alert>
        </div>

        <!-- Uploaded Files List -->
        <div v-if="hasFiles" class="space-y-3">
            <h4 class="text-sm font-medium text-foreground">Uploaded Documents</h4>

            <div class="space-y-2">
                <div
                    v-for="file in uploadedFiles"
                    :key="file.id"
                    class="flex items-start gap-3 p-3 bg-muted/30 rounded-lg border"
                >
                    <component :is="getFileIcon(file.filename)" class="h-5 w-5 text-muted-foreground mt-0.5 flex-shrink-0" />

                    <div class="flex-1 min-w-0 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium truncate">{{ file.filename }}</p>
                                <p class="text-xs text-muted-foreground">{{ file.size }}</p>
                            </div>

                            <Button
                                @click="deleteFile(file.id)"
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                            >
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>

                        <div class="flex flex-wrap gap-1">
                            <Badge variant="secondary" class="text-xs">
                                {{ file.word_count }} words
                            </Badge>
                            <Badge v-if="file.citations_found > 0" variant="secondary" class="text-xs">
                                {{ file.citations_found }} citations
                            </Badge>
                            <Badge v-if="file.main_topics.length > 0" variant="outline" class="text-xs">
                                {{ formatTopics(file.main_topics) }}
                            </Badge>
                        </div>

                        <p v-if="file.uploaded_at" class="text-xs text-muted-foreground">
                            Uploaded {{ file.uploaded_at }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Additional component styles if needed */
</style>