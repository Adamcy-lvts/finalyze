<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button variant="outline" size="sm" class="gap-2">
                <BracesIcon class="h-4 w-4" />
                Insert Variable
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[400px] p-0" align="start">
            <Command>
                <CommandInput placeholder="Search variables..." />
                <CommandList>
                    <CommandEmpty>No variables found.</CommandEmpty>
                    <CommandGroup heading="Student Information">
                        <CommandItem v-for="variable in studentVariables" :key="variable.name" :value="variable.name"
                            @select="handleSelect(variable.name)">
                            <span class="font-mono text-sm" v-text="'{{' + variable.name + '}}'"></span>
                            <span class="ml-2 text-muted-foreground">{{ variable.description }}</span>
                        </CommandItem>
                    </CommandGroup>
                    <CommandSeparator />
                    <CommandGroup heading="Project Information">
                        <CommandItem v-for="variable in projectVariables" :key="variable.name" :value="variable.name"
                            @select="handleSelect(variable.name)">
                            <span class="font-mono text-sm" v-text="'{{' + variable.name + '}}'"></span>
                            <span class="ml-2 text-muted-foreground">{{ variable.description }}</span>
                        </CommandItem>
                    </CommandGroup>
                    <CommandSeparator />
                    <CommandGroup heading="Institution Information">
                        <CommandItem v-for="variable in institutionVariables" :key="variable.name"
                            :value="variable.name" @select="handleSelect(variable.name)">
                            <span class="font-mono text-sm" v-text="'{{' + variable.name + '}}'"></span>
                            <span class="ml-2 text-muted-foreground">{{ variable.description }}</span>
                        </CommandItem>
                    </CommandGroup>
                    <CommandSeparator />
                    <CommandGroup heading="Other">
                        <CommandItem v-for="variable in otherVariables" :key="variable.name" :value="variable.name"
                            @select="handleSelect(variable.name)">
                            <span class="font-mono text-sm" v-text="'{{' + variable.name + '}}'"></span>
                            <span class="ml-2 text-muted-foreground">{{ variable.description }}</span>
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command'
import { BracesIcon } from 'lucide-vue-next'

const emit = defineEmits<{
    variableSelected: [variableName: string]
}>()

const open = ref(false)

// Available template variables grouped by category
const studentVariables = [
    { name: 'student_name', description: "Student's full name" },
    { name: 'student_id', description: 'Student ID number' },
    { name: 'matric_number', description: 'Matriculation number' },
    { name: 'department', description: 'Department name' },
    { name: 'academic_session', description: 'Academic session (e.g., 2023/2024)' },
]

const projectVariables = [
    { name: 'project_title', description: 'Project title' },
    { name: 'project_topic', description: 'Project topic' },
    { name: 'project_type', description: 'Project type (e.g., Thesis, Dissertation)' },
    { name: 'field_of_study', description: 'Field of study' },
    { name: 'degree', description: 'Degree being pursued' },
    { name: 'degree_abbreviation', description: 'Degree abbreviation (e.g., B.Sc., M.Sc.)' },
    { name: 'supervisor_name', description: "Supervisor's name" },
]

const institutionVariables = [
    { name: 'university', description: 'University short name' },
    { name: 'full_university_name', description: 'University full name' },
    { name: 'faculty', description: 'Faculty name' },
    { name: 'course', description: 'Course of study' },
]

const otherVariables = [
    { name: 'current_year', description: 'Current year' },
    { name: 'submission_date', description: 'Project submission date' },
]

const handleSelect = (variableName: string) => {
    emit('variableSelected', variableName)
    open.value = false
}
</script>
