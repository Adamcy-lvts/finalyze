<!-- /resources/js/pages/Dashboard.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { BookOpen, Clock, FileText, PenTool, Plus, TrendingUp, Calendar, Target, Users, Award, Zap, BarChart3 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    user: {
        name: string;
        email: string;
    };
    stats: {
        totalProjects: number;
        completedChapters: number;
        totalChapters: number;
        totalWords: number;
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
        }>;
    } | null;
    recentActivities: Array<{
        id: number;
        type: string;
        description: string;
        time: string;
    }>;
}

defineProps<Props>();

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning! Time to work on your project.';
    if (hour < 17) return "Good afternoon! Let's make progress on your project.";
    return "Good evening! Time to wrap up today's work.";
});

const formatNumber = (num: number) => {
    return new Intl.NumberFormat().format(num);
};

// Enhanced metrics with realistic mock data
const enhancedStats = ref({
    totalProjects: 5,
    activeProjects: 2,
    completedProjects: 3,
    totalWords: 45780,
    avgWordsPerChapter: 3240,
    citationsAdded: 187,
    hoursSpent: 142,
    weeklyGoalProgress: 78,
    researchPapers: 34,
    defenseQuestions: 89,
    aiAssistanceUsed: 245,
    collaborators: 3
});
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="p-6">
            <div class="space-y-8">
                <!-- Header Section -->
                <div class="space-y-2">
                    <h1 class="text-4xl font-bold tracking-tight">Welcome back, {{ user.name }}!</h1>
                    <p class="text-lg text-muted-foreground">{{ greeting }}</p>
                </div>

                <!-- Enhanced Comprehensive Stats Overview -->
                <div class="space-y-6">
                    <!-- Primary Metrics Row -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <Card class="relative rounded-xl border-[0.5px] border-border/50 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 p-6">
                            <CardContent class="grid gap-y-2 p-0">
                                <CardHeader class="flex items-center gap-x-2 p-0">
                                    <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    <CardTitle class="text-sm font-medium text-muted-foreground">Total Projects</CardTitle>
                                </CardHeader>
                                <CardTitle class="text-3xl font-bold tracking-tight text-blue-700 dark:text-blue-300">{{ enhancedStats.totalProjects }}</CardTitle>
                                <CardContent class="flex items-center gap-x-1 p-0 text-sm text-muted-foreground">
                                    <TrendingUp class="h-4 w-4 text-green-500" />
                                    {{ enhancedStats.activeProjects }} active
                                </CardContent>
                            </CardContent>
                        </Card>

                        <Card class="relative rounded-xl border-[0.5px] border-border/50 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-950/20 dark:to-emerald-950/20 p-6">
                            <CardContent class="grid gap-y-2 p-0">
                                <CardHeader class="flex items-center gap-x-2 p-0">
                                    <PenTool class="h-5 w-5 text-green-600 dark:text-green-400" />
                                    <CardTitle class="text-sm font-medium text-muted-foreground">Words Written</CardTitle>
                                </CardHeader>
                                <CardTitle class="text-3xl font-bold tracking-tight text-green-700 dark:text-green-300">{{ formatNumber(enhancedStats.totalWords) }}</CardTitle>
                                <CardContent class="flex items-center gap-x-1 p-0 text-sm text-muted-foreground">
                                    <BarChart3 class="h-4 w-4 text-blue-500" />
                                    {{ formatNumber(enhancedStats.avgWordsPerChapter) }} avg/chapter
                                </CardContent>
                            </CardContent>
                        </Card>

                        <Card class="relative rounded-xl border-[0.5px] border-border/50 bg-gradient-to-br from-purple-50 to-violet-50 dark:from-purple-950/20 dark:to-violet-950/20 p-6">
                            <CardContent class="grid gap-y-2 p-0">
                                <CardHeader class="flex items-center gap-x-2 p-0">
                                    <BookOpen class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                    <CardTitle class="text-sm font-medium text-muted-foreground">Citations Added</CardTitle>
                                </CardHeader>
                                <CardTitle class="text-3xl font-bold tracking-tight text-purple-700 dark:text-purple-300">{{ enhancedStats.citationsAdded }}</CardTitle>
                                <CardContent class="flex items-center gap-x-1 p-0 text-sm text-muted-foreground">
                                    <Award class="h-4 w-4 text-yellow-500" />
                                    {{ enhancedStats.researchPapers }} papers
                                </CardContent>
                            </CardContent>
                        </Card>

                        <Card class="relative rounded-xl border-[0.5px] border-border/50 bg-gradient-to-br from-orange-50 to-red-50 dark:from-orange-950/20 dark:to-red-950/20 p-6">
                            <CardContent class="grid gap-y-2 p-0">
                                <CardHeader class="flex items-center gap-x-2 p-0">
                                    <Clock class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                    <CardTitle class="text-sm font-medium text-muted-foreground">Time Invested</CardTitle>
                                </CardHeader>
                                <CardTitle class="text-3xl font-bold tracking-tight text-orange-700 dark:text-orange-300">{{ enhancedStats.hoursSpent }}h</CardTitle>
                                <CardContent class="flex items-center gap-x-1 p-0 text-sm text-muted-foreground">
                                    <Zap class="h-4 w-4 text-green-500" />
                                    {{ enhancedStats.weeklyGoalProgress }}% of weekly goal
                                </CardContent>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Secondary Metrics Row -->
                    <div class="grid gap-4 md:grid-cols-6">
                        <Card class="rounded-lg border bg-card p-4">
                            <div class="text-center">
                                <Target class="mx-auto mb-2 h-6 w-6 text-green-500" />
                                <div class="text-2xl font-bold text-green-600">{{ enhancedStats.completedProjects }}</div>
                                <div class="text-xs text-muted-foreground">Completed</div>
                            </div>
                        </Card>
                        
                        <Card class="rounded-lg border bg-card p-4">
                            <div class="text-center">
                                <Users class="mx-auto mb-2 h-6 w-6 text-blue-500" />
                                <div class="text-2xl font-bold text-blue-600">{{ enhancedStats.collaborators }}</div>
                                <div class="text-xs text-muted-foreground">Collaborators</div>
                            </div>
                        </Card>
                        
                        <Card class="rounded-lg border bg-card p-4">
                            <div class="text-center">
                                <Award class="mx-auto mb-2 h-6 w-6 text-purple-500" />
                                <div class="text-2xl font-bold text-purple-600">{{ enhancedStats.defenseQuestions }}</div>
                                <div class="text-xs text-muted-foreground">Defense Q&As</div>
                            </div>
                        </Card>
                        
                        <Card class="rounded-lg border bg-card p-4">
                            <div class="text-center">
                                <Zap class="mx-auto mb-2 h-6 w-6 text-yellow-500" />
                                <div class="text-2xl font-bold text-yellow-600">{{ enhancedStats.aiAssistanceUsed }}</div>
                                <div class="text-xs text-muted-foreground">AI Assists</div>
                            </div>
                        </Card>
                        
                        <Card class="rounded-lg border bg-card p-4">
                            <div class="text-center">
                                <Calendar class="mx-auto mb-2 h-6 w-6 text-indigo-500" />
                                <div class="text-2xl font-bold text-indigo-600">15</div>
                                <div class="text-xs text-muted-foreground">Days Streak</div>
                            </div>
                        </Card>
                        
                        <Card class="rounded-lg border bg-card p-4">
                            <div class="text-center">
                                <TrendingUp class="mx-auto mb-2 h-6 w-6 text-emerald-500" />
                                <div class="text-2xl font-bold text-emerald-600">94%</div>
                                <div class="text-xs text-muted-foreground">Quality Score</div>
                            </div>
                        </Card>
                    </div>

                </div>

                <!-- Active Project Section -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Active Project or Empty State -->
                    <div class="lg:col-span-2">
                        <Card class="overflow-hidden">
                            <CardContent class="p-0">
                                <div v-if="!activeProject" class="px-8 py-12 text-center">
                                    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                        <FileText class="h-8 w-8 text-muted-foreground" />
                                    </div>
                                    <h3 class="mb-3 text-xl font-semibold">No Active Project</h3>
                                    <p class="mx-auto mb-6 max-w-sm text-muted-foreground">
                                        Start your final year project journey today and track your progress with our comprehensive tools.
                                    </p>
                                    <Button size="lg" @click="() => router.visit(route('projects.create'))">
                                        <Plus class="mr-2 h-5 w-5" />
                                        Create Your First Project
                                    </Button>
                                </div>

                                <!-- Active Project Details -->
                                <div v-else class="p-8">
                                    <div class="space-y-6">
                                        <div class="flex items-start justify-between">
                                            <div class="space-y-2">
                                                <h3 class="text-2xl font-bold">{{ activeProject.title || 'Untitled Project' }}</h3>
                                                <p class="text-muted-foreground">Currently on Chapter {{ activeProject.currentChapter }}</p>
                                            </div>
                                            <Badge variant="secondary" class="px-3 py-1">{{ activeProject.type }}</Badge>
                                        </div>

                                        <!-- Progress Section -->
                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between">
                                                <span class="font-medium">Overall Progress</span>
                                                <span class="text-2xl font-bold text-primary">{{ activeProject.progress }}%</span>
                                            </div>
                                            <Progress :model-value="activeProject.progress" class="h-3" />
                                        </div>

                                        <!-- Chapter Status Grid -->
                                        <div class="space-y-3">
                                            <h4 class="font-medium">Chapter Progress</h4>
                                            <div class="grid grid-cols-6 gap-3 sm:grid-cols-8 md:grid-cols-10">
                                                <div
                                                    v-for="chapter in activeProject.chapters"
                                                    :key="chapter.number"
                                                    class="flex flex-col items-center space-y-2"
                                                >
                                                    <div
                                                        :class="[
                                                            'h-3 w-full rounded-full transition-all duration-200',
                                                            chapter.status === 'approved'
                                                                ? 'bg-green-500 shadow-sm'
                                                                : chapter.status === 'draft'
                                                                  ? 'bg-yellow-500 shadow-sm'
                                                                  : chapter.status === 'in_review'
                                                                    ? 'bg-blue-500 shadow-sm'
                                                                    : 'bg-muted',
                                                        ]"
                                                    />
                                                    <span class="text-xs font-medium text-muted-foreground">{{ chapter.number }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <Button size="lg" class="w-full" @click="() => router.visit(route('projects.show', activeProject!.slug))">
                                            <PenTool class="mr-2 h-5 w-5" />
                                            Continue Writing
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Recent Activity Sidebar -->
                    <div class="lg:col-span-1">
                        <Card class="h-fit">
                            <CardHeader class="pb-4">
                                <CardTitle class="text-lg">Recent Activity</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="recentActivities.length === 0" class="py-8 text-center">
                                    <Clock class="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                                    <p class="text-sm text-muted-foreground">No recent activity</p>
                                </div>
                                <div v-else class="space-y-4">
                                    <div
                                        v-for="activity in recentActivities"
                                        :key="activity.id"
                                        class="flex items-start gap-3 rounded-lg border bg-muted/30 p-3 transition-colors hover:bg-muted/50"
                                    >
                                        <div
                                            :class="[
                                                'mt-1 h-2 w-2 flex-shrink-0 rounded-full',
                                                activity.type === 'chapter_completed'
                                                    ? 'bg-green-500'
                                                    : activity.type === 'project_created'
                                                      ? 'bg-blue-500'
                                                      : 'bg-muted-foreground',
                                            ]"
                                        />
                                        <div class="flex-1 space-y-1">
                                            <p class="text-sm leading-tight font-medium">{{ activity.description }}</p>
                                            <p class="text-xs text-muted-foreground">{{ activity.time }}</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
