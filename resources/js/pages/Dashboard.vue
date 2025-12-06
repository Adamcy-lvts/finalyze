<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { BookOpen, Clock, FileText, PenTool, Plus, Target, Award, Sparkles, Info } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    user: {
        name: string;
        email: string;
    };
    stats: {
        totalProjects: number;
        activeProjects: number;
        completedProjects: number;
        totalWords: number;
        avgWordsPerChapter: number;
        citationsAdded: number;
        researchPapers: number;
        defenseQuestions: number;
        aiAssistanceUsed: number;
        hoursSpent: number;
        weeklyGoalProgress: number;
    };
    activeProject: {
        id: number;
        slug: string;
        title: string;
        type: string;
        progress: number;
        currentChapter: number;
        chapters: Array<{
            number: number;
            status: string;
            word_count: number;
            target_word_count: number;
        }>;
    } | null;
    recentActivities: Array<{
        id: string;
        type: string;
        description: string;
        time: string;
    }>;
}

import CreditBalanceCard from '@/components/CreditBalanceCard.vue';
import { useWordBalance } from '@/composables/useWordBalance';

defineProps<Props>();

// Use composable for real-time balance updates
const { wordBalance } = useWordBalance();

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return "Good afternoon";
    return "Good evening";
});

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('en-US', { notation: "compact", compactDisplay: "short" }).format(num);
};

const formatStatus = (status: string) => {
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const isChapterComplete = (chapter: any) => {
    return ['approved', 'completed'].includes(chapter.status) || (chapter.target_word_count > 0 && chapter.word_count >= chapter.target_word_count);
};
</script>

<template>
    <AppLayout title="Dashboard">
        <TooltipProvider>
            <div class="p-4 md:p-8 max-w-7xl mx-auto space-y-6 md:space-y-8">
                <!-- Header Section -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="space-y-1">
                        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">{{ greeting }}, {{
                            user.name }}!</h1>
                        <p class="text-sm md:text-base text-muted-foreground">Here's what's happening with your projects
                            today.</p>
                    </div>
                    <div v-if="activeProject" class="w-full md:w-auto">
                        <Button @click="() => router.visit(route('projects.show', activeProject!.slug))"
                            class="w-full md:w-auto shadow-lg shadow-primary/20">
                            <PenTool class="mr-2 h-4 w-4" />
                            Continue Writing
                        </Button>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        class="relative overflow-hidden border-none bg-gradient-to-br from-blue-500/10 to-indigo-500/10 dark:from-blue-500/5 dark:to-indigo-500/5 hover:bg-accent/50 transition-colors">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Total Projects</CardTitle>
                            <FileText class="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.totalProjects }}</div>
                            <p class="text-xs text-muted-foreground mt-1">
                                <span class="text-green-500 font-medium">{{ stats.activeProjects }} active</span>
                                <span class="mx-1">•</span>
                                <span>{{ stats.completedProjects }} completed</span>
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        class="relative overflow-hidden border-none bg-gradient-to-br from-green-500/10 to-emerald-500/10 dark:from-green-500/5 dark:to-emerald-500/5 hover:bg-accent/50 transition-colors">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Words Written</CardTitle>
                            <PenTool class="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatNumber(stats.totalWords) }}</div>
                            <p class="text-xs text-muted-foreground mt-1">
                                ~{{ formatNumber(stats.avgWordsPerChapter) }} words per chapter
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        class="relative overflow-hidden border-none bg-gradient-to-br from-purple-500/10 to-violet-500/10 dark:from-purple-500/5 dark:to-violet-500/5 hover:bg-accent/50 transition-colors">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <div class="flex items-center gap-2">
                                <CardTitle class="text-sm font-medium text-muted-foreground">Research</CardTitle>
                                <Tooltip>
                                    <TooltipTrigger>
                                        <Info class="h-3 w-3 text-muted-foreground/50" />
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Tracks citations added and papers collected across all projects</p>
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <BookOpen class="h-4 w-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.citationsAdded }}</div>
                            <p class="text-xs text-muted-foreground mt-1">
                                Citations from {{ stats.researchPapers }} papers
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        class="relative overflow-hidden border-none bg-gradient-to-br from-orange-500/10 to-amber-500/10 dark:from-orange-500/5 dark:to-amber-500/5 hover:bg-accent/50 transition-colors">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Est. Time Invested</CardTitle>
                            <Clock class="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.hoursSpent }}h</div>
                            <p class="text-xs text-muted-foreground mt-1">
                                Based on writing activity
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content Area -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Active Project Card -->
                        <div v-if="activeProject"
                            class="group relative overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm transition-all hover:shadow-md">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity" />

                            <div class="p-4 md:p-6">
                                <div class="flex flex-col md:flex-row items-start justify-between mb-6 gap-4">
                                    <div class="space-y-1">
                                        <Badge variant="outline" class="mb-2 bg-background/50 backdrop-blur">{{
                                            activeProject.type }}</Badge>
                                        <SafeHtmlText
                                            as="h3"
                                            class="text-xl md:text-2xl font-bold tracking-tight line-clamp-2"
                                            :content="activeProject.title"
                                        />
                                        <p class="text-muted-foreground flex items-center gap-2 text-sm md:text-base">
                                            <span
                                                class="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                                            Currently working on Chapter {{ activeProject.currentChapter }}
                                        </p>
                                    </div>
                                    <div class="text-left md:text-right">
                                        <div class="text-3xl font-bold text-primary">{{ activeProject.progress }}%</div>
                                        <div class="text-xs text-muted-foreground">Overall Completion</div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="font-medium">Progress</span>
                                            <span class="text-muted-foreground">{{ activeProject.progress }}%</span>
                                        </div>
                                        <div class="relative h-3 w-full overflow-hidden rounded-full bg-secondary">
                                            <div class="h-full w-full flex-1 bg-primary transition-all duration-500 ease-in-out"
                                                :style="{ width: `${activeProject.progress}%` }"></div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-5 gap-2 sm:gap-4">
                                        <div v-for="chapter in activeProject.chapters" :key="chapter.number"
                                            class="space-y-2 text-center group/chapter">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <div class="mx-auto h-2 w-full rounded-full transition-all cursor-help"
                                                        :class="{
                                                            'bg-green-500': isChapterComplete(chapter),
                                                            'bg-blue-500': !isChapterComplete(chapter) && chapter.status === 'in_review',
                                                            'bg-yellow-500': !isChapterComplete(chapter) && chapter.status === 'draft',
                                                            'bg-muted group-hover/chapter:bg-muted-foreground/50': !isChapterComplete(chapter) && !['in_review', 'draft'].includes(chapter.status)
                                                        }"></div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Chapter {{ chapter.number }}: {{ formatStatus(chapter.status) }}
                                                    </p>
                                                    <p class="text-xs text-muted-foreground mt-1"
                                                        v-if="chapter.target_word_count > 0">
                                                        {{ formatNumber(chapter.word_count) }} / {{
                                                        formatNumber(chapter.target_word_count) }} words
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                            <span class="text-[10px] md:text-xs text-muted-foreground">Ch. {{
                                                chapter.number }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="bg-muted/30 p-4 flex flex-col sm:flex-row justify-end gap-3 border-t">
                                <Button variant="ghost" size="sm" class="w-full sm:w-auto"
                                    @click="() => router.visit(route('projects.show', activeProject!.slug))">
                                    View Details
                                </Button>
                                <Button size="sm" class="w-full sm:w-auto"
                                    @click="() => router.visit(route('projects.writing', activeProject!.slug))">
                                    <PenTool class="mr-2 h-4 w-4" />
                                    Open Writer
                                </Button>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="rounded-xl border border-dashed p-8 md:p-12 text-center bg-muted/10">
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                <Plus class="h-6 w-6 text-muted-foreground" />
                            </div>
                            <h3 class="text-lg font-semibold">No Active Project</h3>
                            <p class="text-muted-foreground mb-6">Start a new project to begin tracking your progress.
                            </p>
                            <Button @click="() => router.visit(route('projects.create'))">
                                Create Project
                            </Button>
                        </div>

                        <!-- Secondary Stats Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div
                                        class="rounded-lg border bg-card p-4 hover:bg-accent/50 transition-colors cursor-help">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-md bg-purple-500/10 text-purple-500">
                                                <Award class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold">{{ stats.defenseQuestions }}</div>
                                                <div class="text-xs text-muted-foreground">Defense Qs</div>
                                            </div>
                                        </div>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Practice questions generated for your defense</p>
                                </TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div
                                        class="rounded-lg border bg-card p-4 hover:bg-accent/50 transition-colors cursor-help">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-md bg-yellow-500/10 text-yellow-500">
                                                <Sparkles class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold">{{ stats.aiAssistanceUsed }}</div>
                                                <div class="text-xs text-muted-foreground">AI Assists</div>
                                            </div>
                                        </div>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Total AI chat interactions and suggestions used</p>
                                </TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div
                                        class="rounded-lg border bg-card p-4 hover:bg-accent/50 transition-colors cursor-help">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-md bg-emerald-500/10 text-emerald-500">
                                                <Target class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold">Active</div>
                                                <div class="text-xs text-muted-foreground">Status</div>
                                            </div>
                                        </div>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Current status of your main project</p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Credit Balance Card -->
                        <CreditBalanceCard v-if="wordBalance" :balance="wordBalance" />

                        <!-- Recent Activity -->
                        <Card class="border-none shadow-none bg-transparent">
                            <CardHeader class="px-0 pt-0">
                                <CardTitle class="text-lg font-semibold flex items-center gap-2">
                                    <Clock class="h-5 w-5 text-muted-foreground" />
                                    Recent Activity
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="px-0">
                                <div v-if="recentActivities.length === 0"
                                    class="text-center py-8 text-muted-foreground text-sm">
                                    No recent activity
                                </div>
                                <div v-else class="relative space-y-0">
                                    <div v-for="(activity, index) in recentActivities" :key="activity.id"
                                        class="relative pb-8 last:pb-0">
                                        <!-- Timeline Line -->
                                        <span v-if="index !== recentActivities.length - 1"
                                            class="absolute left-2.5 top-3 -ml-px h-full w-0.5 bg-muted"
                                            aria-hidden="true"></span>

                                        <div class="relative flex items-start space-x-3">
                                            <!-- Timeline Dot -->
                                            <div class="relative">
                                                <div class="flex h-5 w-5 items-center justify-center rounded-full ring-8 ring-background"
                                                    :class="{
                                                        'bg-green-100 dark:bg-green-900': activity.type === 'chapter_completed',
                                                        'bg-blue-100 dark:bg-blue-900': activity.type === 'project_created',
                                                        'bg-gray-100 dark:bg-gray-800': !['chapter_completed', 'project_created'].includes(activity.type)
                                                    }">
                                                    <div class="h-2 w-2 rounded-full" :class="{
                                                        'bg-green-600 dark:bg-green-400': activity.type === 'chapter_completed',
                                                        'bg-blue-600 dark:bg-blue-400': activity.type === 'project_created',
                                                        'bg-gray-600 dark:bg-gray-400': !['chapter_completed', 'project_created'].includes(activity.type)
                                                    }"></div>
                                                </div>
                                            </div>

                                            <div class="min-w-0 flex-1 pt-0.5">
                                                <div class="text-sm font-medium text-foreground">
                                                    {{ activity.description }}
                                                </div>
                                                <div class="mt-1 text-xs text-muted-foreground">
                                                    {{ activity.time }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    </AppLayout>
</template>
