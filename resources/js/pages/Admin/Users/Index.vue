<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-slate-900">Users</h2>
    </template>
    <Card class="border border-border bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <CardTitle class="text-base font-semibold text-slate-900">All Users</CardTitle>
        <span class="text-xs text-slate-500">Total: {{ users.data.length }}</span>
      </CardHeader>
      <CardContent>
        <DataTable :columns="userColumns" :data="users.data" search-key="email" search-placeholder="Filter by email..." />
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import DataTable from '@/components/Admin/DataTable.vue'
import { userColumns, type UserRow } from '@/components/Admin/users/columns'

const props = defineProps<{
  users: {
    data: UserRow[]
  }
}>()
</script>
