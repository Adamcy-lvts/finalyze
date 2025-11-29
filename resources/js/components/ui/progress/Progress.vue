<script setup lang="ts">
import type { ProgressRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { computed } from "vue"
import {
  ProgressIndicator,
  ProgressRoot,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = withDefaults(
  defineProps<ProgressRootProps & { class?: HTMLAttributes["class"] }>(),
  {
    modelValue: 0,
  },
)

const delegatedProps = reactiveOmit(props, "class", "modelValue")

const clampedValue = computed(() => {
  const raw = props.modelValue ?? (props as unknown as { value?: number }).value ?? 0
  const parsed =
    typeof raw === "string" ? parseFloat(raw) : Number.isFinite(raw) ? (raw as number) : Number(raw)

  if (!Number.isFinite(parsed)) {
    return 0
  }

  if (parsed < 0) return 0
  if (parsed > 100) return 100

  return parsed
})
</script>

<template>
  <ProgressRoot
    data-slot="progress"
    v-bind="delegatedProps"
    :model-value="clampedValue"
    :class="
      cn(
        'relative h-2 w-full overflow-hidden rounded-full bg-muted',
        props.class,
      )
    "
  >
    <ProgressIndicator
      data-slot="progress-indicator"
      class="bg-primary h-full w-full flex-1 transition-all"
      :style="{ width: `${clampedValue}%` }"
    />
  </ProgressRoot>
</template>
