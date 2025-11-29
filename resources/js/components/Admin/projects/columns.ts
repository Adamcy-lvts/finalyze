import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown } from 'lucide-vue-next'
import { h } from 'vue'
import { Button } from '@/components/ui/button'

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
    cell: ({ row }) => h('div', { class: 'text-slate-600' }, row.getValue('id')),
  },
  {
    accessorKey: 'title',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Title', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => h('div', { class: 'font-medium text-slate-900' }, row.getValue('title')),
  },
  {
    accessorKey: 'user_email',
    header: 'User',
    cell: ({ row }) => h('div', { class: 'text-slate-700' }, row.original.user?.email ?? '—'),
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => h('div', { class: 'text-slate-700' }, row.getValue('status')),
  },
  {
    accessorKey: 'topic_status',
    header: 'Topic',
    cell: ({ row }) => h('div', { class: 'text-slate-700' }, row.getValue('topic_status')),
  },
  {
    accessorKey: 'mode',
    header: 'Mode',
    cell: ({ row }) => h('div', { class: 'text-slate-700' }, row.getValue('mode') ?? '—'),
  },
  {
    accessorKey: 'type',
    header: 'Type',
    cell: ({ row }) => h('div', { class: 'text-slate-700' }, row.getValue('type') ?? '—'),
  },
  {
    accessorKey: 'created_at',
    header: 'Created',
    cell: ({ row }) =>
      h('div', { class: 'text-slate-500 text-sm' }, new Date(row.getValue('created_at')).toLocaleDateString()),
  },
]
