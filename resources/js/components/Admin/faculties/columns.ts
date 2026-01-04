import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Switch } from '@/components/ui/switch'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import FacultyActions from './FacultyActions.vue'
import { ArrowUpDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

export type FacultyRow = {
    id: number
    name: string
    slug: string
    description: string | null
    faculty_structure_id: number | null
    faculty_structure_name: string | null
    sort_order: number
    is_active: boolean
    created_at?: string
}

const toggleActive = (faculty: FacultyRow, val: boolean) => {
    router.put(route('admin.system.faculties.toggle-active', { faculty: faculty.id }), { is_active: val }, {
        preserveScroll: true,
    })
}

export const facultyColumns: ColumnDef<FacultyRow>[] = [
    {
        accessorKey: 'name',
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    class: '-ml-2 text-xs hover:bg-muted/50',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Name', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
            ),
        cell: ({ row }) => {
            const name = row.getValue('name') as string
            const slug = row.original.slug
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-medium' }, name),
                h('span', { class: 'text-xs text-muted-foreground' }, slug),
            ])
        },
    },
    {
        accessorKey: 'faculty_structure_name',
        header: 'Structure',
        cell: ({ row }) => row.original.faculty_structure_name || '—',
    },
    {
        accessorKey: 'sort_order',
        header: 'Order',
        cell: ({ row }) => row.getValue('sort_order'),
    },
    {
        accessorKey: 'is_active',
        header: 'Active',
        cell: ({ row }) => {
            return h(Switch, {
                checked: row.original.is_active,
                'onUpdate:checked': (val: boolean) => toggleActive(row.original, val),
            })
        },
    },
    {
        id: 'actions',
        cell: ({ row }) => h(FacultyActions, { faculty: row.original }),
    },
]
