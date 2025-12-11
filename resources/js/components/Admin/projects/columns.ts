import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown } from 'lucide-vue-next'
import { h } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

export type ProjectRow = {
  id: number
  title: string
  user?: { id: number; email?: string | null; name?: string | null } | null
  status: string
  topic_status: string
  mode: string | null
  type: string | null
  field_of_study?: string | null
  created_at: string
}

export const projectColumns: ColumnDef<ProjectRow>[] = [
  {
    accessorKey: 'id',
    header: 'ID',
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-xs' }, `#${row.getValue('id')}`),
  },
  {
    accessorKey: 'title',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs hover:bg-muted/50',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Title', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => h('div', { class: 'font-medium text-foreground max-w-[200px] truncate', title: row.getValue('title') }, row.getValue('title')),
  },
  {
    accessorKey: 'user_email',
    header: 'User',
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.original.user?.email ?? '—'),
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status: string = row.getValue('status')
      const variant: 'default' | 'secondary' | 'destructive' | 'outline' = 'secondary'
      let className = ''

      if (status === 'completed') {
        className = 'bg-emerald-500/15 text-emerald-600 hover:bg-emerald-500/25 border-emerald-500/20'
      } else if (status === 'writing' || status === 'generating') {
        className = 'bg-blue-500/15 text-blue-600 hover:bg-blue-500/25 border-blue-500/20'
      } else {
        className = 'bg-slate-500/15 text-slate-600 hover:bg-slate-500/25 border-slate-500/20 dark:text-slate-400'
      }

      return h(Badge, { variant, class: className }, () => status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()))
    },
  },
  {
    accessorKey: 'topic_status',
    header: 'Topic',
    cell: ({ row }) => {
      const status: string = row.getValue('topic_status')
      let className = ''

      if (status === 'approved') {
        className = 'bg-emerald-500/10 text-emerald-600 border-emerald-200 dark:border-emerald-800'
      } else if (status === 'pending_approval') {
        className = 'bg-amber-500/10 text-amber-600 border-amber-200 dark:border-amber-800'
      } else {
        className = 'text-muted-foreground'
      }

      return h('span', { class: `text-xs px-2 py-0.5 rounded-full border ${className}` }, status.replace(/_/g, ' '))
    },
  },
  {
    accessorKey: 'mode',
    header: 'Mode',
    cell: ({ row }) => h('div', { class: 'text-muted-foreground capitalize' }, row.getValue('mode') ?? '—'),
  },
  {
    accessorKey: 'created_at',
    header: 'Created',
    cell: ({ row }) =>
      h('div', { class: 'text-muted-foreground text-sm' }, new Date(row.getValue('created_at')).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })),
  },
]
