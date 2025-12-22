<script setup lang="ts">
import { ref, watch } from 'vue';
import { useSwipe } from '@vueuse/core';
import {
    ChevronRight,
    History,
    Presentation,
    MessageSquare,
    FileText,
    Sparkles
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';

interface PresentationSlide {
    title: string;
    duration: string;
    content: string;
    talking_points: string[];
    visuals: string;
}

const props = withDefaults(defineProps<{
    slides: PresentationSlide[];
    isLoading: boolean;
    rawGuide?: string | null;
    activeIndex: number;
    layout?: 'split' | 'compact';
}>(), {
    layout: 'split'
});

const emit = defineEmits<{
    (e: 'update:activeIndex', index: number): void;
    (e: 'regenerate'): void;
}>();

const updateIndex = (index: number) => {
    emit('update:activeIndex', index);
};

const nextSlide = () => {
    if (props.activeIndex < props.slides.length - 1) {
        updateIndex(props.activeIndex + 1);
    }
};

const prevSlide = () => {
    if (props.activeIndex > 0) {
        updateIndex(props.activeIndex - 1);
    }
};

const containerRef = ref<HTMLElement | null>(null);
const { isSwiping, direction } = useSwipe(containerRef);

watch(isSwiping, (newVal, oldVal) => {
    if (oldVal && !newVal) {
        if (direction.value === 'left') {
            nextSlide();
        } else if (direction.value === 'right') {
            prevSlide();
        }
    }
});
</script>

<template>
    <div v-if="slides.length" class="flex flex-col gap-4 h-full" ref="containerRef">
        <!-- Progress Bar -->
        <div class="w-full h-1 bg-zinc-800 rounded-full overflow-hidden shrink-0">
            <div class="h-full bg-indigo-500 transition-all duration-500 ease-out"
                :style="{ width: `${((activeIndex + 1) / slides.length) * 100}%` }">
            </div>
        </div>

        <div class="min-h-0 flex-1"
            :class="layout === 'split' ? 'grid grid-cols-1 lg:grid-cols-12 gap-6' : 'flex flex-col'">
            <!-- Left: Navigation (Slide List) - ONLY IN SPLIT MODE -->
            <div v-if="layout === 'split'"
                class="hidden lg:block lg:col-span-4 space-y-1 overflow-y-auto pr-2 custom-scrollbar h-full max-h-[400px] lg:max-h-none">
                <button v-for="(slide, i) in slides" :key="i" @click="updateIndex(i)"
                    class="w-full flex items-center justify-between p-3 rounded-lg text-left transition-all group border border-transparent shrink-0"
                    :class="activeIndex === i ? 'bg-indigo-500/10 border-indigo-500/20 text-white' : 'hover:bg-muted/50 text-muted-foreground'">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <span class="text-xs font-mono font-bold w-4 shrink-0"
                            :class="activeIndex === i ? 'text-indigo-400' : 'text-zinc-600'">
                            {{ (i + 1).toString().padStart(2, '0') }}
                        </span>
                        <span class="text-xs font-medium truncate">{{ slide.title }}</span>
                    </div>
                    <ChevronRight class="h-3 w-3 transition-transform shrink-0"
                        :class="activeIndex === i ? 'text-indigo-400' : 'opacity-0'" />
                </button>
            </div>

            <!-- Compact Navigation Header - ONLY IN COMPACT MODE -->
            <div v-if="layout === 'compact'"
                class="flex items-center justify-between mb-4 border-b border-white/5 pb-4 shrink-0">
                <Button variant="ghost" size="icon" class="h-8 w-8 text-zinc-400 hover:text-white"
                    :disabled="activeIndex === 0" @click="prevSlide">
                    <ChevronRight class="h-4 w-4 rotate-180" />
                </Button>

                <div class="text-center">
                    <span class="text-[10px] uppercase tracking-wider text-indigo-400 font-bold block">
                        Slide {{ activeIndex + 1 }} of {{ slides.length }}
                    </span>
                    <h4 class="text-sm font-bold font-display text-white truncate max-w-[200px]">
                        {{ slides[activeIndex].title }}
                    </h4>
                </div>

                <Button variant="ghost" size="icon" class="h-8 w-8 text-zinc-400 hover:text-white"
                    :disabled="activeIndex === slides.length - 1" @click="nextSlide">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>

            <!-- Right: Active Slide Content -->
            <div class="flex flex-col h-full min-h-0" :class="layout === 'split' ? 'lg:col-span-8' : 'w-full'">
                <div
                    class="relative bg-zinc-900/50 border border-white/5 rounded-2xl p-5 flex flex-col h-full overflow-hidden">
                    <!-- Slide Header (SPLIT MODE ONLY) -->
                    <div v-if="layout === 'split'"
                        class="flex items-start justify-between mb-4 border-b border-white/5 pb-4 shrink-0">
                        <div>
                            <span
                                class="text-[10px] uppercase tracking-wider text-indigo-400 font-bold mb-1 block">Slide
                                {{ activeIndex + 1 }}</span>
                            <h4 class="text-base md:text-lg font-bold font-display text-white line-clamp-2 md:line-clamp-none">{{
                                slides[activeIndex].title }}</h4>
                        </div>
                        <Badge variant="outline"
                            class="bg-zinc-950/50 border-zinc-800 text-zinc-400 text-[10px] gap-1.5 font-mono shrink-0 ml-2">
                            <History class="h-3 w-3" />
                            <span class="hidden sm:inline">{{ slides[activeIndex].duration }}</span>
                            <span class="sm:hidden">{{ slides[activeIndex].duration.split(' ')[0] }}m</span>
                        </Badge>
                    </div>

                    <!-- Scrollable Content Area -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                        <!-- Visuals Suggestion -->
                        <div v-if="slides[activeIndex].visuals"
                            class="bg-indigo-500/5 border border-indigo-500/10 rounded-lg p-3">
                            <div class="flex items-start gap-2 text-xs text-indigo-300/80">
                                <div class="bg-indigo-500/20 p-1 rounded-md mt-0.5 shrink-0">
                                    <Presentation class="h-3 w-3 text-indigo-400" />
                                </div>
                                <span class="leading-relaxed"><strong class="text-indigo-300">Visuals:</strong> {{
                                    slides[activeIndex].visuals }}</span>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="prose prose-sm dark:prose-invert max-w-none text-zinc-300 text-xs leading-relaxed">
                            <RichTextViewer :content="slides[activeIndex].content" :show-font-controls="false"
                                class="!bg-transparent !p-0" viewer-class="prose-sm" />
                        </div>

                        <!-- Speaker Notes (Talking Points) -->
                        <div class="pt-4 border-t border-white/5">
                            <h5 class="text-xs font-bold text-zinc-500 uppercase mb-3 flex items-center gap-2">
                                <MessageSquare class="h-3 w-3" /> Speaker Notes
                            </h5>
                            <ul class="space-y-2">
                                <li v-for="(point, idx) in slides[activeIndex].talking_points" :key="idx"
                                    class="flex gap-2 text-xs text-zinc-400">
                                    <span class="text-indigo-500/50">•</span>
                                    {{ point }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-else-if="rawGuide" class="mt-4 text-sm text-muted-foreground">
        <RichTextViewer :content="rawGuide" :show-font-controls="false" class="!bg-transparent"
            viewer-class="prose-sm md:prose-base" />
    </div>

    <div v-else class="flex flex-col items-center justify-center py-8 text-center space-y-4 h-full">
        <div class="h-12 w-12 rounded-full bg-indigo-500/10 flex items-center justify-center">
            <Presentation class="h-6 w-6 text-indigo-500/50" />
        </div>
        <p class="text-sm text-zinc-500 max-w-xs">
            No presentation guide yet. Generate a structured slide deck for your defense.
        </p>
        <Button class="gap-2 rounded-xl group" @click="$emit('regenerate')" :disabled="isLoading">
            <FileText class="h-4 w-4" />
            {{ isLoading ? 'Generating...' : 'Generate Guide' }}
            <Sparkles class="h-3.5 w-3.5 opacity-50 group-hover:opacity-100" />
        </Button>
    </div>
</template>
