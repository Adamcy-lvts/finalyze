<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-foreground">Projects</h2>
    </template>
    <Card class="border border-border bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <CardTitle class="text-base font-semibold text-foreground">All Projects</CardTitle>
        <span class="text-xs text-muted-foreground">Total: {{ projects.data.length }}</span>
      </CardHeader>
      <CardContent>
        <DataTable :columns="projectColumns" :data="projects.data" search-key="title" search-placeholder="Filter by title..." />
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import DataTable from '@/components/Admin/DataTable.vue'
import { projectColumns, type ProjectRow } from '@/components/Admin/projects/columns'

const props = defineProps<{
  projects: { data: ProjectRow[] }
}>()
</script>
