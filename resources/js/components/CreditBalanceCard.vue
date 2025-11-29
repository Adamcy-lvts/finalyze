<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { Link } from '@inertiajs/vue3'
import { Coins, Info, Plus } from 'lucide-vue-next'
import { computed } from 'vue'
import { route } from 'ziggy-js'

interface WordBalance {
    balance: number
    formatted_balance: string
    total_purchased: number
    total_used: number
    bonus_received: number
    total_allocated: number
    percentage_used: number
    percentage_remaining: number
}

interface Props {
    balance: WordBalance
    showTopUp?: boolean
    className?: string
}

const props = withDefaults(defineProps<Props>(), {
    showTopUp: true,
    className: '',
})

// Computed
const progressValue = computed(() => props.balance.percentage_remaining)

const balanceStatus = computed(() => {
    const remaining = props.balance.percentage_remaining
    if (remaining > 50) return 'healthy'
    if (remaining > 20) return 'warning'
    return 'critical'
})

const statusColor = computed(() => {
    switch (balanceStatus.value) {
        case 'healthy': return 'bg-green-500'
        case 'warning': return 'bg-yellow-500'
        case 'critical': return 'bg-red-500'
        default: return 'bg-primary'
    }
})

const statusTextColor = computed(() => {
    switch (balanceStatus.value) {
        case 'healthy': return 'text-green-600 dark:text-green-400'
        case 'warning': return 'text-yellow-600 dark:text-yellow-400'
        case 'critical': return 'text-red-600 dark:text-red-400'
        default: return 'text-foreground'
    }
})
</script>

<template>
    <Card :class="['overflow-hidden', className]">
        <CardContent class="p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="p-2 rounded-lg bg-primary/10">
                        <Coins class="w-5 h-5 text-primary" />
                    </div>
                    <div>
                        <h3 class="font-semibold">Credit Balance</h3>
                        <p class="text-xs text-muted-foreground">Your available credits</p>
                    </div>
                </div>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger>
                            <Info class="w-4 h-4 text-muted-foreground" />
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Credits are used for AI generation features.</p>
                            <p>They never expire!</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <!-- Main Balance -->
            <div class="text-center py-4">
                <div :class="['text-4xl font-bold', statusTextColor]">
                    {{ balance.formatted_balance }}
                </div>
                <div class="text-sm text-muted-foreground">credits remaining</div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex justify-between text-xs text-muted-foreground mb-1">
                    <span>{{ balance.percentage_remaining.toFixed(0) }}% remaining</span>
                    <span>{{ balance.total_used.toLocaleString() }} used</span>
                </div>
                <div class="h-2 bg-muted rounded-full overflow-hidden">
                    <div 
                        :class="['h-full transition-all duration-500', statusColor]"
                        :style="{ width: `${progressValue}%` }"
                    />
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 text-center text-sm">
                <div class="p-2 rounded bg-muted/30">
                    <div class="font-medium">{{ balance.total_purchased.toLocaleString() }}</div>
                    <div class="text-xs text-muted-foreground">Purchased</div>
                </div>
                <div class="p-2 rounded bg-muted/30">
                    <div class="font-medium">{{ balance.bonus_received.toLocaleString() }}</div>
                    <div class="text-xs text-muted-foreground">Bonus</div>
                </div>
            </div>

            <!-- Top Up Button -->
            <Link v-if="showTopUp" :href="route('pricing')" class="block mt-4">
                <Button variant="outline" class="w-full">
                    <Plus class="w-4 h-4 mr-2" />
                    Buy More Credits
                </Button>
            </Link>
        </CardContent>
    </Card>
</template>
