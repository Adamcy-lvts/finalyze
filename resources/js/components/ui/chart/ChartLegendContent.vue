<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"
import type { ChartConfig } from "./types"

const props = defineProps<{
  config: ChartConfig
  class?: HTMLAttributes["class"]
  nameKey?: string
}>()
</script>

<template>
  <div :class="cn('flex flex-wrap gap-4 text-sm text-muted-foreground', props.class)">
    <div
      v-for="(entry, key, index) in props.config"
      :key="key as string"
      class="flex items-center gap-2"
    >
      <span
        class="h-3 w-3 rounded-full"
        :style="{ backgroundColor: entry.color ?? `var(--chart-${index + 1})` }"
      />
      <span class="font-medium text-foreground">
        {{ (entry as any).label ?? props.nameKey ?? key }}
      </span>
    </div>
  </div>
</template>
