<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-slate-900">Payment Detail</h2>
    </template>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
      Payment ID: {{ paymentId }}<br />
      Detail view coming soon.
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { defineProps } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'

defineProps<{ paymentId: number }>()
</script>
