<template>
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-xs uppercase tracking-wide text-slate-500">{{ title }}</div>
    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ value }}</div>
    <div class="mt-1 text-xs text-slate-500">{{ sub }}</div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  value: string | number
  sub?: string
}>()
</script>
