<!-- /resources/js/components/chapter-editor/CitationVerificationLayout.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import axios from 'axios';
import { BookCheck, X, CheckCircle, AlertTriangle, Loader } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import { route } from 'ziggy-js';
import { router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

interface Props {
    project: any;
    chapter: any;
    chapterTitle: string;
    chapterContent: string;
    currentWordCount: number;
    targetWordCount: number;
    progressPercentage: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    exitCitationMode: [];
}>();

const page = usePage();

// Citation verification state
const citations = ref([]);
const references = ref([]);
const summary = ref({
    total: 0,
    verified: 0,
    failed: 0,
    unverified: 0,
    pending: 0,
});
const isVerifying = ref(false);
const hasVerified = ref(false);
const verificationMessage = ref('');
const currentProgress = ref({
    percentage: 0,
    message: '',
    completed: false
});

// Async tracking
let currentSessionId = null;
let progressInterval = null;

// Progress tracking functions
const startProgressTracking = (sessionId) => {
    if (progressInterval) {
        clearInterval(progressInterval);
    }
    
    progressInterval = setInterval(async () => {
        try {
            const progressResponse = await axios.get(route('api.citation-verification.progress', sessionId));
            
            if (progressResponse.data.success) {
                const progress = progressResponse.data.progress;
                currentProgress.value = progress;
                verificationMessage.value = progress.message;
                
                // Check if completed
                if (progress.completed || progress.percentage >= 100) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                    
                    // Fetch final results
                    setTimeout(() => fetchVerificationResults(sessionId), 1000);
                }
            }
        } catch (error) {
            console.error('Error fetching progress:', error);
        }
    }, 1000); // Poll every second
};

const fetchVerificationResults = async (sessionId) => {
    try {
        const resultResponse = await axios.get(route('api.citation-verification.result', sessionId));
        
        if (resultResponse.data.success) {
            const data = resultResponse.data;
            citations.value = data.citations || [];
            references.value = data.references || [];
            summary.value = data.summary || summary.value;
            verificationMessage.value = data.message || 'Verification completed';
            hasVerified.value = true;
        } else {
            verificationMessage.value = resultResponse.data.message || 'Failed to fetch results';
        }
    } catch (error) {
        console.error('Error fetching results:', error);
        verificationMessage.value = 'Failed to fetch verification results';
    } finally {
        isVerifying.value = false;
        currentProgress.value = { percentage: 100, message: 'Completed', completed: true };
        currentSessionId = null;
    }
};

// Async verify citations
const verifyCitations = async () => {
    if (!props.chapter?.id) {
        console.error('No chapter ID available');
        verificationMessage.value = 'No chapter available to verify';
        return;
    }
    
    isVerifying.value = true;
    hasVerified.value = false;
    currentProgress.value = { percentage: 0, message: 'Initializing...', completed: false };
    verificationMessage.value = 'Starting async verification process...';
    
    console.log('=== ASYNC CITATION VERIFICATION START ===');
    console.log('Chapter object:', props.chapter);
    console.log('Chapter ID:', props.chapter.id);
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('CSRF token found:', !!csrfToken);
        
        const url = route('api.projects.chapters.verify-citations', { 
            project: props.project.slug, 
            chapter: props.chapter.slug 
        });
        console.log('Generated URL:', url);
        
        const requestOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin' as RequestCredentials,
            body: JSON.stringify({})
        };
        console.log('About to make async fetch request to:', url);
        
        const response = await fetch(url, requestOptions);
        console.log('Response received:', response.status, response.statusText);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Initial response:', data);

        if (data.success && data.async) {
            // Start async processing
            currentSessionId = data.session_id;
            verificationMessage.value = data.message;
            
            console.log('Starting progress tracking for session:', currentSessionId);
            startProgressTracking(currentSessionId);
        } else if (data.success) {
            // Fallback to synchronous response (e.g., no content)
            citations.value = data.citations || [];
            references.value = data.references || [];
            summary.value = data.summary || summary.value;
            verificationMessage.value = data.message || 'Verification completed';
            hasVerified.value = true;
            isVerifying.value = false;
        } else {
            verificationMessage.value = data.message || 'Verification failed';
            isVerifying.value = false;
        }
    } catch (error) {
        console.error('Citation verification error:', error);
        
        if (error.message && error.message.includes('419')) {
            verificationMessage.value = 'Session expired. Please refresh the page and try again.';
        } else if (error.message && error.message.includes('401')) {
            verificationMessage.value = 'Unauthorized. Please log in again.';
        } else if (error.message && error.message.includes('404')) {
            verificationMessage.value = 'Chapter not found. Please try again.';
        } else if (error.message && error.message.includes('500')) {
            verificationMessage.value = 'Server error. Please try again.';
        } else {
            console.error('Error:', error.message);
            verificationMessage.value = 'An unexpected error occurred. Please try again.';
        }
        
        isVerifying.value = false;
        currentProgress.value = { percentage: 0, message: '', completed: false };
    }
};

// Cleanup on component unmount
const cleanup = () => {
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
};

// Copy formatted references to clipboard
const copyFormattedReferences = async () => {
    try {
        const verifiedReferences = references.value.filter(ref => ref.status === 'verified' && ref.formatted_citation);
        let citationsText = '';
        
        if (verifiedReferences.length > 0) {
            citationsText = verifiedReferences
                .map(ref => ref.formatted_citation)
                .join('\n\n');
        } else {
            // Fallback to basic formatting if no formatted citations available
            citationsText = references.value
                .map(ref => {
                    const authors = ref.authors ? ref.authors.join(', ') : 'Unknown';
                    const year = ref.year || 'n.d.';
                    const title = ref.title || 'Title not available';
                    return `${authors} (${year}). ${title}.`;
                })
                .join('\n\n');
        }
        
        await navigator.clipboard.writeText(citationsText);
        
        // Update verification message to show success
        const originalMessage = verificationMessage.value;
        verificationMessage.value = 'References copied to clipboard!';
        
        // Reset message after 2 seconds
        setTimeout(() => {
            verificationMessage.value = originalMessage;
        }, 2000);
        
    } catch (error) {
        console.error('Failed to copy references:', error);
        verificationMessage.value = 'Failed to copy references to clipboard';
    }
};

// Export references (placeholder for future functionality)
const exportReferences = () => {
    // TODO: Implement export functionality (PDF, Word, etc.)
    console.log('Export references:', references.value);
    verificationMessage.value = 'Export functionality coming soon!';
    
    setTimeout(() => {
        verificationMessage.value = hasVerified.value ? 
            `Verified ${summary.value.verified} out of ${summary.value.total} references using bibliography parsing` : '';
    }, 2000);
};

// Retry a single failed reference
const retryReference = async (reference) => {
    // TODO: Implement individual reference retry
    console.log('Retrying reference:', reference);
    verificationMessage.value = `Retrying verification for reference ${reference.id}...`;
    
    // For now, just show a message - this could call a specific API endpoint
    setTimeout(() => {
        verificationMessage.value = 'Individual retry functionality coming soon! Use "Verify Sources" to retry all.';
        setTimeout(() => {
            verificationMessage.value = hasVerified.value ? 
                `Verified ${summary.value.verified} out of ${summary.value.total} references using bibliography parsing` : '';
        }, 2000);
    }, 1000);
};

// Lifecycle hooks
onUnmounted(() => {
    cleanup();
});
</script>

<template>
    <!-- Split Screen Citation Verification Layout -->
    <div class="flex h-screen flex-col overflow-hidden bg-background">
        <!-- Header -->
        <div
            class="flex flex-shrink-0 items-center justify-between border-b bg-background/95 p-3 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="flex items-center gap-4">
                <Button @click="emit('exitCitationMode')" variant="ghost" size="icon">
                    <X class="h-4 w-4" />
                </Button>

                <div class="flex items-center gap-2">
                    <BookCheck class="h-5 w-5 text-primary" />
                    <div>
                        <h1 class="text-lg font-bold">{{ props.project.title }}</h1>
                        <p class="text-sm text-muted-foreground">
                            Citation Verification • Chapter {{ props.chapter.chapter_number }} • {{ currentWordCount }} / {{ targetWordCount }} words
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Badge :variant="chapter.status === 'approved' ? 'default' : 'secondary'">
                    {{ chapter.status.replace('_', ' ') }}
                </Badge>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="flex-shrink-0 bg-muted/30 px-3 py-2">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-medium">Writing Progress</span>
                <span class="text-xs text-muted-foreground">{{ Math.round(progressPercentage) }}%</span>
            </div>
            <Progress :value="progressPercentage" class="h-1" />
        </div>

        <!-- Main Split Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Left Panel - Content Viewer -->
            <div class="flex min-h-0 flex-1 flex-col border-r">
                <Card class="flex min-h-0 flex-1 flex-col rounded-none border-0 bg-transparent shadow-none">
                    <CardHeader class="flex-shrink-0 border-b px-4 py-3">
                        <CardTitle class="text-base">Chapter {{ chapter.chapter_number }} - Content</CardTitle>
                    </CardHeader>

                    <CardContent class="flex min-h-0 flex-1 flex-col space-y-3 p-4">
                        <!-- Chapter Title Display -->
                        <div class="flex-shrink-0 space-y-2">
                            <h2 class="text-lg font-semibold">{{ chapterTitle || 'Untitled Chapter' }}</h2>
                        </div>

                        <!-- Content Viewer -->
                        <ScrollArea class="min-h-0 flex-1">
                            <RichTextViewer
                                v-if="chapterContent"
                                :content="chapterContent"
                                class="p-4"
                                style="font-family: 'Times New Roman', serif; line-height: 1.6"
                            />
                            <div v-else class="flex items-center justify-center h-64 text-muted-foreground">
                                <p>No content available to verify</p>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Panel - Citation Verification -->
            <div class="flex min-h-0 flex-1 flex-col bg-muted/20">
                <Card class="flex min-h-0 flex-1 flex-col rounded-none border-0 bg-transparent shadow-none">
                    <CardHeader class="flex-shrink-0 border-b px-4 py-3">
                        <CardTitle class="text-base flex items-center gap-2">
                            <BookCheck class="h-4 w-4" />
                            Citation Verification
                        </CardTitle>
                    </CardHeader>

                    <CardContent class="flex min-h-0 flex-1 flex-col space-y-4 p-4">
                        <!-- Verification Controls -->
                        <div class="flex-shrink-0 space-y-2">
                            <Button 
                                @click="verifyCitations" 
                                :disabled="isVerifying || !chapterContent"
                                class="w-full"
                            >
                                <Loader v-if="isVerifying" class="h-4 w-4 mr-2 animate-spin" />
                                <BookCheck v-else class="h-4 w-4 mr-2" />
                                {{ isVerifying ? 'Verifying...' : 'Verify Sources' }}
                            </Button>
                            
                            <!-- Additional Actions -->
                            <div v-if="hasVerified && references.length > 0" class="flex gap-2">
                                <Button 
                                    @click="copyFormattedReferences"
                                    variant="outline" 
                                    size="sm"
                                    class="flex-1 text-xs"
                                >
                                    Copy References
                                </Button>
                                <Button 
                                    @click="exportReferences"
                                    variant="outline" 
                                    size="sm"
                                    class="flex-1 text-xs"
                                >
                                    Export
                                </Button>
                            </div>
                        </div>

                        <!-- Verification Status -->
                        <div v-if="verificationMessage || isVerifying" class="flex-shrink-0 space-y-2">
                            <div class="text-sm text-muted-foreground p-3 bg-muted/50 rounded-md">
                                {{ verificationMessage }}
                            </div>
                            
                            <!-- Real-time Progress Bar -->
                            <div v-if="isVerifying && currentProgress.percentage > 0" class="space-y-2">
                                <div class="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{{ currentProgress.message }}</span>
                                    <span>{{ Math.round(currentProgress.percentage) }}%</span>
                                </div>
                                <Progress :value="currentProgress.percentage" class="h-2" />
                            </div>
                        </div>

                        <!-- Summary -->
                        <div v-if="hasVerified" class="flex-shrink-0">
                            <Card class="bg-muted/30">
                                <CardContent class="p-4 space-y-3">
                                    <!-- Success Rate -->
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium text-sm">Verification Results</h4>
                                        <Badge variant="outline" class="text-xs">
                                            {{ Math.round((summary.verified / summary.total) * 100) }}% verified
                                        </Badge>
                                    </div>
                                    
                                    <!-- Progress Bar -->
                                    <Progress :value="(summary.verified / summary.total) * 100" class="h-2" />
                                    
                                    <!-- Stats Grid -->
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="flex items-center gap-2 p-2 rounded bg-background/50">
                                            <div class="h-3 w-3 rounded-full bg-blue-500"></div>
                                            <span class="font-medium">{{ summary.total }}</span>
                                            <span class="text-muted-foreground text-xs">Total</span>
                                        </div>
                                        <div class="flex items-center gap-2 p-2 rounded bg-background/50">
                                            <CheckCircle class="h-3 w-3 text-green-500" />
                                            <span class="font-medium">{{ summary.verified }}</span>
                                            <span class="text-muted-foreground text-xs">Verified</span>
                                        </div>
                                        <div class="flex items-center gap-2 p-2 rounded bg-background/50">
                                            <X class="h-3 w-3 text-red-500" />
                                            <span class="font-medium">{{ summary.failed || 0 }}</span>
                                            <span class="text-muted-foreground text-xs">Failed</span>
                                        </div>
                                        <div class="flex items-center gap-2 p-2 rounded bg-background/50">
                                            <AlertTriangle class="h-3 w-3 text-orange-500" />
                                            <span class="font-medium">{{ summary.pending || 0 }}</span>
                                            <span class="text-muted-foreground text-xs">Pending</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Processing Time -->
                                    <div v-if="summary.processing_time_ms || verificationMessage.includes('processing_time_ms')" class="text-xs text-muted-foreground text-center">
                                        <span>Processing completed</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Citations List -->
                        <ScrollArea v-if="hasVerified" class="min-h-0 flex-1">
                            <div class="space-y-3">
                                <h3 v-if="citations.length > 0" class="font-medium">In-text Citations</h3>
                                <div v-for="citation in citations" :key="citation.id" class="border rounded-md p-3 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <CheckCircle v-if="citation.status === 'verified'" class="h-4 w-4 text-green-500" />
                                        <AlertTriangle v-else-if="citation.status === 'unverified'" class="h-4 w-4 text-orange-500" />
                                        <div v-else class="h-4 w-4 rounded-full bg-gray-300"></div>
                                        <code class="text-sm bg-muted px-1 rounded">{{ citation.text }}</code>
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        <p><strong>Author:</strong> {{ citation.author }}</p>
                                        <p><strong>Year:</strong> {{ citation.year }}</p>
                                        <p v-if="citation.details"><strong>Status:</strong> {{ citation.details }}</p>
                                    </div>
                                </div>

                                <h3 v-if="references.length > 0" class="font-medium pt-4">Reference List</h3>
                                <div v-for="reference in references" :key="reference.id" class="border rounded-md p-4 space-y-3">
                                    <!-- Reference Header -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle v-if="reference.status === 'verified'" class="h-5 w-5 text-green-500" />
                                            <X v-else-if="reference.status === 'failed'" class="h-5 w-5 text-red-500" />
                                            <AlertTriangle v-else class="h-5 w-5 text-orange-500" />
                                            <span class="font-medium text-sm">Reference {{ reference.id }}</span>
                                        </div>
                                        <Badge 
                                            :variant="reference.status === 'verified' ? 'default' : reference.status === 'failed' ? 'destructive' : 'secondary'"
                                            class="text-xs"
                                        >
                                            {{ reference.status || 'unverified' }}
                                        </Badge>
                                    </div>

                                    <!-- Reference Details -->
                                    <div class="space-y-2">
                                        <!-- Title -->
                                        <div v-if="reference.title" class="text-sm">
                                            <span class="font-medium text-primary">{{ reference.title }}</span>
                                        </div>

                                        <!-- Authors and Year -->
                                        <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                            <div v-if="reference.authors && reference.authors.length > 0">
                                                <strong>Authors:</strong> {{ reference.authors.join(', ') }}
                                            </div>
                                            <div v-if="reference.year">
                                                <strong>Year:</strong> {{ reference.year }}
                                            </div>
                                        </div>

                                        <!-- Journal/Publisher -->
                                        <div v-if="reference.journal || reference.publisher" class="text-xs text-muted-foreground">
                                            <strong>Published in:</strong> {{ reference.journal || reference.publisher }}
                                        </div>

                                        <!-- DOI and URL -->
                                        <div v-if="reference.doi || reference.url" class="flex items-center gap-4 text-xs">
                                            <div v-if="reference.doi" class="flex items-center gap-1">
                                                <strong>DOI:</strong> 
                                                <a :href="'https://doi.org/' + reference.doi" target="_blank" class="text-blue-600 hover:underline">
                                                    {{ reference.doi }}
                                                </a>
                                            </div>
                                            <div v-if="reference.url && !reference.doi" class="flex items-center gap-1">
                                                <strong>URL:</strong> 
                                                <a :href="reference.url" target="_blank" class="text-blue-600 hover:underline break-all">
                                                    {{ reference.url.length > 40 ? reference.url.substring(0, 40) + '...' : reference.url }}
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Verification Details -->
                                        <div v-if="reference.status === 'verified'" class="space-y-2">
                                            <div class="text-xs text-muted-foreground">
                                                <strong>Verification Method:</strong> {{ reference.verification_method || 'api' }}
                                                <span v-if="reference.confidence" class="ml-2">
                                                    | <strong>Confidence:</strong> {{ Math.round(reference.confidence * 100) }}%
                                                </span>
                                            </div>
                                            
                                            <!-- Formatted Citation -->
                                            <div v-if="reference.formatted_citation" class="mt-2 p-2 bg-muted/30 rounded border-l-2 border-green-500">
                                                <div class="text-xs font-medium text-muted-foreground mb-1">Formatted Citation (APA):</div>
                                                <div class="text-xs text-foreground italic">{{ reference.formatted_citation }}</div>
                                            </div>
                                        </div>

                                        <!-- Failure Reason -->
                                        <div v-if="reference.status === 'failed'" class="space-y-2">
                                            <div v-if="reference.failure_reason" class="text-xs text-red-600">
                                                <strong>Issue:</strong> {{ reference.failure_reason }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <Button 
                                                    @click="retryReference(reference)"
                                                    variant="outline"
                                                    size="sm"
                                                    class="text-xs h-6 px-2"
                                                    :disabled="isVerifying"
                                                >
                                                    Retry
                                                </Button>
                                                <span class="text-xs text-muted-foreground">
                                                    Try searching with different criteria
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Raw Reference Text (Collapsible) -->
                                        <details class="text-xs">
                                            <summary class="cursor-pointer text-muted-foreground hover:text-foreground">
                                                Show raw reference text
                                            </summary>
                                            <div class="mt-1 p-2 bg-muted/20 rounded text-xs font-mono">
                                                {{ reference.raw_text }}
                                            </div>
                                        </details>
                                    </div>
                                </div>

                                <div v-if="citations.length === 0 && references.length === 0" class="text-center text-muted-foreground py-8">
                                    <BookCheck class="h-12 w-12 mx-auto mb-2 opacity-50" />
                                    <p>No citations found in this chapter</p>
                                    <p class="text-xs">Citations will appear here when detected</p>
                                </div>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>