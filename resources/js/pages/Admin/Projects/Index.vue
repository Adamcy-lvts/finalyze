<template>
  <AdminLayout>
    <template #title>
      <div class="flex flex-col">
        <h2 class="text-2xl font-bold tracking-tight text-foreground">Projects</h2>
        <p class="text-muted-foreground text-sm">Manage and monitor all user projects.</p>
      </div>
    </template>

    <div class="grid gap-4 md:grid-cols-3 mb-8">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Projects</CardTitle>
          <Folder class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ projects.data.length }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Completed</CardTitle>
          <CheckCircle2 class="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ completedProjectsCount }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">In Progress</CardTitle>
          <Clock class="h-4 w-4 text-amber-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ inProgressProjectsCount }}</div>
        </CardContent>
      </Card>
    </div>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base font-semibold text-foreground">All Projects</CardTitle>
          <CardDescription>A comprehensive list of projects created by users.</CardDescription>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="projectColumns" :data="projects.data" search-key="title"
          search-placeholder="Filter by title..." />
      </CardContent>
    </Card>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import DataTable from '@/components/Admin/DataTable.vue'
import { projectColumns, type ProjectRow } from '@/components/Admin/projects/columns'
import { Folder, CheckCircle2, Clock } from 'lucide-vue-next'

const props = defineProps<{
  projects: { data: ProjectRow[] }
}>()

const completedProjectsCount = computed(() => props.projects.data.filter(p => p.status === 'completed').length)
const inProgressProjectsCount = computed(() => props.projects.data.filter(p => p.status !== 'completed').length)
</script>
