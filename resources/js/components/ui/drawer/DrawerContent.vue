<script lang="ts" setup>
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { useForwardPropsEmits } from "reka-ui"
import { DrawerContent, DrawerPortal } from "vaul-vue"
import { cn } from "@/lib/utils"
import DrawerOverlay from "./DrawerOverlay.vue"

const props = withDefaults(defineProps<DialogContentProps & {
  class?: HTMLAttributes["class"]
  side?: "bottom" | "right"
}>(), {
  side: "bottom",
})
const emits = defineEmits<DialogContentEmits>()

const forwarded = useForwardPropsEmits(props, emits)

const baseClass = computed(() => {
  if (props.side === "right") {
    return "fixed inset-y-0 right-0 z-50 flex h-full w-full max-w-md rounded-none border-l bg-background shadow-lg"
  }
  return "fixed inset-x-0 bottom-0 z-50 mt-24 flex h-auto flex-col rounded-t-[10px] border bg-background"
})
</script>

<template>
  <DrawerPortal>
    <DrawerOverlay />
    <DrawerContent
      v-bind="forwarded"
      :class="cn(baseClass.value, props.class)"
    >
      <div v-if="props.side === 'bottom'" class="mx-auto mt-4 h-2 w-[100px] rounded-full bg-muted" />
      <slot />
    </DrawerContent>
  </DrawerPortal>
</template>
