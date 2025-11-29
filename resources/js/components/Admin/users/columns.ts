import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown } from 'lucide-vue-next'
import { h } from 'vue'
import { Button } from '@/components/ui/button'

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
    cell: ({ row }) => h('div', { class: 'text-slate-600' }, row.getValue('id')),
  },
  {
    accessorKey: 'name',
    header: 'Name',
    cell: ({ row }) => h('div', { class: 'font-medium text-slate-900' }, row.getValue('name')),
  },
  {
    accessorKey: 'email',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Email', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => h('div', { class: 'text-slate-700' }, row.getValue('email')),
  },
  {
    accessorKey: 'projects_count',
    header: 'Projects',
    cell: ({ row }) => h('div', null, row.getValue('projects_count')),
  },
  {
    accessorKey: 'payments_count',
    header: 'Payments',
    cell: ({ row }) => h('div', null, row.getValue('payments_count')),
  },
  {
    accessorKey: 'is_banned',
    header: 'Status',
    cell: ({ row }) => {
      const banned = row.getValue('is_banned') as boolean
      return h('span', { class: banned ? 'text-red-600' : 'text-green-600' }, banned ? 'Banned' : 'Active')
    },
  },
  {
    accessorKey: 'created_at',
    header: 'Joined',
    cell: ({ row }) =>
      h('div', { class: 'text-slate-500 text-sm' }, new Date(row.getValue('created_at')).toLocaleDateString()),
  },
]
