<template>
  <AdminLayout title="Faculty Structures" subtitle="Control chapter titles, word counts, and sections per faculty.">
    <Alert v-if="flash?.success" class="mb-6 border border-emerald-500/40 bg-emerald-500/10">
      <Sparkles class="h-4 w-4 text-emerald-500" />
      <AlertTitle>Updated</AlertTitle>
      <AlertDescription>{{ flash.success }}</AlertDescription>
    </Alert>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base font-semibold text-foreground">Structures</CardTitle>
          <CardDescription>
            These drive `FacultyStructureService` and therefore the chapter structure used for generation.
          </CardDescription>
        </div>
        <Button size="sm" @click="openCreateModal">
          <Plus class="mr-2 h-4 w-4" />
          Add Structure
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable :columns="facultyStructureColumns" :data="structures" search-key="faculty_name"
          search-placeholder="Filter structures..." />
      </CardContent>
    </Card>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-[900px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit structure' : 'Create structure' }}</DialogTitle>
          <DialogDescription>
            Create a faculty structure, then manage its chapters and sections.
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Faculty Name</Label>
              <Input v-model="form.faculty_name" placeholder="Social Sciences" />
              <p v-if="form.errors.faculty_name" class="text-xs text-rose-500">{{ form.errors.faculty_name }}</p>
            </div>
            <div class="space-y-1">
              <Label>Faculty Slug</Label>
              <Input v-model="form.faculty_slug" placeholder="social-sciences" />
              <p v-if="form.errors.faculty_slug" class="text-xs text-rose-500">{{ form.errors.faculty_slug }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label>Description</Label>
            <Textarea v-model="form.description" rows="3" placeholder="Optional description..." />
            <p v-if="form.errors.description" class="text-xs text-rose-500">{{ form.errors.description }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-1">
              <Label>Sort Order</Label>
              <Input v-model="form.sort_order" type="number" min="0" max="1000" />
              <p v-if="form.errors.sort_order" class="text-xs text-rose-500">{{ form.errors.sort_order }}</p>
            </div>
            <label
              class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm md:col-span-2">
              <span class="text-foreground">Active</span>
              <Switch :checked="form.is_active" @update:checked="val => form.is_active = val" />
            </label>
          </div>

          <div class="space-y-2">
            <Label>Academic Levels</Label>
            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2 text-sm">
                <Checkbox :checked="levels.undergraduate" @update:checked="val => levels.undergraduate = val" />
                Undergraduate
              </label>
              <label class="flex items-center gap-2 text-sm">
                <Checkbox :checked="levels.masters" @update:checked="val => levels.masters = val" />
                Masters
              </label>
              <label class="flex items-center gap-2 text-sm">
                <Checkbox :checked="levels.phd" @update:checked="val => levels.phd = val" />
                PhD
              </label>
            </div>
            <p v-if="form.errors.academic_levels" class="text-xs text-rose-500">{{ form.errors.academic_levels }}</p>
          </div>

          <Accordion type="multiple" class="w-full">
            <AccordionItem value="advanced">
              <AccordionTrigger>Advanced JSON (optional)</AccordionTrigger>
              <AccordionContent class="space-y-4">
                <div class="space-y-1">
                  <Label>Default Structure (JSON)</Label>
                  <Textarea v-model="form.default_structure" rows="6" class="font-mono text-sm" placeholder="{}" />
                  <p v-if="form.errors.default_structure" class="text-xs text-rose-500">{{ form.errors.default_structure }}</p>
                </div>
                <div class="space-y-1">
                  <Label>Chapter Templates (JSON)</Label>
                  <Textarea v-model="form.chapter_templates" rows="6" class="font-mono text-sm" placeholder="{}" />
                  <p v-if="form.errors.chapter_templates" class="text-xs text-rose-500">{{ form.errors.chapter_templates }}</p>
                </div>
                <div class="space-y-1">
                  <Label>Guidance Templates (JSON)</Label>
                  <Textarea v-model="form.guidance_templates" rows="6" class="font-mono text-sm" placeholder="{}" />
                  <p v-if="form.errors.guidance_templates" class="text-xs text-rose-500">{{ form.errors.guidance_templates }}</p>
                </div>
                <div class="space-y-1">
                  <Label>Terminology (JSON)</Label>
                  <Textarea v-model="form.terminology" rows="6" class="font-mono text-sm" placeholder="{}" />
                  <p v-if="form.errors.terminology" class="text-xs text-rose-500">{{ form.errors.terminology }}</p>
                </div>
              </AccordionContent>
            </AccordionItem>
          </Accordion>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
          <Button :disabled="form.processing" @click="submit">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>{{ isEditing ? 'Update structure' : 'Add structure' }}</span>
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, provide, reactive, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import DataTable from '@/components/Admin/DataTable.vue'
import { facultyStructureColumns, type FacultyStructureRow } from '@/components/Admin/facultyStructures/columns'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Checkbox } from '@/components/ui/checkbox'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'
import { Loader2, Plus, Sparkles } from 'lucide-vue-next'

const props = defineProps<{
  structures: FacultyStructureRow[]
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })
const structures = computed(() => props.structures ?? [])

const editingId = ref<number | null>(null)
const isDialogOpen = ref(false)

const levels = reactive({
  undergraduate: true,
  masters: true,
  phd: true,
})

const initialForm = () => ({
  faculty_name: '',
  faculty_slug: '',
  description: '',
  is_active: true,
  sort_order: 0,
  academic_levels: [],
  default_structure: '',
  chapter_templates: '',
  guidance_templates: '',
  terminology: '',
})

const form = useForm({ ...initialForm() })
const isEditing = computed(() => editingId.value !== null)

const resetForm = () => {
  editingId.value = null
  form.clearErrors()
  Object.assign(form, initialForm())
  levels.undergraduate = true
  levels.masters = true
  levels.phd = true
}

const formatJson = (val: any) => {
  if (val === null || val === undefined) return ''
  if (typeof val === 'string') return val
  try {
    return JSON.stringify(val, null, 2)
  } catch {
    return ''
  }
}

const openCreateModal = () => {
  resetForm()
  isDialogOpen.value = true
}

const startEdit = (s: any) => {
  editingId.value = s.id
  form.clearErrors()
  form.faculty_name = s.faculty_name ?? ''
  form.faculty_slug = s.faculty_slug ?? ''
  form.description = s.description ?? ''
  form.is_active = Boolean(s.is_active)
  form.sort_order = Number(s.sort_order ?? 0)
  form.default_structure = formatJson(s.default_structure)
  form.chapter_templates = formatJson(s.chapter_templates)
  form.guidance_templates = formatJson(s.guidance_templates)
  form.terminology = formatJson(s.terminology)

  const lvls = (s.academic_levels ?? []) as string[]
  levels.undergraduate = lvls.includes('undergraduate')
  levels.masters = lvls.includes('masters')
  levels.phd = lvls.includes('phd')

  isDialogOpen.value = true
}

provide('onEditFacultyStructure', startEdit)

const submit = () => {
  form.clearErrors()
  form.transform((data) => ({
    ...data,
    sort_order: Number(data.sort_order ?? 0),
    is_active: Boolean(data.is_active),
    academic_levels: [
      ...(levels.undergraduate ? ['undergraduate'] : []),
      ...(levels.masters ? ['masters'] : []),
      ...(levels.phd ? ['phd'] : []),
    ],
  }))

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      isDialogOpen.value = false
      resetForm()
    },
  }

  if (isEditing.value && editingId.value) {
    form.put(route('admin.system.faculty-structures.update', { structure: editingId.value }), options)
  } else {
    form.post(route('admin.system.faculty-structures.store'), options)
  }
}
</script>

