<!-- ChatSearch.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Search,
    X,
    Clock,
    MessageSquare,
    ChevronLeft,
    ChevronRight,
    Filter,
} from 'lucide-vue-next';
import { ref, computed, watch, nextTick } from 'vue';
import { route } from 'ziggy-js';
import axios from 'axios';

interface SearchResult {
    id: number;
    content: string;
    message_type: 'user' | 'ai' | 'system';
    timestamp: string;
    session_id: string;
    context: string; // Surrounding messages for context
    highlight: string; // Highlighted match
}

interface Props {
    projectSlug: string;
    chapterNumber: number;
    show: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'close': [];
    'message-selected': [messageId: number, sessionId: string];
}>();

// Reactive state
const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const isSearching = ref(false);
const searchError = ref('');
const currentPage = ref(1);
const totalResults = ref(0);
const resultsPerPage = 10;
const selectedType = ref<'all' | 'user' | 'ai'>('all');

// Computed
const hasResults = computed(() => searchResults.value.length > 0);
const totalPages = computed(() => Math.ceil(totalResults.value / resultsPerPage));
const hasNextPage = computed(() => currentPage.value < totalPages.value);
const hasPrevPage = computed(() => currentPage.value > 1);

const isEmpty = computed(() =>
    !isSearching.value && searchQuery.value.length > 0 && !hasResults.value
);

// Debounced search
let searchTimeout: number | null = null;

const debouncedSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        if (searchQuery.value.trim().length >= 2) {
            performSearch();
        } else {
            searchResults.value = [];
            totalResults.value = 0;
            currentPage.value = 1;
        }
    }, 300) as unknown as number;
};

const performSearch = async (page = 1) => {
    if (!searchQuery.value.trim() || isSearching.value) return;

    isSearching.value = true;
    searchError.value = '';
    currentPage.value = page;

    try {
        const response = await axios.get(
            route('chapters.chat-search', {
                project: props.projectSlug,
                chapter: props.chapterNumber,
            }),
            {
                params: {
                    q: searchQuery.value.trim(),
                    type: selectedType.value === 'all' ? null : selectedType.value,
                    page: page,
                    per_page: resultsPerPage,
                }
            }
        );

        if (response.data.success) {
            searchResults.value = response.data.results;
            totalResults.value = response.data.total;
        } else {
            searchError.value = response.data.error || 'Search failed';
        }
    } catch (error: any) {
        console.error('Search error:', error);
        searchError.value = 'Failed to search chat history. Please try again.';
    } finally {
        isSearching.value = false;
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = [];
    totalResults.value = 0;
    currentPage.value = 1;
    searchError.value = '';
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
        performSearch(page);
    }
};

const selectMessage = (result: SearchResult) => {
    emit('message-selected', result.id, result.session_id);
    emit('close');
};

const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return 'Today ' + date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } else if (diffDays === 1) {
        return 'Yesterday ' + date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } else if (diffDays < 7) {
        return date.toLocaleDateString('en-US', {
            weekday: 'short',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } else {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    }
};

const getMessageTypeLabel = (type: string) => {
    switch (type) {
        case 'user': return 'You';
        case 'ai': return 'AI';
        case 'system': return 'System';
        default: return type;
    }
};

const getMessageTypeColor = (type: string) => {
    switch (type) {
        case 'user': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'ai': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'system': return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
};

// Watch for search query changes
watch(searchQuery, debouncedSearch);

// Watch for type filter changes
watch(selectedType, () => {
    if (searchQuery.value.trim().length >= 2) {
        currentPage.value = 1;
        performSearch();
    }
});

// Focus search input when opened
watch(() => props.show, (newShow) => {
    if (newShow) {
        nextTick(() => {
            const searchInput = document.querySelector('[data-search-input]') as HTMLInputElement;
            searchInput?.focus();
        });
    }
});
</script>

<template>
    <div v-if="show" class="flex h-full flex-col bg-background border-l">
        <!-- Search Header -->
        <div class="flex-shrink-0 border-b p-3">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold flex items-center gap-2">
                    <Search class="h-4 w-4" />
                    Search Chat History
                </h3>
                <Button @click="emit('close')" variant="ghost" size="icon" class="h-6 w-6">
                    <X class="h-3 w-3" />
                </Button>
            </div>

            <!-- Search Input -->
            <div class="space-y-2">
                <div class="relative">
                    <Search class="absolute left-2 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        data-search-input
                        placeholder="Search messages..."
                        class="pl-7 pr-8 h-8 text-sm"
                    />
                    <Button
                        v-if="searchQuery"
                        @click="clearSearch"
                        variant="ghost"
                        size="icon"
                        class="absolute right-1 top-1/2 h-6 w-6 -translate-y-1/2"
                    >
                        <X class="h-3 w-3" />
                    </Button>
                </div>

                <!-- Type Filter -->
                <div class="flex gap-1">
                    <Button
                        @click="selectedType = 'all'"
                        :variant="selectedType === 'all' ? 'default' : 'outline'"
                        size="xs"
                        class="h-6 px-2 text-xs"
                    >
                        All
                    </Button>
                    <Button
                        @click="selectedType = 'user'"
                        :variant="selectedType === 'user' ? 'default' : 'outline'"
                        size="xs"
                        class="h-6 px-2 text-xs"
                    >
                        Your Messages
                    </Button>
                    <Button
                        @click="selectedType = 'ai'"
                        :variant="selectedType === 'ai' ? 'default' : 'outline'"
                        size="xs"
                        class="h-6 px-2 text-xs"
                    >
                        AI Responses
                    </Button>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        <ScrollArea class="flex-1">
            <div class="p-3 space-y-3">
                <!-- Loading State -->
                <div v-if="isSearching" class="text-center py-8">
                    <div class="inline-flex items-center gap-2 text-sm text-muted-foreground">
                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                        Searching...
                    </div>
                </div>

                <!-- Error State -->
                <div v-else-if="searchError" class="text-center py-8">
                    <p class="text-sm text-destructive">{{ searchError }}</p>
                    <Button @click="performSearch()" variant="outline" size="sm" class="mt-2">
                        Try Again
                    </Button>
                </div>

                <!-- Empty State -->
                <div v-else-if="isEmpty" class="text-center py-8">
                    <div class="flex flex-col items-center gap-2">
                        <MessageSquare class="h-8 w-8 text-muted-foreground/50" />
                        <p class="text-sm text-muted-foreground">No messages found</p>
                        <p class="text-xs text-muted-foreground">Try a different search term</p>
                    </div>
                </div>

                <!-- Results -->
                <div v-else-if="hasResults" class="space-y-2">
                    <!-- Results Header -->
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-muted-foreground">
                            {{ totalResults }} result{{ totalResults !== 1 ? 's' : '' }} found
                        </p>
                        <p v-if="totalPages > 1" class="text-xs text-muted-foreground">
                            Page {{ currentPage }} of {{ totalPages }}
                        </p>
                    </div>

                    <!-- Result Items -->
                    <div class="space-y-2">
                        <div
                            v-for="result in searchResults"
                            :key="result.id"
                            @click="selectMessage(result)"
                            class="cursor-pointer rounded-lg border p-3 hover:bg-muted/50 transition-colors"
                        >
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <Badge :class="getMessageTypeColor(result.message_type)" class="text-xs">
                                    {{ getMessageTypeLabel(result.message_type) }}
                                </Badge>
                                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <Clock class="h-3 w-3" />
                                    {{ formatTimestamp(result.timestamp) }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <!-- Highlighted content -->
                                <p class="text-sm leading-relaxed" v-html="result.highlight"></p>

                                <!-- Context if available -->
                                <p v-if="result.context" class="text-xs text-muted-foreground border-l-2 pl-2">
                                    {{ result.context }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="flex items-center justify-center gap-1 pt-2 border-t">
                        <Button
                            @click="goToPage(currentPage - 1)"
                            :disabled="!hasPrevPage"
                            variant="outline"
                            size="icon"
                            class="h-6 w-6"
                        >
                            <ChevronLeft class="h-3 w-3" />
                        </Button>

                        <span class="text-xs text-muted-foreground px-2">
                            {{ currentPage }} / {{ totalPages }}
                        </span>

                        <Button
                            @click="goToPage(currentPage + 1)"
                            :disabled="!hasNextPage"
                            variant="outline"
                            size="icon"
                            class="h-6 w-6"
                        >
                            <ChevronRight class="h-3 w-3" />
                        </Button>
                    </div>
                </div>

                <!-- Prompt to search -->
                <div v-else-if="!searchQuery" class="text-center py-8">
                    <div class="flex flex-col items-center gap-2">
                        <Search class="h-8 w-8 text-muted-foreground/50" />
                        <p class="text-sm text-muted-foreground">Search your chat history</p>
                        <p class="text-xs text-muted-foreground">Enter at least 2 characters to search</p>
                    </div>
                </div>
            </div>
        </ScrollArea>
    </div>
</template>

<style scoped>
/* Highlight search terms in results */
:deep(.search-highlight) {
    background-color: hsl(var(--primary) / 0.2);
    font-weight: 500;
    padding: 0 2px;
    border-radius: 2px;
}
</style>