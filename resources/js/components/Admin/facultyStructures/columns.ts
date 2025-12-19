import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Button } from '@/components/ui/button'
import { ArrowUpDown } from 'lucide-vue-next'
import StructureActions from './StructureActions.vue'

export type FacultyStructureRow = {
  id: number
  faculty_name: string
  faculty_slug: string
  description: string | null
  academic_levels: string[]
  is_active: boolean
  sort_order: number
  chapters_count?: number | null
}

const toggleActive = (s: FacultyStructureRow, val: boolean) => {
  router.put(route('admin.system.faculty-structures.toggle-active', { structure: s.id }), { is_active: val }, {
    preserveScroll: true,
  })
}

export const facultyStructureColumns: ColumnDef<FacultyStructureRow>[] = [
  {
    accessorKey: 'faculty_name',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs hover:bg-muted/50',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Faculty', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => {
      const s = row.original
      return h('div', { class: 'flex flex-col' }, [
        h('span', { class: 'font-medium' }, s.faculty_name),
        h('span', { class: 'text-xs text-muted-foreground' }, s.faculty_slug),
      ])
    },
  },
  {
    id: 'levels',
    header: 'Levels',
    cell: ({ row }) => {
      const levels = (row.original.academic_levels ?? []).slice(0, 3)
      if (!levels.length) return h('span', { class: 'text-xs text-muted-foreground' }, '—')
      return h(
        'div',
        { class: 'flex flex-wrap gap-1' },
        levels.map((l) => h(Badge, { variant: 'outline', class: 'capitalize' }, () => l)),
      )
    },
  },
  {
    accessorKey: 'chapters_count',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          class: '-ml-2 text-xs hover:bg-muted/50',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Chapters', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
      ),
    cell: ({ row }) => String(row.original.chapters_count ?? 0),
  },
  {
    accessorKey: 'sort_order',
    header: 'Order',
    cell: ({ row }) => row.getValue('sort_order'),
  },
  {
    accessorKey: 'is_active',
    header: 'Active',
    cell: ({ row }) =>
      h(Switch, {
        checked: Boolean(row.original.is_active),
        'onUpdate:checked': (val: boolean) => toggleActive(row.original, val),
      }),
  },
  {
    id: 'actions',
    cell: ({ row }) => h(StructureActions, { structure: row.original }),
  },
]
