<!-- /resources/js/components/chapter-editor/ExportMenu.vue -->
<!-- resources/js/components/chapter-editor/ExportMenu.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { ChevronDown, Download, FileDown, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    status: 'not_started' | 'draft' | 'in_review' | 'approved';
}

interface Project {
    id: number;
    slug: string;
    title: string;
    topic: string;
    type: string;
    status: string;
    mode: 'auto' | 'manual';
    field_of_study: string;
    university: string;
    course: string;
}

interface Props {
    project: Project;
    currentChapter: Chapter;
    allChapters: Chapter[];
    triggerElement?: 'button' | 'icon';
    size?: 'sm' | 'default' | 'lg';
    variant?: 'default' | 'outline' | 'secondary' | 'ghost' | 'link';
}

const props = withDefaults(defineProps<Props>(), {
    triggerElement: 'button',
    size: 'default',
    variant: 'outline',
});

// State for multi-chapter export
const showMultiChapterDialog = ref(false);
const selectedChapters = ref<number[]>([]);

// Computed
const availableChapters = computed(() => 
    props.allChapters.filter(chapter => 
        chapter.content && chapter.content.trim().length > 0
    )
);

const selectedChaptersCount = computed(() => selectedChapters.value.length);

const totalSelectedWords = computed(() => {
    return props.allChapters
        .filter(chapter => selectedChapters.value.includes(chapter.chapter_number))
        .reduce((total, chapter) => total + chapter.word_count, 0);
});

// Methods
const exportCurrentChapter = async () => {
    toast.loading('Exporting chapter...', { id: 'export-chapter' });
    
    const exportUrl = route('export.chapter.word', {
        project: props.project.slug,
        chapterNumber: props.currentChapter.chapter_number,
    });
    
    try {
        const response = await fetch(exportUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            // Check if response is JSON (error) or file download
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                toast.error('Export Failed', { 
                    id: 'export-chapter',
                    description: errorData.message || 'An error occurred during export.'
                });
                return;
            }

            // Success - trigger download
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${props.project.slug}-chapter-${props.currentChapter.chapter_number}.docx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            toast.success('Chapter exported successfully!', { 
                id: 'export-chapter',
                description: `${props.currentChapter.title} has been downloaded.`
            });
        } else {
            // Try to get error message from response
            try {
                const errorData = await response.json();
                toast.error('Export Failed', { 
                    id: 'export-chapter',
                    description: errorData.message || 'An error occurred during export.'
                });
            } catch {
                toast.error('Export Failed', { 
                    id: 'export-chapter',
                    description: 'Unable to export chapter. Please try again.'
                });
            }
        }
    } catch (error) {
        toast.error('Export Failed', { 
            id: 'export-chapter',
            description: 'Network error occurred. Please check your connection and try again.'
        });
    }
};

const exportFullProject = async () => {
    toast.loading('Exporting full project...', { id: 'export-project' });

    const exportUrl = route('export.project.word', {
        project: props.project.slug,
    });

    try {
        const response = await fetch(exportUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            // Check if response is JSON (error) or file download
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                toast.error('Export Failed', {
                    id: 'export-project',
                    description: errorData.message || 'An error occurred during export.'
                });
                return;
            }

            // Success - trigger download
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${props.project.slug}.docx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            toast.success('Project exported successfully!', {
                id: 'export-project',
                description: `${props.project.title} has been downloaded.`
            });
        } else {
            try {
                const errorData = await response.json();
                toast.error('Export Failed', {
                    id: 'export-project',
                    description: errorData.message || 'An error occurred during export.'
                });
            } catch {
                toast.error('Export Failed', {
                    id: 'export-project',
                    description: 'Unable to export project. Please try again.'
                });
            }
        }
    } catch (error) {
        toast.error('Export Failed', {
            id: 'export-project',
            description: 'Network error occurred. Please check your connection and try again.'
        });
    }
};

const exportFullProjectPdf = async () => {
    toast.loading('Generating project PDF...', { id: 'export-project-pdf' });

    const exportUrl = route('export.project.pdf', {
        project: props.project.slug,
    });

    try {
        const response = await fetch(exportUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/pdf',
            },
        });

        if (response.ok) {
            // Check if response is JSON (error) or file download
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                toast.error('Export Failed', {
                    id: 'export-project-pdf',
                    description: errorData.message || 'An error occurred during PDF export.'
                });
                return;
            }

            // Success - trigger download
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${props.project.slug}_full_project.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            toast.success('Project PDF exported successfully!', {
                id: 'export-project-pdf',
                description: `${props.project.title} has been downloaded as PDF.`
            });
        } else {
            // Try to get error message from response
            try {
                const errorData = await response.json();
                toast.error('Export Failed', {
                    id: 'export-project-pdf',
                    description: errorData.message || 'An error occurred during PDF export.'
                });
            } catch {
                toast.error('Export Failed', {
                    id: 'export-project-pdf',
                    description: 'Unable to export project as PDF. Please try again.'
                });
            }
        }
    } catch (error) {
        toast.error('Export Failed', {
            id: 'export-project-pdf',
            description: 'Network error occurred. Please check your connection and try again.'
        });
    }
};

const exportSelectedChapters = async () => {
    if (selectedChapters.value.length === 0) {
        toast.error('No chapters selected', {
            description: 'Please select at least one chapter to export.'
        });
        return;
    }

    try {
        toast.loading(`Exporting ${selectedChapters.value.length} chapters...`, { id: 'export-chapters' });
        
        const exportUrl = route('export.chapters.word', {
            project: props.project.slug,
        });
        
        // Create form data
        const formData = new FormData();
        selectedChapters.value.forEach(chapterNumber => {
            formData.append('chapters[]', chapterNumber.toString());
        });
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }
        
        // Make the request
        const response = await fetch(exportUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
            },
        });
        
        if (response.ok) {
            // Check if response is JSON (error) or file download
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                toast.error('Export Failed', { 
                    id: 'export-chapters',
                    description: errorData.message || 'An error occurred during export.'
                });
                return;
            }

            // Success - create blob from response
            const blob = await response.blob();
            
            // Create download link
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${props.project.slug}-chapters-${selectedChapters.value.join('-')}.docx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            toast.success('Selected chapters exported successfully!', { 
                id: 'export-chapters',
                description: `${selectedChapters.value.length} chapters exported to Word.`
            });
            
            // Reset selection and close dialog
            selectedChapters.value = [];
            showMultiChapterDialog.value = false;
        } else {
            // Try to get error message from response
            try {
                const errorData = await response.json();
                toast.error('Export Failed', { 
                    id: 'export-chapters',
                    description: errorData.message || 'An error occurred during export.'
                });
            } catch {
                toast.error('Export Failed', { 
                    id: 'export-chapters',
                    description: 'Unable to export selected chapters. Please try again.'
                });
            }
        }
    } catch (error) {
        toast.error('Export Failed', { 
            id: 'export-chapters',
            description: 'Network error occurred. Please check your connection and try again.'
        });
    }
};

const toggleChapterSelection = (chapterNumber: number) => {
    const index = selectedChapters.value.indexOf(chapterNumber);
    if (index > -1) {
        selectedChapters.value.splice(index, 1);
    } else {
        selectedChapters.value.push(chapterNumber);
    }
};

const selectAllChapters = () => {
    selectedChapters.value = availableChapters.value.map(chapter => chapter.chapter_number);
};

const clearSelection = () => {
    selectedChapters.value = [];
};

const openMultiChapterDialog = () => {
    selectedChapters.value = [];
    showMultiChapterDialog.value = true;
};

const exportCurrentChapterPdf = async () => {
    toast.loading('Generating PDF...', { id: 'export-chapter-pdf' });

    const exportUrl = route('export.chapter.pdf', {
        project: props.project.slug,
        chapterNumber: props.currentChapter.chapter_number,
    });

    try {
        const response = await fetch(exportUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/pdf',
            },
        });

        if (response.ok) {
            // Check if response is JSON (error) or file download
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                toast.error('Export Failed', {
                    id: 'export-chapter-pdf',
                    description: errorData.message || 'An error occurred during PDF export.'
                });
                return;
            }

            // Success - trigger download
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `chapter_${props.currentChapter.chapter_number}_${props.currentChapter.title.toLowerCase().replace(/\s+/g, '_')}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            toast.success('PDF exported successfully!', {
                id: 'export-chapter-pdf',
                description: `${props.currentChapter.title} has been downloaded as PDF.`
            });
        } else {
            // Try to get error message from response
            try {
                const errorData = await response.json();
                toast.error('Export Failed', {
                    id: 'export-chapter-pdf',
                    description: errorData.message || 'An error occurred during PDF export.'
                });
            } catch {
                toast.error('Export Failed', {
                    id: 'export-chapter-pdf',
                    description: 'Unable to export chapter as PDF. Please try again.'
                });
            }
        }
    } catch (error) {
        toast.error('Export Failed', {
            id: 'export-chapter-pdf',
            description: 'Network error occurred. Please check your connection and try again.'
        });
    }
};
</script>

<template>
    <div class="inline-flex">
        <!-- Export Dropdown Menu -->
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button 
                    :variant="variant" 
                    :size="size" 
                    class="gap-2"
                >
                    <template v-if="triggerElement === 'button'">
                        <Download class="h-4 w-4" />
                        Export
                        <ChevronDown class="h-3 w-3" />
                    </template>
                    <template v-else>
                        <Download class="h-4 w-4" />
                    </template>
                </Button>
            </DropdownMenuTrigger>
            
            <DropdownMenuContent align="end" class="w-64">
                <!-- Current Chapter Export Options -->
                <DropdownMenuLabel>Current Chapter</DropdownMenuLabel>

                <DropdownMenuItem @click="exportCurrentChapter" class="gap-2">
                    <FileText class="h-4 w-4" />
                    <div class="flex flex-col">
                        <span class="font-medium">Export as Word</span>
                        <span class="text-xs text-muted-foreground">
                            Chapter {{ currentChapter.chapter_number }}: {{ currentChapter.title }}
                        </span>
                    </div>
                </DropdownMenuItem>

                <DropdownMenuItem @click="exportCurrentChapterPdf" class="gap-2">
                    <FileText class="h-4 w-4" />
                    <div class="flex flex-col">
                        <span class="font-medium">Export as PDF</span>
                        <span class="text-xs text-muted-foreground">
                            Professional academic format
                        </span>
                    </div>
                </DropdownMenuItem>

                <DropdownMenuSeparator />
                
                <!-- Selected Chapters Export -->
                <DropdownMenuItem @click="openMultiChapterDialog" class="gap-2">
                    <FileDown class="h-4 w-4" />
                    <div class="flex flex-col">
                        <span class="font-medium">Selected Chapters</span>
                        <span class="text-xs text-muted-foreground">
                            Choose specific chapters to export
                        </span>
                    </div>
                </DropdownMenuItem>
                
                <DropdownMenuSeparator />
                
                <!-- Full Project Export -->
                <DropdownMenuLabel>Full Project</DropdownMenuLabel>

                <DropdownMenuItem @click="exportFullProject" class="gap-2">
                    <FileText class="h-4 w-4" />
                    <div class="flex flex-col">
                        <span class="font-medium">Export as Word</span>
                        <span class="text-xs text-muted-foreground">
                            Entire project with all chapters
                        </span>
                    </div>
                </DropdownMenuItem>

                <DropdownMenuItem @click="exportFullProjectPdf" class="gap-2">
                    <FileText class="h-4 w-4" />
                    <div class="flex flex-col">
                        <span class="font-medium">Export as PDF</span>
                        <span class="text-xs text-muted-foreground">
                            Professional academic format with cover page
                        </span>
                    </div>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        
        <!-- Multi-Chapter Selection Dialog -->
        <Dialog v-model:open="showMultiChapterDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FileDown class="h-5 w-5" />
                        Select Chapters to Export
                    </DialogTitle>
                    <DialogDescription>
                        Choose which chapters you want to include in your Word export.
                        Only chapters with content can be exported.
                    </DialogDescription>
                </DialogHeader>
                
                <div class="space-y-4">
                    <!-- Selection Controls -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>{{ selectedChaptersCount }} of {{ availableChapters.length }} chapters selected</span>
                            <Badge v-if="totalSelectedWords > 0" variant="outline" class="text-xs">
                                {{ totalSelectedWords.toLocaleString() }} words
                            </Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button @click="selectAllChapters" variant="ghost" size="sm">
                                Select All
                            </Button>
                            <Button @click="clearSelection" variant="ghost" size="sm">
                                Clear
                            </Button>
                        </div>
                    </div>
                    
                    <Separator />
                    
                    <!-- Chapter List -->
                    <ScrollArea class="h-[300px]">
                        <div class="space-y-2">
                            <Card 
                                v-for="chapter in availableChapters" 
                                :key="chapter.id"
                                class="cursor-pointer transition-colors hover:bg-muted/50"
                                :class="{
                                    'ring-2 ring-primary': selectedChapters.includes(chapter.chapter_number),
                                    'bg-muted/30': selectedChapters.includes(chapter.chapter_number)
                                }"
                                @click="toggleChapterSelection(chapter.chapter_number)"
                            >
                                <CardContent class="p-4">
                                    <div class="flex items-start gap-3">
                                        <Checkbox 
                                            :checked="selectedChapters.includes(chapter.chapter_number)"
                                            @update:checked="toggleChapterSelection(chapter.chapter_number)"
                                            class="mt-1"
                                        />
                                        
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <h4 class="font-medium truncate">
                                                    Chapter {{ chapter.chapter_number }}: {{ chapter.title }}
                                                </h4>
                                                <Badge 
                                                    :variant="chapter.status === 'approved' ? 'default' : 'secondary'" 
                                                    class="text-xs"
                                                >
                                                    {{ chapter.status.replace('_', ' ') }}
                                                </Badge>
                                            </div>
                                            
                                            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                                                <span>{{ chapter.word_count.toLocaleString() }} words</span>
                                                <span v-if="chapter.word_count > 0">
                                                    {{ Math.ceil(chapter.word_count / 250) }} pages approx.
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </ScrollArea>
                </div>
                
                <DialogFooter>
                    <Button @click="showMultiChapterDialog = false" variant="outline">
                        Cancel
                    </Button>
                    <Button 
                        @click="exportSelectedChapters" 
                        :disabled="selectedChaptersCount === 0"
                        class="gap-2"
                    >
                        <Download class="h-4 w-4" />
                        Export {{ selectedChaptersCount }} Chapter{{ selectedChaptersCount === 1 ? '' : 's' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>