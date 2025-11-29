<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { router } from '@inertiajs/vue3'
import { AlertTriangle, ArrowRight, Coins, Sparkles } from 'lucide-vue-next'
import { computed } from 'vue'
import { route } from 'ziggy-js'

interface Props {
    open: boolean
    currentBalance: number
    requiredWords: number
    action?: string // e.g., "generate this chapter", "use AI suggestions"
}

const props = withDefaults(defineProps<Props>(), {
    action: 'continue',
})

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
    (e: 'close'): void
}>()

// Computed
const shortage = computed(() => Math.max(0, props.requiredWords - props.currentBalance))

const isLowBalance = computed(() => props.currentBalance < props.requiredWords)

// Recommended package based on shortage
const recommendedPack = computed(() => {
    if (shortage.value <= 5000) return { name: 'Small Top-up', words: '5,000', price: '₦2,500' }
    if (shortage.value <= 15000) return { name: 'Medium Top-up', words: '15,000', price: '₦6,000' }
    return { name: 'Large Top-up', words: '30,000', price: '₦10,000' }
})

// Methods
const goToPricing = () => {
    emit('update:open', false)
    router.visit(route('pricing'))
}

const close = () => {
    emit('update:open', false)
    emit('close')
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="mx-auto mb-4 p-3 rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <AlertTriangle class="w-8 h-8 text-amber-600 dark:text-amber-400" />
                </div>
                <DialogTitle class="text-center">
                    {{ isLowBalance ? 'Insufficient Credits' : 'Credit Balance Check' }}
                </DialogTitle>
                <DialogDescription class="text-center">
                    <template v-if="isLowBalance">
                        You need <strong>{{ requiredWords.toLocaleString() }}</strong> credits to {{ action }},
                        but you only have <strong>{{ currentBalance.toLocaleString() }}</strong> credits remaining.
                    </template>
                    <template v-else>
                        This action requires approximately <strong>{{ requiredWords.toLocaleString() }}</strong> credits.
                        You have <strong>{{ currentBalance.toLocaleString() }}</strong> credits available.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <!-- Balance Display -->
            <div class="my-6 p-4 rounded-lg bg-muted/50">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-muted-foreground">Your Balance</span>
                    <div class="flex items-center gap-1 text-lg font-bold">
                        <Coins class="w-5 h-5 text-primary" />
                        {{ currentBalance.toLocaleString() }}
                    </div>
                </div>
                
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-muted-foreground">Required</span>
                    <span class="text-lg font-medium">{{ requiredWords.toLocaleString() }}</span>
                </div>

                <div v-if="isLowBalance" class="pt-2 border-t border-border">
                    <div class="flex items-center justify-between text-red-600 dark:text-red-400">
                        <span class="text-sm font-medium">Shortage</span>
                        <span class="font-bold">{{ shortage.toLocaleString() }} credits</span>
                    </div>
                </div>
            </div>

            <!-- Recommendation -->
            <div v-if="isLowBalance" class="p-4 rounded-lg border border-primary/20 bg-primary/5">
                <div class="flex items-start gap-3">
                    <Sparkles class="w-5 h-5 text-primary mt-0.5" />
                    <div>
                        <h4 class="font-medium text-sm">Recommended: {{ recommendedPack.name }}</h4>
                        <p class="text-xs text-muted-foreground mt-1">
                            Get {{ recommendedPack.words }} credits for {{ recommendedPack.price }}
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-col sm:flex-col gap-2 mt-4">
                <Button
                    v-if="isLowBalance"
                    class="w-full"
                    @click="goToPricing"
                >
                    Buy More Credits
                    <ArrowRight class="w-4 h-4 ml-2" />
                </Button>
                
                <Button 
                    v-if="!isLowBalance" 
                    class="w-full" 
                    @click="close"
                >
                    Continue
                    <ArrowRight class="w-4 h-4 ml-2" />
                </Button>
                
                <Button 
                    variant="ghost" 
                    class="w-full" 
                    @click="close"
                >
                    {{ isLowBalance ? 'Cancel' : 'Go Back' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
