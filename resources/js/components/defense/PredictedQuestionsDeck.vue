<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useSwipe } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    Sparkles,
    RefreshCw,
    Target,
    HelpCircle,
    Lightbulb
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

interface PredictedQuestion {
    question: string;
    suggested_answer: string;
    category?: string;
}

const props = withDefaults(defineProps<{
    questions: PredictedQuestion[];
    isLoading: boolean;
}>(), {
    questions: () => []
});

const currentIndex = ref(0);
const containerRef = ref<HTMLElement | null>(null);

// Swipe Logic
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

const currentQuestion = computed(() => props.questions[currentIndex.value]);

const nextSlide = () => {
    if (currentIndex.value < props.questions.length - 1) {
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
    return text.split('\n\n').map(p => {
        const content = p.trim().replace(/\n/g, '<br>');
        return `<p>${content}</p>`;
    }).join('');
};
</script>

<template>
    <div class="flex flex-col h-full relative" ref="containerRef">
        <!-- Content Area -->
        <div class="flex-1 min-h-[350px] relative overflow-hidden">
            <template v-if="questions.length > 0">
                <transition enter-active-class="transition-opacity duration-300 ease-out" enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-200 ease-in absolute inset-0"
                    leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div :key="currentIndex" class="h-full flex flex-col pt-2">
                        <!-- Category Badge -->
                        <div v-if="currentQuestion.category" class="mb-6">
                            <Badge variant="outline"
                                class="text-[10px] uppercase tracking-widest bg-rose-500/5 text-rose-400 border-rose-500/20 px-3 py-1">
                                {{ currentQuestion.category }}
                            </Badge>
                        </div>

                        <!-- Question -->
                        <div class="mb-6 md:mb-10">
                            <div class="flex items-start gap-3 md:gap-4">
                                <div class="h-8 w-8 md:h-10 md:w-10 shrink-0 rounded-lg md:rounded-xl bg-rose-500/10 flex items-center justify-center">
                                    <HelpCircle class="h-4 w-4 md:h-5 md:w-5 text-rose-500" />
                                </div>
                                <h3 class="text-xl md:text-4xl font-bold font-display text-white tracking-tight leading-snug md:leading-tight">
                                    {{ currentQuestion.question }}
                                </h3>
                            </div>
                        </div>

                        <!-- Answer -->
                        <div class="flex-1 overflow-y-auto custom-scrollbar pr-4 md:pr-6 pb-6">
                            <div class="relative pl-5 md:pl-6">
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-indigo-500 to-transparent rounded-full opacity-50"></div>
                                <div class="flex items-center gap-2 mb-3 md:mb-4 text-indigo-400 font-medium text-[10px] md:text-sm tracking-wide uppercase">
                                    <Lightbulb class="h-3 w-3 md:h-4 md:w-4" />
                                    <span>Defense Strategy</span>
                                </div>
                                <div v-html="formatContent(currentQuestion.suggested_answer)"
                                    class="text-zinc-300 text-base md:text-xl leading-relaxed tracking-wide italic font-serif [&_p]:mb-4 md:[&_p]:mb-6">
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </template>

            <!-- Empty State -->
            <div v-else-if="!isLoading" class="flex flex-col items-center justify-center h-full text-center space-y-4">
                <div class="h-16 w-16 rounded-full bg-rose-500/10 flex items-center justify-center">
                    <Target class="h-8 w-8 text-rose-500/50" />
                </div>
                <div class="space-y-2">
                    <p class="text-lg font-medium text-white">No questions identified</p>
                    <p class="text-sm text-zinc-500 max-w-xs">
                        Generate defense questions to prepare for potential examiner inquiry.
                    </p>
                </div>
            </div>

            <!-- Loading State Overlay -->
            <div v-if="isLoading"
                class="absolute inset-0 bg-zinc-950/50 backdrop-blur-sm flex items-center justify-center z-10 rounded-3xl border border-white/5">
                <div class="flex flex-col items-center gap-4">
                    <div class="relative">
                        <RefreshCw class="h-10 w-10 text-rose-500 animate-spin" />
                        <Sparkles class="h-4 w-4 text-rose-400 absolute -top-1 -right-1 animate-pulse" />
                    </div>
                    <span class="text-sm font-medium text-rose-200">Simulating Defense Panel...</span>
                </div>
            </div>
        </div>

        <!-- Navigation Footer -->
        <div v-if="questions.length > 0" class="mt-8 pt-6 border-t border-white/5 flex items-center justify-between">
            <!-- Pagination Dots -->
            <div class="flex items-center gap-2">
                <button v-for="(_, idx) in questions" :key="idx" @click="goToSlide(idx)"
                    class="h-2 rounded-full transition-all duration-300"
                    :class="currentIndex === idx ? 'w-8 bg-rose-500' : 'w-2 bg-zinc-800 hover:bg-zinc-700'" />
            </div>

            <!-- Navigation Buttons -->
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon"
                    class="h-10 w-10 rounded-xl border border-white/5 text-zinc-400 hover:text-white hover:bg-white/5 transition-all"
                    :disabled="currentIndex === 0" @click="prevSlide">
                    <ChevronLeft class="h-5 w-5" />
                </Button>
                <div class="flex flex-col items-center min-w-[3rem]">
                    <span class="text-base font-bold text-white leading-none">{{ currentIndex + 1 }}</span>
                    <span class="text-[10px] text-zinc-500 font-mono uppercase mt-1">{{ questions.length }} Total</span>
                </div>
                <Button variant="ghost" size="icon"
                    class="h-10 w-10 rounded-xl border border-white/5 text-zinc-400 hover:text-white hover:bg-white/5 transition-all"
                    :disabled="currentIndex === questions.length - 1" @click="nextSlide">
                    <ChevronRight class="h-5 w-5" />
                </Button>
            </div>
        </div>
    </div>
</template>
