<template>
  <AdminLayout title="Faculties" subtitle="Manage faculties available for project setup.">
    <div class="grid gap-4 md:grid-cols-2 mb-6">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Faculties</CardTitle>
          <GraduationCap class="h-4 w-4 text-muted-foreground" />
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
          <CardTitle class="text-base font-semibold text-foreground">Faculties</CardTitle>
          <CardDescription>Keep faculties aligned with their structure templates.</CardDescription>
        </div>
        <Button size="sm" @click="openCreateModal">
          <Plus class="mr-2 h-4 w-4" />
          Add Faculty
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable :columns="facultyColumns" :data="faculties" search-key="name"
          search-placeholder="Filter faculties..." />
      </CardContent>
    </Card>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit faculty' : 'Create faculty' }}</DialogTitle>
          <DialogDescription>
            {{ isEditing ? 'Update faculty details.' : 'Add a new faculty to the catalog.' }}
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Science" />
              <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-1">
              <Label>Slug</Label>
              <Input v-model="form.slug" placeholder="science" />
              <p v-if="form.errors.slug" class="text-xs text-rose-500">{{ form.errors.slug }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Structure Template</Label>
              <Select v-model="form.faculty_structure_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select structure" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">No structure</SelectItem>
                  <SelectItem v-for="structure in structures" :key="structure.id"
                    :value="String(structure.id)">
                    {{ structure.faculty_name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.faculty_structure_id" class="text-xs text-rose-500">
                {{ form.errors.faculty_structure_id }}
              </p>
            </div>
            <div class="space-y-1">
              <Label>Sort Order</Label>
              <Input v-model="form.sort_order" type="number" min="0" />
              <p v-if="form.errors.sort_order" class="text-xs text-rose-500">{{ form.errors.sort_order }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label>Description</Label>
            <Textarea v-model="form.description" rows="3" placeholder="Optional description..." />
            <p v-if="form.errors.description" class="text-xs text-rose-500">{{ form.errors.description }}</p>
          </div>

          <label
            class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
            <span class="text-foreground">Active</span>
            <Switch :checked="form.is_active" @update:checked="val => form.is_active = val" />
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
          <Button :disabled="form.processing" @click="submit">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>{{ isEditing ? 'Update faculty' : 'Add faculty' }}</span>
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
import { facultyColumns, type FacultyRow } from '@/components/Admin/faculties/columns'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CheckCircle, GraduationCap, Loader2, Plus, Sparkles } from 'lucide-vue-next'

type StructureOption = {
  id: number
  faculty_name: string
}

type Stats = {
  total: number
  active: number
}

const props = defineProps<{
  faculties: FacultyRow[]
  structures: StructureOption[]
  stats: Stats
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })
const faculties = computed(() => props.faculties ?? [])
const structures = computed(() => props.structures ?? [])

const editingId = ref<number | null>(null)
const isDialogOpen = ref(false)

const initialForm = () => ({
  name: '',
  slug: '',
  description: '',
  faculty_structure_id: '',
  sort_order: 0,
  is_active: true,
})

const form = useForm({ ...initialForm() })
const isEditing = computed(() => editingId.value !== null)

const resetForm = () => {
  editingId.value = null
  form.clearErrors()
  Object.assign(form, initialForm())
}

const openCreateModal = () => {
  resetForm()
  isDialogOpen.value = true
}

const startEdit = (f: FacultyRow) => {
  editingId.value = f.id
  form.clearErrors()
  form.name = f.name ?? ''
  form.slug = f.slug ?? ''
  form.description = f.description ?? ''
  form.faculty_structure_id = f.faculty_structure_id ? String(f.faculty_structure_id) : ''
  form.sort_order = Number(f.sort_order ?? 0)
  form.is_active = Boolean(f.is_active)
  isDialogOpen.value = true
}

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      isDialogOpen.value = false
      resetForm()
    },
  }
  if (!form.faculty_structure_id) {
    form.faculty_structure_id = null as any
  }
  if (isEditing.value && editingId.value !== null) {
    form.put(route('admin.system.faculties.update', { faculty: editingId.value }), options)
    return
  }
  form.post(route('admin.system.faculties.store'), options)
}

provide('onEditFaculty', startEdit)
</script>
