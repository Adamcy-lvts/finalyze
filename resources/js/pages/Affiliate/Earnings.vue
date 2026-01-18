<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { route } from 'ziggy-js'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

interface Earning {
    id: number
    referee?: {
        name: string
        email: string
    }
    payment_amount: number
    commission_amount: number
    commission_rate: number
    status: string
    created_at: string
}

const earnings = ref<Earning[]>([])
const loading = ref(false)
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 20,
})

const load = async (page = 1) => {
    loading.value = true
    try {
        const response = await fetch(`${route('affiliate.earnings.data')}?page=${page}`)
        const data = await response.json()
        earnings.value = data.earnings || []
        pagination.value = data.pagination || pagination.value
    } finally {
        loading.value = false
    }
}

const formatCurrency = (amount: number) => {
    return '₦' + (amount / 100).toLocaleString('en-NG', { minimumFractionDigits: 0 })
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-NG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const statusClass = (status: string) => {
    switch (status) {
        case 'paid':
            return 'bg-green-500/10 text-green-600 border-green-500/20'
        case 'pending':
            return 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20'
        case 'failed':
            return 'bg-red-500/10 text-red-600 border-red-500/20'
        default:
            return 'bg-gray-500/10 text-gray-600 border-gray-500/20'
    }
}

onMounted(() => load())
</script>

<template>
    <AppLayout title="Affiliate Earnings">
        <div class="container mx-auto px-4 py-8 max-w-6xl">
            <Card class="border-border/50">
                <CardHeader>
                    <CardTitle>Earnings History</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="loading" class="text-sm text-muted-foreground">Loading earnings...</div>
                    <div v-else-if="earnings.length === 0" class="text-sm text-muted-foreground">No earnings yet.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-border/50">
                                    <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">Referee</th>
                                    <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Payment</th>
                                    <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Commission</th>
                                    <th class="text-center py-3 px-2 text-sm font-medium text-muted-foreground">Status</th>
                                    <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="earning in earnings" :key="earning.id" class="border-b border-border/30">
                                    <td class="py-3 px-2 text-sm">
                                        <div class="font-medium">{{ earning.referee?.name ?? 'Unknown' }}</div>
                                        <div class="text-xs text-muted-foreground">{{ earning.referee?.email ?? '' }}</div>
                                    </td>
                                    <td class="py-3 px-2 text-sm text-right">{{ formatCurrency(earning.payment_amount) }}</td>
                                    <td class="py-3 px-2 text-sm text-right font-medium text-green-600">{{ formatCurrency(earning.commission_amount) }}</td>
                                    <td class="py-3 px-2 text-center">
                                        <Badge :class="statusClass(earning.status)" variant="outline">
                                            {{ earning.status }}
                                        </Badge>
                                    </td>
                                    <td class="py-3 px-2 text-sm text-right text-muted-foreground">{{ formatDate(earning.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="pagination.last_page > 1" class="flex items-center justify-end gap-2 pt-4">
                        <Button variant="outline" size="sm" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">
                            Previous
                        </Button>
                        <Button variant="outline" size="sm" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">
                            Next
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
