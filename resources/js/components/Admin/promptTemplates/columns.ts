import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Button } from '@/components/ui/button'
import { ArrowUpDown } from 'lucide-vue-next'
import PromptTemplateActions from './PromptTemplateActions.vue'

export type PromptTemplateRow = {
  id: number
  context_type: string
  context_value: string
  chapter_type: string | null
  parent_template_id: number | null
  priority: number
  is_active: boolean
  system_prompt: string | null
  chapter_prompt_template: string | null
  updated_at?: string | null
}

const toggleActive = (t: PromptTemplateRow, val: boolean) => {
  router.put(route('admin.system.prompt-templates.toggle-active', { template: t.id }), { is_active: val }, {
    preserveScroll: true,
  })
}

export const promptTemplateColumns: ColumnDef<PromptTemplateRow>[] = [
  {
    accessorKey: 'context_value',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs hover:bg-muted/50',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Context', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => {
      const t = row.original
      return h('div', { class: 'flex flex-col gap-1' }, [
        h('div', { class: 'flex items-center gap-2' }, [
          h(Badge, { variant: 'outline', class: 'capitalize' }, () => t.context_type.replace(/_/g, ' ')),
          h('span', { class: 'font-medium' }, t.context_value),
        ]),
        t.chapter_type
          ? h('span', { class: 'text-xs text-muted-foreground' }, `Chapter: ${t.chapter_type}`)
          : h('span', { class: 'text-xs text-muted-foreground' }, 'Chapter: any'),
      ])
    },
  },
  {
    accessorKey: 'priority',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs hover:bg-muted/50',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Priority', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => row.getValue('priority'),
  },
  {
    id: 'overrides',
    header: 'Overrides',
    cell: ({ row }) => {
      const t = row.original
      const hasSystem = Boolean(t.system_prompt && t.system_prompt.trim())
      const hasUser = Boolean(t.chapter_prompt_template && t.chapter_prompt_template.trim())
      return h('div', { class: 'flex items-center gap-2' }, [
        h(Badge, { variant: hasSystem ? 'default' : 'outline' }, () => `System: ${hasSystem ? 'yes' : 'no'}`),
        h(Badge, { variant: hasUser ? 'default' : 'outline' }, () => `User: ${hasUser ? 'yes' : 'no'}`),
      ])
    },
  },
  {
    accessorKey: 'is_active',
    header: 'Active',
    cell: ({ row }) =>
      h(Switch, {
        checked: row.original.is_active,
        'onUpdate:checked': (val: boolean) => toggleActive(row.original, val),
      }),
  },
  {
    id: 'actions',
    cell: ({ row }) => h(PromptTemplateActions, { template: row.original }),
  },
]

