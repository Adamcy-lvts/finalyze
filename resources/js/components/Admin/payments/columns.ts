import type { ColumnDef } from '@tanstack/vue-table'
import { ArrowUpDown } from 'lucide-vue-next'
import { h } from 'vue'
import { Button } from '@/components/ui/button'

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
    cell: ({ row }) => h('div', { class: 'text-slate-600' }, String(row.getValue('id'))),
  },
  {
    accessorKey: 'user_email',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['User', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => {
      const email = row.original.user?.email ?? '—'
      return h('div', { class: 'text-slate-800' }, email)
    },
    enableSorting: true,
  },
  {
    accessorKey: 'amount',
    header: () => h('div', { class: 'text-right text-xs font-semibold text-slate-500' }, 'Amount (₦)'),
    cell: ({ row }) => {
      const amount = Number.parseFloat(row.getValue('amount'))
      return h('div', { class: 'text-right font-medium text-slate-900' }, amount.toLocaleString())
    },
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status: string = row.getValue('status')
      const color =
        status === 'success' ? 'text-green-600' : status === 'pending' ? 'text-amber-600' : 'text-red-600'
      return h('span', { class: color }, status)
    },
  },
  {
    accessorKey: 'channel',
    header: 'Channel',
    cell: ({ row }) => h('div', { class: 'text-slate-600' }, row.original.channel ?? '—'),
  },
  {
    accessorKey: 'paid_at',
    header: 'Paid At',
    cell: ({ row }) => {
      const paidAt = row.original.paid_at
      return h('div', { class: 'text-slate-500 text-sm' }, paidAt ? new Date(paidAt).toLocaleString() : '—')
    },
  },
]
