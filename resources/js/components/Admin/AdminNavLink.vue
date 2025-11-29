<template>
  <a
    :href="href"
    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium"
    :class="isActive ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
  >
    <span class="w-4 h-4 flex items-center justify-center text-xs text-slate-500">{{ icon }}</span>
    <span>{{ label }}</span>
  </a>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps<{
  href: string
  label: string
  icon?: string
}>()

const page = usePage()

const isActive = computed(() => page.url.startsWith(props.href))
</script>
