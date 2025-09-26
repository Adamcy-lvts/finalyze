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
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, watchEffect } from 'vue';
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
const selectAll = ref(false);
const selectAllIndeterminate = ref(false);
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
      project.university.toLowerCase().includes(query) ||
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
                    updateSelectAllState();
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

// Bulk selection functions
const toggleProjectSelection = (projectId: number) => {
    const index = selectedProjects.value.indexOf(projectId);
    if (index > -1) {
        selectedProjects.value.splice(index, 1);
    } else {
        selectedProjects.value.push(projectId);
    }
    updateSelectAllState();
};

const toggleSelectAll = () => {
    if (selectAll.value || selectAllIndeterminate.value) {
        // If all selected or some selected, clear all
        selectedProjects.value = [];
    } else {
        // If none selected, select all filtered projects
        selectedProjects.value = filteredProjects.value.map(project => project.id);
    }
    updateSelectAllState();
};

const updateSelectAllState = () => {
    const selectedCount = selectedProjects.value.length;
    const totalCount = filteredProjects.value.length;

    if (selectedCount === 0) {
        selectAll.value = false;
        selectAllIndeterminate.value = false;
    } else if (selectedCount === totalCount) {
        selectAll.value = true;
        selectAllIndeterminate.value = false;
    } else {
        // Intermediate state: some but not all selected
        selectAll.value = false;
        selectAllIndeterminate.value = true;
    }
};

const clearSelection = () => {
    selectedProjects.value = [];
    selectAll.value = false;
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

const selectedProjectsCount = computed(() => selectedProjects.value.length);
const hasSelectedProjects = computed(() => selectedProjectsCount.value > 0);

// Watch for changes in filtered projects and update select all state
watchEffect(() => {
    // Remove selected projects that are no longer in filtered results
    const filteredIds = filteredProjects.value.map(p => p.id);
    selectedProjects.value = selectedProjects.value.filter(id => filteredIds.includes(id));
    updateSelectAllState();
});
</script>

<template>
    <AppLayout title="My Projects">
        <div class="min-h-screen bg-gradient-to-br from-background via-background to-muted/20">
            <div class="container mx-auto px-4 py-8 sm:px-6 lg:px-8">
                <!-- Enhanced Header with Stats -->
                <div class="mb-8 space-y-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 class="text-4xl font-bold tracking-tight">Academic Projects</h1>
                            <p class="text-lg text-muted-foreground">Manage and track your research journey</p>
                        </div>
                        <Button 
                            @click="() => router.visit(route('projects.create'))"
                            class="self-start bg-primary hover:bg-primary/90 shadow-lg hover:shadow-xl transition-all duration-300"
                            size="lg"
                        >
                            <Plus class="mr-2 h-5 w-5" />
                            New Project
                        </Button>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid gap-4 md:grid-cols-4" v-if="totalProjects > 0">
                        <Card class="border-0 shadow-sm bg-gradient-to-r from-blue-50 to-blue-100/50 dark:from-blue-950/20 dark:to-blue-900/10">
                            <CardContent class="flex items-center p-4">
                                <div class="rounded-full bg-blue-500/10 p-2 mr-3">
                                    <FileText class="h-5 w-5 text-blue-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-blue-600">Total Projects</p>
                                    <p class="text-2xl font-bold text-blue-700">{{ totalProjects }}</p>
                                </div>
                            </CardContent>
                        </Card>
                        
                        <Card class="border-0 shadow-sm bg-gradient-to-r from-green-50 to-green-100/50 dark:from-green-950/20 dark:to-green-900/10">
                            <CardContent class="flex items-center p-4">
                                <div class="rounded-full bg-green-500/10 p-2 mr-3">
                                    <Star class="h-5 w-5 text-green-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-600">Active</p>
                                    <p class="text-2xl font-bold text-green-700">{{ activeProjects }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-0 shadow-sm bg-gradient-to-r from-purple-50 to-purple-100/50 dark:from-purple-950/20 dark:to-purple-900/10">
                            <CardContent class="flex items-center p-4">
                                <div class="rounded-full bg-purple-500/10 p-2 mr-3">
                                    <CheckCircle class="h-5 w-5 text-purple-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-purple-600">Completed</p>
                                    <p class="text-2xl font-bold text-purple-700">{{ completedProjects }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-0 shadow-sm bg-gradient-to-r from-orange-50 to-orange-100/50 dark:from-orange-950/20 dark:to-orange-900/10">
                            <CardContent class="flex items-center p-4">
                                <div class="rounded-full bg-orange-500/10 p-2 mr-3">
                                    <TrendingUp class="h-5 w-5 text-orange-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-orange-600">Avg Progress</p>
                                    <p class="text-2xl font-bold text-orange-700">{{ averageProgress }}%</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Filters and Controls -->
                <div v-if="totalProjects > 0" class="mb-6 space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Search and Filters -->
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <!-- Select All Checkbox -->
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    :checked="selectAll"
                                    @update:checked="toggleSelectAll"
                                    aria-label="Select all projects"
                                />
                                <span class="text-sm text-muted-foreground">
                                    {{ selectAll ? 'Deselect All' : selectAllIndeterminate ? `Select All (${selectedProjectsCount} selected)` : 'Select All' }}
                                </span>
                            </div>

                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Search projects..."
                                    class="pl-10 w-full sm:w-80 shadow-sm"
                                />
                            </div>
                            
                            <Select v-model="statusFilter">
                                <SelectTrigger class="w-full sm:w-40 shadow-sm">
                                    <Filter class="mr-2 h-4 w-4" />
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
                                <SelectTrigger class="w-full sm:w-40 shadow-sm">
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="thesis">Thesis</SelectItem>
                                    <SelectItem value="dissertation">Dissertation</SelectItem>
                                    <SelectItem value="research">Research</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- View Controls -->
                        <div class="flex items-center gap-2">
                            <Select v-model="sortBy">
                                <SelectTrigger class="w-40 shadow-sm">
                                    <SelectValue placeholder="Sort by" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="created_at">Date Created</SelectItem>
                                    <SelectItem value="title">Title</SelectItem>
                                    <SelectItem value="progress">Progress</SelectItem>
                                    <SelectItem value="status">Status</SelectItem>
                                </SelectContent>
                            </Select>

                            <Tabs v-model="viewMode" class="w-fit">
                                <TabsList class="shadow-sm">
                                    <TabsTrigger value="grid" class="px-3">
                                        <Grid3x3 class="h-4 w-4" />
                                    </TabsTrigger>
                                    <TabsTrigger value="list" class="px-3">
                                        <List class="h-4 w-4" />
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>
                    </div>

                    <!-- Bulk Actions Toolbar -->
                    <div v-if="hasSelectedProjects" class="flex items-center justify-between p-4 bg-primary/5 border border-primary/20 rounded-lg shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-primary" />
                                <span class="text-sm font-medium text-primary">
                                    {{ selectedProjectsCount }} project{{ selectedProjectsCount === 1 ? '' : 's' }} selected
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="showBulkDeleteConfirmation = true"
                                :disabled="bulkDeleting"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete Selected
                            </Button>
                            <Button variant="outline" size="sm" @click="clearSelection">
                                <X class="mr-2 h-4 w-4" />
                                Clear Selection
                            </Button>
                        </div>
                    </div>

                    <!-- Results Counter -->
                    <div class="text-sm text-muted-foreground">
                        {{ filteredProjects.length }} of {{ totalProjects }} projects
                    </div>
                </div>

                <!-- Projects Display -->
                <div v-if="filteredProjects.length > 0">
                    <!-- Grid View -->
                    <div v-if="viewMode === 'grid'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <Card
                            v-for="project in filteredProjects"
                            :key="project.id"
                            :class="[
                                'group relative cursor-pointer transition-all duration-200 hover:shadow-lg',
                                {
                                    '': project.is_active,
                                    'ring-2 ring-primary/20 bg-primary/5': selectedProjects.includes(project.id)
                                },
                            ]"
                            @click.stop="() => router.visit(route('projects.show', project.slug))"
                        >
                            <CardHeader class="pb-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            :checked="selectedProjects.includes(project.id)"
                                            @update:checked="() => toggleProjectSelection(project.id)"
                                            @click.stop
                                            aria-label="Select project"
                                            class="opacity-60 group-hover:opacity-100 transition-opacity"
                                            :class="{ 'opacity-100': selectedProjects.includes(project.id) }"
                                        />
                                        <component :is="getProjectIcon(project.type)" class="h-4 w-4 text-muted-foreground" />
                                        <Badge
                                            v-if="project.is_active"
                                            class="h-4 px-1.5 text-[10px] bg-primary/10 text-primary "
                                        >
                                            Active
                                        </Badge>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
                                                @click.stop
                                            >
                                                <MoreVertical class="h-3 w-3" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-40">
                                            <DropdownMenuItem @click.stop="() => router.visit(route('projects.show', project.slug))">
                                                <Eye class="mr-2 h-3 w-3" />
                                                View
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="!project.is_active"
                                                @click.stop="setActiveProject(project.slug)"
                                            >
                                                <CheckCircle class="mr-2 h-3 w-3" />
                                                Set Active
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click.stop="deleteProject(project)"
                                                class="text-destructive"
                                            >
                                                <Trash2 class="mr-2 h-3 w-3" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                                
                                <CardTitle class="text-sm line-clamp-2 group-hover:text-primary transition-colors">
                                    {{ project.title || 'Untitled Project' }}
                                </CardTitle>
                            </CardHeader>

                            <CardContent class="pt-0">
                                <!-- Meta Info -->
                                <div class="flex items-center justify-between text-xs text-muted-foreground mb-3">
                                    <span class="capitalize">{{ project.type }}</span>
                                    <Badge :class="getStatusColor(project.status)" class="h-4 px-1.5 text-[10px] border-0">
                                        {{ project.status.replace('_', ' ') }}
                                    </Badge>
                                </div>

                                <!-- Progress -->
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs text-muted-foreground">Progress</span>
                                        <span class="text-xs font-medium">{{ project.progress }}%</span>
                                    </div>
                                    <Progress :model-value="project.progress" class="h-1" />
                                </div>

                                <!-- Footer -->
                                <div class="flex items-center justify-between text-xs text-muted-foreground pt-3 border-t">
                                    <span>Ch. {{ project.current_chapter }}/5</span>
                                    <span>{{ formatDate(project.created_at) }}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- List View -->
                    <div v-else class="space-y-2">
                        <Card
                            v-for="project in filteredProjects"
                            :key="project.id"
                            :class="[
                                'group cursor-pointer transition-all duration-200 hover:shadow-lg',
                                {
                                    'ring-2 ring-primary/20 border-primary': project.is_active,
                                    'ring-2 ring-primary/20 bg-primary/5': selectedProjects.includes(project.id)
                                }
                            ]"
                            @click.stop="() => router.visit(route('projects.show', project.slug))"
                        >
                            <CardContent class="py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <Checkbox
                                            :checked="selectedProjects.includes(project.id)"
                                            @update:checked="() => toggleProjectSelection(project.id)"
                                            @click.stop
                                            aria-label="Select project"
                                            class="opacity-60 group-hover:opacity-100 transition-opacity flex-shrink-0"
                                            :class="{ 'opacity-100': selectedProjects.includes(project.id) }"
                                        />
                                        <component :is="getProjectIcon(project.type)" class="h-4 w-4 text-muted-foreground flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <CardTitle class="text-sm truncate group-hover:text-primary transition-colors">
                                                    {{ project.title || 'Untitled Project' }}
                                                </CardTitle>
                                                <Badge
                                                    v-if="project.is_active"
                                                    class="h-4 px-1.5 text-[10px] bg-primary/10 text-primary "
                                                >
                                                    Active
                                                </Badge>
                                            </div>
                                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                                <span class="capitalize">{{ project.type }}</span>
                                                <span>•</span>
                                                <span>{{ formatDate(project.created_at) }}</span>
                                                <span>•</span>
                                                <span>Ch. {{ project.current_chapter }}/5</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <Badge :class="getStatusColor(project.status)" class="h-4 px-1.5 text-[10px] border-0">
                                            {{ project.status.replace('_', ' ') }}
                                        </Badge>
                                        
                                        <div class="flex items-center gap-2 min-w-[80px]">
                                            <Progress :model-value="project.progress" class="h-1 flex-1" />
                                            <span class="text-xs font-medium w-8 text-right">{{ project.progress }}%</span>
                                        </div>
                                        
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-6 w-6" @click.stop>
                                                    <MoreVertical class="h-3 w-3" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-40">
                                                <DropdownMenuItem @click.stop="() => router.visit(route('projects.show', project.slug))">
                                                    <Eye class="mr-2 h-3 w-3" />
                                                    View
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="!project.is_active"
                                                    @click.stop="setActiveProject(project.slug)"
                                                >
                                                    <CheckCircle class="mr-2 h-3 w-3" />
                                                    Set Active
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click.stop="deleteProject(project)"
                                                    class="text-destructive"
                                                >
                                                    <Trash2 class="mr-2 h-3 w-3" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Enhanced Empty State -->
                <Card v-else-if="totalProjects === 0" class="border-0 shadow-lg bg-gradient-to-b from-background to-muted/20">
                    <CardContent class="py-16 text-center">
                        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-primary/10 to-primary/5">
                            <GraduationCap class="h-10 w-10 text-primary" />
                        </div>
                        <h3 class="mb-2 text-2xl font-bold">Start Your Academic Journey</h3>
                        <p class="mb-6 text-lg text-muted-foreground max-w-md mx-auto">
                            Create your first project and begin organizing your research, thesis, or dissertation with powerful tools.
                        </p>
                        <div class="space-y-4">
                            <Button 
                                size="lg" 
                                @click="() => router.visit(route('projects.create'))"
                                class="bg-primary hover:bg-primary/90 shadow-lg hover:shadow-xl transition-all duration-300"
                            >
                                <Plus class="mr-2 h-5 w-5" />
                                Create Your First Project
                            </Button>
                            <div class="flex items-center justify-center gap-8 text-sm text-muted-foreground">
                                <div class="flex items-center gap-2">
                                    <Target class="h-4 w-4" />
                                    <span>Track Progress</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <BookOpen class="h-4 w-4" />
                                    <span>Organize Chapters</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Clock class="h-4 w-4" />
                                    <span>Meet Deadlines</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- No Results State -->
                <Card v-else class="border-0 shadow-sm">
                    <CardContent class="py-12 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                            <Search class="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 class="mb-2 text-xl font-semibold">No projects found</h3>
                        <p class="text-muted-foreground mb-4">
                            Try adjusting your search terms or filters
                        </p>
                        <Button 
                            variant="outline" 
                            @click="() => { searchQuery = ''; statusFilter = 'all'; typeFilter = 'all'; }"
                        >
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
                        Are you sure you want to delete {{ selectedProjectsCount }} project{{ selectedProjectsCount === 1 ? '' : 's' }}?
                        This action cannot be undone and will permanently remove the selected projects and all their data.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:space-x-2">
                    <Button
                        variant="outline"
                        @click="showBulkDeleteConfirmation = false"
                        :disabled="bulkDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="bulkDeleteProjects"
                        :disabled="bulkDeleting"
                    >
                        <Trash2 v-if="!bulkDeleting" class="mr-2 h-4 w-4" />
                        <div v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent"></div>
                        {{ bulkDeleting ? 'Deleting...' : 'Delete Projects' }}
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
                        This action cannot be undone and will permanently remove the project and all its data.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:space-x-2">
                    <Button
                        variant="outline"
                        @click="showIndividualDeleteConfirmation = false"
                        :disabled="individualDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="confirmIndividualDelete"
                        :disabled="individualDeleting"
                    >
                        <Trash2 v-if="!individualDeleting" class="mr-2 h-4 w-4" />
                        <div v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent"></div>
                        {{ individualDeleting ? 'Deleting...' : 'Delete Project' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
