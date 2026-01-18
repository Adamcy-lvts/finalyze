<template>
    <AdminLayout title="Affiliate Program" subtitle="Manage affiliate settings and view analytics.">
        <!-- Stats Cards -->
        <div class="grid gap-4 md:grid-cols-4 mb-8">
            <Card class="bg-card border-border/50">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Total Affiliates</CardTitle>
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_affiliates }}</div>
                </CardContent>
            </Card>

            <Card class="bg-card border-border/50">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Referred Users</CardTitle>
                    <UserPlus class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_referred_users }}</div>
                </CardContent>
            </Card>

            <Card class="bg-card border-border/50">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Commissions Paid</CardTitle>
                    <Banknote class="h-4 w-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-emerald-600">{{ formatAmount(stats.total_commissions_paid) }}</div>
                </CardContent>
            </Card>

            <Card class="bg-card border-border/50">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">This Month</CardTitle>
                    <TrendingUp class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ formatAmount(stats.this_month_commissions) }}</div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-8 lg:grid-cols-2">
            <div class="space-y-6">
                <Card class="border-border/50">
                    <CardHeader>
                        <CardTitle class="text-base">Quick Actions</CardTitle>
                        <CardDescription>Manage affiliate records and requests.</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button as-child variant="outline">
                            <Link :href="route('admin.affiliates.list')">View Affiliates</Link>
                        </Button>
                        <Button as-child variant="outline">
                            <Link :href="route('admin.affiliates.invites.index')">Manage Invites</Link>
                        </Button>
                        <Button as-child variant="outline">
                            <Link :href="route('admin.affiliates.requests.index')" class="flex items-center gap-2">
                                Review Requests
                                <Badge v-if="stats.pending_requests > 0" variant="secondary">{{ stats.pending_requests }}</Badge>
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Top Affiliates -->
                <Card class="border-border/50">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-base">Top Affiliates</CardTitle>
                            <CardDescription>Highest earning affiliate partners</CardDescription>
                        </div>
                        <Button as-child variant="outline" size="sm">
                            <Link :href="route('admin.affiliates.list')">
                                View All
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div v-if="topAffiliates.length === 0" class="text-center py-8 text-muted-foreground">
                            No affiliates yet
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(referrer, index) in topAffiliates"
                                :key="referrer.id"
                                class="flex items-center justify-between p-3 rounded-lg bg-muted/50 border border-border/50"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-sm">
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <p class="font-medium">{{ referrer.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ referrer.referrals_count }} referrals</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-green-600">{{ referrer.total_earned_formatted }}</p>
                                    <code class="text-xs text-muted-foreground">{{ referrer.referral_code }}</code>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Recent Earnings -->
        <Card class="mt-8 border-border/50">
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
            <CardTitle class="text-base">Recent Earnings</CardTitle>
            <CardDescription>Latest affiliate commission activity</CardDescription>
                </div>
                <Button as-child variant="outline" size="sm">
                    <Link :href="route('admin.referrals.earnings')">
                        View All Earnings
                    </Link>
                </Button>
            </CardHeader>
            <CardContent>
                <div v-if="recentEarnings.length === 0" class="text-center py-8 text-muted-foreground">
                    No earnings recorded yet
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-border/50">
                                <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">Referrer</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-muted-foreground">Referee</th>
                                <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Commission</th>
                                <th class="text-center py-3 px-2 text-sm font-medium text-muted-foreground">Status</th>
                                <th class="text-right py-3 px-2 text-sm font-medium text-muted-foreground">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="earning in recentEarnings" :key="earning.id" class="border-b border-border/30">
                                <td class="py-3 px-2 text-sm">{{ earning.referrer_name }}</td>
                                <td class="py-3 px-2 text-sm">{{ earning.referee_name }}</td>
                                <td class="py-3 px-2 text-sm text-right font-medium text-green-600">{{ earning.commission_formatted }}</td>
                                <td class="py-3 px-2 text-center">
                                    <Badge :class="getStatusColor(earning.status)" variant="outline">
                                        {{ earning.status }}
                                    </Badge>
                                </td>
                                <td class="py-3 px-2 text-sm text-right text-muted-foreground">{{ formatDate(earning.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Users, UserPlus, Banknote, TrendingUp } from 'lucide-vue-next'

interface Props {
    stats: {
        total_affiliates: number
        total_referred_users: number
        total_commissions_paid: number
        pending_commissions: number
        this_month_commissions: number
        failed_commissions: number
        pending_requests: number
    }
    topAffiliates: Array<{
        id: number
        name: string
        email: string
        referral_code: string
        referrals_count: number
        total_earned: number
        total_earned_formatted: string
    }>
    recentEarnings: Array<{
        id: number
        referrer_name: string
        referee_name: string
        commission_formatted: string
        status: string
        created_at: string
    }>
}

const props = defineProps<Props>()


const formatAmount = (amountInKobo: number) => {
    return '₦' + (amountInKobo / 100).toLocaleString('en-NG', { minimumFractionDigits: 0 })
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-NG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
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
        default:
            return 'bg-gray-500/10 text-gray-600 border-gray-500/20'
    }
}

</script>
