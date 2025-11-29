<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-slate-900">Payments</h2>
    </template>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr class="text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
              <th class="py-2 pr-3">ID</th>
              <th class="py-2 pr-3">User</th>
              <th class="py-2 pr-3">Amount (₦)</th>
              <th class="py-2 pr-3">Status</th>
              <th class="py-2 pr-3">Channel</th>
              <th class="py-2 pr-3">Paid At</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in payments.data" :key="p.id" class="border-b border-slate-100">
              <td class="py-2 pr-3 text-slate-500">{{ p.id }}</td>
              <td class="py-2 pr-3">{{ p.user?.email ?? '—' }}</td>
              <td class="py-2 pr-3">{{ formatAmount(p.amount) }}</td>
              <td class="py-2 pr-3">
                <span :class="statusColor(p.status)">{{ p.status }}</span>
              </td>
              <td class="py-2 pr-3 text-slate-500">{{ p.channel ?? '—' }}</td>
              <td class="py-2 pr-3 text-slate-500">{{ formatDate(p.paid_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'

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
