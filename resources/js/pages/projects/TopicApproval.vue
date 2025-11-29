<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Clock, Download, FileText, MessageSquare, XCircle, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

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
        <div class="min-h-screen bg-gradient-to-b from-background via-background/95 to-muted/20">
            <div class="mx-auto max-w-4xl space-y-10 p-6 pb-20 lg:p-10">
                <!-- Back Navigation -->
                <div class="flex items-center justify-between">
                    <Button 
                        @click="goBackToTopicSelection" 
                        variant="ghost" 
                        size="sm" 
                        class="group text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted/50 group-hover:bg-primary/10 transition-colors mr-2">
                            <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                        </div>
                        Back to Topic Selection
                    </Button>
                </div>

                <!-- Header -->
                <div class="space-y-4 text-center animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-yellow-500/20 to-yellow-500/5 shadow-lg shadow-yellow-500/10 ring-1 ring-yellow-500/20">
                        <Clock class="h-10 w-10 text-yellow-600" />
                    </div>
                    <div class="space-y-2">
                        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl bg-gradient-to-br from-foreground to-foreground/70 bg-clip-text text-transparent">
                            Awaiting Supervisor Approval
                        </h1>
                        <p class="mx-auto max-w-2xl text-lg text-muted-foreground leading-relaxed">
                            Your project topic is ready for review. Share it with your supervisor and update the status below.
                        </p>
                    </div>
                </div>

                <!-- Project Context -->
                <div class="animate-in fade-in slide-in-from-bottom-6 duration-700 delay-100">
                    <Card class="overflow-hidden border-border/40 bg-card/50 backdrop-blur-sm shadow-sm">
                        <div class="grid grid-cols-1 divide-y divide-border/40 md:grid-cols-2 lg:grid-cols-4 md:divide-x md:divide-y-0">
                            <div class="p-6 flex flex-col gap-2">
                                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Field of Study</span>
                                <div class="flex items-center gap-2 font-semibold">
                                    <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                                    {{ project.field_of_study }}
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-2">
                                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Academic Level</span>
                                <div class="flex items-center gap-2 font-semibold">
                                    <div class="h-2 w-2 rounded-full bg-purple-500"></div>
                                    <span class="capitalize">{{ project.type }}</span>
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-2">
                                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">University</span>
                                <div class="flex items-center gap-2 font-semibold">
                                    <div class="h-2 w-2 rounded-full bg-orange-500"></div>
                                    <span class="truncate" :title="project.full_university_name || project.university">
                                        {{ project.full_university_name || project.university }}
                                    </span>
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-2">
                                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Supervisor</span>
                                <div class="flex items-center gap-2 font-semibold">
                                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                    {{ project.supervisor_name || 'Not assigned' }}
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <div class="grid gap-8 md:grid-cols-1 animate-in fade-in slide-in-from-bottom-8 duration-700 delay-200">
                    <!-- Selected Topic Display -->
                    <Card class="border-border/40 shadow-lg shadow-primary/5 overflow-hidden">
                        <CardHeader class="border-b border-border/40 bg-muted/10 pb-6">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-md bg-primary/10 text-primary">
                                    <MessageSquare class="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-xl">Selected Topic</CardTitle>
                                    <CardDescription>The topic currently pending approval</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-6 md:p-8">
                            <div class="rounded-xl bg-muted/30 p-6 border border-border/50">
                                <h3 v-if="project.title" class="mb-3 text-xl font-semibold text-foreground">{{ project.title }}</h3>
                                <p class="text-base leading-relaxed text-muted-foreground">{{ project.topic }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Action Area -->
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Export for Supervisor Review -->
                        <Card class="border-border/40 shadow-md hover:shadow-lg transition-all duration-300 flex flex-col">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-lg">
                                    <FileText class="h-5 w-5 text-blue-500" />
                                    Share with Supervisor
                                </CardTitle>
                                <CardDescription>Download a professional PDF proposal to submit to your supervisor.</CardDescription>
                            </CardHeader>
                            <CardContent class="mt-auto pt-0">
                                <Button 
                                    @click="exportTopicPdf" 
                                    :disabled="isExporting" 
                                    size="lg" 
                                    variant="outline"
                                    class="w-full h-12 border-blue-200 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-300 transition-all"
                                >
                                    <Download v-if="!isExporting" class="mr-2 h-5 w-5" />
                                    <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                                    {{ isExporting ? 'Generating PDF...' : 'Download Proposal PDF' }}
                                </Button>
                            </CardContent>
                        </Card>

                        <!-- Approval Actions -->
                        <Card class="border-border/40 shadow-md hover:shadow-lg transition-all duration-300 flex flex-col">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2 text-lg">
                                    <ShieldCheck class="h-5 w-5 text-green-600" />
                                    Update Status
                                </CardTitle>
                                <CardDescription>Record your supervisor's decision to proceed.</CardDescription>
                            </CardHeader>
                            <CardContent class="mt-auto pt-0 grid grid-cols-2 gap-3">
                                <Button
                                    @click="() => showApprovalDialog(true)"
                                    :disabled="isSubmitting"
                                    class="h-12 bg-green-600 hover:bg-green-700 text-white shadow-md shadow-green-600/20"
                                >
                                    <CheckCircle class="mr-2 h-5 w-5" />
                                    Approved
                                </Button>

                                <Button
                                    @click="() => showApprovalDialog(false)"
                                    :disabled="isSubmitting"
                                    variant="outline"
                                    class="h-12 border-red-200 text-red-700 hover:bg-red-50 hover:border-red-300"
                                >
                                    <XCircle class="mr-2 h-5 w-5" />
                                    Rejected
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Confirmation Dialog -->
                <Dialog v-model:open="showConfirmDialog">
                    <DialogContent class="sm:max-w-md border-border/50 shadow-2xl">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2 text-xl">
                                <div :class="pendingApproval ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'" class="p-2 rounded-full">
                                    <CheckCircle v-if="pendingApproval" class="h-5 w-5" />
                                    <XCircle v-else class="h-5 w-5" />
                                </div>
                                {{ pendingApproval ? 'Confirm Approval' : 'Confirm Rejection' }}
                            </DialogTitle>
                            <DialogDescription class="pt-2 text-base">
                                {{
                                    pendingApproval
                                        ? 'Great news! Confirm that your supervisor has approved this topic. We will proceed to the next stage.'
                                        : 'Confirm that your supervisor wants you to change this topic. You will be redirected to select a new one.'
                                }}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2 sm:gap-0 mt-4">
                            <Button @click="showConfirmDialog = false" variant="ghost"> Cancel </Button>
                            <Button
                                @click="confirmApproval"
                                :disabled="isSubmitting"
                                :class="pendingApproval ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                                class="min-w-[100px]"
                            >
                                <Clock v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                                {{ isSubmitting ? 'Processing...' : 'Confirm' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AppLayout>
</template>
