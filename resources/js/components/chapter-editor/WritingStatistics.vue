<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

interface WritingStats {
    sentences: number;
    paragraphs: number;
    readingTime: number;
    avgWordLength: number;
    uniqueWords: number;
    commonWords: string[];
}

interface Props {
    showStatistics: boolean;
    currentWordCount: number;
    writingStats: WritingStats;
}

defineProps<Props>();
</script>

<template>
    <Card v-if="showStatistics" class="border-[0.5px] border-border/50 bg-card/50 backdrop-blur">
        <CardContent class="p-4">
            <div class="grid grid-cols-6 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary">{{ currentWordCount }}</div>
                    <div class="text-xs text-muted-foreground">Words</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ writingStats.sentences }}</div>
                    <div class="text-xs text-muted-foreground">Sentences</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ writingStats.paragraphs }}</div>
                    <div class="text-xs text-muted-foreground">Paragraphs</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ writingStats.readingTime }}</div>
                    <div class="text-xs text-muted-foreground">Min Read</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ writingStats.uniqueWords }}</div>
                    <div class="text-xs text-muted-foreground">Unique Words</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ Math.round(writingStats.avgWordLength) }}</div>
                    <div class="text-xs text-muted-foreground">Avg Word Length</div>
                </div>
            </div>

            <!-- Most Common Words -->
            <div v-if="writingStats.commonWords.length > 0" class="mt-4 border-t pt-4">
                <div class="mb-2 text-xs text-muted-foreground">Most Common Words:</div>
                <div class="flex gap-2">
                    <Badge v-for="word in writingStats.commonWords" :key="word" variant="secondary">
                        {{ word }}
                    </Badge>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
