<!-- resources/js/Components/WizardDebugPanel.vue -->
<!-- Add this component to your wizard page during development to monitor state -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Download, Eye, EyeOff, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    currentStep: number;
    projectId: number | null;
    formValues: Record<string, any>;
    savedValues: Record<string, any>;
    isInitializing: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'force-save'): void;
    (e: 'reset-form'): void;
}>();
const showDebug = ref(false);
const autoRefresh = ref(true);

// Computed to check if values match
const valuesInSync = computed(() => {
    return JSON.stringify(props.formValues) === JSON.stringify(props.savedValues);
});

// Get unsaved fields
const unsavedFields = computed(() => {
    const fields: string[] = [];
    for (const key in props.formValues) {
        if (props.formValues[key] !== props.savedValues[key]) {
            fields.push(key);
        }
    }
    return fields;
});

// Export debug data
const exportDebugData = () => {
    const debugData = {
        timestamp: new Date().toISOString(),
        currentStep: props.currentStep,
        projectId: props.projectId,
        formValues: props.formValues,
        savedValues: props.savedValues,
        unsavedFields: unsavedFields.value,
        localStorage: {},
    };

    // Add localStorage data
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.includes('project')) {
            debugData.localStorage[key] = localStorage.getItem(key);
        }
    }

    const blob = new Blob([JSON.stringify(debugData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `wizard-debug-${Date.now()}.json`;
    a.click();
};

// Clear all wizard data
const clearAllData = () => {
    if (confirm('This will clear all saved wizard data. Are you sure?')) {
        // Clear localStorage
        const keys = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.includes('project')) {
                keys.push(key);
            }
        }
        keys.forEach((key) => localStorage.removeItem(key));

        // Reload page
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    }
};

// Watch for changes
watch(
    () => props.formValues,
    () => {
        if (autoRefresh.value) {
            // Log changes to console
            console.log('Form values changed:', props.formValues);
        }
    },
    { deep: true },
);
</script>

<template>
    <!-- Toggle Button -->
    <div class="fixed right-4 bottom-4 z-50">
        <Button @click="showDebug = !showDebug" size="icon" variant="outline" class="bg-background shadow-lg">
            <Eye v-if="!showDebug" class="h-4 w-4" />
            <EyeOff v-else class="h-4 w-4" />
        </Button>
    </div>

    <!-- Debug Panel -->
    <Transition name="slide">
        <Card v-if="showDebug" class="fixed right-4 bottom-20 z-40 max-h-[600px] w-96 overflow-auto shadow-xl">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-medium">Wizard Debug Panel</CardTitle>
                    <div class="flex gap-1">
                        <Button size="icon" variant="ghost" class="h-6 w-6" @click="exportDebugData">
                            <Download class="h-3 w-3" />
                        </Button>
                        <Button size="icon" variant="ghost" class="h-6 w-6" @click="clearAllData">
                            <Trash2 class="h-3 w-3" />
                        </Button>
                        <Button size="icon" variant="ghost" class="h-6 w-6" @click="autoRefresh = !autoRefresh">
                            <RefreshCw :class="['h-3 w-3', autoRefresh && 'animate-spin']" />
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4 text-xs">
                <!-- Status Section -->
                <div class="space-y-2">
                    <div class="font-semibold">Status</div>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Current Step:</span>
                            <Badge variant="outline">{{ currentStep }}</Badge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Project ID:</span>
                            <Badge variant="outline">{{ projectId || 'Not created' }}</Badge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Initializing:</span>
                            <Badge :variant="isInitializing ? 'destructive' : 'default'">
                                {{ isInitializing ? 'Yes' : 'No' }}
                            </Badge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Values Synced:</span>
                            <Badge :variant="valuesInSync ? 'default' : 'destructive'">
                                {{ valuesInSync ? 'Yes' : 'No' }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <!-- Unsaved Fields -->
                <div v-if="unsavedFields.length > 0" class="space-y-2">
                    <div class="font-semibold text-amber-600">Unsaved Fields</div>
                    <div class="space-y-1">
                        <Badge v-for="field in unsavedFields" :key="field" variant="destructive" class="mr-1 mb-1">
                            {{ field }}
                        </Badge>
                    </div>
                </div>

                <!-- Form Values -->
                <div class="space-y-2">
                    <div class="font-semibold">Current Form Values</div>
                    <div class="max-h-40 overflow-auto rounded bg-muted p-2 font-mono text-[10px]">
                        <pre>{{ JSON.stringify(formValues, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Saved Values -->
                <div class="space-y-2">
                    <div class="font-semibold">Saved Values</div>
                    <div class="max-h-40 overflow-auto rounded bg-muted p-2 font-mono text-[10px]">
                        <pre>{{ JSON.stringify(savedValues, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Actions -->
                <div class="space-y-2">
                    <div class="font-semibold">Debug Actions</div>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" @click="emit('force-save')"> Force Save </Button>
                        <Button size="sm" variant="outline" @click="emit('reset-form')"> Reset Form </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </Transition>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s ease-out;
}

.slide-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.slide-leave-to {
    transform: translateX(100%);
    opacity: 0;
}
</style>
