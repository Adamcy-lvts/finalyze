<template>
  <AdminLayout title="Prompt Preview" subtitle="Inspect the exact system/user prompts used for a project.">
    <div class="space-y-6">
      <Card class="border border-border bg-card shadow-sm">
        <CardHeader>
          <CardTitle class="text-base font-semibold">Preview Inputs</CardTitle>
          <CardDescription>Select a project and prompt type, then preview the resolved prompts.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Project</Label>
              <select
                v-model="form.project_id"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                <option value="" disabled>Select a project…</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">
                  #{{ p.id }} · {{ p.title || p.topic || p.slug }} · {{ p.user?.email || '—' }}
                </option>
              </select>
            </div>

            <div class="space-y-2">
              <Label>Type</Label>
              <select
                v-model="form.type"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                <option value="chapter">Chapter</option>
                <option value="editor">Editor (rephrase/expand/improve)</option>
                <option value="chat">Chat</option>
                <option value="analysis">Analysis</option>
              </select>
            </div>
          </div>

          <div v-if="form.type === 'chapter'" class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Chapter Number</Label>
              <Input v-model="form.chapter_number" type="number" min="1" max="20" />
            </div>
          </div>

          <div v-else-if="form.type === 'editor'" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Action</Label>
                <select
                  v-model="form.editor_action"
                  class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="rephrase">Rephrase</option>
                  <option value="expand">Expand</option>
                  <option value="improve">Improve</option>
                </select>
              </div>
              <div class="space-y-2">
                <Label>Style (rephrase only)</Label>
                <Input v-model="form.style" placeholder="Academic Formal" />
              </div>
            </div>
            <div class="space-y-2">
              <Label>Selected Text</Label>
              <Textarea v-model="form.selected_text" rows="6" class="font-mono text-sm" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Chapter ID (optional)</Label>
                <Input v-model="form.chapter_id" type="number" min="1" />
              </div>
            </div>
          </div>

          <div v-else-if="form.type === 'chat'" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Chapter ID (optional)</Label>
                <Input v-model="form.chapter_id" type="number" min="1" />
              </div>
            </div>
            <div class="space-y-2">
              <Label>Message</Label>
              <Input v-model="form.chat_message" placeholder="How can I improve this section?" />
            </div>
            <div class="space-y-2">
              <Label>History (JSON array, optional)</Label>
              <Textarea v-model="chatHistoryJson" rows="6" class="font-mono text-sm" />
              <p class="text-xs text-muted-foreground">Format: [{ "role": "user|assistant", "content": "..." }, ...]</p>
            </div>
          </div>

          <div v-else-if="form.type === 'analysis'" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>Kind</Label>
                <select
                  v-model="form.analysis_kind"
                  class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="structure">Structure</option>
                  <option value="citations">Citations</option>
                  <option value="originality">Originality</option>
                  <option value="argument">Argument</option>
                </select>
              </div>
              <div class="space-y-2">
                <Label>Chapter ID (optional)</Label>
                <Input v-model="form.chapter_id" type="number" min="1" />
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2">
            <Button variant="outline" :disabled="isLoading" @click="reset">Reset</Button>
            <Button :disabled="isLoading || !form.project_id" @click="preview">
              <span v-if="isLoading">Previewing…</span>
              <span v-else>Preview</span>
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card class="border border-border bg-card shadow-sm">
        <CardHeader>
          <CardTitle class="text-base font-semibold">Resolved Prompts</CardTitle>
          <CardDescription>System prompt + user prompt sent to the model.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-md border border-border p-3 text-sm">
              <div class="text-xs text-muted-foreground">Estimated tokens</div>
              <div class="font-semibold">System: {{ result?.estimates?.system ?? 0 }}</div>
              <div class="font-semibold">User: {{ result?.estimates?.user ?? 0 }}</div>
              <div class="font-semibold">Total: {{ result?.estimates?.total ?? 0 }}</div>
            </div>
            <div class="md:col-span-2 rounded-md border border-border p-3 text-sm">
              <div class="text-xs text-muted-foreground">Meta</div>
              <pre class="mt-2 whitespace-pre-wrap break-words font-mono text-xs">{{ metaJson }}</pre>
            </div>
          </div>

          <div class="space-y-2">
            <Label>System Prompt</Label>
            <Textarea :model-value="result?.system_prompt ?? ''" rows="10" class="font-mono text-sm" readonly />
          </div>
          <div class="space-y-2">
            <Label>User Prompt</Label>
            <Textarea :model-value="result?.user_prompt ?? ''" rows="14" class="font-mono text-sm" readonly />
          </div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'

type ProjectOption = {
  id: number
  slug: string
  title: string | null
  topic: string | null
  type: string | null
  course: string | null
  field_of_study: string | null
  user?: { id?: number; name?: string; email?: string }
  updated_at?: string | null
}

const props = defineProps<{ projects: ProjectOption[] }>()

const isLoading = ref(false)
const result = ref<any>(null)

const form = ref<any>({
  type: 'chapter',
  project_id: '',
  chapter_number: 1,
  chapter_id: '',
  editor_action: 'rephrase',
  style: 'Academic Formal',
  selected_text: '',
  chat_message: 'How can I improve this section?',
  analysis_kind: 'structure',
})

const chatHistoryJson = ref<string>('[]')

const projects = computed(() => props.projects ?? [])

const metaJson = computed(() => {
  try {
    return JSON.stringify(result.value?.meta ?? {}, null, 2)
  } catch {
    return '{}'
  }
})

const reset = () => {
  result.value = null
  form.value.chapter_number = 1
  form.value.chapter_id = ''
  form.value.editor_action = 'rephrase'
  form.value.style = 'Academic Formal'
  form.value.selected_text = ''
  form.value.chat_message = 'How can I improve this section?'
  form.value.analysis_kind = 'structure'
  chatHistoryJson.value = '[]'
}

const preview = async () => {
  try {
    isLoading.value = true
    const payload: any = {
      type: String(form.value.type ?? 'chapter'),
      project_id: Number(form.value.project_id),
    }

    if (!Number.isFinite(payload.project_id) || payload.project_id <= 0) {
      delete payload.project_id
    }

    if (payload.type === 'chapter') {
      payload.chapter_number = Number(form.value.chapter_number ?? 1)
    } else if (payload.type === 'editor') {
      payload.editor_action = String(form.value.editor_action ?? 'rephrase')
      payload.style = String(form.value.style ?? 'Academic Formal')
      payload.selected_text = String(form.value.selected_text ?? '')

      const chapterId = form.value.chapter_id
      if (chapterId !== undefined && chapterId !== null && chapterId !== '') {
        const n = Number(chapterId)
        if (Number.isFinite(n) && n > 0) payload.chapter_id = n
      }
    } else if (payload.type === 'chat') {
      payload.chat_message = String(form.value.chat_message ?? 'How can I improve this section?')

      const chapterId = form.value.chapter_id
      if (chapterId !== undefined && chapterId !== null && chapterId !== '') {
        const n = Number(chapterId)
        if (Number.isFinite(n) && n > 0) payload.chapter_id = n
      }

      try {
        payload.chat_history = JSON.parse(chatHistoryJson.value || '[]')
      } catch {
        payload.chat_history = []
      }
    } else if (payload.type === 'analysis') {
      payload.analysis_kind = String(form.value.analysis_kind ?? 'structure')

      const chapterId = form.value.chapter_id
      if (chapterId !== undefined && chapterId !== null && chapterId !== '') {
        const n = Number(chapterId)
        if (Number.isFinite(n) && n > 0) payload.chapter_id = n
      }
    }

    const res = await fetch('/admin/system/prompt-preview', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify(payload),
    })
    const contentType = res.headers.get('content-type') || ''
    if (!res.ok) {
      const bodyText = await res.text()
      const parsed = (() => {
        if (!contentType.includes('application/json')) return null
        try {
          return JSON.parse(bodyText)
        } catch {
          return null
        }
      })()

      result.value = {
        success: false,
        system_prompt: '',
        user_prompt: '',
        meta: { status: res.status, statusText: res.statusText, errors: parsed?.errors ?? undefined },
        estimates: { system: 0, user: 0, total: 0 },
        error: parsed?.message ?? (contentType.includes('application/json') ? bodyText : 'Request failed. Check server logs.'),
      }
      return
    }

    if (!contentType.includes('application/json')) {
      const bodyText = await res.text()
      result.value = {
        success: false,
        system_prompt: '',
        user_prompt: '',
        meta: { status: res.status, statusText: res.statusText },
        estimates: { system: 0, user: 0, total: 0 },
        error: 'Server did not return JSON. This is usually a 419/500 HTML response. Check server logs.',
        body_preview: bodyText.slice(0, 500),
      }
      return
    }

    const data = await res.json()
    result.value = data
  } finally {
    isLoading.value = false
  }
}
</script>
