<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Coins, Plus } from 'lucide-vue-next'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import CreditBalanceCard from '@/components/CreditBalanceCard.vue'

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
    compact?: boolean
    showTopUp?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    compact: false,
    showTopUp: true,
})
</script>

<template>
    <!-- Compact Version (for header/sidebar) - Now with Popover -->
    <div v-if="compact" class="flex items-center gap-2">
        <Popover>
            <PopoverTrigger as-child>
                <div
                    class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-muted/50 hover:bg-muted transition-colors cursor-pointer">
                    <Coins class="w-4 h-4 text-primary" />
                    <span class="font-medium text-sm text-foreground">
                        {{ balance.formatted_balance }}
                    </span>
                    <span class="text-xs text-muted-foreground">credits</span>
                </div>
            </PopoverTrigger>
            <PopoverContent class="w-80 p-0" align="end">
                <CreditBalanceCard :balance="balance" :show-top-up="showTopUp" class="border-0 shadow-none" />
            </PopoverContent>
        </Popover>

        <Link v-if="showTopUp" :href="route('pricing')" id="top-up-btn">
            <Button variant="ghost" size="icon" class="h-8 w-8 text-foreground">
                <Plus class="w-4 h-4" />
            </Button>
        </Link>
    </div>

    <!-- Full Version (for pricing page, dashboard) -->
    <CreditBalanceCard v-else :balance="balance" :show-top-up="showTopUp" />
</template>
