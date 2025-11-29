<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-slate-900">Payments</h2>
    </template>
    <Card class="border border-border bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <CardTitle class="text-base font-semibold text-slate-900">All Payments</CardTitle>
        <span class="text-xs text-slate-500">Total: {{ payments.data.length }}</span>
      </CardHeader>
      <CardContent>
        <DataTable :columns="paymentColumns" :data="payments.data" search-key="user_email" search-placeholder="Filter by email..." />
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import DataTable from '@/components/Admin/DataTable.vue'
import { paymentColumns, type PaymentRow } from '@/components/Admin/payments/columns'

const props = defineProps<{
  payments: { data: PaymentRow[] }
}>()
</script>
