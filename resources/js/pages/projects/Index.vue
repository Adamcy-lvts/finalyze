<!-- /resources/js/pages/projects/Index.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, watchEffect, watch } from 'vue';
import {
    CheckCircle,
    Eye,
    MoreVertical,
    Plus,
    Trash2,
    Search,
    Filter,
    Grid3x3,
    List,
    Clock,
    BookOpen,
    GraduationCap,
    TrendingUp,
    Star,
    Target,
    FileText,
    X
} from 'lucide-vue-next';

interface Project {
    id: number;
    slug: string;
    title: string | null;
    type: string;
    status: string;
    topic_status: string;
    progress: number;
    created_at: string;
    is_active: boolean;
    current_chapter: number;
    university: string;
    full_university_name: string;
}

interface Props {
    projects: Project[];
}

const props = defineProps<Props>();

// Reactive state for UI controls
const searchQuery = ref('');
const statusFilter = ref('all');
const typeFilter = ref('all');
const sortBy = ref('created_at');
const sortOrder = ref<'asc' | 'desc'>('desc');
const viewMode = ref<'grid' | 'list'>('grid');

// Bulk selection state
const selectedProjects = ref<number[]>([]);
const showBulkDeleteConfirmation = ref(false);
const bulkDeleting = ref(false);

// Individual delete state
const showIndividualDeleteConfirmation = ref(false);
const projectToDelete = ref<Project | null>(null);
const individualDeleting = ref(false);

// Computed filtered and sorted projects
const filteredProjects = computed(() => {
    let filtered = [...props.projects];

    // Search filter
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(project =>
            (project.title || '').toLowerCase().includes(query) ||
            (project.full_university_name || project.university).toLowerCase().includes(query) ||
            project.type.toLowerCase().includes(query)
        );
    }

    // Status filter
    if (statusFilter.value !== 'all') {
        filtered = filtered.filter(project => project.status === statusFilter.value);
    }

    // Type filter
    if (typeFilter.value !== 'all') {
        filtered = filtered.filter(project => project.type === typeFilter.value);
    }

    // Sort
    filtered.sort((a, b) => {
        let aValue = a[sortBy.value as keyof Project];
        let bValue = b[sortBy.value as keyof Project];

        if (sortBy.value === 'created_at') {
            aValue = new Date(aValue as string).getTime();
            bValue = new Date(bValue as string).getTime();
        }

        if (typeof aValue === 'string') aValue = aValue.toLowerCase();
        if (typeof bValue === 'string') bValue = bValue.toLowerCase();

        if (aValue != null && bValue != null) {
            if (aValue < bValue) return sortOrder.value === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortOrder.value === 'asc' ? 1 : -1;
        }
        return 0;
    });

    return filtered;
});

// Stats computed properties
const totalProjects = computed(() => props.projects.length);
const activeProjects = computed(() => props.projects.filter(p => p.is_active).length);
const completedProjects = computed(() => props.projects.filter(p => p.status === 'completed').length);
const averageProgress = computed(() => {
    if (props.projects.length === 0) return 0;
    return Math.round(props.projects.reduce((sum, p) => sum + p.progress, 0) / props.projects.length);
});

// Bulk selection functions
const toggleProjectSelection = (projectId: number) => {
    const index = selectedProjects.value.indexOf(projectId);
    if (index > -1) {
        selectedProjects.value.splice(index, 1);
    } else {
        selectedProjects.value.push(projectId);
    }
};

// Select All Computed State
const selectAllState = computed({
    get() {
        if (filteredProjects.value.length === 0) return false;
        if (selectedProjects.value.length === 0) return false;
        if (selectedProjects.value.length === filteredProjects.value.length) return true;
        return 'indeterminate';
    },
    set(value: boolean | 'indeterminate') {
        if (value === true) {
            selectedProjects.value = filteredProjects.value.map(p => p.id);
        } else {
            selectedProjects.value = [];
        }
    }
});

const selectedProjectsCount = computed(() => selectedProjects.value.length);
const hasSelectedProjects = computed(() => selectedProjectsCount.value > 0);

// Watch for changes in filtered projects to clean up selection
watch(filteredProjects, (newFiltered) => {
    const filteredIds = newFiltered.map(p => p.id);
    const newSelected = selectedProjects.value.filter(id => filteredIds.includes(id));
    // Only update if the selection actually changed to avoid unnecessary updates
    if (newSelected.length !== selectedProjects.value.length) {
        selectedProjects.value = newSelected;
    }
});

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const getProjectIcon = (type: string) => {
    switch (type.toLowerCase()) {
        case 'thesis': return GraduationCap;
        case 'dissertation': return BookOpen;
        case 'research': return Target;
        default: return FileText;
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'draft': return 'bg-gray-500/10 text-gray-700';
        case 'setup': return 'bg-blue-500/10 text-blue-700';
        case 'planning': return 'bg-purple-500/10 text-purple-700';
        case 'writing': return 'bg-blue-500/10 text-blue-700';
        case 'review': return 'bg-yellow-500/10 text-yellow-700';
        case 'completed': return 'bg-green-500/10 text-green-700';
        case 'on_hold': return 'bg-orange-500/10 text-orange-700';
        case 'archived': return 'bg-gray-500/10 text-gray-700';
        // Legacy topic statuses (for backwards compatibility during transition)
        case 'topic_selection': return 'bg-blue-500/10 text-blue-700';
        case 'topic_pending_approval': return 'bg-yellow-500/10 text-yellow-700';
        case 'topic_approved': return 'bg-green-500/10 text-green-700';
        default: return 'bg-gray-500/10 text-gray-700';
    }
};

const setActiveProject = (projectSlug: string) => {
    router.post(route('projects.set-active', projectSlug));
};

const deleteProject = (project: Project) => {
    projectToDelete.value = project;
    showIndividualDeleteConfirmation.value = true;
};

const confirmIndividualDelete = async () => {
    if (!projectToDelete.value) return;

    const projectId = projectToDelete.value.id;
    const projectSlug = projectToDelete.value.slug;

    individualDeleting.value = true;

    try {
        router.delete(route('projects.destroy', projectSlug), {
            onSuccess: () => {
                showIndividualDeleteConfirmation.value = false;
                projectToDelete.value = null;
                // Clear selection if deleted project was selected
                if (selectedProjects.value.includes(projectId)) {
                    selectedProjects.value = selectedProjects.value.filter(id => id !== projectId);
                }
            },
            onError: () => {
                alert('Failed to delete project. Please try again.');
            },
            onFinish: () => {
                individualDeleting.value = false;
            }
        });
    } catch (error) {
        console.error('Error deleting project:', error);
        alert('An error occurred while deleting the project.');
        individualDeleting.value = false;
    }
};

const clearSelection = () => {
    selectedProjects.value = [];
};

const bulkDeleteProjects = async () => {
    if (selectedProjects.value.length === 0) return;

    bulkDeleting.value = true;

    try {
        const response = await fetch(route('projects.bulk-destroy'), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                project_ids: selectedProjects.value
            })
        });

        const data = await response.json();

        if (data.success) {
            // Refresh the page to show updated projects
            router.reload();
        } else {
            alert('Error deleting projects: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting projects:', error);
        alert('An error occurred while deleting projects.');
    } finally {
        bulkDeleting.value = false;
        showBulkDeleteConfirmation.value = false;
        clearSelection();
    }
};
</script>

<template>
    <AppLayout title="My Projects">
        <div class="min-h-screen bg-background">
            <div class="container mx-auto px-4 py-8 sm:px-6 lg:px-8 max-w-7xl">
                <!-- Header -->
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-foreground">Projects</h1>
                        <p class="text-muted-foreground mt-1">Manage and track your research progress.</p>
                    </div>
                    <Button @click="() => router.visit(route('projects.create'))" size="lg" class="shadow-sm">
                        <Plus class="mr-2 h-4 w-4" />
                        New Project
                    </Button>
                </div>

                <!-- Stats -->
                <div v-if="totalProjects > 0" class="grid gap-4 md:grid-cols-4 mb-8">
                    <Card class="shadow-sm">
                        <CardContent class="p-6 flex items-center gap-4">
                            <div
                                class="p-3 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400">
                                <FileText class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Total Projects</p>
                                <h3 class="text-2xl font-bold">{{ totalProjects }}</h3>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="shadow-sm">
                        <CardContent class="p-6 flex items-center gap-4">
                            <div
                                class="p-3 rounded-full bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400">
                                <Star class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Active</p>
                                <h3 class="text-2xl font-bold">{{ activeProjects }}</h3>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="shadow-sm">
                        <CardContent class="p-6 flex items-center gap-4">
                            <div
                                class="p-3 rounded-full bg-purple-50 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400">
                                <CheckCircle class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Completed</p>
                                <h3 class="text-2xl font-bold">{{ completedProjects }}</h3>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="shadow-sm">
                        <CardContent class="p-6 flex items-center gap-4">
                            <div
                                class="p-3 rounded-full bg-orange-50 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400">
                                <TrendingUp class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Avg Progress</p>
                                <h3 class="text-2xl font-bold">{{ averageProgress }}%</h3>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Filters & Toolbar -->
                <div v-if="totalProjects > 0" class="space-y-4 mb-6">
                    <div class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
                        <!-- Left: Search & Select All -->
                        <div class="flex items-center gap-4 w-full md:w-auto">
                            <div class="flex items-center gap-2 px-3 py-2 bg-muted/50 rounded-md border">
                                <Checkbox :checked="selectAllState"
                                    @update:checked="(val: boolean | 'indeterminate') => selectAllState = val"
                                    id="select-all" />
                                <label for="select-all" class="text-sm font-medium cursor-pointer select-none">
                                    Select All
                                </label>
                            </div>
                            <div class="relative flex-1 md:w-64">
                                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input v-model="searchQuery" placeholder="Search projects..."
                                    class="pl-9 bg-background" />
                            </div>
                        </div>

                        <!-- Right: Filters & View -->
                        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                            <Select v-model="statusFilter">
                                <SelectTrigger class="w-[140px]">
                                    <Filter class="mr-2 h-4 w-4 text-muted-foreground" />
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="draft">Draft</SelectItem>
                                    <SelectItem value="setup">Setup</SelectItem>
                                    <SelectItem value="planning">Planning</SelectItem>
                                    <SelectItem value="writing">Writing</SelectItem>
                                    <SelectItem value="review">Under Review</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="on_hold">On Hold</SelectItem>
                                    <SelectItem value="archived">Archived</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select v-model="typeFilter">
                                <SelectTrigger class="w-[140px]">
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="thesis">Thesis</SelectItem>
                                    <SelectItem value="dissertation">Dissertation</SelectItem>
                                    <SelectItem value="research">Research</SelectItem>
                                </SelectContent>
                            </Select>

                            <div class="h-8 w-px bg-border mx-1 hidden md:block"></div>

                            <Select v-model="sortBy">
                                <SelectTrigger class="w-[140px]">
                                    <SelectValue placeholder="Sort by" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="created_at">Date Created</SelectItem>
                                    <SelectItem value="title">Title</SelectItem>
                                    <SelectItem value="progress">Progress</SelectItem>
                                    <SelectItem value="status">Status</SelectItem>
                                </SelectContent>
                            </Select>

                            <div class="flex items-center border rounded-md bg-background p-1">
                                <Button variant="ghost" size="sm" class="h-7 px-2"
                                    :class="{ 'bg-muted shadow-sm': viewMode === 'grid' }" @click="viewMode = 'grid'">
                                    <Grid3x3 class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="sm" class="h-7 px-2"
                                    :class="{ 'bg-muted shadow-sm': viewMode === 'list' }" @click="viewMode = 'list'">
                                    <List class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Actions (Floating) -->
                    <div v-if="hasSelectedProjects"
                        class="flex items-center justify-between p-3 bg-primary/5 border border-primary/20 rounded-lg text-primary animate-in fade-in slide-in-from-top-2">
                        <span class="text-sm font-medium flex items-center gap-2">
                            <CheckCircle class="h-4 w-4" />
                            {{ selectedProjectsCount }} project{{ selectedProjectsCount === 1 ? '' : 's' }} selected
                        </span>
                        <div class="flex gap-2">
                            <Button variant="ghost" size="sm" @click="clearSelection"
                                class="hover:bg-primary/10 hover:text-primary">
                                Cancel
                            </Button>
                            <Button variant="destructive" size="sm" @click="showBulkDeleteConfirmation = true">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete Selected
                            </Button>
                        </div>
                    </div>

                    <!-- Results Count -->
                    <div class="text-sm text-muted-foreground px-1">
                        Showing {{ filteredProjects.length }} of {{ totalProjects }} projects
                    </div>
                </div>

                <!-- Projects Display -->
                <div v-if="filteredProjects.length > 0">
                    <!-- Grid View -->
                    <div v-if="viewMode === 'grid'" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <Card v-for="project in filteredProjects" :key="project.id" :class="[
                            'group relative cursor-pointer transition-all duration-300 hover:shadow-md border-muted/60',
                            {
                                'ring-2 ring-primary ring-offset-2': selectedProjects.includes(project.id)
                            },
                        ]" @click.stop="() => router.visit(route('projects.show', project.slug))">
                            <!-- Selection Overlay (Mobile/Hover) -->
                            <div class="absolute top-3 left-3 z-10">
                                <Checkbox :checked="selectedProjects.includes(project.id)"
                                    @update:checked="() => toggleProjectSelection(project.id)" @click.stop
                                    class="bg-background/80 backdrop-blur-sm transition-opacity"
                                    :class="{ 'opacity-0 group-hover:opacity-100': !selectedProjects.includes(project.id) }" />
                            </div>

                            <CardHeader class="pb-3 pt-12">
                                <div class="absolute top-3 right-3">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon"
                                                class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
                                                @click.stop>
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-48">
                                            <DropdownMenuItem
                                                @click.stop="() => router.visit(route('projects.show', project.slug))">
                                                <Eye class="mr-2 h-4 w-4" />
                                                Open Project
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="!project.is_active"
                                                @click.stop="setActiveProject(project.slug)">
                                                <CheckCircle class="mr-2 h-4 w-4" />
                                                Set as Active
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click.stop="deleteProject(project)"
                                                class="text-destructive focus:text-destructive">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div class="mb-2">
                                    <component :is="getProjectIcon(project.type)"
                                        class="h-8 w-8 text-primary/80 mb-3" />
                                    <CardTitle
                                        class="text-lg font-semibold line-clamp-2 group-hover:text-primary transition-colors leading-tight">
                                        <SafeHtmlText
                                            as="span"
                                            class="block"
                                            :content="project.title || 'Untitled Project'"
                                        />
                                    </CardTitle>
                                </div>
                            </CardHeader>

                            <CardContent>
                                <div class="flex items-center gap-2 mb-4">
                                    <Badge variant="secondary" class="capitalize font-normal">
                                        {{ project.type }}
                                    </Badge>
                                    <Badge :class="getStatusColor(project.status)"
                                        class="capitalize border-0 font-normal">
                                        {{ project.status.replace('_', ' ') }}
                                    </Badge>
                                </div>

                                <div class="space-y-2">
                                    <div class="flex justify-between text-xs text-muted-foreground">
                                        <span>Progress</span>
                                        <span class="font-medium text-foreground">{{ project.progress }}%</span>
                                    </div>
                                    <Progress :model-value="project.progress" class="h-1.5" />
                                </div>

                                <div
                                    class="mt-4 pt-4 border-t flex items-center justify-between text-xs text-muted-foreground">
                                    <div class="flex items-center gap-1">
                                        <BookOpen class="h-3 w-3" />
                                        <span>{{ project.current_chapter }}/5 Chapters</span>
                                    </div>
                                    <span>{{ formatDate(project.created_at) }}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- List View -->
                    <div v-else class="space-y-3">
                        <div v-for="project in filteredProjects" :key="project.id" :class="[
                            'group flex items-center gap-4 p-4 bg-card rounded-lg border transition-all duration-200 hover:shadow-md cursor-pointer',
                            {
                                'ring-2 ring-primary ring-offset-1': selectedProjects.includes(project.id)
                            }
                        ]" @click.stop="() => router.visit(route('projects.show', project.slug))">
                            <div class="flex items-center h-full" @click.stop>
                                <Checkbox :checked="selectedProjects.includes(project.id)"
                                    @update:checked="() => toggleProjectSelection(project.id)" />
                            </div>

                            <div class="p-2 rounded-md bg-muted/50 text-muted-foreground">
                                <component :is="getProjectIcon(project.type)" class="h-5 w-5" />
                            </div>

                            <div class="flex-1 min-w-0 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                                <div class="md:col-span-4">
                                    <SafeHtmlText
                                        as="h3"
                                        class="font-semibold truncate group-hover:text-primary transition-colors"
                                        :content="project.title || 'Untitled Project'"
                                    />
                                    <p class="text-xs text-muted-foreground flex items-center gap-2 mt-1">
                                        <span class="capitalize">{{ project.type }}</span>
                                        <span>•</span>
                                        <span>Created {{ formatDate(project.created_at) }}</span>
                                    </p>
                                </div>

                                <div class="md:col-span-3 flex items-center gap-2">
                                    <Badge :class="getStatusColor(project.status)"
                                        class="capitalize border-0 font-normal">
                                        {{ project.status.replace('_', ' ') }}
                                    </Badge>
                                    <Badge v-if="project.is_active" variant="outline"
                                        class="border-primary/30 text-primary bg-primary/5">
                                        Active
                                    </Badge>
                                </div>

                                <div class="md:col-span-4">
                                    <div class="flex items-center gap-3">
                                        <Progress :model-value="project.progress" class="h-2 flex-1" />
                                        <span class="text-xs font-medium w-10">{{ project.progress }}%</span>
                                    </div>
                                </div>

                                <div class="md:col-span-1 flex justify-end">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop>
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                @click.stop="() => router.visit(route('projects.show', project.slug))">
                                                <Eye class="mr-2 h-4 w-4" />
                                                Open
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="!project.is_active"
                                                @click.stop="setActiveProject(project.slug)">
                                                <CheckCircle class="mr-2 h-4 w-4" />
                                                Set Active
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click.stop="deleteProject(project)"
                                                class="text-destructive">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <Card v-else-if="totalProjects === 0" class="border-dashed shadow-none bg-muted/30">
                    <CardContent class="py-16 text-center">
                        <div
                            class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-background shadow-sm">
                            <GraduationCap class="h-10 w-10 text-muted-foreground/50" />
                        </div>
                        <h3 class="mb-2 text-xl font-semibold">No projects yet</h3>
                        <p class="mb-6 text-muted-foreground max-w-sm mx-auto">
                            Start your academic journey by creating your first project.
                        </p>
                        <Button size="lg" @click="() => router.visit(route('projects.create'))">
                            <Plus class="mr-2 h-5 w-5" />
                            Create Project
                        </Button>
                    </CardContent>
                </Card>

                <!-- No Search Results -->
                <Card v-else class="border-dashed shadow-none bg-muted/30">
                    <CardContent class="py-12 text-center">
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-background">
                            <Search class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <h3 class="mb-1 text-lg font-semibold">No matches found</h3>
                        <p class="text-muted-foreground mb-4 text-sm">
                            Adjust your filters or search query to find what you're looking for.
                        </p>
                        <Button variant="outline"
                            @click="() => { searchQuery = ''; statusFilter = 'all'; typeFilter = 'all'; }">
                            Clear Filters
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Bulk Delete Confirmation Modal -->
        <Dialog :open="showBulkDeleteConfirmation" @update:open="showBulkDeleteConfirmation = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Selected Projects</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete {{ selectedProjectsCount }} project{{ selectedProjectsCount ===
                        1 ? '' : 's' }}?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="showBulkDeleteConfirmation = false" :disabled="bulkDeleting">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="bulkDeleteProjects" :disabled="bulkDeleting">
                        <Trash2 v-if="!bulkDeleting" class="mr-2 h-4 w-4" />
                        <div v-else
                            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent">
                        </div>
                        {{ bulkDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Individual Delete Confirmation Modal -->
        <Dialog :open="showIndividualDeleteConfirmation" @update:open="showIndividualDeleteConfirmation = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Project</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ projectToDelete?.title || 'Untitled Project' }}"?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="showIndividualDeleteConfirmation = false"
                        :disabled="individualDeleting">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="confirmIndividualDelete" :disabled="individualDeleting">
                        <Trash2 v-if="!individualDeleting" class="mr-2 h-4 w-4" />
                        <div v-else
                            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent">
                        </div>
                        {{ individualDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
