<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'

interface SimulationPersona {
    id: string
    name: string
    role: string
    avatar: string
}

defineProps<{
    activeSimulationPersonas: SimulationPersona[]
    isFetchingQuestion: boolean
    thinkingPersonaId: string | null
    panelistQuestionCounts: Record<string, number>
    panelistQuestionTarget: Record<string, number>
    clarityScore: number
    depthScore: number
    confidenceScore: number
}>()
</script>

<template>
    <div class="lg:col-span-4 space-y-4 md:space-y-6 overflow-y-auto pr-2 custom-scrollbar">
        <h3 class="text-[10px] md:text-sm font-bold text-zinc-500 uppercase tracking-widest px-2">The Panel</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-4 md:gap-6">
            <Card v-for="persona in activeSimulationPersonas" :key="persona.id"
                class="bg-zinc-900/50 border-zinc-800 hover:border-zinc-700 transition-all rounded-3xl">
                <CardContent class="p-4 md:p-6">
                    <div class="flex items-center gap-3 md:gap-4">
                        <div
                            class="h-10 w-10 md:h-12 md:w-12 rounded-2xl bg-zinc-800 flex items-center justify-center text-xl md:text-2xl">
                            {{ persona.avatar }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-zinc-200 text-sm md:text-base truncate">{{ persona.name
                                    }}</span>
                                <Badge
                                    v-if="isFetchingQuestion && (!thinkingPersonaId || thinkingPersonaId === persona.id)"
                                    class="bg-amber-500/10 text-amber-500 border-none text-[8px] h-4 shrink-0">
                                    THINKING</Badge>
                            </div>
                            <span class="text-[10px] md:text-xs text-zinc-500 truncate">{{ persona.role }}</span>
                            <div class="text-[10px] md:text-xs text-zinc-600">
                                Questions asked: {{ panelistQuestionCounts[persona.id] ?? 0 }} / {{
                                    panelistQuestionTarget[persona.id] ?? 0 }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card
            class="bg-zinc-900 border-none rounded-3xl p-4 md:p-6 mt-6 md:mt-12 bg-gradient-to-br from-zinc-900 to-black">
            <h4 class="text-[10px] md:text-xs font-bold text-zinc-500 mb-4 uppercase">Live Performance</h4>
            <div class="grid grid-cols-3 lg:grid-cols-1 gap-4 md:gap-6">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[10px] md:text-xs">
                        <span class="text-zinc-400">Clarity</span>
                        <span class="text-zinc-200">{{ clarityScore }}%</span>
                    </div>
                    <Progress :model-value="clarityScore" class="h-1 bg-zinc-800"
                        indicator-class="bg-blue-500" />
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[10px] md:text-xs">
                        <span class="text-zinc-400">Depth</span>
                        <span class="text-zinc-200">{{ depthScore }}%</span>
                    </div>
                    <Progress :model-value="depthScore" class="h-1 bg-zinc-800"
                        indicator-class="bg-emerald-500" />
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[10px] md:text-xs">
                        <span class="text-zinc-400">Confidence</span>
                        <span class="text-zinc-200">{{ confidenceScore }}%</span>
                    </div>
                    <Progress :model-value="confidenceScore" class="h-1 bg-zinc-800"
                        indicator-class="bg-rose-500" />
                </div>
            </div>
        </Card>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #27272a;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #3f3f46;
}
</style>
