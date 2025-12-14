<template>
  <AdminLayout title="Payments" subtitle="Monitor revenue and transaction history.">

    <div class="grid gap-4 md:grid-cols-3 mb-8">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Revenue</CardTitle>
          <DollarSign class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">₦{{ totalRevenue.toLocaleString() }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Successful Transactions</CardTitle>
          <CheckCircle2 class="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ successfulPaymentsCount }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Pending/Failed</CardTitle>
          <AlertCircle class="h-4 w-4 text-rose-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ otherPaymentsCount }}</div>
        </CardContent>
      </Card>
    </div>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base font-semibold text-foreground">All Payments</CardTitle>
          <CardDescription>A detailed log of all transactions.</CardDescription>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="paymentColumns" :data="payments.data" search-key="user_email"
          search-placeholder="Filter by email..." />
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import DataTable from '@/components/Admin/DataTable.vue'
import { paymentColumns, type PaymentRow } from '@/components/Admin/payments/columns'
import { DollarSign, CheckCircle2, AlertCircle } from 'lucide-vue-next'

const props = defineProps<{
  payments: { data: PaymentRow[] }
}>()

const totalRevenue = computed(() => props.payments.data.reduce((acc, curr) => acc + Number(curr.amount), 0))
const successfulPaymentsCount = computed(() => props.payments.data.filter(p => p.status === 'success').length)
const otherPaymentsCount = computed(() => props.payments.data.filter(p => p.status !== 'success').length)
</script>
