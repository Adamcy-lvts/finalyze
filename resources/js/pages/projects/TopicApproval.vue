<!-- /resources/js/pages/projects/TopicApproval.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Clock, Download, FileText, MessageSquare, XCircle } from 'lucide-vue-next';
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
                toast('🎉 Topic Approved!', {
                    description: "Perfect! Now let's set up your AI writing assistant based on your mode preference.",
                });
                // Use the updated slug from the response (slug changes after approval)
                const updatedSlug = data.slug || props.project.slug;
                router.visit(route('projects.guidance', updatedSlug));
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

/**
 * START WRITING DIRECTLY
 * Takes user directly to chapter 1 editor without AI generation
 */
const startWriting = () => {
    toast('Starting Chapter 1', {
        description: 'Opening the editor for manual writing',
    });

    // Navigate directly to chapter 1 in write mode to start writing
    router.visit(
        route('chapters.write', {
            project: props.project.slug,
            chapter: 1,
        }),
    );
};
</script>

<template>
    <AppLayout title="Topic Approval">
        <div class="mx-auto max-w-3xl space-y-8 p-6">
            <!-- Back Navigation -->
            <div class="flex items-center justify-between">
                <Button @click="goBackToTopicSelection" variant="ghost" size="sm" class="text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Topic Selection
                </Button>
            </div>

            <!-- Header -->
            <div class="space-y-3 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-yellow-100">
                    <Clock class="h-8 w-8 text-yellow-600" />
                </div>
                <h1 class="text-3xl font-bold">Awaiting Supervisor Approval</h1>
                <p class="mx-auto max-w-2xl text-muted-foreground">
                    Your project topic is ready for review. Share it with your supervisor and update the status below.
                </p>
            </div>

            <!-- Project Context -->
            <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                <CardHeader>
                    <CardTitle>Project Information</CardTitle>
                    <CardDescription>Current project details for supervisor review</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <p class="mb-1 font-medium text-muted-foreground">Field of Study</p>
                            <p class="font-semibold">{{ project.field_of_study }}</p>
                        </div>
                        <div>
                            <p class="mb-1 font-medium text-muted-foreground">Academic Level</p>
                            <Badge variant="outline" class="capitalize">{{ project.type }}</Badge>
                        </div>
                        <div>
                            <p class="mb-1 font-medium text-muted-foreground">University</p>
                            <p class="text-sm font-semibold">{{ project.university }}</p>
                        </div>
                        <div v-if="project.supervisor_name">
                            <p class="mb-1 font-medium text-muted-foreground">Supervisor</p>
                            <p class="font-semibold">{{ project.supervisor_name }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Selected Topic Display -->
            <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Selected Topic
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-if="project.title" class="rounded-lg bg-muted/30 p-4">
                            <h3 class="mb-2 text-lg font-semibold">{{ project.title }}</h3>
                            <p class="text-sm leading-relaxed">{{ project.topic }}</p>
                        </div>
                        <div v-else class="rounded-lg bg-muted/30 p-4">
                            <p class="text-sm leading-relaxed">{{ project.topic }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Export for Supervisor Review -->
            <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Share with Supervisor
                    </CardTitle>
                    <CardDescription> Download a professional PDF document to share with your supervisor for review and approval. </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button @click="exportTopicPdf" :disabled="isExporting" size="lg" class="w-full">
                        <Download v-if="!isExporting" class="mr-2 h-5 w-5" />
                        <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                        {{ isExporting ? 'Generating PDF...' : 'Download Topic Proposal PDF' }}
                    </Button>
                </CardContent>
            </Card>
           

            <!-- Approval Actions -->
            <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                <CardHeader>
                    <CardTitle>Update Approval Status</CardTitle>
                    <CardDescription> Update the status after supervisor review. </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Approved Button -->
                        <Button
                            @click="() => showApprovalDialog(true)"
                            :disabled="isSubmitting"
                            size="lg"
                            class="flex h-auto flex-col items-center gap-3 border-green-200 bg-green-50 p-6 text-green-700 hover:bg-green-100 hover:text-green-800"
                        >
                            <CheckCircle class="h-8 w-8" />
                            <div class="text-center">
                                <p class="font-semibold">✅ Supervisor Approved</p>
                            </div>
                        </Button>

                        <!-- Needs Revision Button -->
                        <Button
                            @click="() => showApprovalDialog(false)"
                            :disabled="isSubmitting"
                            size="lg"
                            variant="outline"
                            class="flex h-auto flex-col items-center gap-3 border-red-200 bg-red-50 p-6 text-red-700 hover:bg-red-100 hover:text-red-800"
                        >
                            <XCircle class="h-8 w-8" />
                            <div class="text-center">
                                <p class="font-semibold">❌ Needs Changes</p>
                            </div>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Confirmation Dialog -->
            <Dialog v-model:open="showConfirmDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <CheckCircle v-if="pendingApproval" class="h-5 w-5 text-green-600" />
                            <XCircle v-else class="h-5 w-5 text-red-600" />
                            Confirm Supervisor Decision
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                pendingApproval
                                    ? 'Confirm that your supervisor has approved this topic and you are ready to begin writing.'
                                    : 'Confirm that your supervisor wants you to revise this topic and find a new one.'
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex-col gap-2 sm:flex-row">
                        <Button @click="showConfirmDialog = false" variant="outline" class="w-full sm:w-auto"> Cancel </Button>
                        <Button
                            @click="confirmApproval"
                            :disabled="isSubmitting"
                            :class="pendingApproval ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                            class="w-full sm:w-auto"
                        >
                            <Clock v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isSubmitting ? 'Processing...' : pendingApproval ? 'Yes, Approved' : 'Yes, Needs Changes' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
