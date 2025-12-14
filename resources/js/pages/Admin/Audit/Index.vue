<template>
  <AdminLayout title="Audit Logs">
    <Card class="border border-border bg-card shadow-sm">
      <CardHeader>
        <CardTitle class="text-base font-semibold text-foreground">Recent Activity</CardTitle>
      </CardHeader>
      <CardContent>
        <DataTable :columns="columns" :data="logs.data" />
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import DataTable from '@/components/Admin/DataTable.vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'

type LogRow = {
  id: number
  admin?: { id: number; email?: string; name?: string } | null
  action: string
  description: string
  model_type: string | null
  model_id: number | null
  ip_address: string | null
  created_at: string
}

const props = defineProps<{
  logs: { data: LogRow[] }
}>()

const columns: ColumnDef<LogRow>[] = [
  { accessorKey: 'id', header: 'ID', cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.getValue('id')) },
  { accessorKey: 'action', header: 'Action' },
  { accessorKey: 'description', header: 'Description' },
  {
    accessorKey: 'admin',
    header: 'Admin',
    cell: ({ row }) => h('div', { class: 'text-sm' }, row.original.admin?.email ?? '—'),
  },
  {
    accessorKey: 'created_at',
    header: 'Date',
    cell: ({ row }) => h('div', { class: 'text-xs text-muted-foreground' }, new Date(row.getValue('created_at')).toLocaleString()),
  },
]
</script>
