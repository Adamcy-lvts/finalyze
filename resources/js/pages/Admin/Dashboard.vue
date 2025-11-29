<template>
  <AdminLayout>
    <template #title>
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold text-slate-900">Admin Dashboard</h2>
        <span class="text-xs text-slate-500">Phase 1 placeholder</span>
      </div>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <Card v-for="card in statCards" :key="card.title" class="border bg-white">
        <CardHeader class="pb-2">
          <CardTitle class="text-xs font-semibold text-slate-500">{{ card.title }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="text-2xl font-semibold text-slate-900">{{ card.value }}</div>
          <div class="text-xs text-slate-500 mt-1">{{ card.sub }}</div>
        </CardContent>
      </Card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <Card class="border bg-white">
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-semibold text-slate-900">Revenue Trend (7d)</CardTitle>
          <span class="text-xs text-slate-500">Coming soon</span>
        </CardHeader>
        <CardContent class="h-48 flex items-center justify-center text-slate-400 text-sm">
          Chart placeholder
        </CardContent>
      </Card>
      <Card class="border bg-white">
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-semibold text-slate-900">User Signups (7d)</CardTitle>
          <span class="text-xs text-slate-500">Coming soon</span>
        </CardHeader>
        <CardContent class="h-48 flex items-center justify-center text-slate-400 text-sm">
          Chart placeholder
        </CardContent>
      </Card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
      <Card class="border bg-white">
        <CardHeader class="flex items-center justify-between pb-2">
          <CardTitle class="text-sm font-semibold text-slate-900">Recent Activity</CardTitle>
          <span class="text-xs text-slate-500">Latest events</span>
        </CardHeader>
        <CardContent>
          <ul class="space-y-2 text-sm text-slate-600">
            <li v-if="recentActivity.length === 0" class="text-slate-400">No data yet</li>
            <li v-for="(item, idx) in recentActivity" :key="idx" class="flex items-center justify-between">
              <span>{{ item.message }}</span>
              <span class="text-xs text-slate-400">{{ item.time }}</span>
            </li>
          </ul>
        </CardContent>
      </Card>
      <Card class="border bg-white">
        <CardHeader class="flex items-center justify-between pb-2">
          <CardTitle class="text-sm font-semibold text-slate-900">System Status</CardTitle>
          <span class="text-xs text-slate-500">Coming soon</span>
        </CardHeader>
        <CardContent>
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
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
}
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

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
const statCards = [
  { title: 'Users', value: formatNumber(props.stats.users.total), sub: `New today: ${formatNumber(props.stats.users.today)}` },
  { title: 'Revenue', value: `₦${formatNumber(props.stats.revenue.total)}`, sub: `Today: ₦${formatNumber(props.stats.revenue.today)}` },
  { title: 'Projects', value: formatNumber(props.stats.projects.total), sub: `New today: ${formatNumber(props.stats.projects.today)}` },
  { title: 'Words Generated', value: formatNumber(props.stats.words.total), sub: `Today: ${formatNumber(props.stats.words.today)}` },
]
</script>
