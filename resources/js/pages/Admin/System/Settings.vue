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
        <TabsList class="flex w-full overflow-x-auto lg:w-auto lg:inline-flex bg-muted/40 p-1 rounded-xl h-auto gap-1">
           <TabsTrigger 
             v-for="cat in categories" 
             :key="cat.id" 
             :value="cat.id"
             class="flex-1 lg:flex-none px-4 py-2 rounded-lg data-[state=active]:bg-background data-[state=active]:text-primary data-[state=active]:shadow-sm transition-all duration-200 font-medium text-muted-foreground"
           >
             {{ cat.label }}
           </TabsTrigger>
        </TabsList>

        <TabsContent v-for="category in categories" :key="category.id" :value="category.id" class="space-y-4 animate-in fade-in-50 slide-in-from-bottom-2 duration-300">
          <Card class="border-border/60 shadow-sm">
            <CardHeader>
              <CardTitle class="text-lg">{{ category.label }}</CardTitle>
              <CardDescription>{{ category.description }}</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <div v-for="(setting, index) in category.settings" :key="setting.key" class="group grid gap-3 p-5 rounded-xl border border-border/50 bg-card hover:border-primary/20 hover:shadow-sm transition-all duration-200">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Label :for="setting.key" class="text-base font-medium text-foreground group-hover:text-primary transition-colors">
                      {{ formatLabel(setting.key) }}
                    </Label>
                    <code class="text-[10px] px-1.5 py-0.5 rounded bg-muted/50 text-muted-foreground font-mono opacity-50 group-hover:opacity-100 transition-opacity">{{ setting.key }}</code>
                  </div>
                  
                  <Badge v-if="isUnwired(setting.key)" variant="outline" class="gap-1.5 text-amber-600 border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-800 dark:text-amber-400">
                    <AlertCircle class="w-3 h-3" />
                    Pending
                  </Badge>
                  <Badge v-else variant="outline" class="gap-1.5 text-emerald-600 border-emerald-200 bg-emerald-50 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400">
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
                     class="font-mono text-sm leading-relaxed bg-muted/5 border-border/50 focus:bg-background transition-colors"
                   />
                   <Input 
                     v-else 
                     :id="setting.key" 
                     v-model="setting.value" 
                     class="max-w-md bg-muted/5 border-border/50 focus:bg-background transition-colors"
                   />
                   <p v-if="setting.description" class="text-sm text-muted-foreground/80">
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
