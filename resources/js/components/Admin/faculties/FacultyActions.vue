<script setup lang="ts">
import { inject } from 'vue'
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps<{
    faculty: any
}>()

const onEdit = inject<(faculty: any) => void>('onEditFaculty')

const deleteFaculty = () => {
    if (!confirm(`Delete ${props.faculty.name}?`)) return
    router.delete(route('admin.system.faculties.destroy', { faculty: props.faculty.id }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" class="h-8 w-8 p-0">
                <span class="sr-only">Open menu</span>
                <MoreHorizontal class="h-4 w-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuLabel>Actions</DropdownMenuLabel>
            <DropdownMenuItem @click="onEdit?.(props.faculty)">
                <Pencil class="mr-2 h-4 w-4" />
                Edit
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="deleteFaculty" class="text-rose-600 focus:text-rose-600 focus:bg-rose-50">
                <Trash2 class="mr-2 h-4 w-4" />
                Delete
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
