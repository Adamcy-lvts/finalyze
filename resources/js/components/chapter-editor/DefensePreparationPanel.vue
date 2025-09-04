<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { AlertCircle, AlertTriangle, CheckCircle, ChevronDown, Download, FileQuestion, HelpCircle, Lightbulb, Shield } from 'lucide-vue-next';

interface Props {
    showDefensePrep: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:showDefensePrep': [value: boolean];
}>();

// Mock data - this would come from props or API in real implementation
const defenseQuestions = [
    {
        question: 'How does your methodology in Chapter 3 address the limitations identified in your literature review?',
        tip: 'Reference specific methodological choices and link to Ch.2 findings',
    },
    {
        question: 'What alternative approaches did you consider and why did you reject them?',
        tip: 'Prepare 2-3 alternatives with clear justification',
    },
    {
        question: 'How does your sample size affect the generalizability of your findings?',
        tip: 'Acknowledge limitations but emphasize depth over breadth',
    },
];

const weakPoints = [
    'Limited discussion of ethical considerations',
    'Need more recent citations (2023-2024)',
    'Statistical analysis explanation could be clearer',
];

const defenseTips = [
    {
        type: 'Confidence Builder',
        text: 'Your methodology section is well-structured. Practice explaining your 3-phase approach clearly.',
    },
    {
        type: 'Key Strength',
        text: "Your literature synthesis shows deep understanding. Emphasize novel connections you've identified.",
    },
    {
        type: 'Practice Point',
        text: 'Be ready to explain Figure 3.2 in detail - examiners often focus on visual data.',
    },
];

const handleGenerateMoreQuestions = () => {
    // Placeholder for generating more questions
    console.log('Generating more defense questions...');
};

const handleExportPrepGuide = () => {
    // Placeholder for exporting prep guide
    console.log('Exporting defense preparation guide...');
};
</script>

<template>
    <Card class="border-[0.5px] border-border/50">
        <Collapsible :open="showDefensePrep" @update:open="emit('update:showDefensePrep', $event)">
            <CollapsibleTrigger class="w-full">
                <CardHeader class="pb-3 transition-colors hover:bg-muted/30">
                    <CardTitle class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2">
                            <Shield class="h-4 w-4 text-muted-foreground" />
                            Defense Preparation
                            <Badge variant="secondary" class="ml-1 text-xs"> AI-Powered </Badge>
                        </span>
                        <ChevronDown :class="['h-4 w-4 text-muted-foreground transition-transform', showDefensePrep ? 'rotate-180' : '']" />
                    </CardTitle>
                </CardHeader>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <CardContent class="space-y-4 pt-0">
                    <!-- Potential Questions Section -->
                    <div class="space-y-2">
                        <Label class="flex items-center gap-1 text-xs font-semibold">
                            <HelpCircle class="h-3 w-3" />
                            Potential Defense Questions
                        </Label>
                        <ScrollArea class="h-[200px]">
                            <div class="space-y-2 pr-2">
                                <Alert v-for="(item, index) in defenseQuestions" :key="index" class="py-2">
                                    <AlertCircle class="h-3 w-3 text-muted-foreground" />
                                    <AlertDescription class="ml-1 text-xs">
                                        <strong>Q{{ index + 1 }}:</strong> "{{ item.question }}"
                                        <div class="mt-1 text-xs text-muted-foreground"><strong>Tip:</strong> {{ item.tip }}</div>
                                    </AlertDescription>
                                </Alert>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- Weak Points Analysis -->
                    <Separator />
                    <div class="space-y-2">
                        <Label class="flex items-center gap-1 text-xs font-semibold">
                            <AlertTriangle class="h-3 w-3 text-muted-foreground" />
                            Areas to Strengthen
                        </Label>
                        <div class="space-y-1">
                            <div v-for="point in weakPoints" :key="point" class="flex items-center gap-2 text-xs">
                                <div class="h-2 w-2 rounded-full bg-yellow-500"></div>
                                <span>{{ point }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Defense Tips -->
                    <Separator />
                    <div class="space-y-2">
                        <Label class="flex items-center gap-1 text-xs font-semibold">
                            <Lightbulb class="h-3 w-3 text-muted-foreground" />
                            Defense Tips
                        </Label>
                        <div class="space-y-2 rounded-lg bg-muted/30 p-3">
                            <div v-for="tip in defenseTips" :key="tip.type" class="flex items-start gap-2">
                                <CheckCircle class="mt-0.5 h-3 w-3 flex-shrink-0 text-muted-foreground" />
                                <p class="text-xs">
                                    <strong>{{ tip.type }}:</strong> {{ tip.text }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-2">
                        <Button @click="handleGenerateMoreQuestions" size="sm" variant="outline" class="text-xs">
                            <FileQuestion class="mr-1 h-3 w-3" />
                            Generate More Q's
                        </Button>
                        <Button @click="handleExportPrepGuide" size="sm" variant="outline" class="text-xs">
                            <Download class="mr-1 h-3 w-3" />
                            Export Prep Guide
                        </Button>
                    </div>
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>
</template>
