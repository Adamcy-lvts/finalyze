<template>
  <AdminLayout title="Universities" subtitle="Manage universities available for project setup.">
    <div class="grid gap-4 md:grid-cols-2 mb-6">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Universities</CardTitle>
          <Building2 class="h-4 w-4 text-muted-foreground" />
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
          <CardTitle class="text-base font-semibold text-foreground">Universities</CardTitle>
          <CardDescription>Control what appears in the institution wizard.</CardDescription>
        </div>
        <Button size="sm" @click="openCreateModal">
          <Plus class="mr-2 h-4 w-4" />
          Add University
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable :columns="universityColumns" :data="universities" search-key="name"
          search-placeholder="Filter universities..." />
      </CardContent>
    </Card>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit university' : 'Create university' }}</DialogTitle>
          <DialogDescription>
            {{ isEditing ? 'Update university details.' : 'Add a new university to the catalog.' }}
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="University of Lagos" />
              <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-1">
              <Label>Short Name</Label>
              <Input v-model="form.short_name" placeholder="UNILAG" />
              <p v-if="form.errors.short_name" class="text-xs text-rose-500">{{ form.errors.short_name }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Slug</Label>
              <Input v-model="form.slug" placeholder="unilag" />
              <p v-if="form.errors.slug" class="text-xs text-rose-500">{{ form.errors.slug }}</p>
            </div>
            <div class="space-y-1">
              <Label>Type</Label>
              <Select v-model="form.type">
                <SelectTrigger>
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="federal">Federal</SelectItem>
                  <SelectItem value="state">State</SelectItem>
                  <SelectItem value="private">Private</SelectItem>
                  <SelectItem value="other">Other</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.type" class="text-xs text-rose-500">{{ form.errors.type }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Location</Label>
              <Input v-model="form.location" placeholder="Akoka, Lagos" />
              <p v-if="form.errors.location" class="text-xs text-rose-500">{{ form.errors.location }}</p>
            </div>
            <div class="space-y-1">
              <Label>State</Label>
              <Input v-model="form.state" placeholder="Lagos" />
              <p v-if="form.errors.state" class="text-xs text-rose-500">{{ form.errors.state }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Country</Label>
              <Input v-model="form.country" placeholder="Nigeria" />
              <p v-if="form.errors.country" class="text-xs text-rose-500">{{ form.errors.country }}</p>
            </div>
            <div class="space-y-1">
              <Label>Website</Label>
              <Input v-model="form.website" placeholder="https://unilag.edu.ng" />
              <p v-if="form.errors.website" class="text-xs text-rose-500">{{ form.errors.website }}</p>
            </div>
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
              <Switch :checked="form.is_active" @update:checked="val => form.is_active = val" />
            </label>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
          <Button :disabled="form.processing" @click="submit">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>{{ isEditing ? 'Update university' : 'Add university' }}</span>
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
import { universityColumns, type UniversityRow } from '@/components/Admin/universities/columns'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Building2, CheckCircle, Loader2, Plus, Sparkles } from 'lucide-vue-next'

type Stats = {
  total: number
  active: number
}

const props = defineProps<{
  universities: UniversityRow[]
  stats: Stats
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })
const universities = computed(() => props.universities ?? [])

const editingId = ref<number | null>(null)
const isDialogOpen = ref(false)

const initialForm = () => ({
  name: '',
  short_name: '',
  slug: '',
  type: 'federal',
  location: '',
  state: '',
  country: 'Nigeria',
  website: '',
  description: '',
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

const startEdit = (u: UniversityRow) => {
  editingId.value = u.id
  form.clearErrors()
  form.name = u.name ?? ''
  form.short_name = u.short_name ?? ''
  form.slug = u.slug ?? ''
  form.type = u.type ?? 'federal'
  form.location = u.location ?? ''
  form.state = u.state ?? ''
  form.country = u.country ?? 'Nigeria'
  form.website = u.website ?? ''
  form.description = u.description ?? ''
  form.sort_order = Number(u.sort_order ?? 0)
  form.is_active = Boolean(u.is_active)
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
  if (isEditing.value && editingId.value !== null) {
    form.put(route('admin.system.universities.update', { university: editingId.value }), options)
    return
  }
  form.post(route('admin.system.universities.store'), options)
}

provide('onEditUniversity', startEdit)
</script>
