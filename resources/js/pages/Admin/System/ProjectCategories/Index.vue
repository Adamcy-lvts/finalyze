<template>
  <AdminLayout title="Project Categories" subtitle="Manage project templates available in the wizard.">
    <div class="grid gap-4 md:grid-cols-2 mb-6">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Categories</CardTitle>
          <Tags class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.total }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Active</CardTitle>
          <CheckCircle class="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.active }}</div>
        </CardContent>
      </Card>
    </div>

    <Alert v-if="flash?.success" class="mb-6 border border-emerald-500/40 bg-emerald-500/10">
      <Sparkles class="h-4 w-4 text-emerald-500" />
      <AlertTitle>Updated</AlertTitle>
      <AlertDescription>{{ flash.success }}</AlertDescription>
    </Alert>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base font-semibold text-foreground">Project Categories</CardTitle>
          <CardDescription>Control templates and defaults for the project wizard.</CardDescription>
        </div>
        <Button size="sm" @click="openCreateModal">
          <Plus class="mr-2 h-4 w-4" />
          Add Category
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable :columns="projectCategoryColumns" :data="categories" search-key="name"
          search-placeholder="Filter categories..." />
      </CardContent>
    </Card>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-[760px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit category' : 'Create category' }}</DialogTitle>
          <DialogDescription>
            {{ isEditing ? 'Update category settings.' : 'Add a new project category.' }}
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Final Year Project" />
              <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-1">
              <Label>Slug</Label>
              <Input v-model="form.slug" placeholder="final-year-project" />
              <p v-if="form.errors.slug" class="text-xs text-rose-500">{{ form.errors.slug }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label>Academic Levels</Label>
            <div class="grid gap-2 md:grid-cols-2">
              <label v-for="level in academicLevels" :key="level"
                class="flex items-center gap-2 rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
                <Checkbox :model-value="selectedLevels.includes(level)"
                  @update:modelValue="(val: boolean | 'indeterminate') => toggleLevel(level, val)" />
                <span class="capitalize">{{ level }}</span>
              </label>
            </div>
            <p v-if="form.errors.academic_levels" class="text-xs text-rose-500">{{ form.errors.academic_levels }}</p>
          </div>

          <div class="space-y-1">
            <Label>Description</Label>
            <Textarea v-model="form.description" rows="3" placeholder="Describe this project category..." />
            <p v-if="form.errors.description" class="text-xs text-rose-500">{{ form.errors.description }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Default Chapters</Label>
              <Input v-model="form.default_chapter_count" type="number" min="1" max="20" />
              <p v-if="form.errors.default_chapter_count" class="text-xs text-rose-500">{{ form.errors.default_chapter_count }}</p>
            </div>
            <div class="space-y-1">
              <Label>Target Word Count</Label>
              <Input v-model="form.target_word_count" type="number" min="0" />
              <p v-if="form.errors.target_word_count" class="text-xs text-rose-500">{{ form.errors.target_word_count }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Target Duration</Label>
              <Input v-model="form.target_duration" placeholder="2 semesters" />
              <p v-if="form.errors.target_duration" class="text-xs text-rose-500">{{ form.errors.target_duration }}</p>
            </div>
            <div class="space-y-1">
              <Label>Sort Order</Label>
              <Input v-model="form.sort_order" type="number" min="0" />
              <p v-if="form.errors.sort_order" class="text-xs text-rose-500">{{ form.errors.sort_order }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label>Chapter Structure (JSON)</Label>
            <Textarea v-model="form.chapter_structure" rows="6" class="font-mono text-xs"
              placeholder='[{ "number": 1, "title": "Introduction" }]' />
            <p v-if="form.errors.chapter_structure" class="text-xs text-rose-500">{{ form.errors.chapter_structure }}</p>
          </div>

          <label
            class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
            <span class="text-foreground">Active</span>
            <Switch :checked="form.is_active" @update:checked="(val: boolean) => form.is_active = val" />
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
          <Button :disabled="form.processing" @click="submit">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>{{ isEditing ? 'Update category' : 'Add category' }}</span>
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, provide, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import DataTable from '@/components/Admin/DataTable.vue'
import { projectCategoryColumns, type ProjectCategoryRow } from '@/components/Admin/projectCategories/columns'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Checkbox } from '@/components/ui/checkbox'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { CheckCircle, Loader2, Plus, Sparkles, Tags } from 'lucide-vue-next'

type Stats = {
  total: number
  active: number
}

const props = defineProps<{
  categories: ProjectCategoryRow[]
  academicLevels: string[]
  stats: Stats
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })
const categories = computed(() => props.categories ?? [])
const academicLevels = computed(() => props.academicLevels ?? [])

const editingSlug = ref<string | null>(null)
const isDialogOpen = ref(false)
const selectedLevels = ref<string[]>([])

const initialForm = () => ({
  name: '',
  slug: '',
  academic_levels: [] as string[],
  description: '',
  default_chapter_count: 5,
  chapter_structure: '',
  target_word_count: '',
  target_duration: '',
  sort_order: 0,
  is_active: true,
})

const form = useForm({ ...initialForm() })
const isEditing = computed(() => editingSlug.value !== null)

const resetForm = () => {
  editingSlug.value = null
  form.clearErrors()
  Object.assign(form, initialForm())
  selectedLevels.value = []
}

const openCreateModal = () => {
  resetForm()
  isDialogOpen.value = true
}

const startEdit = (category: ProjectCategoryRow) => {
  editingSlug.value = category.slug
  form.clearErrors()
  form.name = category.name ?? ''
  form.slug = category.slug ?? ''
  form.description = category.description ?? ''
  form.default_chapter_count = Number(category.default_chapter_count ?? 5)
  form.chapter_structure = category.chapter_structure?.length
    ? JSON.stringify(category.chapter_structure, null, 2)
    : ''
  form.target_word_count = category.target_word_count ?? ''
  form.target_duration = category.target_duration ?? ''
  form.sort_order = Number(category.sort_order ?? 0)
  form.is_active = Boolean(category.is_active)
  selectedLevels.value = (category.academic_levels ?? []).slice()
  form.academic_levels = selectedLevels.value.slice()
  isDialogOpen.value = true
}

const toggleLevel = (level: string, val: boolean | 'indeterminate') => {
  if (val === true) {
    selectedLevels.value = Array.from(new Set([...selectedLevels.value, level]))
  } else {
    selectedLevels.value = selectedLevels.value.filter((l) => l !== level)
  }
  form.academic_levels = selectedLevels.value.slice()
}

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      isDialogOpen.value = false
      resetForm()
    },
  }
  if (isEditing.value && editingSlug.value !== null) {
    form.put(route('admin.system.project-categories.update', { category: editingSlug.value }), options)
    return
  }
  form.post(route('admin.system.project-categories.store'), options)
}

provide('onEditCategory', startEdit)
</script>
