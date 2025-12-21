<script setup lang="ts">
import { ref, computed } from 'vue';
import { useSwipe } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    Sparkles,
    RefreshCw
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';


interface BriefingSlide {
    title: string;
    content: string;
}

const props = withDefaults(defineProps<{
    slides: BriefingSlide[];
    isLoading: boolean;
}>(), {
    slides: () => []
});

const emit = defineEmits<{
    (e: 'refresh'): void;
}>();

const currentIndex = ref(0);
const containerRef = ref<HTMLElement | null>(null);

// Swipe Logic
const { isSwiping, direction } = useSwipe(containerRef);

// Watch for swipe completion
import { watch } from 'vue';
watch(isSwiping, (newVal, oldVal) => {
    if (oldVal && !newVal) {
        if (direction.value === 'left') {
            nextSlide();
        } else if (direction.value === 'right') {
            prevSlide();
        }
    }
});

const currentSlide = computed(() => props.slides[currentIndex.value]);

const nextSlide = () => {
    if (currentIndex.value < props.slides.length - 1) {
        currentIndex.value++;
    }
};

const prevSlide = () => {
    if (currentIndex.value > 0) {
        currentIndex.value--;
    }
};

const goToSlide = (index: number) => {
    currentIndex.value = index;
};

const formatContent = (text: string): string => {
    if (!text) return '';

    let html = text;

    // Convert headings
    html = html.replace(/^#### (.*$)/gim, '<h4>$1</h4>');
    html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');

    // Convert bold text
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Convert italic text
    html = html.replace(/(?<!\*)\*([^*\n]+?)\*(?!\*)/g, '<em>$1</em>');

    // Split into blocks
    const blocks = html.split(/\n\s*\n/).filter(block => block.trim());

    return blocks.map(block => {
        const trimmed = block.trim();
        if (trimmed.match(/^<h[1-4]>/)) return trimmed;

        // Lists
        if (trimmed.includes('\n') && (trimmed.match(/^\d+\.\s/) || trimmed.match(/^[-*]\s/))) {
            const lines = trimmed.split('\n').map(l => l.trim()).filter(l => l);
            const isNumbered = lines.some(l => l.match(/^\d+\.\s/));

            const items = lines.map(line => {
                const content = line.replace(/^(\d+\.|[-*])\s/, '');
                return `<li>${content}</li>`;
            }).join('');

            return isNumbered ? `<ol>${items}</ol>` : `<ul>${items}</ul>`;
        }

        // Paragraphs
        return `<p>${trimmed.replace(/\n/g, '<br>')}</p>`;
    }).join('');
};
</script>

<template>
    <div class="flex flex-col h-full relative" ref="containerRef">
        <!-- Content Area -->
        <div class="flex-1 min-h-[300px] relative overflow-hidden">
            <template v-if="slides.length > 0">
                <transition enter-active-class="transition-opacity duration-300 ease-out" enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-200 ease-in absolute inset-0"
                    leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div :key="currentIndex" class="h-full flex flex-col">
                        <h3 class="text-2xl md:text-4xl font-bold font-display text-white mb-8 md:mb-10 tracking-tight">
                            {{ currentSlide.title }}
                        </h3>

                        <div class="flex-1 overflow-y-auto custom-scrollbar pr-4 md:pr-6 pb-4 max-w-4xl">
                            <div v-html="formatContent(currentSlide.content)"
                                class="prose-base md:prose-xl dark:prose-invert leading-loose tracking-wide [&_p]:mb-6 [&_strong]:text-indigo-400 [&_strong]:font-semibold text-zinc-50 [&_ul]:list-none [&_ul]:pl-0 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-6 [&_li]:pl-4 [&_li]:border-l-2 [&_li]:border-indigo-500/50">
                            </div>
                        </div>
                    </div>
                </transition>
            </template>

            <!-- Empty State -->
            <div v-else-if="!isLoading" class="flex flex-col items-center justify-center h-full text-center space-y-4">
                <div class="h-12 w-12 rounded-full bg-indigo-500/10 flex items-center justify-center">
                    <Sparkles class="h-6 w-6 text-indigo-500/50" />
                </div>
                <p class="text-base text-zinc-500 max-w-xs">
                    No executive briefing yet. Click refresh to generate a summary.
                </p>
            </div>

            <!-- Loading State Overlay -->
            <div v-if="isLoading"
                class="absolute inset-0 bg-zinc-950/50 backdrop-blur-sm flex items-center justify-center z-10 rounded-xl">
                <div class="flex flex-col items-center gap-3">
                    <RefreshCw class="h-6 w-6 text-indigo-400 animate-spin" />
                    <span class="text-xs font-medium text-indigo-300">Analyzing Project...</span>
                </div>
            </div>
        </div>

        <!-- Navigation Footer -->
        <div v-if="slides.length > 0" class="mt-6 pt-4 border-t border-white/5 flex items-center justify-between">
            <!-- Pagination Dots -->
            <div class="flex items-center gap-1.5">
                <button v-for="(_, idx) in slides" :key="idx" @click="goToSlide(idx)"
                    class="h-1.5 rounded-full transition-all duration-300"
                    :class="currentIndex === idx ? 'w-6 bg-indigo-500' : 'w-1.5 bg-zinc-700 hover:bg-zinc-600'" />
            </div>

            <!-- Navigation Buttons -->
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon" class="h-8 w-8 text-zinc-400 hover:text-white"
                    :disabled="currentIndex === 0" @click="prevSlide">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <span class="text-sm font-mono text-zinc-500 w-12 text-center">
                    {{ currentIndex + 1 }} / {{ slides.length }}
                </span>
                <Button variant="ghost" size="icon" class="h-8 w-8 text-zinc-400 hover:text-white"
                    :disabled="currentIndex === slides.length - 1" @click="nextSlide">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
