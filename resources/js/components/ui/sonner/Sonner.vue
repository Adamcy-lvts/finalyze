<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { Toaster as Sonner } from "vue-sonner"

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    class="toaster group"
    v-bind="props"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      'z-index': '9999',
    }"
  />
</template>

<style>
/* Clean, modern toast styling */
[data-sonner-toaster] {
  z-index: 999999 !important;
  position: fixed !important;
  top: 24px !important;
  right: 24px !important;
  pointer-events: auto !important;
  max-width: 300px !important;
  width: auto !important;
}

[data-sonner-toast] {
  background: hsl(var(--popover)) !important;
  color: hsl(var(--popover-foreground)) !important;
  border: 1px solid hsl(var(--border)) !important;
  border-radius: 6px !important;
  padding: 12px 16px !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  min-width: 120px !important;
  backdrop-filter: blur(8px) !important;
}

/* Success toast - subtle green accent */
[data-sonner-toast][data-type="success"] {
  border-left: 3px solid #10b981 !important;
}

/* Remove close button for cleaner look */
[data-sonner-toast] [data-close-button] {
  display: none !important;
}

/* Center text */
[data-sonner-toast] [data-title] {
  text-align: center !important;
  margin: 0 !important;
  font-weight: 500 !important;
}
</style>
