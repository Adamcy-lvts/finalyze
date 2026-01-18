<script setup lang="ts">
import { computed, ref, reactive } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { useForm } from '@inertiajs/vue3'
import { AlertCircle, CheckCircle2, Save, Loader2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner' // Added toast import
import { route } from 'ziggy-js'

const props = defineProps<{
  settings: {
    key: string
    value: any
    type: string
    group: string
    description: string | null
  }[]
}>()

// Use a ref for the form to match the previous implementation's reactivity
// We need to transform the props into a mutable format for the form
const editableSettings = props.settings.map((s) => ({
  key: s.key,
  // Keep original value handling: stringify objects
  value: typeof s.value === 'object' ? JSON.stringify(s.value, null, 2) : s.value,
  type: s.type,
  group: s.group,
  description: s.description,
}))

const form = useForm({
  settings: editableSettings,
})

// Tab State Management
import { useUrlSearchParams } from '@vueuse/core'
import { watch } from 'vue'

const params = useUrlSearchParams('history')
const activeTab = ref(params.tab as string || 'general')

watch(activeTab, (newValue) => {
  params.tab = newValue
})

// Define known unwired settings
const unwiredSettings = ['refund_policy', 'support_email']

const isUnwired = (key: string) => unwiredSettings.includes(key)

// Categorization Logic
// Use a method instead of computed for the settings grouping to avoid
// potential reactivity issues with deep mutation in computed props,
// although strictly speaking it should work if references are maintained.
// However, creating a computed view is cleaner.
const categories = computed(() => {
  const cats = {
    general: { id: 'general', label: 'General', description: 'General system configurations', settings: [] as typeof editableSettings },
    ai: { id: 'ai', label: 'AI & Prompts', description: 'Configure AI models and system prompts', settings: [] as typeof editableSettings },
    affiliate: { id: 'affiliate', label: 'Affiliate System', description: 'Manage affiliate program settings', settings: [] as typeof editableSettings },
    feedback: { id: 'feedback', label: 'User Feedback', description: 'Feedback collection triggers and limits', settings: [] as typeof editableSettings },
  }

  // Iterate over form.settings to ensure v-model binds to the form's reactive data
  form.settings.forEach((setting) => {
    if (setting.key.startsWith('ai.') || setting.key.includes('prompt')) {
      cats.ai.settings.push(setting)
    } else if (setting.key.startsWith('affiliate.')) {
      cats.affiliate.settings.push(setting)
    } else if (setting.key.startsWith('feedback.')) {
      cats.feedback.settings.push(setting)
    } else {
      cats.general.settings.push(setting)
    }
  })

  return Object.values(cats).filter(c => c.settings.length > 0)
})

const shouldUseTextarea = (key: string, value: unknown) => {
  const k = String(key ?? '')
  const v = String(value ?? '')
  
  if (k.startsWith('ai.') || k.includes('prompt')) return true
  if (v.includes('\n')) return true
  if (v.length > 80) return true
  
  return false
}

const textareaRows = (value: unknown) => {
  const v = String(value ?? '')
  const lines = v.split('\n').length
  return Math.min(15, Math.max(3, lines))
}

const formatLabel = (key: string) => {
  return key
    .split('.')
    .pop()
    ?.split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ') || key
}

const save = () => {
  console.log('Saving settings:', form.settings)
  
  form.put(route('admin.system.update-settings'), {
    preserveScroll: true,
    onSuccess: () => {
      console.log('Save success')
      toast('Settings Saved', {
        description: 'System settings have been updated successfully.',
      })
    },
    onError: (errors) => {
        console.error('Save error:', errors)
        toast('Save Failed', {
            description: 'There was an error saving the settings. Please check your inputs.',
        }) // Added error toast
    }
  })
}
</script>

<template>
  <AdminLayout title="System Settings">
    <div class="space-y-6 max-w-5xl mx-auto">
      <div class="flex items-center justify-between pb-4 border-b">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-foreground">System Settings</h1>
          <p class="text-muted-foreground mt-1">Manage global application configurations and parameters.</p>
        </div>
        <Button @click="save" :disabled="form.processing" class="gap-2">
          <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
          <Save v-else class="w-4 h-4" />
          {{ form.processing ? 'Saving...' : 'Save Changes' }}
        </Button>
      </div>

      <Tabs v-model="activeTab" class="space-y-6">
        <TabsList class="grid w-full grid-cols-4 lg:w-[600px]">
           <TabsTrigger v-for="cat in categories" :key="cat.id" :value="cat.id">
             {{ cat.label }}
           </TabsTrigger>
        </TabsList>

        <TabsContent v-for="category in categories" :key="category.id" :value="category.id" class="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{{ category.label }}</CardTitle>
              <CardDescription>{{ category.description }}</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <div v-for="(setting, index) in category.settings" :key="setting.key" class="grid gap-3 p-4 rounded-lg border bg-card hover:bg-accent/5 transition-colors">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Label :for="setting.key" class="text-base font-semibold text-foreground">
                      {{ formatLabel(setting.key) }}
                    </Label>
                    <code class="text-xs px-2 py-0.5 rounded bg-muted text-muted-foreground font-mono">{{ setting.key }}</code>
                  </div>
                  
                  <Badge v-if="isUnwired(setting.key)" variant="destructive" class="gap-1">
                    <AlertCircle class="w-3 h-3" />
                    Not Implemented
                  </Badge>
                  <Badge v-else variant="outline" class="gap-1 text-emerald-600 border-emerald-200 bg-emerald-50 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400">
                    <CheckCircle2 class="w-3 h-3" />
                    Active
                  </Badge>
                </div>

                <div class="grid gap-2">
                   <Textarea
                     v-if="shouldUseTextarea(setting.key, setting.value)"
                     :id="setting.key"
                     v-model="setting.value"
                     :rows="textareaRows(setting.value)"
                     class="font-mono text-sm leading-relaxed"
                   />
                   <Input 
                     v-else 
                     :id="setting.key" 
                     v-model="setting.value" 
                     class="max-w-md"
                   />
                   <p v-if="setting.description" class="text-sm text-muted-foreground">
                     {{ setting.description }}
                   </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </AdminLayout>
</template>
