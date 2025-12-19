<script setup lang="ts">
import { inject } from 'vue'
import { MoreHorizontal, Pencil, Trash2, Folder } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps<{
  structure: any
}>()

const onEdit = inject<(structure: any) => void>('onEditFacultyStructure')

const deleteStructure = () => {
  if (!confirm(`Delete ${props.structure.faculty_name}? This will also delete chapters/sections.`)) return
  router.delete(route('admin.system.faculty-structures.destroy', { structure: props.structure.id }), {
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
      <DropdownMenuItem as-child>
        <Link :href="route('admin.system.faculty-structures.show', { structure: props.structure.id })">
          <Folder class="mr-2 h-4 w-4" />
          Manage Chapters
        </Link>
      </DropdownMenuItem>
      <DropdownMenuItem @click="onEdit?.(props.structure)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem @click="deleteStructure" class="text-rose-600 focus:text-rose-600 focus:bg-rose-50">
        <Trash2 class="mr-2 h-4 w-4" />
        Delete
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

