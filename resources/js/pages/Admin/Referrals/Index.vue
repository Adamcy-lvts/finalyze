<template>
    <AdminLayout title="Referral Program" subtitle="Manage referral settings and view analytics.">
        <!-- Stats Cards -->
        <div class="grid gap-4 md:grid-cols-4 mb-8">
            <Card class="bg-card border-border/50">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Total Referrers</CardTitle>
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_referrers }}</div>
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
            <!-- Settings Card -->
            <Card class="border-border/50">
                <CardHeader>
                    <CardTitle class="text-base">Referral Settings</CardTitle>
                    <CardDescription>Configure the referral program</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <Label class="text-base">Program Status</Label>
                            <p class="text-sm text-muted-foreground">Enable or disable the referral program</p>
                        </div>
                        <Switch
                            :checked="settingsForm.enabled"
                            @update:checked="settingsForm.enabled = $event"
                        />
                    </div>

                    <Separator />

                    <div class="space-y-2">
                        <Label>Default Commission Rate (%)</Label>
                        <Input
                            v-model.number="settingsForm.commission_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                        />
                        <p class="text-xs text-muted-foreground">
                            Percentage of each payment that goes to the referrer
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Minimum Payment Amount (NGN)</Label>
                        <Input
                            v-model.number="minPaymentNaira"
                            type="number"
                            min="0"
                            step="100"
                        />
                        <p class="text-xs text-muted-foreground">
                            Minimum payment amount to qualify for referral commission
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Fee Bearer</Label>
                        <Select v-model="settingsForm.fee_bearer">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="account">Main Account</SelectItem>
                                <SelectItem value="subaccount">Referrer (Subaccount)</SelectItem>
                                <SelectItem value="all">Split Proportionally</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Who pays the Paystack transaction fees
                        </p>
                    </div>

                    <Button @click="saveSettings" :disabled="saving" class="w-full">
                        <Loader2 v-if="saving" class="h-4 w-4 animate-spin mr-2" />
                        Save Settings
                    </Button>
                </CardContent>
            </Card>

            <!-- Top Referrers -->
            <Card class="border-border/50">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base">Top Referrers</CardTitle>
                        <CardDescription>Highest earning referral partners</CardDescription>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="route('admin.referrals.users')">
                            View All
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="topReferrers.length === 0" class="text-center py-8 text-muted-foreground">
                        No referrers yet
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="(referrer, index) in topReferrers"
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

        <!-- Recent Earnings -->
        <Card class="mt-8 border-border/50">
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle class="text-base">Recent Earnings</CardTitle>
                    <CardDescription>Latest referral commission activity</CardDescription>
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
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Separator } from '@/components/ui/separator'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Users, UserPlus, Banknote, TrendingUp, Loader2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface Props {
    stats: {
        total_referrers: number
        total_referred_users: number
        total_commissions_paid: number
        pending_commissions: number
        this_month_commissions: number
        failed_commissions: number
    }
    settings: {
        enabled: boolean
        commission_percentage: number
        minimum_payment_amount: number
        fee_bearer: string
    }
    topReferrers: Array<{
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

const saving = ref(false)

const settingsForm = ref({
    enabled: props.settings.enabled,
    commission_percentage: props.settings.commission_percentage,
    minimum_payment_amount: props.settings.minimum_payment_amount,
    fee_bearer: props.settings.fee_bearer,
})

const minPaymentNaira = computed({
    get: () => settingsForm.value.minimum_payment_amount / 100,
    set: (value: number) => {
        settingsForm.value.minimum_payment_amount = value * 100
    },
})

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

const saveSettings = async () => {
    saving.value = true

    try {
        const response = await fetch(route('admin.referrals.update-settings'), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(settingsForm.value),
        })

        const data = await response.json()

        if (data.success) {
            toast.success('Settings saved successfully')
        } else {
            toast.error(data.message || 'Failed to save settings')
        }
    } catch (error) {
        toast.error('Failed to save settings')
    } finally {
        saving.value = false
    }
}
</script>
