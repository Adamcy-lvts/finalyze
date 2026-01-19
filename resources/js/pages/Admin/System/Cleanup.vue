<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { route } from 'ziggy-js'
import { AlertTriangle, Database, Trash2, Shield, Lock } from 'lucide-vue-next'

const props = defineProps<{
  tables: {
    name: string
    count: number
    locked: boolean
  }[]
  userStats: {
    total: number
    super_admins: number
    deletable: number
  }
  confirmPhrase: string
}>()

const tables = computed(() => props.tables)
const userStats = computed(() => props.userStats)
const confirmPhrase = computed(() => props.confirmPhrase)
const purgeableTables = computed(() => tables.value.filter((table) => !table.locked))
const lockedTables = computed(() => tables.value.filter((table) => table.locked))
const selectedTables = ref<string[]>(purgeableTables.value.map((table) => table.name))
const confirmDialogOpen = ref(false)

const tableLookup = computed(() => {
  const map = new Map<string, { count: number }>()
  tables.value.forEach((table) => {
    map.set(table.name, { count: table.count })
  })
  return map
})

const selectedRows = computed(() => {
  return selectedTables.value.reduce((total, name) => {
    if (name === 'users') {
      return total + userStats.value.deletable
    }
    return total + (tableLookup.value.get(name)?.count ?? 0)
  }, 0)
})

const form = useForm({
  tables: [] as string[],
  confirm_phrase: '',
})

const toggleTable = (tableName: string, selected: boolean | 'indeterminate') => {
  if (selected === true) {
    if (!selectedTables.value.includes(tableName)) {
      selectedTables.value.push(tableName)
    }
  } else {
    selectedTables.value = selectedTables.value.filter((name) => name !== tableName)
  }
}

const selectAll = () => {
  selectedTables.value = purgeableTables.value.map((table) => table.name)
}

const clearAll = () => {
  selectedTables.value = []
}

const runCleanup = () => {
  form.tables = selectedTables.value
  form.post(route('admin.system.cleanup.run'), {
    preserveScroll: true,
    onSuccess: () => {
      form.confirm_phrase = ''
      confirmDialogOpen.value = false
      toast('Cleanup complete', {
        description: 'Selected tables have been cleared.',
      })
    },
    onError: () => {
      toast('Cleanup failed', {
        description: 'Review the errors and try again.',
      })
    },
  })
}
</script>

<template>
  <AdminLayout title="Data Cleanup" subtitle="Permanently delete non-core data before launch">
    <div class="space-y-6 max-w-5xl mx-auto">
      <Card class="border-l-4 border-l-destructive border-t-destructive/20 border-r-destructive/20 border-b-destructive/20 bg-gradient-to-br from-destructive/10 via-background to-destructive/5 shadow-sm">
        <CardHeader>
          <div class="flex items-center gap-2">
            <div class="p-2 rounded-full bg-destructive/10 text-destructive">
               <AlertTriangle class="w-5 h-5" />
            </div>
            <CardTitle class="text-destructive text-lg">Danger Zone</CardTitle>
          </div>
          <CardDescription class="text-destructive/80 font-medium">
            This action deletes production data. Core lookup tables are locked and cannot be selected.
          </CardDescription>
        </CardHeader>
        <CardContent class="grid gap-2 text-sm text-muted-foreground ml-1">
          <div class="flex items-center gap-2">
            <Shield class="w-4 h-4 text-emerald-500" />
            <span>Superadmin users will always remain protected.</span>
          </div>
          <div class="flex items-center gap-2">
             <Trash2 class="w-4 h-4 text-destructive/70" />
             <span>Selected rows to delete: <span class="font-bold text-destructive text-base">{{ selectedRows.toLocaleString() }}</span></span>
          </div>
        </CardContent>
      </Card>

      <Card class="shadow-sm border-border/60">
        <CardHeader class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between pb-6">
          <div class="space-y-1">
            <CardTitle class="flex items-center gap-2">
              <Database class="w-5 h-5 text-primary" />
              Database Tables
            </CardTitle>
            <CardDescription>Select which tables to purge. Locked tables are excluded.</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="selectAll" class="h-8 text-xs">Select all</Button>
            <Button variant="ghost" size="sm" @click="clearAll" class="h-8 text-xs">Clear all</Button>
          </div>
        </CardHeader>
        <CardContent class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label
            v-for="table in purgeableTables"
            :key="table.name"
            class="group relative flex items-start gap-3 rounded-xl border border-border/50 bg-card p-4 text-sm transition-all duration-200 hover:shadow-md hover:border-primary/20 cursor-pointer"
            :class="{ 'ring-1 ring-primary/20 bg-primary/5 border-primary/30': selectedTables.includes(table.name) }"
          >
            <Checkbox
              :model-value="selectedTables.includes(table.name)"
              class="mt-1 data-[state=checked]:bg-primary data-[state=checked]:border-primary"
              @update:modelValue="(val: boolean | 'indeterminate') => toggleTable(table.name, val)"
            />
            <div class="flex-1 space-y-1">
              <div class="flex items-center justify-between gap-2">
                <span class="font-semibold text-foreground tracking-tight">{{ table.name }}</span>
                <Badge v-if="selectedTables.includes(table.name)" variant="default" class="h-5 px-1.5 text-[10px] bg-destructive/90 hover:bg-destructive text-destructive-foreground shadow-none">
                    Purge
                </Badge>
                <Badge v-else variant="outline" class="h-5 px-1.5 text-[10px] text-muted-foreground font-normal">
                    Ready
                </Badge>
              </div>
              <div class="text-xs text-muted-foreground flex flex-col gap-0.5">
                <span v-if="table.name === 'users'" class="text-amber-600 dark:text-amber-500 font-medium">
                  {{ userStats.deletable }} deletable <span class="text-muted-foreground font-normal">/ {{ userStats.total }} total</span>
                </span>
                <span v-else class="font-medium">
                  {{ table.count.toLocaleString() }} <span class="text-muted-foreground font-normal">rows</span>
                </span>
              </div>
            </div>
            
            <!-- Selection Highlight Indicator -->
             <div v-if="selectedTables.includes(table.name)" class="absolute inset-0 rounded-xl ring-1 ring-inset ring-primary/10 pointer-events-none" />
          </label>
        </CardContent>

        <div v-if="lockedTables.length > 0" class="border-t border-border/50 px-6 py-4 bg-muted/20">
             <div class="mb-3 flex items-center gap-2">
                <Lock class="w-4 h-4 text-muted-foreground" />
                <h3 class="text-sm font-semibold text-muted-foreground">Locked System Tables ({{ lockedTables.length }})</h3>
             </div>
             <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="table in lockedTables" :key="table.name" class="flex items-center justify-between gap-2 p-2 rounded-lg bg-background/50 border border-border/30 text-xs opacity-75">
                    <span class="font-mono text-muted-foreground">{{ table.name }}</span>
                    <Badge variant="secondary" class="h-4 px-1 text-[9px] pointer-events-none">Locked</Badge>
                </div>
             </div>
        </div>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Run Cleanup</CardTitle>
          <CardDescription>
            Confirm and execute deletion for selected tables. This cannot be undone.
          </CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
          <div class="text-sm text-muted-foreground">
            Selected tables: <span class="font-semibold text-foreground">{{ selectedTables.length }}</span>
          </div>
          <AlertDialog :open="confirmDialogOpen" @update:open="confirmDialogOpen = $event">
            <AlertDialogTrigger as-child>
              <Button
                variant="destructive"
                :disabled="selectedTables.length === 0"
              >
                Delete Selected Data
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent class="max-w-md">
              <AlertDialogHeader>
                <AlertDialogTitle class="text-destructive">Confirm data cleanup</AlertDialogTitle>
                <AlertDialogDescription class="space-y-3 text-sm">
                  <div>
                    This will delete {{ selectedRows }} rows across {{ selectedTables.length }} tables.
                  </div>
                  <div>
                    Type <code class="font-mono text-xs bg-muted px-2 py-1 rounded">{{ confirmPhrase }}</code> to continue.
                  </div>
                  <Input v-model="form.confirm_phrase" placeholder="Confirmation phrase" />
                  <p v-if="form.errors.confirm_phrase" class="text-xs text-rose-500">
                    {{ form.errors.confirm_phrase }}
                  </p>
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                  class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  :disabled="form.processing || form.confirm_phrase !== confirmPhrase"
                  @click="runCleanup"
                >
                  {{ form.processing ? 'Deleting...' : 'Confirm Delete' }}
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
          <p v-if="form.errors.tables" class="text-xs text-rose-500">
            {{ form.errors.tables }}
          </p>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
