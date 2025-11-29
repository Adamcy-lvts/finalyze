<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-slate-900">Users</h2>
    </template>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr class="text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
              <th class="py-2 pr-3">ID</th>
              <th class="py-2 pr-3">Name</th>
              <th class="py-2 pr-3">Email</th>
              <th class="py-2 pr-3">Projects</th>
              <th class="py-2 pr-3">Payments</th>
              <th class="py-2 pr-3">Banned</th>
              <th class="py-2 pr-3">Joined</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users.data" :key="user.id" class="border-b border-slate-100">
              <td class="py-2 pr-3 text-slate-500">{{ user.id }}</td>
              <td class="py-2 pr-3">{{ user.name }}</td>
              <td class="py-2 pr-3">{{ user.email }}</td>
              <td class="py-2 pr-3">{{ user.projects_count }}</td>
              <td class="py-2 pr-3">{{ user.payments_count }}</td>
              <td class="py-2 pr-3">
                <span :class="user.is_banned ? 'text-red-600' : 'text-green-600'">
                  {{ user.is_banned ? 'Yes' : 'No' }}
                </span>
              </td>
              <td class="py-2 pr-3 text-slate-500">{{ formatDate(user.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import type { PageProps } from '@inertiajs/core'
import AdminLayout from '@/layouts/AdminLayout.vue'

type UserRow = {
  id: number
  name: string
  email: string
  projects_count: number
  payments_count: number
  is_banned: boolean
  created_at: string
}

const props = defineProps<{
  users: {
    data: UserRow[]
  }
}>()

const formatDate = (dateStr: string) => new Date(dateStr).toLocaleDateString()
</script>
