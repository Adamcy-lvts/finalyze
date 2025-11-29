<template>
  <AdminLayout>
    <template #title>
      <h2 class="text-lg font-semibold text-foreground">Project Detail</h2>
    </template>
    <div class="grid gap-4 lg:grid-cols-3">
      <Card class="lg:col-span-2 border border-border bg-card shadow-sm">
        <CardHeader>
          <CardTitle class="text-base font-semibold text-foreground">{{ project.title }}</CardTitle>
          <p class="text-sm text-muted-foreground">Owner: {{ project.user?.email ?? '—' }}</p>
        </CardHeader>
        <CardContent class="text-sm space-y-2 text-muted-foreground">
          <div class="flex flex-wrap gap-4">
            <span class="font-semibold text-foreground">Status:</span> <span>{{ project.status }}</span>
            <span class="font-semibold text-foreground">Topic:</span> <span>{{ project.topic_status }}</span>
            <span class="font-semibold text-foreground">Mode:</span> <span>{{ project.mode ?? '—' }}</span>
            <span class="font-semibold text-foreground">Type:</span> <span>{{ project.type ?? '—' }}</span>
          </div>
          <div class="flex flex-wrap gap-4">
            <span class="font-semibold text-foreground">Field:</span> <span>{{ project.field_of_study ?? '—' }}</span>
            <span class="font-semibold text-foreground">University:</span> <span>{{ project.university ?? '—' }}</span>
            <span class="font-semibold text-foreground">Course:</span> <span>{{ project.course ?? '—' }}</span>
          </div>
          <div class="text-xs text-muted-foreground">
            Created: {{ formatDate(project.created_at) }} · Updated: {{ formatDate(project.updated_at) }}
          </div>
        </CardContent>
      </Card>

      <Card class="border border-border bg-card shadow-sm">
        <CardHeader>
          <CardTitle class="text-base font-semibold text-foreground">Chapters</CardTitle>
        </CardHeader>
        <CardContent class="text-sm text-muted-foreground">
          <ul class="space-y-2">
            <li v-if="project.chapters.length === 0" class="text-muted-foreground">No chapters yet.</li>
            <li v-for="chapter in project.chapters" :key="chapter.id" class="flex items-center justify-between">
              <div>
                <div class="font-semibold text-foreground">Chapter {{ chapter.chapter_number }}: {{ chapter.title }}</div>
                <div class="text-xs text-muted-foreground">
                  Status: {{ chapter.status }} · {{ chapter.word_count }}/{{ chapter.target_word_count }} words
                </div>
              </div>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

const props = defineProps<{
  project: {
    id: number
    title: string
    status: string
    topic_status: string
    mode: string | null
    type: string | null
    field_of_study?: string | null
    university?: string | null
    course?: string | null
    user?: { id: number; email?: string | null; name?: string | null } | null
    chapters: {
      id: number
      chapter_number: number
      title: string
      status: string
      word_count: number
      target_word_count: number
    }[]
    created_at: string
    updated_at: string
  }
}>()

const formatDate = (d: string) => new Date(d).toLocaleString()
</script>
