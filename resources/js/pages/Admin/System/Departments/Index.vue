<template>
  <AdminLayout title="Departments" subtitle="Manage departments available for project setup.">
    <div class="grid gap-4 md:grid-cols-2 mb-6">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Departments</CardTitle>
          <Layers class="h-4 w-4 text-muted-foreground" />
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
          <CardTitle class="text-base font-semibold text-foreground">Departments</CardTitle>
          <CardDescription>Assign departments to faculties for selection.</CardDescription>
        </div>
        <Button size="sm" @click="openCreateModal">
          <Plus class="mr-2 h-4 w-4" />
          Add Department
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable :columns="departmentColumns" :data="departments" search-key="name"
          search-placeholder="Filter departments..." />
      </CardContent>
    </Card>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit department' : 'Create department' }}</DialogTitle>
          <DialogDescription>
            {{ isEditing ? 'Update department details.' : 'Add a new department to the catalog.' }}
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Computer Science" />
              <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-1">
              <Label>Slug</Label>
              <Input v-model="form.slug" placeholder="computer-science" />
              <p v-if="form.errors.slug" class="text-xs text-rose-500">{{ form.errors.slug }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Code</Label>
              <Input v-model="form.code" placeholder="CSC" />
              <p v-if="form.errors.code" class="text-xs text-rose-500">{{ form.errors.code }}</p>
            </div>
            <div class="space-y-1">
              <Label>Primary Faculty</Label>
              <Select v-model="selectedPrimaryFacultyId">
                <SelectTrigger>
                  <SelectValue placeholder="Select primary faculty" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="faculty in selectedFaculties" :key="faculty.id" :value="String(faculty.id)">
                    {{ faculty.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.primary_faculty_id" class="text-xs text-rose-500">{{ form.errors.primary_faculty_id }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label>Faculties (appear in multiple lists)</Label>
            <div class="grid gap-2 md:grid-cols-2">
              <label v-for="faculty in faculties" :key="faculty.id"
                class="flex items-center gap-2 rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
                <Checkbox :model-value="selectedFacultyIds.includes(String(faculty.id))"
                  @update:modelValue="(val: boolean | 'indeterminate') => toggleFaculty(faculty.id, val)" />
                <span>{{ faculty.name }}</span>
              </label>
            </div>
            <p v-if="form.errors.faculty_ids" class="text-xs text-rose-500">{{ form.errors.faculty_ids }}</p>
          </div>

          <div class="space-y-1">
            <Label>Description</Label>
            <Textarea v-model="form.description" rows="3" placeholder="Optional description..." />
            <p v-if="form.errors.description" class="text-xs text-rose-500">{{ form.errors.description }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Sort Order</Label>
              <Input v-model="form.sort_order" type="number" min="0" />
              <p v-if="form.errors.sort_order" class="text-xs text-rose-500">{{ form.errors.sort_order }}</p>
            </div>
            <label
              class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
              <span class="text-foreground">Active</span>
              <Switch :checked="form.is_active" @update:checked="(val: boolean) => form.is_active = val" />
            </label>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
          <Button :disabled="form.processing" @click="submit">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>{{ isEditing ? 'Update department' : 'Add department' }}</span>
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
import { departmentColumns, type DepartmentRow } from '@/components/Admin/departments/columns'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Checkbox } from '@/components/ui/checkbox'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CheckCircle, Layers, Loader2, Plus, Sparkles } from 'lucide-vue-next'

type FacultyOption = {
  id: number
  name: string
}

type Stats = {
  total: number
  active: number
}

const props = defineProps<{
  departments: DepartmentRow[]
  faculties: FacultyOption[]
  stats: Stats
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })
const departments = computed(() => props.departments ?? [])
const faculties = computed(() => props.faculties ?? [])

const editingId = ref<number | null>(null)
const isDialogOpen = ref(false)

// Separate reactive state for faculty checkboxes (Inertia's useForm has issues with array reactivity)
const selectedFacultyIds = ref<string[]>([])
const selectedPrimaryFacultyId = ref<string>('')

const initialForm = () => ({
  name: '',
  slug: '',
  code: '',
  description: '',
  faculty_ids: [] as string[],
  primary_faculty_id: '',
  sort_order: 0,
  is_active: true,
})

const form = useForm({ ...initialForm() })
const isEditing = computed(() => editingId.value !== null)

const resetForm = () => {
  editingId.value = null
  form.clearErrors()
  Object.assign(form, initialForm())
  selectedFacultyIds.value = []
  selectedPrimaryFacultyId.value = ''
}

const openCreateModal = () => {
  resetForm()
  isDialogOpen.value = true
}

const startEdit = (d: DepartmentRow) => {
  editingId.value = d.id
  form.clearErrors()
  form.name = d.name ?? ''
  form.slug = d.slug ?? ''
  form.code = d.code ?? ''
  form.description = d.description ?? ''

  // Set up faculty IDs using the separate refs
  const fallbackFacultyIds = d.faculty_id ? [d.faculty_id] : []
  const existingFacultyIds = (d.faculty_ids && d.faculty_ids.length > 0) ? d.faculty_ids : fallbackFacultyIds
  selectedFacultyIds.value = existingFacultyIds.filter(Boolean).map((id) => String(id))

  const primaryId = d.primary_faculty_id ?? d.faculty_id ?? existingFacultyIds[0] ?? null
  selectedPrimaryFacultyId.value = primaryId ? String(primaryId) : ''

  // Ensure primary is in the list
  if (selectedPrimaryFacultyId.value && !selectedFacultyIds.value.includes(selectedPrimaryFacultyId.value)) {
    selectedFacultyIds.value = [...selectedFacultyIds.value, selectedPrimaryFacultyId.value]
  }

  form.sort_order = Number(d.sort_order ?? 0)
  form.is_active = Boolean(d.is_active)
  isDialogOpen.value = true
}

const selectedFaculties = computed(() =>
  faculties.value.filter((faculty) => selectedFacultyIds.value.includes(String(faculty.id))),
)

const toggleFaculty = (facultyId: number, enabled: boolean | 'indeterminate') => {
  const id = String(facultyId)
  const isEnabled = enabled === true
  if (isEnabled) {
    if (!selectedFacultyIds.value.includes(id)) {
      selectedFacultyIds.value = [...selectedFacultyIds.value, id]
    }
    if (!selectedPrimaryFacultyId.value) {
      selectedPrimaryFacultyId.value = id
    }
    return
  }
  selectedFacultyIds.value = selectedFacultyIds.value.filter((value) => value !== id)
  if (selectedPrimaryFacultyId.value === id) {
    selectedPrimaryFacultyId.value = selectedFacultyIds.value[0] ?? ''
  }
}

const submit = () => {
  // Sync the separate refs to the form before submitting
  form.faculty_ids = [...selectedFacultyIds.value]
  form.primary_faculty_id = selectedPrimaryFacultyId.value

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      isDialogOpen.value = false
      resetForm()
    },
  }

  if (!form.primary_faculty_id && form.faculty_ids.length > 0) {
    form.primary_faculty_id = form.faculty_ids[0]
  }

  if (isEditing.value && editingId.value !== null) {
    form.put(route('admin.system.departments.update', { department: editingId.value }), options)
    return
  }
  form.post(route('admin.system.departments.store'), options)
}

provide('onEditDepartment', startEdit)
</script>
