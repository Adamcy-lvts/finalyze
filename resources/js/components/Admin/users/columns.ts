import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown } from 'lucide-vue-next'
import { h } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

export type UserRow = {
  id: number
  name: string
  email: string
  projects_count: number
  payments_count: number
  is_banned: boolean
  created_at: string
}

export const userColumns: ColumnDef<UserRow>[] = [
  {
    accessorKey: 'id',
    header: 'ID',
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-xs' }, `#${row.getValue('id')}`),
  },
  {
    accessorKey: 'name',
    header: 'Name',
    cell: ({ row }) => h('div', { class: 'font-medium text-foreground' }, row.getValue('name')),
  },
  {
    accessorKey: 'email',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs hover:bg-muted/50',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Email', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.getValue('email')),
  },
  {
    accessorKey: 'projects_count',
    header: () => h('div', { class: 'text-center' }, 'Projects'),
    cell: ({ row }) => h('div', { class: 'text-center font-medium' }, row.getValue('projects_count')),
  },
  {
    accessorKey: 'payments_count',
    header: () => h('div', { class: 'text-center' }, 'Payments'),
    cell: ({ row }) => h('div', { class: 'text-center font-medium' }, row.getValue('payments_count')),
  },
  {
    accessorKey: 'is_banned',
    header: 'Status',
    cell: ({ row }) => {
      const banned = row.getValue('is_banned') as boolean
      return h(Badge, { variant: banned ? 'destructive' : 'secondary', class: banned ? '' : 'bg-emerald-500/15 text-emerald-600 hover:bg-emerald-500/25 border-emerald-500/20' }, () => banned ? 'Banned' : 'Active')
    },
  },
  {
    accessorKey: 'created_at',
    header: 'Joined',
    cell: ({ row }) =>
      h('div', { class: 'text-muted-foreground text-sm' }, new Date(row.getValue('created_at')).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })),
  },
]
