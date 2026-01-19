<script setup lang="ts">
import { Play, Target, Users } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

interface SimulationPersona {
    id: string
    name: string
    role: string
    avatar: string
}

defineProps<{
    plannedQuestionLimit: number
    activeSimulationPersonas: SimulationPersona[]
    isStarting: boolean
    highlight: boolean
}>()

const emit = defineEmits<{
    (e: 'start'): void
}>()
</script>

<template>
    <div class="flex flex-col items-center justify-center py-20 animate-in zoom-in-95 duration-700"
        :class="highlight ? 'ring-2 ring-primary/40 shadow-[0_0_0_6px_rgba(14,165,233,0.15)] rounded-[2.5rem]' : ''">
        <div class="relative mb-8">
            <div
                class="absolute -inset-4 bg-gradient-to-r from-primary to-purple-500 rounded-full opacity-20 blur-2xl animate-pulse">
            </div>
            <Users class="h-24 w-24 text-primary relative z-10" />
        </div>
        <h2 class="text-3xl font-bold mb-4">The Mock Defense</h2>
        <p class="max-w-md text-center text-muted-foreground mb-12">
            Face a panel of AI examiners who will challenge your research from different perspectives.
            Record your session for post-defense feedback.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-5xl mb-12">
            <Card class="border-border/50 bg-card/50 backdrop-blur-sm rounded-3xl overflow-hidden">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base flex items-center gap-2">
                        <Users class="h-4 w-4 text-primary" />
                        How the simulation works
                    </CardTitle>
                    <CardDescription>Chair-moderated rotation with real-time feedback.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-muted-foreground">
                    <p>1) The chair opens, then panelists rotate with targeted questions.</p>
                    <p>2) You respond; the system scores clarity and technical depth.</p>
                    <p>3) Hints guide you before answers are revealed.</p>
                    <p>4) When the question limit is reached, the session auto-completes and scores.</p>
                    <p class="text-xs text-muted-foreground/80">Total questions: {{ plannedQuestionLimit }}.
                        Each question is worth 10 marks (100 total).</p>
                </CardContent>
            </Card>
            <Card class="border-border/50 bg-card/50 backdrop-blur-sm rounded-3xl overflow-hidden">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base flex items-center gap-2">
                        <Target class="h-4 w-4 text-emerald-500" />
                        Scoring breakdown
                    </CardTitle>
                    <CardDescription>Readiness is a weighted summary of core metrics.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <span>Per-question mark</span>
                        <span class="text-foreground font-semibold">10 marks total</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Derived from</span>
                        <span class="text-foreground font-semibold">Clarity + Depth</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Coverage, confidence, and response time</span>
                        <span class="text-foreground font-semibold">Shown as diagnostics</span>
                    </div>
                    <div class="text-xs text-muted-foreground/80">
                        10 questions x 10 marks each = 100 total marks. Readiness uses clarity/depth averages.
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-4xl mb-12">
            <Card v-for="persona in activeSimulationPersonas" :key="persona.id"
                class="border-border/50 bg-card/50 backdrop-blur-sm rounded-3xl overflow-hidden hover:border-primary/30 transition-all">
                <CardContent class="p-8 text-center space-y-3">
                    <div class="text-4xl mb-4">{{ persona.avatar }}</div>
                    <h4 class="font-bold">{{ persona.name }}</h4>
                    <Badge variant="secondary" class="text-[10px]">{{ persona.role }}</Badge>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        {{ persona.name === 'The Skeptic' ? 'High pressure, doubts your findings.' :
                            persona.name === 'The Methodologist' ? 'Strict on research design and ethics.' :
                                'Focuses on the bigger picture.' }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Button size="lg" @click="emit('start')" :disabled="isStarting"
            class="px-12 py-8 text-xl font-bold rounded-3xl shadow-2xl shadow-primary/20 gap-4 hover:scale-105 transition-all">
            <Play class="h-6 w-6 fill-current" />
            START SIMULATION
        </Button>
    </div>
</template>
