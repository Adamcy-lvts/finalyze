<template>
  <AdminLayout title="System Settings">
    <Card class="border border-border bg-card shadow-sm">
      <CardHeader>
        <CardTitle class="text-base font-semibold text-foreground">Settings</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-for="setting in editableSettings" :key="setting.key" class="space-y-1">
          <div class="flex items-center justify-between text-xs text-muted-foreground">
            <span class="font-semibold text-foreground">{{ setting.key }}</span>
            <span class="text-muted-foreground">{{ setting.group }}</span>
          </div>
          <Textarea
            v-if="shouldUseTextarea(setting.key, setting.value)"
            v-model="setting.value"
            :rows="textareaRows(setting.value)"
            class="font-mono text-sm"
          />
          <Input v-else v-model="setting.value" />
          <p class="text-xs text-muted-foreground">{{ setting.description || 'No description' }}</p>
        </div>
        <div class="flex justify-end gap-2">
          <Button @click="save" :disabled="form.processing">Save</Button>
        </div>
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'
import { useForm } from '@inertiajs/vue3'
import { reactive } from 'vue'

const props = defineProps<{
  settings: {
    key: string
    value: any
    type: string
    group: string
    description: string | null
  }[]
}>()

const editableSettings = reactive(
  props.settings.map((s) => ({
    key: s.key,
    value: typeof s.value === 'object' ? JSON.stringify(s.value) : s.value,
    group: s.group,
    description: s.description,
  })),
)

const form = useForm({
  settings: editableSettings,
})

const shouldUseTextarea = (key: string, value: unknown) => {
  const k = String(key ?? '')
  const v = typeof value === 'string' ? value : JSON.stringify(value ?? '')

  if (k.startsWith('ai.') || k.includes('prompt')) return true
  if (v.includes('\n')) return true
  if (v.length > 120) return true

  return false
}

const textareaRows = (value: unknown) => {
  const v = typeof value === 'string' ? value : JSON.stringify(value ?? '')
  const lines = v.split('\n').length
  return Math.min(18, Math.max(4, lines))
}

const save = () => {
  form.put(route('admin.system.update-settings'), { preserveScroll: true })
}
</script>
