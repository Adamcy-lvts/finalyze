import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import PackageActions from './PackageActions.vue'
import { ArrowUpDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

export type PackageRow = {
    id: number
    name: string
    slug: string
    type: 'project' | 'topup'
    tier: string | null
    words: number
    formatted_words: string
    price: number
    formatted_price: string
    currency: string
    description: string | null
    features: string[]
    sort_order: number
    is_active: boolean
    is_popular: boolean
    created_at: string
}

const toggleActive = (pkg: PackageRow, val: boolean) => {
    router.put(route('admin.packages.toggle-active', { package: pkg.id }), { is_active: val }, {
        preserveScroll: true,
    })
}

const togglePopular = (pkg: PackageRow, val: boolean) => {
    router.put(route('admin.packages.toggle-popular', { package: pkg.id }), { is_popular: val }, {
        preserveScroll: true,
    })
}

export const packageColumns: ColumnDef<PackageRow>[] = [
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
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => {
            const type = row.getValue('type') as string
            return h(Badge, { variant: type === 'project' ? 'default' : 'outline', class: 'capitalize' }, () => type)
        },
    },
    {
        accessorKey: 'price',
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    class: '-ml-2 text-xs hover:bg-muted/50',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Price', h(ArrowUpDown, { class: 'ml-2 h-3 w-3' })],
            ),
        cell: ({ row }) => row.original.formatted_price,
    },
    {
        accessorKey: 'words',
        header: 'Words',
        cell: ({ row }) => row.original.formatted_words,
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
        accessorKey: 'is_popular',
        header: 'Popular',
        cell: ({ row }) => {
            return h(Switch, {
                checked: row.original.is_popular,
                'onUpdate:checked': (val: boolean) => togglePopular(row.original, val),
            })
        },
    },
    {
        id: 'actions',
        cell: ({ row }) => h(PackageActions, { package: row.original }),
    },
]
