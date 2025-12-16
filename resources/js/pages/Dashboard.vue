<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { BookOpen, Clock, FileText, PenTool, Plus, Target, Award, Sparkles, Info, HelpCircle } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

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
        status: string;
        setupStep: number;
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

const props = defineProps<Props>();

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

// Helper to check if project needs setup completion
const needsSetupCompletion = (project: Props['activeProject']) => {
    if (!project) return false;
    // Project is in setup phase and hasn't completed setup
    return project.status === 'setup' && project.setupStep < 4;
};

// Helper to check if project is in writing phase
const isInWritingPhase = (project: Props['activeProject']) => {
    if (!project) return false;
    return ['writing', 'completed'].includes(project.status);
};

// Helper to check if project needs topic selection/approval
const needsTopicWork = (project: Props['activeProject']) => {
    if (!project) return false;
    return ['topic_selection', 'topic_pending_approval', 'topic_approved', 'guidance'].includes(project.status);
};

const startTour = () => {
    const steps = [
        {
            element: '#dashboard-header',
            popover: {
                title: 'Welcome to your Dashboard',
                description: 'This is your central command center where you can manage all your writing projects and track your progress.'
            }
        },
        {
            element: '#app-sidebar',
            popover: {
                title: 'Navigation',
                description: 'Navigate between projects, access the topic library, buy credits, and manage your account settings.'
            }
        },
        {
            element: '#app-header',
            popover: {
                title: 'Top Bar',
                description: 'Toggle the sidebar, switch between light and dark themes, check your credit balance, and use the + button to top up.'
            }
        },
        {
            element: '#stats-overview',
            popover: {
                title: 'Stats Overview',
                description: 'Keep track of your total projects, words written, research papers processed, and time invested at a glance.'
            }
        }
    ];

    if (props.activeProject) {
        steps.push({
            element: '#quick-actions',
            popover: {
                title: 'Quick Actions',
                description: 'Jump straight back into your latest work or manage your project with a single click.'
            }
        });
        steps.push({
            element: '#active-project-section',
            popover: {
                title: 'Active Project',
                description: 'Quickly access your current project, check its status, and continue where you left off.'
            }
        });
    } else {
        steps.push({
            element: '#active-project-section',
            popover: {
                title: 'Start a Project',
                description: 'Ready to begin? Click here to create your first project and start writing.'
            }
        });
    }

    steps.push(
        {
            element: '#credit-balance-card',
            popover: {
                title: 'Credit Balance',
                description: 'Track your available credits for AI generation. "Purchased" shows credits you\'ve bought, while "Bonus" includes free or promotional credits. Credits are consumed when you use AI tools to write or edit content.'
            }
        },
        {
            element: '#recent-activity-card',
            popover: {
                title: 'Recent Activity',
                description: 'Stay updated with a timeline of your recent actions and milestones.'
            }
        },
        {
            element: '#help-button',
            popover: {
                title: 'Need Help?',
                description: 'You can restart this tour anytime by clicking here.'
            }
        }
    );

    const driverObj = driver({
        showProgress: true,
        animate: true,
        steps: steps
    });

    driverObj.drive();
    localStorage.setItem(`dashboard_tour_seen_${props.user.email}`, 'true');
};

onMounted(() => {
    // Small delay to ensure DOM is ready and animations are settled
    setTimeout(() => {
        if (!localStorage.getItem(`dashboard_tour_seen_${props.user.email}`)) {
            startTour();
        }
    }, 1000);
});
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="min-h-screen bg-background">
            <div class="container mx-auto px-4 py-8 sm:px-6 lg:px-8 max-w-7xl">
                <TooltipProvider>
                    <div class="space-y-8">
                        <!-- Header Section -->
                        <div id="dashboard-header"
                            class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div class="space-y-1">
                                <h1
                                    class="text-3xl md:text-3xl font-bold tracking-tight text-foreground bg-clip-text text-transparent bg-gradient-to-r from-foreground to-foreground/70">
                                    {{ greeting }}, {{ user.name }}!
                                </h1>
                                <p class="text-muted-foreground">Here's what's happening with your projects today.</p>
                            </div>
                            <div class="flex items-center gap-2 w-full md:w-auto">
                                <div id="quick-actions" v-if="activeProject" class="flex gap-2 w-full md:w-auto">
                                    <!-- Show Complete Setup only for projects in setup phase -->
                                    <Button v-if="needsSetupCompletion(activeProject)"
                                        @click="() => router.visit(route('projects.show', activeProject!.slug))"
                                        class="w-full md:w-auto shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-all">
                                        <span class="mr-2">🚀</span>
                                        Complete Setup
                                    </Button>
                                    <!-- Show Continue Writing for projects in writing phase -->
                                    <Button v-else-if="isInWritingPhase(activeProject)"
                                        @click="() => router.visit(route('projects.writing', activeProject!.slug))"
                                        class="w-full md:w-auto shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-all">
                                        <PenTool class="mr-2 h-4 w-4" />
                                        Continue Writing
                                    </Button>
                                    <!-- Show View Project for other statuses (topic selection, guidance, etc.) -->
                                    <Button v-else
                                        @click="() => router.visit(route('projects.show', activeProject!.slug))"
                                        class="w-full md:w-auto shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-all">
                                        <Target class="mr-2 h-4 w-4" />

                                        View Project
                                    </Button>
                                </div>
                                <Button id="help-button" variant="outline" size="icon" @click="startTour"
                                    title="Start Tour">
                                    <HelpCircle class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>



                        <!-- Stats Overview (Premium Cards) -->
                        <div id="stats-overview" class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                            <!-- Total Projects -->
                            <Card
                                class="shadow-sm border-border/50 bg-card/50 backdrop-blur-sm relative overflow-hidden group hover:border-blue-500/30 transition-all duration-300">
                                <div
                                    class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-blue-500/0 via-blue-500/50 to-blue-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <CardContent class="p-6 flex items-center gap-4">
                                    <div
                                        class="p-3 rounded-xl bg-blue-500/10 text-blue-500 ring-1 ring-blue-500/20 shadow-[0_0_15px_-3px_rgba(59,130,246,0.3)] group-hover:scale-110 transition-transform duration-300">
                                        <FileText class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">Total Projects</p>
                                        <h3 class="text-2xl font-bold tracking-tight">{{ stats.totalProjects }}</h3>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            <span class="text-green-500 font-medium">{{ stats.activeProjects }}
                                                active</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ stats.completedProjects }} done</span>
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Words Written -->
                            <Card
                                class="shadow-sm border-border/50 bg-card/50 backdrop-blur-sm relative overflow-hidden group hover:border-green-500/30 transition-all duration-300">
                                <div
                                    class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-green-500/0 via-green-500/50 to-green-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <CardContent class="p-6 flex items-center gap-4">
                                    <div
                                        class="p-3 rounded-xl bg-green-500/10 text-green-500 ring-1 ring-green-500/20 shadow-[0_0_15px_-3px_rgba(34,197,94,0.3)] group-hover:scale-110 transition-transform duration-300">
                                        <PenTool class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">Words Written</p>
                                        <h3 class="text-2xl font-bold tracking-tight">{{ formatNumber(stats.totalWords)
                                        }}</h3>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            ~{{ formatNumber(stats.avgWordsPerChapter) }}/chapter
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Research Stats -->
                            <Card
                                class="shadow-sm border-border/50 bg-card/50 backdrop-blur-sm relative overflow-hidden group hover:border-purple-500/30 transition-all duration-300">
                                <div
                                    class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-purple-500/0 via-purple-500/50 to-purple-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <CardContent class="p-6 flex items-center gap-4">
                                    <div
                                        class="p-3 rounded-xl bg-purple-500/10 text-purple-500 ring-1 ring-purple-500/20 shadow-[0_0_15px_-3px_rgba(168,85,247,0.3)] group-hover:scale-110 transition-transform duration-300">
                                        <BookOpen class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1">
                                            <p class="text-sm font-medium text-muted-foreground">Research</p>
                                            <Tooltip>
                                                <TooltipTrigger>
                                                    <Info
                                                        class="h-3 w-3 text-muted-foreground/50 hover:text-primary transition-colors" />
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Citations added across all projects</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                        <h3 class="text-2xl font-bold tracking-tight">{{ stats.citationsAdded }}</h3>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            From {{ stats.researchPapers }} papers
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Time Invested -->
                            <Card
                                class="shadow-sm border-border/50 bg-card/50 backdrop-blur-sm relative overflow-hidden group hover:border-orange-500/30 transition-all duration-300">
                                <div
                                    class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-orange-500/0 via-orange-500/50 to-orange-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <CardContent class="p-6 flex items-center gap-4">
                                    <div
                                        class="p-3 rounded-xl bg-orange-500/10 text-orange-500 ring-1 ring-orange-500/20 shadow-[0_0_15px_-3px_rgba(249,115,22,0.3)] group-hover:scale-110 transition-transform duration-300">
                                        <Clock class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">Time Invested</p>
                                        <h3 class="text-2xl font-bold tracking-tight">{{ stats.hoursSpent }}h</h3>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            Based on activity
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid gap-8 lg:grid-cols-3">
                            <!-- Main Content Area -->
                            <div id="active-project-section" class="lg:col-span-2 space-y-8">

                                <!-- Active Project Card Refined -->
                                <div v-if="activeProject"
                                    class="group relative overflow-hidden rounded-xl border border-border/50 bg-card/60 backdrop-blur-sm text-card-foreground shadow-sm transition-all duration-300 hover:shadow-lg hover:border-primary/20">
                                    <div
                                        class="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500" />

                                    <div class="p-4 md:p-6 relative z-10">
                                        <div class="flex flex-col md:flex-row items-start justify-between mb-6 gap-4">
                                            <div class="space-y-1.5">
                                                <Badge variant="outline"
                                                    class="mb-2 bg-background/50 backdrop-blur border-primary/20 text-primary uppercase text-xs font-semibold tracking-wider">
                                                    {{ activeProject.type }}
                                                </Badge>
                                                <SafeHtmlText as="h3"
                                                    class="text-xl md:text-2xl font-bold tracking-tight line-clamp-2 leading-tight group-hover:text-primary transition-colors duration-300"
                                                    :content="activeProject.title" />
                                                <p
                                                    class="text-muted-foreground flex items-center gap-2 text-sm md:text-base">
                                                    <span class="relative flex h-2 w-2">
                                                        <span
                                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                        <span
                                                            class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                                    </span>
                                                    Working on Chapter {{ activeProject.currentChapter }}
                                                </p>
                                            </div>
                                            <div
                                                class="text-left md:text-right bg-primary/5 p-3 rounded-lg border border-primary/10">
                                                <div class="text-3xl font-bold text-primary">{{ activeProject.progress
                                                }}%</div>
                                                <div
                                                    class="text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                                    Completion</div>
                                            </div>
                                        </div>

                                        <div class="space-y-6">
                                            <div class="space-y-2">
                                                <div class="flex justify-between text-sm">
                                                    <span class="font-medium text-muted-foreground">Overall
                                                        Progress</span>
                                                </div>
                                                <div
                                                    class="relative h-2 w-full overflow-hidden rounded-full bg-secondary/50">
                                                    <div class="h-full bg-gradient-to-r from-primary to-primary/80 transition-all duration-500 ease-out shadow-[0_0_10px_rgba(59,130,246,0.5)]"
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
                                                                    'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.4)]': isChapterComplete(chapter),
                                                                    'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.4)]': !isChapterComplete(chapter) && chapter.status === 'in_review',
                                                                    'bg-yellow-500': !isChapterComplete(chapter) && chapter.status === 'draft',
                                                                    'bg-muted group-hover/chapter:bg-muted-foreground/50': !isChapterComplete(chapter) && !['in_review', 'draft'].includes(chapter.status)
                                                                }"></div>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p class="font-medium">Chapter {{ chapter.number }}: {{
                                                                formatStatus(chapter.status) }}</p>
                                                            <p class="text-xs text-muted-foreground mt-1"
                                                                v-if="chapter.target_word_count > 0">
                                                                {{ formatNumber(chapter.word_count) }} / {{
                                                                    formatNumber(chapter.target_word_count) }} words
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <span
                                                        class="text-[10px] md:text-xs text-muted-foreground font-medium">Ch.
                                                        {{ chapter.number }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="bg-muted/30 p-4 flex flex-col sm:flex-row justify-end gap-3 border-t border-border/50">
                                        <!-- Show Complete Setup only for projects in setup phase -->
                                        <div v-if="needsSetupCompletion(activeProject)" class="w-full flex justify-end">
                                            <Button size="sm" class="w-full sm:w-auto shadow-md shadow-primary/20"
                                                @click="() => router.visit(route('projects.show', activeProject!.slug))">
                                                <span class="mr-2">🚀</span>
                                                Complete Setup
                                            </Button>
                                        </div>
                                        <!-- Show writing actions for projects in writing phase -->
                                        <template v-else-if="isInWritingPhase(activeProject)">
                                            <Button variant="ghost" size="sm" class="w-full sm:w-auto"
                                                @click="() => router.visit(route('projects.show', activeProject!.slug))">
                                                View Details
                                            </Button>
                                            <Button size="sm" class="w-full sm:w-auto shadow-md shadow-primary/20"
                                                @click="() => router.visit(route('projects.writing', activeProject!.slug))">
                                                <PenTool class="mr-2 h-4 w-4" />
                                                Open Writer
                                            </Button>
                                        </template>
                                        <!-- Show View Project for other statuses -->
                                        <template v-else>
                                            <Button size="sm" class="w-full sm:w-auto shadow-md shadow-primary/20"
                                                @click="() => router.visit(route('projects.show', activeProject!.slug))">
                                                <Target class="mr-2 h-4 w-4" />
                                                View Project
                                            </Button>
                                        </template>
                                    </div>
                                </div>

                                <!-- Empty State Refined -->
                                <div v-else
                                    class="rounded-xl border border-dashed border-border/50 p-8 md:p-12 text-center bg-card/30 backdrop-blur-sm">
                                    <div
                                        class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-primary/5 text-primary ring-1 ring-primary/10 shadow-lg shadow-primary/5">
                                        <Plus class="h-8 w-8 opacity-80" />
                                    </div>
                                    <h3 class="text-xl font-semibold mb-2">No Active Project</h3>
                                    <p class="text-muted-foreground mb-6 max-w-sm mx-auto">Start a new project to begin
                                        tracking your progress and using our AI tools.</p>
                                    <Button @click="() => router.visit(route('projects.create'))" size="lg"
                                        class="shadow-lg shadow-primary/20">
                                        <Plus class="mr-2 h-4 w-4" />
                                        Create New Project
                                    </Button>
                                </div>

                                <!-- Secondary Stats Grid Refined -->
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <div
                                                class="rounded-xl border border-border/50 bg-card/50 backdrop-blur-sm p-4 hover:bg-card hover:border-purple-500/30 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 cursor-help group">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="p-2 rounded-lg bg-purple-500/10 text-purple-500 group-hover:scale-110 transition-transform">
                                                        <Award class="h-5 w-5" />
                                                    </div>
                                                    <div>
                                                        <div class="text-lg font-bold">{{ stats.defenseQuestions }}
                                                        </div>
                                                        <div class="text-xs text-muted-foreground font-medium">Defense
                                                            Qs</div>
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
                                                class="rounded-xl border border-border/50 bg-card/50 backdrop-blur-sm p-4 hover:bg-card hover:border-yellow-500/30 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 cursor-help group">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="p-2 rounded-lg bg-yellow-500/10 text-yellow-500 group-hover:scale-110 transition-transform">
                                                        <Sparkles class="h-5 w-5" />
                                                    </div>
                                                    <div>
                                                        <div class="text-lg font-bold">{{ stats.aiAssistanceUsed }}
                                                        </div>
                                                        <div class="text-xs text-muted-foreground font-medium">AI
                                                            Assists</div>
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
                                                class="rounded-xl border border-border/50 bg-card/50 backdrop-blur-sm p-4 hover:bg-card hover:border-emerald-500/30 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 cursor-help group">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="p-2 rounded-lg bg-emerald-500/10 text-emerald-500 group-hover:scale-110 transition-transform">
                                                        <Target class="h-5 w-5" />
                                                    </div>
                                                    <div>
                                                        <div class="text-lg font-bold">Active</div>
                                                        <div class="text-xs text-muted-foreground font-medium">Status
                                                        </div>
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
                                <div id="credit-balance-card">
                                    <CreditBalanceCard v-if="wordBalance" :balance="wordBalance" />
                                </div>

                                <!-- Recent Activity Refined -->
                                <Card id="recent-activity-card"
                                    class="border border-border/50 bg-card/50 backdrop-blur-sm shadow-sm">
                                    <CardHeader class="pb-2">
                                        <CardTitle class="text-base font-semibold flex items-center gap-2">
                                            <div class="p-1.5 rounded-md bg-muted text-muted-foreground">
                                                <Clock class="h-4 w-4" />
                                            </div>
                                            Recent Activity
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div v-if="recentActivities.length === 0"
                                            class="text-center py-8 text-muted-foreground text-sm">
                                            No recent activity
                                        </div>
                                        <div v-else class="relative space-y-0 pl-1">
                                            <div v-for="(activity, index) in recentActivities" :key="activity.id"
                                                class="relative pb-6 last:pb-0">
                                                <!-- Timeline Line -->
                                                <span v-if="index !== recentActivities.length - 1"
                                                    class="absolute left-[0.6rem] top-3 -ml-px h-full w-0.5 bg-border/50"
                                                    aria-hidden="true"></span>

                                                <div class="relative flex items-start space-x-3 group">
                                                    <!-- Timeline Dot -->
                                                    <div class="relative mt-0.5">
                                                        <div
                                                            class="flex h-5 w-5 items-center justify-center rounded-full bg-background ring-1 ring-border group-hover:ring-primary/50 transition-all">
                                                            <div class="h-2 w-2 rounded-full transition-colors" :class="{
                                                                'bg-green-500 shadow-[0_0_5px_rgba(34,197,94,0.6)]': activity.type === 'chapter_completed',
                                                                'bg-blue-500 shadow-[0_0_5px_rgba(59,130,246,0.6)]': activity.type === 'project_created',
                                                                'bg-muted-foreground': !['chapter_completed', 'project_created'].includes(activity.type)
                                                            }"></div>
                                                        </div>
                                                    </div>

                                                    <div class="min-w-0 flex-1">
                                                        <div
                                                            class="text-sm font-medium text-foreground group-hover:text-primary transition-colors">
                                                            {{ activity.description }}
                                                        </div>
                                                        <div class="mt-0.5 text-xs text-muted-foreground">
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
            </div>
        </div>
    </AppLayout>
</template>
