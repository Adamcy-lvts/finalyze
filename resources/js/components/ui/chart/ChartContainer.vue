<script setup lang="ts">
import type { CSSProperties, HTMLAttributes } from "vue"
import { computed } from "vue"
import { cn } from "@/lib/utils"
import type { ChartConfig } from "./types"

const props = defineProps<{
  config?: ChartConfig
  class?: HTMLAttributes["class"]
}>()

const styleVars = computed(() => {
  const entries = Object.values(props.config ?? {})
  const vars: Record<string, string> = {}
  entries.forEach((entry, index) => {
    const color = entry.theme?.light ?? entry.color
    const darkColor = entry.theme?.dark ?? color
    vars[`--chart-${index + 1}`] = color ?? "hsl(var(--primary))"
    vars[`--chart-${index + 1}-dark`] = darkColor ?? vars[`--chart-${index + 1}`]
  })
  return vars as CSSProperties
})
</script>

<template>
  <div
    :style="styleVars"
    :class="cn('rounded-xl border bg-card text-card-foreground shadow-sm p-4', props.class)"
  >
    <slot />
  </div>
</template>
