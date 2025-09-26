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
    Wand2
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
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:showCitationHelper': [value: boolean];
    'insert-citation': [citation: string];
}>();

// Local state
const selectedStyle = ref<'apa' | 'harvard' | 'ieee'>('apa');
const searchInput = ref('');
const isSearching = ref(false);
const isClearing = ref(false);
const showClearDialog = ref(false);
const recentCitations = ref<Citation[]>([]);
const expandedCitation = ref<string | null>(null);

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

// Load citations on mount
onMounted(() => {
    loadRecentCitations();
});
</script>

<template>
    <Card class="border-[0.5px] border-border/50">
        <Collapsible :open="showCitationHelper" @update:open="handleToggle">
            <CollapsibleTrigger class="w-full">
                <CardHeader class="pb-3 transition-colors hover:bg-muted/30">
                    <CardTitle class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2">
                            <Quote class="h-4 w-4 text-muted-foreground" />
                            Citation Helper
                            <Badge variant="secondary" class="ml-1 text-xs">
                                {{ selectedStyle.toUpperCase() }}
                            </Badge>
                        </span>
                        <ChevronDown :class="[
                            'h-4 w-4 text-muted-foreground transition-transform',
                            showCitationHelper ? 'rotate-180' : ''
                        ]" />
                    </CardTitle>
                </CardHeader>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <CardContent class="space-y-4 pt-0">
                    <!-- Info Footer -->
                    <div class="rounded-lg bg-gradient-to-r from-amber-50 to-indigo-50 dark:from-amber-950/20 dark:to-indigo-950/20 border border-amber-200/50 dark:border-amber-800/50 p-3">
                        <div class="flex items-start gap-2">
                            <BookOpen class="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                            <div class="text-xs leading-relaxed">
                                <p class="font-medium text-amber-900 dark:text-amber-100 mb-1">Smart Citation Assistant</p>
                                <p class="text-amber-700 dark:text-amber-300">Automatically formats citations in your preferred academic style.</p>
                                <p class="mt-1 text-amber-600 dark:text-amber-400">💡 Click citations to insert them directly into your document.</p>
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
                                <Button 
                                    v-for="style in Object.keys(citationStyles)" 
                                    :key="style"
                                    @click="selectedStyle = style as 'apa' | 'harvard' | 'ieee'"
                                    :variant="selectedStyle === style ? 'default' : 'outline'"
                                    size="sm"
                                    class="text-xs flex-1"
                                >
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
                            <Input
                                v-model="searchInput"
                                placeholder="Enter URL, DOI, or search term..."
                                class="text-xs"
                                @keyup.enter="searchCitation"
                                :disabled="isSearching"
                            />
                            <Button 
                                @click="searchCitation" 
                                size="sm"
                                :disabled="isSearching || !searchInput.trim()"
                            >
                                <Loader2 v-if="isSearching" class="h-3 w-3 animate-spin" />
                                <Search v-else class="h-3 w-3" />
                            </Button>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            💡 Paste a journal URL, DOI, or search for papers by keywords
                        </p>
                    </div>

                    <!-- Recent Citations -->
                    <div v-if="recentCitations.length" class="space-y-3">
                        <Separator />
                        
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                <History class="h-3 w-3" />
                                Recent Citations ({{ recentCitations.length }})
                            </h4>
                            <Button 
                                @click="handleClearClick"
                                :disabled="isClearing"
                                variant="ghost" 
                                size="icon"
                                class="h-6 w-6 hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Loader2 v-if="isClearing" class="h-3 w-3 animate-spin" />
                                <Trash2 v-else class="h-3 w-3" />
                            </Button>
                        </div>

                        <ScrollArea class="h-[300px]">
                            <div class="space-y-2 pr-2">
                                <div 
                                    v-for="citation in recentCitations" 
                                    :key="citation.id"
                                    class="group"
                                >
                                    <div 
                                        @click="toggleCitationExpand(citation.id)"
                                        class="cursor-pointer rounded-lg border bg-background p-3 hover:bg-muted/30 transition-colors"
                                        :class="expandedCitation === citation.id ? 'ring-2 ring-primary' : ''"
                                    >
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
                                            
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <Button
                                                    @click.stop="insertCitation(citation)"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-6 w-6"
                                                >
                                                    <Plus class="h-3 w-3" />
                                                </Button>
                                                <Button
                                                    @click.stop="copyCitation(citation)"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-6 w-6"
                                                >
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
                                                <Button 
                                                    @click="insertCitation(citation)"
                                                    size="sm" 
                                                    class="flex-1 text-xs"
                                                >
                                                    <Plus class="mr-1 h-3 w-3" />
                                                    Insert
                                                </Button>
                                                <Button 
                                                    @click="copyCitation(citation)"
                                                    size="sm" 
                                                    variant="outline"
                                                    class="flex-1 text-xs"
                                                >
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
                <AlertDialogAction 
                    @click="clearAllCitations"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                >
                    Clear All Citations
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>