import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown } from 'lucide-vue-next'
import { h } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

export type PaymentRow = {
  id: number | string
  user?: { id: number; email?: string | null; name?: string | null } | null
  amount: number
  status: string
  channel?: string | null
  paid_at?: string | null
}

export const paymentColumns: ColumnDef<PaymentRow>[] = [
  {
    accessorKey: 'id',
    header: 'ID',
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-xs' }, `#${row.getValue('id')}`),
  },
  {
    accessorKey: 'user_email',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs hover:bg-muted/50',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['User', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => {
      const email = row.original.user?.email ?? '—'
      return h('div', { class: 'text-foreground font-medium' }, email)
    },
    enableSorting: true,
  },
  {
    accessorKey: 'amount',
    header: () => h('div', { class: 'text-right text-xs font-semibold text-muted-foreground' }, 'Amount (₦)'),
    cell: ({ row }) => {
      const amount = Number.parseFloat(row.getValue('amount'))
      return h('div', { class: 'text-right font-medium text-foreground' }, amount.toLocaleString())
    },
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status: string = row.getValue('status')
      let variant: 'default' | 'secondary' | 'destructive' | 'outline' = 'secondary'
      let className = ''

      if (status === 'success') {
        className = 'bg-emerald-500/15 text-emerald-600 hover:bg-emerald-500/25 border-emerald-500/20'
      } else if (status === 'pending') {
        className = 'bg-amber-500/15 text-amber-600 hover:bg-amber-500/25 border-amber-500/20'
      } else {
        variant = 'destructive'
      }

      return h(Badge, { variant, class: className }, () => status.charAt(0).toUpperCase() + status.slice(1))
    },
  },
  {
    accessorKey: 'channel',
    header: 'Channel',
    cell: ({ row }) => h('div', { class: 'text-muted-foreground capitalize' }, row.original.channel ?? '—'),
  },
  {
    accessorKey: 'paid_at',
    header: 'Paid At',
    cell: ({ row }) => {
      const paidAt = row.original.paid_at
      return h('div', { class: 'text-muted-foreground text-sm' }, paidAt ? new Date(paidAt).toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—')
    },
  },
]
