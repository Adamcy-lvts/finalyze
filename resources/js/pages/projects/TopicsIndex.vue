<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    BookOpen,
    RefreshCw,
    ArrowRight,
    Sparkles,
    ShieldCheck,
    Clock,
    Library,
    ArrowUpRight,
    Zap,
    MessageSquare,
    Search,
    Filter,
    ChevronLeft,
    ChevronRight,
    LayoutGrid,
    List,
    FolderOpen
} from 'lucide-vue-next';

interface Topic {
    id: number;
    title: string;
    description: string;
    difficulty?: string;
    timeline?: string;
    resource_level?: string;
    feasibility_score?: number;
    keywords?: string[];
    research_type?: string;
    field_of_study?: string;
    faculty?: string;
    course?: string;
    academic_level?: string;
}

interface ProjectSummary {
    id: number;
    slug: string;
    title: string | null;
    topic: string | null;
    topic_status?: string | null;
    status: string;
    type: string;
    course: string | null;
    field_of_study: string | null;
    created_at?: string | null;
}

interface ProjectTopics {
    project: ProjectSummary;
    topics: Topic[];
}

interface Faculty {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    projectTopics: ProjectTopics[];
    allTopics?: Topic[];
    faculties?: Faculty[];
    meta?: {
        totalProjects: number;
        totalTopics: number;
    };
}

const props = defineProps<Props>();

// Use allTopics if provided, otherwise flatten from projectTopics
const allTopicsList = reactive<Topic[]>([...(props.allTopics ?? props.projectTopics.flatMap(set => set.topics))]);
const topicSets = reactive<ProjectTopics[]>([...props.projectTopics]);
const loadingProjectId = ref<number | null>(null);

const totalTopics = computed(() => props.meta?.totalTopics ?? allTopicsList.length);
const totalProjects = computed(() => props.meta?.totalProjects ?? topicSets.length);

const filters = reactive({
    search: '',
    difficulty: '',
    timeline: '',
    researchType: '',
});

// Utility: Strip HTML tags for cleaner text matching
const stripHtml = (html: string): string => {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

// Utility: Normalize text (lowercase, remove diacritics, collapse whitespace)
const normalizeText = (text: string): string => {
    if (!text) return '';
    return stripHtml(text)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove diacritics
        .replace(/[^\w\s]/g, ' ')        // Remove special characters
        .replace(/\s+/g, ' ')
        .trim();
};

const levenshtein = (a: string, b: string) => {
    const al = a.length;
    const bl = b.length;
    if (al === 0) return bl;
    if (bl === 0) return al;
    const matrix = Array.from({ length: al + 1 }, (_, i) => [i]);
    for (let j = 0; j <= bl; j++) matrix[0][j] = j;
    for (let i = 1; i <= al; i++) {
        for (let j = 1; j <= bl; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            matrix[i][j] = Math.min(
                matrix[i - 1][j] + 1,
                matrix[i][j - 1] + 1,
                matrix[i - 1][j - 1] + cost
            );
        }
    }
    return matrix[al][bl];
};

const fuzzyMatch = (query: string, haystack: string) => {
    const q = normalizeText(query);
    const h = normalizeText(haystack);
    if (!q) return true;
    if (h.includes(q)) return true;

    const tokens = q.split(/\s+/).filter(Boolean);
    const words = h.split(/\s+/);

    // All tokens must match at least one word (via inclusion, prefix, or fuzzy)
    return tokens.every(token => {
        // Direct inclusion check
        if (h.includes(token)) return true;

        return words.some(word => {
            // Exact match or word contains token
            if (word === token || word.includes(token)) return true;
            // Prefix match (e.g., "block" matches "blockchain")
            if (word.startsWith(token) || token.startsWith(word)) return true;
            // Fuzzy match with dynamic threshold based on word length
            const threshold = Math.min(2, Math.floor(token.length / 3));
            return levenshtein(token, word) <= threshold;
        });
    });
};

const filterOptions = computed(() => {
    const difficulties = new Set<string>();
    const timelines = new Set<string>();
    const researchTypes = new Set<string>();
    const projectTypes = new Set<string>();

    topicSets.forEach(set => {
        if (set.project.type) projectTypes.add(set.project.type);
        set.topics.forEach(topic => {
            if (topic.difficulty) difficulties.add(topic.difficulty);
            if (topic.timeline) timelines.add(topic.timeline);
            if (topic.research_type) researchTypes.add(topic.research_type);
        });
    });

    return {
        difficulties: Array.from(difficulties),
        timelines: Array.from(timelines),
        researchTypes: Array.from(researchTypes),
        projectTypes: Array.from(projectTypes),
    };
});

// Match filters for a single topic (no project dependency)
const matchTopicFilters = (topic: Topic) => {
    const search = filters.search.trim();

    // Build searchable text from all relevant fields
    const haystacks = [
        topic.title,
        topic.description,
        ...(topic.keywords || []),
        topic.research_type || '',
        topic.difficulty || '',
    ].map(val => val || '');

    const matchesSearch = !search || haystacks.some(val => fuzzyMatch(search, val));

    const matchesDifficulty = !filters.difficulty || normalizeText(topic.difficulty || '') === normalizeText(filters.difficulty);
    const matchesTimeline = !filters.timeline || normalizeText(topic.timeline || '') === normalizeText(filters.timeline);
    const matchesResearchType = !filters.researchType || normalizeText(topic.research_type || '') === normalizeText(filters.researchType);

    return matchesSearch && matchesDifficulty && matchesTimeline && matchesResearchType;
};

const matchTopicToCategory = (topic: Topic, categoryName: string): boolean => {
    // If topic has no faculty set, it shouldn't appear in specific faculty tabs
    if (!topic.faculty) return false;

    const clean = (str: string) => {
        return normalizeText(str)
            .replace(/\b(faculty|school|college|department|institute|centre|center)\s+(of\s+)?/g, '')
            .trim();
    };

    const target = clean(categoryName);
    const faculty = clean(topic.faculty);

    // Strict match on the cleaned faculty name
    return faculty === target;
};

// Filtered flat list of all topics
const filteredAllTopics = computed(() => {
    return allTopicsList.filter(topic => matchTopicFilters(topic));
});

// Pagination state
const currentPage = ref(1);
const itemsPerPage = ref(12);
const viewMode = ref<'all' | 'categorized'>('all');
const selectedCategory = ref<string>('');

// Use provided faculties for categories
const categories = computed(() => {
    return props.faculties || [];
});

// Filtered topics based on selected category (Faculty)
const categoryFilteredTopics = computed(() => {
    if (!selectedCategory.value) return filteredAllTopics.value;

    // Check if we selected "Other" or "General" which might not be in faculties list
    // But since selectedCategory comes from categories list (faculties), it will be a Faculty Name.
    // We filter topics whose field_of_study matches the faculty name.
    // If field_of_study is empty, we might want to map it to 'General' or something.

    return filteredAllTopics.value.filter(topic => {
        return matchTopicToCategory(topic, selectedCategory.value);
    });
});

// Paginated topics
const paginatedTopics = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return categoryFilteredTopics.value.slice(start, end);
});

// Total pages
const totalPages = computed(() => {
    return Math.ceil(categoryFilteredTopics.value.length / itemsPerPage.value);
});

// Topics grouped by category
const topicsByCategory = computed(() => {
    const grouped: Record<string, Topic[]> = {};
    filteredAllTopics.value.forEach(topic => {
        const category = topic.field_of_study || 'General';
        if (!grouped[category]) grouped[category] = [];
        grouped[category].push(topic);
    });
    return Object.entries(grouped).sort(([a], [b]) => a.localeCompare(b));
});

// Reset page when filters change
watch([() => filters.search, () => filters.difficulty, () => filters.timeline, () => filters.researchType, selectedCategory], () => {
    currentPage.value = 1;
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        // Scroll to top of topics grid
        window.scrollTo({ top: 400, behavior: 'smooth' });
    }
};

// Keep legacy for project-grouped display if needed
const filteredTopicSets = computed(() => {
    return topicSets.map(set => ({
        ...set,
        topics: set.topics.filter(topic => matchTopicFilters(topic)),
    }));
});

const clearFilters = () => {
    filters.search = '';
    filters.difficulty = '';
    filters.timeline = '';
    filters.researchType = '';
    selectedCategory.value = '';
    currentPage.value = 1;
};

const refreshTopics = async (project: ProjectSummary) => {
    loadingProjectId.value = project.id;
    try {
        const response = await fetch(route('topics.generate', project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ regenerate: true }),
        });

        const data = await response.json();
        if (!response.ok || !data.topics) {
            throw new Error(data.message || 'Failed to generate topics');
        }

        const index = topicSets.findIndex((set) => set.project.id === project.id);
        if (index !== -1) {
            topicSets[index] = {
                ...topicSets[index],
                topics: data.topics,
            };
        }

        toast.success(`Generated ${data.topics.length} topics for ${project.title || 'project'}`);
    } catch (error: any) {
        console.error('Failed to refresh topics', error);
        toast.error(error?.message || 'Failed to generate topics. Please try again.');
    } finally {
        loadingProjectId.value = null;
    }
};

const goToTopicSelection = (slug: string) => {
    router.visit(route('projects.topic-selection', slug));
};

const goToProject = (slug: string) => {
    router.visit(route('projects.show', slug));
};

const difficultyBadge = (difficulty?: string) => {
    switch ((difficulty || '').toLowerCase()) {
        case 'easy': return 'bg-green-500/10 text-green-600 dark:text-green-400 border-green-200 dark:border-green-800';
        case 'medium': return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800';
        case 'hard': return 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800';
        default: return 'bg-muted text-muted-foreground border-border';
    }
};
</script>

<template>
    <AppLayout title="Topic Library">
        <div class="min-h-screen bg-gradient-to-b from-background via-background/95 to-muted/20">
            <div class="mx-auto max-w-7xl space-y-10 p-6 pb-20 lg:p-10">

                <!-- Back Navigation -->
                <div class="flex items-center justify-between">
                    <Button @click="router.visit(route('projects.index'))" variant="ghost" size="sm"
                        class="group text-muted-foreground hover:text-foreground transition-colors">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-muted/50 group-hover:bg-primary/10 transition-colors mr-2">
                            <ArrowRight class="h-4 w-4 rotate-180 transition-transform group-hover:-translate-x-0.5" />
                        </div>
                        Back to Projects
                    </Button>
                </div>

                <!-- Header -->
                <div class="space-y-4 text-center animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div
                        class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 shadow-lg shadow-primary/10 ring-1 ring-white/20">
                        <Library class="h-10 w-10 text-primary" />
                    </div>
                    <div class="space-y-2">
                        <h1
                            class="text-4xl font-bold tracking-tight sm:text-5xl bg-gradient-to-br from-foreground to-foreground/70 bg-clip-text text-transparent">
                            Topic Library
                        </h1>
                        <p class="mx-auto max-w-2xl text-lg text-muted-foreground leading-relaxed">
                            Review every topic generated for your projects and spin up fresh ideas.
                        </p>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 animate-in fade-in slide-in-from-bottom-6 duration-700 delay-100">
                    <!-- Projects Stat -->
                    <Card
                        class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1">
                        <div
                            class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-blue-500/10 group-hover:bg-blue-500/20 transition-colors blur-2xl">
                        </div>
                        <div class="p-6 relative flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500 group-hover:scale-110 group-hover:bg-blue-500/20 transition-all duration-300">
                                <BookOpen class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Active Projects</p>
                                <p class="text-3xl font-bold text-foreground">{{ totalProjects }}</p>
                            </div>
                        </div>
                    </Card>

                    <!-- Generated Topics Stat -->
                    <Card
                        class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1">
                        <div
                            class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-emerald-500/10 group-hover:bg-emerald-500/20 transition-colors blur-2xl">
                        </div>
                        <div class="p-6 relative flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                                <Sparkles class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Total Generated</p>
                                <p class="text-3xl font-bold text-foreground">{{ totalTopics }}</p>
                            </div>
                        </div>
                    </Card>

                    <!-- Status Stat -->
                    <Card
                        class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1">
                        <div
                            class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-purple-500/10 group-hover:bg-purple-500/20 transition-colors blur-2xl">
                        </div>
                        <div class="p-6 relative flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500 group-hover:scale-110 group-hover:bg-purple-500/20 transition-all duration-300">
                                <ShieldCheck class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">System Status</p>
                                <p class="text-lg font-bold text-foreground">Operational</p>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Filters -->
                <Card class="border-border/50 bg-card/60 backdrop-blur-sm">
                    <CardContent class="p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div class="w-full lg:max-w-sm">
                                <label class="text-xs text-muted-foreground flex items-center gap-2 mb-1">
                                    <Search class="h-3.5 w-3.5" />
                                    Search topics or projects
                                </label>
                                <Input v-model="filters.search"
                                    placeholder="Search by title, keywords, course, field..."
                                    class="bg-background/80" />
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 flex-1">
                                <div>
                                    <label class="text-xs text-muted-foreground flex items-center gap-1 mb-1">
                                        <Filter class="h-3.5 w-3.5" /> Difficulty
                                    </label>
                                    <select v-model="filters.difficulty"
                                        class="w-full rounded-md border border-border/50 bg-background/80 px-3 py-2 text-sm">
                                        <option value="">All</option>
                                        <option v-for="opt in filterOptions.difficulties" :key="opt" :value="opt">{{ opt
                                            }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground flex items-center gap-1 mb-1">
                                        <Clock class="h-3.5 w-3.5" /> Timeline
                                    </label>
                                    <select v-model="filters.timeline"
                                        class="w-full rounded-md border border-border/50 bg-background/80 px-3 py-2 text-sm">
                                        <option value="">All</option>
                                        <option v-for="opt in filterOptions.timelines" :key="opt" :value="opt">{{ opt }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground flex items-center gap-1 mb-1">
                                        <Sparkles class="h-3.5 w-3.5" /> Research Type
                                    </label>
                                    <select v-model="filters.researchType"
                                        class="w-full rounded-md border border-border/50 bg-background/80 px-3 py-2 text-sm">
                                        <option value="">All</option>
                                        <option v-for="opt in filterOptions.researchTypes" :key="opt" :value="opt">{{
                                            opt }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="ghost" size="sm" @click="clearFilters" class="text-muted-foreground">
                                    Clear filters
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Category Tabs -->
                <div class="mb-6">
                    <ScrollArea class="w-full whitespace-nowrap pb-2">
                        <div class="flex w-max space-x-2 p-1">
                            <button @click="selectedCategory = ''"
                                :class="['flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all duration-200 border',
                                    !selectedCategory
                                        ? 'bg-primary text-primary-foreground border-primary shadow-sm hover:bg-primary/90'
                                        : 'bg-background text-muted-foreground border-border/50 hover:bg-muted/50 hover:text-foreground']">
                                <LayoutGrid class="h-4 w-4" />
                                All Topics
                                <Badge :variant="!selectedCategory ? 'secondary' : 'outline'"
                                    :class="['ml-1.5 h-5 px-1.5 min-w-[1.25rem] justify-center', !selectedCategory ? 'bg-primary-foreground/20 text-primary-foreground border-transparent' : 'border-border/50']">
                                    {{ filteredAllTopics.length }}
                                </Badge>
                            </button>

                            <button v-for="category in categories" :key="category.id"
                                @click="selectedCategory = category.name"
                                :class="['flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all duration-200 border',
                                    selectedCategory === category.name
                                        ? 'bg-primary text-primary-foreground border-primary shadow-sm hover:bg-primary/90'
                                        : 'bg-background text-muted-foreground border-border/50 hover:bg-muted/50 hover:text-foreground']">
                                <FolderOpen class="h-4 w-4" />
                                {{ category.name }}
                                <Badge :variant="selectedCategory === category.name ? 'secondary' : 'outline'"
                                    :class="['ml-1.5 h-5 px-1.5 min-w-[1.25rem] justify-center', selectedCategory === category.name ? 'bg-primary-foreground/20 text-primary-foreground border-transparent' : 'border-border/50']">
                                    {{filteredAllTopics.filter(t => matchTopicToCategory(t, category.name)).length}}
                                </Badge>
                            </button>
                        </div>
                        <ScrollBar orientation="horizontal" />
                    </ScrollArea>
                </div>

                <!-- All Topics Grid (Flat Library View) -->
                <div class="animate-in fade-in slide-in-from-bottom-8 duration-700 delay-200">
                    <!-- Empty State -->
                    <div v-if="categoryFilteredTopics.length === 0"
                        class="rounded-xl border border-dashed border-border/50 p-12 text-center bg-muted/5">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-muted/50 mb-4">
                            <Sparkles class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <h3 class="font-medium text-lg text-foreground mb-1">
                            <template v-if="filters.search || selectedCategory">No matching topics found</template>
                            <template v-else>No topics in your library yet</template>
                        </h3>
                        <p class="text-muted-foreground text-sm max-w-sm mx-auto mb-6">
                            <template v-if="filters.search || selectedCategory">Try adjusting your search or
                                filters.</template>
                            <template v-else>Create a project and generate topics to build your library.</template>
                        </p>
                        <Button v-if="filters.search || selectedCategory" variant="outline" @click="clearFilters">
                            Clear Filters
                        </Button>
                        <Button v-else @click="router.visit(route('projects.index'))">
                            Go to Projects
                        </Button>
                    </div>

                    <!-- Topics Grid -->
                    <div v-else class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="topic in paginatedTopics" :key="topic.id"
                            class="group flex flex-col rounded-xl border border-border/50 bg-card/50 backdrop-blur-sm p-5 transition-all duration-300 hover:bg-card hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1 relative overflow-hidden">

                            <!-- Category Badge -->
                            <div
                                class="absolute top-0 right-0 px-3 py-1 bg-primary/10 text-primary text-[10px] font-medium rounded-bl-lg">
                                {{ topic.faculty || topic.field_of_study || 'General' }}
                            </div>

                            <!-- Card Header -->
                            <div class="mb-4 mt-2">
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <Badge :class="difficultyBadge(topic.difficulty)" variant="outline"
                                        class="rounded-md">
                                        {{ topic.difficulty || 'Intermediate' }}
                                    </Badge>
                                    <Badge variant="secondary"
                                        class="bg-secondary/50 text-secondary-foreground rounded-md">
                                        {{ topic.timeline || '6-9 months' }}
                                    </Badge>
                                    <span v-if="topic.feasibility_score"
                                        class="ml-auto text-xs font-medium flex items-center gap-1.5 text-muted-foreground bg-muted/50 px-2 py-0.5 rounded-md border border-border/50">
                                        <Clock class="h-3 w-3" /> {{ topic.feasibility_score }}%
                                    </span>
                                </div>

                                <SafeHtmlText :content="topic.title" as="h3"
                                    class="font-bold text-lg text-foreground leading-snug group-hover:text-primary transition-colors line-clamp-2" />
                            </div>

                            <!-- Description -->
                            <div class="flex-1 mb-5 relative">
                                <SafeHtmlText :content="topic.description" as="p"
                                    class="text-sm text-muted-foreground leading-relaxed line-clamp-4" />
                                <!-- Fade out effect at bottom of text -->
                                <div
                                    class="absolute bottom-0 left-0 right-0 h-6 bg-gradient-to-t from-card/50 to-transparent pointer-events-none group-hover:from-card group-hover:via-card/80 transition-all">
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="mt-auto pt-4 border-t border-border/50 flex items-center justify-between gap-3">
                                <div class="flex flex-wrap gap-1.5 overflow-hidden h-6">
                                    <Badge v-for="keyword in (topic.keywords || []).slice(0, 2)" :key="keyword"
                                        variant="outline"
                                        class="text-[10px] bg-background/50 border-border/50 text-muted-foreground h-5 font-normal">
                                        {{ keyword }}
                                    </Badge>
                                    <span v-if="(topic.keywords?.length || 0) > 2"
                                        class="text-[10px] text-muted-foreground flex items-center px-1">
                                        +{{ (topic.keywords?.length || 0) - 2 }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-1">
                                    <Badge v-if="topic.research_type" variant="outline"
                                        class="text-[10px] bg-background/50 border-border/50 text-muted-foreground h-5 font-normal">
                                        {{ topic.research_type }}
                                    </Badge>
                                    <Button v-if="topicSets[0]?.project" size="icon" variant="ghost"
                                        class="h-8 w-8 rounded-full hover:bg-primary hover:text-primary-foreground transition-colors shrink-0"
                                        @click="router.visit(route('topics.lab', topicSets[0].project.slug))"
                                        title="Refine in Topic Lab">
                                        <MessageSquare class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="mt-8 flex items-center justify-center gap-2">
                        <Button variant="outline" size="sm" :disabled="currentPage === 1"
                            @click="goToPage(currentPage - 1)" class="gap-1 px-2 sm:px-4">
                            <ChevronLeft class="h-4 w-4" />
                            <span class="hidden sm:inline">Previous</span>
                        </Button>

                        <!-- Mobile View: Page X of Y -->
                        <div class="flex sm:hidden items-center text-sm text-muted-foreground font-medium px-2">
                            Page {{ currentPage }} of {{ totalPages }}
                        </div>

                        <!-- Desktop View: Numbered Buttons -->
                        <div class="hidden sm:flex items-center gap-1">
                            <button v-for="page in Math.min(5, totalPages)" :key="page" @click="goToPage(page)"
                                :class="['h-9 w-9 rounded-lg text-sm font-medium transition-all',
                                    currentPage === page
                                        ? 'bg-primary text-primary-foreground shadow-md'
                                        : 'bg-card/50 text-muted-foreground hover:bg-card hover:text-foreground border border-border/50']">
                                {{ page }}
                            </button>
                            <span v-if="totalPages > 5" class="px-2 text-muted-foreground">...</span>
                            <button v-if="totalPages > 5" @click="goToPage(totalPages)"
                                :class="['h-9 w-9 rounded-lg text-sm font-medium transition-all',
                                    currentPage === totalPages
                                        ? 'bg-primary text-primary-foreground shadow-md'
                                        : 'bg-card/50 text-muted-foreground hover:bg-card hover:text-foreground border border-border/50']">
                                {{ totalPages }}
                            </button>
                        </div>

                        <Button variant="outline" size="sm" :disabled="currentPage === totalPages"
                            @click="goToPage(currentPage + 1)" class="gap-1 px-2 sm:px-4">
                            <span class="hidden sm:inline">Next</span>
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Results count -->
                    <div v-if="categoryFilteredTopics.length > 0"
                        class="mt-6 text-center text-sm text-muted-foreground">
                        Showing {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage,
                            categoryFilteredTopics.length) }} of {{ categoryFilteredTopics.length }} topics
                        <span v-if="selectedCategory" class="text-primary"> in {{ selectedCategory }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
