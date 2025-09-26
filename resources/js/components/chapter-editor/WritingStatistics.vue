<!-- /resources/js/components/chapter-editor/WritingStatistics.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { BookCheck, Target, TrendingUp, AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface WritingStats {
    sentences: number;
    paragraphs: number;
    readingTime: number;
    avgWordLength: number;
    uniqueWords: number;
    commonWords: string[];
}

interface QualityAnalysis {
    total_score?: number;
    quality_level?: string;
    meets_threshold?: boolean;
    scores?: {
        [key: string]: {
            score: number;
            max: number;
            percentage: number;
            status?: string; // 'disabled' for Grammar & Readability
        };
    };
    suggestions?: {
        [key: string]: string[];
    };
}

interface Props {
    showStatistics: boolean;
    currentWordCount: number;
    writingStats: WritingStats;
    qualityAnalysis?: QualityAnalysis | null;
    isAnalyzing?: boolean;
}

const props = defineProps<Props>();

// Quality level color mapping
const qualityLevelColor = computed(() => {
    if (!props.qualityAnalysis?.quality_level) return 'text-muted-foreground';

    switch (props.qualityAnalysis.quality_level) {
        case 'Excellent': return 'text-green-600';
        case 'Good': return 'text-blue-600';
        case 'Satisfactory': return 'text-yellow-600';
        case 'Needs Improvement': return 'text-orange-600';
        case 'Poor': return 'text-red-600';
        default: return 'text-muted-foreground';
    }
});

// Get areas needing improvement (exclude disabled categories)
const improvementAreas = computed(() => {
    if (!props.qualityAnalysis?.scores) return [];

    return Object.entries(props.qualityAnalysis.scores)
        .filter(([, data]) => data.max > 0) // Filter out disabled categories (max = 0)
        .filter(([, data]) => data.percentage < 75) // Only show areas that genuinely need improvement
        .sort(([,a], [,b]) => a.percentage - b.percentage)
        .map(([category, data]) => ({
            category: category.replace(' & ', ' & '),
            percentage: Math.round(data.percentage),
            score: data.score,
            max: data.max,
            status: data.percentage < 40 ? 'poor' : data.percentage < 60 ? 'needs-work' : 'fair'
        }));
});

// Get quality level description
const qualityDescription = computed(() => {
    const score = props.qualityAnalysis?.total_score || 0;
    if (score >= 80) return { text: 'Excellent', color: 'text-green-600', bgColor: 'bg-green-50' };
    if (score >= 70) return { text: 'Satisfactory', color: 'text-yellow-600', bgColor: 'bg-yellow-50' };
    if (score >= 60) return { text: 'Needs Work', color: 'text-orange-600', bgColor: 'bg-orange-50' };
    return { text: 'Poor', color: 'text-red-600', bgColor: 'bg-red-50' };
});

</script>

<template>
    <Card v-if="showStatistics" class="border border-border/50 bg-gradient-to-br from-card/80 to-card/60 backdrop-blur-sm shadow-lg">
        <!-- Quality Header -->
        <CardHeader v-if="qualityAnalysis" class="pb-4 border-b border-border/50">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-blue-500/10">
                        <BookCheck class="h-4 w-4 text-blue-600" />
                    </div>
                    <div>
                        <CardTitle class="text-sm font-semibold">Academic Quality</CardTitle>
                        <p class="text-xs text-muted-foreground">AI-powered analysis</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold" :class="qualityDescription.color">
                        {{ qualityAnalysis.total_score }}<span class="text-sm text-muted-foreground">/100</span>
                    </div>
                    <Badge :class="[qualityDescription.color, qualityDescription.bgColor]" variant="outline" class="text-xs font-medium">
                        {{ qualityDescription.text }}
                    </Badge>
                </div>
            </div>

            <!-- Quality Progress -->
            <div class="space-y-2">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-muted-foreground">Overall Score</span>
                    <span class="font-medium" :class="qualityAnalysis.meets_threshold ? 'text-green-600' : 'text-orange-600'">
                        {{ qualityAnalysis.meets_threshold ? 'Meets threshold ✓' : 'Below 80% threshold' }}
                    </span>
                </div>
                <div class="relative">
                    <Progress
                        :model-value="Math.round(qualityAnalysis.total_score || 0)"
                        class="h-2"
                        :class="{
                            '[&>[data-slot=progress-indicator]]:bg-green-500': (qualityAnalysis.total_score || 0) >= 80,
                            '[&>[data-slot=progress-indicator]]:bg-yellow-500': (qualityAnalysis.total_score || 0) >= 70 && (qualityAnalysis.total_score || 0) < 80,
                            '[&>[data-slot=progress-indicator]]:bg-orange-500': (qualityAnalysis.total_score || 0) >= 60 && (qualityAnalysis.total_score || 0) < 70,
                            '[&>[data-slot=progress-indicator]]:bg-red-500': (qualityAnalysis.total_score || 0) < 60
                        }"
                    />
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-0.5 h-4 bg-orange-400/60 rounded-full" style="margin-left: 80%"></div>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="p-4 space-y-4">

            <!-- Analysis Results -->
            <div v-if="qualityAnalysis?.scores">
                <h4 class="text-xs font-semibold text-muted-foreground mb-3 flex items-center gap-2">
                    <div class="w-1 h-4 bg-green-500 rounded-full"></div>
                    Analysis Results
                </h4>
                <div class="space-y-3">
                    <div
                        v-for="[category, data] in Object.entries(qualityAnalysis.scores).filter(([, data]) => data.max > 0)"
                        :key="category"
                        class="flex items-center justify-between p-3 rounded-lg bg-muted/30 hover:bg-muted/40 transition-colors"
                    >
                        <div class="flex-1">
                            <div class="text-sm font-medium">{{ category }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ data.score }}/{{ data.max }} points
                                <span class="ml-1" :class="{
                                    'text-red-500': data.percentage < 50,
                                    'text-orange-500': data.percentage >= 50 && data.percentage < 75,
                                    'text-blue-500': data.percentage >= 75 && data.percentage < 90,
                                    'text-green-500': data.percentage >= 90
                                }">
                                    ({{ Math.round(data.percentage) }}%)
                                </span>
                            </div>
                        </div>
                        <div class="w-20 h-2 bg-muted rounded-full overflow-hidden ml-3">
                            <div
                                class="h-full transition-all duration-500 rounded-full"
                                :class="{
                                    'bg-red-500': data.percentage < 50,
                                    'bg-orange-500': data.percentage >= 50 && data.percentage < 75,
                                    'bg-blue-500': data.percentage >= 75 && data.percentage < 90,
                                    'bg-green-500': data.percentage >= 90
                                }"
                                :style="{ width: `${data.percentage}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Areas for Improvement -->
            <div v-if="improvementAreas.length > 0">
                <h4 class="text-xs font-semibold text-muted-foreground mb-3 flex items-center gap-2">
                    <div class="w-1 h-4 bg-orange-500 rounded-full"></div>
                    Focus Areas
                </h4>
                <div class="space-y-2">
                    <div v-for="area in improvementAreas" :key="area.category"
                         class="flex items-center gap-3 p-3 rounded-lg border border-orange-200/50 bg-orange-50/30">
                        <AlertCircle class="h-4 w-4 text-orange-500 flex-shrink-0" />
                        <div class="flex-1">
                            <div class="text-sm font-medium">{{ area.category }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ area.score }}/{{ area.max }} points -
                                <span :class="{
                                    'text-red-600': area.status === 'poor',
                                    'text-orange-600': area.status === 'needs-work',
                                    'text-yellow-600': area.status === 'fair'
                                }">
                                    {{
                                        area.status === 'poor' ? 'needs significant improvement' :
                                        area.status === 'needs-work' ? 'needs improvement' :
                                        'fair, could be better'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isAnalyzing" class="flex items-center justify-center gap-3 p-4 rounded-lg bg-blue-50/50 border border-blue-200/50">
                <TrendingUp class="h-4 w-4 text-blue-500 animate-pulse" />
                <span class="text-sm text-blue-700 font-medium">Analyzing academic quality with AI...</span>
            </div>

            <!-- No Analysis State -->
            <div v-if="!qualityAnalysis && !isAnalyzing" class="text-center p-4 rounded-lg bg-muted/30">
                <BookCheck class="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                <p class="text-sm text-muted-foreground">Click "Analyze" to get AI-powered quality insights</p>
            </div>
        </CardContent>
    </Card>
</template>
