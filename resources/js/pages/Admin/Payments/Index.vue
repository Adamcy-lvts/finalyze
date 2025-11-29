<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-slate-900">Payments</h2>
    </template>
    <Card class="border bg-white">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <CardTitle class="text-base font-semibold text-slate-900">All Payments</CardTitle>
        <span class="text-xs text-slate-500">Total: {{ payments.data.length }}</span>
      </CardHeader>
      <CardContent class="px-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-14">ID</TableHead>
              <TableHead>User</TableHead>
              <TableHead>Amount (₦)</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Channel</TableHead>
              <TableHead>Paid At</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="p in payments.data" :key="p.id">
              <TableCell class="text-slate-500">{{ p.id }}</TableCell>
              <TableCell class="text-slate-700">{{ p.user?.email ?? '—' }}</TableCell>
              <TableCell class="font-medium text-slate-900">{{ formatAmount(p.amount) }}</TableCell>
              <TableCell>
                <span :class="statusColor(p.status)">{{ p.status }}</span>
              </TableCell>
              <TableCell class="text-slate-600">{{ p.channel ?? '—' }}</TableCell>
              <TableCell class="text-slate-500">{{ formatDate(p.paid_at) }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

type PaymentRow = {
  id: number
  user?: { id: number; email: string; name?: string } | null
  amount: number
  status: string
  channel?: string | null
  paid_at?: string | null
}

const props = defineProps<{
  payments: { data: PaymentRow[] }
}>()

const formatAmount = (amount: number) => amount.toLocaleString()
const formatDate = (date?: string | null) => (date ? new Date(date).toLocaleString() : '—')
const statusColor = (status: string) =>
  status === 'success'
    ? 'text-green-600'
    : status === 'pending'
      ? 'text-amber-600'
      : 'text-red-600'
</script>
