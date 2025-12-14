<template>
  <AdminLayout title="Revenue">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <div v-for="(val, key) in summary" :key="key" class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ labels[key] }}</div>
        <div class="mt-1 text-2xl font-semibold text-slate-900">₦{{ formatAmount(val) }}</div>
      </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
      Charts coming soon.
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'

const props = defineProps<{
  summary: { today: number; week: number; month: number; all_time: number }
}>()

const labels: Record<string, string> = {
  today: 'Today',
  week: 'This Week',
  month: 'This Month',
  all_time: 'All Time',
}

const formatAmount = (amount: number) => amount.toLocaleString()
</script>
