<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { AlertCircle, Check, Loader2, Sparkles, Zap } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'
import { route } from 'ziggy-js'

// Types
interface Package {
    id: number
    name: string
    slug: string
    type: 'project' | 'topup'
    tier: string | null
    words: number
    formatted_words: string
    price: number
    price_in_naira: number
    formatted_price: string
    description: string
    features: string[]
    is_popular: boolean
}

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
    packages: {
        projects: Package[]
        topups: Package[]
    }
    wordBalance: WordBalance | null
    paystackPublicKey: string | null
    paystackConfigured: boolean
    activePackageId?: number | null
}

const props = defineProps<Props>()
const page = usePage()

// State
const processingPackage = ref<number | null>(null)
const showTopups = ref(false)
const currentPackage = ref<Package | null>(null)

// Flash messages
const flash = computed(() => page.props.flash as { success?: string; error?: string })

// Check if user is logged in
const isAuthenticated = computed(() => !!page.props.auth?.user)

// Verify payment after completion
const verifyPayment = async (reference: string) => {
    try {
        const response = await fetch(route('payments.verify'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ reference }),
        })

        const data = await response.json()

        if (data.success) {
            toast.success(`Payment successful! ${data.data.words_credited.toLocaleString()} words added to your balance.`)
            // Reload page to update balance
            router.reload()
        } else {
            toast.error(data.message || 'Payment verification failed')
        }
    } catch (error) {
        console.error('Payment verification error:', error)
        toast.error('Could not verify payment. Please check your balance.')
    }
}

// Open Paystack inline popup
const openPaystackPopup = (data: { authorization_url: string; access_code: string; reference: string }, pkg: Package) => {
     const userEmail = (page.props.auth?.user as any)?.email

    if (!userEmail) {
        toast.error('User email is missing. Cannot proceed with payment.')
        return
    }

    // @ts-ignore
    const handler = PaystackPop.setup({
        key: props.paystackPublicKey,
        email: userEmail,
        amount: pkg.price, // Amount in kobo
        ref: data.reference,
        access_code: data.access_code,
        onClose: () => {
            toast.info('Payment window closed')
        },
        callback: (response: any) => {
            // Verify payment
            verifyPayment(response.reference)
        },
    })

    handler.openIframe()
}

// Initialize payment
const initializePayment = async (pkg: Package) => {
    if (!props.paystackConfigured) {
        toast.error('Payment system is not available at the moment. Please contact support.')
        return
    }

    if (!isAuthenticated.value) {
        router.visit(route('login'), {
            data: { redirect: route('pricing') }
        })
        return
    }

    // New logic for free package
    if (pkg.price === 0) {
        // Since it's free/signup bonus, just redirect to dashboard or show success
         toast.success("Free credits claimed! Redirecting to dashboard...");
         router.visit(route('dashboard'));
         return;
    }

    processingPackage.value = pkg.id
    currentPackage.value = pkg

    try {
        const response = await fetch(route('payments.initialize'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ package_id: pkg.id }),
        })

        const data = await response.json()

        if (data.success) {
            // Open Paystack popup
            openPaystackPopup(data.data, pkg)
        } else {
            toast.error(data.message || 'Failed to initialize payment')
        }
    } catch (error) {
        console.error('Payment initialization error:', error)
        toast.error('Something went wrong. Please try again.')
    } finally {
        processingPackage.value = null
    }
}


</script>

<template>
    <AppLayout title="Pricing">


        <div class="min-h-screen bg-gradient-to-b from-background to-muted/30">
            <div class="container mx-auto px-4 py-12">
                <!-- Header -->
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-bold tracking-tight mb-4">
                        Simple, Transparent Pricing
                    </h1>
                    <p class="text-xl text-muted-foreground max-w-2xl mx-auto">
                        Pay once for your project. No subscriptions. Credits never expire.
                    </p>
                </div>

                <!-- Payment System Not Configured Warning -->
                <Alert v-if="!paystackConfigured" variant="destructive" class="mb-8 max-w-2xl mx-auto">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>Payment System Unavailable</AlertTitle>
                    <AlertDescription>
                        The payment system is currently not configured. You can view pricing, but payments cannot be
                        processed at this time.
                        Please contact support if you need to make a purchase.
                    </AlertDescription>
                </Alert>

                <!-- Flash Messages -->
                <Alert v-if="flash?.success"
                    class="mb-8 max-w-2xl mx-auto border-green-500 bg-green-50 dark:bg-green-950">
                    <Check class="h-4 w-4 text-green-600" />
                    <AlertDescription class="text-green-800 dark:text-green-200">
                        {{ flash.success }}
                    </AlertDescription>
                </Alert>

                <Alert v-if="flash?.error" variant="destructive" class="mb-8 max-w-2xl mx-auto">
                    <AlertDescription>{{ flash.error }}</AlertDescription>
                </Alert>



                <!-- Project Packages -->
                <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto mb-16">
                    <Card v-for="pkg in packages.projects" :key="pkg.id" :class="[
                        'relative transition-all duration-300 hover:shadow-lg',
                        pkg.is_popular ? 'border-primary shadow-md' : '',
                        activePackageId === pkg.id ? 'border-indigo-500 shadow-[0_0_30px_-5px_rgba(99,102,241,0.3)] ring-1 ring-indigo-500' : ''
                    ]">
                        <!-- Active Badge -->
                        <Badge v-if="activePackageId === pkg.id" class="absolute -top-3 right-4 bg-indigo-500 hover:bg-indigo-600">
                             Active Plan
                        </Badge>

                        <!-- Popular Badge -->
                        <Badge v-else-if="pkg.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <Sparkles class="w-3 h-3 mr-1" />
                            Most Popular
                        </Badge>

                        <CardHeader class="text-center pb-2">
                            <CardTitle class="text-2xl">{{ pkg.name }}</CardTitle>
                            <CardDescription class="text-sm">
                                {{ pkg.description }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="text-center">
                            <!-- Price -->
                            <div class="mb-6">
                                <div v-if="pkg.price === 0">
                                    <span class="text-4xl font-bold">Free</span>
                                    <span class="text-muted-foreground ml-2">on signup</span>
                                </div>
                                <div v-else>
                                    <span class="text-4xl font-bold">{{ pkg.formatted_price }}</span>
                                    <span class="text-muted-foreground ml-2">one-time</span>
                                </div>
                            </div>

                            <!-- Words/Credits -->
                            <div class="bg-muted/50 rounded-lg p-4 mb-6">
                                <div class="text-3xl font-bold text-primary">
                                    {{ pkg.formatted_words }}
                                </div>
                                <div class="text-sm text-muted-foreground">credits included</div>
                            </div>

                            <!-- Features -->
                            <ul class="space-y-3 text-left">
                                <li v-for="feature in pkg.features" :key="feature" class="flex items-start gap-2">
                                    <Check class="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                                    <span class="text-sm">{{ feature }}</span>
                                </li>
                            </ul>
                        </CardContent>

                        <CardFooter>
                            <Button class="w-full" size="lg" :variant="pkg.is_popular ? 'default' : 'outline'"
                                :disabled="processingPackage === pkg.id || (pkg.price > 0 && !paystackConfigured)"
                                @click="initializePayment(pkg)">
                                <Loader2 v-if="processingPackage === pkg.id" class="mr-2 h-4 w-4 animate-spin" />
                                <template v-else>
                                    <span v-if="pkg.price === 0">Get Started Free</span>
                                    <span v-else>{{ paystackConfigured ? 'Get Started' : 'Unavailable' }}</span>
                                </template>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Top-up Section -->
                <div class="max-w-4xl mx-auto">
                    <div class="text-center mb-8">
                        <Button variant="ghost" @click="showTopups = !showTopups" class="text-muted-foreground">
                            <Zap class="w-4 h-4 mr-2" />
                            {{ showTopups ? 'Hide' : 'Show' }} Top-up Packs
                        </Button>
                        <p class="text-sm text-muted-foreground mt-2">
                            Need more credits? Buy additional credit packs anytime.
                        </p>
                    </div>

                    <div v-show="showTopups" class="grid md:grid-cols-3 gap-6">
                        <Card v-for="pkg in packages.topups" :key="pkg.id" :class="[
                            'relative transition-all duration-300 hover:shadow-md',
                            pkg.is_popular ? 'border-primary' : ''
                        ]">
                            <Badge v-if="pkg.is_popular" variant="secondary" class="absolute -top-2 right-4">
                                Best Value
                            </Badge>

                            <CardHeader class="pb-2">
                                <CardTitle class="text-lg">{{ pkg.name }}</CardTitle>
                            </CardHeader>

                            <CardContent>
                                <div class="text-2xl font-bold mb-1">
                                    {{ pkg.formatted_price }}
                                </div>
                                <div class="text-primary font-medium mb-4">
                                    {{ pkg.formatted_words }} credits
                                </div>
                                <ul class="space-y-1">
                                    <li v-for="feature in pkg.features" :key="feature"
                                        class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Check class="h-3 w-3 text-green-500" />
                                        {{ feature }}
                                    </li>
                                </ul>
                            </CardContent>

                            <CardFooter>
                                <Button variant="outline" class="w-full"
                                    :disabled="!paystackConfigured || processingPackage === pkg.id"
                                    @click="initializePayment(pkg)">
                                    <Loader2 v-if="processingPackage === pkg.id" class="mr-2 h-4 w-4 animate-spin" />
                                    <template v-else>{{ paystackConfigured ? 'Buy Now' : 'Unavailable' }}</template>
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </div>

                <!-- FAQ / Trust Signals -->
                <div class="max-w-2xl mx-auto mt-16 text-center">
                    <h2 class="text-xl font-semibold mb-6">Frequently Asked Questions</h2>

                    <div class="space-y-4 text-left">
                        <div class="p-4 rounded-lg bg-muted/30">
                            <h3 class="font-medium mb-1">Do credits expire?</h3>
                            <p class="text-sm text-muted-foreground">
                                No! Your credits never expire. Use them whenever you're ready.
                            </p>
                        </div>

                        <div class="p-4 rounded-lg bg-muted/30">
                            <h3 class="font-medium mb-1">What if I run out of credits?</h3>
                            <p class="text-sm text-muted-foreground">
                                Simply purchase a top-up pack. Your new credits are added instantly.
                            </p>
                        </div>

                        <div class="p-4 rounded-lg bg-muted/30">
                            <h3 class="font-medium mb-1">Can I use credits across multiple projects?</h3>
                            <p class="text-sm text-muted-foreground">
                                Yes! Your credit balance is account-wide. Use it for any project, seminar, or
                                presentation.
                            </p>
                        </div>

                        <div class="p-4 rounded-lg bg-muted/30">
                            <h3 class="font-medium mb-1">What payment methods are accepted?</h3>
                            <p class="text-sm text-muted-foreground">
                                We accept all Nigerian bank cards, bank transfers, and USSD payments via Paystack.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
