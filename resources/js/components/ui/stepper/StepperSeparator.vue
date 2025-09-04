<!-- resources/js/components/ui/stepper/StepperSeparator.vue -->
<script lang="ts" setup>
import type { StepperSeparatorProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { StepperSeparator, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<StepperSeparatorProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <StepperSeparator v-bind="forwarded" :class="cn(
    // Reset positioning and ensure it's contained
    'h-0.5 bg-muted relative',
    // Make sure it stretches properly
    'w-full flex-1',
    // Disabled state
    'group-data-[disabled]:bg-muted group-data-[disabled]:opacity-50',
    // Completed state - should be primary color
    'group-data-[state=completed]:bg-primary',
    // Remove any margin that might be causing issues
    'my-0',
    props.class,
  )" />
</template>