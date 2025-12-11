<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, nextTick, ref, computed } from 'vue';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Bot,
    Sparkles,
    BookOpen,
    Target,
    Zap,
    CheckCircle,
    XCircle,
    ArrowRight,
    GraduationCap,
    FileText,
    LayoutTemplate,
    ChevronRight,
    Star,
    Clock,
    PenTool,
    LayoutDashboard,
    Folder,
    PieChart,
    Settings,
    Search,
    Bell,
    Menu,
    AlertCircle,
    Check,
    Loader2,
    LogOut
} from 'lucide-vue-next';

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

const props = defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    packages?: {
        projects: Package[]
        topups: Package[]
    }
    wordBalance?: WordBalance | null
    paystackPublicKey?: string | null
    paystackConfigured?: boolean
    activePackageId?: number | null
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

// State
const processingPackage = ref<number | null>(null)
const showTopups = ref(false)
const currentPackage = ref<Package | null>(null)

// Flash messages
const flash = computed(() => page.props.flash as { success?: string; error?: string })

// Check if user is logged in
const isAuthenticated = computed(() => !!page.props.auth?.user)


// Claim free package
const claimFreePackage = async (pkg: Package) => {
    if (!isAuthenticated.value) {
        router.visit(route('register'));
        return;
    }

    if (user.value?.received_signup_bonus) {
        toast.error("You have already claimed your free starter credits.");
        return;
    }

    router.visit(route('dashboard'));
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
    // If package is free (price 0), handle claim directly
    if (pkg.price === 0) {
        claimFreePackage(pkg);
        return;
    }

    if (!props.paystackConfigured) {
        toast.error('Payment system is not available at the moment. Please contact support.')
        return
    }

    if (!isAuthenticated.value) {
        router.visit(route('login'))
        return
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

gsap.registerPlugin(ScrollTrigger);

const scrollTo = (id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
};

onMounted(() => {
    nextTick(() => {
        // Navbar slide down
        gsap.from('nav', {
            y: -100,
            opacity: 0,
            duration: 1.5,
            ease: 'power4.out'
        });

        // Hero Stagger
        const heroTl = gsap.timeline();
        heroTl.from('.hero-content > *', {
            y: 40,
            opacity: 0,
            duration: 1.5,
            stagger: 0.2,
            ease: 'power4.out',
            delay: 0.2
        });

        // Hero Visual Sequence
        const visualTl = gsap.timeline({ delay: 0.6 });
        visualTl.fromTo('.hero-visual',
            { y: 80, opacity: 0 },
            { y: 0, opacity: 1, duration: 1.5, ease: 'power4.out' }
        )
            .to('.hero-visual', {
                y: -20,
                duration: 4,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1
            });

        // Social Proof
        gsap.from('.social-proof span', {
            scrollTrigger: {
                trigger: '.social-proof',
                start: 'top 95%',
            },
            y: 20,
            opacity: 0,
            duration: 1.2,
            stagger: 0.1,
            ease: 'power4.out'
        });

        // Generic Scroll Fade Up
        const fadeElements = document.querySelectorAll('.gsap-fade-up');
        fadeElements.forEach((el) => {
            gsap.from(el, {
                scrollTrigger: {
                    trigger: el,
                    start: 'top 90%',
                    toggleActions: 'play none none reverse'
                },
                y: 40,
                opacity: 0,
                duration: 1.5,
                ease: 'power4.out'
            });
        });

        // Staggered Cards (Features, Comparison)
        // We use a simpler fade-in for the list entry, separate from the glow
        const cardSections = ['.features-grid', '.comparison-grid'];
        cardSections.forEach(section => {
            const cards = document.querySelectorAll(`${section} > div`);
            if (cards.length > 0) {
                gsap.from(cards, {
                    scrollTrigger: {
                        trigger: section,
                        start: 'top 85%',
                    },
                    y: 60,
                    opacity: 0,
                    duration: 1.5,
                    stagger: 0.2,
                    ease: 'power4.out',
                    clearProps: 'all'
                });
            }
        });

        // Mobile Glow Auto-Hover Logic
        // Feature Colors in order: Indigo, Emerald, Amber, Blue, Rose, Purple
        const featureColors = [
            { border: 'rgba(99, 102, 241, 0.3)', shadow: 'rgba(99, 102, 241, 0.1)', glowLine: 'rgba(99, 102, 241, 1)' }, // Indigo
            { border: 'rgba(16, 185, 129, 0.3)', shadow: 'rgba(16, 185, 129, 0.1)', glowLine: 'rgba(16, 185, 129, 1)' }, // Emerald
            { border: 'rgba(245, 158, 11, 0.3)', shadow: 'rgba(245, 158, 11, 0.1)', glowLine: 'rgba(245, 158, 11, 1)' }, // Amber
            { border: 'rgba(59, 130, 246, 0.3)', shadow: 'rgba(59, 130, 246, 0.1)', glowLine: 'rgba(59, 130, 246, 1)' }, // Blue
            { border: 'rgba(244, 63, 94, 0.3)', shadow: 'rgba(244, 63, 94, 0.1)', glowLine: 'rgba(244, 63, 94, 1)' }, // Rose
            { border: 'rgba(168, 85, 247, 0.3)', shadow: 'rgba(168, 85, 247, 0.1)', glowLine: 'rgba(168, 85, 247, 1)' }, // Purple
        ];

        // Apply only on mobile/touch screens via matchMedia
        ScrollTrigger.matchMedia({
            "(max-width: 1024px)": function () {
                const featureCards = document.querySelectorAll('.features-grid > div');
                featureCards.forEach((card, i) => {
                    const color = featureColors[i] || featureColors[0];
                    const glowLine = card.querySelector('.glow-line'); // We will add this class
                    const glowIcon = card.querySelector('.glow-icon'); // We will add this class

                    gsap.to(card, {
                        scrollTrigger: {
                            trigger: card,
                            start: "top center+=100",
                            end: "bottom center-=100",
                            toggleActions: "play reverse play reverse",
                        },
                        borderColor: color.border,
                        boxShadow: `0 25px 50px -12px ${color.shadow}`,
                        y: -4,
                        duration: 0.5,
                        ease: 'power2.out'
                    });

                    if (glowLine) {
                        gsap.to(glowLine, {
                            scrollTrigger: {
                                trigger: card,
                                start: "top center+=100",
                                end: "bottom center-=100",
                                toggleActions: "play reverse play reverse",
                            },
                            opacity: 1,
                            duration: 0.5
                        });
                    }

                    if (glowIcon) {
                        gsap.to(glowIcon, {
                            scrollTrigger: {
                                trigger: card,
                                start: "top center+=100",
                                end: "bottom center-=100",
                                toggleActions: "play reverse play reverse",
                            },
                            scale: 1.1,
                            duration: 0.5
                        });
                    }
                });

                // Comparison Cards (Generic vs Finalyze)
                const comparisonCards = document.querySelectorAll('.comparison-grid > div');

                // Generic (Zinc)
                if (comparisonCards[0]) {
                    gsap.to(comparisonCards[0], {
                        scrollTrigger: {
                            trigger: comparisonCards[0],
                            start: "top center+=100",
                            end: "bottom center-=100",
                            toggleActions: "play reverse play reverse",
                        },
                        boxShadow: '0 20px 25px -5px rgba(39, 39, 42, 0.5)', // Zinc-800/50
                        y: -4,
                        duration: 0.5
                    });
                }

                // Finalyze (Indigo)
                if (comparisonCards[1]) {
                    gsap.to(comparisonCards[1], {
                        scrollTrigger: {
                            trigger: comparisonCards[1],
                            start: "top center+=100",
                            end: "bottom center-=100",
                            toggleActions: "play reverse play reverse",
                        },
                        boxShadow: '0 25px 50px -12px rgba(99, 102, 241, 0.2)',
                        y: -4,
                        duration: 0.5
                    });
                }
            }
        });

        // Refresh ScrollTrigger to ensure positions are correct
        ScrollTrigger.refresh();
    });
});

onUnmounted(() => {
    ScrollTrigger.getAll().forEach(t => t.kill());
});
</script>

<template>

    <Head title="Master Your Final Year Project" />

    <div
        class="min-h-screen bg-[#09090b] text-zinc-100 font-sans selection:bg-zinc-800 selection:text-white overflow-x-hidden">

        <!-- Subtle Background Gradients (Lucid Style - Faint & diffused) -->
        <div class="fixed inset-0 z-0 pointer-events-none">
            <div
                class="absolute top-[-10%] left-[20%] w-[40rem] h-[40rem] bg-indigo-500/5 blur-[120px] rounded-full mix-blend-screen">
            </div>
            <div
                class="absolute bottom-[-10%] right-[10%] w-[30rem] h-[30rem] bg-blue-500/5 blur-[100px] rounded-full mix-blend-screen">
            </div>
        </div>

        <nav
            class="relative z-50 border-b border-white/5 bg-[#09090b]/80 backdrop-blur-xl supports-[backdrop-filter]:bg-[#09090b]/60">
            <div class="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center gap-2 group cursor-pointer">
                    <Link :href="route('home')">
                        <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 1561.3 468.1"
                            class="h-8 md:h-10 w-auto fill-white">
                            <switch>
                                <g>
                                    <path
                                        d="M1560.6 275.4h-167.4c-6.7 0-6.7 0-5 6.5 7.5 28.1 26.5 43.3 54.4 47.7 26.7 4.2 50.8-1.8 70.3-21.8.3-.3.7-.5 1.5-1 10.6 11.4 21.3 22.8 32.8 35.2-7.2 6.1-14.1 12.6-21.7 18.1-24.9 17.9-53.1 22.6-83.1 20.4-20.6-1.5-39.9-7-57.6-17.6-32.2-19.3-50.6-48.1-55.4-84.8-4.3-32.9.1-64.6 19.5-92.9 20-29.2 47.5-46.4 82.8-50.4 30.6-3.5 58.9 2.5 84.3 20.6 22.8 16.3 34.8 39.3 41.1 65.8 4.4 17.7 4.8 35.6 3.5 54.2zm-56.6-39.3c.3-25.1-19.5-55.1-56-55-38.9.2-58 28-60 55h116zM603.3 204.9c-1.8-3.6-3.6-6.8-5.2-10.2-4.5-9-8.7-18.1-13.6-26.9-2.2-3.9-1.3-5.6 2.1-7.8 17.8-11.6 36.8-20.3 58-23.7 23.9-3.8 47.8-3.8 71.2 2.9 36.4 10.4 57.3 34.8 63.2 72 1.2 7.7 1.9 15.6 1.9 23.4.2 46 .1 92 .1 138 0 1.2-.1 2.4-.2 3.8h-53.9v-24.2c-2.6 1.9-4.5 2.9-6 4.4-12.9 12.3-27.9 20.8-45.6 23-32.7 4-63.3-.9-87.1-26.4-10.5-11.3-16.5-25-17.2-40.7-.8-17.2 2.2-33.3 13.3-47.1 11.8-14.7 27.6-23.5 45.8-26.9 13.5-2.5 27.5-3.2 41.3-3.8 15.5-.6 31.1-.2 46.6-.1 3.5 0 4.5-1.1 4.3-4.4-1.7-27.3-16.6-44.8-44.6-47.4-26.1-2.4-49.4 4.7-70.5 19.8-1.2.7-2.4 1.3-3.9 2.3zm119.4 68.7c-18.7 0-36.2-.5-53.6.2-7.5.3-15.2 2.2-22.3 4.7-10.8 3.9-17 12.2-17.8 23.9-.9 12.9 4.3 22.8 15.8 29.2 9.8 5.4 20.3 5.9 31.2 5.4 15.6-.8 28-8 37.7-19.7 3.5-4.2 6.5-9.8 7.4-15.2 1.3-9.3 1.1-18.8 1.6-28.5zM478.5 376.3v-25.2c0-34.5.1-69-.1-103.5-.1-13-1.3-25.9-6.9-37.9-9.2-19.6-26.5-26.4-45.7-24.5-28.6 2.9-45.3 20.1-51.1 49.6-1.2 6.3-1.7 12.9-1.7 19.3-.2 34.8-.1 69.7-.1 104.5v17.7h-58.5V137.5h55.7v26.9c.4.1.8.3 1.1.4 2.9-3 5.5-6.2 8.7-8.8 23.7-19.7 51.3-24.3 81-21.4 20.3 2 37.6 10.6 51.7 24.9 12.5 12.7 18.8 28.9 21.9 46.1 1.8 9.7 2.7 19.7 2.7 29.6.3 45.5.1 91.1.1 136.6 0 1.3-.1 2.6-.2 4.2-19.4.3-38.7.3-58.6.3zM94.8 188.1v188H36.5V188H0v-47.6h36.9c0-8.3-.4-16.2.1-24.1C38.1 96.9 43.5 79 56.4 64c12.5-14.3 28.4-22.4 47-25.5 24.3-4 47.3-.6 69.6 11.3-5.1 15.3-10.2 30.5-15 45.1-8.6-2.4-16.7-5.5-25-6.5-6.5-.8-13.7-.1-19.9 1.9-10 3.2-15.6 11.3-17 21.5-1.2 9.2-1.2 18.5-1.8 28.3h61.4c.2 1.4.5 2.4.4 3.3 0 13.4-.2 26.8 0 40.3 0 3.5-1.2 4.3-4.4 4.3-17.2-.1-34.4 0-51.6 0-1.6.1-3.2.1-5.3.1zM1237.5 185h-95.2c8.7-11.5 17-22.4 25.2-33.2 1.4-1.8 3.1-3.4 4.1-5.4 3.9-7.9 10-9.7 18.6-9.5 39.2.7 78.4.6 117.6.5 3.8 0 4.5 1.2 4.4 4.6-.2 10.1.1 20.2-.1 30.3 0 2.2-.7 4.9-2.1 6.6-21.6 28.1-43.4 56.1-65.2 84.1-15.6 20.1-31.1 40.2-46.6 60.3-.9 1.1-1.6 2.4-3 4.4h120.2v48.5h-196.7c-.1-1.1-.3-2.4-.3-3.7 0-7.8.5-15.7-.2-23.5-.6-8.1 2-14.3 7-20.5 22.1-27.8 43.8-55.9 65.7-83.8 14.6-18.6 29.2-37.1 43.8-55.7 1-.9 1.6-2.1 2.8-4zM886.2 376.4h-58.3V41.3h58.3v335.1zM918.3 163.9c19.6-4.4 39.3-7.8 59.5-6.1 9.1.7 15.2 7 20.1 14 9.4 13.2 18.4 26.7 27.6 40.1.6.9 1.2 1.8 1.8 2.6.3.3.8.4 1.7.9 1.6-2.2 3.3-4.5 4.9-6.8 23.6-34.2 50.3-65.8 78.5-96.1 35.8-38.5 75.5-72.1 121.3-98.2 9.2-5.3 19.3-8.9 29.1-13.3 1.3-.6 2.9-.6 4.7-1-4.8 5.1-9.2 9.9-13.7 14.5-18.3 18.7-37.5 36.7-54.9 56.3-20.3 22.8-39.8 46.4-58.2 70.8-31.8 42-58.9 87.2-85.8 132.4-3 5-6.1 10-9.6 14.7-7.2 9.8-17.2 11.5-25.9 3.3-7.5-7-14.3-15-20.2-23.5-11.2-16.1-21.3-33.1-32.4-49.3-13.2-19.4-29.5-36.1-46.5-52.2-.8-.7-1.7-1.2-2.6-1.8.2-.4.4-.9.6-1.3zM206.6 137.6h57.9v238.7h-57.9V137.6z" />
                                    <path
                                        d="M995.3 303.3c2.8 2.3 7.2 5.7 11.5 9.2 15.8 13 37.4 11 49.2-5.6 6.8-9.5 11.7-20.2 17.4-30.4.8-1.5 1.4-3.1 3.2-4.4 0 1.1.2 2.2 0 3.3-5.3 27.1-10.5 54.2-16.1 81.3-4.2 20.5-9.5 40.7-18.2 59.7-8.8 19.4-21.3 35.6-41.4 44.5-14 6.2-28.6 7.8-43.7 6.8-18.5-1.3-35.1-7.8-50-18.5-.5-.3-.9-.7-1.6-1.4 7-14.5 14-28.9 21.1-43.5 5.6 3 10.3 5.7 15.3 8.1 8.4 3.9 17.2 5.4 26.4 4 13.2-2 21.3-10.5 25.3-22.4 2.8-8.4 5-17.5 5.2-26.3.3-17-1-34-1.8-51-.5-5.3-1.5-10.7-1.8-13.4zM236.2 100.8c-22.3.3-38.8-18.1-38.3-37.2.5-20.5 17.4-36.5 39.3-36.4 19.8.1 36.9 17.5 36.8 37.5-.1 20.3-16.7 36.2-37.8 36.1z" />

                                </g>
                            </switch>
                        </svg>
                    </Link>
                </div>

                <!-- Desktop Links -->
                <div class="hidden md:flex items-center gap-8 text-sm font-medium text-zinc-400">
                    <button @click="scrollTo('features')"
                        class="hover:text-white transition-colors duration-300">Features</button>
                    <button @click="scrollTo('comparison')"
                        class="hover:text-white transition-colors duration-300">Comparison</button>
                    <button @click="scrollTo('pricing')"
                        class="hover:text-white transition-colors duration-300">Pricing</button>
                </div>

                <!-- Auth Buttons -->
                <div class="flex items-center gap-2 md:gap-4">
                    <template v-if="user">
                        <Link :href="route('logout')" method="post" as="button"
                            class="text-sm font-medium text-zinc-400 hover:text-white transition-colors flex items-center gap-2">
                            <LogOut class="w-4 h-4" />
                            <span class="hidden sm:inline">Log Out</span>
                        </Link>
                        <Link :href="route('dashboard')"
                            class="group relative px-3 md:px-5 py-2 rounded-lg bg-zinc-100 text-zinc-950 text-sm font-semibold hover:bg-white transition-all shadow-[0_0_15px_rgba(255,255,255,0.1)] hover:shadow-[0_0_25px_rgba(255,255,255,0.2)]">
                            <span class="relative z-10 flex items-center gap-2">
                                Dashboard
                                <ArrowRight
                                    class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5 hidden sm:block" />
                            </span>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')"
                            class="text-sm font-medium text-zinc-400 hover:text-white transition-colors hidden sm:block">
                            Log in
                        </Link>
                        <Link :href="route('register')"
                            class="group relative px-5 py-2 rounded-lg bg-zinc-100 text-zinc-950 text-sm font-semibold hover:bg-white transition-all shadow-[0_0_15px_rgba(255,255,255,0.1)] hover:shadow-[0_0_25px_rgba(255,255,255,0.2)]">
                            <span class="relative z-10 flex items-center gap-2">
                                Get Started
                                <ArrowRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
                            </span>
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="relative z-10">
            <!-- Hero Section -->
            <section class="relative pt-24 pb-24 md:pt-32 md:pb-40 overflow-hidden">
                <div class="max-w-7xl mx-auto px-6 text-center hero-content">

                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-zinc-900/50 border border-white/10 text-zinc-400 text-xs font-medium mb-8 backdrop-blur-md">
                        <span class="relative flex h-2 w-2">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                        </span>
                        v2.0 Now Available: Advanced Citation Manager
                    </div>

                    <h1
                        class="text-4xl md:text-5xl lg:text-7xl font-semibold tracking-tight mb-8 leading-[1.1] text-white">
                        Research smarter. <br />
                        <span class="text-zinc-500">Write with confidence.</span>
                    </h1>

                    <p class="text-lg md:text-xl text-zinc-400 max-w-2xl mx-auto mb-12 leading-relaxed">
                        The AI companion that understands your entire project context.
                        Structured guidance, verifiable citations, and academic-grade writing tailored for university
                        success.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link v-if="user" :href="route('dashboard')"
                            class="h-12 px-8 rounded-xl bg-white text-black font-semibold hover:bg-zinc-200 transition-all shadow-[0_4px_20px_-4px_rgba(255,255,255,0.25)] hover:shadow-[0_8px_30px_-4px_rgba(255,255,255,0.3)] hover:-translate-y-1 flex items-center justify-center gap-2 w-full sm:w-auto hover-glow">
                            <LayoutTemplate class="w-4 h-4" />
                            Go to Dashboard
                        </Link>
                        <Link v-else :href="route('register')"
                            class="h-12 px-8 rounded-xl bg-white text-black font-semibold hover:bg-zinc-200 transition-all shadow-[0_4px_20px_-4px_rgba(255,255,255,0.25)] hover:shadow-[0_8px_30px_-4px_rgba(255,255,255,0.3)] hover:-translate-y-1 flex items-center justify-center gap-2 w-full sm:w-auto hover-glow">
                            <Sparkles class="w-4 h-4" />
                            Start Your Project
                        </Link>
                        <button @click="scrollTo('features')"
                            class="h-12 px-8 rounded-xl bg-zinc-900/50 border border-zinc-800 text-white font-medium hover:bg-zinc-900 hover:border-zinc-700 transition-all backdrop-blur-sm flex items-center justify-center gap-2 w-full sm:w-auto hover:-translate-y-1">
                            <LayoutTemplate class="w-4 h-4 text-zinc-400" />
                            View Examples
                        </button>
                    </div>

                    <!-- Hero Visual / Glass Interface -->
                    <div class="mt-24 relative mx-auto max-w-6xl perspective-1000 hero-visual">
                        <div
                            class="absolute inset-x-0 -top-20 h-[500px] bg-gradient-to-b from-indigo-500/5 via-transparent to-transparent opacity-50 blur-3xl pointer-events-none">
                        </div>

                        <div
                            class="relative rounded-2xl border border-white/10 bg-[#0c0c0e]/80 backdrop-blur-sm shadow-2xl shadow-black/50 overflow-hidden transform rotate-x-2 transition-transform duration-1000 hover:rotate-0">
                            <!-- Mac-style Header (Browser Frame) -->
                            <div
                                class="h-10 border-b border-white/5 bg-[#0c0c0e] flex items-center px-4 justify-between">
                                <div class="flex gap-2">
                                    <div class="w-3 h-3 rounded-full bg-[#FF5F56] border border-[#E0443E]"></div>
                                    <div class="w-3 h-3 rounded-full bg-[#FFBD2E] border border-[#DEA123]"></div>
                                    <div class="w-3 h-3 rounded-full bg-[#27C93F] border border-[#1AAB29]"></div>
                                </div>
                                <div
                                    class="px-3 py-0.5 rounded-full bg-zinc-900 border border-white/5 text-[10px] text-zinc-500 font-mono flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-indigo-500/50 animate-pulse"></div>
                                    app.finalyze.ai/dashboard
                                </div>
                                <div class="w-16"></div>
                            </div>

                            <div class="bg-[#09090b]">
                                <img src="/img/finalyze_dasboard.png" alt="Finalyze Dashboard" class="w-full h-auto" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Social Proof -->
            <section class="py-12 border-y border-white/5 bg-[#0c0c0e]/50 backdrop-blur-sm">
                <div class="max-w-7xl mx-auto px-6">
                    <div
                        class="flex flex-wrap justify-center items-center gap-x-12 gap-y-8 opacity-40 grayscale hover:grayscale-0 transition-all duration-500 social-proof">
                        <span class="text-xl font-bold">Harvard</span>
                        <span class="text-xl font-bold">Stanford</span>
                        <span class="text-xl font-bold">MIT</span>
                        <span class="text-xl font-bold">Oxford</span>
                        <span class="text-xl font-bold">Cambridge</span>
                    </div>
                </div>
            </section>

            <!-- Features Grid (The "Lucid Cards") -->
            <section id="features" class="py-32 relative">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="text-center max-w-3xl mx-auto mb-20 gsap-fade-up">
                        <h2 class="text-3xl md:text-5xl font-bold mb-6 text-white tracking-tight">Everything you need to
                            <span class="text-zinc-500">succeed</span></h2>
                        <p class="text-zinc-400 text-lg">A consolidated workspace designed specifically for the academic
                            writing
                            workflow.</p>
                    </div>

                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 features-grid">
                        <!-- Feature 1: Topic Lab -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/20 backdrop-blur-sm p-8 transition-all duration-300 hover:border-indigo-500/30 hover:shadow-2xl hover:shadow-indigo-500/10 hover:-translate-y-1">
                            <div
                                class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-indigo-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 glow-line">
                            </div>
                            <div
                                class="mb-6 inline-flex p-3 rounded-xl bg-indigo-500/10 text-indigo-500 ring-1 ring-indigo-500/20 shadow-[0_0_15px_-3px_rgba(99,102,241,0.3)] group-hover:scale-110 transition-transform duration-300 glow-icon">
                                <Target class="h-6 w-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-3 text-white">Topic Generation Lab</h3>
                            <p class="text-zinc-400 leading-relaxed text-sm">
                                Analyzes your field of study to suggest novel, feasible research topics tailored to your
                                interests and academic level.
                            </p>
                        </div>

                        <!-- Feature 2: Structured Writing -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/20 backdrop-blur-sm p-8 transition-all duration-300 hover:border-emerald-500/30 hover:shadow-2xl hover:shadow-emerald-500/10 hover:-translate-y-1">
                            <div
                                class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-emerald-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 glow-line">
                            </div>
                            <div
                                class="mb-6 inline-flex p-3 rounded-xl bg-emerald-500/10 text-emerald-500 ring-1 ring-emerald-500/20 shadow-[0_0_15px_-3px_rgba(16,185,129,0.3)] group-hover:scale-110 transition-transform duration-300 glow-icon">
                                <BookOpen class="h-6 w-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-3 text-white">Structured Writing</h3>
                            <p class="text-zinc-400 leading-relaxed text-sm">
                                Break down your thesis into manageable chapters. Our AI understands the nuances of
                                Introductions, Lit Reviews, and Conclusions.
                            </p>
                        </div>

                        <!-- Feature 3: Smart Citations -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/20 backdrop-blur-sm p-8 transition-all duration-300 hover:border-amber-500/30 hover:shadow-2xl hover:shadow-amber-500/10 hover:-translate-y-1">
                            <div
                                class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-amber-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 glow-line">
                            </div>
                            <div
                                class="mb-6 inline-flex p-3 rounded-xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20 shadow-[0_0_15px_-3px_rgba(245,158,11,0.3)] group-hover:scale-110 transition-transform duration-300 glow-icon">
                                <GraduationCap class="h-6 w-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-3 text-white">Smart Citations</h3>
                            <p class="text-zinc-400 leading-relaxed text-sm">
                                Auto-suggests verified sources and formats citations perfectly in APA, MLA, Harvard, or
                                Chicago
                                styles.
                            </p>
                        </div>

                        <!-- Feature 4: Export -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/20 backdrop-blur-sm p-8 transition-all duration-300 hover:border-blue-500/30 hover:shadow-2xl hover:shadow-blue-500/10 hover:-translate-y-1">
                            <div
                                class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 glow-line">
                            </div>
                            <div
                                class="mb-6 inline-flex p-3 rounded-xl bg-blue-500/10 text-blue-500 ring-1 ring-blue-500/20 shadow-[0_0_15px_-3px_rgba(59,130,246,0.3)] group-hover:scale-110 transition-transform duration-300 glow-icon">
                                <FileText class="h-6 w-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-3 text-white">Export Ready</h3>
                            <p class="text-zinc-400 leading-relaxed text-sm">
                                Download perfectly formatted PDF or Word documents with auto-generated Tables of
                                Contents and
                                Reference lists.
                            </p>
                        </div>

                        <!-- Feature 5: Context AI -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/20 backdrop-blur-sm p-8 transition-all duration-300 hover:border-rose-500/30 hover:shadow-2xl hover:shadow-rose-500/10 hover:-translate-y-1">
                            <div
                                class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-rose-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 glow-line">
                            </div>
                            <div
                                class="mb-6 inline-flex p-3 rounded-xl bg-rose-500/10 text-rose-500 ring-1 ring-rose-500/20 shadow-[0_0_15px_-3px_rgba(244,63,94,0.3)] group-hover:scale-110 transition-transform duration-300 glow-icon">
                                <Bot class="h-6 w-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-3 text-white">Project Memory</h3>
                            <p class="text-zinc-400 leading-relaxed text-sm">
                                Unlike chat tools, Finalyze remembers your hypothesis, methodology, and previous
                                chapters for
                                consistent output.
                            </p>
                        </div>

                        <!-- Feature 6: Grades -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/20 backdrop-blur-sm p-8 transition-all duration-300 hover:border-purple-500/30 hover:shadow-2xl hover:shadow-purple-500/10 hover:-translate-y-1">
                            <div
                                class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 glow-line">
                            </div>
                            <div
                                class="mb-6 inline-flex p-3 rounded-xl bg-purple-500/10 text-purple-500 ring-1 ring-purple-500/20 shadow-[0_0_15px_-3px_rgba(168,85,247,0.3)] group-hover:scale-110 transition-transform duration-300 glow-icon">
                                <Sparkles class="h-6 w-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-3 text-white">Grade Booster</h3>
                            <p class="text-zinc-400 leading-relaxed text-sm">
                                Real-time checks for academic tone, clarity, and argument flow to help you aim for that
                                distinction.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Comparison Section -->
            <section id="comparison" class="py-24 border-t border-white/5 bg-[#09090b]">
                <div class="max-w-5xl mx-auto px-6">
                    <div class="text-center mb-16 gsap-fade-up">
                        <h2 class="text-3xl md:text-4xl font-bold mb-6 text-white">Why not just use <span
                                class="text-zinc-500">ChatGPT?</span></h2>
                        <p class="text-zinc-400 text-lg">Chatbots are great for quick answers. They struggle with
                            10,000-word
                            academic projects.</p>
                    </div>

                    <div class="grid md:grid-cols-2 gap-8 items-start comparison-grid">
                        <!-- Generic Chat -->
                        <div
                            class="relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/30 p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-zinc-800/50">
                            <div class="flex items-center gap-4 mb-8">
                                <div class="w-12 h-12 rounded-full bg-zinc-800 flex items-center justify-center">
                                    <Bot class="w-6 h-6 text-zinc-400" />
                                </div>
                                <h3 class="text-xl font-bold text-zinc-300">Generic Chat</h3>
                            </div>
                            <ul class="space-y-4">
                                <li class="flex items-start gap-3 text-zinc-500">
                                    <XCircle class="w-5 h-5 text-zinc-700 shrink-0 mt-0.5" />
                                    <span>Hallucinates citations & sources</span>
                                </li>
                                <li class="flex items-start gap-3 text-zinc-500">
                                    <XCircle class="w-5 h-5 text-zinc-700 shrink-0 mt-0.5" />
                                    <span>Loses context after long conversations</span>
                                </li>
                                <li class="flex items-start gap-3 text-zinc-500">
                                    <XCircle class="w-5 h-5 text-zinc-700 shrink-0 mt-0.5" />
                                    <span>No formatting or document export</span>
                                </li>
                                <li class="flex items-start gap-3 text-zinc-500">
                                    <XCircle class="w-5 h-5 text-zinc-700 shrink-0 mt-0.5" />
                                    <span>Requires endless copy-pasting</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Finalyze -->
                        <div
                            class="relative overflow-hidden rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-8 shadow-2xl shadow-indigo-500/10 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-indigo-500/20">
                            <div
                                class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-indigo-500 to-transparent">
                            </div>
                            <div class="flex items-center gap-4 mb-8">
                                <div
                                    class="w-12 h-12 rounded-full bg-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-500/40">
                                    <Sparkles class="w-6 h-6 text-white" />
                                </div>
                                <h3 class="text-xl font-bold text-white">Finalyze</h3>
                            </div>
                            <ul class="space-y-4">
                                <li class="flex items-start gap-3 text-zinc-200">
                                    <CheckCircle class="w-5 h-5 text-indigo-400 shrink-0 mt-0.5" />
                                    <span>Verifiable, real academic sources</span>
                                </li>
                                <li class="flex items-start gap-3 text-zinc-200">
                                    <CheckCircle class="w-5 h-5 text-indigo-400 shrink-0 mt-0.5" />
                                    <span>Full awareness of all project chapters</span>
                                </li>
                                <li class="flex items-start gap-3 text-zinc-200">
                                    <CheckCircle class="w-5 h-5 text-indigo-400 shrink-0 mt-0.5" />
                                    <span>One-click PDF/Word export</span>
                                </li>
                                <li class="flex items-start gap-3 text-zinc-200">
                                    <CheckCircle class="w-5 h-5 text-indigo-400 shrink-0 mt-0.5" />
                                    <span>Structured academic workflow</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pricing / CTA -->
            <section id="pricing" class="py-32 relative overflow-hidden bg-[#0c0c0e]">
                <div
                    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-white/[0.02] rounded-full blur-3xl pointer-events-none">
                </div>

                <div class="max-w-7xl mx-auto px-6 relative z-10">
                    <!-- Header -->
                    <div class="text-center max-w-3xl mx-auto mb-16 gsap-fade-up">
                        <h2 class="text-4xl md:text-5xl font-bold mb-6 text-white">Simple, Transparent Pricing</h2>
                        <p class="text-xl text-zinc-400">Pay once for your project. No subscriptions. Credits never
                            expire.</p>
                    </div>

                    <!-- Payment System Not Configured Warning -->
                    <Alert v-if="!paystackConfigured" variant="destructive"
                        class="mb-8 max-w-2xl mx-auto border-red-900/50 bg-red-900/10 text-red-200">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>Payment System Unavailable</AlertTitle>
                        <AlertDescription>
                            The payment system is currently not configured. You can view pricing, but payments cannot be
                            processed at this time.
                        </AlertDescription>
                    </Alert>

                    <!-- Flash Messages -->
                    <Alert v-if="flash?.success"
                        class="mb-8 max-w-2xl mx-auto border-green-500/50 bg-green-900/10 text-green-200">
                        <Check class="h-4 w-4 text-green-500" />
                        <AlertDescription>
                            {{ flash.success }}
                        </AlertDescription>
                    </Alert>

                    <Alert v-if="flash?.error" variant="destructive"
                        class="mb-8 max-w-2xl mx-auto border-red-900/50 bg-red-900/10 text-red-200">
                        <AlertDescription>{{ flash.error }}</AlertDescription>
                    </Alert>

                    <div v-if="packages">
                        <!-- Project Packages -->
                        <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto mb-16">
                            <Card v-for="pkg in packages.projects" :key="pkg.id" :class="[
                                'relative transition-all duration-300 hover:shadow-2xl hover:border-indigo-500/30 hover:-translate-y-1 bg-zinc-900/40 border-white/10 text-zinc-100',
                                pkg.is_popular ? 'border-indigo-500/50 shadow-lg shadow-indigo-500/10 ring-1 ring-indigo-500/20' : ''
                            ]">
                                <!-- Popular Badge -->
                                <Badge v-if="pkg.is_popular"
                                    class="absolute -top-3 left-1/2 -translate-x-1/2 bg-indigo-500 hover:bg-indigo-600 border-0">
                                    <Sparkles class="w-3 h-3 mr-1" />
                                    Most Popular
                                </Badge>

                                <CardHeader class="text-center pb-2">
                                    <CardTitle class="text-2xl">{{ pkg.name }}</CardTitle>
                                    <CardDescription class="text-zinc-400">
                                        {{ pkg.description }}
                                    </CardDescription>
                                </CardHeader>

                                <CardContent class="text-center">
                                    <!-- Price -->
                                    <div class="mb-6">
                                        <div v-if="pkg.price === 0">
                                            <span class="text-4xl font-bold">Free</span>
                                            <span class="text-zinc-500 ml-2">on signup</span>
                                        </div>
                                        <div v-else>
                                            <span class="text-4xl font-bold">{{ pkg.formatted_price }}</span>
                                            <span class="text-zinc-500 ml-2">one-time</span>
                                        </div>
                                    </div>

                                    <!-- Words/Credits -->
                                    <div class="bg-zinc-900/50 border border-white/5 rounded-lg p-4 mb-6">
                                        <div class="text-3xl font-bold text-indigo-400">
                                            {{ pkg.formatted_words }}
                                        </div>
                                        <div class="text-sm text-zinc-500">credits included</div>
                                    </div>

                                    <!-- Features -->
                                    <ul class="space-y-3 text-left">
                                        <li v-for="feature in pkg.features" :key="feature"
                                            class="flex items-start gap-2 text-zinc-300">
                                            <Check class="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                                            <span class="text-sm">{{ feature }}</span>
                                        </li>
                                    </ul>
                                </CardContent>

                                <CardFooter>
                                    <Button class="w-full bg-white text-black hover:bg-zinc-200" size="lg"
                                        :disabled="processingPackage === pkg.id || (pkg.price > 0 && !paystackConfigured)"
                                        @click="initializePayment(pkg)">
                                        <Loader2 v-if="processingPackage === pkg.id"
                                            class="mr-2 h-4 w-4 animate-spin" />
                                        <template v-else>
                                            <span v-if="pkg.price === 0">Get Started Free</span>
                                            <span v-else>{{ paystackConfigured ? 'Purchase Package' : 'Unavailable'
                                                }}</span>
                                        </template>
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>

                        <!-- Top-up Section -->
                        <div class="max-w-4xl mx-auto mb-24">
                            <div class="text-center mb-8">
                                <Button variant="ghost" @click="showTopups = !showTopups"
                                    class="text-zinc-400 hover:text-white hover:bg-white/5">
                                    <Zap class="w-4 h-4 mr-2" />
                                    {{ showTopups ? 'Hide' : 'Show' }} Top-up Packs
                                </Button>
                                <p class="text-sm text-zinc-500 mt-2">
                                    Need more credits? Buy additional credit packs anytime.
                                </p>
                            </div>

                            <div v-show="showTopups" class="grid md:grid-cols-3 gap-6">
                                <Card v-for="pkg in packages.topups" :key="pkg.id" :class="[
                                    'relative transition-all duration-300 hover:shadow-lg bg-zinc-900/40 border-white/10 text-zinc-100',
                                    pkg.is_popular ? 'border-indigo-500/50' : ''
                                ]">
                                    <Badge v-if="pkg.is_popular" variant="secondary"
                                        class="absolute -top-2 right-4 bg-zinc-800 text-zinc-300">
                                        Best Value
                                    </Badge>

                                    <CardHeader class="pb-2">
                                        <CardTitle class="text-lg">{{ pkg.name }}</CardTitle>
                                    </CardHeader>

                                    <CardContent>
                                        <div class="text-2xl font-bold mb-1">
                                            {{ pkg.formatted_price }}
                                        </div>
                                        <div class="text-indigo-400 font-medium mb-4">
                                            {{ pkg.formatted_words }} credits
                                        </div>
                                        <ul class="space-y-1">
                                            <li v-for="feature in pkg.features" :key="feature"
                                                class="flex items-center gap-2 text-xs text-zinc-400">
                                                <Check class="h-3 w-3 text-green-500" />
                                                {{ feature }}
                                            </li>
                                        </ul>
                                    </CardContent>

                                    <CardFooter>
                                        <Button variant="outline"
                                            class="w-full border-white/10 text-zinc-300 hover:bg-white/5 hover:text-white"
                                            :disabled="!paystackConfigured || processingPackage === pkg.id"
                                            @click="initializePayment(pkg)">
                                            <Loader2 v-if="processingPackage === pkg.id"
                                                class="mr-2 h-4 w-4 animate-spin" />
                                            <template v-else>{{ paystackConfigured ? 'Buy Now' : 'Unavailable'
                                                }}</template>
                                        </Button>
                                    </CardFooter>
                                </Card>
                            </div>
                        </div>
                    </div>

                    <!-- Fallback CTA if no packages or simpler display -->
                    <div v-else class="text-center mb-16">
                        <h2 class="text-3xl font-bold mb-4 text-white">Ready to finish your project?</h2>
                        <p class="text-zinc-400 mb-8">Join thousands of students who have verified their research and
                            graduated
                            with Finalyze.</p>
                        <Link :href="route('register')"
                            class="bg-white text-black px-8 py-3 rounded-full font-bold hover:bg-zinc-200 transition-colors">
                            Get
                            Started Free</Link>
                    </div>

                    <!-- FAQ / Trust Signals -->
                    <div class="max-w-3xl mx-auto text-center">
                        <h2 class="text-xl font-semibold mb-6">Frequently Asked Questions</h2>

                        <div class="space-y-4 text-left">
                            <div
                                class="p-4 rounded-lg bg-zinc-900/30 border border-white/5 hover:border-white/10 transition-colors">
                                <h3 class="font-bold text-zinc-200 mb-2">What is a credit?</h3>
                                <p class="text-sm text-zinc-400 leading-relaxed">
                                    A credit equals one generated word. When the AI writes for you, it consumes credits
                                    from
                                    your balance.
                                </p>
                            </div>
                            <div
                                class="p-6 rounded-xl bg-zinc-900/30 border border-white/5 hover:border-white/10 transition-colors">
                                <h3 class="font-bold text-zinc-200 mb-2">Do credits expire?</h3>
                                <p class="text-sm text-zinc-400 leading-relaxed">
                                    No! Your credits never expire. Use them whenever you're ready, whether it's this
                                    semester or
                                    next year.
                                </p>
                            </div>

                            <div
                                class="p-6 rounded-xl bg-zinc-900/30 border border-white/5 hover:border-white/10 transition-colors">
                                <h3 class="font-bold text-zinc-200 mb-2">What if I run out of credits?</h3>
                                <p class="text-sm text-zinc-400 leading-relaxed">
                                    Simply purchase a top-up pack. Your new credits are added instantly to your account
                                    balance.
                                </p>
                            </div>

                            <div
                                class="p-6 rounded-xl bg-zinc-900/30 border border-white/5 hover:border-white/10 transition-colors">
                                <h3 class="font-bold text-zinc-200 mb-2">Can I use credits across projects?</h3>
                                <p class="text-sm text-zinc-400 leading-relaxed">
                                    Yes! Your credit balance is account-wide. Use it for unlimited projects, seminars,
                                    or
                                    presentations.
                                </p>
                            </div>

                            <div
                                class="p-6 rounded-xl bg-zinc-900/30 border border-white/5 hover:border-white/10 transition-colors">
                                <h3 class="font-bold text-zinc-200 mb-2">What payment methods?</h3>
                                <p class="text-sm text-zinc-400 leading-relaxed">
                                    We accept all Nigerian bank cards, bank transfers, and USSD payments securely
                                    processed via
                                    Paystack.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="border-t border-white/5 bg-[#09090b] py-12">
                <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-2 opacity-50 hover:opacity-100 transition-opacity">
                        <Sparkles class="w-4 h-4 text-white" />
                        <span class="font-bold text-zinc-200">Finalyze</span>
                    </div>

                    <div class="text-sm text-zinc-600">
                        &copy; {{ new Date().getFullYear() }} Finalyze. All rights reserved.
                    </div>
                </div>
            </footer>
        </main>
    </div>
</template>

<style scoped>
.perspective-1000 {
    perspective: 1000px;
}

.hover-glow {
    transition: box-shadow 0.3s ease;
}

.hover-glow:hover {
    box-shadow: 0 0 30px -5px rgba(99, 102, 241, 0.3);
}
</style>
