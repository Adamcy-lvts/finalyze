<template>
  <AdminLayout title="Notifications">
    <Card class="border border-border bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <CardTitle class="text-base font-semibold text-foreground">Recent Notifications</CardTitle>
        <form :action="route('admin.notifications.read-all')" method="post">
          <Button size="sm" variant="outline">Mark all read</Button>
        </form>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="notifications.length === 0" class="text-sm text-muted-foreground">No notifications yet.</div>
        <div v-for="note in notifications" :key="note.id" class="rounded-lg border border-border/70 bg-muted/30 p-3">
          <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-foreground">{{ note.title }}</div>
            <span class="text-xs text-muted-foreground">{{ formatDate(note.created_at) }}</span>
          </div>
          <div class="text-sm text-muted-foreground mt-1">{{ note.message }}</div>
          <div class="text-xs text-muted-foreground mt-1">Type: {{ note.type }} · Severity: {{ note.severity }}</div>
          <form v-if="!note.is_read" :action="route('admin.notifications.read', { notification: note.id })" method="post" class="mt-2">
            <Button size="xs" variant="outline">Mark read</Button>
          </form>
        </div>
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'

const props = defineProps<{
  notifications: {
    id: number
    type: string
    title: string
    message: string
    severity: string
    is_read: boolean
    created_at: string
  }[]
}>()

const formatDate = (d: string) => new Date(d).toLocaleString()
</script>
