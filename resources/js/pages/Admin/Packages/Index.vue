<template>
  <AdminLayout title="Packages" subtitle="Control what shows on the pricing page.">

    <div class="grid gap-4 md:grid-cols-4 mb-6">
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Packages</CardTitle>
          <Package class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.total }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Active</CardTitle>
          <Zap class="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.active }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Projects</CardTitle>
          <Layers class="h-4 w-4 text-primary" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.projects }}</div>
        </CardContent>
      </Card>
      <Card class="bg-card text-card-foreground border-border/50 shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Top-ups</CardTitle>
          <Coins class="h-4 w-4 text-amber-500" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.topups }}</div>
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
          <CardTitle class="text-base font-semibold text-foreground">Existing packages</CardTitle>
          <CardDescription>Manage your pricing packages.</CardDescription>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" as-child>
            <Link :href="route('pricing')" target="_blank">View pricing page</Link>
          </Button>
          <Button size="sm" @click="openCreateModal">
            <Plus class="mr-2 h-4 w-4" />
            Add Package
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="packageColumns" :data="packages" search-key="name"
          search-placeholder="Filter packages..." />
      </CardContent>
    </Card>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit package' : 'Create package' }}</DialogTitle>
          <DialogDescription>
            {{ isEditing ? 'Update pricing details and save changes.' : 'Add a new package to the pricing page.' }}
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="space-y-1">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" placeholder="Undergraduate Project" />
            <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
          </div>
          <div class="space-y-1">
            <Label for="slug">Slug</Label>
            <Input id="slug" v-model="form.slug" placeholder="undergraduate" />
            <p v-if="form.errors.slug" class="text-xs text-rose-500">{{ form.errors.slug }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <Label>Type</Label>
              <Select v-model="form.type">
                <SelectTrigger>
                  <SelectValue placeholder="Type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="project">Project</SelectItem>
                  <SelectItem value="topup">Top-up</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.type" class="text-xs text-rose-500">{{ form.errors.type }}</p>
            </div>
            <div class="space-y-1">
              <Label for="tier">Tier</Label>
              <Input id="tier" v-model="form.tier" :disabled="form.type !== 'project'" placeholder="undergraduate" />
              <p v-if="form.errors.tier" class="text-xs text-rose-500">{{ form.errors.tier }}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <Label for="words">Words</Label>
              <Input id="words" v-model="form.words" type="number" min="0" placeholder="25000" />
              <p v-if="form.errors.words" class="text-xs text-rose-500">{{ form.errors.words }}</p>
            </div>
            <div class="space-y-1">
              <Label for="price">Price (₦)</Label>
              <Input id="price" v-model="form.price" type="number" min="0" step="0.01" placeholder="15000" />
              <p v-if="form.errors.price" class="text-xs text-rose-500">{{ form.errors.price }}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <Label for="currency">Currency</Label>
              <Input id="currency" v-model="form.currency" placeholder="NGN" />
              <p v-if="form.errors.currency" class="text-xs text-rose-500">{{ form.errors.currency }}</p>
            </div>
            <div class="space-y-1">
              <Label for="sort_order">Sort order</Label>
              <Input id="sort_order" v-model="form.sort_order" type="number" min="0" placeholder="1" />
              <p v-if="form.errors.sort_order" class="text-xs text-rose-500">{{ form.errors.sort_order }}</p>
            </div>
          </div>
          <div class="space-y-1">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" rows="3"
              placeholder="Perfect for HND and BSc final year projects..." />
            <p v-if="form.errors.description" class="text-xs text-rose-500">{{ form.errors.description }}</p>
          </div>
          <div class="space-y-1">
            <Label for="features">Features (one per line)</Label>
            <Textarea id="features" v-model="form.features_text" rows="4"
              placeholder="25,000 words allocation&#10;Full 5-chapter project generation" />
            <p v-if="form.errors.features" class="text-xs text-rose-500">{{ form.errors.features }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <label
              class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
              <span class="text-foreground">Active</span>
              <Switch :checked="form.is_active" @update:checked="val => form.is_active = val" />
            </label>
            <label
              class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
              <span class="text-foreground">Popular badge</span>
              <Switch :checked="form.is_popular" @update:checked="val => form.is_popular = val" />
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
          <Button :disabled="form.processing" @click="submit">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>{{ isEditing ? 'Update package' : 'Add package' }}</span>
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref, provide } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Coins, Layers, Loader2, Package, Plus, Sparkles, Zap } from 'lucide-vue-next'
import { route } from 'ziggy-js'
import DataTable from '@/components/Admin/DataTable.vue'
import { packageColumns, type PackageRow } from '@/components/Admin/packages/columns'

type Stats = {
  total: number
  active: number
  projects: number
  topups: number
}

const props = defineProps<{
  packages: PackageRow[]
  stats: Stats
  defaults: { currency: string }
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })
const editingId = ref<number | null>(null)
const isDialogOpen = ref(false)

const initialForm = () => ({
  name: '',
  slug: '',
  type: 'project' as PackageRow['type'],
  tier: '',
  words: 0,
  price: 0,
  currency: props.defaults?.currency ?? 'NGN',
  description: '',
  features_text: '',
  sort_order: 0,
  is_active: true,
  is_popular: false,
})

const form = useForm({ ...initialForm() })

const isEditing = computed(() => editingId.value !== null)

const parseFeatures = (value: string) => value.split(/\r?\n/).map((f) => f.trim()).filter(Boolean)

const submit = () => {
  form.clearErrors()
  form.transform((data) => ({
    ...data,
    type: data.type,
    tier: data.type === 'project' ? data.tier || null : null,
    words: Number(data.words),
    price: Number(data.price),
    sort_order: data.sort_order ? Number(data.sort_order) : 0,
    features: parseFeatures(data.features_text),
  }))

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      isDialogOpen.value = false
      resetForm()
    },
  }

  if (isEditing.value && editingId.value) {
    form.put(route('admin.packages.update', { package: editingId.value }), options)
  } else {
    form.post(route('admin.packages.store'), options)
  }
}

const startEdit = (pkg: PackageRow) => {
  editingId.value = pkg.id
  form.clearErrors()
  form.name = pkg.name
  form.slug = pkg.slug
  form.type = pkg.type
  form.tier = pkg.tier ?? ''
  form.words = pkg.words
  form.price = pkg.price
  form.currency = pkg.currency
  form.description = pkg.description ?? ''
  form.features_text = pkg.features?.join('\n') ?? ''
  form.sort_order = pkg.sort_order
  form.is_active = pkg.is_active
  form.is_popular = pkg.is_popular
  isDialogOpen.value = true
}

const openCreateModal = () => {
  resetForm()
  isDialogOpen.value = true
}

const resetForm = () => {
  editingId.value = null
  form.defaults({ ...initialForm() })
  form.reset()
  form.clearErrors()
}

provide('onEditPackage', startEdit)
</script>
