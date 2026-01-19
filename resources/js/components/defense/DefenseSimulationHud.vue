<script setup lang="ts">
import { ArrowLeft, History, Target } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

defineProps<{
    formattedSimulationTime: string
    timeLabel: string
    secondaryTimeLabel?: string
    secondaryTime?: string
    questionLimitLabel: string
}>()

const emit = defineEmits<{
    (e: 'stop'): void
}>()
</script>

<template>
    <div class="w-full max-w-6xl flex items-center justify-between mb-8">
        <div class="flex items-center gap-4">
            <div class="h-3 w-3 rounded-full bg-rose-600 animate-pulse"></div>
            <span class="text-xs font-bold tracking-[0.2em] text-zinc-500 uppercase">Live Simulation</span>
            <div class="flex items-center gap-2 bg-zinc-900 px-4 py-2 rounded-full border border-zinc-800">
                <History class="h-3.5 w-3.5 text-zinc-400" />
                <div class="flex flex-col leading-tight">
                    <span class="text-[10px] uppercase tracking-widest text-zinc-500">{{ timeLabel }}</span>
                    <span class="text-sm font-mono text-zinc-300">{{ formattedSimulationTime }}</span>
                    <span v-if="secondaryTime && secondaryTimeLabel"
                        class="text-[9px] uppercase tracking-widest text-zinc-600">
                        {{ secondaryTimeLabel }} {{ secondaryTime }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2 bg-zinc-900 px-4 py-2 rounded-full border border-zinc-800">
                <Target class="h-3.5 w-3.5 text-zinc-400" />
                <span class="text-xs text-zinc-300">Questions {{ questionLimitLabel }}</span>
            </div>
        </div>
        <Button variant="ghost" @click="emit('stop')"
            class="rounded-full text-zinc-400 hover:text-white hover:bg-zinc-900 gap-2">
            Finish & Analysis
            <ArrowLeft class="h-4 w-4 rotate-180" />
        </Button>
    </div>
</template>
