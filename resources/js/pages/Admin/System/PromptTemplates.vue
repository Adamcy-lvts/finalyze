<template>
  <AdminLayout title="Prompt Templates" subtitle="Override chapter system/user prompts without changing code.">
    <Alert v-if="flash?.success" class="mb-6 border border-emerald-500/40 bg-emerald-500/10">
      <Sparkles class="h-4 w-4 text-emerald-500" />
      <AlertTitle>Saved</AlertTitle>
      <AlertDescription>{{ flash.success }}</AlertDescription>
    </Alert>

    <Card class="border border-border/50 bg-card shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base font-semibold text-foreground">Templates</CardTitle>
          <CardDescription>
            Active templates override code templates when a matching context is found.
          </CardDescription>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" @click="openSeedModal">
            <Sparkles class="mr-2 h-4 w-4" />
            Create From Code
          </Button>
          <Button size="sm" @click="openCreateModal">
            <Plus class="mr-2 h-4 w-4" />
            Add Template
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="promptTemplateColumns" :data="templates" search-key="context_value"
          search-placeholder="Filter templates..." />
      </CardContent>
    </Card>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-[900px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{{ isEditing ? 'Edit template' : 'Create template' }}</DialogTitle>
          <DialogDescription>
            Edit system and user prompt overrides, plus optional requirement JSON.
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <Label>Context Type</Label>
              <select
                v-model="form.context_type"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                <option v-for="ct in contextTypes" :key="ct" :value="ct">
                  {{ ct.replace(/_/g, ' ') }}
                </option>
              </select>
              <p v-if="form.errors.context_type" class="text-xs text-rose-500">{{ form.errors.context_type }}</p>
            </div>

            <div class="space-y-1">
              <Label>Context Value</Label>
              <Input v-model="form.context_value" placeholder="engineering / computer_science / etc" />
              <p v-if="form.errors.context_value" class="text-xs text-rose-500">{{ form.errors.context_value }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-1">
              <Label>Chapter Type (optional)</Label>
              <Input v-model="form.chapter_type" placeholder="introduction / methodology / ..." />
              <p v-if="form.errors.chapter_type" class="text-xs text-rose-500">{{ form.errors.chapter_type }}</p>
            </div>
            <div class="space-y-1">
              <Label>Priority</Label>
              <Input v-model="form.priority" type="number" min="0" max="1000" />
              <p v-if="form.errors.priority" class="text-xs text-rose-500">{{ form.errors.priority }}</p>
            </div>
            <label class="flex items-center justify-between rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm">
              <span class="text-foreground">Active</span>
              <Switch :checked="form.is_active" @update:checked="val => form.is_active = val" />
            </label>
          </div>

          <div class="space-y-1">
            <Label>Parent Template (optional)</Label>
            <select
              v-model="form.parent_template_id"
              class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              <option value="">None</option>
              <option v-for="p in parentOptions" :key="p.id" :value="String(p.id)">
                #{{ p.id }} · {{ p.context_type }}:{{ p.context_value }}{{ p.chapter_type ? ` · ${p.chapter_type}` : '' }}
              </option>
            </select>
            <p v-if="form.errors.parent_template_id" class="text-xs text-rose-500">{{ form.errors.parent_template_id }}</p>
            <p class="text-xs text-muted-foreground">
              Note: inheritance/merging is currently limited; parent is primarily for future use.
            </p>
          </div>

          <div class="space-y-1">
            <Label>System Prompt Override (optional)</Label>
            <Textarea v-model="form.system_prompt" rows="8" class="font-mono text-sm" placeholder="Leave empty to use the code template system prompt." />
            <p v-if="form.errors.system_prompt" class="text-xs text-rose-500">{{ form.errors.system_prompt }}</p>
          </div>

          <div class="space-y-1">
            <Label>User Prompt Override (chapter prompt template) (optional)</Label>
            <Textarea
              v-model="form.chapter_prompt_template"
              rows="10"
              class="font-mono text-sm"
              placeholder="Leave empty to use the code template chapter prompt."
            />
            <p v-if="form.errors.chapter_prompt_template" class="text-xs text-rose-500">{{ form.errors.chapter_prompt_template }}</p>
            <p class="text-xs text-muted-foreground">
              Supported variables: <span class="font-mono">{{vars}}</span>
            </p>
          </div>

          <Accordion type="multiple" class="w-full">
            <AccordionItem value="requirements">
              <AccordionTrigger>Requirements JSON (optional)</AccordionTrigger>
              <AccordionContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                  <div class="space-y-1">
                    <Label>Table Requirements (JSON)</Label>
                    <Textarea v-model="form.table_requirements" rows="6" class="font-mono text-sm" placeholder="[] or { ... }" />
                    <p v-if="form.errors.table_requirements" class="text-xs text-rose-500">{{ form.errors.table_requirements }}</p>
                  </div>
                  <div class="space-y-1">
                    <Label>Diagram Requirements (JSON)</Label>
                    <Textarea v-model="form.diagram_requirements" rows="6" class="font-mono text-sm" placeholder="[] or { ... }" />
                    <p v-if="form.errors.diagram_requirements" class="text-xs text-rose-500">{{ form.errors.diagram_requirements }}</p>
                  </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                  <div class="space-y-1">
                    <Label>Calculation Requirements (JSON)</Label>
                    <Textarea v-model="form.calculation_requirements" rows="6" class="font-mono text-sm" placeholder="[] or { ... }" />
                    <p v-if="form.errors.calculation_requirements" class="text-xs text-rose-500">{{ form.errors.calculation_requirements }}</p>
                  </div>
                  <div class="space-y-1">
                    <Label>Code Requirements (JSON)</Label>
                    <Textarea v-model="form.code_requirements" rows="6" class="font-mono text-sm" placeholder="[] or { ... }" />
                    <p v-if="form.errors.code_requirements" class="text-xs text-rose-500">{{ form.errors.code_requirements }}</p>
                  </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                  <div class="space-y-1">
                    <Label>Placeholder Rules (JSON)</Label>
                    <Textarea v-model="form.placeholder_rules" rows="6" class="font-mono text-sm" placeholder="[] or { ... }" />
                    <p v-if="form.errors.placeholder_rules" class="text-xs text-rose-500">{{ form.errors.placeholder_rules }}</p>
                  </div>
                  <div class="space-y-1">
                    <Label>Recommended Tools (JSON)</Label>
                    <Textarea v-model="form.recommended_tools" rows="6" class="font-mono text-sm" placeholder="[] or { ... }" />
                    <p v-if="form.errors.recommended_tools" class="text-xs text-rose-500">{{ form.errors.recommended_tools }}</p>
                  </div>
                </div>
              </AccordionContent>
            </AccordionItem>

            <AccordionItem value="extras">
              <AccordionTrigger>Extra JSON (optional)</AccordionTrigger>
              <AccordionContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                  <div class="space-y-1">
                    <Label>Mock Data Config (JSON)</Label>
                    <Textarea v-model="form.mock_data_config" rows="6" class="font-mono text-sm" placeholder="{}" />
                    <p v-if="form.errors.mock_data_config" class="text-xs text-rose-500">{{ form.errors.mock_data_config }}</p>
                  </div>
                  <div class="space-y-1">
                    <Label>Citation Requirements (JSON)</Label>
                    <Textarea v-model="form.citation_requirements" rows="6" class="font-mono text-sm" placeholder="{}" />
                    <p v-if="form.errors.citation_requirements" class="text-xs text-rose-500">{{ form.errors.citation_requirements }}</p>
                  </div>
                </div>
                <div class="space-y-1">
                  <Label>Formatting Rules (JSON)</Label>
                  <Textarea v-model="form.formatting_rules" rows="6" class="font-mono text-sm" placeholder="{}" />
                  <p v-if="form.errors.formatting_rules" class="text-xs text-rose-500">{{ form.errors.formatting_rules }}</p>
                </div>
              </AccordionContent>
            </AccordionItem>
          </Accordion>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
          <Button :disabled="form.processing" @click="submit">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>{{ isEditing ? 'Update template' : 'Add template' }}</span>
          </Button>
        </div>
      </DialogContent>
    </Dialog>

    <Dialog :open="isSeedDialogOpen" @update:open="isSeedDialogOpen = $event">
      <DialogContent class="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle>Create From Code Template</DialogTitle>
          <DialogDescription>
            Creates inactive starter templates in the database so you can edit and then activate them.
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 py-4">
          <div class="space-y-1">
            <Label>Faculty</Label>
            <select
              v-model="seedForm.faculty"
              class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              <option v-for="f in facultyOptions" :key="f" :value="f">
                {{ f.replace(/_/g, ' ') }}
              </option>
            </select>
            <p v-if="seedForm.errors.faculty" class="text-xs text-rose-500">{{ seedForm.errors.faculty }}</p>
          </div>

          <div class="space-y-1">
            <Label>Chapter Type</Label>
            <select
              v-model="seedForm.chapter_type"
              class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              <option value="all">All (1–6)</option>
              <option value="introduction">Introduction</option>
              <option value="literature_review">Literature review</option>
              <option value="methodology">Methodology</option>
              <option value="results">Results</option>
              <option value="discussion">Discussion</option>
              <option value="conclusion">Conclusion</option>
            </select>
            <p v-if="seedForm.errors.chapter_type" class="text-xs text-rose-500">{{ seedForm.errors.chapter_type }}</p>
            <p class="text-xs text-muted-foreground">
              Tip: these are created with <span class="font-mono">is_active=false</span>, so they won’t affect generation until you activate.
            </p>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="isSeedDialogOpen = false">Cancel</Button>
          <Button :disabled="seedForm.processing" @click="seedFromCode">
            <Loader2 v-if="seedForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            <span>Create</span>
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
import { promptTemplateColumns, type PromptTemplateRow } from '@/components/Admin/promptTemplates/columns'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'
import { Loader2, Plus, Sparkles } from 'lucide-vue-next'

const props = defineProps<{
  templates: PromptTemplateRow[]
  contextTypes: string[]
  facultyOptions: string[]
}>()

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string })

const editingId = ref<number | null>(null)
const isDialogOpen = ref(false)
const isSeedDialogOpen = ref(false)

const templates = computed(() => props.templates ?? [])
const contextTypes = computed(() => props.contextTypes ?? [])
const facultyOptions = computed(() => props.facultyOptions ?? [])

const vars = '{{topic}}, {{faculty}}, {{department}}, {{course}}, {{field_of_study}}, {{academic_level}}, {{university}}, {{chapter_number}}'

const parentOptions = computed(() => templates.value.filter((t) => t.id !== editingId.value))

const initialForm = () => ({
  context_type: 'faculty',
  context_value: '',
  chapter_type: '',
  parent_template_id: '',
  priority: 0,
  is_active: true,
  system_prompt: '',
  chapter_prompt_template: '',
  table_requirements: '',
  diagram_requirements: '',
  calculation_requirements: '',
  code_requirements: '',
  placeholder_rules: '',
  recommended_tools: '',
  mock_data_config: '',
  citation_requirements: '',
  formatting_rules: '',
})

const form = useForm({ ...initialForm() })
const seedForm = useForm({
  faculty: (props.facultyOptions?.[0] || 'engineering'),
  chapter_type: 'all',
})

const isEditing = computed(() => editingId.value !== null)

const resetForm = () => {
  editingId.value = null
  form.clearErrors()
  Object.assign(form, initialForm())
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

const openSeedModal = () => {
  seedForm.clearErrors()
  isSeedDialogOpen.value = true
}

const startEdit = (t: any) => {
  editingId.value = t.id
  form.clearErrors()
  form.context_type = t.context_type ?? 'faculty'
  form.context_value = t.context_value ?? ''
  form.chapter_type = t.chapter_type ?? ''
  form.parent_template_id = t.parent_template_id ? String(t.parent_template_id) : ''
  form.priority = Number(t.priority ?? 0)
  form.is_active = Boolean(t.is_active)
  form.system_prompt = t.system_prompt ?? ''
  form.chapter_prompt_template = t.chapter_prompt_template ?? ''
  form.table_requirements = formatJson(t.table_requirements)
  form.diagram_requirements = formatJson(t.diagram_requirements)
  form.calculation_requirements = formatJson(t.calculation_requirements)
  form.code_requirements = formatJson(t.code_requirements)
  form.placeholder_rules = formatJson(t.placeholder_rules)
  form.recommended_tools = formatJson(t.recommended_tools)
  form.mock_data_config = formatJson(t.mock_data_config)
  form.citation_requirements = formatJson(t.citation_requirements)
  form.formatting_rules = formatJson(t.formatting_rules)
  isDialogOpen.value = true
}

provide('onEditPromptTemplate', startEdit)

const submit = () => {
  form.clearErrors()
  form.transform((data) => ({
    ...data,
    priority: Number(data.priority ?? 0),
    chapter_type: data.chapter_type ? String(data.chapter_type) : null,
    parent_template_id: data.parent_template_id ? Number(data.parent_template_id) : null,
    is_active: Boolean(data.is_active),
  }))

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      isDialogOpen.value = false
      resetForm()
    },
  }

  if (isEditing.value && editingId.value) {
    form.put(route('admin.system.prompt-templates.update', { template: editingId.value }), options)
  } else {
    form.post(route('admin.system.prompt-templates.store'), options)
  }
}

const seedFromCode = () => {
  seedForm.clearErrors()
  seedForm.post(route('admin.system.prompt-templates.seed-from-code'), {
    preserveScroll: true,
    onSuccess: () => {
      isSeedDialogOpen.value = false
    },
  })
}
</script>
