<template>
    <AdminLayout title="Referral Earnings" subtitle="View all referral commission transactions.">
        <Card class="border-border/50">
            <CardHeader class="flex flex-row items-center justify-between pb-4">
                <div>
                    <CardTitle class="text-base">Earnings History</CardTitle>
                    <CardDescription>All commission earnings across referrers</CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <!-- Filters -->
                <div class="flex flex-wrap gap-4 mb-6">
                    <Select v-model="filters.status" @update:model-value="applyFilters">
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Statuses</SelectItem>
                            <SelectItem value="paid">Paid</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="failed">Failed</SelectItem>
                            <SelectItem value="refunded">Refunded</SelectItem>
                        </SelectContent>
                    </Select>

                    <Input
                        v-model="filters.from_date"
                        type="date"
                        class="w-40"
                        placeholder="From date"
                        @change="applyFilters"
                    />

                    <Input
                        v-model="filters.to_date"
                        type="date"
                        class="w-40"
                        placeholder="To date"
                        @change="applyFilters"
                    />

                    <Button v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters">
                        Clear Filters
                    </Button>
                </div>

                <div v-if="earnings.data.length === 0" class="text-center py-12 text-muted-foreground">
                    No earnings found
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-border/50">
                                <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">Referrer</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">Referee</th>
                                <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Payment</th>
                                <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Commission</th>
                                <th class="text-center py-3 px-2 text-sm font-medium text-muted-foreground">Rate</th>
                                <th class="text-center py-3 px-2 text-sm font-medium text-muted-foreground">Status</th>
                                <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="earning in earnings.data" :key="earning.id" class="border-b border-border/30">
                                <td class="py-4 px-2">
                                    <div>
                                        <p class="font-medium">{{ earning.referrer.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ earning.referrer.email }}</p>
                                        <code class="text-xs text-muted-foreground">{{ earning.referrer.referral_code }}</code>
                                    </div>
                                </td>
                                <td class="py-4 px-2">
                                    <div>
                                        <p class="font-medium">{{ earning.referee.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ earning.referee.email }}</p>
                                    </div>
                                </td>
                                <td class="py-4 px-2 text-right">
                                    {{ earning.payment_formatted }}
                                </td>
                                <td class="py-4 px-2 text-right font-medium text-green-600">
                                    {{ earning.commission_formatted }}
                                </td>
                                <td class="py-4 px-2 text-center">
                                    {{ earning.commission_rate }}%
                                </td>
                                <td class="py-4 px-2 text-center">
                                    <Badge :class="getStatusColor(earning.status)" variant="outline">
                                        {{ earning.status }}
                                    </Badge>
                                </td>
                                <td class="py-4 px-2 text-right text-sm text-muted-foreground">
                                    {{ formatDate(earning.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="earnings.last_page > 1" class="flex items-center justify-between mt-6">
                    <p class="text-sm text-muted-foreground">
                        Page {{ earnings.current_page }} of {{ earnings.last_page }} ({{ earnings.total }} total)
                    </p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="earnings.current_page === 1"
                            @click="goToPage(earnings.current_page - 1)"
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="earnings.current_page === earnings.last_page"
                            @click="goToPage(earnings.current_page + 1)"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface Earning {
    id: number
    referrer: {
        id: number
        name: string
        email: string
        referral_code: string
    }
    referee: {
        id: number
        name: string
        email: string
    }
    payment_amount: number
    payment_formatted: string
    commission_amount: number
    commission_formatted: string
    commission_rate: number
    status: string
    created_at: string
}

interface Props {
    earnings: {
        data: Earning[]
        current_page: number
        last_page: number
        total: number
    }
    filters: {
        status: string | null
        referrer_id: number | null
        from_date: string | null
        to_date: string | null
    }
}

const props = defineProps<Props>()

const filters = ref({
    status: props.filters.status || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
})

const hasFilters = computed(() => {
    return filters.value.status || filters.value.from_date || filters.value.to_date
})

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-NG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const getStatusColor = (status: string) => {
    switch (status) {
        case 'paid':
            return 'bg-green-500/10 text-green-600 border-green-500/20'
        case 'pending':
            return 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20'
        case 'failed':
            return 'bg-red-500/10 text-red-600 border-red-500/20'
        case 'refunded':
            return 'bg-gray-500/10 text-gray-600 border-gray-500/20'
        default:
            return 'bg-gray-500/10 text-gray-600 border-gray-500/20'
    }
}

const applyFilters = () => {
    const params: Record<string, string> = {}
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.from_date) params.from_date = filters.value.from_date
    if (filters.value.to_date) params.to_date = filters.value.to_date

    router.get(route('admin.referrals.earnings'), params, {
        preserveState: true,
        replace: true,
    })
}

const clearFilters = () => {
    filters.value = { status: '', from_date: '', to_date: '' }
    router.get(route('admin.referrals.earnings'), {}, {
        preserveState: true,
        replace: true,
    })
}

const goToPage = (page: number) => {
    const params: Record<string, string | number> = { page }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.from_date) params.from_date = filters.value.from_date
    if (filters.value.to_date) params.to_date = filters.value.to_date

    router.get(route('admin.referrals.earnings'), params, {
        preserveState: true,
    })
}
</script>
