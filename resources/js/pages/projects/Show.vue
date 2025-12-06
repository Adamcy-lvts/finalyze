<!-- /resources/js/pages/projects/Show.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Calendar, Edit, FileText, Lightbulb, Play, Settings, University } from 'lucide-vue-next';

interface Chapter {
    id: number;
    number: number;
    title: string;
    status: string;
    word_count: number;
    target_word_count: number;
    progress: number;
}

interface Project {
    id: number;
    title: string | null;
    slug: string;
    type: string;
    status: string;
    mode: string;
    current_chapter: number;
    chapters: Chapter[];
}

interface Props {
    project: Project;
}

const props = defineProps<Props>();

const getStatusColor = (status: string) => {
    const colors = {
        not_started: 'bg-gray-500',
        draft: 'bg-blue-500',
        in_review: 'bg-yellow-500',
        approved: 'bg-green-500',
    };
    return colors[status] || 'bg-gray-500';
};

const getChapterStatusText = (status: string) => {
    const statusMap = {
        not_started: 'Not Started',
        draft: 'Draft',
        in_review: 'In Review',
        approved: 'Completed',
    };
    return statusMap[status] || status;
};

const startWriting = (chapterNumber: number) => {
    router.visit(route('chapters.write', { project: props.project.id, chapter: chapterNumber }));
};

const continueCurrentChapter = () => {
    const currentChapter =
        props.project.chapters.find((c) => c.number === props.project.current_chapter) ||
        props.project.chapters.find((c) => c.status === 'not_started') ||
        props.project.chapters[0];

    if (currentChapter) {
        startWriting(currentChapter.number);
    }
};

const totalProgress = () => {
    const completedChapters = props.project.chapters.filter((c) => c.status === 'approved').length;
    return (completedChapters / props.project.chapters.length) * 100;
};

const totalWordsWritten = () => {
    return props.project.chapters.reduce((sum, chapter) => sum + chapter.word_count, 0);
};

const totalTargetWords = () => {
    return props.project.chapters.reduce((sum, chapter) => sum + chapter.target_word_count, 0);
};
</script>

<template>
    <AppLayout :title="project.title || 'Project Details'">
        <div class="space-y-8 p-6">
            <!-- Header -->
            <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                <Button variant="outline" size="sm" @click="router.visit(route('projects.index'))" class="self-start">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Projects
                </Button>
                <div class="flex-1 space-y-3">
                    <SafeHtmlText
                        as="h1"
                        class="text-4xl font-bold tracking-tight"
                        :content="project.title || 'Untitled Project'"
                    />
                    <div class="flex flex-wrap items-center gap-3">
                        <Badge variant="outline" class="px-2 py-1 text-xs capitalize">{{ project.type }}</Badge>
                        <Badge :variant="project.status === 'completed' ? 'default' : 'secondary'" class="px-2 py-1 text-xs">
                            {{ project.status.replace('_', ' ') }}
                        </Badge>
                        <span class="text-sm text-muted-foreground">{{ project.mode }} mode</span>
                    </div>
                </div>
                <div class="flex gap-3 self-start sm:self-center">
                    <Button variant="outline" @click="router.visit(route('projects.edit', project.slug))" size="sm">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Details
                    </Button>
                    <Button variant="outline" @click="() => {
                        const guidanceUrl = route('projects.guidance', project.slug);
                        console.log('🎯 GUIDANCE - Navigating to:', guidanceUrl, 'Project slug:', project.slug);
                        router.visit(guidanceUrl);
                    }" size="lg">
                        <Lightbulb class="mr-2 h-5 w-5" />
                        Guidance
                    </Button>
                    <Button @click="continueCurrentChapter" size="lg">
                        <Play class="mr-2 h-5 w-5" />
                        Continue Writing
                    </Button>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                    <CardContent class="p-6">
                        <div class="mb-2 flex items-center justify-between">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Overall Progress</CardTitle>
                            <FileText class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="mb-3 text-3xl font-semibold tracking-tight">{{ Math.round(totalProgress()) }}%</div>
                        <Progress :model-value="totalProgress()" class="h-2" />
                    </CardContent>
                </Card>

                <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                    <CardContent class="p-6">
                        <div class="mb-2 flex items-center justify-between">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Chapters</CardTitle>
                            <BookOpen class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="mb-1 text-3xl font-semibold tracking-tight">
                            {{ project.chapters.filter((c) => c.status === 'approved').length }}/{{ project.chapters.length }}
                        </div>
                        <p class="text-xs text-muted-foreground">Chapters completed</p>
                    </CardContent>
                </Card>

                <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                    <CardContent class="p-6">
                        <div class="mb-2 flex items-center justify-between">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Words Written</CardTitle>
                            <FileText class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="mb-1 text-3xl font-semibold tracking-tight">{{ totalWordsWritten().toLocaleString() }}</div>
                        <p class="text-xs text-muted-foreground">of {{ totalTargetWords().toLocaleString() }} target</p>
                    </CardContent>
                </Card>

                <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                    <CardContent class="p-6">
                        <div class="mb-2 flex items-center justify-between">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Current Chapter</CardTitle>
                            <BookOpen class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div class="mb-1 text-3xl font-semibold tracking-tight">{{ project.current_chapter }}</div>
                        <p class="text-xs text-muted-foreground">Currently working on</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chapter Overview -->
            <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                <CardHeader class="pb-6">
                    <CardTitle class="text-xl">Chapter Progress</CardTitle>
                    <CardDescription>Track the progress of each chapter in your project</CardDescription>
                </CardHeader>
                <CardContent class="pt-0">
                    <div class="space-y-3">
                        <div
                            v-for="chapter in project.chapters"
                            :key="chapter.id"
                            class="flex items-center gap-4 rounded-lg border-[0.5px] border-border/40 p-4 transition-colors hover:bg-muted/30"
                        >
                            <div class="flex-shrink-0">
                                <div
                                    :class="[
                                        'flex h-12 w-12 items-center justify-center rounded-full text-sm font-semibold',
                                        chapter.status === 'approved'
                                            ? 'border border-green-200 bg-green-100 text-green-700'
                                            : chapter.status === 'in_review'
                                              ? 'border border-yellow-200 bg-yellow-100 text-yellow-700'
                                              : chapter.status === 'draft'
                                                ? 'border border-blue-200 bg-blue-100 text-blue-700'
                                                : 'border border-gray-200 bg-gray-100 text-gray-500',
                                    ]"
                                >
                                    {{ chapter.number }}
                                </div>
                            </div>

                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="mb-1 text-base font-semibold">{{ chapter.title }}</h4>
                                        <div class="flex items-center gap-3 text-sm text-muted-foreground">
                                            <span
                                                >{{ chapter.word_count.toLocaleString() }} /
                                                {{ chapter.target_word_count.toLocaleString() }} words</span
                                            >
                                            <Badge
                                                variant="secondary"
                                                class="px-2 py-0.5 text-xs"
                                                :class="[
                                                    chapter.status === 'approved'
                                                        ? 'bg-green-100 text-green-700'
                                                        : chapter.status === 'in_review'
                                                          ? 'bg-yellow-100 text-yellow-700'
                                                          : chapter.status === 'draft'
                                                            ? 'bg-blue-100 text-blue-700'
                                                            : 'bg-gray-100 text-gray-500',
                                                ]"
                                            >
                                                {{ getChapterStatusText(chapter.status) }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="startWriting(chapter.number)"
                                        :disabled="chapter.status === 'approved'"
                                        class="flex-shrink-0"
                                    >
                                        {{ chapter.status === 'not_started' ? 'Start' : chapter.status === 'approved' ? 'View' : 'Continue' }}
                                    </Button>
                                </div>
                                <Progress :model-value="chapter.progress" class="h-2 bg-muted/40" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Project Tabs -->
            <Tabs default-value="overview" class="space-y-6">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="settings">Settings</TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Quick Actions -->
                        <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                            <CardHeader class="pb-4">
                                <CardTitle class="flex items-center gap-2 text-lg">
                                    <Play class="h-5 w-5" />
                                    Quick Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3 pt-0">
                                <Button variant="default" class="w-full" @click="continueCurrentChapter">
                                    <Play class="mr-2 h-4 w-4" />
                                    Continue Writing
                                </Button>
                                <Button variant="outline" class="w-full" disabled>
                                    <FileText class="mr-2 h-4 w-4" />
                                    Generate Outline
                                </Button>
                                <Button variant="outline" class="w-full" disabled>
                                    <BookOpen class="mr-2 h-4 w-4" />
                                    Export Draft
                                </Button>
                            </CardContent>
                        </Card>

                        <!-- Project Info -->
                        <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                            <CardHeader class="pb-4">
                                <CardTitle class="flex items-center gap-2 text-lg">
                                    <University class="h-5 w-5" />
                                    Project Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4 pt-0 text-sm">
                                <div class="flex items-center justify-between py-1">
                                    <span class="font-medium text-muted-foreground">Type:</span>
                                    <span class="font-semibold capitalize">{{ project.type }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <span class="font-medium text-muted-foreground">Mode:</span>
                                    <span class="font-semibold capitalize">{{ project.mode }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <span class="font-medium text-muted-foreground">Status:</span>
                                    <Badge variant="outline" class="text-xs">{{ project.status.replace('_', ' ') }}</Badge>
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <span class="font-medium text-muted-foreground">Current Chapter:</span>
                                    <span class="font-semibold">Chapter {{ project.current_chapter }}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="settings">
                    <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Settings class="h-5 w-5" />
                                Project Settings
                            </CardTitle>
                            <CardDescription>Manage your project preferences and settings</CardDescription>
                        </CardHeader>
                        <CardContent class="pt-6">
                            <p class="text-muted-foreground">Settings panel coming soon...</p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="history">
                    <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Calendar class="h-5 w-5" />
                                Project History
                            </CardTitle>
                            <CardDescription>View your project's progress timeline</CardDescription>
                        </CardHeader>
                        <CardContent class="pt-6">
                            <p class="text-muted-foreground">History timeline coming soon...</p>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
