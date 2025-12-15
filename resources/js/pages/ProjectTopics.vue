<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch, onMounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import TopicDetailsDialog, { type TopicDetails } from '@/components/topics/TopicDetailsDialog.vue';
import { route } from 'ziggy-js';
import {
    BookOpen,
    Sparkles,
    Clock,
    Library,
    Search,
    Filter,
    ChevronLeft,
    ChevronRight,
    LayoutGrid,
    FolderOpen,
    Menu,
    X,
    LogOut,
    ArrowRight,
    Target
} from 'lucide-vue-next';

// Interfaces
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

interface Faculty {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    allTopics: Topic[];
    faculties: Faculty[];
    meta: {
        totalTopics: number;
    };
}

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => page.props.auth.user);
const isAuthenticated = computed(() => !!user.value);

// Mobile Menu
const isMobileMenuOpen = ref(false);

// Topic Logic
const allTopicsList = reactive<Topic[]>([...props.allTopics]);
const totalTopics = computed(() => props.meta.totalTopics);

const selectedTopic = ref<TopicDetails | null>(null);
const isTopicModalOpen = ref(false);
const openTopicModal = (topic: Topic) => {
    selectedTopic.value = topic;
    isTopicModalOpen.value = true;
};

const filters = reactive({
    search: '',
    difficulty: '',
    timeline: '',
    researchType: '',
});

// Utilities
const stripHtml = (html: string): string => {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

const normalizeText = (text: string): string => {
    if (!text) return '';
    return stripHtml(text)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\w\s]/g, ' ')
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

    return tokens.every(token => {
        if (h.includes(token)) return true;
        return words.some(word => {
            if (word === token || word.includes(token)) return true;
            if (word.startsWith(token) || token.startsWith(word)) return true;
            const threshold = Math.min(2, Math.floor(token.length / 3));
            return levenshtein(token, word) <= threshold;
        });
    });
};

const filterOptions = computed(() => {
    const difficulties = new Set<string>();
    const timelines = new Set<string>();
    const researchTypes = new Set<string>();

    allTopicsList.forEach(topic => {
        if (topic.difficulty) difficulties.add(topic.difficulty);
        if (topic.timeline) timelines.add(topic.timeline);
        if (topic.research_type) researchTypes.add(topic.research_type);
    });

    return {
        difficulties: Array.from(difficulties),
        timelines: Array.from(timelines),
        researchTypes: Array.from(researchTypes),
    };
});

const matchTopicFilters = (topic: Topic) => {
    const search = filters.search.trim();
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
    if (!topic.faculty) return false;
    const clean = (str: string) => normalizeText(str).replace(/\b(faculty|school|college|department|institute|centre|center)\s+(of\s+)?/g, '').trim();
    const target = clean(categoryName);
    const faculty = clean(topic.faculty);
    return faculty === target;
};

const filteredAllTopics = computed(() => {
    return allTopicsList.filter(topic => matchTopicFilters(topic));
});

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(12);
const selectedCategory = ref<string>('');

const categoryFilteredTopics = computed(() => {
    if (!selectedCategory.value) return filteredAllTopics.value;
    return filteredAllTopics.value.filter(topic => matchTopicToCategory(topic, selectedCategory.value));
});

const paginatedTopics = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return categoryFilteredTopics.value.slice(start, end);
});

const totalPages = computed(() => {
    return Math.ceil(categoryFilteredTopics.value.length / itemsPerPage.value);
});

watch([() => filters.search, () => filters.difficulty, () => filters.timeline, () => filters.researchType, selectedCategory], () => {
    currentPage.value = 1;
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        window.scrollTo({ top: 400, behavior: 'smooth' });
    }
};

const clearFilters = () => {
    filters.search = '';
    filters.difficulty = '';
    filters.timeline = '';
    filters.researchType = '';
    selectedCategory.value = '';
    currentPage.value = 1;
};

// Actions
const startProject = () => {
    if (user.value) {
        router.visit(route('projects.create'));
    } else {
        router.visit(route('register'));
    }
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

    <Head title="Project Topics Library" />

    <div class="min-h-screen bg-[#09090b] text-zinc-100 font-sans selection:bg-zinc-800 selection:text-white">

        <!-- Background Gradients -->
        <div class="fixed inset-0 z-0 pointer-events-none">
            <div
                class="absolute top-[-10%] left-[20%] w-[40rem] h-[40rem] bg-indigo-500/5 blur-[120px] rounded-full mix-blend-screen">
            </div>
            <div
                class="absolute bottom-[-10%] right-[10%] w-[30rem] h-[30rem] bg-blue-500/5 blur-[100px] rounded-full mix-blend-screen">
            </div>
        </div>

        <!-- Navbar -->
        <nav class="relative z-50 border-b border-white/5 bg-[#09090b]/80 backdrop-blur-xl sticky top-0">
            <div class="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between relative">
                <!-- Logo -->
                <div class="flex items-center gap-2 group cursor-pointer">
                    <Link :href="route('home')">
                        <AppLogo class="h-8 md:h-10 w-auto fill-white" />
                    </Link>
                </div>

                <!-- Desktop Links -->
                <div
                    class="hidden md:flex items-center gap-8 text-sm font-medium text-zinc-400 absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                    <Link :href="route('home')" class="hover:text-white transition-colors duration-300">Home</Link>
                    <Link :href="route('project-topics.index')" class="text-white">Topic Library</Link>
                </div>

                <!-- Right Side -->
                <div class="flex items-center gap-2 md:gap-4">
                    <template v-if="user">
                        <Link :href="route('dashboard')"
                            class="group relative px-3 md:px-5 py-2 rounded-lg bg-zinc-100 text-zinc-950 text-sm font-semibold hover:bg-white transition-all">
                            <span class="relative z-10 flex items-center gap-2">Dashboard
                                <ArrowRight class="w-3.5 h-3.5" />
                            </span>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')"
                            class="text-sm font-medium text-zinc-400 hover:text-white transition-colors hidden sm:block">
                            Log in</Link>
                        <Link :href="route('register')"
                            class="group relative px-5 py-2 rounded-lg bg-zinc-100 text-zinc-950 text-sm font-semibold hover:bg-white transition-all">
                            <span class="relative z-10 flex items-center gap-2">Get Started
                                <ArrowRight class="w-3.5 h-3.5" />
                            </span>
                        </Link>
                    </template>

                    <!-- Mobile Menu Toggle -->
                    <button @click="isMobileMenuOpen = !isMobileMenuOpen"
                        class="md:hidden text-zinc-400 hover:text-white p-2">
                        <Menu v-if="!isMobileMenuOpen" class="w-6 h-6" />
                        <X v-else class="w-6 h-6" />
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Menu -->
            <div v-if="isMobileMenuOpen"
                class="md:hidden absolute top-20 left-0 w-full bg-[#09090b]/95 backdrop-blur-xl border-b border-white/5 py-6 px-4 flex flex-col gap-4 shadow-2xl">
                <Link :href="route('home')"
                    class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2">Home
                </Link>
                <Link :href="route('project-topics.index')"
                    class="text-base font-medium text-white transition-colors py-2">Topic
                    Library</Link>
                <div class="h-px bg-white/5 my-2"></div>
                <template v-if="!user">
                    <Link :href="route('login')"
                        class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2">Log in
                    </Link>
                </template>
            </div>
        </nav>

        <main class="relative z-10">
            <!-- Hero -->
            <section class="relative py-20 px-6 text-center">
                <div class="max-w-4xl mx-auto space-y-6">
                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-400 ring-1 ring-indigo-500/20 mb-8">
                        <Library class="h-8 w-8" />
                    </div>
                    <h1 class="text-4xl md:text-6xl font-bold tracking-tight text-white mb-6">
                        Find your next <span
                            class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400">breakthrough.</span>
                    </h1>
                    <p class="text-lg md:text-xl text-zinc-400 max-w-2xl mx-auto leading-relaxed">
                        Explore thousands of vetted project topics across every faculty.
                        Found something you like? Start your project instantly.
                    </p>
                </div>
            </section>

            <!-- Main Content -->
            <div class="max-w-7xl mx-auto px-6 pb-24">

                <!-- Filters Card -->
                <Card class="border-white/10 bg-zinc-900/50 backdrop-blur-md mb-12">
                    <CardContent class="p-6">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                            <div class="w-full lg:max-w-sm">
                                <label class="text-xs text-zinc-400 flex items-center gap-2 mb-2 font-medium">
                                    <Search class="h-3.5 w-3.5" /> Search topics
                                </label>
                                <Input v-model="filters.search" placeholder="Search by title, keywords, field..."
                                    class="bg-zinc-950/50 border-white/10 text-zinc-200 placeholder:text-zinc-600 focus-visible:ring-indigo-500/50" />
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 flex-1">
                                <div>
                                    <label class="text-xs text-zinc-400 flex items-center gap-1 mb-2 font-medium">
                                        <Filter class="h-3.5 w-3.5" /> Difficulty
                                    </label>
                                    <select v-model="filters.difficulty"
                                        class="w-full rounded-md border border-white/10 bg-zinc-950/50 px-3 py-2 text-sm text-zinc-300 focus:ring-1 focus:ring-indigo-500/50 focus:border-indigo-500/50">
                                        <option value="">All Levels</option>
                                        <option v-for="opt in filterOptions.difficulties" :key="opt" :value="opt">{{ opt
                                            }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-zinc-400 flex items-center gap-1 mb-2 font-medium">
                                        <Clock class="h-3.5 w-3.5" /> Timeline
                                    </label>
                                    <select v-model="filters.timeline"
                                        class="w-full rounded-md border border-white/10 bg-zinc-950/50 px-3 py-2 text-sm text-zinc-300 focus:ring-1 focus:ring-indigo-500/50 focus:border-indigo-500/50">
                                        <option value="">All Timelines</option>
                                        <option v-for="opt in filterOptions.timelines" :key="opt" :value="opt">{{ opt }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-zinc-400 flex items-center gap-1 mb-2 font-medium">
                                        <Sparkles class="h-3.5 w-3.5" /> Research Type
                                    </label>
                                    <select v-model="filters.researchType"
                                        class="w-full rounded-md border border-white/10 bg-zinc-950/50 px-3 py-2 text-sm text-zinc-300 focus:ring-1 focus:ring-indigo-500/50 focus:border-indigo-500/50">
                                        <option value="">All Types</option>
                                        <option v-for="opt in filterOptions.researchTypes" :key="opt" :value="opt">{{
                                            opt }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <Button
                                v-if="filters.search || filters.difficulty || filters.timeline || filters.researchType"
                                variant="ghost" size="sm" @click="clearFilters"
                                class="text-zinc-400 hover:text-white hover:bg-white/5">
                                Clear filters
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Faculty Tabs -->
                <div class="mb-10">
                    <ScrollArea class="w-full whitespace-nowrap pb-4">
                        <div class="flex w-max space-x-2">
                            <button @click="selectedCategory = ''"
                                :class="['flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all duration-300 border',
                                    !selectedCategory ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20 shadow-[0_0_15px_-3px_rgba(99,102,241,0.2)]' : 'bg-transparent text-zinc-500 border-white/5 hover:bg-white/5 hover:text-zinc-300']">
                                <LayoutGrid class="h-4 w-4" />
                                All Topics
                                <span
                                    class="ml-1.5 inline-flex items-center justify-center rounded-md bg-white/5 px-1.5 h-5 text-xs font-normal"
                                    :class="!selectedCategory ? 'text-indigo-300' : 'text-zinc-500'">{{
                                    filteredAllTopics.length
                                    }}</span>
                            </button>

                            <button v-for="category in props.faculties" :key="category.id"
                                @click="selectedCategory = category.name"
                                :class="['flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all duration-300 border',
                                    selectedCategory === category.name ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20 shadow-[0_0_15px_-3px_rgba(99,102,241,0.2)]' : 'bg-transparent text-zinc-500 border-white/5 hover:bg-white/5 hover:text-zinc-300']">
                                <FolderOpen class="h-4 w-4" />
                                {{ category.name }}
                                <span
                                    class="ml-1.5 inline-flex items-center justify-center rounded-md bg-white/5 px-1.5 h-5 text-xs font-normal"
                                    :class="selectedCategory === category.name ? 'text-indigo-300' : 'text-zinc-500'">{{
                                        filteredAllTopics.filter(t => matchTopicToCategory(t, category.name)).length
                                    }}</span>
                            </button>
                        </div>
                        <ScrollBar orientation="horizontal" class="bg-white/5" />
                    </ScrollArea>
                </div>

                <!-- Topics Grid -->
                <div>
                    <!-- Empty State -->
                    <div v-if="categoryFilteredTopics.length === 0"
                        class="rounded-2xl border border-dashed border-white/10 p-20 text-center bg-zinc-900/30">
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-zinc-800/50 mb-6">
                            <Search class="h-8 w-8 text-zinc-500" />
                        </div>
                        <h3 class="font-medium text-xl text-zinc-300 mb-2">No topics found</h3>
                        <p class="text-zinc-500 max-w-sm mx-auto mb-8">
                            We couldn't find any topics matching your current filters. Try broadening your search.
                        </p>
                        <Button variant="outline" @click="clearFilters"
                            class="border-white/10 bg-transparent text-white hover:bg-white/5">
                            Clear Filters
                        </Button>
                    </div>

                    <!-- Grid -->
	                    <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
	                        <div v-for="topic in paginatedTopics" :key="topic.id"
	                            class="group flex flex-col rounded-2xl border border-white/5 bg-zinc-900/40 backdrop-blur-sm p-6 transition-all duration-300 hover:bg-zinc-900/60 hover:shadow-2xl hover:shadow-indigo-500/10 hover:border-indigo-500/20 hover:-translate-y-1 relative overflow-hidden cursor-pointer"
                                role="button"
                                tabindex="0"
                                @click="openTopicModal(topic)"
                                @keydown.enter.prevent="openTopicModal(topic)"
                                @keydown.space.prevent="openTopicModal(topic)"
                            >
                            <!-- Category Tag -->
                            <div
                                class="absolute top-0 right-0 px-4 py-1.5 bg-white/5 text-zinc-400 text-[10px] uppercase tracking-wider font-semibold rounded-bl-xl border-l border-b border-white/5">
                                {{ topic.faculty || topic.field_of_study || 'General' }}
                            </div>

                            <div class="mb-5 mt-2">
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <Badge :class="difficultyBadge(topic.difficulty)" variant="outline"
                                        class="rounded-md border-transparent ring-1 ring-inset ring-transparent">
                                        {{ topic.difficulty || 'Intermediate' }}
                                    </Badge>
                                    <Badge variant="secondary"
                                        class="bg-zinc-800/50 text-zinc-400 border-transparent rounded-md">
                                        {{ topic.timeline || '6-9 months' }}
                                    </Badge>
                                </div>
                                <SafeHtmlText :content="topic.title" as="h3"
                                    class="font-bold text-xl text-zinc-100 leading-snug group-hover:text-indigo-400 transition-colors line-clamp-2 mb-3" />
                            </div>

                            <div class="flex-1 mb-6 relative">
                                <SafeHtmlText :content="topic.description" as="p"
                                    class="text-sm text-zinc-400 leading-relaxed line-clamp-4" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-zinc-900/40 to-transparent pointer-events-none">
                                </div>
                            </div>

                            <div class="mt-auto pt-5 border-t border-white/5 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2 text-xs text-zinc-500">
                                    <Target class="h-3.5 w-3.5" />
                                    <span>{{ topic.research_type || 'Research' }}</span>
                                </div>
                                <Button @click.stop="startProject" size="sm"
                                    class="bg-indigo-600 hover:bg-indigo-500 text-white border-0 shadow-lg shadow-indigo-500/20 rounded-lg text-xs font-semibold px-4 h-9">
                                    Start Project
                                </Button>
                            </div>

                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="mt-16 flex items-center justify-center gap-2">
                        <Button variant="outline" size="sm" :disabled="currentPage === 1"
                            @click="goToPage(currentPage - 1)"
                            class="gap-1 px-4 border-white/10 bg-transparent text-zinc-400 hover:text-white hover:bg-white/5">
                            <ChevronLeft class="h-4 w-4" /> Previous
                        </Button>
                        <div class="flex items-center gap-1 mx-2">
                            <span class="text-sm font-medium text-zinc-400">Page {{ currentPage }} of {{ totalPages
                                }}</span>
                        </div>
                        <Button variant="outline" size="sm" :disabled="currentPage === totalPages"
                            @click="goToPage(currentPage + 1)"
                            class="gap-1 px-4 border-white/10 bg-transparent text-zinc-400 hover:text-white hover:bg-white/5">
                            Next
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

            </div>
	        </main>
	    </div>

        <TopicDetailsDialog v-model:open="isTopicModalOpen" :topic="selectedTopic" title-label="Topic Details">
            <template #footer>
                <Button
                    type="button"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white border-0"
                    @click="startProject"
                >
                    Start Project
                </Button>
            </template>
        </TopicDetailsDialog>
	</template>
