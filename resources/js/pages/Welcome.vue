<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, nextTick, ref, computed } from 'vue';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import AppLogo from '@/components/AppLogo.vue';
import PWAInstallPrompt from '@/components/PWAInstallPrompt.vue';
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
    X,
    AlertCircle,
    Check,
    Loader2,
    LogOut,
    School,
    Lightbulb,
    Terminal,
    Wifi,
    WifiOff,
    RefreshCw,
    Play,
    Pause,
    RotateCcw,
    AlertTriangle,
    Edit,
    Shield,
    Users,
    MessageSquare,
    Presentation,
    Brain,
    Trophy
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
const isMobileMenuOpen = ref(false)
const processingPackage = ref<number | null>(null)
const showTopups = ref(false)
const currentPackage = ref<Package | null>(null)

const testimonials = [
    {
        name: 'Adebayo O.',
        initials: 'AO',
        major: 'B.Sc. Computer Science',
        text: 'The Literature Review used to take me weeks to draft. With Finalyze, I was able to synthesize 15 papers into a coherent chapter in just two days. The citations are actually accurate!',
        gradient: 'from-indigo-500 to-purple-500',
        border: 'hover:border-indigo-500/30'
    },
    {
        name: 'Chidi N.',
        initials: 'CN',
        major: 'M.Sc. Economics',
        text: 'I was struggling with my methodology section until I found this. It guided me through the right terminology and structure. I just defended my project and got an A!',
        gradient: 'from-purple-500 to-rose-500',
        border: 'hover:border-purple-500/30',
        offset: true
    },
    {
        name: 'Fatima E.',
        initials: 'FE',
        major: 'B.A. Political Science',
        text: 'The topic generator is a lifesaver. My supervisor rejected 3 of my initial ideas, but the one I got from Finalyze was approved immediately. It really understands academic standards.',
        gradient: 'from-emerald-500 to-teal-500',
        border: 'hover:border-emerald-500/30'
    }
];

// Flash messages
const flash = computed(() => page.props.flash as { success?: string; error?: string })

// Check if user is logged in
const isAuthenticated = computed(() => !!page.props.auth?.user)

// Simulation State
const simulationStatus = ref<'idle' | 'running' | 'completed'>('idle');
const simulationProgress = ref(0);
const simulationLogs = ref<{ timestamp: string, message: string, type: string }[]>([]);
const currentSimulationStage = ref(-1);

const simulationStages = ref<any[]>([
    { id: 'mining', name: 'Literature Mining', description: 'Collecting research papers from academic databases', type: 'mining', time: '12.4s', words: 0, target: 0 },
    { id: 'ch1', name: 'Chapter 1', subtitle: 'Introduction', description: 'Processing and saving Chapter 1...', type: 'chapter', time: '35.6s', words: 0, target: 1500 },
    { id: 'ch2', name: 'Chapter 2', subtitle: 'Literature Review', description: 'Processing and saving Chapter 2...', type: 'chapter', time: '87.7s', words: 0, target: 2000 },
    { id: 'ch3', name: 'Chapter 3', subtitle: 'Research Methodology', description: 'Processing and saving Chapter 3...', type: 'chapter', time: '61.6s', words: 0, target: 1500 },
    { id: 'ch4', name: 'Chapter 4', subtitle: 'Results and Discussion', description: 'Processing and saving Chapter 4...', type: 'chapter', time: '96.5s', words: 0, target: 2000 },
    { id: 'ch5', name: 'Chapter 5', subtitle: 'Summary and Conclusion', description: 'Processing and saving Chapter 5...', type: 'chapter', time: '42.2s', words: 0, target: 1500 },
    { id: 'final', name: 'Finalizing Project', description: 'Formatting content and preparing final document', type: 'final', time: '15.1s', words: 0, target: 0 },
]);

let simulationTimeout: any = null;

const startSimulation = () => {
    if (simulationStatus.value === 'running') return;
    
    simulationStatus.value = 'running';
    simulationProgress.value = 0;
    simulationLogs.value = [];
    currentSimulationStage.value = 0;
    
    // Reset stages
    simulationStages.value.forEach(s => {
        s.words = 0;
    });

    const runStage = async (index: number) => {
        if (index >= simulationStages.value.length) {
            simulationStatus.value = 'completed';
            simulationProgress.value = 100;
            currentSimulationStage.value = index;
            // Wait 10 seconds and restart the loop
            simulationTimeout = setTimeout(() => {
                simulationStatus.value = 'idle';
                startSimulation();
            }, 10000);
            return;
        }

        currentSimulationStage.value = index;
        const stage = simulationStages.value[index];

        const timestamp = () => new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

        if (stage.type === 'mining') {
            simulationLogs.value.unshift({ timestamp: timestamp(), message: 'Searching multiple academic databases in parallel...', type: 'info' });
            await new Promise(r => setTimeout(r, 1200));
            simulationLogs.value.unshift({ timestamp: timestamp(), message: 'Found 12 papers from Crossref', type: 'mining' });
            simulationLogs.value.unshift({ timestamp: timestamp(), message: 'Found 8 papers from OpenAlex', type: 'mining' });
            await new Promise(r => setTimeout(r, 800));
            simulationLogs.value.unshift({ timestamp: timestamp(), message: 'Literature mining completed - 8 papers collected', type: 'success' });
            simulationProgress.value = Math.round(((index + 1) / simulationStages.value.length) * 100);
            runStage(index + 1);
        } else if (stage.type === 'chapter') {
            simulationLogs.value.unshift({ timestamp: timestamp(), message: `Starting ${stage.name}: ${stage.subtitle}`, type: 'stage' });
            
            const duration = 5; // seconds
            const targetWordCount = stage.target + Math.floor(Math.random() * 100);
            
            gsap.to(stage, {
                words: targetWordCount,
                duration: duration,
                ease: "none",
                onUpdate: () => {
                    const stageBaseProgress = (index / simulationStages.value.length) * 100;
                    const stageContribution = (1 / simulationStages.value.length) * 100;
                    simulationProgress.value = Math.round(stageBaseProgress + (stageContribution * (stage.words / targetWordCount)));
                }
            });

            await new Promise(r => setTimeout(r, duration * 1000));
            simulationLogs.value.unshift({ timestamp: timestamp(), message: `${stage.name} completed (${Math.round(stage.words).toLocaleString()} words)`, type: 'success' });
            runStage(index + 1);
        } else if (stage.type === 'final') {
            simulationLogs.value.unshift({ timestamp: timestamp(), message: 'Formatting final document...', type: 'info' });
            await new Promise(r => setTimeout(r, 2000));
            simulationLogs.value.unshift({ timestamp: timestamp(), message: 'Project generation successful!', type: 'success' });
            simulationProgress.value = 100;
            runStage(index + 1);
        }
    };

    runStage(0);
};

const resetSimulation = () => {
    if (simulationTimeout) clearTimeout(simulationTimeout);
    simulationStatus.value = 'idle';
    simulationProgress.value = 0;
    simulationLogs.value = [];
    currentSimulationStage.value = -1;
    simulationStages.value.forEach(s => {
        s.words = 0;
    });
};

onMounted(() => {
    setTimeout(() => {
        startSimulation();
    }, 1000);
});


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

        // Testimonials Animation
        const testimonialItems = document.querySelectorAll('.testimonials-grid > div');
        testimonialItems.forEach((item, i) => {
            gsap.fromTo(item, 
                { 
                    y: 40, 
                    opacity: 0,
                    visibility: 'hidden'
                },
                {
                    scrollTrigger: {
                        trigger: item,
                        start: 'top 92%',
                        toggleActions: 'play none none reverse'
                    },
                    y: 0,
                    opacity: 1,
                    visibility: 'visible',
                    duration: 1.2,
                    delay: i * 0.15,
                    ease: 'power3.out',
                    clearProps: 'transform,visibility'
                }
            );
        });

        ScrollTrigger.refresh();

        // Journey Section Animation (Detailed Loop)
        const journeySteps = document.querySelectorAll('.journey-step-item');
        const journeyLine = document.querySelector('.journey-line-progress');

        if (journeySteps.length > 0) {
            // Colors for each step
            const stepColors = ['#818cf8', '#c084fc', '#fbbf24', '#34d399', '#60a5fa']; // Indigo, Purple, Amber, Emerald, Blue
            let journeyLoopTl: gsap.core.Timeline | null = null;

            // Initial Timeline (Entrance)
            const entryTl = gsap.timeline({
                scrollTrigger: {
                    trigger: '#how-it-works',
                    start: 'top 75%',
                    onEnter: () => startJourneyLoop(),
                    once: true
                }
            });

            entryTl.from(journeySteps, {
                y: 50,
                opacity: 0,
                duration: 0.8,
                stagger: 0.2,
                ease: 'back.out(1.7)'
            });

            // Master Loop function
            function startJourneyLoop() {
                if (journeyLoopTl) {
                    journeyLoopTl.kill();
                }

                const masterTl = gsap.timeline({ repeat: -1, repeatDelay: 1 });
                journeyLoopTl = masterTl;

                // Reset State
                masterTl.set(journeyLine, { width: '0%', opacity: 1 })
                        .set('.processing-ring', { opacity: 0, rotation: 0 })
                        .set('.step-completed-bg', { opacity: 0 })
                        .set('.mobile-line-progress', { height: '0%' })
                        .set('.journey-step-icon', { color: '#71717a', scale: 1 }) // zinc-500
                        .set('.journey-step-icon-bg', { borderColor: '#27272a' }); // zinc-800

                journeySteps.forEach((step, index) => {
                    // Use querySelectorAll to target BOTH desktop and mobile versions of elements
                    const rings = step.querySelectorAll('.processing-ring');
                    const iconBgs = step.querySelectorAll('.journey-step-icon-bg');
                    const icons = step.querySelectorAll('.journey-step-icon');
                    const completedBgs = step.querySelectorAll('.step-completed-bg');
                    const mobileLine = step.querySelector('.mobile-line-progress');
                    const color = stepColors[index] ?? stepColors[stepColors.length - 1];
                    const connectorColor = stepColors[index + 1] ?? color;

                    // Set mobile line color
                    if (mobileLine) {
                        gsap.set(mobileLine, { backgroundColor: connectorColor });
                    }

                    // Step Processing State
                    masterTl.addLabel(`step-${index}`)
                            .to(rings, { opacity: 1, duration: 0.3 }, `step-${index}`)
                            .to(rings, { rotation: 360, duration: 2, ease: 'linear', repeat: 0 }, `step-${index}`) // Rotate once per step duration
                            .to(icons, { color: color, scale: 1.2, duration: 0.5, yoyo: true, repeat: 3 }, `step-${index}`)
                            .to(iconBgs, { borderColor: color, duration: 0.5 }, `step-${index}`);

                    // Step Completion State
                    masterTl.to(rings, { opacity: 0, duration: 0.3 })
                            .to(completedBgs, { opacity: 1, duration: 0.5 }, "<")
                            .to(icons, { color: color, scale: 1, duration: 0.5 }, "<");

                    // Animate Line to next segment
                    if (index < journeySteps.length - 1) {
                         // e.g. 0 -> 33%, 33% -> 66%, 66% -> 100%
                         const nextWidth = ((index + 1) / (journeySteps.length - 1)) * 100;
                         masterTl.to(journeyLine, { width: `${nextWidth}%`, duration: 0.8, ease: 'power2.inOut' });
                         
                         // Animate Mobile Line
                         if (mobileLine) {
                             masterTl.to(mobileLine, { height: '100%', duration: 0.8, ease: 'power2.inOut' }, "<");
                         }
                    }
                });

                // End of Loop: Fade out line to restart
                masterTl.to(journeyLine, { opacity: 0, duration: 0.5, delay: 0.5 });
            }
        }

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
            <div class="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between relative">
                <!-- Logo -->
                <div class="flex items-center gap-2 group cursor-pointer">
                    <Link :href="route('home')">
                        <AppLogo class="h-8 md:h-10 w-auto fill-white" />
                    </Link>
                </div>

                <!-- Desktop Links (Absolute Center) -->
                <div
                    class="hidden md:flex items-center gap-8 text-sm font-medium text-zinc-400 absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                    <Link :href="route('project-topics.index')" class="hover:text-white transition-colors duration-300">
                        Topic Library</Link>
                    <button @click="scrollTo('features')"
                        class="hover:text-white transition-colors duration-300">Features</button>
                    <button @click="scrollTo('defense')"
                        class="hover:text-white transition-colors duration-300">Defense Lab</button>
                    <button @click="scrollTo('comparison')"
                        class="hover:text-white transition-colors duration-300">Comparison</button>
                    <button @click="scrollTo('pricing')"
                        class="hover:text-white transition-colors duration-300">Pricing</button>
                </div>

                <!-- Right Side (Auth + Mobile Toggle) -->
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

                    <!-- Mobile Menu Toggle -->
                    <button @click="isMobileMenuOpen = !isMobileMenuOpen"
                        class="md:hidden text-zinc-400 hover:text-white p-2">
                        <Menu v-if="!isMobileMenuOpen" class="w-6 h-6" />
                        <X v-else class="w-6 h-6" />
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Menu -->
            <transition enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2">
                <div v-if="isMobileMenuOpen"
                    class="md:hidden absolute top-20 left-0 w-full bg-[#09090b]/95 backdrop-blur-xl border-b border-white/5 py-6 px-4 flex flex-col gap-4 shadow-2xl">
                    <Link :href="route('project-topics.index')"
                        class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2 text-left">
                        Topic Library
                    </Link>
                    <button @click="scrollTo('features'); isMobileMenuOpen = false"
                        class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2 text-left">Features</button>
                    <button @click="scrollTo('defense'); isMobileMenuOpen = false"
                        class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2 text-left">Defense Lab</button>
                    <button @click="scrollTo('comparison'); isMobileMenuOpen = false"
                        class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2 text-left">Comparison</button>
                    <button @click="scrollTo('pricing'); isMobileMenuOpen = false"
                        class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2 text-left">Pricing</button>

                    <div class="h-px bg-white/5 my-2"></div>

                    <template v-if="!user">
                        <Link :href="route('login')"
                            class="text-base font-medium text-zinc-400 hover:text-white transition-colors py-2">
                            Log in
                        </Link>
                    </template>
                </div>
            </transition>
        </nav>

        <main class="relative z-10">
            <!-- Hero Section -->
            <section class="relative pt-24 pb-24 md:pt-32 md:pb-40 overflow-hidden">
                <div class="max-w-7xl mx-auto px-6 text-center hero-content">

                    <h1
                        class="text-4xl md:text-5xl lg:text-7xl font-semibold tracking-tight mb-8 leading-[1.1] text-white">
                        AI that works with you end to end. <br />
                        <span class="text-zinc-500">From topic to defense.</span>
                    </h1>

                    <p class="text-lg md:text-xl text-zinc-400 max-w-2xl mx-auto mb-12 leading-relaxed">
                        For final year projects and theses: choose a topic, get approval, write with AI assistance,
                        generate full drafts, and prepare for defense backed by verified citations.
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
                        <button @click="scrollTo('demo')"
                            class="h-12 px-8 rounded-xl bg-zinc-900/50 border border-zinc-800 text-white font-medium hover:bg-zinc-900 hover:border-zinc-700 transition-all backdrop-blur-sm flex items-center justify-center gap-2 w-full sm:w-auto hover:-translate-y-1">
                            <Play class="w-4 h-4 text-zinc-400" />
                            Watch System Demo
                        </button>
                    </div>

                    <!-- Hero Visual / Glass Interface -->
                    <div class="mt-24 relative mx-auto max-w-6xl perspective-1000 hero-visual">
                        <div
                            class="absolute inset-x-0 -top-20 h-[500px] bg-gradient-to-b from-indigo-500/5 via-transparent to-transparent opacity-50 blur-3xl pointer-events-none">
                        </div>

                        <div
                            class="group relative rounded-2xl border border-white/10 bg-[#0c0c0e]/80 backdrop-blur-sm shadow-2xl shadow-black/50 overflow-hidden transform rotate-x-2 transition-transform duration-1000 hover:rotate-0 flex flex-col">
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

                            <div class="relative bg-[#09090b] overflow-hidden aspect-[16/10]">
                                <img src="/img/finalyze_dasboard.png" alt="Finalyze Dashboard"
                                    class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03]" />
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
                            <span class="text-zinc-500">succeed</span>
                        </h2>
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

            <!-- How It Works (Journey) -->
            <section id="how-it-works" class="py-24 relative overflow-hidden bg-[#0c0c0e]">
                <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-500/5 rounded-full blur-[100px]"></div>
                </div>

                <div class="max-w-7xl mx-auto px-6 relative z-10">
                    <div class="text-center max-w-3xl mx-auto mb-20 gsap-fade-up">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-zinc-900/50 border border-white/10 text-indigo-400 text-xs font-medium mb-6 backdrop-blur-md">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                            </span>
                            Simple Workflow
                        </div>
                        <h2 class="text-3xl md:text-5xl font-bold mb-6 text-white tracking-tight">
                            From Idea to Draft in <span class="text-indigo-500">Minutes</span>
                        </h2>
                        <p class="text-zinc-400 text-lg">
                            Stop worrying about "what to write". Finalyze guides you from a blank page to an approved topic and a full draft.
                        </p>
                    </div>

                    <div class="relative">
                        <!-- Connecting Line (Desktop) -->
                        <div class="hidden md:block absolute top-[2.5rem] left-[10%] w-[80%] h-0.5 bg-zinc-800/50">
                            <div class="absolute top-0 left-0 h-full bg-gradient-to-r from-indigo-500 via-purple-500 via-amber-500 via-emerald-500 to-blue-500 w-0 journey-line-progress"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-5 gap-12 relative journey-steps">
                            <!-- Step 1: Profile -->
                            <div class="relative group journey-step-item">
                                <div class="hidden md:flex items-center justify-center w-20 h-20 rounded-2xl bg-[#09090b] border border-zinc-800 relative z-10 mx-auto mb-8 transition-all duration-300 journey-step-icon-bg">
                                    <div class="absolute inset-[-4px] rounded-2xl border-2 border-dashed border-indigo-500/50 opacity-0 processing-ring"></div>
                                    <div class="absolute inset-0 bg-indigo-500/10 rounded-2xl opacity-0 transition-opacity step-completed-bg"></div>
                                    <School class="w-8 h-8 text-zinc-500 transition-colors relative z-10 journey-step-icon" />
                                </div>
                                <!-- Mobile connector -->
                                <div class="md:hidden absolute left-5 top-10 bottom-[-64px] w-0.5 bg-zinc-800/50 overflow-hidden">
                                    <div class="absolute top-0 left-0 w-full h-0 mobile-line-progress"></div>
                                </div>

                                <div class="pl-16 md:pl-0 md:text-center relative">
                                    <div class="md:hidden absolute left-0 top-0 flex items-center justify-center w-10 h-10 rounded-lg bg-[#09090b] border border-zinc-800 z-10 journey-step-icon-bg">
                                        <div class="absolute inset-[-4px] rounded-lg border-2 border-dashed border-indigo-500/50 opacity-0 processing-ring"></div>
                                        <div class="absolute inset-0 bg-indigo-500/10 rounded-lg opacity-0 transition-opacity step-completed-bg"></div>
                                        <School class="w-5 h-5 text-indigo-400 transition-colors relative z-10 journey-step-icon" />
                                    </div>
                                    <h3 class="text-xl font-bold text-white mb-2">1. Project Setup</h3>
                                    <p class="text-sm text-zinc-400 leading-relaxed">
                                        Choose your academic level and project type so we can tailor the structure and requirements.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 2: Discovery -->
                            <div class="relative group journey-step-item">
                                <div class="hidden md:flex items-center justify-center w-20 h-20 rounded-2xl bg-[#09090b] border border-zinc-800 relative z-10 mx-auto mb-8 transition-all duration-300 journey-step-icon-bg">
                                    <div class="absolute inset-[-4px] rounded-2xl border-2 border-dashed border-purple-500/50 opacity-0 processing-ring"></div>
                                    <div class="absolute inset-0 bg-purple-500/10 rounded-2xl opacity-0 transition-opacity step-completed-bg"></div>
                                    <Lightbulb class="w-8 h-8 text-zinc-500 transition-colors relative z-10 journey-step-icon" />
                                </div>
                                <!-- Mobile connector -->
                                <div class="md:hidden absolute left-5 top-10 bottom-[-64px] w-0.5 bg-zinc-800/50 overflow-hidden">
                                    <div class="absolute top-0 left-0 w-full h-0 mobile-line-progress"></div>
                                </div>

                                <div class="pl-16 md:pl-0 md:text-center relative">
                                    <div class="md:hidden absolute left-0 top-0 flex items-center justify-center w-10 h-10 rounded-lg bg-[#09090b] border border-zinc-800 z-10 journey-step-icon-bg">
                                        <div class="absolute inset-[-4px] rounded-lg border-2 border-dashed border-purple-500/50 opacity-0 processing-ring"></div>
                                        <div class="absolute inset-0 bg-purple-500/10 rounded-lg opacity-0 transition-opacity step-completed-bg"></div>
                                        <Lightbulb class="w-5 h-5 text-purple-400 transition-colors relative z-10 journey-step-icon" />
                                    </div>
                                    <h3 class="text-xl font-bold text-white mb-2">2. Create Project</h3>
                                    <p class="text-sm text-zinc-400 leading-relaxed">
                                        Add your institution, department, and supervisor details to create your project workspace.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 3: Proposal -->
                            <div class="relative group journey-step-item">
                                <div class="hidden md:flex items-center justify-center w-20 h-20 rounded-2xl bg-[#09090b] border border-zinc-800 relative z-10 mx-auto mb-8 transition-all duration-300 journey-step-icon-bg">
                                    <div class="absolute inset-[-4px] rounded-2xl border-2 border-dashed border-amber-500/50 opacity-0 processing-ring"></div>
                                    <div class="absolute inset-0 bg-amber-500/10 rounded-2xl opacity-0 transition-opacity step-completed-bg"></div>
                                    <FileText class="w-8 h-8 text-zinc-500 transition-colors relative z-10 journey-step-icon" />
                                </div>
                                <!-- Mobile connector -->
                                <div class="md:hidden absolute left-5 top-10 bottom-[-64px] w-0.5 bg-zinc-800/50 overflow-hidden">
                                    <div class="absolute top-0 left-0 w-full h-0 mobile-line-progress"></div>
                                </div>

                                <div class="pl-16 md:pl-0 md:text-center relative">
                                    <div class="md:hidden absolute left-0 top-0 flex items-center justify-center w-10 h-10 rounded-lg bg-[#09090b] border border-zinc-800 z-10 journey-step-icon-bg">
                                        <div class="absolute inset-[-4px] rounded-lg border-2 border-dashed border-amber-500/50 opacity-0 processing-ring"></div>
                                        <div class="absolute inset-0 bg-amber-500/10 rounded-lg opacity-0 transition-opacity step-completed-bg"></div>
                                        <FileText class="w-5 h-5 text-amber-400 transition-colors relative z-10 journey-step-icon" />
                                    </div>
                                    <h3 class="text-xl font-bold text-white mb-2">3. Topic Selection</h3>
                                    <p class="text-sm text-zinc-400 leading-relaxed">
                                        Generate topic ideas with AI or enter your own, then refine and select the best fit.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 4: Approval -->
                            <div class="relative group journey-step-item">
                                <div class="hidden md:flex items-center justify-center w-20 h-20 rounded-2xl bg-[#09090b] border border-zinc-800 relative z-10 mx-auto mb-8 transition-all duration-300 journey-step-icon-bg">
                                    <div class="absolute inset-[-4px] rounded-2xl border-2 border-dashed border-emerald-500/50 opacity-0 processing-ring"></div>
                                    <div class="absolute inset-0 bg-emerald-500/10 rounded-2xl opacity-0 transition-opacity step-completed-bg"></div>
                                    <CheckCircle class="w-8 h-8 text-zinc-500 transition-colors relative z-10 journey-step-icon" />
                                </div>
                                <!-- Mobile connector -->
                                <div class="md:hidden absolute left-5 top-10 bottom-[-64px] w-0.5 bg-zinc-800/50 overflow-hidden">
                                    <div class="absolute top-0 left-0 w-full h-0 mobile-line-progress"></div>
                                </div>

                                <div class="pl-16 md:pl-0 md:text-center relative">
                                    <div class="md:hidden absolute left-0 top-0 flex items-center justify-center w-10 h-10 rounded-lg bg-[#09090b] border border-zinc-800 z-10 journey-step-icon-bg">
                                        <div class="absolute inset-[-4px] rounded-lg border-2 border-dashed border-emerald-500/50 opacity-0 processing-ring"></div>
                                        <div class="absolute inset-0 bg-emerald-500/10 rounded-lg opacity-0 transition-opacity step-completed-bg"></div>
                                        <CheckCircle class="w-5 h-5 text-emerald-400 transition-colors relative z-10 journey-step-icon" />
                                    </div>
                                    <h3 class="text-xl font-bold text-white mb-2">4. Supervisor Approval</h3>
                                    <p class="text-sm text-zinc-400 leading-relaxed">
                                        Share the selected topic for review and mark it approved to unlock the Chapter Editor.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 5: Writing -->
                            <div class="relative group journey-step-item">
                                <div class="hidden md:flex items-center justify-center w-20 h-20 rounded-2xl bg-[#09090b] border border-zinc-800 relative z-10 mx-auto mb-8 transition-all duration-300 journey-step-icon-bg">
                                    <div class="absolute inset-[-4px] rounded-2xl border-2 border-dashed border-blue-500/50 opacity-0 processing-ring"></div>
                                    <div class="absolute inset-0 bg-blue-500/10 rounded-2xl opacity-0 transition-opacity step-completed-bg"></div>
                                    <PenTool class="w-8 h-8 text-zinc-500 transition-colors relative z-10 journey-step-icon" />
                                </div>

                                <div class="pl-16 md:pl-0 md:text-center relative">
                                    <div class="md:hidden absolute left-0 top-0 flex items-center justify-center w-10 h-10 rounded-lg bg-[#09090b] border border-zinc-800 z-10 journey-step-icon-bg">
                                        <div class="absolute inset-[-4px] rounded-lg border-2 border-dashed border-blue-500/50 opacity-0 processing-ring"></div>
                                        <div class="absolute inset-0 bg-blue-500/10 rounded-lg opacity-0 transition-opacity step-completed-bg"></div>
                                        <PenTool class="w-5 h-5 text-blue-400 transition-colors relative z-10 journey-step-icon" />
                                    </div>
                                    <h3 class="text-xl font-bold text-white mb-2">5. AI-Assisted Writing</h3>
                                    <p class="text-sm text-zinc-400 leading-relaxed">
                                        Use AI-assisted writing or generate a full project draft with all chapters and preliminary pages.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Live Generation Simulation -->
            <section id="demo" class="py-12 md:py-24 relative overflow-hidden bg-[#09090b]">
                <div class="max-w-7xl mx-auto px-4 md:px-6 relative z-10">
                    <div class="text-center max-w-4xl mx-auto mb-10 md:mb-16 gsap-fade-up">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] md:text-xs font-medium mb-6 md:mb-8 backdrop-blur-md">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                            </span>
                            Live System Demo
                        </div>
                        
                        <div class="space-y-4 mb-8 md:mb-12">
                            <h2 class="text-2xl md:text-5xl font-bold mb-4 md:mb-6 text-white tracking-tight leading-tight px-2">
                                Watch the <span class="text-indigo-500">Engine</span> in Action
                            </h2>
                            <div class="flex items-center justify-center gap-4">
                                <p v-if="simulationStatus === 'completed'" class="text-green-500 text-xs md:text-sm font-bold animate-bounce">Project Ready!</p>
                                <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-green-500/10 border border-green-500/20 text-[9px] md:text-[10px] font-bold text-green-500 uppercase tracking-widest">
                                    <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div>
                                    System Online
                                </div>
                            </div>
                        </div>

                        <p class="text-zinc-400 text-sm md:text-lg max-w-2xl mx-auto px-4">
                            Observe how our proprietary engine handles the heavy lifting—from literature mining to final academic formatting in real-time.
                        </p>
                    </div>

                    <div class="grid lg:grid-cols-12 gap-8 md:gap-12 items-start relative px-1 md:px-0">
                        <!-- Left: Stages Timeline -->
                        <div class="lg:col-span-7 space-y-6 md:space-y-8 relative">
                            <h3 class="text-lg md:text-xl font-bold text-white mb-4 md:mb-6 px-2">Generation Sequence</h3>
                            
                            <div class="relative space-y-0 pl-1 md:pl-2">
                                <div v-for="(stage, index) in simulationStages" :key="stage.id" class="relative flex gap-3 md:gap-6 group">
                                    <!-- Stage Icon/Indicator -->
                                    <div class="flex flex-col items-center">
                                        <div class="relative z-10 flex h-8 w-8 md:h-10 md:w-10 items-center justify-center rounded-full border-2 shadow-sm transition-all duration-500"
                                            :class="{
                                                'border-zinc-800 bg-zinc-900 scale-95 opacity-50': currentSimulationStage < index,
                                                'border-primary bg-background ring-2 ring-primary/20 scale-110 shadow-[0_0_10px_rgba(var(--primary),0.3)]': currentSimulationStage === index,
                                                'border-green-500 bg-green-500 text-white scale-100 shadow-green-500/20': currentSimulationStage > index,
                                            }">
                                            <Check v-if="currentSimulationStage > index" class="h-3 w-3 md:h-4 md:w-4 stroke-[3]" />
                                            <Loader2 v-else-if="currentSimulationStage === index"
                                                class="h-3 w-3 md:h-4 md:w-4 animate-spin text-primary" />
                                            <component v-else :is="stage.type === 'mining' ? Search : stage.type === 'chapter' ? FileText : Sparkles"
                                                class="h-3 w-3 md:h-3.5 md:w-3.5 text-zinc-500" />
                                        </div>

                                        <!-- Segmented Vertical Line -->
                                        <div v-if="index < simulationStages.length - 1"
                                            class="w-0.5 flex-1 transition-all duration-700 ease-in-out mt-[-2px] mb-[-2px]"
                                            :class="{
                                                'bg-green-500': currentSimulationStage > index,
                                                'bg-zinc-800/50': currentSimulationStage <= index
                                            }">
                                            <div v-if="currentSimulationStage === index"
                                                class="w-full h-full bg-gradient-to-b from-primary to-transparent animate-pulse shadow-[0_0_10px_rgba(var(--primary),0.5)]">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content Card -->
                                    <div class="flex-1 pb-6 md:pb-10 min-w-0">
                                        <div
                                            class="flex flex-col gap-3 md:gap-4 p-4 md:p-5 rounded-2xl border border-white/5 bg-white/[0.02] backdrop-blur-md hover:bg-white/[0.05] transition-all duration-500"
                                            :class="{ 'border-primary/20 bg-primary/[0.02]': currentSimulationStage === index }">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-2 md:gap-3">
                                                        <h4 class="font-bold text-base md:text-lg text-white" :class="{ 'text-primary': currentSimulationStage === index }">
                                                            {{ stage.name }}
                                                        </h4>
                                                        <Badge v-if="currentSimulationStage >= index" variant="secondary" class="text-[9px] md:text-[10px] h-4 md:h-5 bg-zinc-800 text-zinc-400 border-none font-mono">
                                                            {{ stage.time }}
                                                        </Badge>
                                                    </div>
                                                    <p class="text-[11px] md:text-sm leading-relaxed" :class="currentSimulationStage === index ? 'text-zinc-300' : 'text-zinc-500'">
                                                        {{ stage.type === 'chapter' && currentSimulationStage === index ? 'Generating content with AI-assisted research...' : 
                                                           stage.type === 'chapter' && currentSimulationStage > index ? stage.subtitle : stage.description }}
                                                     </p>
                                                 </div>
                                                 <div v-if="currentSimulationStage === index" class="hidden sm:block">
                                                     <Loader2 class="h-5 w-5 animate-spin text-primary opacity-50" />
                                                 </div>
                                             </div>

                                             <!-- Chapter Specific Progress UI -->
                                             <div v-if="stage.type === 'chapter' && currentSimulationStage >= index" 
                                                 class="bg-black/40 rounded-xl p-3 md:p-4 space-y-2 md:space-y-3 border border-white/5 shadow-inner">
                                                 <div class="flex justify-between text-[8px] md:text-[10px] font-bold uppercase tracking-widest text-zinc-500">
                                                     <span>Content Generation</span>
                                                     <span class="font-mono">
                                                         <span :class="currentSimulationStage === index ? 'text-primary' : 'text-green-500'">
                                                             {{ Math.round(stage.words).toLocaleString() }}
                                                         </span>
                                                         <span class="opacity-30 mx-1">/</span>
                                                         {{ stage.target.toLocaleString() }} words
                                                     </span>
                                                 </div>
                                                 <div class="h-1.5 md:h-2 w-full bg-zinc-800 rounded-full overflow-hidden">
                                                     <div class="h-full transition-all duration-500 ease-out relative"
                                                         :class="currentSimulationStage === index ? 'bg-primary shadow-[0_0_15px_rgba(var(--primary),0.4)]' : 'bg-green-500'"
                                                         :style="{ width: `${Math.min((stage.words / stage.target) * 100, 100)}%` }">
                                                         <div v-if="currentSimulationStage === index"
                                                             class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer"></div>
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                            </div>
                        </div>

                        <!-- Right Sidebar: Controls & Logs -->
                        <div class="lg:col-span-5 flex flex-col gap-6 lg:sticky lg:top-24 mt-4 lg:mt-0">
                            <!-- Main Progress Card -->
                            <div class="group relative">
                                <div class="absolute -inset-0.5 bg-gradient-to-r from-primary to-purple-600 rounded-2xl blur opacity-10 group-hover:opacity-20 transition duration-1000"></div>
                                <Card class="relative border-white/5 bg-zinc-900/80 backdrop-blur-xl shadow-2xl p-6 md:p-8 rounded-2xl overflow-hidden">
                                    <div class="flex flex-col items-center justify-center py-2 md:py-4">
                                        <!-- Circular Progress Ring (Smaller on mobile) -->
                                        <div class="relative w-36 h-36 md:w-48 md:h-48 mb-6 md:mb-8">
                                            <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                                                <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" class="text-zinc-800" stroke-width="8" />
                                                <circle cx="50" cy="50" r="45" fill="none" stroke="url(#demo-gradient)" stroke-width="8" stroke-linecap="round" class="transition-all duration-1000 ease-out"
                                                    :stroke-dasharray="2 * Math.PI * 45"
                                                    :stroke-dashoffset="2 * Math.PI * 45 * (1 - simulationProgress / 100)" />
                                                <defs>
                                                    <linearGradient id="demo-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                                        <stop offset="0%" stop-color="#3b82f6" />
                                                        <stop offset="100%" stop-color="#8b5cf6" />
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                                <span class="text-3xl md:text-4xl font-bold text-white tracking-tighter tabular-nums">{{ simulationProgress }}%</span>
                                                <span class="text-[9px] md:text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-0.5 md:mt-1">
                                                    {{ simulationStatus === 'completed' ? 'Complete' : 'Progress' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-center space-y-2 md:space-y-3 mb-6 md:mb-8">
                                            <p class="text-zinc-300 text-xs md:text-sm font-medium leading-relaxed px-4">
                                                {{ simulationStatus === 'completed' ? 'Your project has been successfully generated.' : 
                                                   currentSimulationStage >= 0 ? `Processing ${simulationStages[currentSimulationStage].name}...` : 'System initialized and ready' }}
                                            </p>
                                            <div v-if="simulationStatus !== 'completed'" class="flex items-center justify-center gap-2 text-[10px] md:text-xs text-zinc-500 bg-zinc-800/80 px-4 py-1.5 md:py-2 rounded-full w-fit mx-auto border border-white/5">
                                                <Clock class="h-3 md:h-3.5 w-3 md:w-3.5 text-primary" />
                                                <span class="font-mono">~{{ Math.max(1, (simulationStages.length - currentSimulationStage)) * 2 }} mins remaining</span>
                                            </div>
                                            <div v-else class="flex items-center justify-center gap-2 text-[10px] md:text-xs text-green-500 bg-green-500/10 px-4 py-1.5 md:py-2 rounded-full w-fit mx-auto border border-green-500/20">
                                                <Check class="h-3 md:h-3.5 w-3 md:w-3.5" />
                                                <span class="font-mono">Process Complete</span>
                                            </div>
                                        </div>

                                        <!-- Controls Map to status -->
                                        <div class="w-full space-y-3 md:space-y-4 pt-4 border-t border-white/5">
                                            <Button v-if="simulationStatus === 'idle'" @click="startSimulation" class="w-full bg-white text-black hover:bg-zinc-200 font-bold h-11 md:h-12 rounded-xl transition-all hover:scale-[1.02] text-sm md:text-base">
                                                <Play class="mr-2 h-4 w-4 fill-current" /> Initialize Generation
                                            </Button>

                                            <Button v-if="simulationStatus === 'running'" variant="destructive" @click="resetSimulation" class="w-full bg-red-600/20 border border-red-500 text-red-500 hover:bg-red-600 hover:text-white font-bold h-11 md:h-12 rounded-xl transition-all shadow-lg shadow-red-500/10 text-sm md:text-base">
                                                Cancel Process
                                            </Button>

                                            <div v-if="simulationStatus === 'completed'" class="grid grid-cols-2 gap-2 md:gap-3 animate-in fade-in zoom-in duration-500">
                                                <Button class="bg-white text-black hover:bg-zinc-200 font-bold h-11 md:h-12 rounded-xl shadow-lg shadow-white/10 text-xs md:text-sm">
                                                    <Edit class="mr-1.5 md:mr-2 h-3.5 w-3.5 md:h-4 md:w-4" /> View Chapters
                                                </Button>
                                                <Button variant="secondary" class="bg-zinc-800 text-white hover:bg-zinc-700 font-bold h-11 md:h-12 rounded-xl text-xs md:text-sm">
                                                    <FileText class="mr-1.5 md:mr-2 h-3.5 w-3.5 md:h-4 md:w-4" /> Export PDF
                                                </Button>
                                            </div>

                                            <div class="p-3 md:p-4 rounded-xl bg-amber-500/5 border border-amber-500/10 transition-all duration-500" :class="{ 'opacity-100': simulationStatus === 'completed', 'opacity-50': simulationStatus !== 'completed' }">
                                                <div class="flex gap-2 md:gap-3">
                                                    <AlertTriangle class="h-3.5 w-3.5 md:h-4 md:w-4 text-amber-500 shrink-0 mt-0.5" />
                                                    <p class="text-[9px] md:text-[10px] text-zinc-500 leading-relaxed">
                                                        AI is not perfect and can make mistakes. Please review each chapter and make adjustments where necessary. 
                                                        Defending your project requires thorough knowledge.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            </div>

                            <!-- System Logs Section -->
                            <div class="space-y-3 flex-1 flex flex-col min-h-0">
                                <div class="flex items-center justify-between px-1">
                                    <div class="flex items-center gap-2">
                                        <Terminal class="h-3.5 w-3.5 md:h-4 md:w-4 text-zinc-500" />
                                        <h4 class="text-xs md:text-sm font-bold text-zinc-400">System Logs</h4>
                                    </div>
                                    <div class="flex gap-1">
                                        <div class="w-2 h-2 rounded-full bg-red-500/50"></div>
                                        <div class="w-2 h-2 rounded-full bg-amber-500/50"></div>
                                        <div class="w-2 h-2 rounded-full bg-green-500/50"></div>
                                    </div>
                                </div>

                                <div class="flex-1 min-h-[250px] md:min-h-[300px] bg-black border border-white/5 rounded-2xl overflow-hidden flex flex-col shadow-2xl">
                                    <div class="bg-zinc-900/80 border-b border-white/5 px-4 py-1.5 md:py-2 flex justify-center shrink-0">
                                        <span class="text-[8px] md:text-[9px] font-mono text-zinc-600 tracking-widest uppercase">generation_engine_v3.0.log</span>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-4 md:p-5 font-mono text-[9px] md:text-[11px] space-y-1.5 md:space-y-2 custom-scrollbar bg-[#050505]">
                                        <div v-if="simulationLogs.length === 0" class="h-full flex flex-col items-center justify-center text-zinc-800 opacity-50">
                                            <div class="animate-pulse flex flex-col items-center">
                                                <Terminal class="h-8 w-8 md:h-10 md:w-10 mb-2 md:mb-3" />
                                                <p class="text-[10px] md:text-xs">Waiting for initialization...</p>
                                            </div>
                                        </div>
                                        <div v-for="(log, i) in simulationLogs" :key="i" 
                                            class="flex gap-2 md:gap-3 animate-in fade-in slide-in-from-left-2 duration-300 border-l-2 pl-2 md:pl-3 py-0.5 transition-all hover:bg-white/[0.02]"
                                            :class="{
                                                'border-indigo-500/50': log.type === 'info',
                                                'border-green-500/50': log.type === 'success',
                                                'border-purple-500/50': log.type === 'stage',
                                                'border-transparent': !['info', 'success', 'stage'].includes(log.type)
                                            }">
                                            <span class="text-zinc-700 shrink-0 text-[8px] md:text-[10px]">[{{ log.timestamp }}]</span>
                                            <span class="leading-tight text-[9px] md:text-[11px]" :class="{
                                                'text-indigo-400': log.type === 'info',
                                                'text-green-400 font-medium': log.type === 'success',
                                                'text-purple-400 font-bold': log.type === 'stage',
                                                'text-amber-400': log.type === 'mining',
                                                'text-zinc-400': !log.type
                                            }">
                                                <span v-if="log.type === 'stage'" class="text-zinc-600 mr-1 md:mr-2">$</span>
                                                {{ log.message }}
                                            </span>
                                        </div>
                                        <div v-if="simulationStatus === 'running'" class="h-2.5 w-1 md:h-3 md:w-1.5 bg-zinc-700 animate-pulse inline-block ml-1"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Defense Lab Section -->
            <section id="defense" class="py-24 md:py-32 relative overflow-hidden bg-[#0c0c0e]">
                <!-- Background Decoration -->
                <div class="absolute inset-0 z-0 pointer-events-none">
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-rose-500/5 blur-[120px] rounded-full"></div>
                </div>

                <div class="max-w-7xl mx-auto px-6 relative z-10">
                    <div class="text-center max-w-3xl mx-auto mb-20 gsap-fade-up">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium mb-6 backdrop-blur-md">
                            <Shield class="w-3.5 h-3.5" />
                            Final Milestone
                        </div>
                        <h2 class="text-3xl md:text-5xl font-bold mb-6 text-white tracking-tight">
                            Defend with <span class="text-rose-500">Unshakable</span> Confidence
                        </h2>
                        <p class="text-zinc-400 text-lg">
                            Master your research presentation with AI-powered simulators that predict examiner questions and refine your pitch.
                        </p>
                    </div>

                    <div class="grid lg:grid-cols-2 gap-16 items-center">
                        <div class="space-y-8 gsap-fade-up">
                            <!-- Feature 1 -->
                            <div class="group flex gap-6 p-6 rounded-2xl border border-white/5 bg-zinc-900/20 hover:bg-zinc-900/40 transition-all duration-300">
                                <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform">
                                    <Users class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white mb-2">Mock Defense Simulator</h3>
                                    <p class="text-zinc-400 text-sm leading-relaxed">
                                        Face a rotating panel of AI examiners—from the skeptic to the methodologist—who challenge your thesis from multiple angles in a real-time chat simulation.
                                    </p>
                                </div>
                            </div>

                            <!-- Feature 2 -->
                            <div class="group flex gap-6 p-6 rounded-2xl border border-white/5 bg-zinc-900/20 hover:bg-zinc-900/40 transition-all duration-300">
                                <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-rose-500/10 flex items-center justify-center text-rose-400 group-hover:scale-110 transition-transform">
                                    <Brain class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white mb-2">AI-Predicted Questions</h3>
                                    <p class="text-zinc-400 text-sm leading-relaxed">
                                        Our AI analyzes your specific project content to predict the 10 most likely (and most difficult) questions you'll face, providing expert-crafted strategies for each.
                                    </p>
                                </div>
                            </div>

                            <!-- Feature 3 -->
                            <div class="group flex gap-6 p-6 rounded-2xl border border-white/5 bg-zinc-900/20 hover:bg-zinc-900/40 transition-all duration-300">
                                <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover:scale-110 transition-transform">
                                    <Presentation class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white mb-2">Presentation Deck Builder</h3>
                                    <p class="text-zinc-400 text-sm leading-relaxed">
                                        Generate a comprehensive slide outline and speaker notes tailored to your research findings. One-click export to a professional PPTX format.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Visual Preview Card -->
                        <div class="relative gsap-fade-up">
                            <div class="absolute -inset-4 bg-gradient-to-r from-rose-500/20 to-purple-600/20 rounded-[2.5rem] blur-2xl opacity-50"></div>
                            <div class="relative bg-zinc-950 border border-white/10 rounded-[2rem] overflow-hidden shadow-2xl">
                                <!-- Simulation Header -->
                                <div class="p-4 border-b border-white/5 flex items-center justify-between bg-zinc-900/50">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></div>
                                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest leading-none">Simulation Active</span>
                                    </div>
                                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-zinc-800 text-[10px] font-mono text-zinc-400 border border-white/5">
                                        Readiness Score: 85%
                                    </div>
                                </div>
                                
                                <!-- Simulation Content Preview -->
                                <div class="p-6 space-y-6 min-h-[400px]">
                                    <!-- Panelist Question -->
                                    <div class="flex flex-col items-start space-y-2 max-w-[85%]">
                                        <div class="flex items-center gap-2 px-1">
                                            <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-widest">Panelist (The Skeptic)</span>
                                        </div>
                                        <div class="p-4 rounded-2xl rounded-tl-none bg-zinc-800/80 border border-white/5 text-zinc-200 text-xs italic leading-relaxed">
                                            "How does your proposed blockchain framework specifically address the latency issues inherent in public land registry transactions?"
                                        </div>
                                    </div>

                                    <!-- Candidate Response -->
                                    <div class="flex flex-col items-end space-y-2 ml-auto max-w-[85%]">
                                        <div class="flex items-center gap-2 px-1">
                                            <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-widest">Candidate</span>
                                        </div>
                                        <div class="p-4 rounded-2xl rounded-tr-none bg-white text-black text-xs font-medium leading-relaxed">
                                            "Our framework utilizes a sharding-inspired architecture combined with a private side-chain for high-frequency updates, significantly reducing..."
                                        </div>
                                    </div>

                                    <!-- Score Booster Suggestion -->
                                    <div class="mt-8 p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                                        <div class="flex items-center gap-2 mb-2 font-bold text-[10px] uppercase">
                                            <Brain class="w-3.5 h-3.5" />
                                            Defense Strategy Booster
                                        </div>
                                        <p class="text-[11px] leading-relaxed opacity-90">
                                            Highlight the distinction between "Settlement Layer" and "Transaction Layer" to show technical depth.
                                        </p>
                                    </div>

                                    <!-- Prediction Cards Preview -->
                                    <div class="grid grid-cols-2 gap-3 pt-4">
                                        <div class="p-3 rounded-xl border border-white/5 bg-zinc-900 flex flex-col gap-2">
                                            <Target class="w-4 h-4 text-rose-500" />
                                            <span class="text-[10px] font-bold text-white">Predicted Question #4</span>
                                            <span class="text-[9px] text-zinc-500 leading-tight">"What are the limitations of your methodology?"</span>
                                        </div>
                                        <div class="p-3 rounded-xl border border-white/5 bg-zinc-900 flex flex-col gap-2 opacity-50">
                                            <Trophy class="w-4 h-4 text-amber-500" />
                                            <span class="text-[10px] font-bold text-white">Winner Strategy</span>
                                            <span class="text-[9px] text-zinc-500 leading-tight">Focus on data triangulation...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

            <!-- Testimonials Section -->
            <section id="testimonials" class="py-24 relative overflow-hidden bg-[#0c0c0e]">
                <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
                    <div class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-purple-500/5 rounded-full blur-[120px]"></div>
                </div>

                <div class="max-w-7xl mx-auto px-6 relative z-10">
                    <div class="text-center max-w-3xl mx-auto mb-20 gsap-fade-up">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-zinc-900/50 border border-white/10 text-amber-400 text-xs font-medium mb-6 backdrop-blur-md">
                            <div class="flex gap-0.5">
                                <Star v-for="i in 5" :key="i" class="w-3 h-3 fill-current" />
                            </div>
                            Loved by 2,000+ Students
                        </div>
                        <h2 class="text-3xl md:text-5xl font-bold mb-6 text-white tracking-tight">
                            Trusted by Students <span class="text-indigo-500">Worldwide</span>
                        </h2>
                        <p class="text-zinc-400 text-lg">
                            Join thousands of successful graduates who used Finalyze to conquer their final year projects.
                        </p>
                    </div>

                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 testimonials-grid">
                        <div v-for="(t, i) in testimonials" :key="i"
                            :class="[
                                'group relative p-8 rounded-2xl border border-white/5 bg-zinc-900/20 backdrop-blur-sm hover:bg-zinc-900/40 hover:-translate-y-2 transition-colors duration-300',
                                t.border,
                                t.offset ? 'lg:mt-8' : ''
                            ]">
                            <div class="flex gap-1 mb-6 text-amber-500/80">
                                <Star v-for="j in 5" :key="j" class="w-4 h-4 fill-current" />
                            </div>
                            <p class="text-zinc-300 italic mb-8 leading-relaxed">
                                "{{ t.text }}"
                            </p>
                            <div class="flex items-center gap-4">
                                <div :class="['w-12 h-12 rounded-full bg-gradient-to-br p-[2px]', t.gradient]">
                                    <div class="w-full h-full rounded-full bg-zinc-900 flex items-center justify-center font-bold text-white text-lg">
                                        {{ t.initials }}
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white">{{ t.name }}</h4>
                                    <p class="text-xs text-zinc-500 font-medium">{{ t.major }}</p>
                                </div>
                            </div>
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

                    <div v-if="packages && (packages.projects.length > 0 || packages.topups.length > 0)">
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
                    <Link :href="route('home')" class="opacity-50 hover:opacity-100 transition-opacity">
                        <AppLogo class="h-7 w-auto fill-white" />
                    </Link>

                    <div class="text-sm text-zinc-600">
                        &copy; {{ new Date().getFullYear() }} Finalyze. All rights reserved.
                    </div>
                </div>
            </footer>
        </main>
        <PWAInstallPrompt />
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

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.2);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.4);
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-shimmer {
    animation: shimmer 2s infinite linear;
}

@keyframes gradient-x {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
.animate-gradient-x {
    background-size: 200% 200%;
    animation: gradient-x 15s ease infinite;
}

/* List Transitions */
.list-enter-active,
.list-leave-active {
    transition: all 0.4s ease;
}
.list-enter-from {
    opacity: 0;
    transform: translateX(-20px);
}
.list-leave-to {
    opacity: 0;
    transform: translateY(-20px);
}

</style>
