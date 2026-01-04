import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import UniversityActions from './UniversityActions.vue'
import { ArrowUpDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

export type UniversityRow = {
    id: number
    name: string
    short_name: string | null
    slug: string
    type: string
    location: string | null
    state: string | null
    country: string | null
    website: string | null
    description: string | null
    sort_order: number
    is_active: boolean
    created_at?: string
}

const toggleActive = (university: UniversityRow, val: boolean) => {
    router.put(route('admin.system.universities.toggle-active', { university: university.id }), { is_active: val }, {
        preserveScroll: true,
    })
}

export const universityColumns: ColumnDef<UniversityRow>[] = [
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
            const shortName = row.original.short_name
            const slug = row.original.slug
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-medium' }, name),
                h('span', { class: 'text-xs text-muted-foreground' }, shortName ? `${shortName} · ${slug}` : slug),
            ])
        },
    },
    {
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => {
            const type = row.getValue('type') as string
            return h(Badge, { variant: 'outline', class: 'capitalize' }, () => type)
        },
    },
    {
        accessorKey: 'state',
        header: 'State',
        cell: ({ row }) => row.getValue('state') || '—',
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
        cell: ({ row }) => h(UniversityActions, { university: row.original }),
    },
]
