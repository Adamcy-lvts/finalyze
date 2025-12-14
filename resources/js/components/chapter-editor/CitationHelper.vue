<!-- CitationHelper.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    BookOpen,
    ChevronDown,
    Copy,
    ExternalLink,
    History,
    Loader2,
    Plus,
    Quote,
    Search,
    Trash2,
    Wand2,
    Sparkles,
    AlertTriangle,
    ChevronRight,
    Maximize2,
    X
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import axios from 'axios';

interface Citation {
    id: string;
    title: string;
    authors: string[];
    year: number;
    journal?: string;
    url?: string;
    doi?: string;
    type: 'journal' | 'book' | 'website' | 'conference';
    style: {
        apa: string;
        harvard: string;
        ieee: string;
    };
    createdAt: Date;
}

interface Props {
    showCitationHelper: boolean;
    chapterContent: string;
    chapterId: number;
    citationOperationMode?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    citationOperationMode: false,
});

const emit = defineEmits<{
    'update:showCitationHelper': [value: boolean];
    'insert-citation': [citation: string, claimText?: string];
    'enter-operation-mode': [];
    'exit-operation-mode': [];
}>();

// Local state
const selectedStyle = ref<'apa' | 'harvard' | 'ieee'>('apa');
const searchInput = ref('');
const isSearching = ref(false);
const isClearing = ref(false);
const showClearDialog = ref(false);
const recentCitations = ref<Citation[]>([]);
const expandedCitation = ref<string | null>(null);

// Auto-detect state
const isDetecting = ref(false);
const isLoadingDetection = ref(false);
const detectedClaims = ref<any[]>([]);
const showDetectedClaims = ref(false);
const expandedClaim = ref<string | null>(null);
const detectedAt = ref<string | null>(null);

// Collapsible section states (collapsed by default in operation mode to save space)
const showCitationStyleSection = ref(true);
const showGenerateCitationSection = ref(true);

// Load saved detection on mount
const loadSavedDetections = async () => {
    if (!props.chapterId) return;

    isLoadingDetection.value = true;
    try {
        const response = await axios.get('/api/citations/detected-claims', {
            params: { chapter_id: props.chapterId }
        });

        if (response.data.success && response.data.has_detection) {
            detectedClaims.value = response.data.claims || [];
            detectedAt.value = response.data.detected_at;
            showDetectedClaims.value = detectedClaims.value.length > 0;
        }
    } catch (error) {
        console.error('Failed to load saved detections:', error);
    } finally {
        isLoadingDetection.value = false;
    }
};

// Auto-detect claims that need citations
const detectClaims = async () => {
    if (!props.chapterContent || props.chapterContent.trim().length < 100) {
        toast.error('Please add more content before detecting citations (minimum 100 characters)');
        return;
    }

    if (!props.chapterId) {
        toast.error('Chapter ID is required for detection');
        return;
    }

    isDetecting.value = true;
    detectedClaims.value = [];
    showDetectedClaims.value = true;

    try {
        const response = await axios.post('/api/citations/detect-claims', {
            content: props.chapterContent,
            chapter_id: props.chapterId
        });

        if (response.data.success) {
            detectedClaims.value = response.data.claims || [];
            detectedAt.value = response.data.detected_at;
            if (detectedClaims.value.length === 0) {
                toast.info('No claims requiring citations were detected');
            } else {
                toast.success(`Found ${detectedClaims.value.length} claims that may need citations`);
            }
        } else {
            toast.error('Failed to detect claims');
        }
    } catch (error: any) {
        console.error('Failed to detect claims:', error);
        toast.error('Failed to analyze content for citations');
    } finally {
        isDetecting.value = false;
    }
};

const toggleClaimExpand = (claimId: string) => {
    expandedClaim.value = expandedClaim.value === claimId ? null : claimId;
};

const insertSuggestion = (suggestion: any, claimText?: string) => {
    const formattedCitation = suggestion.style[selectedStyle.value];
    emit('insert-citation', formattedCitation, claimText);
    toast.success('Citation inserted into document');
};

const copySuggestion = (suggestion: any) => {
    const formattedCitation = suggestion.style[selectedStyle.value];
    navigator.clipboard.writeText(formattedCitation);
    toast.success('Citation copied to clipboard');
};

const openUrl = (url: string) => {
    if (url) {
        window.open(url, '_blank');
    }
};

// Citation style templates
const citationStyles = {
    apa: {
        name: 'APA Style',
        description: 'American Psychological Association',
        example: 'Smith, J. (2023). Research methods. Journal of Science, 15(3), 123-145.'
    },
    harvard: {
        name: 'Harvard Style',
        description: 'Author-date referencing system',
        example: 'Smith, J. (2023) "Research methods", Journal of Science, 15(3), pp. 123-145.'
    },
    ieee: {
        name: 'IEEE Style',
        description: 'Institute of Electrical and Electronics Engineers',
        example: 'J. Smith, "Research methods," Journal of Science, vol. 15, no. 3, pp. 123-145, 2023.'
    }
};

// Computed
const currentStyleInfo = computed(() => citationStyles[selectedStyle.value]);

// Methods
const handleToggle = (isOpen: boolean) => {
    emit('update:showCitationHelper', isOpen);
};

const toggleCitationExpand = (citationId: string) => {
    expandedCitation.value = expandedCitation.value === citationId ? null : citationId;
};

const searchCitation = async () => {
    if (!searchInput.value.trim()) {
        toast.error('Please enter a URL, DOI, or search term');
        return;
    }

    isSearching.value = true;
    try {
        const response = await axios.post('/api/citations/generate', {
            input: searchInput.value.trim()
        });

        if (response.data.success) {
            const citationData = response.data.citation;
            const citation: Citation = {
                id: citationData.id.toString(),
                title: citationData.title,
                authors: citationData.authors,
                year: citationData.year,
                journal: citationData.journal,
                url: citationData.url,
                doi: citationData.doi,
                type: citationData.type,
                style: {
                    apa: citationData.style.apa,
                    harvard: citationData.style.harvard,
                    ieee: citationData.style.ieee
                },
                createdAt: new Date(citationData.created_at)
            };

            recentCitations.value.unshift(citation);
            searchInput.value = '';

            toast.success('Citation generated successfully!');
        } else {
            toast.error('Failed to generate citation');
        }
    } catch (error: any) {
        console.error('Failed to search citation:', error);

        if (error.response?.status === 422) {
            toast.error('Invalid input format');
        } else {
            toast.error('Failed to generate citation');
        }
    } finally {
        isSearching.value = false;
    }
};

const insertCitation = (citation: Citation) => {
    const formattedCitation = citation.style[selectedStyle.value];
    emit('insert-citation', formattedCitation);
    toast.success('Citation inserted into document');
};

const copyCitation = (citation: Citation) => {
    const formattedCitation = citation.style[selectedStyle.value];
    navigator.clipboard.writeText(formattedCitation);
    toast.success('Citation copied to clipboard');
};

const handleClearClick = () => {
    if (!recentCitations.value.length) {
        toast.error('No citations to clear');
        return;
    }
    showClearDialog.value = true;
};

const clearAllCitations = async () => {
    isClearing.value = true;
    showClearDialog.value = false;

    try {
        const response = await axios.delete('/api/citations/clear');

        if (response.data.success) {
            recentCitations.value = [];
            expandedCitation.value = null;
            toast.success('All citations cleared successfully');
        } else {
            toast.error('Failed to clear citations');
        }
    } catch (error: any) {
        console.error('Failed to clear citations:', error);
        toast.error('Failed to clear citations');
    } finally {
        isClearing.value = false;
    }
};

// Load recent citations from API
const loadRecentCitations = async () => {
    try {
        const response = await axios.get('/api/citations/recent', {
            params: { limit: 10 }
        });

        if (response.data.success) {
            recentCitations.value = response.data.citations.map((c: any) => ({
                id: c.id.toString(),
                title: c.title,
                authors: c.authors,
                year: c.year,
                journal: c.journal,
                url: c.url,
                doi: c.doi,
                type: c.type,
                style: {
                    apa: c.style.apa,
                    harvard: c.style.harvard,
                    ieee: c.style.ieee
                },
                createdAt: new Date(c.created_at)
            }));
        }
    } catch (error) {
        console.error('Failed to load recent citations:', error);
        // Don't show error to user - just log it
    }
};

// Load citations and saved detections on mount
onMounted(() => {
    loadRecentCitations();
    loadSavedDetections();
});
</script>

<template>
    <!-- Operation Mode Header (when expanded) -->
    <div v-if="citationOperationMode" class="sticky top-0 z-10 bg-background border-b border-border/60">
        <div class="flex items-center justify-between p-4">
            <div class="flex items-center gap-3">
                <Quote class="h-5 w-5 text-primary" />
                <h2 class="text-lg font-semibold text-foreground">Citation Helper</h2>
                <Badge variant="secondary" class="ml-1">
                    {{ selectedStyle.toUpperCase() }}
                </Badge>
            </div>
            <Button @click="emit('exit-operation-mode')" variant="ghost" size="sm"
                class="gap-2 text-muted-foreground hover:text-foreground">
                <X class="h-4 w-4" />
                Close
            </Button>
        </div>
    </div>

    <!-- Operation Mode Content (expanded view with more space) -->
    <div v-if="citationOperationMode" class="p-6 space-y-4">
        <!-- Citation Style Selector (Collapsible) -->
        <div class="space-y-2">
            <button 
                @click="showCitationStyleSection = !showCitationStyleSection"
                class="w-full flex items-center justify-between text-sm font-semibold text-foreground hover:text-primary transition-colors"
            >
                <span class="flex items-center gap-2">
                    <BookOpen class="h-4 w-4" />
                    Citation Style
                    <Badge variant="outline" class="text-xs">{{ selectedStyle.toUpperCase() }}</Badge>
                </span>
                <ChevronDown :class="['h-4 w-4 transition-transform', showCitationStyleSection ? 'rotate-180' : '']" />
            </button>
            <div v-if="showCitationStyleSection" class="space-y-2 pl-6">
                <div class="flex gap-2">
                    <Button v-for="style in Object.keys(citationStyles)" :key="style"
                        @click="selectedStyle = style as 'apa' | 'harvard' | 'ieee'"
                        :variant="selectedStyle === style ? 'default' : 'outline'" size="sm" class="flex-1">
                        {{ style.toUpperCase() }}
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">{{ currentStyleInfo.description }}</p>
            </div>
        </div>

        <Separator />

        <!-- Citation Generator (Collapsible) -->
        <div class="space-y-2">
            <button 
                @click="showGenerateCitationSection = !showGenerateCitationSection"
                class="w-full flex items-center justify-between text-sm font-semibold text-foreground hover:text-primary transition-colors"
            >
                <span class="flex items-center gap-2">
                    <Wand2 class="h-4 w-4" />
                    Generate Citation
                </span>
                <ChevronDown :class="['h-4 w-4 transition-transform', showGenerateCitationSection ? 'rotate-180' : '']" />
            </button>
            <div v-if="showGenerateCitationSection" class="pl-6">
                <div class="flex gap-2">
                    <Input v-model="searchInput" placeholder="URL, DOI, or search..." class="flex-1"
                        @keyup.enter="searchCitation" :disabled="isSearching" />
                    <Button @click="searchCitation" :disabled="isSearching || !searchInput.trim()" size="sm">
                        <Loader2 v-if="isSearching" class="h-4 w-4 animate-spin" />
                        <Search v-else class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <Separator />

        <!-- Auto-Detect Citations (highlighted in operation mode) -->
        <div class="space-y-4">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
                <Sparkles class="h-4 w-4 text-purple-500" />
                Auto-Detect Citations
            </h3>

            <Button @click="detectClaims" :disabled="isDetecting"
                class="w-full gap-2 h-12 text-base bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600 text-white">
                <Loader2 v-if="isDetecting" class="h-5 w-5 animate-spin" />
                <Sparkles v-else class="h-5 w-5" />
                <span class="font-medium">
                    {{ isDetecting ? 'Scanning content for claims...' : 'Scan for Missing Citations' }}
                </span>
            </Button>

            <p class="text-sm text-muted-foreground">
                🔍 AI analyzes your chapter content to find claims that need academic references, then searches multiple
                databases for relevant papers.
            </p>

            <!-- Detected Claims (larger layout) -->
            <div v-if="showDetectedClaims && detectedClaims.length > 0" class="space-y-3 mt-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-amber-600 dark:text-amber-400 flex items-center gap-2">
                        <AlertTriangle class="h-4 w-4" />
                        {{ detectedClaims.length }} claims need citations
                    </span>
                    <Button variant="outline" size="sm" @click="showDetectedClaims = false; detectedClaims = []">
                        Clear Results
                    </Button>
                </div>

                <div class="flex gap-4 max-h-[500px]">
                    <!-- Left Column: Claims List -->
                    <div class="w-1/2 overflow-y-auto pr-2 custom-scrollbar space-y-2">
                        <div 
                            v-for="claim in detectedClaims" 
                            :key="claim.id"
                            @click="expandedClaim = expandedClaim === claim.id ? null : claim.id"
                            :class="[
                                'rounded-lg border p-3 cursor-pointer transition-all',
                                expandedClaim === claim.id 
                                    ? 'bg-primary/10 border-primary' 
                                    : 'bg-card hover:bg-muted/50 border-border'
                            ]"
                        >
                            <p class="text-sm font-medium text-foreground leading-snug line-clamp-3">
                                "{{ claim.text }}"
                            </p>
                            <div class="flex items-center gap-2 mt-2">
                                <Badge 
                                    variant="outline" 
                                    :class="claim.confidence > 0.8 ? 'border-red-300 text-red-600' : claim.confidence > 0.6 ? 'border-amber-300 text-amber-600' : 'border-gray-300 text-gray-600'"
                                    class="text-xs"
                                >
                                    {{ Math.round(claim.confidence * 100) }}%
                                </Badge>
                                <span v-if="claim.suggestions?.length" class="text-xs text-muted-foreground">
                                    {{ claim.suggestions.length }} suggestions
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Suggestions for Selected Claim -->
                    <div class="w-1/2 overflow-y-auto pl-2 custom-scrollbar">
                        <template v-if="expandedClaim && detectedClaims.find(c => c.id === expandedClaim)">
                            <div class="space-y-3">
                                <div class="text-xs text-muted-foreground mb-2">
                                    {{ detectedClaims.find(c => c.id === expandedClaim)?.reason }}
                                </div>
                                
                                <p class="text-xs font-medium text-foreground">Suggested Citations:</p>
                                
                                <div 
                                    v-for="suggestion in detectedClaims.find(c => c.id === expandedClaim)?.suggestions" 
                                    :key="suggestion.id"
                                    class="rounded-lg border bg-card p-3 space-y-2"
                                >
                                    <p class="text-sm font-medium text-foreground leading-snug">
                                        {{ suggestion.title }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ suggestion.authors?.join(', ') }} ({{ suggestion.year }})
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary" class="text-xs">
                                            {{ suggestion.source }}
                                        </Badge>
                                        <span v-if="suggestion.citation_count" class="text-xs text-muted-foreground">
                                            {{ suggestion.citation_count }} citations
                                        </span>
                                    </div>
                                    <div class="flex gap-2 mt-2">
                                        <Button 
                                            @click="insertSuggestion(suggestion, detectedClaims.find(c => c.id === expandedClaim)?.text)" 
                                            size="sm" 
                                            class="flex-1"
                                        >
                                            <Plus class="h-4 w-4 mr-1" />
                                            Insert
                                        </Button>
                                        <Button @click="copySuggestion(suggestion)" size="sm" variant="outline">
                                            <Copy class="h-4 w-4" />
                                        </Button>
                                        <Button 
                                            v-if="suggestion.url" 
                                            @click="openUrl(suggestion.url)" 
                                            size="sm" 
                                            variant="outline"
                                        >
                                            <ExternalLink class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div 
                                    v-if="!detectedClaims.find(c => c.id === expandedClaim)?.suggestions?.length" 
                                    class="text-sm text-muted-foreground text-center py-4"
                                >
                                    No matching papers found. Try manual search.
                                </div>
                            </div>
                        </template>
                        
                        <div v-else class="flex items-center justify-center h-full text-muted-foreground">
                            <div class="text-center py-8">
                                <Quote class="h-8 w-8 mx-auto mb-2 opacity-30" />
                                <p class="text-sm">Select a claim to view suggestions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="showDetectedClaims && detectedClaims.length === 0 && !isDetecting" class="text-center py-6">
                <p class="text-sm text-green-600 dark:text-green-400">
                    ✅ No claims requiring citations were detected in your content!
                </p>
            </div>
        </div>

        <Separator />

        <!-- Recent Citations -->
        <div v-if="recentCitations.length" class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
                    <History class="h-4 w-4" />
                    Recent Citations ({{ recentCitations.length }})
                </h3>
                <Button @click="handleClearClick" :disabled="isClearing" variant="ghost" size="sm">
                    <Loader2 v-if="isClearing" class="h-4 w-4 animate-spin" />
                    <Trash2 v-else class="h-4 w-4 mr-1" />
                    Clear All
                </Button>
            </div>
            <div class="space-y-2 max-h-[200px] overflow-y-auto pr-2 custom-scrollbar">
                <div v-for="citation in recentCitations" :key="citation.id"
                    class="rounded border bg-muted/30 p-3 cursor-pointer hover:bg-muted/50 transition-colors"
                    @click="insertCitation(citation)">
                    <p class="text-sm font-medium text-foreground truncate">{{ citation.title }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ citation.authors.join(', ') }} ({{ citation.year }})
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Normal Mode (Collapsible Card) -->
    <Card v-if="!citationOperationMode"
        class="border border-border/60 shadow-sm dark:border-border/50 dark:shadow-none transition-all duration-300 hover:shadow-md">
        <Collapsible :open="showCitationHelper" @update:open="handleToggle">
            <CollapsibleTrigger class="w-full">
                <CardHeader
                    class="pb-3 transition-colors bg-muted/40 hover:bg-muted/60 dark:bg-transparent dark:hover:bg-muted/30">
                    <CardTitle class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2">
                            <Quote class="h-4 w-4 text-muted-foreground" />
                            Citation Helper
                            <Badge variant="secondary" class="ml-1 text-xs">
                                {{ selectedStyle.toUpperCase() }}
                            </Badge>
                        </span>
                        <div class="flex items-center gap-1">
                            <!-- Expand Button -->
                            <Button v-if="showCitationHelper" @click.stop="emit('enter-operation-mode')" variant="ghost"
                                size="icon" class="h-6 w-6 text-muted-foreground hover:text-primary"
                                title="Expand to Split View">
                                <Maximize2 class="h-3 w-3" />
                            </Button>
                            <ChevronDown :class="[
                                'h-4 w-4 text-muted-foreground transition-transform',
                                showCitationHelper ? 'rotate-180' : ''
                            ]" />
                        </div>
                    </CardTitle>
                </CardHeader>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <CardContent class="space-y-4 pt-0">
                    <!-- Info Footer -->
                    <div
                        class="rounded-lg bg-gradient-to-r from-amber-50 to-indigo-50 dark:from-amber-950/20 dark:to-indigo-950/20 border border-amber-200/50 dark:border-amber-800/50 p-3">
                        <div class="flex items-start gap-2">
                            <BookOpen class="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                            <div class="text-xs leading-relaxed">
                                <p class="font-medium text-amber-900 dark:text-amber-100 mb-1">Smart Citation Assistant
                                </p>
                                <p class="text-amber-700 dark:text-amber-300">Automatically formats citations in your
                                    preferred academic style.</p>
                                <p class="mt-1 text-amber-600 dark:text-amber-400">💡 Click citations to insert them
                                    directly into your document.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Citation Style Selector -->
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <h4 class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                <BookOpen class="h-3 w-3" />
                                Citation Style
                            </h4>
                            <div class="flex gap-1">
                                <Button v-for="style in Object.keys(citationStyles)" :key="style"
                                    @click="selectedStyle = style as 'apa' | 'harvard' | 'ieee'"
                                    :variant="selectedStyle === style ? 'default' : 'outline'" size="sm"
                                    class="text-xs flex-1">
                                    {{ style.toUpperCase() }}
                                </Button>
                            </div>
                        </div>

                        <!-- Style Info -->
                        <div class="rounded-lg bg-muted/30 p-2">
                            <p class="text-xs font-medium text-foreground mb-1">
                                {{ currentStyleInfo.name }}
                            </p>
                            <p class="text-xs text-muted-foreground mb-2">
                                {{ currentStyleInfo.description }}
                            </p>
                            <p class="text-xs text-muted-foreground font-mono bg-background p-2 rounded border">
                                {{ currentStyleInfo.example }}
                            </p>
                        </div>
                    </div>

                    <Separator />

                    <!-- Citation Generator -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                            <Wand2 class="h-3 w-3" />
                            Generate Citation
                        </h4>

                        <div class="flex gap-2">
                            <Input v-model="searchInput" placeholder="Enter URL, DOI, or search term..." class="text-xs"
                                @keyup.enter="searchCitation" :disabled="isSearching" />
                            <Button @click="searchCitation" size="sm" :disabled="isSearching || !searchInput.trim()">
                                <Loader2 v-if="isSearching" class="h-3 w-3 animate-spin" />
                                <Search v-else class="h-3 w-3" />
                            </Button>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            💡 Paste a journal URL, DOI, or search for papers by keywords
                        </p>
                    </div>

                    <Separator />

                    <!-- Auto-Detect Citations -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                <Sparkles class="h-3 w-3" />
                                Auto-Detect Citations
                            </h4>
                        </div>

                        <Button @click="detectClaims" :disabled="isDetecting" variant="outline"
                            class="w-full gap-2 bg-gradient-to-r from-purple-500/10 to-indigo-500/10 border-purple-200/50 dark:border-purple-800/50 hover:from-purple-500/20 hover:to-indigo-500/20">
                            <Loader2 v-if="isDetecting" class="h-4 w-4 animate-spin" />
                            <Sparkles v-else class="h-4 w-4 text-purple-500" />
                            <span class="text-xs font-medium">
                                {{ isDetecting ? 'Scanning content...' : 'Scan for Missing Citations' }}
                            </span>
                        </Button>

                        <p class="text-xs text-muted-foreground">
                            🔍 AI analyzes your content to find claims that need academic references
                        </p>

                        <!-- Detected Claims -->
                        <div v-if="showDetectedClaims && detectedClaims.length > 0" class="space-y-2 mt-3">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                    <AlertTriangle class="h-3 w-3" />
                                    {{ detectedClaims.length }} claims need citations
                                </span>
                                <Button variant="ghost" size="sm" class="h-6 text-xs"
                                    @click="showDetectedClaims = false; detectedClaims = []">
                                    Clear
                                </Button>
                            </div>

                            <ScrollArea class="h-[280px]">
                                <div class="space-y-2 pr-2">
                                    <div v-for="claim in detectedClaims" :key="claim.id"
                                        class="rounded-lg border bg-background overflow-hidden">
                                        <!-- Claim Header -->
                                        <div @click="toggleClaimExpand(claim.id)"
                                            class="cursor-pointer p-3 hover:bg-muted/30 transition-colors"
                                            :class="expandedClaim === claim.id ? 'bg-muted/20' : ''">
                                            <div class="flex items-start gap-2">
                                                <ChevronRight
                                                    class="h-4 w-4 mt-0.5 text-muted-foreground transition-transform flex-shrink-0"
                                                    :class="expandedClaim === claim.id ? 'rotate-90' : ''" />
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs font-medium text-foreground line-clamp-2">
                                                        "{{ claim.text }}"
                                                    </p>
                                                    <p class="text-[10px] text-muted-foreground mt-1">
                                                        {{ claim.reason }}
                                                    </p>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <Badge variant="outline" class="text-[10px] h-4"
                                                            :class="claim.confidence > 0.8 ? 'border-red-300 text-red-600' : claim.confidence > 0.6 ? 'border-amber-300 text-amber-600' : 'border-gray-300 text-gray-600'">
                                                            {{ Math.round(claim.confidence * 100) }}% confidence
                                                        </Badge>
                                                        <span class="text-[10px] text-muted-foreground">
                                                            {{ claim.suggestions?.length || 0 }} suggestions
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Expanded: Suggestions -->
                                        <div v-if="expandedClaim === claim.id && claim.suggestions?.length > 0"
                                            class="border-t bg-muted/10 p-3 space-y-2">
                                            <p class="text-[10px] font-medium text-muted-foreground mb-2">
                                                Suggested Citations:
                                            </p>
                                            <div v-for="suggestion in claim.suggestions" :key="suggestion.id"
                                                class="rounded border bg-background p-2 space-y-1">
                                                <p class="text-xs font-medium text-foreground line-clamp-2">
                                                    {{ suggestion.title }}
                                                </p>
                                                <p class="text-[10px] text-muted-foreground">
                                                    {{ suggestion.authors?.join(', ') }} ({{ suggestion.year }})
                                                </p>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <Badge variant="secondary" class="text-[9px] h-4">
                                                        {{ suggestion.source }}
                                                    </Badge>
                                                    <span v-if="suggestion.citation_count"
                                                        class="text-[9px] text-muted-foreground">
                                                        {{ suggestion.citation_count }} citations
                                                    </span>
                                                </div>
                                                <div class="flex gap-1 mt-2">
                                                    <Button @click="insertSuggestion(suggestion, claim.text)" size="sm"
                                                        class="flex-1 h-7 text-xs">
                                                        <Plus class="h-3 w-3 mr-1" />
                                                        Insert
                                                    </Button>
                                                    <Button @click="copySuggestion(suggestion)" size="sm"
                                                        variant="outline" class="h-7 text-xs">
                                                        <Copy class="h-3 w-3" />
                                                    </Button>
                                                    <Button v-if="suggestion.url" @click="openUrl(suggestion.url)"
                                                        size="sm" variant="outline" class="h-7 text-xs">
                                                        <ExternalLink class="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Expanded: No Suggestions -->
                                        <div v-if="expandedClaim === claim.id && (!claim.suggestions || claim.suggestions.length === 0)"
                                            class="border-t bg-muted/10 p-3">
                                            <p class="text-xs text-muted-foreground text-center py-2">
                                                No suggestions found. Try searching manually with keywords:
                                                <span class="font-medium">{{ claim.search_keywords?.join(', ') }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </ScrollArea>
                        </div>

                        <!-- Empty state after detection -->
                        <div v-else-if="showDetectedClaims && detectedClaims.length === 0 && !isDetecting"
                            class="text-center py-4">
                            <p class="text-xs text-green-600 dark:text-green-400">
                                ✅ No claims requiring citations were detected!
                            </p>
                        </div>
                    </div>

                    <!-- Recent Citations -->
                    <div v-if="recentCitations.length" class="space-y-3">
                        <Separator />

                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                <History class="h-3 w-3" />
                                Recent Citations ({{ recentCitations.length }})
                            </h4>
                            <Button @click="handleClearClick" :disabled="isClearing" variant="ghost" size="icon"
                                class="h-6 w-6 hover:bg-destructive/10 hover:text-destructive">
                                <Loader2 v-if="isClearing" class="h-3 w-3 animate-spin" />
                                <Trash2 v-else class="h-3 w-3" />
                            </Button>
                        </div>

                        <ScrollArea class="h-[300px]">
                            <div class="space-y-2 pr-2">
                                <div v-for="citation in recentCitations" :key="citation.id" class="group">
                                    <div @click="toggleCitationExpand(citation.id)"
                                        class="cursor-pointer rounded-lg border bg-background p-3 hover:bg-muted/30 transition-colors"
                                        :class="expandedCitation === citation.id ? 'ring-2 ring-primary' : ''">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-foreground truncate">
                                                    {{ citation.title }}
                                                </p>
                                                <p class="text-xs text-muted-foreground mt-1">
                                                    {{ citation.authors.join(', ') }} ({{ citation.year }})
                                                </p>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <Badge variant="outline" class="text-xs">
                                                        {{ citation.type }}
                                                    </Badge>
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ citation.createdAt.toLocaleDateString() }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div
                                                class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <Button @click.stop="insertCitation(citation)" variant="ghost"
                                                    size="icon" class="h-6 w-6">
                                                    <Plus class="h-3 w-3" />
                                                </Button>
                                                <Button @click.stop="copyCitation(citation)" variant="ghost" size="icon"
                                                    class="h-6 w-6">
                                                    <Copy class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Expanded Citation Preview -->
                                        <div v-if="expandedCitation === citation.id" class="mt-3 pt-3 border-t">
                                            <div class="bg-muted/30 rounded p-2">
                                                <p class="text-xs font-medium text-foreground mb-1">
                                                    {{ selectedStyle.toUpperCase() }} Format:
                                                </p>
                                                <p class="text-xs text-muted-foreground font-mono leading-relaxed">
                                                    {{ citation.style[selectedStyle] }}
                                                </p>
                                            </div>

                                            <div class="flex gap-2 mt-3">
                                                <Button @click="insertCitation(citation)" size="sm"
                                                    class="flex-1 text-xs">
                                                    <Plus class="mr-1 h-3 w-3" />
                                                    Insert
                                                </Button>
                                                <Button @click="copyCitation(citation)" size="sm" variant="outline"
                                                    class="flex-1 text-xs">
                                                    <Copy class="mr-1 h-3 w-3" />
                                                    Copy
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="py-8 text-center">
                        <Quote class="mx-auto h-12 w-12 text-muted-foreground/30 mb-3" />
                        <p class="text-xs text-muted-foreground mb-1">
                            No citations yet
                        </p>
                        <p class="text-xs text-muted-foreground/80">
                            Generate your first citation using the search above
                        </p>
                    </div>
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>

    <!-- Clear Citations Confirmation Dialog -->
    <AlertDialog :open="showClearDialog" @update:open="showClearDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Clear All Citations</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to clear all {{ recentCitations.length }} citations?
                    This action cannot be undone and will permanently remove all citations from your library.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="showClearDialog = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="clearAllCitations"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                    Clear All Citations
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>