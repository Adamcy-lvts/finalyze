<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-slate-900">Users</h2>
    </template>
    <Card class="border bg-white">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <CardTitle class="text-base font-semibold text-slate-900">All Users</CardTitle>
        <span class="text-xs text-slate-500">Total: {{ users.data.length }}</span>
      </CardHeader>
      <CardContent class="px-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-12">ID</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Projects</TableHead>
              <TableHead>Payments</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Joined</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="user in users.data" :key="user.id">
              <TableCell class="text-slate-500">{{ user.id }}</TableCell>
              <TableCell class="font-medium text-slate-900">{{ user.name }}</TableCell>
              <TableCell class="text-slate-600">{{ user.email }}</TableCell>
              <TableCell>{{ user.projects_count }}</TableCell>
              <TableCell>{{ user.payments_count }}</TableCell>
              <TableCell>
                <span :class="user.is_banned ? 'text-red-600' : 'text-green-600'">
                  {{ user.is_banned ? 'Banned' : 'Active' }}
                </span>
              </TableCell>
              <TableCell class="text-slate-500">{{ formatDate(user.created_at) }}</TableCell>
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
