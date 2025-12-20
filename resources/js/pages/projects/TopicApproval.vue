<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Clock, Download, FileText, MessageSquare, XCircle, ShieldCheck, BookOpen, GraduationCap, School, User } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Project {
    id: number;
    slug: string;
    title: string | null;
    topic: string | null;
    type: string;
    status: string;
    field_of_study: string;
    course: string;
    university: string;
    full_university_name: string;
    supervisor_name: string | null;
    description: string | null;
}

interface Props {
    project: Project;
}

const props = defineProps<Props>();

const isSubmitting = ref(false);
const isExporting = ref(false);
const showConfirmDialog = ref(false);
const pendingApproval = ref<boolean | null>(null);

/**
 * PDF EXPORT FOR SUPERVISOR REVIEW
 * Downloads professional PDF document for offline supervisor review
 * Uses Spatie PDF with Tailwind CSS styling
 */
const exportTopicPdf = async () => {
    isExporting.value = true;

    try {
        // Make a proper fetch request to the PDF endpoint
        const response = await fetch(route('topics.export-pdf', props.project.slug), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/pdf',
            },
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        // Get the blob from the response
        const blob = await response.blob();

        // Create a URL for the blob
        const url = window.URL.createObjectURL(blob);

        // Create a temporary link and trigger download
        const link = document.createElement('a');
        link.href = url;
        link.download = `project_topic_proposal_${props.project.slug}.pdf`;
        document.body.appendChild(link);
        link.click();

        // Cleanup
        window.URL.revokeObjectURL(url);
        document.body.removeChild(link);

        toast('📄 PDF Generated!', {
            description: 'Topic proposal downloaded successfully. Share with your supervisor.',
        });
    } catch (error) {
        console.error('PDF Export error:', error);
        toast('Export Failed', {
            description: 'Could not generate PDF. Please try again.',
        });
    } finally {
        isExporting.value = false;
    }
};

const showApprovalDialog = (approved: boolean) => {
    pendingApproval.value = approved;
    showConfirmDialog.value = true;
};

const confirmApproval = async () => {
    if (pendingApproval.value === null) return;

    const approved = pendingApproval.value;
    showConfirmDialog.value = false;
    isSubmitting.value = true;

    try {
        const response = await fetch(route('topics.approve', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                approved: approved,
            }),
        });

        const data = await response.json();

        if (data.success) {
            if (approved) {
                const redirectRoute: string | undefined = data.redirect_route;
                const redirectUrl: string | undefined = data.redirect_url;
                const isAutoMode = data.mode === 'auto' || redirectRoute === 'projects.writing';

                toast(isAutoMode ? 'Topic Approved!' : '🎉 Topic Approved!', {
                    description: isAutoMode
                        ? 'Auto mode selected. Jumping straight into the writing workspace.'
                        : "Perfect! Now let's set up your AI writing assistant based on your mode preference.",
                });

                if (redirectUrl) {
                    router.visit(redirectUrl);
                } else if (redirectRoute === 'projects.writing') {
                    const slug = data.slug || props.project.slug;
                    router.visit(route('projects.writing', slug));
                } else {
                    const slug = data.slug || props.project.slug;
                    router.visit(route('projects.guidance', slug));
                }
            } else {
                toast('New Topic Needed', {
                    description: "No problem! Let's find a topic that works better for your supervisor.",
                });
                router.visit(route('projects.topic-selection', props.project.slug));
            }
        }
    } catch (error) {
        toast('Error', {
            description: 'Failed to update approval status. Please try again.',
        });
    } finally {
        isSubmitting.value = false;
        pendingApproval.value = null;
    }
};

/**
 * GO BACK TO TOPIC SELECTION
 * Allows users to change their topic choice
 */
const goBackToTopicSelection = async () => {
    try {
        // Use Inertia router for better CSRF handling
        router.post(
            route('projects.go-back-to-topic-selection', props.project.slug),
            {},
            {
                onSuccess: () => {
                    toast('Success', {
                        description: 'Returned to topic selection',
                    });
                    // Navigate to topic selection page after successful state update
                    router.visit(route('projects.topic-selection', props.project.slug));
                },
                onError: () => {
                    toast('Error', {
                        description: 'Failed to go back to topic selection. Please try again.',
                    });
                },
            },
        );
    } catch (error) {
        toast('Error', {
            description: 'Failed to go back to topic selection. Please try again.',
        });
    }
};
</script>

<template>
    <AppLayout title="Topic Approval">
        <div class="min-h-screen bg-background relative selection:bg-primary/10 selection:text-primary">
            <!-- Ambient Background Effects -->
            <div class="fixed inset-0 pointer-events-none z-0">
                <div class="absolute top-0 right-0 w-3/4 h-3/4 bg-yellow-500/5 rounded-full blur-[120px] opacity-50"></div>
                <div class="absolute bottom-0 left-0 w-1/2 h-1/2 bg-yellow-500/5 rounded-full blur-[100px] opacity-30"></div>
            </div>

            <!-- Compact Header -->
            <header class="sticky top-0 z-40 w-full border-b border-border/40 bg-background/80 backdrop-blur-xl supports-[backdrop-filter]:bg-background/60">
                <div class="container mx-auto flex h-16 items-center justify-between py-4 px-4">
                    <div class="flex items-center gap-4">
                        <Button @click="goBackToTopicSelection" variant="ghost" size="icon" class="h-9 w-9 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted/80">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <div class="flex flex-col">
                            <h1 class="text-sm font-semibold leading-none flex items-center gap-2">
                                Topic Approval
                            </h1>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">Step 3 of 4</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="container mx-auto max-w-5xl py-8 pb-24 px-4 relative z-10 space-y-8">
                <!-- Hero Section -->
                <div class="space-y-4 text-center pb-2">
                    <div class="flex justify-center mb-6">
                        <div class="relative flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-tr from-yellow-500/20 to-orange-500/20 border border-white/10 shadow-[0_0_30px_rgba(234,179,8,0.2)]">
                            <div class="absolute inset-0 rounded-full bg-yellow-500/10 blur-xl"></div>
                            <Clock class="h-8 w-8 text-yellow-400 drop-shadow-[0_0_10px_rgba(250,204,21,0.8)] relative z-10" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl bg-gradient-to-br from-foreground via-foreground to-muted-foreground bg-clip-text text-transparent">
                            Awaiting Supervisor Approval
                        </h2>
                        <p class="mx-auto max-w-[600px] text-muted-foreground text-lg leading-relaxed">
                            Your project topic is ready for review. Share it with your supervisor and update the status below.
                        </p>
                    </div>
                </div>

                <!-- Project Context Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                    <div class="flex flex-col gap-1 p-3 rounded-xl bg-card/50 border border-border/50 backdrop-blur-sm">
                        <span class="text-[10px] uppercase text-muted-foreground font-medium flex items-center gap-1.5">
                            <BookOpen class="h-3 w-3" /> Field
                        </span>
                        <span class="text-sm font-medium truncate" :title="project.field_of_study">{{ project.field_of_study }}</span>
                    </div>
                    <div class="flex flex-col gap-1 p-3 rounded-xl bg-card/50 border border-border/50 backdrop-blur-sm">
                        <span class="text-[10px] uppercase text-muted-foreground font-medium flex items-center gap-1.5">
                            <GraduationCap class="h-3 w-3" /> Level
                        </span>
                        <span class="text-sm font-medium truncate capitalize">{{ project.type }}</span>
                    </div>
                    <div class="flex flex-col gap-1 p-3 rounded-xl bg-card/50 border border-border/50 backdrop-blur-sm">
                        <span class="text-[10px] uppercase text-muted-foreground font-medium flex items-center gap-1.5">
                            <School class="h-3 w-3" /> Institution
                        </span>
                        <span class="text-sm font-medium truncate" :title="project.full_university_name || project.university">{{ project.university }}</span>
                    </div>
                    <div class="flex flex-col gap-1 p-3 rounded-xl bg-card/50 border border-border/50 backdrop-blur-sm">
                        <span class="text-[10px] uppercase text-muted-foreground font-medium flex items-center gap-1.5">
                            <User class="h-3 w-3" /> Supervisor
                        </span>
                        <span class="text-sm font-medium truncate">{{ project.supervisor_name || 'Not assigned' }}</span>
                    </div>
                </div>

                <!-- Selected Topic Card -->
                <Card class="border-border/50 shadow-lg shadow-primary/5 bg-card/80 backdrop-blur-sm overflow-hidden">
                    <CardHeader class="border-b border-border/40 bg-muted/10 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-primary/10 text-primary">
                                <MessageSquare class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-lg">Selected Topic</CardTitle>
                                <CardDescription>The topic currently pending approval</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-6 md:p-8 space-y-6">
                            <div class="space-y-4">
                                <SafeHtmlText
                                    v-if="project.topic"
                                    as="h3"
                                    class="text-2xl font-bold text-foreground leading-tight"
                                    :content="project.topic"
                                />
                                <div class="relative pl-4 border-l-2 border-primary/20" v-if="project.description">
                                    <h4 class="text-sm font-semibold text-muted-foreground mb-2">Description / Scope</h4>
                                    <SafeHtmlText
                                        as="div"
                                        class="prose prose-invert prose-sm sm:prose-base max-w-none text-foreground/80 leading-relaxed"
                                        :content="project.description"
                                    />
                                </div>
                            </div>
                    </CardContent>
                </Card>

                <!-- Action Cards -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Share with Supervisor -->
                    <Card class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:border-blue-500/30 flex flex-col h-full">
                        <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-blue-500/5 group-hover:bg-blue-500/10 transition-colors blur-3xl"></div>
                        <CardHeader class="relative">
                            <CardTitle class="flex items-center gap-2 text-lg group-hover:text-blue-500 transition-colors">
                                <FileText class="h-5 w-5 text-blue-500" />
                                Share with Supervisor
                            </CardTitle>
                            <CardDescription>Download a professional PDF proposal to submit to your supervisor.</CardDescription>
                        </CardHeader>
                        <CardContent class="mt-auto pt-0 relative">
                            <Button @click="exportTopicPdf" :disabled="isExporting" size="lg" variant="outline" class="w-full h-12 border-blue-200/20 hover:bg-blue-500/10 hover:text-blue-500 hover:border-blue-500/50 transition-all font-medium">
                                <Download v-if="!isExporting" class="mr-2 h-4 w-4" />
                                <Clock v-else class="mr-2 h-4 w-4 animate-spin" />
                                {{ isExporting ? 'Generating PDF...' : 'Download Proposal PDF' }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Update Status -->
                    <Card class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:border-green-500/30 flex flex-col h-full">
                        <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-green-500/5 group-hover:bg-green-500/10 transition-colors blur-3xl"></div>
                        <CardHeader class="relative">
                            <CardTitle class="flex items-center gap-2 text-lg group-hover:text-green-500 transition-colors">
                                <ShieldCheck class="h-5 w-5 text-green-600" />
                                Update Status
                            </CardTitle>
                            <CardDescription>Record your supervisor's decision to proceed.</CardDescription>
                        </CardHeader>
                        <CardContent class="mt-auto pt-0 grid grid-cols-2 gap-3 relative">
                            <Button @click="() => showApprovalDialog(true)" :disabled="isSubmitting" class="h-12 bg-green-600 hover:bg-green-700 text-white shadow-lg shadow-green-600/10 transition-all hover:scale-[1.02] font-medium">
                                <CheckCircle class="mr-2 h-4 w-4" />
                                Approved
                            </Button>
                            <Button @click="() => showApprovalDialog(false)" :disabled="isSubmitting" variant="outline" class="h-12 border-red-200/20 text-red-500 hover:bg-red-500/10 hover:border-red-500/50 hover:text-red-600 transition-all hover:scale-[1.02] font-medium">
                                <XCircle class="mr-2 h-4 w-4" />
                                Rejected
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <!-- Confirmation Dialog -->
                <Dialog v-model:open="showConfirmDialog">
                    <DialogContent class="sm:max-w-md border-border/50 shadow-2xl bg-background/95 backdrop-blur-xl">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2 text-xl">
                                <div :class="pendingApproval ? 'bg-green-100 dark:bg-green-500/10 text-green-600 dark:text-green-500' : 'bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-500'" class="p-2 rounded-full">
                                    <CheckCircle v-if="pendingApproval" class="h-5 w-5" />
                                    <XCircle v-else class="h-5 w-5" />
                                </div>
                                {{ pendingApproval ? 'Confirm Approval' : 'Confirm Rejection' }}
                            </DialogTitle>
                            <DialogDescription class="pt-2 text-base text-muted-foreground">
                                {{
                                    pendingApproval
                                        ? `Great news! Confirm that your supervisor has approved this topic. We will proceed to the next stage.`
                                        : `Confirm that your supervisor wants you to change this topic. You will be redirected to select a new one.`
                                }}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2 sm:gap-0 mt-4">
                            <Button @click="showConfirmDialog = false" variant="ghost"> Cancel </Button>
                            <Button @click="confirmApproval" :disabled="isSubmitting" :class="pendingApproval ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white'" class="min-w-[100px]">
                                <Clock v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                                {{ isSubmitting ? 'Processing...' : 'Confirm' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </main>
        </div>
    </AppLayout>
</template>
