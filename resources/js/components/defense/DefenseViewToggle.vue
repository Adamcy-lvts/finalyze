<script setup lang="ts">
import { Zap, Users } from 'lucide-vue-next'

const props = defineProps<{
    modelValue: 'preparation' | 'simulation'
    isSimulating: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: 'preparation' | 'simulation'): void
}>()
</script>

<template>
    <div v-show="!isSimulating"
        class="flex p-1 rounded-2xl bg-muted/50 border border-border/50 mb-8 w-full sm:w-auto sm:inline-flex">
        <button @click="emit('update:modelValue', 'preparation')"
            class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 md:px-6 py-2 rounded-xl text-sm font-medium transition-all"
            :class="modelValue === 'preparation' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'">
            <Zap class="h-4 w-4" />
            <span class="hidden sm:inline">Preparation Suite</span>
            <span class="sm:hidden">Prep</span>
        </button>
        <button @click="emit('update:modelValue', 'simulation')"
            class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 md:px-6 py-2 rounded-xl text-sm font-medium transition-all"
            :class="modelValue === 'simulation' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'">
            <Users class="h-4 w-4" />
            <span class="hidden sm:inline">Simulation Lab</span>
            <span class="sm:hidden">Sim</span>
        </button>
    </div>
</template>
