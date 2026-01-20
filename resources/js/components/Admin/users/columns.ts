import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown } from 'lucide-vue-next'
import { h } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import UserActions from './UserActions.vue'

export type UserRow = {
  id: number
  name: string
  email: string
  word_balance: number
  total_words_purchased: number
  package: string
  projects_count: number
  payments_count: number
  is_banned: boolean
  is_online: boolean
  last_active_at: string | null
  last_login_at: string | null
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
    accessorKey: 'package',
    header: 'Package',
    cell: ({ row }) => h(Badge, { variant: 'outline', class: 'font-normal' }, () => row.getValue('package')),
  },
  {
    accessorKey: 'word_balance',
    header: 'Balance',
    cell: ({ row }) => h('div', { class: 'font-mono text-xs' }, (row.getValue('word_balance') as number).toLocaleString()),
  },
  {
    accessorKey: 'total_words_purchased',
    header: 'Purchased',
    cell: ({ row }) => h('div', { class: 'text-muted-foreground text-xs' }, (row.getValue('total_words_purchased') as number).toLocaleString()),
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
    accessorKey: 'is_online',
    header: 'Online',
    cell: ({ row }) => {
      const online = row.getValue('is_online') as boolean
      return h(Badge, { variant: online ? 'secondary' : 'outline', class: online ? 'bg-emerald-500/15 text-emerald-600 border-emerald-500/20' : 'text-muted-foreground' }, () => online ? 'Online' : 'Offline')
    },
  },
  {
    accessorKey: 'last_active_at',
    header: 'Last Active',
    cell: ({ row }) => {
      const value = row.getValue('last_active_at') as string | null
      return h('div', { class: 'text-muted-foreground text-xs' }, value ? new Date(value).toLocaleString() : '—')
    },
  },
  {
    accessorKey: 'last_login_at',
    header: 'Last Login',
    cell: ({ row }) => {
      const value = row.getValue('last_login_at') as string | null
      return h('div', { class: 'text-muted-foreground text-xs' }, value ? new Date(value).toLocaleString() : '—')
    },
  },
  {
    accessorKey: 'created_at',
    header: 'Joined',
    cell: ({ row }) =>
      h('div', { class: 'text-muted-foreground text-sm' }, new Date(row.getValue('created_at')).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })),
  },
  {
    id: 'actions',
    cell: ({ row }) => h(UserActions, { user: row.original }),
  },
]
