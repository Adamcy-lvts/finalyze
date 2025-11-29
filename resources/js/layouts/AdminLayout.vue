<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 flex">
    <aside class="w-64 bg-white border-r border-slate-200 flex flex-col">
      <div class="px-4 py-5 border-b border-slate-200">
        <h1 class="text-lg font-semibold text-slate-900">Admin</h1>
        <p class="text-xs text-slate-500">Project Companion</p>
      </div>
      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <AdminNavLink href="/admin" label="Dashboard" icon="home" />
        <AdminNavLink href="/admin/users" label="Users" icon="users" />
        <AdminNavLink href="/admin/payments" label="Payments" icon="credit-card" />
        <AdminNavLink href="/admin/analytics" label="Analytics" icon="chart-bar" />
        <AdminNavLink href="/admin/projects" label="Projects" icon="folder" />
        <AdminNavLink href="/admin/ai" label="AI Monitoring" icon="cpu" />
        <AdminNavLink href="/admin/system/features" label="Feature Flags" icon="toggle" />
        <AdminNavLink href="/admin/system/settings" label="Settings" icon="settings" />
        <AdminNavLink href="/admin/audit" label="Audit Logs" icon="list" />
        <AdminNavLink href="/admin/notifications" label="Notifications" icon="bell" />
      </nav>
      <div class="px-4 py-3 border-t border-slate-200 text-xs text-slate-500">
        <div class="flex items-center gap-2">
          <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
          <span>Online</span>
        </div>
      </div>
    </aside>

    <div class="flex-1 flex flex-col">
      <header class="h-14 border-b border-slate-200 bg-white flex items-center justify-between px-4">
        <div class="flex items-center gap-3">
          <slot name="title">
            <h2 class="text-base font-semibold text-slate-900">Admin</h2>
          </slot>
        </div>
        <div class="flex items-center gap-3 text-sm text-slate-600">
          <span>{{ user?.name }}</span>
          <span class="text-slate-400">•</span>
          <a href="/dashboard" class="text-indigo-600 hover:text-indigo-700 text-xs">Back to app</a>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminNavLink from '../components/Admin/AdminNavLink.vue'

const page = usePage()
const user = computed(() => page.props.auth?.user)
</script>
