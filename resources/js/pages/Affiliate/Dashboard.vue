<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { ChevronsUpDown, Check, Banknote, Users, TrendingUp, Copy, Share2, Loader2, AlertCircle, Gift, Star, Building2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface Bank {
    code: string
    name: string
}

interface Earning {
    id: number
    referee_name: string
    payment_amount: number
    payment_formatted: string
    commission_amount: number
    commission_formatted: string
    commission_rate: number
    status: string
    created_at: string
}

interface Referral {
    id: number
    name: string
    email: string
    successful_payments_count: number
    joined_at: string
}

interface Props {
    data: {
        referral_code: string | null
        referral_link: string | null
        bank_setup_complete: boolean
        has_custom_rate: boolean
        commission_rate: number
        stats: {
            total_referrals: number
            active_referrals: number
            total_earned: number
            total_earned_formatted: string
            pending_earnings: number
            pending_earnings_formatted: string
            this_month: number
            this_month_formatted: string
        }
        recent_earnings: Earning[]
        referrals: Referral[]
    }
    banks: Bank[]
    isEnabled: boolean
}

const props = defineProps<Props>()

const copied = ref(false)
const verifying = ref(false)
const verifiedAccountName = ref<string | null>(null)
const verificationError = ref<string | null>(null)

const bankForm = useForm({
    bank_code: '',
    bank_name: '',
    account_number: '',
    account_name: '',
})

const selectedBank = computed(() => {
    return props.banks.find(b => b.code === bankForm.bank_code)
})

const bankPopoverOpen = ref(false)

const canShare = computed(() => typeof navigator !== 'undefined' && typeof navigator.share === 'function')

const copyReferralLink = () => {
    if (props.data.referral_link) {
        navigator.clipboard.writeText(props.data.referral_link)
        copied.value = true
        toast.success('Referral link copied to clipboard!')
        setTimeout(() => {
            copied.value = false
        }, 2000)
    }
}

const shareReferralLink = async () => {
    if (!props.data.referral_link) {
        return
    }

    if (canShare.value) {
        try {
            await navigator.share({
                title: 'Join me on Finalyze',
                text: 'Use my referral link to sign up.',
                url: props.data.referral_link,
            })
        } catch (error) {
            // User cancelled share; no-op
        }
        return
    }

    copyReferralLink()
}

const verifyBankAccount = async () => {
    if (!bankForm.bank_code || bankForm.account_number.length !== 10) {
        verificationError.value = 'Please select a bank and enter a 10-digit account number'
        return
    }

    verifying.value = true
    verificationError.value = null
    verifiedAccountName.value = null

    try {
        const response = await fetch(route('affiliate.verify-bank'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                bank_code: bankForm.bank_code,
                account_number: bankForm.account_number,
            }),
        })

        const data = await response.json()

        if (data.success) {
            verifiedAccountName.value = data.account_name
            bankForm.account_name = data.account_name
            bankForm.bank_name = selectedBank.value?.name || ''
            toast.success('Bank account verified!')
        } else {
            verificationError.value = data.message || 'Could not verify account'
        }
    } catch (error) {
        verificationError.value = 'Failed to verify account. Please try again.'
    } finally {
        verifying.value = false
    }
}

const setupBankAccount = async () => {
    try {
        const response = await fetch(route('affiliate.setup-bank'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(bankForm.data()),
        })

        const data = await response.json()

        if (data.success) {
            toast.success('Bank account setup complete! You can now share your referral link.')
            window.location.reload()
        } else {
            toast.error(data.message || 'Failed to setup bank account. Please try again.')
        }
    } catch (error) {
        toast.error('Failed to setup bank account. Please try again.')
    }
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

<template>
    <AppLayout title="Affiliate Dashboard">
        <div class="container mx-auto px-4 py-8 max-w-6xl">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight">Affiliate Dashboard</h1>
                <p class="text-muted-foreground mt-1">
                    Earn {{ data.commission_rate }}% commission on every purchase made by users you refer.
                    <Badge v-if="data.has_custom_rate" variant="secondary" class="ml-2">
                        <Star class="h-3 w-3 mr-1" />
                        VIP Rate
                    </Badge>
                </p>
            </div>

            <!-- Disabled State -->
            <Alert v-if="!isEnabled" class="mb-6">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    The affiliate program is currently disabled. Please check back later.
                </AlertDescription>
            </Alert>

            <!-- Bank Setup Required -->
            <template v-else-if="!data.bank_setup_complete">
                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building2 class="h-5 w-5" />
                            Setup Your Bank Account
                        </CardTitle>
                        <CardDescription>
                            To start earning commissions, you need to setup your bank account first.
                            Your commissions will be automatically sent to this account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label>Select Bank</Label>
                                <Popover v-model:open="bankPopoverOpen">
                                    <PopoverTrigger as-child>
                                        <Button variant="outline" role="combobox" :class="[
                                            'w-full justify-between',
                                            !bankForm.bank_code && 'text-muted-foreground',
                                        ]">
                                            {{ selectedBank?.name ?? 'Choose your bank' }}
                                            <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-full p-0" align="start">
                                        <Command>
                                            <CommandInput placeholder="Search bank..." />
                                            <CommandEmpty>No bank found.</CommandEmpty>
                                            <CommandList>
                                                <CommandGroup>
                                                    <CommandItem
                                                        v-for="bank in banks"
                                                        :key="bank.code"
                                                        :value="bank.name"
                                                        @select="() => {
                                                            bankForm.bank_code = bank.code;
                                                            bankForm.bank_name = bank.name;
                                                            bankPopoverOpen = false;
                                                        }"
                                                    >
                                                        {{ bank.name }}
                                                        <Check
                                                            :class="[
                                                                'ml-auto h-4 w-4',
                                                                bank.code === bankForm.bank_code ? 'opacity-100' : 'opacity-0',
                                                            ]"
                                                        />
                                                    </CommandItem>
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            <div class="space-y-2">
                                <Label>Account Number</Label>
                                <div class="flex gap-2">
                                    <Input
                                        v-model="bankForm.account_number"
                                        placeholder="Enter 10-digit account number"
                                        maxlength="10"
                                        class="flex-1"
                                    />
                                    <Button
                                        @click="verifyBankAccount"
                                        :disabled="verifying || !bankForm.bank_code || bankForm.account_number.length !== 10"
                                        variant="outline"
                                    >
                                        <Loader2 v-if="verifying" class="h-4 w-4 animate-spin mr-2" />
                                        Verify
                                    </Button>
                                </div>
                            </div>

                            <Alert v-if="verificationError" variant="destructive">
                                <AlertCircle class="h-4 w-4" />
                                <AlertDescription>{{ verificationError }}</AlertDescription>
                            </Alert>

                            <div v-if="verifiedAccountName" class="p-4 rounded-lg bg-green-500/10 border border-green-500/20">
                                <p class="text-sm text-muted-foreground">Account Name</p>
                                <p class="text-lg font-semibold text-green-600">{{ verifiedAccountName }}</p>
                            </div>
                        </div>

                        <Button
                            @click="setupBankAccount"
                            :disabled="!verifiedAccountName || bankForm.processing"
                            class="w-full"
                        >
                            <Loader2 v-if="bankForm.processing" class="h-4 w-4 animate-spin mr-2" />
                            Complete Setup & Get Referral Code
                        </Button>
                    </CardContent>
                </Card>
            </template>

            <!-- Main Dashboard (Bank Setup Complete) -->
            <template v-else>
                <!-- Stats Cards -->
                <div class="grid gap-4 md:grid-cols-4 mb-8">
                    <Card class="bg-card border-border/50">
                        <CardContent class="p-6">
                            <div class="flex items-center gap-4">
                                <div class="p-3 rounded-xl bg-blue-500/10 text-blue-500">
                                    <Users class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Referrals</p>
                                    <p class="text-2xl font-bold">{{ data.stats.total_referrals }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ data.stats.active_referrals }} made purchases
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="bg-card border-border/50">
                        <CardContent class="p-6">
                            <div class="flex items-center gap-4">
                                <div class="p-3 rounded-xl bg-green-500/10 text-green-500">
                                    <Banknote class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Earned</p>
                                    <p class="text-2xl font-bold">{{ data.stats.total_earned_formatted }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="bg-card border-border/50">
                        <CardContent class="p-6">
                            <div class="flex items-center gap-4">
                                <div class="p-3 rounded-xl bg-yellow-500/10 text-yellow-500">
                                    <TrendingUp class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">This Month</p>
                                    <p class="text-2xl font-bold">{{ data.stats.this_month_formatted }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="bg-card border-border/50">
                        <CardContent class="p-6">
                            <div class="flex items-center gap-4">
                                <div class="p-3 rounded-xl bg-purple-500/10 text-purple-500">
                                    <Gift class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Commission Rate</p>
                                    <p class="text-2xl font-bold">{{ data.commission_rate }}%</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Referral Link Card -->
                <Card class="mb-8 border-primary/20 bg-gradient-to-r from-primary/5 to-transparent">
                    <CardHeader>
                        <CardTitle>Your Referral Link</CardTitle>
                        <CardDescription>
                            Share this link with friends. When they sign up and make a purchase, you earn {{ data.commission_rate }}%!
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex gap-2">
                            <Input
                                :model-value="data.referral_link || ''"
                                readonly
                                class="flex-1 bg-background font-mono text-sm"
                            />
                            <Button v-if="data.referral_link" @click="shareReferralLink" variant="outline">
                                <Share2 class="h-4 w-4 mr-2" />
                                Share
                            </Button>
                            <Button @click="copyReferralLink" variant="outline">
                                <Check v-if="copied" class="h-4 w-4 mr-2 text-green-500" />
                                <Copy v-else class="h-4 w-4 mr-2" />
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </Button>
                        </div>
                        <p class="text-sm text-muted-foreground mt-2">
                            Your referral code: <code class="px-2 py-1 bg-muted rounded font-mono">{{ data.referral_code }}</code>
                        </p>
                    </CardContent>
                </Card>

                <div class="grid gap-8 lg:grid-cols-2">
                    <!-- Recent Earnings -->
                    <Card class="border-border/50">
                        <CardHeader>
                            <CardTitle class="text-base">Recent Earnings</CardTitle>
                            <CardDescription>Your latest commission earnings</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="data.recent_earnings.length === 0" class="text-center py-8 text-muted-foreground">
                                No earnings yet. Share your referral link to start earning!
                            </div>
                            <div v-else class="space-y-4">
                                <div
                                    v-for="earning in data.recent_earnings"
                                    :key="earning.id"
                                    class="flex items-center justify-between p-3 rounded-lg bg-muted/50 border border-border/50"
                                >
                                    <div>
                                        <p class="font-medium">{{ earning.referee_name }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ earning.payment_formatted }} purchase
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatDate(earning.created_at) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-green-600">{{ earning.commission_formatted }}</p>
                                        <Badge :class="getStatusColor(earning.status)" variant="outline">
                                            {{ earning.status }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Referred Users -->
                    <Card class="border-border/50">
                        <CardHeader>
                            <CardTitle class="text-base">Your Referrals</CardTitle>
                            <CardDescription>People who signed up with your link</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="data.referrals.length === 0" class="text-center py-8 text-muted-foreground">
                                No referrals yet. Share your link to invite friends!
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="referral in data.referrals"
                                    :key="referral.id"
                                    class="flex items-center justify-between p-3 rounded-lg bg-muted/50 border border-border/50"
                                >
                                    <div>
                                        <p class="font-medium">{{ referral.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ referral.email }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            Joined {{ formatDate(referral.joined_at) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <Badge v-if="referral.successful_payments_count > 0" class="bg-green-500/10 text-green-600 border-green-500/20">
                                            {{ referral.successful_payments_count }} purchase{{ referral.successful_payments_count > 1 ? 's' : '' }}
                                        </Badge>
                                        <Badge v-else variant="outline" class="text-muted-foreground">
                                            No purchases
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
