import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import CategoryActions from './CategoryActions.vue'
import { ArrowUpDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

export type ProjectCategoryRow = {
    id: number
    name: string
    slug: string
    academic_levels: string[]
    description: string
    default_chapter_count: number
    chapter_structure: any[]
    target_word_count: number | null
    target_duration: string | null
    sort_order: number
    is_active: boolean
    created_at?: string
}

const toggleActive = (category: ProjectCategoryRow, val: boolean) => {
    router.put(route('admin.system.project-categories.toggle-active', { category: category.slug }), { is_active: val }, {
        preserveScroll: true,
    })
}

export const projectCategoryColumns: ColumnDef<ProjectCategoryRow>[] = [
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
        accessorKey: 'academic_levels',
        header: 'Levels',
        cell: ({ row }) => {
            const levels = row.original.academic_levels || []
            if (!levels.length) return '—'
            return h('div', { class: 'flex flex-wrap gap-1' }, levels.map((level) =>
                h(Badge, { variant: 'outline', class: 'capitalize text-[10px]' }, () => level),
            ))
        },
    },
    {
        accessorKey: 'default_chapter_count',
        header: 'Chapters',
        cell: ({ row }) => row.getValue('default_chapter_count'),
    },
    {
        accessorKey: 'target_word_count',
        header: 'Target Words',
        cell: ({ row }) => row.getValue('target_word_count') ?? '—',
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
        cell: ({ row }) => h(CategoryActions, { category: row.original }),
    },
]
