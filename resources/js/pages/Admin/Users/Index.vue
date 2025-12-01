<template>
  <AdminLayout>
    <template #title>
      <div class="flex flex-col">
        <h2 class="text-2xl font-bold tracking-tight text-foreground">Users</h2>
        <p class="text-muted-foreground text-sm">Manage your platform's user base.</p>
      </div>
    </template>

    <div class="grid gap-4 md:grid-cols-3 mb-8">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Users</CardTitle>
          <Users class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ users.data.length }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Active Users</CardTitle>
          <UserCheck class="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ activeUsersCount }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Banned Users</CardTitle>
          <UserX class="h-4 w-4 text-rose-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ bannedUsersCount }}</div>
        </CardContent>
      </Card>
    </div>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base font-semibold text-foreground">All Users</CardTitle>
          <CardDescription>A list of all registered users including their name, email, and status.</CardDescription>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="userColumns" :data="users.data" search-key="email"
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
import { userColumns, type UserRow } from '@/components/Admin/users/columns'
import { Users, UserCheck, UserX } from 'lucide-vue-next'

const props = defineProps<{
  users: {
    data: UserRow[]
  }
}>()

const activeUsersCount = computed(() => props.users.data.filter(u => !u.is_banned).length)
const bannedUsersCount = computed(() => props.users.data.filter(u => u.is_banned).length)
</script>
