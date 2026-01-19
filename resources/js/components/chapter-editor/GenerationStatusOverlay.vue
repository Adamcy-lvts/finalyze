<script setup lang="ts">
import { Brain, X, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
  isGenerating: boolean;
  generationProgress: string;
  generationPercentage: number;
}>();

const emit = defineEmits<{
  (e: 'stop'): void;
}>();
</script>

<template>
  <Transition
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-full height-0"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition-all duration-300 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-full height-0"
  >
    <div v-if="props.isGenerating" class="w-full relative z-30 bg-background/80 backdrop-blur-xl border-b border-border/50">
      
      <!-- Animated Background Gradient (Subtle) -->
      <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 via-purple-500/5 to-pink-500/5 pointer-events-none"></div>
      
      <!-- Progress Bar (Top Line) -->
      <div class="absolute top-0 left-0 right-0 h-[2px] bg-primary/10 overflow-hidden">
         <div 
           class="h-full bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 shadow-[0_0_10px_rgba(59,130,246,0.5)] transition-all duration-300 ease-out relative"
           :style="{ width: `${props.generationPercentage}%` }"
         >
           <div class="absolute inset-0 bg-white/30 w-full animate-shimmer -skew-x-12 translate-x-[-100%]"></div>
         </div>
      </div>

      <div class="container mx-auto max-w-5xl px-4 py-2 flex items-center justify-between gap-4 relative">
        
        <!-- Left: Status Text -->
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <div class="relative flex-shrink-0">
             <!-- Outer Ring Spinner (Circling) -->
             <div class="absolute inset-0 rounded-full border-2 border-primary/30 border-t-primary animate-spin" style="animation-duration: 1.5s;"></div>
             
             <!-- Inner Pulse (Optional/Subtle) -->
             <div class="absolute inset-0 rounded-full animate-ping opacity-20 bg-primary"></div>

             <div class="h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center text-primary relative z-10">
               <Brain class="h-3.5 w-3.5" />
             </div>
          </div>
          
          <div class="flex flex-col min-w-0">
             <div class="flex items-baseline gap-2">
                <span class="text-xs font-semibold text-foreground tracking-tight">Writing Chapter</span>
                <span class="text-[10px] text-muted-foreground hidden sm:inline-block truncate">
                  {{ props.generationProgress || 'Initializing...' }}
                </span>
             </div>
             <!-- Mobile-only progress subtext -->
             <span class="text-[10px] text-muted-foreground sm:hidden truncate">
                {{ props.generationProgress || 'Initializing...' }}
             </span>
          </div>
        </div>

        <!-- Right: Actions & Percentage -->
        <div class="flex items-center gap-3 flex-shrink-0">
           <div class="flex items-center gap-1.5 px-2 py-1 rounded-md bg-secondary/50 border border-border/50">
              <span class="text-xs font-mono font-medium text-primary">
                {{ Math.round(props.generationPercentage) }}%
              </span>
           </div>

           <div class="h-4 w-px bg-border/60"></div>

           <button 
             @click="emit('stop')"
             class="group flex items-center gap-1.5 px-2.5 py-1.5 rounded-full hover:bg-destructive/10 hover:text-destructive text-muted-foreground transition-all duration-200"
             title="Stop Generation"
           >
             <div class="p-0.5 rounded-full bg-destructive/10 group-hover:bg-destructive/20 text-destructive text-[10px]">
                <div class="h-1.5 w-1.5 rounded-[1px] bg-current"></div>
             </div>
             <span class="text-xs font-medium hidden sm:inline-block">Stop</span>
           </button>
        </div>

      </div>
    </div>
  </Transition>
</template>

