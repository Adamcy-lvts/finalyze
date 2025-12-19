<template>
  <AdminLayout :title="structureTitle" subtitle="Edit chapters and sections that drive project chapter structure.">
    <Alert v-if="flash?.success" class="mb-6 border border-emerald-500/40 bg-emerald-500/10">
      <Sparkles class="h-4 w-4 text-emerald-500" />
      <AlertTitle>Updated</AlertTitle>
      <AlertDescription>{{ flash.success }}</AlertDescription>
    </Alert>

    <div class="mb-4 flex items-center justify-between gap-3">
      <div class="text-sm text-muted-foreground">
        <Link :href="route('admin.system.faculty-structures')"
          class="text-primary hover:text-primary/80 font-medium transition-colors">
          ← Back to structures
        </Link>
      </div>
      <Button size="sm" @click="openCreateChapter">
        <Plus class="mr-2 h-4 w-4" />
        Add Chapter
      </Button>
    </div>

    <Card class="border border-border/50 bg-card shadow-sm mb-6">
      <CardHeader>
        <CardTitle class="text-base font-semibold text-foreground">Structure</CardTitle>
        <CardDescription>
          {{ structure.faculty_name }} ({{ structure.faculty_slug }}) · Active: {{ structure.is_active ? 'yes' : 'no' }}
        </CardDescription>
      </CardHeader>
    </Card>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader>
        <CardTitle class="text-base font-semibold text-foreground">Chapters</CardTitle>
        <CardDescription>Chapters + sections for this structure.</CardDescription>
      </CardHeader>
      <CardContent>
        <div v-if="!chapters.length" class="text-sm text-muted-foreground">
          No chapters yet. Click “Add Chapter”.
        </div>

        <Accordion v-else type="multiple" class="w-full">
          <AccordionItem v-for="c in chapters" :key="c.id" :value="String(c.id)">
            <AccordionTrigger>
              <div class="flex items-center gap-3">
                <Badge variant="outline">#{{ c.chapter_number }}</Badge>
                <span class="font-medium">{{ c.chapter_title }}</span>
                <span class="text-xs text-muted-foreground">
                  {{ c.academic_level }} · {{ c.project_type }} · {{ c.target_word_count }} words
                </span>
                <Badge v-if="!c.is_required" variant="secondary">optional</Badge>
              </div>
            </AccordionTrigger>
            <AccordionContent class="space-y-4">
              <div class="flex items-center justify-between gap-2">
                <div class="text-xs text-muted-foreground">
                  Completion threshold: {{ c.completion_threshold }}% · Sort: {{ c.sort_order }}
                </div>
                <div class="flex items-center gap-2">
                  <Button variant="outline" size="sm" @click="openEditChapter(c)">Edit</Button>
                  <Button variant="destructive" size="sm" @click="deleteChapter(c)">Delete</Button>
                </div>
              </div>

              <div v-if="c.description" class="text-sm text-muted-foreground whitespace-pre-wrap">
                {{ c.description }}
              </div>

              <div class="flex items-center justify-between">
                <div class="text-sm font-semibold">Sections</div>
                <Button size="sm" variant="outline" @click="openCreateSection(c)">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Section
                </Button>
              </div>

              <div v-if="!c.sections?.length" class="text-sm text-muted-foreground">
                No sections yet.
              </div>

              <div v-else class="space-y-2">
                <div v-for="s in c.sections" :key="s.id"
                  class="flex items-start justify-between gap-3 rounded-md border border-border/60 bg-muted/20 px-3 py-3">
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <Badge variant="outline">{{ s.section_number }}</Badge>
                      <span class="font-medium">{{ s.section_title }}</span>
                      <span class="text-xs text-muted-foreground">{{ s.target_word_count }} words</span>
                      <Badge v-if="!s.is_required" variant="secondary">optional</Badge>
                    </div>
                    <div v-if="s.description" class="text-xs text-muted-foreground whitespace-pre-wrap">
                      {{ s.description }}
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <Button size="sm" variant="outline" @click="openEditSection(c, s)">Edit</Button>
                    <Button size="sm" variant="destructive" @click="deleteSection(c, s)">Delete</Button>
                  </div>
                </div>
              </div>
            </AccordionContent>
          </AccordionItem>
        </Accordion>
      </CardContent>
    </Card>

    <!-- Chapter dialog -->
    <Dialog :open="isChapterDialogOpen" @update:open="isChapterDialogOpen = $event">
      <DialogContent class="sm:max-w-[700px]">
        <DialogHeader>
          <DialogTitle>{{ chapterEditingId ? 'Edit chapter' : 'Create chapter' }}</DialogTitle>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Academic Level</Label>
              <select v-model="chapterForm.academic_level"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                <option v-for="l in academicLevels" :key="l" :value="l">{{ l }}</option>
              </select>
              <p v-if="chapterForm.errors.academic_level" class="text-xs text-rose-500">{{ chapterForm.errors.academic_level }}</p>
            </div>
            <div class="space-y-1">
              <Label>Project Type</Label>
              <select v-model="chapterForm.project_type"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                <option v-for="t in projectTypes" :key="t" :value="t">{{ t }}</option>
              </select>
              <p v-if="chapterForm.errors.project_type" class="text-xs text-rose-500">{{ chapterForm.errors.project_type }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-1">
              <Label>Chapter #</Label>
              <Input v-model="chapterForm.chapter_number" type="number" min="1" max="30" />
              <p v-if="chapterForm.errors.chapter_number" class="text-xs text-rose-500">{{ chapterForm.errors.chapter_number }}</p>
            </div>
            <div class="space-y-1 md:col-span-2">
              <Label>Title</Label>
              <Input v-model="chapterForm.chapter_title" />
              <p v-if="chapterForm.errors.chapter_title" class="text-xs text-rose-500">{{ chapterForm.errors.chapter_title }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label>Description</Label>
            <Textarea v-model="chapterForm.description" rows="3" />
            <p v-if="chapterForm.errors.description" class="text-xs text-rose-500">{{ chapterForm.errors.description }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-1">
              <Label>Target Words</Label>
              <Input v-model="chapterForm.target_word_count" type="number" min="100" />
              <p v-if="chapterForm.errors.target_word_count" class="text-xs text-rose-500">{{ chapterForm.errors.target_word_count }}</p>
            </div>
            <div class="space-y-1">
              <Label>Completion %</Label>
              <Input v-model="chapterForm.completion_threshold" type="number" min="1" max="100" />
              <p v-if="chapterForm.errors.completion_threshold" class="text-xs text-rose-500">{{ chapterForm.errors.completion_threshold }}</p>
            </div>
            <div class="space-y-1">
              <Label>Sort Order</Label>
              <Input v-model="chapterForm.sort_order" type="number" min="0" max="1000" />
              <p v-if="chapterForm.errors.sort_order" class="text-xs text-rose-500">{{ chapterForm.errors.sort_order }}</p>
            </div>
          </div>

          <label class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
            <span class="text-foreground">Required</span>
            <Switch :checked="chapterForm.is_required" @update:checked="val => chapterForm.is_required = val" />
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isChapterDialogOpen = false">Cancel</Button>
          <Button :disabled="chapterForm.processing" @click="saveChapter">
            <Loader2 v-if="chapterForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Save
          </Button>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Section dialog -->
    <Dialog :open="isSectionDialogOpen" @update:open="isSectionDialogOpen = $event">
      <DialogContent class="sm:max-w-[700px]">
        <DialogHeader>
          <DialogTitle>{{ sectionEditingId ? 'Edit section' : 'Create section' }}</DialogTitle>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-1">
              <Label>Section #</Label>
              <Input v-model="sectionForm.section_number" placeholder="1.1" />
              <p v-if="sectionForm.errors.section_number" class="text-xs text-rose-500">{{ sectionForm.errors.section_number }}</p>
            </div>
            <div class="space-y-1 md:col-span-2">
              <Label>Title</Label>
              <Input v-model="sectionForm.section_title" />
              <p v-if="sectionForm.errors.section_title" class="text-xs text-rose-500">{{ sectionForm.errors.section_title }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label>Description</Label>
            <Textarea v-model="sectionForm.description" rows="3" />
            <p v-if="sectionForm.errors.description" class="text-xs text-rose-500">{{ sectionForm.errors.description }}</p>
          </div>

          <div class="space-y-1">
            <Label>Writing Guidance</Label>
            <Textarea v-model="sectionForm.writing_guidance" rows="5" />
            <p v-if="sectionForm.errors.writing_guidance" class="text-xs text-rose-500">{{ sectionForm.errors.writing_guidance }}</p>
          </div>

          <div class="space-y-1">
            <Label>Tips (one per line or JSON array)</Label>
            <Textarea v-model="sectionForm.tips" rows="4" class="font-mono text-sm" />
            <p v-if="sectionForm.errors.tips" class="text-xs text-rose-500">{{ sectionForm.errors.tips }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Target Words</Label>
              <Input v-model="sectionForm.target_word_count" type="number" min="50" />
              <p v-if="sectionForm.errors.target_word_count" class="text-xs text-rose-500">{{ sectionForm.errors.target_word_count }}</p>
            </div>
            <div class="space-y-1">
              <Label>Sort Order</Label>
              <Input v-model="sectionForm.sort_order" type="number" min="0" max="1000" />
              <p v-if="sectionForm.errors.sort_order" class="text-xs text-rose-500">{{ sectionForm.errors.sort_order }}</p>
            </div>
          </div>

          <label class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
            <span class="text-foreground">Required</span>
            <Switch :checked="sectionForm.is_required" @update:checked="val => sectionForm.is_required = val" />
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isSectionDialogOpen = false">Cancel</Button>
          <Button :disabled="sectionForm.processing" @click="saveSection">
            <Loader2 v-if="sectionForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Save
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'
import { Loader2, Plus, Sparkles } from 'lucide-vue-next'

type SectionRow = {
  id: number
  section_number: string
  section_title: string
  description: string | null
  writing_guidance: string | null
  tips: any
  target_word_count: number
  is_required: boolean
  sort_order: number
}

type ChapterRow = {
  id: number
  academic_level: string
  project_type: string
  chapter_number: number
  chapter_title: string
  description: string | null
  target_word_count: number
  completion_threshold: number
  is_required: boolean
  sort_order: number
  sections: SectionRow[]
}

const props = defineProps<{
  structure: any
  chapters: ChapterRow[]
  academicLevels: string[]
  projectTypes: string[]
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })

const structure = computed(() => props.structure)
const chapters = computed(() => props.chapters ?? [])
const academicLevels = computed(() => props.academicLevels ?? [])
const projectTypes = computed(() => props.projectTypes ?? [])

const structureTitle = computed(() => `${structure.value.faculty_name} · Chapters`)

const isChapterDialogOpen = ref(false)
const chapterEditingId = ref<number | null>(null)
const chapterForm = useForm({
  academic_level: 'all',
  project_type: 'thesis',
  chapter_number: 1,
  chapter_title: '',
  description: '',
  target_word_count: 3000,
  completion_threshold: 80,
  is_required: true,
  sort_order: 1,
})

const openCreateChapter = () => {
  chapterEditingId.value = null
  chapterForm.clearErrors()
  chapterForm.academic_level = 'all'
  chapterForm.project_type = 'thesis'
  chapterForm.chapter_number = (chapters.value?.length ? Math.max(...chapters.value.map((c) => c.chapter_number)) + 1 : 1)
  chapterForm.chapter_title = ''
  chapterForm.description = ''
  chapterForm.target_word_count = 3000
  chapterForm.completion_threshold = 80
  chapterForm.is_required = true
  chapterForm.sort_order = chapterForm.chapter_number
  isChapterDialogOpen.value = true
}

const openEditChapter = (c: ChapterRow) => {
  chapterEditingId.value = c.id
  chapterForm.clearErrors()
  chapterForm.academic_level = c.academic_level
  chapterForm.project_type = c.project_type
  chapterForm.chapter_number = c.chapter_number
  chapterForm.chapter_title = c.chapter_title
  chapterForm.description = c.description ?? ''
  chapterForm.target_word_count = c.target_word_count
  chapterForm.completion_threshold = c.completion_threshold
  chapterForm.is_required = c.is_required
  chapterForm.sort_order = c.sort_order
  isChapterDialogOpen.value = true
}

const saveChapter = () => {
  chapterForm.clearErrors()
  const options = { preserveScroll: true, onSuccess: () => { isChapterDialogOpen.value = false } }
  if (chapterEditingId.value) {
    chapterForm.put(route('admin.system.faculty-structures.chapters.update', { chapter: chapterEditingId.value }), options)
  } else {
    chapterForm.post(route('admin.system.faculty-structures.chapters.store', { structure: structure.value.id }), options)
  }
}

const deleteChapter = (c: ChapterRow) => {
  if (!confirm(`Delete chapter ${c.chapter_number}: ${c.chapter_title}? This will delete its sections.`)) return
  chapterForm.delete(route('admin.system.faculty-structures.chapters.destroy', { chapter: c.id }), { preserveScroll: true })
}

const isSectionDialogOpen = ref(false)
const sectionEditingId = ref<number | null>(null)
const sectionChapterId = ref<number | null>(null)
const sectionForm = useForm({
  section_number: '',
  section_title: '',
  description: '',
  writing_guidance: '',
  tips: '',
  target_word_count: 500,
  is_required: true,
  sort_order: 0,
})

const formatTips = (tips: any) => {
  if (tips === null || tips === undefined) return ''
  if (typeof tips === 'string') return tips
  try {
    if (Array.isArray(tips)) return tips.join('\n')
    return JSON.stringify(tips, null, 2)
  } catch {
    return ''
  }
}

const openCreateSection = (c: ChapterRow) => {
  sectionEditingId.value = null
  sectionChapterId.value = c.id
  sectionForm.clearErrors()
  sectionForm.section_number = `${c.chapter_number}.1`
  sectionForm.section_title = ''
  sectionForm.description = ''
  sectionForm.writing_guidance = ''
  sectionForm.tips = ''
  sectionForm.target_word_count = 500
  sectionForm.is_required = true
  sectionForm.sort_order = (c.sections?.length ?? 0) + 1
  isSectionDialogOpen.value = true
}

const openEditSection = (c: ChapterRow, s: SectionRow) => {
  sectionEditingId.value = s.id
  sectionChapterId.value = c.id
  sectionForm.clearErrors()
  sectionForm.section_number = s.section_number
  sectionForm.section_title = s.section_title
  sectionForm.description = s.description ?? ''
  sectionForm.writing_guidance = s.writing_guidance ?? ''
  sectionForm.tips = formatTips(s.tips)
  sectionForm.target_word_count = s.target_word_count
  sectionForm.is_required = s.is_required
  sectionForm.sort_order = s.sort_order
  isSectionDialogOpen.value = true
}

const saveSection = () => {
  sectionForm.clearErrors()
  const options = { preserveScroll: true, onSuccess: () => { isSectionDialogOpen.value = false } }
  if (sectionEditingId.value) {
    sectionForm.put(route('admin.system.faculty-structures.sections.update', { section: sectionEditingId.value }), options)
  } else {
    sectionForm.post(route('admin.system.faculty-structures.sections.store', { chapter: sectionChapterId.value }), options)
  }
}

const deleteSection = (c: ChapterRow, s: SectionRow) => {
  if (!confirm(`Delete section ${s.section_number}: ${s.section_title}?`)) return
  sectionForm.delete(route('admin.system.faculty-structures.sections.destroy', { section: s.id }), { preserveScroll: true })
}
</script>

