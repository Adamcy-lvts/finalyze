<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { useAppearance } from '@/composables/useAppearance'
import { Button } from '@/components/ui/button'
import { Moon, Sun } from 'lucide-vue-next'
import { ref, onMounted, watch } from 'vue'

interface Props {
    heavyData?: any
    dataSize?: number
    insideMiddleware?: boolean
}

const props = defineProps<Props>()

const { appearance, updateAppearance, isDark, toggle } = useAppearance()

// Debug info
const debugInfo = ref({
    htmlHasDark: false,
    localStorageValue: '',
    appearanceRef: '',
    isDarkValue: false,
})

function updateDebugInfo() {
    if (typeof window !== 'undefined') {
        debugInfo.value = {
            htmlHasDark: document.documentElement.classList.contains('dark'),
            localStorageValue: localStorage.getItem('appearance') || 'not set',
            appearanceRef: appearance.value,
            isDarkValue: isDark.value,
        }
    }
}

onMounted(() => {
    updateDebugInfo()
    // Update every 500ms to track changes
    setInterval(updateDebugInfo, 500)
})

watch([appearance, isDark], () => {
    updateDebugInfo()
})

function setLight() {
    updateAppearance('light')
}

function setDark() {
    updateAppearance('dark')
}

function setSystem() {
    updateAppearance('system')
}

// Format bytes for display
function formatBytes(bytes: number): string {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}
</script>

<template>
    <Head title="Theme Test (Heavy Data)" />
    
    <div class="min-h-screen bg-background text-foreground p-8">
        <div class="max-w-4xl mx-auto space-y-8">
            <!-- Header -->
            <div class="text-center">
                <h1 class="text-3xl font-bold mb-2">Theme Test Page (Heavy Data)</h1>
                <p class="text-muted-foreground">Testing dark mode with heavy Inertia props</p>
                <div v-if="insideMiddleware" class="mt-2 inline-block px-3 py-1 rounded-full bg-orange-500/20 text-orange-500 text-sm font-medium">
                    Inside ProjectStateMiddleware
                </div>
                <div v-else class="mt-2 inline-block px-3 py-1 rounded-full bg-green-500/20 text-green-500 text-sm font-medium">
                    Outside ProjectStateMiddleware
                </div>
            </div>

            <!-- Data Info -->
            <div class="p-6 rounded-lg border border-border bg-card text-card-foreground">
                <h2 class="text-xl font-semibold mb-4">Heavy Data Load</h2>
                <div class="space-y-2 font-mono text-sm">
                    <div class="flex justify-between">
                        <span>Data Size:</span>
                        <span class="text-primary">{{ formatBytes(dataSize || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Has Project:</span>
                        <span :class="heavyData?.project ? 'text-green-500' : 'text-red-500'">
                            {{ heavyData?.project ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Chapters Count:</span>
                        <span class="text-primary">{{ heavyData?.allChapters?.length || 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Projects Count:</span>
                        <span class="text-primary">{{ heavyData?.allProjects?.length || 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Sample Nested Items:</span>
                        <span class="text-primary">{{ heavyData?.sampleData?.nestedData?.length || 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Debug Info Card -->
            <div class="p-6 rounded-lg border border-border bg-card text-card-foreground">
                <h2 class="text-xl font-semibold mb-4">Debug Information</h2>
                <div class="space-y-2 font-mono text-sm">
                    <div class="flex justify-between">
                        <span>html.classList.contains('dark'):</span>
                        <span :class="debugInfo.htmlHasDark ? 'text-green-500' : 'text-red-500'">
                            {{ debugInfo.htmlHasDark }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>localStorage.appearance:</span>
                        <span class="text-primary">{{ debugInfo.localStorageValue }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>appearance ref:</span>
                        <span class="text-primary">{{ debugInfo.appearanceRef }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>isDark computed:</span>
                        <span :class="debugInfo.isDarkValue ? 'text-green-500' : 'text-red-500'">
                            {{ debugInfo.isDarkValue }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Theme Controls -->
            <div class="p-6 rounded-lg border border-border bg-card text-card-foreground">
                <h2 class="text-xl font-semibold mb-4">Theme Controls</h2>
                <div class="flex gap-4 flex-wrap">
                    <Button @click="setLight" :variant="appearance === 'light' ? 'default' : 'outline'">
                        <Sun class="w-4 h-4 mr-2" />
                        Light
                    </Button>
                    <Button @click="setDark" :variant="appearance === 'dark' ? 'default' : 'outline'">
                        <Moon class="w-4 h-4 mr-2" />
                        Dark
                    </Button>
                    <Button @click="setSystem" :variant="appearance === 'system' ? 'default' : 'outline'">
                        System
                    </Button>
                    <Button @click="toggle" variant="secondary">
                        Toggle (current: {{ isDark ? 'Dark' : 'Light' }})
                    </Button>
                </div>
            </div>

            <!-- Test Areas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Test Card 1 -->
                <div class="p-6 rounded-lg border border-border bg-background">
                    <h3 class="font-semibold mb-2">bg-background</h3>
                    <p class="text-sm text-muted-foreground">This box uses bg-background class</p>
                </div>

                <!-- Test Card 2 -->
                <div class="p-6 rounded-lg border border-border bg-card">
                    <h3 class="font-semibold mb-2">bg-card</h3>
                    <p class="text-sm text-muted-foreground">This box uses bg-card class</p>
                </div>

                <!-- Test Card 3 -->
                <div class="p-6 rounded-lg border border-border bg-muted">
                    <h3 class="font-semibold mb-2">bg-muted</h3>
                    <p class="text-sm text-muted-foreground">This box uses bg-muted class</p>
                </div>

                <!-- Test Card 4 with inline style -->
                <div class="p-6 rounded-lg border" style="background-color: var(--background); color: var(--foreground);">
                    <h3 class="font-semibold mb-2">Inline CSS Variables</h3>
                    <p class="text-sm" style="color: var(--muted-foreground);">This box uses inline CSS variables</p>
                </div>
            </div>

            <!-- Project Data Display (if available) -->
            <div v-if="heavyData?.project" class="p-6 rounded-lg border border-border bg-card text-card-foreground">
                <h2 class="text-xl font-semibold mb-4">Project Data</h2>
                <div class="space-y-2">
                    <p><strong>Title:</strong> {{ heavyData.project.title }}</p>
                    <p><strong>Mode:</strong> {{ heavyData.project.mode }}</p>
                    <p><strong>Status:</strong> {{ heavyData.project.status }}</p>
                </div>
            </div>

            <!-- Color Swatches -->
            <div class="p-6 rounded-lg border border-border bg-card text-card-foreground">
                <h2 class="text-xl font-semibold mb-4">Color Swatches</h2>
                <div class="grid grid-cols-4 gap-4">
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-background border"></div>
                        <p class="text-xs text-center">background</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-foreground"></div>
                        <p class="text-xs text-center">foreground</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-card border"></div>
                        <p class="text-xs text-center">card</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-muted"></div>
                        <p class="text-xs text-center">muted</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-primary"></div>
                        <p class="text-xs text-center">primary</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-secondary"></div>
                        <p class="text-xs text-center">secondary</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-accent"></div>
                        <p class="text-xs text-center">accent</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-12 rounded bg-destructive"></div>
                        <p class="text-xs text-center">destructive</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
