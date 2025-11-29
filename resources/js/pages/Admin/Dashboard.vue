<template>
  <AdminLayout>
    <template #title>
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold text-slate-900">Admin Dashboard</h2>
        <span class="text-xs text-slate-500">Phase 1 placeholder</span>
      </div>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <StatsCard title="Users" value="—" sub="Total users" />
      <StatsCard title="Revenue" value="—" sub="All-time (₦)" />
      <StatsCard title="Projects" value="—" sub="Total projects" />
      <StatsCard title="Words Generated" value="—" sub="All-time" />
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
          <li class="text-slate-400">No data yet</li>
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
</script>
