<template>
  <AdminLayout>
    <template #title>
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold text-slate-900">Admin Dashboard</h2>
        <span class="text-xs text-slate-500">Phase 1 placeholder</span>
      </div>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <StatsCard title="Users" :value="formatNumber(stats.users.total)" :sub="`New today: ${formatNumber(stats.users.today)}`" />
      <StatsCard title="Revenue" :value="`₦${formatNumber(stats.revenue.total)}`" :sub="`Today: ₦${formatNumber(stats.revenue.today)}`" />
      <StatsCard title="Projects" :value="formatNumber(stats.projects.total)" :sub="`New today: ${formatNumber(stats.projects.today)}`" />
      <StatsCard title="Words Generated" :value="formatNumber(stats.words.total)" :sub="`Today: ${formatNumber(stats.words.today)}`" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-slate-900">Revenue Trend (7d)</h3>
          <span class="text-xs text-slate-500">Coming soon</span>
        </div>
        <div class="h-48 flex items-center justify-center text-slate-400 text-sm">Chart placeholder</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-slate-900">User Signups (7d)</h3>
          <span class="text-xs text-slate-500">Coming soon</span>
        </div>
        <div class="h-48 flex items-center justify-center text-slate-400 text-sm">Chart placeholder</div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-slate-900">Recent Activity</h3>
          <span class="text-xs text-slate-500">Coming soon</span>
        </div>
        <ul class="space-y-2 text-sm text-slate-600">
          <li v-if="recentActivity.length === 0" class="text-slate-400">No data yet</li>
          <li v-for="(item, idx) in recentActivity" :key="idx" class="flex items-center justify-between">
            <span>{{ item.message }}</span>
            <span class="text-xs text-slate-400">{{ item.time }}</span>
          </li>
        </ul>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-slate-900">System Status</h3>
          <span class="text-xs text-slate-500">Coming soon</span>
        </div>
        <ul class="space-y-2 text-sm text-slate-600">
          <li class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-green-500"></span> Queue: OK
          </li>
          <li class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-green-500"></span> OpenAI: OK
          </li>
          <li class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-green-500"></span> Cache: OK
          </li>
        </ul>
      </div>
    </div>
  </AdminLayout>
}
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatsCard from '@/components/Admin/StatsCard.vue'

const props = defineProps<{
  stats: {
    users: { total: number; today: number }
    revenue: { total: number; today: number }
    projects: { total: number; today: number }
    words: { total: number; today: number }
  }
  recentActivity: { message: string; time: string }[]
}>()

const formatNumber = (val: number) => Number(val ?? 0).toLocaleString()
</script>
