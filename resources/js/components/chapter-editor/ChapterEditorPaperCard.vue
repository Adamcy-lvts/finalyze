<template>
    <Card class="w-full max-w-[850px] min-h-[calc(100vh-180px)] flex flex-col bg-background shadow-xl shadow-black/5 ring-1 ring-black/5 dark:ring-white/10 rounded-xl overflow-hidden transition-all duration-300">
        <CardHeader class="flex-shrink-0 border-b border-border/30 px-8 py-6 bg-background/50 backdrop-blur-sm sticky top-0 z-10">
            <div class="flex items-center justify-between">
                <div class="space-y-1 w-full max-w-[520px]">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex flex-col">
                            <Label for="chapter-title"
                                class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Chapter
                                Title</Label>
                            <Input id="chapter-title" v-model="context.chapterTitle"
                                placeholder="Enter chapter title..."
                                class="h-auto p-0 border-0 bg-transparent text-2xl font-bold placeholder:text-muted-foreground/40 focus-visible:ring-0 px-0 text-foreground" />
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :variant="context.chapter.status === 'approved' ? 'default' : 'secondary'"
                                class="rounded-full px-3">
                                {{ context.chapter.status.replace('_', ' ') }}
                            </Badge>
                            <Badge variant="outline" class="rounded-full px-3 transition-colors" :class="{
                                'text-green-600 border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800': (context.latestAnalysis?.total_score || 0) >= 80,
                                'text-yellow-600 border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800': (context.latestAnalysis?.total_score || 0) >= 70 && (context.latestAnalysis?.total_score || 0) < 80,
                                'text-orange-600 border-orange-200 bg-orange-50 dark:bg-orange-900/20 dark:border-orange-800': (context.latestAnalysis?.total_score || 0) >= 60 && (context.latestAnalysis?.total_score || 0) < 70,
                                'text-red-600 border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800': (context.latestAnalysis?.total_score || 0) < 60
                            }">
                                {{ context.latestAnalysis?.total_score ? Math.round(context.latestAnalysis.total_score)
                                    :
                                    context.writingQualityScore }}% Quality
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="flex min-h-0 flex-1 flex-col p-0">
            <div class="flex items-center justify-between px-6 py-2 border-b border-border/30 bg-muted/5">
                <div class="flex items-center gap-2">
                    <Button @click="context.togglePresentationMode"
                        :variant="context.showPresentationMode ? 'default' : 'ghost'" size="sm"
                        class="h-7 text-xs rounded-full text-zinc-700 dark:text-zinc-300 hover:text-foreground">
                        <Eye class="mr-1.5 h-3.5 w-3.5" />
                        {{ context.showPresentationMode ? 'Edit' : 'Preview' }}
                    </Button>
                </div>
                <div class="space-y-1 relative z-10">
                    <div class="text-sm font-medium text-foreground">
                        {{ sectionName }}
                    </div>
                    <div class="text-[10px] text-muted-foreground line-clamp-2 leading-relaxed">
                        {{ sectionDescription }}
                    </div>
                </div>
            </div>

            <div class="flex-1 relative bg-background">
                <RichTextEditor v-show="!context.showPresentationMode" v-model="context.chapterContent"
                    placeholder="Start writing your chapter..." min-height="500px"
                    class="min-h-[500px] px-8 py-6" :ref="context.richTextEditor" :show-toolbar="true"
                    :streaming-mode="context.isStreamingMode" :is-generating="context.isGenerating"
                    :generation-progress="context.generationProgress"
                    :generation-percentage="context.generationPercentage"
                    :generation-phase="context.generationPhase"
                    @update:selected-text="context.setSelectedText" />

                <div v-show="context.showPresentationMode" class="px-12 py-10 min-h-[500px]">
                    <RichTextViewer :content="context.chapterContent" :show-font-controls="false"
                        class="prose-lg mx-auto"
                        style="font-family: 'Times New Roman', serif; line-height: 1.8" />
                </div>
            </div>
        </CardContent>

        <div class="sticky bottom-0 z-10 border-t border-border/30 bg-background/80 backdrop-blur-md p-4 flex items-center justify-between">
            <div class="text-xs text-muted-foreground">
                {{ context.isSaving ? 'Saving...' : 'All changes saved' }}
            </div>
            <div class="flex items-center gap-2">
                <Button @click="() => context.save(false)" :disabled="!context.isValid || context.isSaving" size="sm"
                    variant="ghost"
                    class="h-8 rounded-full text-zinc-700 dark:text-zinc-300 hover:text-foreground">
                    <Save class="mr-2 h-3.5 w-3.5" />
                    Save Draft
                </Button>

                <Button @click="context.goToBulkAnalysis" variant="outline" size="sm"
                    class="h-8 rounded-full">
                    <BookCheck class="mr-2 h-3.5 w-3.5" />
                    Run Bulk Analysis
                </Button>

                <Button @click="context.markAsComplete" :disabled="context.isSaving" size="sm"
                    class="h-8 rounded-full bg-gradient-to-r from-primary to-primary/90 shadow-sm hover:shadow-md transition-all">
                    <CheckCircle class="mr-2 h-3.5 w-3.5" />
                    Complete
                </Button>
            </div>
        </div>
    </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, Save, BookCheck, CheckCircle } from 'lucide-vue-next';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import type { ChapterEditorPaperCardContext } from '@/types/chapter-editor-layout';

const props = defineProps<{
    context: ChapterEditorPaperCardContext;
}>();

const context = props.context;

const sectionDetails = computed(() => {
    const fallback = context.getSectionInfo(context.nextSection);
    return context.aiChapterAnalysis?.section || fallback;
});

const sectionName = computed(() => sectionDetails.value?.name || '');
const sectionDescription = computed(() => sectionDetails.value?.description || '');
</script>
