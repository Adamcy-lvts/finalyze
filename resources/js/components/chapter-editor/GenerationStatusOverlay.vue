<script setup lang="ts">
import { Brain } from 'lucide-vue-next';

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
  <Teleport to="body">
    <Transition enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-4" enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-4">
      <div v-if="props.isGenerating"
        class="fixed top-2 left-0 right-0 z-50 px-3 sm:top-8 sm:px-4 flex justify-center pointer-events-none">
        <div class="w-full max-w-md pointer-events-auto">
          <div
            class="group relative overflow-hidden rounded-2xl border border-white/20 bg-white/80 dark:bg-zinc-900/80 p-5 shadow-2xl backdrop-blur-xl transition-all duration-300 dark:border-white/10 ring-1 ring-black/5 dark:ring-white/5">

            <!-- Animated Background Glow -->
            <div class="absolute -top-24 -right-24 h-48 w-48 rounded-full bg-blue-500/20 blur-3xl animate-pulse">
            </div>
            <div
              class="absolute -bottom-24 -left-24 h-48 w-48 rounded-full bg-purple-500/20 blur-3xl animate-pulse delay-1000">
            </div>

            <div class="relative flex items-center gap-4">
              <!-- Icon & Spinner -->
              <div class="relative flex-shrink-0">
                <!-- Outer Ring Spinner -->
                <div
                  class="absolute inset-0 -m-1.5 rounded-full border-2 border-transparent border-t-blue-500 border-r-purple-500 animate-spin [animation-duration:2s]">
                </div>

                <!-- Icon Container -->
                <div
                  class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center shadow-lg shadow-blue-500/20 z-10 relative">
                  <Brain class="h-5 w-5 text-white animate-pulse" />
                </div>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-2">
                  <div class="flex flex-col">
                    <h4 class="text-sm font-bold text-foreground tracking-tight flex items-center gap-2">
                      Writing Chapter
                      <span
                        class="flex h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></span>
                    </h4>
                  </div>
                  <span
                    class="text-xs font-mono font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-md border border-primary/20">
                    {{ Math.round(props.generationPercentage) }}%
                  </span>
                </div>

                <!-- Progress Bar -->
                <div class="h-1.5 w-full bg-zinc-200 dark:bg-zinc-800 rounded-full overflow-hidden backdrop-blur-sm">
                  <div
                    class="h-full bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 transition-all duration-300 ease-out shadow-[0_0_10px_rgba(59,130,246,0.5)] relative"
                    :style="{ width: `${props.generationPercentage}%` }">
                    <div class="absolute inset-0 bg-white/30 w-full animate-shimmer -skew-x-12 translate-x-[-100%]">
                    </div>
                  </div>
                </div>

                <!-- Detailed Status -->
                <div class="mt-2.5 flex items-center justify-between">
                  <p class="text-[10px] font-medium text-muted-foreground truncate max-w-[200px] flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-blue-500"></span>
                    {{ props.generationProgress || 'Initializing writer...' }}
                  </p>

                  <!-- Stop Button (Integrated) -->
                  <button @click="emit('stop')"
                    class="text-[10px] font-semibold text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 transition-colors uppercase tracking-wider flex items-center gap-1 hover:bg-red-50 dark:hover:bg-red-900/10 px-2 py-0.5 rounded-full">
                    Stop
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
