<script setup lang="ts">
import { Sparkles, RefreshCw, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

defineProps<{
  show: boolean
  isGenerating: boolean
}>()

const emit = defineEmits<{
  regenerate: []
  dismiss: []
}>()
</script>

<template>
  <Transition name="slide-up">
    <div v-if="show" class="fixed bottom-20 left-1/2 z-50 -translate-x-1/2 px-3">
      <div class="flex items-center gap-3 rounded-full border bg-background/95 px-4 py-2 shadow-lg backdrop-blur">
        <Sparkles class="h-4 w-4 shrink-0 text-primary" />
        <span class="text-sm">AI suggests an opening</span>
        <kbd class="rounded bg-muted px-1.5 py-0.5 text-xs">Tab</kbd>
        <span class="text-xs text-muted-foreground">to accept</span>

        <div class="ml-1 flex items-center gap-1">
          <Button
            variant="ghost"
            size="icon"
            class="h-6 w-6"
            :disabled="isGenerating"
            title="Regenerate"
            @click="emit('regenerate')"
          >
            <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': isGenerating }" />
          </Button>
          <Button variant="ghost" size="icon" class="h-6 w-6" title="Dismiss" @click="emit('dismiss')">
            <X class="h-3 w-3" />
          </Button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 180ms ease, opacity 180ms ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
  transform: translate(-50%, 8px);
  opacity: 0;
}

.slide-up-enter-to,
.slide-up-leave-from {
  transform: translate(-50%, 0);
  opacity: 1;
}
</style>

