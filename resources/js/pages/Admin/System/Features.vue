<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-foreground">Feature Flags</h2>
    </template>
    <Card class="border border-border bg-card shadow-sm">
      <CardHeader>
        <CardTitle class="text-base font-semibold text-foreground">Flags</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-for="flag in flags" :key="flag.id" class="flex items-start justify-between rounded-lg border border-border/70 bg-muted/30 px-3 py-3">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-foreground">{{ flag.name }}</span>
              <span class="text-xs text-muted-foreground">{{ flag.key }}</span>
            </div>
            <p class="text-sm text-muted-foreground">{{ flag.description || 'No description' }}</p>
          </div>
          <Switch :checked="flag.is_enabled" @update:checked="toggle(flag.id, $event)" />
        </div>
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{
  flags: {
    id: number
    key: string
    name: string
    description: string | null
    is_enabled: boolean
  }[]
}>()

const form = useForm({ is_enabled: false })

const toggle = (id: number, val: boolean) => {
  form.is_enabled = val
  form.put(route('admin.system.update-feature', { flag: id }), { preserveScroll: true, preserveState: false })
}
</script>
